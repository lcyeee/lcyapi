<?php
class RateLimit
{
    public static function check($key, $limit = 60, $window = 60)
    {
        $count = self::increment($key, $window);
        return $count <= $limit;
    }

    public static function increment($key, $window = 60)
    {
        $file = CACHE_PATH . '/ratelimit_' . sha1($key) . '.json';
        $count = 0;
        if (is_file($file)) {
            $data = @json_decode(@file_get_contents($file), true);
            if (is_array($data)) {
                if (time() - $data['time'] > $window) {
                    $data = ['count' => 0, 'time' => time()];
                } else {
                    $data['count']++;
                    $count = $data['count'];
                }
                $data = ['count' => $count, 'time' => $data['time']];
            }
        }
        if (!isset($data)) {
            $data = ['count' => 1, 'time' => time()];
            $count = 1;
        }
        @file_put_contents($file, json_encode($data), LOCK_EX);
        return $count;
    }

    public static function getRemaining($key, $limit = 60, $window = 60)
    {
        $file = CACHE_PATH . '/ratelimit_' . sha1($key) . '.json';
        if (!is_file($file)) {
            return $limit;
        }
        $data = @json_decode(@file_get_contents($file), true);
        if (!is_array($data) || (time() - $data['time']) > $window) {
            return $limit;
        }
        return max(0, $limit - $data['count']);
    }

    public static function reset($key)
    {
        $file = CACHE_PATH . '/ratelimit_' . sha1($key) . '.json';
        return is_file($file) ? @unlink($file) : true;
    }

    public static function checkLogin($username)
    {
        $key = 'login:' . strtolower($username) . ':' . client_ip();
        $file = CACHE_PATH . '/ratelimit_' . sha1($key) . '.json';
        $limit = (int)config('security.login_attempts', 5);
        $window = (int)config('security.login_lock_time', 900);
        if (!is_file($file)) {
            return ['allowed' => true, 'retry_after' => 0];
        }
        $data = @json_decode(@file_get_contents($file), true);
        if (!is_array($data)) {
            return ['allowed' => true, 'retry_after' => 0];
        }
        $remaining = $window - (time() - $data['time']);
        if ($remaining <= 0) {
            self::reset($key);
            return ['allowed' => true, 'retry_after' => 0];
        }
        return ['allowed' => $data['count'] < $limit, 'retry_after' => $remaining];
    }

    public static function recordLogin($username, $success)
    {
        $key = 'login:' . strtolower($username) . ':' . client_ip();
        $file = CACHE_PATH . '/ratelimit_' . sha1($key) . '.json';
        $window = (int)config('security.login_lock_time', 900);
        $data = ['count' => 0, 'time' => time()];
        if (is_file($file)) {
            $old = @json_decode(@file_get_contents($file), true);
            if (is_array($old) && (time() - $old['time']) <= $window) {
                $data = ['count' => $old['count'] + 1, 'time' => $old['time']];
            }
        }
        if ($success) {
            @unlink($file);
        } else {
            @file_put_contents($file, json_encode($data), LOCK_EX);
        }
    }
}