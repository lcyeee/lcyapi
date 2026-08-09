<?php
/**
 * 格式转换器（对齐 lcyapi）：OpenAI Chat ⇄ Claude Messages ⇄ Gemini generateContent
 * 覆盖：请求体转换、非流式响应转换、SSE 流式转换
 */

class Converter
{
    /* ============ 请求转换：OpenAI → Claude ============ */

    public static function openaiToClaude($p)
    {
        $out = ['model' => isset($p['model']) ? $p['model'] : ''];
        $out['max_tokens'] = isset($p['max_tokens']) ? (int)$p['max_tokens'] : (int)(isset($p['max_completion_tokens']) ? $p['max_completion_tokens'] : 4096);

        $system = [];
        $messages = [];
        foreach ((array)($p['messages'] ?? []) as $msg) {
            $role = isset($msg['role']) ? $msg['role'] : 'user';
            $content = isset($msg['content']) ? $msg['content'] : '';
            if ($role === 'system') {
                $system[] = is_array($content) ? json_encode($content, JSON_UNESCAPED_UNICODE) : (string)$content;
                continue;
            }
            if ($role === 'tool') {
                $messages[] = [
                    'role' => 'user',
                    'content' => [[
                        'type' => 'tool_result',
                        'tool_use_id' => isset($msg['tool_call_id']) ? $msg['tool_call_id'] : '',
                        'content' => is_array($content) ? json_encode($content, JSON_UNESCAPED_UNICODE) : (string)$content,
                    ]],
                ];
                continue;
            }
            if ($role === 'assistant' && isset($msg['tool_calls']) && is_array($msg['tool_calls'])) {
                $parts = [];
                if ($content !== '' && $content !== null) {
                    $parts[] = ['type' => 'text', 'text' => is_array($content) ? json_encode($content, JSON_UNESCAPED_UNICODE) : (string)$content];
                }
                foreach ($msg['tool_calls'] as $tc) {
                    $fn = isset($tc['function']) ? $tc['function'] : [];
                    $parts[] = [
                        'type' => 'tool_use',
                        'id' => isset($tc['id']) ? $tc['id'] : 'toolu_' . bin2hex(random_bytes(8)),
                        'name' => isset($fn['name']) ? $fn['name'] : '',
                        'input' => isset($fn['arguments']) ? (json_decode($fn['arguments'], true) ?: []) : [],
                    ];
                }
                $messages[] = ['role' => 'assistant', 'content' => $parts];
                continue;
            }
            if (is_array($content)) {
                $parts = [];
                foreach ($content as $part) {
                    if (!is_array($part)) {
                        $parts[] = ['type' => 'text', 'text' => (string)$part];
                        continue;
                    }
                    $type = isset($part['type']) ? $part['type'] : 'text';
                    if ($type === 'image_url' && isset($part['image_url'])) {
                        $url = is_array($part['image_url']) ? ($part['image_url']['url'] ?? '') : $part['image_url'];
                        if (starts_with((string)$url, 'data:')) {
                            $parts[] = ['type' => 'image', 'source' => self::dataUrlToSource($url)];
                        } else {
                            $parts[] = ['type' => 'image', 'source' => ['type' => 'url', 'url' => $url]];
                        }
                    } elseif ($type === 'text') {
                        $parts[] = ['type' => 'text', 'text' => (string)($part['text'] ?? '')];
                    }
                }
                $messages[] = ['role' => $role, 'content' => $parts];
            } else {
                $messages[] = ['role' => $role, 'content' => (string)$content];
            }
        }
        if (!empty($system)) {
            $out['system'] = count($system) === 1 ? $system[0] : $system;
        }
        $out['messages'] = $messages;

        $copy = ['temperature', 'top_p', 'top_k', 'stop_sequences', 'stream', 'metadata'];
        foreach ($copy as $key) {
            if (isset($p[$key])) {
                $out[$key] = $p[$key];
            }
        }
        if (isset($p['stop'])) {
            $out['stop_sequences'] = is_array($p['stop']) ? $p['stop'] : [$p['stop']];
        }
        if (isset($p['stream_options']) && !empty($p['stream_options']['include_usage'])) {
            $out['stream_options'] = ['include_usage' => true];
        }
        if (isset($p['tools']) && is_array($p['tools'])) {
            $out['tools'] = array_values(array_filter(array_map(function ($t) {
                $fn = isset($t['function']) ? $t['function'] : [];
                if (!isset($fn['name'])) {
                    return null;
                }
                $tool = ['name' => $fn['name']];
                if (isset($fn['description'])) {
                    $tool['description'] = $fn['description'];
                }
                if (isset($fn['parameters'])) {
                    $tool['input_schema'] = $fn['parameters'];
                }
                return $tool;
            }, $p['tools'])));
            if (isset($p['tool_choice'])) {
                $out['tool_choice'] = self::openaiToolChoiceToClaude($p['tool_choice']);
            }
        }
        if (isset($p['thinking']) && is_array($p['thinking'])) {
            $out['thinking'] = $p['thinking'];
        }
        return $out;
    }

