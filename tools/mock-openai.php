<?php
$path = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
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

if (strpos($path, '/models') !== false) {
    header('Content-Type: application/json');
    $mockModels = ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'text-embedding-3-small', 'text-embedding-3-large', 'dall-e-3', 'whisper-1', 'tts-1'];
    echo json_encode([
        'object' => 'list',
        'data' => array_map(function ($id) {
            return ['id' => $id, 'object' => 'model', 'created' => 1700000000, 'owned_by' => 'mock'];
        }, $mockModels),
    ]);
    exit;
}
if (strpos($path, '/embeddings') !== false) {
    header('Content-Type: application/json');
    echo json_encode([
        'object' => 'list',
        'data' => [['object' => 'embedding', 'embedding' => array_fill(0, 4, 0.0123), 'index' => 0]],
        'model' => $model,
        'usage' => ['prompt_tokens' => 4, 'total_tokens' => 4],
    ]);
    exit;
}
if (strpos($path, '/images/generations') !== false) {
    header('Content-Type: application/json');
    echo json_encode([
        'created' => time(),
        'data' => [['url' => 'https://example.com/generated.png']],
    ]);
    exit;
}
if (strpos($path, '/audio/transcriptions') !== false) {
    header('Content-Type: application/json');
    echo json_encode(['text' => '这是模拟转写出的文本内容。']);
    exit;
}
if (strpos($path, '/audio/speech') !== false) {
    header('Content-Type: audio/mpeg');
    echo "\x00\x00\x00\x18ftypmp42";
    exit;
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