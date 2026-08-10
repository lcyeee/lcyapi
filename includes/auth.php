<?php
class Auth
{
    const SESSION_KEY = 'lcyapi_user_id';

    public static function check()
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }
        $user = User::find((int)$_SESSION[self::SESSION_KEY]);
        if ($user === false || (int)$user['status'] !== 1) {
            unset($_SESSION[self::SESSION_KEY]);
            $GLOBALS['__current_user'] = null;
            return false;
        }
        /* 会话管理：会话被撤销后强制下线 */
        if (!self::sessionAlive((int)$user['id'])) {
            self::logout();
            return false;
        }
        $GLOBALS['__current_user'] = $user;
        return true;
    }

    /**
     * 校验当前会话是否仍有效（user_sessions 表中存在且未被撤销）
     */
    public static function sessionAlive($userId)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return true;
        }
        try {
            $hasTable = DB::value('SELECT id FROM user_sessions LIMIT 1');
        } catch (Throwable $e) {
            return true;
        }
        if ($hasTable === null) {
            return true;
        }
        $sidHash = hash('sha256', session_id());
        $row = DB::fetch('SELECT id FROM user_sessions WHERE sid_hash = ? AND user_id = ?', [$sidHash, (int)$userId]);
        if ($row === false) {
            return false;
        }
        DB::query('UPDATE user_sessions SET last_active_at = NOW() WHERE sid_hash = ?', [$sidHash]);
        return true;
    }

    /**
     * 记录本次登录会话（user_sessions），供个人中心展示与撤销
     */
    public static function recordSession($userId)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        try {
            /* 会话数量限制：超过则删除最旧的非当前会话 */
            $maxSessions = (int)setting('max_active_sessions', '0');
            if ($maxSessions > 0) {
                $count = (int)DB::value('SELECT COUNT(*) FROM user_sessions WHERE user_id = ?', [(int)$userId]);
                if ($count >= $maxSessions) {
                    DB::query('DELETE FROM user_sessions WHERE user_id = ? ORDER BY last_active_at ASC LIMIT ' . ($count - $maxSessions + 1), [(int)$userId]);
                }
            }
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '';
            DB::query(
                'INSERT INTO user_sessions (user_id, sid_hash, ip, user_agent, device, last_active_at) VALUES (?, ?, ?, ?, ?, NOW()) '
                . 'ON DUPLICATE KEY UPDATE last_active_at = NOW(), ip = VALUES(ip), user_agent = VALUES(user_agent), device = VALUES(device)',
                [(int)$userId, hash('sha256', session_id()), client_ip(), $ua, self::detectDevice($ua)]
            );
        } catch (Throwable $ex) {
            write_log('record session error: ' . $ex->getMessage());
        }
    }

    private static function detectDevice($ua)
    {
        $ua = (string)$ua;
        if (stripos($ua, 'Windows') !== false) {
            $device = 'Windows';
        } elseif (stripos($ua, 'Macintosh') !== false || stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
            $device = 'Apple';
        } elseif (stripos($ua, 'Android') !== false) {
            $device = 'Android';
        } elseif (stripos($ua, 'Linux') !== false) {
            $device = 'Linux';
        } else {
            $device = '未知设备';
        }
        if (preg_match('/(Chrome|Firefox|Safari|Edge|Edg\/|Opera|MSIE|Trident)[\/ ]([\d.]+)/i', $ua, $m)) {
            $browser = $m[1] === 'Edg/' ? 'Edge' : $m[1];
            $device .= ' · ' . $browser;
        }
        return mb_substr($device, 0, 50);
    }

    public static function user()
    {
        if (!isset($GLOBALS['__current_user'])) {
            if (!self::check()) {
                return null;
            }
        }
        return $GLOBALS['__current_user'];
    }

    public static function id()
    {
        $user = self::user();
        return $user ? (int)$user['id'] : null;
    }

    public static function isAdmin()
    {
        $user = self::user();
        return $user !== null && $user['role'] === 'admin';
    }

    public static function login($username, $password)
    {
        $check = RateLimit::checkLogin($username);
        if (!$check['allowed']) {
            self::recordLoginLog(null, $username, false, '登录限流');
            return ['ok' => false, 'reason' => '登录尝试次数过多，请 ' . ceil($check['retry_after'] / 60) . ' 分钟后再试'];
        }
        $user = User::findByUsername($username);
        if ($user === false) {
            RateLimit::recordLogin($username, false);
            self::recordLoginLog(null, $username, false, '用户不存在');
            return ['ok' => false, 'reason' => '用户名或密码错误'];
        }
        if ((int)$user['status'] !== 1) {
            RateLimit::recordLogin($username, false);
            self::recordLoginLog((int)$user['id'], $username, false, '账号已禁用');
            return ['ok' => false, 'reason' => '账号已被禁用'];
        }
        if (!password_verify($password, $user['password'])) {
            RateLimit::recordLogin($username, false);
            self::recordLoginLog((int)$user['id'], $username, false, '密码错误');
            return ['ok' => false, 'reason' => '用户名或密码错误'];
        }
        RateLimit::recordLogin($username, true);
        session_regenerate_id(true);
        if ((int)$user['totp_enabled'] === 1) {
            $_SESSION['lcyapi_2fa_pending'] = (int)$user['id'];
            return ['ok' => true, 'twofa' => true];
        }
        return self::completeLogin((int)$user['id'], $username);
    }

    /**
     * 完成登录：写入登录态 + 更新最后登录 + 记录会话
     */
    private static function completeLogin($userId, $username)
    {
        unset($_SESSION['lcyapi_2fa_pending']);
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = $userId;
        User::updateLastLogin($userId, client_ip());
        self::recordLoginLog($userId, $username, true, '');
        self::recordSession($userId);
        $GLOBALS['__current_user'] = User::find($userId);
        return ['ok' => true];
    }

    /**
     * 2FA 二次校验：TOTP 6 位码或一次性备份码
     */
    public static function verify2fa($userId, $code)
    {
        $user = User::find((int)$userId);
        if ($user === false) {
            return ['ok' => false, 'msg' => '用户不存在'];
        }
        /* 2FA 锁定检测 */
        if (!empty($user['totp_locked_until']) && strtotime($user['totp_locked_until']) > time()) {
            $remaining = strtotime($user['totp_locked_until']) - time();
            return ['ok' => false, 'msg' => '2FA 已锁定，请 ' . ceil($remaining / 60) . ' 分钟后再试'];
        }
        if (empty($_SESSION['lcyapi_2fa_pending']) || (int)$_SESSION['lcyapi_2fa_pending'] !== (int)$userId) {
            return ['ok' => false, 'msg' => '登录状态已失效，请重新登录'];
        }
        $code = trim((string)$code);
        if ($code === '') {
            return ['ok' => false, 'msg' => '请输入验证码'];
        }
        $ok = TOTP::verify((string)$user['totp_secret'], $code) || TOTP::consumeBackupCode((int)$userId, $code);
        if ($ok) {
            /* 成功：重置失败计数 */
            DB::update('users', ['totp_fail_count' => 0, 'totp_locked_until' => null], 'id = ?', [(int)$userId]);
            /* 递增 auth_version（安全事件） */
            DB::query('UPDATE users SET auth_version = auth_version + 1 WHERE id = ?', [(int)$userId]);
            return self::completeLogin((int)$userId, $user['username']);
        }
        /* 失败：递增计数，达到阈值锁定 */
        $maxFails = max(1, (int)setting('totp_max_fails', '5'));
        $lockMinutes = max(1, (int)setting('totp_lock_minutes', '5'));
        $newFailCount = (int)$user['totp_fail_count'] + 1;
        if ($newFailCount >= $maxFails) {
            DB::update('users', ['totp_fail_count' => $newFailCount, 'totp_locked_until' => date('Y-m-d H:i:s', time() + $lockMinutes * 60)], 'id = ?', [(int)$userId]);
            write_log("2FA locked for user #{$userId} after {$newFailCount} failed attempts", 'auth');
        } else {
            DB::update('users', ['totp_fail_count' => $newFailCount], 'id = ?', [(int)$userId]);
        }
        self::recordLoginLog((int)$user['id'], $user['username'], false, '2FA 验证码错误');
        return ['ok' => false, 'msg' => '验证码错误，请重试' . ($newFailCount >= $maxFails ? '（已锁定 ' . $lockMinutes . ' 分钟）' : '（剩余 ' . ($maxFails - $newFailCount) . ' 次机会）')];
    }

    public static function logout()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            try {
                DB::delete('user_sessions', 'sid_hash = ?', [hash('sha256', session_id())]);
            } catch (Throwable $ex) {
                write_log('logout session error: ' . $ex->getMessage());
            }
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        $GLOBALS['__current_user'] = null;
    }

    /**
     * 撤销指定会话（不能撤销当前会话）
     */
    public static function revokeSession($sessionId, $userId)
    {
        $current = hash('sha256', session_id());
        $row = DB::fetch('SELECT sid_hash FROM user_sessions WHERE id = ? AND user_id = ?', [(int)$sessionId, (int)$userId]);
        if ($row === false) {
            return ['ok' => false, 'msg' => '会话不存在'];
        }
        if (hash_equals($current, $row['sid_hash'])) {
            return ['ok' => false, 'msg' => '不能撤销当前会话'];
        }
        DB::delete('user_sessions', 'id = ?', [(int)$sessionId]);
        return ['ok' => true];
    }

    /**
     * 撤销用户全部其他会话（保留当前）
     */
    public static function revokeAllSessions($userId)
    {
        DB::delete('user_sessions', 'user_id = ? AND sid_hash != ?', [(int)$userId, hash('sha256', session_id())]);
        return ['ok' => true];
    }

    public static function register($data)
    {
        if (!setting('register_enabled', '1')) {
            return ['ok' => false, 'msg' => '注册已关闭'];
        }
        $username = isset($data['username']) ? trim($data['username']) : '';
        $email = isset($data['email']) ? trim($data['email']) : '';
        $password = isset($data['password']) ? $data['password'] : '';

        $validator = Validator::make(
            ['username' => $username, 'email' => $email, 'password' => $password],
            [
                'username' => 'required|username|unique:users,username',
                'password' => 'required|min:6|max:64',
            ]
        );
        if ($validator->fails()) {
            return ['ok' => false, 'msg' => $validator->firstError()];
        }
        /* 要求邮箱验证时，邮箱必填 */
        $verifyRequired = setting('email_verify_required', '0') === '1';
        if ($verifyRequired && $email === '') {
            return ['ok' => false, 'msg' => '本站需要验证邮箱，请填写邮箱'];
        }
        if ($email !== '' && Validator::make(['email' => $email], ['email' => 'email|unique:users,email'])->fails()) {
            return ['ok' => false, 'msg' => '邮箱格式不正确或已被使用'];
        }
        if ($email !== '') {
            /* 邮箱域名白名单 */
            $domainRestrict = setting('email_domain_restriction', '0') === '1';
            $domains = setting('email_domain_whitelist', '');
            if ($domainRestrict && $domains !== '') {
                $host = strtolower((string)parse_url('mailto:' . $email, PHP_URL_HOST));
                $allowed = array_map('trim', explode(',', str_replace('，', ',', $domains)));
                $match = false;
                foreach ($allowed as $d) {
                    $d = strtolower(trim($d, " \t\n\r\0\x0B."));
                    if ($d !== '' && ($host === $d || substr($host, -strlen($d) - 1) === '.' . $d)) {
                        $match = true;
                        break;
                    }
                }
                if (!$match) {
                    return ['ok' => false, 'msg' => '该邮箱域名不在允许注册的范围内'];
                }
            }
            /* 邮箱别名限制（禁止 + 和 . 别名混淆） */
            if (setting('email_alias_restriction', '0') === '1') {
                $local = strstr($email, '@', true);
                if ($local !== false && (strpos($local, '+') !== false || strpos($local, '.') !== false)) {
                    return ['ok' => false, 'msg' => '不允许使用含 + 或 . 的邮箱别名'];
                }
            }
        }
        $userId = User::create([
            'username' => $username,
            'email' => $email !== '' ? $email : null,
            'password' => self::hashPassword($password),
            'role' => 'user',
            'quota' => (float)setting('default_quota', config('site.default_quota', 0)),
            'status' => 1,
            'group' => 'default',
        ]);
        if ($userId === false || $userId === 0) {
            return ['ok' => false, 'msg' => '注册失败，请稍后重试'];
        }
        /* 邀请关系绑定与奖励发放 */
        if (setting('aff_enabled', '0') === '1' && isset($data['aff_code']) && trim($data['aff_code']) !== '') {
            self::bindAffiliate((int)$userId, trim($data['aff_code']));
        }
        /* 需要邮箱验证时自动发送验证码 */
        if ($verifyRequired && $email !== '') {
            self::sendVerificationCodeByEmail($email, (int)$userId);
        }
        RateLimit::recordLogin($username, true);
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = (int)$userId;
        self::recordSession((int)$userId);
        $GLOBALS['__current_user'] = User::find((int)$userId);
        return ['ok' => true, 'user_id' => (int)$userId];
    }

    /**
     * 给指定邮箱发送「验证邮箱」验证码（type=email）
     */
    public static function sendVerificationCodeByEmail($email, $userId = null)
    {
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'msg' => '邮箱格式不正确'];
        }
        if ($userId !== null) {
            $user = User::find((int)$userId);
            if ($user !== false && strtolower($user['email']) === strtolower($email) && (int)$user['email_verified'] === 1) {
                return ['ok' => false, 'msg' => '该邮箱已验证'];
            }
        }
        return Mailer::sendVerificationCode($email, Mailer::TYPE_EMAIL);
    }

    /**
     * 校验邮箱验证码并置 email_verified=1
     */
    public static function verifyEmail($userId, $code)
    {
        $user = User::find((int)$userId);
        if ($user === false || empty($user['email'])) {
            return ['ok' => false, 'msg' => '用户不存在或未绑定邮箱'];
        }
        if (Mailer::verifyCode($user['email'], Mailer::TYPE_EMAIL, $code)) {
            User::update((int)$userId, ['email_verified' => 1]);
            return ['ok' => true];
        }
        return ['ok' => false, 'msg' => '验证码错误或已过期'];
    }

    /**
     * 找回密码第一步：发送重置验证码（type=forgot）
     */
    public static function sendForgotCode($email)
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'msg' => '邮箱格式不正确'];
        }
        $user = User::findByEmail($email);
        if ($user === false) {
            return ['ok' => false, 'msg' => '该邮箱未注册'];
        }
        return Mailer::sendVerificationCode($email, Mailer::TYPE_FORGOT);
    }

    /**
     * 找回密码第二步：校验验证码并重置密码
     */
    public static function resetPassword($email, $code, $newPassword)
    {
        $email = strtolower(trim($email));
        if (strlen($newPassword) < 6 || strlen($newPassword) > 64) {
            return ['ok' => false, 'msg' => '新密码长度需在 6-64 位之间'];
        }
        $user = User::findByEmail($email);
        if ($user === false) {
            return ['ok' => false, 'msg' => '该邮箱未注册'];
        }
        if (!Mailer::verifyCode($email, Mailer::TYPE_FORGOT, $code)) {
            return ['ok' => false, 'msg' => '验证码错误或已过期'];
        }
        User::update((int)$user['id'], ['password' => self::hashPassword($newPassword)]);
        /* 重置密码后注销全部登录态（会话表 + 当前 session） */
        if (DB::value('SELECT id FROM user_sessions LIMIT 1') !== null) {
            DB::delete('user_sessions', 'user_id = ?', [(int)$user['id']]);
        }
        if (!empty($_SESSION) && isset($_SESSION[self::SESSION_KEY]) && (int)$_SESSION[self::SESSION_KEY] === (int)$user['id']) {
            self::logout();
        }
        return ['ok' => true];
    }


    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * 绑定邀请关系：新用户记录邀请人，邀请人获得待转奖励
     */
    public static function bindAffiliate($userId, $affCode)
    {
        try {
            $inviter = User::findByAffCode($affCode);
            if ($inviter === false || (int)$inviter['id'] === (int)$userId || (int)$inviter['status'] !== 1) {
                return false;
            }
            DB::update('users', ['aff_by' => (int)$inviter['id']], 'id = ?', [(int)$userId]);
            $reward = (float)setting('aff_quota', '0');
            if ($reward > 0) {
                DB::query('UPDATE users SET aff_quota = aff_quota + ?, aff_history_quota = aff_history_quota + ? WHERE id = ?', [$reward, $reward, (int)$inviter['id']]);
            }
            return true;
        } catch (Exception $ex) {
            write_log('bindAffiliate error: ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * 将邀请待转收益转入余额
     */
    public static function transferAffQuota($userId)
    {
        $user = User::find((int)$userId);
        if ($user === false || (float)$user['aff_quota'] <= 0) {
            return ['ok' => false, 'msg' => '没有可转入的邀请收益'];
        }
        $amount = (float)$user['aff_quota'];
        DB::begin();
        try {
            DB::query('UPDATE users SET aff_quota = 0 WHERE id = ?', [(int)$userId]);
            DB::query('UPDATE users SET quota = quota + ?, total_quota = total_quota + ? WHERE id = ?', [$amount, $amount, (int)$userId]);
            DB::insert('recharge_logs', [
                'user_id' => (int)$userId,
                'amount' => $amount,
                'type' => 'aff',
                'remark' => '邀请收益转入',
            ]);
            DB::commit();
            return ['ok' => true, 'msg' => '已转入 $' . number_format($amount, 4)];
        } catch (Exception $ex) {
            DB::rollback();
            write_log('transferAffQuota error: ' . $ex->getMessage());
            return ['ok' => false, 'msg' => '转入失败，请重试'];
        }
    }

    /**
     * 每日签到：同一用户同一天仅一次
     */
    public static function checkin($userId)
    {
        if (setting('checkin_enabled', '0') !== '1') {
            return ['ok' => false, 'msg' => '签到功能未开启'];
        }
        $today = date('Y-m-d');
        $exists = DB::value('SELECT id FROM checkins WHERE user_id = ? AND checkin_date = ?', [(int)$userId, $today]);
        if ($exists !== null) {
            return ['ok' => false, 'msg' => '今天已签到'];
        }
        $reward = (float)setting('checkin_reward', '0');
        /* 随机签到奖励：开启后每天在 min~max 范围内随机（基础奖励仍参与递增） */
        if (setting('checkin_random_reward', '0') === '1') {
            $min = max(0, (float)setting('checkin_reward_min', '0'));
            $max = max(0, (float)setting('checkin_reward_max', '0'));
            if ($max > $min) {
                $reward = round($min + mt_rand() / mt_getrandmax() * ($max - $min), 6);
            }
        }
        /* 连续签到：昨天签过则 +1，否则重新计 1；奖励递增按每日加成，封顶天数可配置 */
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $streakBase = (int)DB::value('SELECT checkin_streak FROM users WHERE id = ?', [(int)$userId]);
        $prevDay = DB::value('SELECT id FROM checkins WHERE user_id = ? AND checkin_date = ?', [(int)$userId, $yesterday]);
        $streak = $prevDay !== null ? $streakBase + 1 : 1;
        $bonusStep = (float)setting('checkin_bonus_step', '0');
        $bonusCap = max(1, (int)setting('checkin_bonus_max_days', '7'));
        $bonusDays = max(0, min($streak - 1, $bonusCap - 1));
        $reward = round($reward + $bonusDays * $bonusStep, 6);
        DB::begin();
        try {
            DB::insert('checkins', ['user_id' => (int)$userId, 'checkin_date' => $today, 'reward' => $reward]);
            DB::query('UPDATE users SET quota = quota + ?, total_quota = total_quota + ?, checkin_streak = ? WHERE id = ?', [$reward, $reward, $streak, (int)$userId]);
            if ($reward > 0) {
                DB::insert('recharge_logs', [
                    'user_id' => (int)$userId,
                    'amount' => $reward,
                    'type' => 'checkin',
                    'remark' => '每日签到奖励（连续 ' . $streak . ' 天）',
                ]);
            }
            DB::commit();
            return ['ok' => true, 'msg' => $reward > 0 ? '签到成功，奖励 $' . number_format($reward, 4) . '（连续 ' . $streak . ' 天）' : '签到成功'];
        } catch (Exception $ex) {
            DB::rollback();
            /* 唯一索引冲突 = 并发重复签到 */
            if (strpos($ex->getMessage(), 'Duplicate') !== false) {
                return ['ok' => false, 'msg' => '今天已签到'];
            }
            write_log('checkin error: ' . $ex->getMessage());
            return ['ok' => false, 'msg' => '签到失败，请重试'];
        }
    }

    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    public static function changePassword($userId, $oldPassword, $newPassword)
    {
        $user = User::find($userId);
        if ($user === false) {
            return ['ok' => false, 'msg' => '用户不存在'];
        }
        if (!password_verify($oldPassword, $user['password'])) {
            return ['ok' => false, 'msg' => '原密码不正确'];
        }
        if (strlen($newPassword) < 6 || strlen($newPassword) > 64) {
            return ['ok' => false, 'msg' => '新密码长度需在 6-64 位之间'];
        }
        User::update($userId, ['password' => self::hashPassword($newPassword)]);
        return ['ok' => true];
    }

    public static function requireLogin()
    {
        if (!self::check()) {
            redirect(base_url('user/login.php'));
        }
    }

    public static function requireAdmin()
    {
        if (!self::check() || !self::isAdmin()) {
            redirect(base_url('admin/login.php'));
        }
    }

    public static function guestOnly()
    {
        if (self::check()) {
            redirect(base_url('user/index.php'));
        }
    }

    private static function recordLoginLog($userId, $username, $status, $reason)
    {
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
        try {
            DB::insert('login_logs', [
                'user_id' => $userId !== null ? (int)$userId : null,
                'username' => mb_substr($username, 0, 50),
                'ip' => client_ip(),
                'user_agent' => $userAgent,
                'status' => $status ? 1 : 0,
                'reason' => $reason !== '' ? mb_substr($reason, 0, 100) : null,
            ]);
        } catch (Exception $ex) {
            write_log('record login log error: ' . $ex->getMessage());
        }
    }
}