    private static function openaiToolChoiceToClaude($choice)
    {
        if (is_string($choice)) {
            return $choice === 'required' ? 'any' : $choice;
        }
        if (is_array($choice) && ($choice['type'] ?? '') === 'function') {
            return ['type' => 'tool', 'name' => $choice['function']['name'] ?? ''];
        }
        return 'auto';
    }

    private static function dataUrlToSource($url)
    {
        if (preg_match('#^data:image/(png|jpeg|jpg|gif|webp);base64,(.+)$#i', $url, $m)) {
            return [
                'type' => 'base64',
                'media_type' => 'image/' . ($m[1] === 'jpg' ? 'jpeg' : $m[1]),
                'data' => $m[2],
            ];
        }
        return ['type' => 'url', 'url' => $url];
    }

    /* ============ 请求转换：Claude → OpenAI ============ */

    public static function claudeToOpenAI($p)
    {
        $out = ['model' => isset($p['model']) ? $p['model'] : ''];
        $messages = [];
        if (isset($p['system'])) {
            $systemText = is_array($p['system'])
                ? implode("\n", array_map(function ($s) {
                    return is_array($s) ? (string)($s['text'] ?? '') : (string)$s;
                }, $p['system']))
                : (string)$p['system'];
            if ($systemText !== '') {
                $messages[] = ['role' => 'system', 'content' => $systemText];
            }
        }
        foreach ((array)($p['messages'] ?? []) as $msg) {
            $role = isset($msg['role']) ? $msg['role'] : 'user';
            $content = isset($msg['content']) ? $msg['content'] : '';
            if ($role === 'assistant' && is_array($content)) {
                $text = [];
                $toolCalls = [];
                foreach ($content as $block) {
                    if (!is_array($block)) {
                        $text[] = (string)$block;
                        continue;
                    }
                    $type = isset($block['type']) ? $block['type'] : 'text';
                    if ($type === 'text') {
                        $text[] = (string)($block['text'] ?? '');
                    } elseif ($type === 'tool_use') {
                        $toolCalls[] = [
                            'id' => isset($block['id']) ? $block['id'] : '',
                            'type' => 'function',
                            'function' => [
                                'name' => isset($block['name']) ? $block['name'] : '',
                                'arguments' => json_encode(isset($block['input']) ? $block['input'] : [], JSON_UNESCAPED_UNICODE),
                            ],
                        ];
                    } elseif ($type === 'thinking') {
                        $text[] = isset($block['thinking']) ? (string)$block['thinking'] : '';
                    }
                }
                $m = ['role' => 'assistant'];
                $m['content'] = implode('', $text);
                if (!empty($toolCalls)) {
                    $m['tool_calls'] = $toolCalls;
                }
                $messages[] = $m;
                continue;
            }
            if ($role === 'user' && is_array($content)) {
                $openaiContent = [];
                $toolResult = null;
                foreach ($content as $block) {
                    if (!is_array($block)) {
                        $openaiContent[] = ['type' => 'text', 'text' => (string)$block];
                        continue;
                    }
                    $type = isset($block['type']) ? $block['type'] : 'text';
                    if ($type === 'text') {
                        $openaiContent[] = ['type' => 'text', 'text' => (string)($block['text'] ?? '')];
                    } elseif ($type === 'image') {
                        $src = isset($block['source']) ? $block['source'] : [];
                        if (isset($src['type']) && $src['type'] === 'url') {
                            $openaiContent[] = ['type' => 'image_url', 'image_url' => ['url' => $src['url']]];
                        } elseif (isset($src['type']) && $src['type'] === 'base64') {
                            $openaiContent[] = [
                                'type' => 'image_url',
                                'image_url' => ['url' => 'data:' . ($src['media_type'] ?? 'image/png') . ';base64,' . ($src['data'] ?? '')],
                            ];
                        }
                    } elseif ($type === 'tool_result') {
                        $toolResult = [
                            'role' => 'tool',
                            'tool_call_id' => isset($block['tool_use_id']) ? $block['tool_use_id'] : '',
                            'content' => is_array($block['content'] ?? null) ? json_encode($block['content'], JSON_UNESCAPED_UNICODE) : (string)($block['content'] ?? ''),
                        ];
                    }
                }
                if ($toolResult !== null) {
                    $messages[] = $toolResult;
                } else {
                    $messages[] = ['role' => 'user', 'content' => $openaiContent];
                }
                continue;
            }
            $messages[] = ['role' => $role, 'content' => is_array($content) ? json_encode($content, JSON_UNESCAPED_UNICODE) : (string)$content];
        }
        if (isset($p['max_tokens'])) {
            $out['max_tokens'] = (int)$p['max_tokens'];
        }
        $out['messages'] = $messages;
        foreach (['temperature', 'top_p', 'stream'] as $key) {
            if (isset($p[$key])) {
                $out[$key] = $p[$key];
            }
        }
        if (isset($p['stop_sequences'])) {
            $out['stop'] = count($p['stop_sequences']) === 1 ? $p['stop_sequences'][0] : $p['stop_sequences'];
        }
        if (isset($p['tools']) && is_array($p['tools'])) {
            $out['tools'] = array_values(array_filter(array_map(function ($t) {
                if (!isset($t['name'])) {
                    return null;
                }
                $fn = ['name' => $t['name']];
                if (isset($t['description'])) {
                    $fn['description'] = $t['description'];
                }
                if (isset($t['input_schema'])) {
                    $fn['parameters'] = $t['input_schema'];
                }
                return ['type' => 'function', 'function' => $fn];
            }, $p['tools'])));
            if (isset($p['tool_choice'])) {
                $out['tool_choice'] = self::claudeToolChoiceToOpenAI($p['tool_choice']);
            }
        }
        return $out;
    }

