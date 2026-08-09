<?php
define('ROOT_PATH', str_replace('\\', '/', dirname(__DIR__)));
define('APP_PATH', ROOT_PATH . '/app');
define('INCLUDE_PATH', ROOT_PATH . '/includes');
define('DATA_PATH', ROOT_PATH . '/data');
define('API_PATH', ROOT_PATH . '/api/v1');
define('CACHE_PATH', DATA_PATH . '/cache');
define('LOG_PATH', DATA_PATH . '/logs');
define('UPLOAD_PATH', DATA_PATH . '/uploads');

$GLOBALS['__config'] = require ROOT_PATH . '/config.php';
$config = $GLOBALS['__config'];

date_default_timezone_set($config['timezone']);
mb_internal_encoding('UTF-8');

error_reporting(E_ALL);
if (empty($config['app']['debug'])) {
    ini_set('display_errors', '0');
} else {
    ini_set('display_errors', '1');
}

foreach ([CACHE_PATH, LOG_PATH, UPLOAD_PATH] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

spl_autoload_register(function ($class) {
    $map = [
        'DB' => 'db', 'Auth' => 'auth', 'Channel' => 'channel', 'Token' => 'token',
        'User' => 'user', 'Log' => 'log', 'Billing' => 'billing', 'Cache' => 'cache',
        'RateLimit' => 'ratelimit', 'Validator' => 'validator', 'Response' => 'response',
        'Admin' => 'admin', 'Relay' => 'relay', 'Model' => 'model', 'Redemption' => 'redemption',
        'Recharge' => 'recharge', 'Dashboard' => 'dashboard', 'Group' => 'group',
        'ChannelType' => 'channel_types', 'Converter' => 'converter', 'OAuth' => 'oauth', 'TOTP' => 'totp',
        'Mailer' => 'mailer', 'PayOrder' => 'pay_order', 'Sensitive' => 'sensitive',
        'Affinity' => 'affinity',
    ];
    if (isset($map[$class])) {
        $file = INCLUDE_PATH . '/' . $map[$class] . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require INCLUDE_PATH . '/functions.php';

DB::init($config['db']);
Cache::setConfig([
    'enabled' => $config['cache']['enabled'],
    'ttl' => $config['cache']['ttl'],
    'path' => $config['cache']['path'] ?: CACHE_PATH,
]);

if (defined('API_REQUEST') && API_REQUEST) {
    return;
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => (int)$config['security']['session_lifetime'],
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('lcyapi_sid');
    session_start();
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}