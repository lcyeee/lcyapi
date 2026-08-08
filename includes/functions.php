<?php
function config($key = null, $default = null)
{
    $config = $GLOBALS['__config'];
    if ($key === null) {
        return $config;
    }
    $parts = explode('.', $key);
    $value = $config;
    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function e($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function redirect($url, $code = 302)
{
    header('Location: ' . $url, true, $code);
    exit;
}

function client_ip()
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

function csrf_token()
{
    return isset($_SESSION['csrf']) ? $_SESSION['csrf'] : '';
}

function csrf_verify($token = null)
{
    if ($token === null) {
        $token = isset($_POST['_csrf']) ? $_POST['_csrf'] : (isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : null);
    }
    return is_string($token) && is_string(csrf_token()) && $token !== '' && hash_equals(csrf_token(), $token);
}

function setting($key, $default = null)
{
    $value = Cache::remember('setting:' . $key, 60, function () use ($key) {
        $row = DB::fetch('SELECT value FROM settings WHERE `key` = :k', ['k' => $key]);
        return $row === false ? '__NULL__' : $row['value'];
    });
    return $value === '__NULL__' ? $default : $value;
}

function setting_set($key, $value)
{
    DB::query('INSERT INTO settings (`key`, value, updated_at) VALUES (:k, :v, NOW()) ON DUPLICATE KEY UPDATE value = :v2, updated_at = NOW()', ['k' => $key, 'v' => $value, 'v2' => $value]);
    Cache::delete('setting:' . $key);
    Cache::delete('settings:all');
}

function settings_all()
{
    return Cache::remember('settings:all', config('cache.ttl'), function () {
        $rows = DB::fetchAll('SELECT `key`, value FROM settings');
        $out = [];
        foreach ($rows as $row) {
            $out[$row['key']] = $row['value'];
        }
        return $out;
    });
}

function random_string($length = 32)
{
    $bytes = random_bytes(ceil($length / 2));
    return substr(bin2hex($bytes), 0, $length);
}

function starts_with($haystack, $needle)
{
    return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
}

function ends_with($haystack, $needle)
{
    return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
}

function format_quota($quota)
{
    return number_format((float)$quota, 4, '.', '');
}

function format_money($amount)
{
    return '$' . number_format((float)$amount, 4, '.', ',');
}

function format_elapsed($milliseconds)
{
    if ($milliseconds < 1000) {
        return $milliseconds . ' ms';
    }
    return number_format($milliseconds / 1000, 2) . ' s';
}

function write_log($message, $type = 'error')
{
    $file = LOG_PATH . '/' . $type . '.log';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

function get_input_json()
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function http_request_id()
{
    if (!empty($_SERVER['HTTP_X_REQUEST_ID'])) {
        return preg_replace('/[^A-Za-z0-9\-_]/', '', $_SERVER['HTTP_X_REQUEST_ID']);
    }
    return strtoupper(bin2hex(random_bytes(8)));
}

function session_flash($key, $value = null)
{
    if ($value !== null) {
        $_SESSION['flash'][$key] = $value;
        return $value;
    }
    if (isset($_SESSION['flash'][$key])) {
        $value = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $value;
    }
    return '';
}

function app_installed()
{
    try {
        $row = DB::fetch("SELECT value FROM settings WHERE `key` = 'installed' LIMIT 1");
        return $row !== false && (string)$row['value'] === '1';
    } catch (Throwable $e) {
        return false;
    }
}

function base_url($path = '')
{
    $url = rtrim(config('site.url', '/'), '/');
    return $url . ($path !== '' ? '/' . ltrim($path, '/') : '');
}