    private static function claudeToolChoiceToOpenAI($choice)
    {
        if (is_string($choice)) {
            return $choice === 'any' ? 'required' : $choice;
        }
        if (is_array($choice) && ($choice['type'] ?? '') === 'tool') {
            return ['type' => 'function', 'function' => ['name' => $choice['name'] ?? '']];
        }
        return 'auto';
    }

    /* ============ 请求转换：OpenAI → Gemini ============ */

    public static function openaiToGemini($p)
    {
        $out = [];
        $contents = [];
        $system = [];
        foreach ((array)($p['messages'] ?? []) as $msg) {
            $role = isset($msg['role']) ? $msg['role'] : 'user';
            $content = isset($msg['content']) ? $msg['content'] : '';
            if ($role === 'system') {
                $system[] = ['text' => is_array($content) ? json_encode($content, JSON_UNESCAPED_UNICODE) : (string)$content];
                continue;
            }
            $gRole = $role === 'assistant' ? 'model' : 'user';
            $parts = [];
            if ($role === 'tool' && isset($msg['tool_call_id'])) {
                $parts[] = [
                    'functionResponse' => [
                        'name' => isset($msg['name']) ? $msg['name'] : 'tool',
                        'response' => ['result' => is_array($content) ? $content : (string)$content],
                    ],
                ];
            } elseif (is_array($content)) {
                foreach ($content as $part) {
                    if (!is_array($part)) {
                        $parts[] = ['text' => (string)$part];
                        continue;
                    }
                    $type = isset($part['type']) ? $part['type'] : 'text';
                    if ($type === 'text') {
                        $parts[] = ['text' => (string)($part['text'] ?? '')];
                    } elseif ($type === 'image_url') {
                        $url = is_array($part['image_url']) ? ($part['image_url']['url'] ?? '') : $part['image_url'];
                        if (preg_match('#^data:image/(\w+);base64,(.+)$#i', (string)$url, $m)) {
                            $parts[] = ['inlineData' => ['mimeType' => 'image/' . $m[1], 'data' => $m[2]]];
                        }
                    }
                }
            } else {
                $parts[] = ['text' => (string)$content];
            }
            if (!empty($parts)) {
                $contents[] = ['role' => $gRole, 'parts' => $parts];
            }
        }
        if (!empty($system)) {
            $out['systemInstruction'] = ['parts' => $system];
        }
        $out['contents'] = $contents;

        $gen = [];
        foreach ([['temperature', 'temperature'], ['top_p', 'topP'], ['max_tokens', 'maxOutputTokens'], ['max_completion_tokens', 'maxOutputTokens'], ['top_k', 'topK']] as $pair) {
            if (isset($p[$pair[0]])) {
                $gen[$pair[1]] = $p[$pair[0]];
            }
        }
        if (!empty($gen)) {
            $out['generationConfig'] = $gen;
        }
        if (isset($p['stream'])) {
            $out['stream'] = (bool)$p['stream'];
        }
        if (isset($p['tools']) && is_array($p['tools'])) {
            $decl = [];
            foreach ($p['tools'] as $t) {
                $fn = isset($t['function']) ? $t['function'] : [];
                if (isset($fn['name'])) {
                    $d = ['name' => $fn['name']];
                    if (isset($fn['description'])) {
                        $d['description'] = $fn['description'];
                    }
                    if (isset($fn['parameters'])) {
                        $d['parameters'] = $fn['parameters'];
                    }
                    $decl[] = $d;
                }
            }
            if (!empty($decl)) {
                $out['tools'] = [['functionDeclarations' => $decl]];
            }
        }
        return $out;
    }

    /* ============ 非流式响应转换：Claude → OpenAI ============ */

