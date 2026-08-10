<?php
/**
 * Midjourney API 路由（/mj/*）
 * - /mj/submit/{action}    提交任务（需令牌认证）
 * - /mj/task/{id}/fetch    查询任务
 * - /mj/task/list          任务列表
 * - /mj/notify             上游回调（mj-api-secret 鉴权）
 */
if (!defined('ROOT_PATH')) {
    define('API_REQUEST', true);
    require dirname(__DIR__, 2) . '/includes/bootstrap.php';
}

$path = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
$path = rtrim($path, '/');
$segments = array_values(array_filter(explode('/', $path), 'strlen'));

/* /mj/notify 不需要登录（mj-api-secret 鉴权） */
if (count($segments) >= 2 && $segments[0] === 'mj' && $segments[1] === 'notify') {
    $secret = Midjourney::apiKey();
    $given = isset($_SERVER['HTTP_MJ_API_SECRET']) ? (string)$_SERVER['HTTP_MJ_API_SECRET'] : '';
    if ($secret !== '' && !hash_equals($secret, $given)) {
        http_response_code(401);
        echo json_encode(['code' => 401, 'description' => 'unauthorized']);
        exit;
    }
    Midjourney::handleNotify();
    exit;
}

/* 其余 /mj/* 需要令牌认证 */
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

if (count($segments) < 2 || $segments[0] !== 'mj') {
    http_response_code(404);
    echo json_encode(['error' => ['message' => 'Not Found', 'type' => 'invalid_request_error', 'code' => 'route_not_found']]);
    exit;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

/* GET /mj/task/list 或 /mj/task/list-by-condition */
if ($method === 'GET' && $segments[1] === 'task' && isset($segments[2]) && in_array($segments[2], ['list', 'list-by-condition'], true)) {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(50, max(1, (int)($_GET['page_size'] ?? 10)));
    $rows = Midjourney::getByUser($userId, $page, $pageSize, [
        'status' => isset($_GET['status']) ? trim((string)$_GET['status']) : '',
        'action' => isset($_GET['action']) ? trim((string)$_GET['action']) : '',
    ]);
    $total = Midjourney::countByUser($userId, [
        'status' => isset($_GET['status']) ? trim((string)$_GET['status']) : '',
        'action' => isset($_GET['action']) ? trim((string)$_GET['action']) : '',
    ]);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['data' => array_map('Midjourney::toDto', $rows), 'total' => $total], JSON_UNESCAPED_UNICODE);
    exit;
}

/* GET /mj/task/{id}/fetch */
if ($method === 'GET' && $segments[1] === 'task' && isset($segments[2])) {
    $mjId = urldecode($segments[2]);
    $local = DB::fetch('SELECT * FROM midjourneys WHERE mj_id = ? AND user_id = ?', [$mjId, $userId]);
    if ($local === false) {
        /* 尝试按对外 task_id 查 tasks 表 */
        $task = TaskWorker::findByTaskId($mjId, $userId);
        if ($task !== false) {
            $pd = json_decode((string)$task['private_data'], true);
            $mjId = is_array($pd) && !empty($pd['upstream_task_id']) ? $pd['upstream_task_id'] : $mjId;
            $local = DB::fetch('SELECT * FROM midjourneys WHERE mj_id = ? AND user_id = ?', [$mjId, $userId]);
        }
    }
    if ($local === false) {
        http_response_code(404);
        echo json_encode(['error' => ['message' => '任务不存在', 'type' => 'invalid_request_error', 'code' => 'task_not_found']]);
        exit;
    }
    $res = Midjourney::fetchTask($local['mj_id']);
    if (!$res['ok']) {
        /* 上游查询失败时返回本地状态 */
        $res['json'] = Midjourney::toDto($local);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($res['json'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* POST /mj/submit/{action} */
if ($method === 'POST' && $segments[1] === 'submit' && isset($segments[2])) {
    $action = strtoupper($segments[2]);
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        $body = $_POST;
    }
    $prompt = isset($body['prompt']) ? (string)$body['prompt'] : '';
    if ($action === 'IMAGINE' && $prompt === '') {
        http_response_code(400);
        echo json_encode(['error' => ['message' => 'prompt 不能为空', 'type' => 'invalid_request_error', 'code' => 'missing_prompt']]);
        exit;
    }
    unset($body['prompt']);
    /* inPaint/customZoom 不扣费 */
    $isFreeAction = ($action === 'CHANGE' && (strpos($prompt, 'inpaint') !== false || strpos($prompt, 'customZoom') !== false));
    $charge = $isFreeAction ? 0 : max(0, (float)setting('midjourney_charge', '0'));
    if ($charge > 0) {
        $ok = User::deductQuota($userId, $charge);
        if (!$ok) {
            http_response_code(403);
            echo json_encode(['error' => ['message' => '余额不足，无法提交绘图任务', 'type' => 'insufficient_quota', 'code' => 'insufficient_quota']]);
            exit;
        }
    }
    $result = Midjourney::submit($userId, $action, $prompt, $body, [
        'group' => $token['group'] ?? 'default',
        'channel_id' => 0,
        'quota' => $charge,
    ]);
    if (!$result['ok']) {
        if ($charge > 0) {
            User::addQuota($userId, $charge, 'refund', 'Midjourney 提交失败退款');
        }
        http_response_code(400);
        echo json_encode(['error' => ['message' => $result['msg'], 'type' => 'invalid_request_error', 'code' => 'submit_failed']]);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => 1, 'description' => '提交成功', 'result' => $result['mj_id'], 'task_id' => $result['task_id']], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(404);
echo json_encode(['error' => ['message' => 'Not Found', 'type' => 'invalid_request_error', 'code' => 'route_not_found']]);
exit;
