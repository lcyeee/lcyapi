<?php
/**
 * OpenAI 兼容账单查询端点（供 OpenAI SDK / 第三方工具查询余额与用量）
 * GET /v1/dashboard/billing/subscription
 * GET /v1/dashboard/billing/usage?start_date=2020-01-01&end_date=2030-01-01
 * 认证：Bearer sk-（普通令牌即可，与 lcyapi 一致）
 */
if (!defined('ROOT_PATH')) {
    define('API_REQUEST', true);
    require dirname(__DIR__, 2) . '/includes/bootstrap.php';
}

$action = isset($_GET['billing_action']) ? $_GET['billing_action'] : '';

/* 令牌认证：与 /v1/chat/completions 一致（Token::verify） */
$rawKey = '';
$authorization = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if ($authorization === '' && function_exists('getallheaders')) {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $authorization = $headers['Authorization'];
    }
}
if ($authorization !== '') {
    $rawKey = $authorization;
} elseif (!empty($_SERVER['HTTP_X_API_KEY'])) {
    $rawKey = $_SERVER['HTTP_X_API_KEY'];
}
if (preg_match('/^Bearer\s+(.+)$/i', $rawKey, $m)) {
    $rawKey = trim($m[1]);
}
if ($rawKey === '') {
    $auth = ['ok' => false, 'error' => 'unauthorized', 'message' => '缺少认证信息', 'http_code' => 401];
} else {
    $auth = Token::verify($rawKey);
}
if (empty($auth['ok'])) {
    $code = isset($auth['http_code']) && (int)$auth['http_code'] >= 400 ? (int)$auth['http_code'] : 401;
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode(['error' => ['message' => $auth['message'], 'type' => 'invalid_request_error', 'code' => $auth['error']]], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = $auth['user'];

if ($action === 'subscription') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'object' => 'billing_subscription',
        'has_payment_method' => true,
        'soft_limit_usd' => (float)$user['quota'],
        'hard_limit_usd' => (float)$user['quota'],
        'system_hard_limit_usd' => 999999.0,
        'access_until' => 0,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'usage') {
    /* total_usage 单位为分（cents），1 美元 = 100 */
    $used = (float)$user['used_quota'];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'object' => 'billing_usage',
        'total_usage' => (int)round($used * 100),
        'total_granted' => (int)round((float)$user['total_quota'] * 100),
        'start_date' => isset($_GET['start_date']) ? substr(preg_replace('/[^0-9-]/', '', (string)$_GET['start_date']), 0, 10) : date('Y-m-01'),
        'end_date' => isset($_GET['end_date']) ? substr(preg_replace('/[^0-9-]/', '', (string)$_GET['end_date']), 0, 10) : date('Y-m-d'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
http_response_code(404);
echo json_encode(['error' => ['message' => 'Not Found', 'type' => 'invalid_request_error', 'code' => 'route_not_found']], JSON_UNESCAPED_UNICODE);
exit;