    public static function claudeResponseToOpenAI($json)
    {
        $r = json_decode($json, true);
        if (!is_array($r)) {
            return $json;
        }
        if (isset($r['error'])) {
            $err = $r['error'];
            $out = [
                'error' => [
                    'message' => isset($err['message']) ? $err['message'] : 'upstream error',
                    'type' => isset($err['type']) ? $err['type'] : 'api_error',
                    'code' => 'upstream_error',
                ],
            ];
            return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $content = '';
        $toolCalls = [];
        foreach ((array)($r['content'] ?? []) as $block) {
            if (!is_array($block)) {
                continue;
            }
            $type = isset($block['type']) ? $block['type'] : '';
            if ($type === 'text') {
                $content .= (string)($block['text'] ?? '');
            } elseif ($type === 'tool_use') {
                $toolCalls[] = [
                    'id' => isset($block['id']) ? $block['id'] : '',
                    'type' => 'function',
                    'function' => [
                        'name' => isset($block['name']) ? $block['name'] : '',
                        'arguments' => json_encode(isset($block['input']) ? $block['input'] : [], JSON_UNESCAPED_UNICODE),
                    ],
                ];
            }
        }
        $choice = [
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => $content],
            'finish_reason' => self::claudeStopReason((isset($r['stop_reason']) ? $r['stop_reason'] : '')),
        ];
        if (!empty($toolCalls)) {
            $choice['message']['tool_calls'] = $toolCalls;
        }
        $usage = isset($r['usage']) ? $r['usage'] : [];
        $out = [
            'id' => isset($r['id']) ? $r['id'] : ('chatcmpl-' . bin2hex(random_bytes(8))),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => isset($r['model']) ? $r['model'] : '',
            'choices' => [$choice],
            'usage' => [
                'prompt_tokens' => isset($usage['input_tokens']) ? (int)$usage['input_tokens'] : 0,
                'completion_tokens' => isset($usage['output_tokens']) ? (int)$usage['output_tokens'] : 0,
                'total_tokens' => (isset($usage['input_tokens']) ? (int)$usage['input_tokens'] : 0) + (isset($usage['output_tokens']) ? (int)$usage['output_tokens'] : 0),
            ],
        ];
        return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /* ============ 非流式响应转换：Gemini → OpenAI ============ */

    public static function geminiResponseToOpenAI($json)
    {
        $r = json_decode($json, true);
        if (!is_array($r)) {
            return $json;
        }
        if (isset($r['error'])) {
            $err = $r['error'];
            return json_encode([
                'error' => [
                    'message' => isset($err['message']) ? $err['message'] : 'upstream error',
                    'type' => isset($err['status']) ? $err['status'] : 'api_error',
                    'code' => 'upstream_error',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $text = '';
        $toolCalls = [];
        $finishReason = isset($r['candidates'][0]['finishReason']) ? $r['candidates'][0]['finishReason'] : '';
        foreach ((array)($r['candidates'][0]['content']['parts'] ?? []) as $part) {
            if (!is_array($part)) {
                continue;
            }
            if (isset($part['text'])) {
                $text .= (string)$part['text'];
            }
            if (isset($part['functionCall'])) {
                $toolCalls[] = [
                    'id' => 'call_' . bin2hex(random_bytes(8)),
                    'type' => 'function',
                    'function' => [
                        'name' => isset($part['functionCall']['name']) ? $part['functionCall']['name'] : '',
                        'arguments' => json_encode(isset($part['functionCall']['args']) ? $part['functionCall']['args'] : [], JSON_UNESCAPED_UNICODE),
                    ],
                ];
            }
        }
        $choice = [
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => $text],
            'finish_reason' => self::geminiFinishReason($finishReason),
        ];
        if (!empty($toolCalls)) {
            $choice['message']['tool_calls'] = $toolCalls;
        }
        $usage = isset($r['usageMetadata']) ? $r['usageMetadata'] : [];
        $out = [
            'id' => 'chatcmpl-' . bin2hex(random_bytes(8)),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => isset($r['modelVersion']) ? $r['modelVersion'] : '',
            'choices' => [$choice],
            'usage' => [
                'prompt_tokens' => isset($usage['promptTokenCount']) ? (int)$usage['promptTokenCount'] : 0,
                'completion_tokens' => isset($usage['candidatesTokenCount']) ? (int)$usage['candidatesTokenCount'] : 0,
                'total_tokens' => isset($usage['totalTokenCount']) ? (int)$usage['totalTokenCount'] : 0,
            ],
        ];
        return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /* ============ 非流式响应转换：OpenAI → Claude ============ */

    public static function openaiResponseToClaude($json)
    {
        $r = json_decode($json, true);
        if (!is_array($r)) {
            return $json;
        }
        if (isset($r['error'])) {
            return json_encode([
                'type' => 'error',
                'error' => [
                    'type' => isset($r['error']['type']) ? $r['error']['type'] : 'api_error',
                    'message' => isset($r['error']['message']) ? $r['error']['message'] : 'upstream error',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $content = [];
        $text = isset($r['choices'][0]['message']['content']) ? $r['choices'][0]['message']['content'] : '';
        if ($text !== '' && $text !== null) {
            $content[] = ['type' => 'text', 'text' => (string)$text];
        }
        foreach ((array)($r['choices'][0]['message']['tool_calls'] ?? []) as $tc) {
            $fn = isset($tc['function']) ? $tc['function'] : [];
            $content[] = [
                'type' => 'tool_use',
                'id' => isset($tc['id']) ? $tc['id'] : '',
                'name' => isset($fn['name']) ? $fn['name'] : '',
                'input' => isset($fn['arguments']) ? (json_decode($fn['arguments'], true) ?: []) : [],
            ];
        }
        $usage = isset($r['usage']) ? $r['usage'] : [];
        $out = [
            'id' => isset($r['id']) ? $r['id'] : 'msg_' . bin2hex(random_bytes(8)),
            'type' => 'message',
            'role' => 'assistant',
            'model' => isset($r['model']) ? $r['model'] : '',
            'content' => $content,
            'stop_reason' => self::openaiStopReason(isset($r['choices'][0]['finish_reason']) ? $r['choices'][0]['finish_reason'] : ''),
            'stop_sequence' => null,
            'usage' => [
                'input_tokens' => isset($usage['prompt_tokens']) ? (int)$usage['prompt_tokens'] : 0,
                'output_tokens' => isset($usage['completion_tokens']) ? (int)$usage['completion_tokens'] : 0,
            ],
        ];
        return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /* ============ 流式转换：Claude SSE → OpenAI SSE ============ */

    public static function makeClaudeStreamToOpenAI($model)
    {
        $state = [
            'id' => 'chatcmpl-' . bin2hex(random_bytes(8)),
            'started' => false,
            'done' => false,
            'usage' => null,
            'buffer' => '',
        ];
        $transform = function ($chunk) use (&$state, $model) {
            $out = '';
            $state['buffer'] .= $chunk;
            $lines = explode("\n", $state['buffer']);
            $state['buffer'] = array_pop($lines);
            foreach ($lines as $line) {
                if (strncmp($line, 'data: ', 6) !== 0) {
                    continue;
                }
                $json = json_decode(substr($line, 6), true);
                if (!is_array($json)) {
                    continue;
                }
                $type = isset($json['type']) ? $json['type'] : '';
                if ($type === 'message_start') {
                    $state['id'] = isset($json['message']['id']) ? $json['message']['id'] : $state['id'];
                } elseif ($type === 'content_block_delta') {
                    $delta = isset($json['delta']) ? $json['delta'] : [];
                    $dType = isset($delta['type']) ? $delta['type'] : '';
                    if ($dType === 'text_delta') {
                        if (!$state['started']) {
                            $out .= self::sseChunk($state['id'], $model, [0 => ['delta' => ['role' => 'assistant', 'content' => '']]]);
                            $state['started'] = true;
                        }
                        $out .= self::sseChunk($state['id'], $model, [0 => ['delta' => ['content' => (string)$delta['text']]]]);
                    } elseif ($dType === 'thinking_delta') {
                        if (!$state['started']) {
                            $out .= self::sseChunk($state['id'], $model, [0 => ['delta' => ['role' => 'assistant', 'content' => '']]]);
                            $state['started'] = true;
                        }
                        $out .= self::sseChunk($state['id'], $model, [0 => ['delta' => ['reasoning_content' => (string)$delta['thinking']]]]);
                    } elseif ($dType === 'input_json_delta') {
                        if (!$state['started']) {
                            $out .= self::sseChunk($state['id'], $model, [0 => ['delta' => ['role' => 'assistant', 'content' => '']]]);
                            $state['started'] = true;
                        }
                        $out .= self::sseChunk($state['id'], $model, [0 => ['delta' => ['tool_calls' => [['index' => 0, 'function' => ['arguments' => (string)$delta['partial_json']]]]]]]);
                    }
                } elseif ($type === 'message_delta') {
                    $state['usage'] = isset($json['usage']) ? $json['usage'] : $state['usage'];
                }
            }
            return $out;
        };
        $finish = function () use (&$state, $model) {
            $out = '';
            if (!$state['started']) {
                $out .= self::sseChunk($state['id'], $model, [0 => ['delta' => ['role' => 'assistant', 'content' => '']]]);
            }
            $usage = null;
            if (is_array($state['usage'])) {
                $usage = [
                    'prompt_tokens' => isset($state['usage']['input_tokens']) ? (int)$state['usage']['input_tokens'] : 0,
                    'completion_tokens' => isset($state['usage']['output_tokens']) ? (int)$state['usage']['output_tokens'] : 0,
                    'total_tokens' => (isset($state['usage']['input_tokens']) ? (int)$state['usage']['input_tokens'] : 0) + (isset($state['usage']['output_tokens']) ? (int)$state['usage']['output_tokens'] : 0),
                ];
            }
            $out .= self::sseChunk($state['id'], $model, [], $usage);
            $out .= "data: [DONE]\n\n";
            return $out;
        };
        return ['transform' => $transform, 'finish' => $finish];
    }

    /* ============ 流式转换：OpenAI SSE → Claude SSE ============ */

    public static function makeOpenAIStreamToClaude($model)
    {
        $state = [
            'started' => false,
            'blockStarted' => false,
            'done' => false,
            'usage' => null,
            'buffer' => '',
            'id' => 'msg_' . bin2hex(random_bytes(8)),
            'text' => '',
            'thinking' => '',
            'toolCalls' => [],
        ];
        $transform = function ($chunk) use (&$state, $model) {
            $out = '';
            $state['buffer'] .= $chunk;
            $lines = explode("\n", $state['buffer']);
            $state['buffer'] = array_pop($lines);
            foreach ($lines as $line) {
                if (strncmp($line, 'data: ', 6) !== 0) {
                    continue;
                }
                $payload = substr($line, 6);
                if (trim($payload) === '[DONE]') {
                    continue;
                }
                $json = json_decode($payload, true);
                if (!is_array($json)) {
                    continue;
                }
                $delta = isset($json['choices'][0]['delta']) ? $json['choices'][0]['delta'] : [];
                $finish = isset($json['choices'][0]['finish_reason']) ? $json['choices'][0]['finish_reason'] : null;
                if (isset($json['usage'])) {
                    $state['usage'] = $json['usage'];
                }
                if (!$state['started']) {
                    $out .= self::claudeEvent('message_start', [
                        'type' => 'message_start',
                        'message' => [
                            'id' => $state['id'],
                            'type' => 'message',
                            'role' => 'assistant',
                            'content' => [],
                            'model' => $model,
                            'stop_reason' => null,
                            'usage' => ['input_tokens' => 0, 'output_tokens' => 0],
                        ],
                    ]);
                    $out .= self::claudeEvent('content_block_start', [
                        'type' => 'content_block_start',
                        'index' => 0,
                        'content_block' => ['type' => 'text', 'text' => ''],
                    ]);
                    $state['started'] = true;
                    $state['blockStarted'] = true;
                }
                if (isset($delta['reasoning_content']) && $delta['reasoning_content'] !== '') {
                    $state['thinking'] .= (string)$delta['reasoning_content'];
                    $out .= self::claudeEvent('content_block_delta', [
                        'type' => 'content_block_delta',
                        'index' => 0,
                        'delta' => ['type' => 'thinking_delta', 'thinking' => (string)$delta['reasoning_content']],
                    ]);
                }
                if (isset($delta['content']) && $delta['content'] !== '') {
                    $state['text'] .= (string)$delta['content'];
                    $out .= self::claudeEvent('content_block_delta', [
                        'type' => 'content_block_delta',
                        'index' => 0,
                        'delta' => ['type' => 'text_delta', 'text' => (string)$delta['content']],
                    ]);
                }
                if (isset($delta['tool_calls']) && is_array($delta['tool_calls'])) {
                    foreach ($delta['tool_calls'] as $tc) {
                        if (isset($tc['function']) && isset($tc['function']['arguments'])) {
                            $state['toolCalls'][] = $tc['function']['arguments'];
                            $out .= self::claudeEvent('content_block_delta', [
                                'type' => 'content_block_delta',
                                'index' => 0,
                                'delta' => ['type' => 'input_json_delta', 'partial_json' => (string)$tc['function']['arguments']],
                            ]);
                        }
                    }
                }
                if ($finish !== null) {
                    $out .= self::claudeEvent('content_block_stop', ['type' => 'content_block_stop', 'index' => 0]);
                    $state['blockStarted'] = false;
                }
            }
            return $out;
        };
        $finish = function () use (&$state, $model) {
            $out = '';
            if (!$state['started']) {
                return $out;
            }
            if ($state['blockStarted']) {
                $out .= self::claudeEvent('content_block_stop', ['type' => 'content_block_stop', 'index' => 0]);
                $state['blockStarted'] = false;
            }
            $outputTokens = isset($state['usage']['completion_tokens']) ? (int)$state['usage']['completion_tokens'] : 0;
            $out .= self::claudeEvent('message_delta', [
                'type' => 'message_delta',
                'delta' => ['stop_reason' => 'end_turn', 'stop_sequence' => null],
                'usage' => ['output_tokens' => $outputTokens],
            ]);
            $out .= self::claudeEvent('message_stop', ['type' => 'message_stop']);
            return $out;
        };
        return ['transform' => $transform, 'finish' => $finish];
    }

    /* ============ 流式转换：Gemini SSE → OpenAI SSE ============ */

    public static function makeGeminiStreamToOpenAI($model)
    {
        $state = ['started' => false, 'usage' => null, 'buffer' => '', 'id' => 'chatcmpl-' . bin2hex(random_bytes(8))];
        $transform = function ($chunk) use (&$state, $model) {
            $out = '';
            $state['buffer'] .= $chunk;
            $lines = explode("\n", $state['buffer']);
            $state['buffer'] = array_pop($lines);
            foreach ($lines as $line) {
                if (strncmp($line, 'data: ', 6) !== 0) {
                    continue;
                }
                $payload = substr($line, 6);
                if (trim($payload) === '[DONE]') {
                    continue;
                }
                $json = json_decode($payload, true);
                if (!is_array($json)) {
                    continue;
                }
                if (isset($json['usageMetadata'])) {
                    $state['usage'] = $json['usageMetadata'];
                }
                foreach ((array)($json['candidates'] ?? []) as $cand) {
                    foreach ((array)($cand['content']['parts'] ?? []) as $part) {
                        if (isset($part['text']) && $part['text'] !== '') {
                            if (!$state['started']) {
                                $out .= self::sseChunk($state['id'], $model, [0 => ['delta' => ['role' => 'assistant', 'content' => '']]]);
                                $state['started'] = true;
                            }
                            $out .= self::sseChunk($state['id'], $model, [0 => ['delta' => ['content' => (string)$part['text']]]]);
                        }
                        if (isset($part['functionCall'])) {
                            if (!$state['started']) {
                                $out .= self::sseChunk($state['id'], $model, [0 => ['delta' => ['role' => 'assistant', 'content' => '']]]);
                                $state['started'] = true;
                            }
                            $out .= self::sseChunk($state['id'], $model, [0 => ['delta' => ['tool_calls' => [['index' => 0, 'id' => 'call_' . bin2hex(random_bytes(8)), 'type' => 'function', 'function' => ['name' => isset($part['functionCall']['name']) ? $part['functionCall']['name'] : '', 'arguments' => json_encode(isset($part['functionCall']['args']) ? $part['functionCall']['args'] : [], JSON_UNESCAPED_UNICODE)]]]]]]);
                        }
                    }
                    if (isset($cand['finishReason'])) {
                        $out .= self::sseChunk($state['id'], $model, [0 => ['delta' => [], 'finish_reason' => self::geminiFinishReason($cand['finishReason'])]]);
                    }
                }
            }
            return $out;
        };
        $finish = function () use (&$state, $model) {
            $out = '';
            $usage = null;
            if (is_array($state['usage'])) {
                $usage = [
                    'prompt_tokens' => isset($state['usage']['promptTokenCount']) ? (int)$state['usage']['promptTokenCount'] : 0,
                    'completion_tokens' => isset($state['usage']['candidatesTokenCount']) ? (int)$state['usage']['candidatesTokenCount'] : 0,
                    'total_tokens' => isset($state['usage']['totalTokenCount']) ? (int)$state['usage']['totalTokenCount'] : 0,
                ];
            }
            if (!$state['started']) {
                $out .= self::sseChunk($state['id'], $model, [0 => ['delta' => ['role' => 'assistant', 'content' => ''], 'finish_reason' => 'stop']]);
            }
            $out .= self::sseChunk($state['id'], $model, [], $usage);
            $out .= "data: [DONE]\n\n";
            return $out;
        };
        return ['transform' => $transform, 'finish' => $finish];
    }

    /* ============ 流式转换：OpenAI SSE → Gemini SSE ============ */

    public static function makeOpenAIStreamToGemini($model)
    {
        $state = ['started' => false, 'usage' => null, 'buffer' => '', 'hasFinish' => false];
        $transform = function ($chunk) use (&$state, $model) {
            $out = '';
            $state['buffer'] .= $chunk;
            $lines = explode("\n", $state['buffer']);
            $state['buffer'] = array_pop($lines);
            foreach ($lines as $line) {
                if (strncmp($line, 'data: ', 6) !== 0) {
                    continue;
                }
                $payload = substr($line, 6);
                if (trim($payload) === '[DONE]') {
                    continue;
                }
                $json = json_decode($payload, true);
                if (!is_array($json)) {
                    continue;
                }
                if (isset($json['usage'])) {
                    $state['usage'] = $json['usage'];
                }
                $delta = isset($json['choices'][0]['delta']) ? $json['choices'][0]['delta'] : [];
                $finish = isset($json['choices'][0]['finish_reason']) ? $json['choices'][0]['finish_reason'] : null;
                if (isset($delta['content']) && $delta['content'] !== '') {
                    $gemini = [
                        'candidates' => [[
                            'content' => ['parts' => [['text' => (string)$delta['content']]], 'role' => 'model'],
                        ]],
                    ];
                    $out .= 'data: ' . json_encode($gemini, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                }
                if ($finish !== null && !$state['hasFinish']) {
                    $state['hasFinish'] = true;
                    $finishMap = ['stop' => 'STOP', 'length' => 'MAX_TOKENS', 'content_filter' => 'SAFETY', 'tool_calls' => 'STOP'];
                    $gemini = ['candidates' => [['finishReason' => isset($finishMap[$finish]) ? $finishMap[$finish] : 'STOP']]];
                    if (is_array($state['usage'])) {
                        $gemini['usageMetadata'] = [
                            'promptTokenCount' => isset($state['usage']['prompt_tokens']) ? (int)$state['usage']['prompt_tokens'] : 0,
                            'candidatesTokenCount' => isset($state['usage']['completion_tokens']) ? (int)$state['usage']['completion_tokens'] : 0,
                            'totalTokenCount' => isset($state['usage']['total_tokens']) ? (int)$state['usage']['total_tokens'] : 0,
                        ];
                    }
                    $out .= 'data: ' . json_encode($gemini, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                }
            }
            return $out;
        };
        $finish = function () use (&$state) {
            if ($state['hasFinish']) {
                return '';
            }
            $gemini = ['candidates' => [['finishReason' => 'STOP']]];
            if (is_array($state['usage'])) {
                $gemini['usageMetadata'] = [
                    'promptTokenCount' => isset($state['usage']['prompt_tokens']) ? (int)$state['usage']['prompt_tokens'] : 0,
                    'candidatesTokenCount' => isset($state['usage']['completion_tokens']) ? (int)$state['usage']['completion_tokens'] : 0,
                    'totalTokenCount' => isset($state['usage']['total_tokens']) ? (int)$state['usage']['total_tokens'] : 0,
                ];
            }
            return 'data: ' . json_encode($gemini, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        };
        return ['transform' => $transform, 'finish' => $finish];
    }

    /* ============ 公共小工具 ============ */

    /** 输出 OpenAI 格式 SSE data 块 */
    public static function sseChunk($id, $model, $choices, $usage = null)
    {
        $chunk = [
            'id' => $id,
            'object' => 'chat.completion.chunk',
            'created' => time(),
            'model' => $model,
            'choices' => [],
        ];
        foreach ($choices as $idx => $c) {
            $chunk['choices'][] = array_merge([
                'index' => (int)$idx,
                'delta' => [],
                'finish_reason' => null,
            ], $c);
        }
        if ($usage !== null) {
            $chunk['usage'] = $usage;
        }
        return 'data: ' . json_encode($chunk, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    }

    /** 输出 Claude 格式 SSE 块 */
    public static function claudeEvent($event, $data)
    {
        return 'event: ' . $event . "\n" . 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    }

    /** 提取上游响应 usage（openai 兼容格式） */
    public static function extractUsage($body)
    {
        $json = json_decode((string)$body, true);
        return is_array($json) && isset($json['usage']) ? $json['usage'] : null;
    }

    /** 把各协议 usage 键名统一为 prompt_tokens / completion_tokens */
    public static function normalizeUsage($u)
    {
        if (!is_array($u)) {
            return ['prompt_tokens' => 0, 'completion_tokens' => 0];
        }
        $p = isset($u['prompt_tokens']) ? $u['prompt_tokens']
            : (isset($u['input_tokens']) ? $u['input_tokens']
            : (isset($u['promptTokenCount']) ? $u['promptTokenCount']
            : (isset($u['prompt_token_count']) ? $u['prompt_token_count'] : 0)));
        $c = isset($u['completion_tokens']) ? $u['completion_tokens']
            : (isset($u['output_tokens']) ? $u['output_tokens']
            : (isset($u['candidatesTokenCount']) ? $u['candidatesTokenCount']
            : (isset($u['completion_token_count']) ? $u['completion_token_count'] : 0)));
        return ['prompt_tokens' => (int)$p, 'completion_tokens' => (int)$c];
    }

    /** 流式缓冲提取 usage：兼容 OpenAI/Claude/Gemini 的 data 块 */
    public static function extractStreamUsage($buffer)
    {
        $usage = null;
        $chunks = explode("\n\n", $buffer);
        foreach ($chunks as $chunk) {
            if (preg_match('/^data: (.+)$/m', $chunk, $m)) {
                $json = json_decode($m[1], true);
                if (is_array($json)) {
                    if (isset($json['usage'])) {
                        $usage = $json['usage'];
                    } elseif (isset($json['type']) && $json['type'] === 'message_delta' && isset($json['usage'])) {
                        $usage = $json['usage'];
                    } elseif (isset($json['usageMetadata'])) {
                        $u = $json['usageMetadata'];
                        $usage = [
                            'prompt_tokens' => isset($u['promptTokenCount']) ? (int)$u['promptTokenCount'] : 0,
                            'completion_tokens' => isset($u['candidatesTokenCount']) ? (int)$u['candidatesTokenCount'] : 0,
                            'total_tokens' => isset($u['totalTokenCount']) ? (int)$u['totalTokenCount'] : 0,
                        ];
                    }
                }
            }
        }
        return $usage;
    }

    private static function claudeStopReason($reason)
    {
        $map = [
            'end_turn' => 'stop',
            'max_tokens' => 'length',
            'tool_use' => 'tool_calls',
            'stop_sequence' => 'stop',
            'refusal' => 'content_filter',
        ];
        return isset($map[$reason]) ? $map[$reason] : 'stop';
    }

    private static function openaiStopReason($reason)
    {
        $map = [
            'stop' => 'end_turn',
            'length' => 'max_tokens',
            'tool_calls' => 'tool_use',
            'content_filter' => 'refusal',
        ];
        return isset($map[$reason]) ? $map[$reason] : 'end_turn';
    }

    private static function geminiFinishReason($reason)
    {
        $map = [
            'STOP' => 'stop',
            'MAX_TOKENS' => 'length',
            'SAFETY' => 'content_filter',
            'RECITATION' => 'content_filter',
            'OTHER' => 'stop',
        ];
        return isset($map[$reason]) ? $map[$reason] : 'stop';
    }
}
