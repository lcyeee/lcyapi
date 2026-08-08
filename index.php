<?php
$path = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '/';
$path = rtrim($path, '/');

$isApi = strncmp($path, '/v1/', 4) === 0;
if ($isApi) {
    define('API_REQUEST', true);
}
require __DIR__ . '/includes/bootstrap.php';

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

redirect(config('site.url', '/'));