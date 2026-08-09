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
    $mockModels = ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'text-embedding-3-small', 'text-embedding-3-large', 'dall-e-3', 'whisper-1', 'tts-1', 'claude-3-5-sonnet', 'gemini-2.0-flash'];
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
if (strpos($path, '/images/generations') !== false || strpos($path, '/images/edits') !== false) {
    header('Content-Type: application/json');
    echo json_encode([
        'created' => time(),
        'data' => [['url' => 'https://example.com/generated.png']],
    ]);
    exit;
}
if (strpos($path, '/audio/transcriptions') !== false || strpos($path, '/audio/translations') !== false) {
    header('Content-Type: application/json');
    echo json_encode(['text' => '这是模拟转写出的文本内容。']);
    exit;
}
if (strpos($path, '/audio/speech') !== false) {
    header('Content-Type: audio/mpeg');
    echo "\x00\x00\x00\x18ftypmp42";
    exit;
}
if (strpos($path, '/moderations') !== false) {
    header('Content-Type: application/json');
    $input = isset($payload['input']) ? $payload['input'] : '';
    echo json_encode([
        'id' => 'modr-mock',
        'model' => $model,
        'results' => [[
            'flagged' => false,
            'categories' => ['harassment' => false, 'hate' => false, 'sexual' => false, 'violence' => false],
            'category_scores' => ['harassment' => 0.001, 'hate' => 0.001, 'sexual' => 0.001, 'violence' => 0.001],
        ]],
        'usage' => ['prompt_tokens' => 6, 'total_tokens' => 6],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
if (strpos($path, '/rerank') !== false) {
    header('Content-Type: application/json');
    $docs = isset($payload['documents']) ? $payload['documents'] : [];
    $results = [];
    foreach ($docs as $i => $doc) {
        $results[] = ['index' => $i, 'relevance_score' => 1.0 - $i * 0.1, 'document' => is_array($doc) ? (isset($doc['text']) ? $doc['text'] : json_encode($doc)) : $doc];
    }
    echo json_encode([
        'id' => 'rerank-mock',
        'results' => $results,
        'usage' => ['total_tokens' => 10],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
if (strpos($path, '/responses') !== false) {
    $usage = ['input_tokens' => 12, 'output_tokens' => 8, 'total_tokens' => 20];
    if (!empty($payload['stream'])) {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        echo "event: response.created\ndata: {\"type\":\"response.created\",\"response\":{\"id\":\"resp_mock\",\"object\":\"response\",\"model\":\"$model\"}}\n\n";
        echo "event: response.output_text.delta\ndata: {\"type\":\"response.output_text.delta\",\"delta\":\"你好，世界\",\"item_id\":\"it1\",\"output_index\":0}\n\n";
        echo "event: response.completed\ndata: {\"type\":\"response.completed\",\"response\":{\"id\":\"resp_mock\",\"object\":\"response\",\"model\":\"$model\",\"usage\":{\"input_tokens\":12,\"output_tokens\":8,\"total_tokens\":20}}}\n\n";
        echo "data: [DONE]\n\n";
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode([
        'id' => 'resp_mock',
        'object' => 'response',
        'created_at' => time(),
        'model' => $model,
        'status' => 'completed',
        'output' => [['type' => 'message', 'id' => 'msg_1', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => '这是模拟 Responses 回复，model=' . $model]]]],
        'usage' => $usage,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* Gemini 格式：/v1beta/models/{model}:generateContent 或 :streamGenerateContent */
if (strpos($path, '/v1beta/models/') !== false) {
    if (preg_match('#/models/([^:/]+):(streamGenerateContent|generateContent)$#', $path, $m)) {
        $gModel = urldecode($m[1]);
        $gStream = $m[2] === 'streamGenerateContent';
        if ($gStream) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            echo "data: {\"candidates\":[{\"content\":{\"parts\":[{\"text\":\"你好\"}],\"role\":\"model\"}}]}\n\n";
            echo "data: {\"candidates\":[{\"content\":{\"parts\":[{\"text\":\"，Gemini\"}]}}],\"finishReason\":\"STOP\"}\n\n";
            echo "data: {\"candidates\":[{\"finishReason\":\"STOP\"}],\"usageMetadata\":{\"promptTokenCount\":12,\"candidatesTokenCount\":8,\"totalTokenCount\":20}}\n\n";
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode([
            'candidates' => [['content' => ['parts' => [['text' => '这是模拟 Gemini 回复，model=' . $gModel]], 'role' => 'model'], 'finishReason' => 'STOP']],
            'usageMetadata' => ['promptTokenCount' => 12, 'candidatesTokenCount' => 8, 'totalTokenCount' => 20],
            'modelVersion' => $gModel,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['error' => ['code' => 404, 'message' => 'Not Found', 'status' => 'NOT_FOUND']], JSON_UNESCAPED_UNICODE);
    exit;
}

/* Claude 格式：/v1/messages */
if (strpos($path, '/messages') !== false) {
    $usage = ['input_tokens' => 12, 'output_tokens' => 8];
    if (!empty($payload['stream'])) {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        echo "event: message_start\ndata: {\"type\":\"message_start\",\"message\":{\"id\":\"msg_mock\",\"type\":\"message\",\"role\":\"assistant\",\"content\":[],\"model\":\"$model\",\"stop_reason\":null,\"usage\":{\"input_tokens\":12,\"output_tokens\":0}}}\n\n";
        echo "event: content_block_start\ndata: {\"type\":\"content_block_start\",\"index\":0,\"content_block\":{\"type\":\"text\",\"text\":\"\"}}\n\n";
        echo "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"index\":0,\"delta\":{\"type\":\"text_delta\",\"text\":\"你好\"}}\n\n";
        echo "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"index\":0,\"delta\":{\"type\":\"text_delta\",\"text\":\"，Claude\"}}\n\n";
        echo "event: content_block_stop\ndata: {\"type\":\"content_block_stop\",\"index\":0}\n\n";
        echo "event: message_delta\ndata: {\"type\":\"message_delta\",\"delta\":{\"stop_reason\":\"end_turn\",\"stop_sequence\":null},\"usage\":{\"output_tokens\":8}}\n\n";
        echo "event: message_stop\ndata: {\"type\":\"message_stop\"}\n\n";
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode([
        'id' => 'msg_mock',
        'type' => 'message',
        'role' => 'assistant',
        'model' => $model,
        'content' => [['type' => 'text', 'text' => '这是模拟 Claude 回复，model=' . $model]],
        'stop_reason' => 'end_turn',
        'stop_sequence' => null,
        'usage' => $usage,
    ], JSON_UNESCAPED_UNICODE);
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
