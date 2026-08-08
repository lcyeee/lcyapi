<?php
header('Content-Type: application/json');
http_response_code(500);
echo json_encode(['error' => ['message' => '模拟上游故障（500）', 'type' => 'server_error']], JSON_UNESCAPED_UNICODE);