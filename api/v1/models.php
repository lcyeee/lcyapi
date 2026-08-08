<?php
if (!defined('ROOT_PATH')) {
    define('API_REQUEST', true);
    require dirname(__DIR__, 2) . '/includes/bootstrap.php';
}

function api_models_auth()
{
    $authorization = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if ($authorization === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $authorization = $headers['Authorization'];
        }
    }
    $rawKey = '';
    if ($authorization !== '') {
        $rawKey = $authorization;
    } elseif (!empty($_SERVER['HTTP_X_API_KEY'])) {
        $rawKey = $_SERVER['HTTP_X_API_KEY'];
    }
    if ($rawKey === '') {
        return null;
    }
    $result = Token::verify($rawKey);
    return $result['ok'];
}

$auth = api_models_auth();
if ($auth === null) {
    Response::openaiError('缺少认证信息', 'invalid_request_error', 'unauthorized', 401);
}
if ($auth !== true) {
    Response::openaiError('无效的令牌', 'invalid_request_error', 'invalid_token', 401);
}

$models = Model::all(true);
$data = [];
foreach ($models as $model) {
    $data[] = [
        'id' => $model['name'],
        'object' => 'model',
        'created' => strtotime($model['created_at']),
        'owned_by' => 'new-api',
    ];
}
Response::json(['object' => 'list', 'data' => $data]);