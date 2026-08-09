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
        $GLOBALS['__current_user'] = $user;
        return true;
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
        $_SESSION[self::SESSION_KEY] = (int)$user['id'];
        User::updateLastLogin((int)$user['id'], client_ip());
        self::recordLoginLog((int)$user['id'], $username, true, '');
        $GLOBALS['__current_user'] = User::find((int)$user['id']);
        return ['ok' => true];
    }

    public static function logout()
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        $GLOBALS['__current_user'] = null;
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
        if ($email !== '' && Validator::make(['email' => $email], ['email' => 'email|unique:users,email'])->fails()) {
            return ['ok' => false, 'msg' => '邮箱格式不正确或已被使用'];
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
        RateLimit::recordLogin($username, true);
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = (int)$userId;
        $GLOBALS['__current_user'] = User::find((int)$userId);
        return ['ok' => true, 'user_id' => (int)$userId];
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
        DB::begin();
        try {
            DB::insert('checkins', ['user_id' => (int)$userId, 'checkin_date' => $today, 'reward' => $reward]);
            if ($reward > 0) {
                DB::query('UPDATE users SET quota = quota + ?, total_quota = total_quota + ? WHERE id = ?', [$reward, $reward, (int)$userId]);
                DB::insert('recharge_logs', [
                    'user_id' => (int)$userId,
                    'amount' => $reward,
                    'type' => 'checkin',
                    'remark' => '每日签到奖励',
                ]);
            }
            DB::commit();
            return ['ok' => true, 'msg' => $reward > 0 ? '签到成功，奖励 $' . number_format($reward, 4) : '签到成功'];
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