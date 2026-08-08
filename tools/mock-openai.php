<?php
$body = file_get_contents('php://input');
$payload = json_decode($body, true);
if (!is_array($payload)) {
    $payload = [];
}
$model = isset($payload['model']) ? $payload['model'] : 'unknown';

if (isset($_GET['fail'])) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => ['message' => '模拟上游内部错误', 'type' => 'server_error']], JSON_UNESCAPED_UNICODE);
    exit;
}
if (isset($_GET['slow'])) {
    sleep(3);
}

$usage = ['prompt_tokens' => 12, 'completion_tokens' => 8, 'total_tokens' => 20];

if (!empty($payload['stream'])) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    echo "data: {\"id\":\"chatcmpl-mock\",\"object\":\"chat.completion.chunk\",\"created\":" . time() . ",\"model\":\"$model\",\"choices\":[{\"index\":0,\"delta\":{\"role\":\"assistant\"},\"finish_reason\":null}]}\n\n";
    echo "data: {\"id\":\"chatcmpl-mock\",\"object\":\"chat.completion.chunk\",\"created\":" . time() . ",\"model\":\"$model\",\"choices\":[{\"index\":0,\"delta\":{\"content\":\"你好\"},\"finish_reason\":null}]}\n\n";
    echo "data: {\"id\":\"chatcmpl-mock\",\"object\":\"chat.completion.chunk\",\"created\":" . time() . ",\"model\":\"$model\",\"choices\":[{\"index\":0,\"delta\":{\"content\":\"，世界\"},\"finish_reason\":null}]}\n\n";
    echo "data: {\"id\":\"chatcmpl-mock\",\"object\":\"chat.completion.chunk\",\"created\":" . time() . ",\"model\":\"$model\",\"choices\":[{\"index\":0,\"delta\":{},\"finish_reason\":\"stop\"}]}\n\n";
    echo "data: {\"id\":\"chatcmpl-mock\",\"object\":\"chat.completion.chunk\",\"created\":" . time() . ",\"model\":\"$model\",\"choices\":[],\"usage\":" . json_encode($usage) . "}\n\n";
    echo "data: [DONE]\n\n";
    exit;
}

header('Content-Type: application/json');
echo json_encode([
    'id' => 'chatcmpl-mock',
    'object' => 'chat.completion',
    'created' => time(),
    'model' => $model,
    'choices' => [[
        'index' => 0,
        'message' => ['role' => 'assistant', 'content' => '这是模拟回复，model=' . $model],
        'finish_reason' => 'stop',
    ]],
    'usage' => $usage,
], JSON_UNESCAPED_UNICODE);