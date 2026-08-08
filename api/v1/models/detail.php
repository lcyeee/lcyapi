<?php
if (!defined('ROOT_PATH')) {
    define('API_REQUEST', true);
    require dirname(__DIR__, 3) . '/includes/bootstrap.php';
}

function api_model_detail_auth()
{
    $authorization = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if ($authorization === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $authorization = $headers['Authorization'];
        }
    }
    $rawKey = $authorization !== '' ? $authorization : (isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '');
    if ($rawKey === '') {
        return null;
    }
    $result = Token::verify($rawKey);
    return $result['ok'];
}

$auth = api_model_detail_auth();
if ($auth === null) {
    Response::openaiError('缺少认证信息', 'invalid_request_error', 'unauthorized', 401);
}
if ($auth !== true) {
    Response::openaiError('无效的令牌', 'invalid_request_error', 'invalid_token', 401);
}

$modelName = isset($_GET['model']) ? $_GET['model'] : '';
$model = $modelName !== '' ? Model::find($modelName) : false;
if ($model === false || (int)$model['enabled'] !== 1) {
    Response::openaiError('模型不存在或已停用', 'invalid_request_error', 'model_not_found', 404);
}
Response::json([
    'id' => $model['name'],
    'object' => 'model',
    'created' => strtotime($model['created_at']),
    'owned_by' => 'lcyapi',
]);