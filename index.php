<?php
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$path = rtrim($path, '/');

if ($path === '/v1' || starts_with($path, '/v1/')) {
    $route = ltrim(substr($path, 3), '/');
    if (preg_match('#^models/([^/]+)$#', $route, $m)) {
        $_GET['model'] = urldecode($m[1]);
        $file = API_PATH . '/models/detail.php';
    } else {
        $file = API_PATH . '/' . $route . '.php';
    }
    if (is_file($file)) {
        define('API_REQUEST', true);
        require INCLUDE_PATH . '/bootstrap.php';
        require $file;
        exit;
    }
    require INCLUDE_PATH . '/bootstrap.php';
    if (function_exists('api_error_404')) {
        api_error_404();
    }
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => ['message' => 'Not Found', 'type' => 'invalid_request_error', 'code' => 'route_not_found']], JSON_UNESCAPED_UNICODE);
    exit;
}

require INCLUDE_PATH . '/bootstrap.php';
redirect(config('site.url', '/'));