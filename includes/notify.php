<?php
/**
 * 用户通知系统：支持 Email / Webhook / Bark / Gotify
 */
class Notify
{
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_WEBHOOK = 'webhook';
    const CHANNEL_BARK = 'bark';
    const CHANNEL_GOTIFY = 'gotify';

    /**
     * 发送通知给用户
     * $type: 通知类型（quota/order/system 等），限频按「用户+类型」独立统计
     */
    public static function send($userId, $title, $message, $channel = null, $type = 'general')
    {
        /* 通知频率限制：同一用户同一类型每小时最多 N 条 */
        if (!self::checkRateLimit((int)$userId, $type)) {
            return false;
        }
        $user = User::find($userId);
        if ($user === false) {
            return false;
        }
        $channels = $channel !== null ? [$channel] : self::userChannels($user);
        $ok = false;
        foreach ($channels as $ch) {
            $method = 'send' . ucfirst($ch);
            if (method_exists(self::class, $method)) {
                if (self::$method($user, $title, $message)) {
                    $ok = true;
                }
            }
        }
        return $ok;
    }

    public static function sendEmail($user, $title, $message)
    {
        if (empty($user['email'])) {
            return false;
        }
        $smtp = [
            'host' => setting('smtp_host', ''),
            'port' => (int)setting('smtp_port', '465'),
            'user' => setting('smtp_user', ''),
            'pass' => setting('smtp_pass', ''),
            'encryption' => setting('smtp_encryption', 'ssl'),
            'auth' => setting('smtp_auth', 'auto'),
            'from' => setting('smtp_from', ''),
        ];
        if ($smtp['host'] === '') {
            return false;
        }
        try {
            $mailer = new Mailer($smtp);
            return $mailer->send($user['email'], $title, $message);
        } catch (Throwable $e) {
            write_log('notify email error: ' . $e->getMessage(), 'notify');
            return false;
        }
    }

    public static function sendWebhook($user, $title, $message)
    {
        $url = setting('webhook_url', '');
        if ($url === '') {
            return false;
        }
        $payload = json_encode(['user_id' => (int)$user['id'], 'username' => $user['username'], 'title' => $title, 'message' => $message, 'timestamp' => date('c')], JSON_UNESCAPED_UNICODE);
        $secret = setting('webhook_secret', '');
        $signature = $secret !== '' ? hash_hmac('sha256', $payload, $secret) : '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => array_filter(['Content-Type: application/json', $signature !== '' ? 'X-Signature: ' . $signature : null]),
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code >= 200 && $code < 300;
    }

    public static function sendBark($user, $title, $message)
    {
        $key = setting('bark_key', '');
        if ($key === '') {
            return false;
        }
        $url = 'https://api.day.app/' . urlencode($key) . '/' . urlencode($title) . '/' . urlencode($message);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code >= 200 && $code < 300;
    }

    public static function sendGotify($user, $title, $message)
    {
        $url = setting('gotify_url', '');
        $token = setting('gotify_token', '');
        if ($url === '' || $token === '') {
            return false;
        }
        $payload = json_encode(['title' => $title, 'message' => $message, 'priority' => 5], JSON_UNESCAPED_UNICODE);
        $ch = curl_init(rtrim($url, '/') . '/message');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Gotify-Key: ' . $token],
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code >= 200 && $code < 300;
    }

    private static function userChannels($user)
    {
        $pref = isset($user['notify_prefs']) ? json_decode($user['notify_prefs'], true) : [];
        if (!is_array($pref) || empty($pref)) {
            return [self::CHANNEL_EMAIL];
        }
        return $pref;
    }

    /**
     * 余额告警通知
     */
    public static function quotaAlert($userId)
    {
        $user = User::find($userId);
        if ($user === false) {
            return;
        }
        $threshold = (float)setting('quota_alert_threshold', '0.5');
        if ((float)$user['quota'] <= $threshold) {
            self::send($userId, '余额不足提醒', '您的账户余额已低于 $' . number_format($threshold, 2) . '，请及时充值。当前余额：$' . number_format((float)$user['quota'], 4), null, 'quota');
        }
    }

    /**
     * 通知频率限制：同一用户同一类型每小时最多 N 条
     */
    private static function checkRateLimit($userId, $type)
    {
        $limit = max(1, (int)setting('notify_rate_limit', '60'));
        $key = 'notify:' . (int)$userId . ':' . $type . ':' . date('YmdH');
        if (class_exists('RateLimit') && method_exists('RateLimit', 'check')) {
            return RateLimit::check($key, $limit, 3600);
        }
        return true;
    }
}