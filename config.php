<?php
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'lcyapi',
        'user' => 'root',
        'pass' => 'root',
        'charset' => 'utf8mb4',
    ],
    'site' => [
        'name' => 'lcyapi',
        'url' => 'http://localhost:8000',
        'description' => 'AI 模型网关',
        'register_enabled' => true,
        'default_quota' => 0.0000,
    ],
    'security' => [
        'session_lifetime' => 86400,
        'login_attempts' => 5,
        'login_lock_time' => 900,
        'api_rate_limit' => 60,
        'api_rate_window' => 60,
    ],
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'path' => '',
    ],
    'log' => [
        'level' => 'error',
        'path' => '',
        'save_request_body' => false,
    ],
    'relay' => [
        'timeout' => 120,
        'retry_count' => 0,
        'stream_enabled' => true,
        'auto_disable' => false,
        'auto_disable_threshold' => 100,
    ],
    'app' => [
        'debug' => false,
    ],
    'timezone' => 'Asia/Shanghai',
];