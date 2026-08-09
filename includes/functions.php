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

/**
 * 按后台「额度显示」设置格式化余额/费用（库内仍为美元，仅展示层换算）
 */
function quota_display($quota)
{
    static $type = null, $symbol = null, $rate = null;
    if ($type === null) {
        $type = setting('quota_display_type', 'USD');
        $symbol = setting('custom_currency_symbol', '');
        $rate = max(0.0001, (float)setting('custom_currency_rate', '1'));
    }
    $value = (float)$quota;
    switch ($type) {
        case 'CNY':
            return '¥' . number_format($value * $rate, 2, '.', ',');
        case 'TOKENS':
            return number_format($value * $rate, 0, '.', ',') . ' 积分';
        case 'CUSTOM':
            return ($symbol !== '' ? $symbol : '') . number_format($value * $rate, 2, '.', ',');
        default:
            return '$' . number_format($value, 4, '.', ',');
    }
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

/**
 * 管理操作审计日志：记录高危操作的操作者/IP/参数
 */
function audit_log($action, $target = null, $detail = null)
{
    try {
        DB::insert('audit_logs', [
            'admin_id' => Auth::id() ? Auth::id() : 0,
            'action' => mb_substr($action, 0, 50),
            'target' => $target !== null ? mb_substr((string)$target, 0, 100) : null,
            'detail' => $detail !== null ? mb_substr((string)$detail, 0, 2000) : null,
            'ip' => client_ip(),
        ]);
    } catch (Exception $ex) {
        write_log('audit_log error: ' . $ex->getMessage());
    }
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

/* ==================== 系统实例心跳（多节点） ==================== */

function system_instances_heartbeat()
{
    $node = (string)config('instance.node_name', '');
    if ($node === '') {
        $node = 'lcyapi-' . (function_exists('gethostname') ? gethostname() : 'node');
    }
    $node = mb_substr((string)preg_replace('/[^a-zA-Z0-9_\-]/', '', $node), 0, 50);
    /* 60 秒节流，避免每次请求都写库 */
    $cacheFile = CACHE_PATH . '/heartbeat-' . md5($node) . '.tmp';
    if (is_file($cacheFile) && filemtime($cacheFile) > time() - 60) {
        return;
    }
    @file_put_contents($cacheFile, time(), LOCK_EX);
    try {
        DB::query(
            'INSERT INTO system_instances (node_name, ip, status, last_heartbeat) VALUES (?, ?, 1, NOW()) '
            . 'ON DUPLICATE KEY UPDATE ip = VALUES(ip), status = 1, last_heartbeat = NOW()',
            [$node, client_ip()]
        );
    } catch (Throwable $ex) {
        write_log('instance heartbeat error: ' . $ex->getMessage());
    }
}

/* ==================== Cloudflare Turnstile 人机验证 ==================== */

function turnstile_enabled()
{
    return setting('turnstile_site_key', '') !== '' && setting('turnstile_secret_key', '') !== '';
}

/**
 * 渲染 Turnstile 挂件（放入 form 内）
 */
function turnstile_widget()
{
    if (!turnstile_enabled()) {
        return '';
    }
    return '<div class="cf-turnstile" data-sitekey="' . e(setting('turnstile_site_key')) . '"></div>'
        . '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
}

/**
 * 服务端校验 Turnstile 令牌
 */
function turnstile_verify($token = null)
{
    if (!turnstile_enabled()) {
        return true;
    }
    if ($token === null) {
        $token = isset($_POST['cf-turnstile-response']) ? (string)$_POST['cf-turnstile-response'] : '';
    }
    if ($token === '') {
        return false;
    }
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => setting('turnstile_secret_key'),
            'response' => $token,
            'remoteip' => client_ip(),
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) {
        return false;
    }
    $json = json_decode((string)$resp, true);
    return is_array($json) && !empty($json['success']);
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

/**
 * 纯 SVG 线性图标（iOS 风格，24x24 视口、2px 描边）
 * 用法：echo svg_icon('home'); 配合 CSS class="i" 控制尺寸颜色
 */
function svg_icon($name, $class = 'i')
{
    static $icons = null;
    if ($icons === null) {
        $icons = [
            'home'     => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-6h6v6"/>',
            'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>',
            'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.5 2.9-5.5 6.5-5.5s6.5 2 6.5 5.5"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18.5 14.7c1.9.8 3 2.3 3 4.3"/>',
            'channel'  => '<path d="M12 2 2 7l10 5 10-5-10-5z"/><path d="M2 12l10 5 10-5"/><path d="M2 17l10 5 10-5"/>',
            'cpu'      => '<rect x="6" y="6" width="12" height="12" rx="2"/><rect x="10" y="10" width="4" height="4"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4M5 5l2.5 2.5M16.5 16.5 19 19M19 5l-2.5 2.5M7.5 16.5 5 19"/>',
            'key'      => '<circle cx="8" cy="15" r="4.5"/><path d="M11.2 11.8 20 3M16 7l3 3M13.5 9.5l2 2"/>',
            'list'     => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1"/><circle cx="3.5" cy="12" r="1"/><circle cx="3.5" cy="18" r="1"/>',
            'alert'    => '<path d="M12 3 2.5 20h19L12 3z"/><path d="M12 9.5v4.5"/><circle cx="12" cy="17" r=".5"/>',
            'gift'     => '<rect x="3" y="9" width="18" height="4" rx="1"/><path d="M5 13v8h14v-8M12 9v12"/><path d="M12 9c-4.5 0-6-1.5-6-3.5C6 3.5 8 3 9.5 4 11 5 12 9 12 9zm0 0c4.5 0 6-1.5 6-3.5C18 3.5 16 3 14.5 4 13 5 12 9 12 9z"/>',
            'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2.5v3M12 18.5v3M2.5 12h3M18.5 12h3M5.3 5.3l2.1 2.1M16.6 16.6l2.1 2.1M18.7 5.3l-2.1 2.1M7.4 16.6l-2.1 2.1"/>',
            'wallet'   => '<rect x="2.5" y="6" width="19" height="14" rx="3"/><path d="M2.5 10h19"/><circle cx="17" cy="15" r="1"/>',
            'refresh'  => '<path d="M20 11a8 8 0 1 0-2.3 6.3"/><path d="M20 4v7h-7"/>',
            'logout'   => '<path d="M14 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8"/><path d="M10 12h11M17 8l4 4-4 4"/>',
            'edit'     => '<path d="M4 20h4L20 8.5a2.1 2.1 0 0 0-3-3L5.5 17 4 20z"/><path d="M14.5 7 17 9.5"/>',
            'trash'    => '<path d="M4 7h16M9 7V4h6v3M6.5 7l1 14h9l1-14"/><path d="M10 11v6M14 11v6"/>',
            'plus'     => '<path d="M12 5v14M5 12h14"/>',
            'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
            'copy'     => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
            'check'    => '<path d="m4.5 12.5 5 5 10-11"/>',
            'close'    => '<path d="M6 6l12 12M18 6 6 18"/>',
            'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
            'moon'     => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
            'zap'      => '<path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z"/>',
            'shield'   => '<path d="M12 2 4 5.5v6c0 5 3.5 8.5 8 10.5 4.5-2 8-5.5 8-10.5v-6L12 2z"/>',
            'chart'    => '<path d="M4 20V9M10 20V4M16 20v-8M21 20H3"/>',
            'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
            'dollar'   => '<circle cx="12" cy="12" r="9"/><path d="M12 6v12M15 8.5c-.8-1-4.5-1.2-4.5.9 0 2.4 4.8 1.3 4.8 3.8 0 2.2-4 2-5.3.8"/>',
            'send'     => '<path d="m3 11 18-8-8 18-2.5-7.5L3 11z"/>',
            'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><circle cx="12" cy="8" r=".5"/>',
            'file'     => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z"/><path d="M14 3v5h5"/>',
            'lock'     => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
            'eye'      => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
            'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18z"/>',
            'tag'      => '<path d="M3 3h8l10 10-8 8L3 11V3z"/><circle cx="8" cy="8" r="1.5"/>',
            'tag'      => '<path d="M3 3h8l10 10-8 8L3 11V3z"/><circle cx="8" cy="8" r="1.5"/>',
            'ratio'    => '<path d="M21 3 14 10M14 10l4 4M14 10H3l6 6"/>',
            'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 11a3.5 3.5 0 0 1 0 7"/>',
            'copy'     => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>',
            'search'   => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
            'trash'    => '<path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
            'check'    => '<path d="M4 12l5 5L20 7"/>',
            'refresh'  => '<path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 3v6h-6"/>',
            'plus'     => '<path d="M12 5v14M5 12h14"/>',
            'minus'    => '<path d="M5 12h14"/>',
            'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
            'gift'     => '<rect x="4" y="8" width="16" height="4" rx="1"/><path d="M12 8v13M5 12v9h14v-9M12 8c-2.5 0-4-1.5-4-3s1.5-3 4-3 4 1.5 4 3-1.5 3-4 3z"/>',
            'list'     => '<path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
            'cpu'      => '<rect x="7" y="7" width="10" height="10" rx="1"/><path d="M4 10H2M4 14H2M10 4V2M14 4V2M22 10h-2M22 14h-2M14 22v-2M10 22v-2M12 11v2M11 12h2"/>',
            'key'      => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M15 8l3 3M19 4l2 2"/>',
            'crown'    => '<path d="M3 7l4 4 5-6 5 6 4-4-2 12H5L3 7z"/><path d="M6 17h12"/>',
            'server'   => '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01"/>',
            'download' => '<path d="M12 3v12M7 10l5 5 5-5M4 21h16"/>',
        ];
    }
    $inner = isset($icons[$name]) ? $icons[$name] : '';
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" aria-hidden="true">' . $inner . '</svg>';
}

/**
 * 主题引擎公共片段：防闪烁内联脚本（head 中尽早执行）+ theme.js 引入
 */
function theme_head_scripts()
{
    $inline = <<<'JS'
(function(){try{var m=localStorage.getItem('lcy_mode');var d=m==='dark'||((!m||m==='auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.setAttribute('data-mode',d?'dark':'light');}catch(e){}})();
JS;
    return '<script>' . $inline . '</script>' . "\n" . '<script src="' . base_url('assets/js/theme.js') . '"></script>' . "\n" . '<script src="' . base_url('assets/js/modal.js') . '"></script>';
}