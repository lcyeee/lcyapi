<?php
/**
 * 模型价格预设库（离线内置版，参考各厂商公开定价，单位 $/1K tokens）
 * 结构：厂商 => [ 模型名 => ['input','output','cache_input'(可选,-1=同input),'ctx','maxout','type'] ]
 * type: chat/completion/embedding/image/audio
 */

function model_presets_all()
{
    $openai = [
        'gpt-4o' => ['input' => 0.0025, 'output' => 0.01, 'cache' => 0.00125, 'ctx' => 128000, 'maxout' => 16384, 'type' => 'chat'],
        'gpt-4o-2024-08-06' => ['input' => 0.0025, 'output' => 0.01, 'cache' => 0.00125, 'ctx' => 128000, 'maxout' => 16384, 'type' => 'chat'],
        'gpt-4o-mini' => ['input' => 0.00015, 'output' => 0.0006, 'cache' => 0.000075, 'ctx' => 128000, 'maxout' => 16384, 'type' => 'chat'],
        'gpt-4o-mini-2024-07-18' => ['input' => 0.00015, 'output' => 0.0006, 'cache' => 0.000075, 'ctx' => 128000, 'maxout' => 16384, 'type' => 'chat'],
        'gpt-4.1' => ['input' => 0.002, 'output' => 0.008, 'cache' => 0.0005, 'ctx' => 1047576, 'maxout' => 32768, 'type' => 'chat'],
        'gpt-4.1-mini' => ['input' => 0.0004, 'output' => 0.0016, 'cache' => 0.0001, 'ctx' => 1047576, 'maxout' => 32768, 'type' => 'chat'],
        'gpt-4.1-nano' => ['input' => 0.0001, 'output' => 0.0004, 'cache' => 0.000025, 'ctx' => 1047576, 'maxout' => 32768, 'type' => 'chat'],
        'gpt-4.5-preview' => ['input' => 0.075, 'output' => 0.15, 'cache' => 0.0375, 'ctx' => 128000, 'maxout' => 16384, 'type' => 'chat'],
        'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03, 'cache' => 0.003, 'ctx' => 128000, 'maxout' => 4096, 'type' => 'chat'],
        'gpt-4' => ['input' => 0.03, 'output' => 0.06, 'ctx' => 8192, 'maxout' => 4096, 'type' => 'chat'],
        'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015, 'ctx' => 16385, 'maxout' => 4096, 'type' => 'chat'],
        'o1' => ['input' => 0.015, 'output' => 0.06, 'cache' => 0.0075, 'ctx' => 200000, 'maxout' => 100000, 'type' => 'chat'],
        'o1-mini' => ['input' => 0.0011, 'output' => 0.0044, 'cache' => 0.00055, 'ctx' => 128000, 'maxout' => 65536, 'type' => 'chat'],
        'o3' => ['input' => 0.01, 'output' => 0.04, 'cache' => 0.0025, 'ctx' => 200000, 'maxout' => 100000, 'type' => 'chat'],
        'o3-mini' => ['input' => 0.0011, 'output' => 0.0044, 'cache' => 0.00055, 'ctx' => 200000, 'maxout' => 100000, 'type' => 'chat'],
        'chatgpt-4o-latest' => ['input' => 0.005, 'output' => 0.015, 'ctx' => 128000, 'maxout' => 16384, 'type' => 'chat'],
        'text-embedding-3-small' => ['input' => 0.00002, 'output' => 0, 'ctx' => 8191, 'maxout' => 0, 'type' => 'embedding'],
        'text-embedding-3-large' => ['input' => 0.00013, 'output' => 0, 'ctx' => 8191, 'maxout' => 0, 'type' => 'embedding'],
        'dall-e-3' => ['input' => 0.08, 'output' => 0, 'ctx' => 0, 'maxout' => 0, 'type' => 'image'],
        'dall-e-2' => ['input' => 0.02, 'output' => 0, 'ctx' => 0, 'maxout' => 0, 'type' => 'image'],
        'whisper-1' => ['input' => 0.006, 'output' => 0, 'ctx' => 0, 'maxout' => 0, 'type' => 'audio'],
        'tts-1' => ['input' => 0.015, 'output' => 0, 'ctx' => 0, 'maxout' => 0, 'type' => 'audio'],
        'tts-1-hd' => ['input' => 0.03, 'output' => 0, 'ctx' => 0, 'maxout' => 0, 'type' => 'audio'],
    ];
    $claude = [
        'claude-3-7-sonnet-20250219' => ['input' => 0.003, 'output' => 0.015, 'cache' => 0.0003, 'ctx' => 200000, 'maxout' => 64000, 'type' => 'chat'],
        'claude-3-5-sonnet-20241022' => ['input' => 0.003, 'output' => 0.015, 'cache' => 0.0003, 'ctx' => 200000, 'maxout' => 64000, 'type' => 'chat'],
        'claude-3-5-sonnet-20240620' => ['input' => 0.003, 'output' => 0.015, 'cache' => 0.0003, 'ctx' => 200000, 'maxout' => 8192, 'type' => 'chat'],
        'claude-3-5-haiku-20241022' => ['input' => 0.0008, 'output' => 0.004, 'cache' => 0.00008, 'ctx' => 200000, 'maxout' => 8192, 'type' => 'chat'],
        'claude-3-opus-20240229' => ['input' => 0.015, 'output' => 0.075, 'cache' => 0.0015, 'ctx' => 200000, 'maxout' => 4096, 'type' => 'chat'],
        'claude-3-haiku-20240307' => ['input' => 0.00025, 'output' => 0.00125, 'cache' => 0.000025, 'ctx' => 200000, 'maxout' => 4096, 'type' => 'chat'],
        'claude-3-sonnet-20240229' => ['input' => 0.003, 'output' => 0.015, 'ctx' => 200000, 'maxout' => 4096, 'type' => 'chat'],
    ];
    $gemini = [
        'gemini-2.5-pro' => ['input' => 0.00125, 'output' => 0.01, 'cache' => 0.0003125, 'ctx' => 1000000, 'maxout' => 65536, 'type' => 'chat'],
        'gemini-2.5-flash' => ['input' => 0.0003, 'output' => 0.0025, 'cache' => 0.000075, 'ctx' => 1000000, 'maxout' => 65536, 'type' => 'chat'],
        'gemini-2.0-flash' => ['input' => 0.0001, 'output' => 0.0004, 'cache' => 0.000025, 'ctx' => 1000000, 'maxout' => 8192, 'type' => 'chat'],
        'gemini-2.0-flash-lite' => ['input' => 0.000075, 'output' => 0.0003, 'cache' => 0.00001875, 'ctx' => 1000000, 'maxout' => 8192, 'type' => 'chat'],
        'gemini-1.5-pro' => ['input' => 0.00125, 'output' => 0.005, 'cache' => 0.0003125, 'ctx' => 2000000, 'maxout' => 8192, 'type' => 'chat'],
        'gemini-1.5-flash' => ['input' => 0.000075, 'output' => 0.0003, 'cache' => 0.00001875, 'ctx' => 1000000, 'maxout' => 8192, 'type' => 'chat'],
        'gemini-1.5-flash-8b' => ['input' => 0.0000375, 'output' => 0.00015, 'cache' => 0.000009375, 'ctx' => 1000000, 'maxout' => 8192, 'type' => 'chat'],
        'gemini-embedding-001' => ['input' => 0.000025, 'output' => 0, 'ctx' => 2048, 'maxout' => 0, 'type' => 'embedding'],
    ];
    $domestic = [
        'deepseek-chat' => ['input' => 0.00028, 'output' => 0.00042, 'cache' => 0.000028, 'ctx' => 65536, 'maxout' => 8192, 'type' => 'chat'],
        'deepseek-reasoner' => ['input' => 0.00055, 'output' => 0.00219, 'cache' => 0.000055, 'ctx' => 65536, 'maxout' => 8192, 'type' => 'chat'],
        'moonshot-v1-8k' => ['input' => 0.00012, 'output' => 0.00012, 'ctx' => 8192, 'maxout' => 8192, 'type' => 'chat'],
        'moonshot-v1-32k' => ['input' => 0.00024, 'output' => 0.00024, 'ctx' => 32768, 'maxout' => 8192, 'type' => 'chat'],
        'moonshot-v1-128k' => ['input' => 0.0006, 'output' => 0.0006, 'ctx' => 128000, 'maxout' => 8192, 'type' => 'chat'],
        'kimi-k2' => ['input' => 0.0006, 'output' => 0.0025, 'ctx' => 128000, 'maxout' => 128000, 'type' => 'chat'],
        'kimi-k2-thinking' => ['input' => 0.0006, 'output' => 0.0025, 'ctx' => 128000, 'maxout' => 128000, 'type' => 'chat'],
        'qwen-turbo' => ['input' => 0.00005, 'output' => 0.0002, 'ctx' => 1000000, 'maxout' => 8192, 'type' => 'chat'],
        'qwen-plus' => ['input' => 0.0001, 'output' => 0.0002, 'ctx' => 1000000, 'maxout' => 8192, 'type' => 'chat'],
        'qwen-max' => ['input' => 0.0012, 'output' => 0.002, 'ctx' => 32768, 'maxout' => 8192, 'type' => 'chat'],
        'glm-4-plus' => ['input' => 0.0005, 'output' => 0.0005, 'ctx' => 128000, 'maxout' => 8192, 'type' => 'chat'],
        'glm-4-air' => ['input' => 0.0001, 'output' => 0.0001, 'ctx' => 128000, 'maxout' => 8192, 'type' => 'chat'],
        'glm-4-flash' => ['input' => 0, 'output' => 0, 'ctx' => 128000, 'maxout' => 8192, 'type' => 'chat'],
        'ernie-4.0-turbo' => ['input' => 0.0008, 'output' => 0.0008, 'ctx' => 128000, 'maxout' => 4096, 'type' => 'chat'],
        'ernie-3.5-8k' => ['input' => 0.0004, 'output' => 0.0004, 'ctx' => 8192, 'maxout' => 2048, 'type' => 'chat'],
        'hunyuan-turbo' => ['input' => 0.0003, 'output' => 0.0009, 'ctx' => 32768, 'maxout' => 4096, 'type' => 'chat'],
        'doubao-1-5-pro-32k' => ['input' => 0.0008, 'output' => 0.002, 'ctx' => 32768, 'maxout' => 4096, 'type' => 'chat'],
        'doubao-1-5-lite-32k' => ['input' => 0.0003, 'output' => 0.0006, 'ctx' => 32768, 'maxout' => 4096, 'type' => 'chat'],
        'MiniMax-Text-01' => ['input' => 0.0002, 'output' => 0.0011, 'ctx' => 1000000, 'maxout' => 65536, 'type' => 'chat'],
        'grok-2' => ['input' => 0.002, 'output' => 0.01, 'ctx' => 131072, 'maxout' => 16384, 'type' => 'chat'],
        'grok-beta' => ['input' => 0.00015, 'output' => 0.0006, 'ctx' => 131072, 'maxout' => 16384, 'type' => 'chat'],
        'bge-reranker-v2-m3' => ['input' => 0.00002, 'output' => 0, 'ctx' => 8191, 'maxout' => 0, 'type' => 'embedding'],
    ];
    return [
        'openai' => ['label' => 'OpenAI', 'models' => $openai],
        'claude' => ['label' => 'Anthropic Claude', 'models' => $claude],
        'gemini' => ['label' => 'Google Gemini', 'models' => $gemini],
        'domestic' => ['label' => '国产模型（DeepSeek/Kimi/Qwen/GLM 等）', 'models' => $domestic],
    ];
}