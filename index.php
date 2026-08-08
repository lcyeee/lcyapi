<?php
$path = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '/';
$path = rtrim($path, '/');

$isApi = strncmp($path, '/v1/', 4) === 0;
if ($isApi) {
    define('API_REQUEST', true);
}

if (!is_file(__DIR__ . '/config.php')) {
    if ($isApi) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => ['message' => '系统尚未安装，请先运行安装向导', 'type' => 'server_error', 'code' => 'not_installed']], JSON_UNESCAPED_UNICODE);
        exit;
    }
    redirect('install.php');
}

require __DIR__ . '/includes/bootstrap.php';

if (!app_installed()) {
    if ($isApi) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => ['message' => '系统尚未完成安装，请先运行安装向导', 'type' => 'server_error', 'code' => 'not_installed']], JSON_UNESCAPED_UNICODE);
        exit;
    }
    redirect(base_url('install.php'));
}

if ($isApi) {
    $route = ltrim(substr($path, 3), '/');
    if (preg_match('#^models/([^/]+)$#', $route, $m)) {
        $_GET['model'] = urldecode($m[1]);
        $file = API_PATH . '/models/detail.php';
    } else {
        $file = API_PATH . '/' . $route . '.php';
    }
    if (is_file($file)) {
        require $file;
        exit;
    }
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => ['message' => 'Not Found', 'type' => 'invalid_request_error', 'code' => 'route_not_found']], JSON_UNESCAPED_UNICODE);
    exit;
}

redirect(base_url('user/index.php'));