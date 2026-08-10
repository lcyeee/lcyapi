<?php
if (!defined('ROOT_PATH')) {
    define('API_REQUEST', true);
    require dirname(__DIR__, 2) . '/includes/bootstrap.php';
}

$path = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
$path = ltrim(rtrim($path, '/'), '/');
if (strncmp($path, 'v1beta', 6) === 0) {
    $path = substr($path, 6);
}
$path = ltrim($path, '/');
$segments = explode('/', $path);

if (($segments[0] ?? '') !== 'models') {
    return Response::openaiError('Not Found', 'invalid_request_error', 'route_not_found', 404);
}

/* GET /v1beta/models 模型列表（Gemini 格式） */
if (!isset($segments[1]) || $segments[1] === '') {
    $models = Model::all(true);
    $list = [];
    foreach ($models as $m) {
        $list[] = [
            'name' => 'models/' . $m['name'],
            'displayName' => isset($m['description']) && trim((string)$m['description']) !== '' ? $m['description'] : $m['name'],
            'description' => isset($m['description']) ? (string)$m['description'] : '',
            'supportedGenerationMethods' => ['generateContent'],
        ];
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['models' => $list], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$modelAction = $segments[1];
$action = '';
$model = $modelAction;
if (strpos($modelAction, ':') !== false) {
    list($model, $action) = explode(':', $modelAction, 2);
}

/* GET /v1beta/models/{model} 单个模型信息 */
if ($action === '') {
    $info = Model::find($model);
    if ($info === false || (int)$info['enabled'] !== 1) {
        return Response::openaiError('模型 ' . $model . ' 不存在', 'invalid_request_error', 'model_not_found', 404);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'name' => 'models/' . $model,
        'displayName' => isset($info['description']) && trim((string)$info['description']) !== '' ? $info['description'] : $model,
        'description' => isset($info['description']) ? (string)$info['description'] : '',
        'supportedGenerationMethods' => ['generateContent'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'generateContent' || $action === 'streamGenerateContent') {
    $endpoint = 'models/' . $model . ':' . $action;
    Relay::handle($endpoint, 'gemini', 'gemini', $model);
    exit;
}

return Response::openaiError('Not Found', 'invalid_request_error', 'route_not_found', 404);
