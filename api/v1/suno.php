<?php
/**
 * Suno API 路由（/suno/*）
 * - POST /suno/submit/{action}  提交任务（music/lyrics）
 * - POST /suno/fetch            批量查询用户任务
 * - GET  /suno/fetch/{id}       查询单个任务
 * - POST /suno/notify           上游回调（Bearer 鉴权）
 */
if (!defined('ROOT_PATH')) {
    define('API_REQUEST', true);
    require dirname(__DIR__, 2) . '/includes/bootstrap.php';
}

$path = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
$path = rtrim($path, '/');
$segments = array_values(array_filter(explode('/', $path), 'strlen'));

/* /suno/notify 不需要登录（Bearer 鉴权） */
if (count($segments) >= 2 && $segments[0] === 'suno' && $segments[1] === 'notify') {
    $secret = Suno::apiKey();
    $given = '';
    $authorization = isset($_SERVER['HTTP_AUTHORIZATION']) ? trim((string)$_SERVER['HTTP_AUTHORIZATION']) : '';
    if ($authorization !== '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $authorization = trim((string)$headers['Authorization']);
        }
    }
    if (strncasecmp($authorization, 'Bearer ', 7) === 0) {
        $given = substr($authorization, 7);
    }
    if ($secret !== '' && !hash_equals($secret, $given)) {
        http_response_code(401);
        echo json_encode(['code' => 401, 'message' => 'unauthorized']);
        exit;
    }
    $raw = file_get_contents('php://input');
    $json = json_decode((string)$raw, true);
    if (is_array($json) && !empty($json['task_id']) && is_array($json['data'])) {
        $taskId = (string)$json['task_id'];
        $task = DB::fetch('SELECT user_id FROM tasks WHERE task_id = ? AND platform = ?', [$taskId, TaskWorker::PLATFORM_SUNO]);
        if ($task !== false) {
            Suno::applyUpstreamStatus((int)$task['user_id'], $taskId, $json['data']);
        }
    }
    echo json_encode(['code' => 0, 'message' => 'ok']);
    exit;
}

/* 其余 /suno/* 需要令牌认证 */
$authorization = isset($_SERVER['HTTP_AUTHORIZATION']) ? trim((string)$_SERVER['HTTP_AUTHORIZATION']) : '';
if ($authorization === '' && function_exists('getallheaders')) {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $authorization = trim((string)$headers['Authorization']);
    }
}
$auth = Token::verify($authorization);
if (!$auth['ok']) {
    http_response_code(401);
    echo json_encode(['error' => ['message' => '认证失败或令牌已禁用', 'type' => 'auth_error', 'code' => 'invalid_token']]);
    exit;
}
$token = $auth['token'];
$userId = (int)$auth['user']['id'];

if (count($segments) < 2 || $segments[0] !== 'suno') {
    http_response_code(404);
    echo json_encode(['error' => ['message' => 'Not Found', 'type' => 'invalid_request_error', 'code' => 'route_not_found']]);
    exit;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

/* POST /suno/fetch 批量查询 */
if ($method === 'POST' && $segments[1] === 'fetch' && !isset($segments[2])) {
    $rows = TaskWorker::getByUser($userId, 1, 100, ['platform' => TaskWorker::PLATFORM_SUNO]);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_map('Suno::toDto', $rows), JSON_UNESCAPED_UNICODE);
    exit;
}

/* GET /suno/fetch/{id} 单个任务（先查本地，再拉上游刷新） */
if (isset($segments[1]) && $segments[1] === 'fetch' && isset($segments[2])) {
    $taskId = urldecode($segments[2]);
    $task = DB::fetch('SELECT * FROM tasks WHERE task_id = ? AND platform = ? AND user_id = ?', [$taskId, TaskWorker::PLATFORM_SUNO, $userId]);
    if ($task === false) {
        http_response_code(404);
        echo json_encode(['error' => ['message' => '任务不存在', 'type' => 'invalid_request_error', 'code' => 'task_not_found']]);
        exit;
    }
    $res = Suno::fetchById($userId, $taskId);
    $fresh = DB::fetch('SELECT * FROM tasks WHERE task_id = ? AND platform = ? AND user_id = ?', [$taskId, TaskWorker::PLATFORM_SUNO, $userId]);
    if ($fresh === false) {
        $fresh = $task;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(Suno::toDto($fresh), JSON_UNESCAPED_UNICODE);
    exit;
}

/* POST /suno/submit/{action} */
if ($method === 'POST' && $segments[1] === 'submit' && isset($segments[2])) {
    $action = strtoupper($segments[2]);
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        $body = $_POST;
    }
    $input = isset($body['input']) && is_array($body['input']) ? $body['input'] : $body;
    $charge = max(0, (float)setting('suno_charge', '0'));
    if ($charge > 0) {
        $ok = User::deductQuota($userId, $charge);
        if (!$ok) {
            http_response_code(403);
            echo json_encode(['error' => ['message' => '余额不足，无法提交音乐生成任务', 'type' => 'insufficient_quota', 'code' => 'insufficient_quota']]);
            exit;
        }
    }
    $result = Suno::submit($userId, $action, $input, [
        'group' => $token['group'] ?? 'default',
        'channel_id' => 0,
        'quota' => $charge,
    ]);
    if (!$result['ok']) {
        if ($charge > 0) {
            User::addQuota($userId, $charge, 'refund', 'Suno 提交失败退款');
        }
        http_response_code(400);
        echo json_encode(['error' => ['message' => $result['msg'], 'type' => 'invalid_request_error', 'code' => 'submit_failed']]);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => 1, 'message' => '提交成功', 'data' => ['task_id' => $result['task_id']]], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(404);
echo json_encode(['error' => ['message' => 'Not Found', 'type' => 'invalid_request_error', 'code' => 'route_not_found']]);
exit;
