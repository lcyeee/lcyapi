<?php
/**
 * OAuth 第三方登录：GitHub（OAuth2 code 交换） + Telegram（Login Widget 签名校验）
 */
class OAuth
{
    const PROVIDERS = ['github', 'telegram'];

    public static function enabled($provider)
    {
        $provider = self::normalize($provider);
        if ($provider === 'github') {
            return setting('oauth_github_enabled', '0') === '1';
        }
        if ($provider === 'telegram') {
            return setting('oauth_telegram_enabled', '0') === '1';
        }
        return false;
    }

    private static function normalize($provider)
    {
        $provider = strtolower(trim((string)$provider));
        return in_array($provider, self::PROVIDERS, true) ? $provider : '';
    }

    /**
     * 构造授权跳转地址
     */
    public static function authorizeUrl($provider)
    {
        $provider = self::normalize($provider);
        if ($provider === 'github') {
            if (!self::enabled('github')) {
                return '';
            }
            $clientId = setting('oauth_github_client_id', '');
            $state = self::state();
            $params = http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => base_url('user/oauth.php?provider=github'),
                'scope' => 'user:email',
                'state' => $state,
            ]);
            return 'https://github.com/login/oauth/authorize?' . $params;
        }
        if ($provider === 'telegram') {
            if (!self::enabled('telegram')) {
                return '';
            }
            $bot = setting('oauth_telegram_bot_username', '');
            $state = self::state();
            $params = http_build_query([
                'bot_id' => self::telegramBotId(),
                'origin' => base_url(),
                'redirect' => base_url('user/oauth.php?provider=telegram'),
                'request_access' => 'write',
                'return_to' => 'lcyapi_oauth_' . $state,
            ]);
            return 'https://oauth.telegram.org/auth?' . $params;
        }
        return '';
    }

    private static function telegramBotId()
    {
        $token = setting('oauth_telegram_bot_token', '');
        if (preg_match('/^(\d+):/', $token, $m)) {
            return (int)$m[1];
        }
        return 0;
    }

    private static function state()
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;
        return $state;
    }

    /**
     * 校验回调 state，防止 CSRF
     */
    public static function verifyState()
    {
        $stored = isset($_SESSION['oauth_state']) ? $_SESSION['oauth_state'] : '';
        $given = isset($_GET['state']) ? (string)$_GET['state'] : '';
        unset($_SESSION['oauth_state']);
        return $stored !== '' && $given !== '' && hash_equals($stored, $given);
    }

    /**
     * 处理 GitHub 回调：换取 token 并拉取用户信息
     * 返回 ['ok'=>true,'provider','openid','username','avatar','email'] 或 ['ok'=>false,'msg']
     */
    public static function handleCallback($provider)
    {
        $provider = self::normalize($provider);
        if ($provider === 'github') {
            return self::githubCallback();
        }
        if ($provider === 'telegram') {
            return self::telegramCallback();
        }
        return ['ok' => false, 'msg' => '不支持的登录方式'];
    }

    private static function githubCallback()
    {
        $code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
        if ($code === '') {
            return ['ok' => false, 'msg' => '授权失败（缺少 code）'];
        }
        $clientId = setting('oauth_github_client_id', '');
        $clientSecret = setting('oauth_github_client_secret', '');
        $resp = self::httpPostJson('https://github.com/login/oauth/access_token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => base_url('user/oauth.php?provider=github'),
        ], ['Accept: application/json']);
        if (!$resp['ok'] || empty($resp['json']['access_token'])) {
            return ['ok' => false, 'msg' => 'GitHub 令牌换取失败'];
        }
        $accessToken = $resp['json']['access_token'];
        $userResp = self::httpGetJson('https://api.github.com/user', ['Authorization: Bearer ' . $accessToken, 'Accept: application/vnd.github+json', 'User-Agent: lcyapi']);
        if (!$userResp['ok'] || empty($userResp['json']['id'])) {
            return ['ok' => false, 'msg' => 'GitHub 用户信息获取失败'];
        }
        $u = $userResp['json'];
        $email = '';
        if (!empty($u['email'])) {
            $email = $u['email'];
        }
        return [
            'ok' => true,
            'provider' => 'github',
            'openid' => (string)$u['id'],
            'username' => isset($u['login']) ? mb_substr($u['login'], 0, 50) : '',
            'avatar' => isset($u['avatar_url']) ? $u['avatar_url'] : '',
            'email' => $email,
        ];
    }

    private static function telegramCallback()
    {
        $user = isset($_GET['user']) ? (string)$_GET['user'] : '';
        if ($user === '') {
            return ['ok' => false, 'msg' => 'Telegram 授权失败（缺少 user 数据）'];
        }
        $data = json_decode($user, true);
        if (!is_array($data) || empty($data['id'])) {
            return ['ok' => false, 'msg' => 'Telegram 用户数据无效'];
        }
        /* 校验签名：hash = HMAC_SHA256(bot_token, 按字段升序拼接的 data_check_string) */
        $token = setting('oauth_telegram_bot_token', '');
        if ($token === '') {
            return ['ok' => false, 'msg' => 'Telegram Bot Token 未配置'];
        }
        $hash = isset($data['hash']) ? (string)$data['hash'] : '';
        unset($data['hash']);
        ksort($data);
        $checkString = '';
        foreach ($data as $k => $v) {
            $checkString .= $k . '=' . $v . "\n";
        }
        $checkString = rtrim($checkString, "\n");
        $secretKey = hash('sha256', $token, true);
        $computed = hash_hmac('sha256', $checkString, $secretKey);
        if (!hash_equals($computed, $hash)) {
            return ['ok' => false, 'msg' => 'Telegram 签名校验失败'];
        }
        return [
            'ok' => true,
            'provider' => 'telegram',
            'openid' => (string)$data['id'],
            'username' => isset($data['username']) ? mb_substr($data['username'], 0, 50) : (isset($data['first_name']) ? mb_substr($data['first_name'], 0, 50) : ''),
            'avatar' => '',
            'email' => '',
        ];
    }

    /**
     * 第三方身份登录/绑定统一入口
     * $info 来自 handleCallback；$bindUserId 非空表示「绑定到当前账号」
     * 未绑定且当前未登录时自动创建账号并登录
     */
    public static function loginWithIdentity($info, $bindUserId = null)
    {
        $provider = $info['provider'];
        $openid = $info['openid'];
        $existing = DB::fetch('SELECT * FROM oauth_bindings WHERE provider = ? AND openid = ?', [$provider, $openid]);
        if ($existing !== false && $existing['user_id'] !== null) {
            /* 已绑定：直接登录该用户（若当前在登录中且是绑定操作，则提示已绑定其他账号） */
            $user = User::find((int)$existing['user_id']);
            if ($user === false || (int)$user['status'] !== 1) {
                return ['ok' => false, 'msg' => '绑定账号不存在或已被禁用'];
            }
            if ($bindUserId !== null && (int)$bindUserId !== (int)$user['id']) {
                return ['ok' => false, 'msg' => '该第三方账号已绑定其他用户'];
            }
            self::oauthSession($user);
            return ['ok' => true, 'new' => false, 'user_id' => (int)$user['id']];
        }
        if ($bindUserId !== null) {
            /* 登录态下绑定新第三方账号 */
            $user = User::find((int)$bindUserId);
            if ($user === false) {
                return ['ok' => false, 'msg' => '当前用户不存在'];
            }
            DB::insert('oauth_bindings', [
                'user_id' => (int)$bindUserId,
                'provider' => $provider,
                'openid' => $openid,
                'username' => $info['username'] !== '' ? $info['username'] : null,
                'avatar' => $info['avatar'] !== '' ? $info['avatar'] : null,
            ]);
            audit_log('user_bind_oauth', 'user#' . $bindUserId, $provider);
            return ['ok' => true, 'new' => false, 'user_id' => (int)$bindUserId, 'bound' => true];
        }
        if (!setting('register_enabled', '1')) {
            return ['ok' => false, 'msg' => '注册已关闭，无法通过第三方登录创建账号'];
        }
        /* 自动创建账号 */
        $username = $info['username'] !== '' ? $info['username'] : $provider . '_' . substr($openid, 0, 8);
        $base = $username;
        $i = 1;
        while (User::findByUsername($username) !== false) {
            $username = $base . '_' . $i++;
            if ($i > 50) {
                $username = $provider . '_' . random_string(6);
                break;
            }
        }
        $userId = User::create([
            'username' => $username,
            'email' => !empty($info['email']) ? $info['email'] : null,
            'password' => Auth::hashPassword(random_string(24)),
            'nickname' => $info['username'] !== '' ? $info['username'] : null,
            'avatar' => $info['avatar'] !== '' ? $info['avatar'] : null,
            'role' => 'user',
            'quota' => (float)setting('default_quota', config('site.default_quota', 0)),
            'status' => 1,
            'group' => 'default',
        ]);
        if ($userId === false || $userId === 0) {
            return ['ok' => false, 'msg' => '自动创建账号失败'];
        }
        DB::insert('oauth_bindings', [
            'user_id' => $userId,
            'provider' => $provider,
            'openid' => $openid,
            'username' => $info['username'] !== '' ? $info['username'] : null,
            'avatar' => $info['avatar'] !== '' ? $info['avatar'] : null,
        ]);
        self::oauthSession(User::find($userId));
        return ['ok' => true, 'new' => true, 'user_id' => $userId];
    }

    private static function oauthSession($user)
    {
        session_regenerate_id(true);
        $_SESSION[Auth::SESSION_KEY] = (int)$user['id'];
        Auth::recordSession((int)$user['id']);
        User::updateLastLogin((int)$user['id'], client_ip());
        $GLOBALS['__current_user'] = $user;
    }

    public static function unbind($userId, $provider)
    {
        $provider = self::normalize($provider);
        if ($provider === '') {
            return false;
        }
        $remaining = DB::count('oauth_bindings', 'user_id = ? AND provider != ?', [(int)$userId, $provider]);
        $user = User::find((int)$userId);
        $hasPassword = $user !== false && !empty($user['password']) && !preg_match('/^[a-f0-9]{48}$/', $user['password']);
        if ($remaining <= 0 && !$hasPassword) {
            return false;
        }
        return DB::delete('oauth_bindings', 'user_id = ? AND provider = ?', [(int)$userId, $provider]) > 0;
    }

    private static function httpPostJson($url, $data, $headers = [])
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers),
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string)$body, true);
        return ['ok' => $code >= 200 && $code < 300, 'http_code' => $code, 'body' => (string)$body, 'json' => is_array($json) ? $json : []];
    }

    private static function httpGetJson($url, $headers = [])
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string)$body, true);
        return ['ok' => $code >= 200 && $code < 300, 'http_code' => $code, 'body' => (string)$body, 'json' => is_array($json) ? $json : []];
    }
}
