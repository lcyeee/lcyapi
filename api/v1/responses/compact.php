<?php
/**
 * /v1/responses/compact - Responses API 压缩端点
 * 将请求体压缩后转发到 /v1/responses
 */
$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'Invalid JSON', 'type' => 'invalid_request_error']]);
    exit;
}
/* 直接转发到 /v1/responses */
require __DIR__ . '/responses.php';