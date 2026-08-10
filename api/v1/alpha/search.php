<?php
/**
 * /v1/alpha/search - 联网搜索端点（简版 Alpha Search）
 * 接收 OpenAI 聊天请求，追加搜索结果后转发
 */
if (!defined('API_REQUEST')) {
    define('API_REQUEST', true);
}
if (!defined('ROOT_PATH')) {
    require dirname(__DIR__, 3) . '/includes/bootstrap.php';
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'Invalid JSON', 'type' => 'invalid_request_error', 'code' => 'invalid_json']], JSON_UNESCAPED_UNICODE);
    exit;
}

$searchQuery = '';
$messages = $payload['messages'] ?? [];
$lastMsg = end($messages);
if (is_array($lastMsg) && isset($lastMsg['content'])) {
    $searchQuery = is_string($lastMsg['content']) ? $lastMsg['content'] : (is_array($lastMsg['content']) ? ($lastMsg['content'][0]['text'] ?? '') : '');
}

/* 简单搜索：用 DuckDuckGo 或直接返回提示 */
$searchResults = '';
if ($searchQuery !== '') {
    $searchUrl = 'https://api.duckduckgo.com/?q=' . urlencode($searchQuery) . '&format=json&no_html=1&skip_disambig=1';
    $ch = curl_init($searchUrl);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_USERAGENT => 'lcyapi/1.0']);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200) {
        $json = json_decode((string)$resp, true);
        if (is_array($json) && !empty($json['AbstractText'])) {
            $searchResults = $json['AbstractText'];
        } elseif (is_array($json) && !empty($json['RelatedTopics'])) {
            $items = array_slice($json['RelatedTopics'], 0, 5);
            $texts = [];
            foreach ($items as $item) {
                if (isset($item['Text'])) {
                    $texts[] = $item['Text'];
                }
            }
            $searchResults = implode("\n", $texts);
        }
    }
}

/* 将搜索结果注入系统消息 */
if ($searchResults !== '') {
    $payload['messages'] = array_merge([
        ['role' => 'system', 'content' => '以下是实时搜索结果，请基于此回答用户问题：' . "\n" . $searchResults]
    ], $payload['messages']);
}

/* 去掉 stream 选项，简化 */
$payload['stream'] = false;

/* 转发到 relay */
require dirname(__DIR__, 3) . '/api/v1/chat/completions.php';