<?php
class Relay
{
    private static $requestId = '';

    /**
     * 统一转发入口
     * @param string $endpoint      上游端点路径（如 chat/completions、models/{model}:generateContent）
     * @param string $apiType       计费/日志类型：chat|completion|embedding|image|audio|speech|claude|responses|rerank|moderation|gemini
     * @param string $requestFormat 客户端请求格式：openai|claude|gemini
     * @param string|null $urlModel 模型在 URL 里的请求（Gemini 专用），null 表示模型在 body
     * @param string $contentType   请求 Content-Type（multipart 直通时需保留原始头）
     */
    public static function handle($endpoint, $apiType, $requestFormat = 'openai', $urlModel = null, $contentType = 'application/json')
    {
        self::$requestId = http_request_id();
        header('X-Lcyapi-Request-Id: ' . self::$requestId);

        $auth = self::authenticate();
        if (!$auth['ok']) {
            return self::openaiError($auth['message'], 'invalid_request_error', $auth['error'], $auth['http_code']);
        }
        $token = $auth['token'];
        $user = $auth['user'];

        $limit = (int)setting('api_rate_limit', config('security.api_rate_limit', 60));
        $window = (int)setting('api_rate_window', config('security.api_rate_window', 60));
        if ($limit > 0 && !RateLimit::check('api:' . (int)$token['id'] . ':' . client_ip(), $limit, $window)) {
            return self::openaiError('请求过于频繁，请稍后再试', 'rate_limit_error', 'rate_limit_exceeded', 429, $window);
        }

        $rawBody = file_get_contents('php://input');
        if ($rawBody === false || $rawBody === '') {
            $rawBody = '{}';
        }
        /* 请求体大小限制 */
        $maxBodyMb = (int)setting('max_request_mb', '0');
        if ($maxBodyMb > 0 && strlen($rawBody) > $maxBodyMb * 1048576) {
            return self::openaiError('请求体过大，最大允许 ' . $maxBodyMb . ' MB', 'invalid_request_error', 'request_too_large', 413);
        }
        $isMultipart = strpos($contentType, 'multipart/form-data') !== false;
        $payload = [];
        if (!$isMultipart) {
            $payload = json_decode($rawBody, true);
            if (!is_array($payload)) {
                return self::openaiError('请求体不是合法的 JSON', 'invalid_request_error', 'invalid_json', 400);
            }
            /* 敏感词过滤：请求内容命中直接拒绝 */
            $hits = Sensitive::check(self::extractText($payload));
            if (!empty($hits)) {
                return self::openaiError('请求内容包含敏感词：' . implode('、', array_slice($hits, 0, 5)), 'invalid_request_error', 'sensitive_content', 400);
            }
        }

        if ($urlModel !== null) {
            $model = (string)$urlModel;
        } else {
            $model = isset($payload['model']) ? (string)$payload['model'] : '';
        }
        if ($model === '') {
            return self::openaiError('请提供 model 参数', 'invalid_request_error', 'model_not_found', 404);
        }

        $price = Model::find($model);
        if ($price === false || (int)$price['enabled'] !== 1) {
            return self::openaiError('模型 ' . $model . ' 不存在或已停用', 'invalid_request_error', 'model_not_found', 404);
        }

        if ($apiType === 'image') {
            $price['input_price'] = isset($price['input_price']) ? $price['input_price'] : 0;
        }

        $userGroup = isset($user['group']) && trim((string)$user['group']) !== '' ? (string)$user['group'] : 'default';
        $estimatedCost = self::estimateCost($apiType, $payload, $price) * Group::getUserGroupRatio($userGroup);
        if ((float)$user['quota'] < $estimatedCost) {
            return self::openaiError('账户余额不足，预估需要 $' . number_format($estimatedCost, 6), 'insufficient_quota', 'insufficient_user_quota', 403);
        }

        /* 令牌模型限制：{模型名:单次最大token}，估算超限直接拒绝 */
        $limits = Token::modelLimits($token);
        if (is_array($limits) && array_key_exists($model, $limits)) {
            $estTokens = self::estimateTokens($payload);
            if ($estTokens > (int)$limits[$model]) {
                return self::openaiError('模型 ' . $model . ' 单次请求超过令牌限制（最多 ' . (int)$limits[$model] . ' tokens）', 'invalid_request_error', 'model_token_limit_exceeded', 400);
            }
        }

        $isStream = !empty($payload['stream']) && in_array($apiType, ['chat', 'completion', 'claude', 'responses', 'gemini'], true);
        $allowConvert = in_array($apiType, ['chat', 'completion', 'claude', 'gemini'], true);
        $maxAttempts = 1 + max(0, (int)setting('retry_count', config('relay.retry_count', 0)));
        $excludeIds = [];
        $startMs = microtime(true);
        $lastAttempt = null;
        $selectedAny = false;

        /* 分组：令牌组（支持 auto 自动分组）优先，其次用户分组 */
        $groups = Group::resolveTokenGroups($token, $userGroup);
        $lastSelectedGroup = '';

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $selectedGroup = null;
            $channel = false;
            /* 渠道亲和性：会话期优先复用上次成功的渠道 */
            if ($attempt === 0) {
                $affinityId = Affinity::get($user['id'], $model);
                if ($affinityId > 0 && !in_array($affinityId, $excludeIds, true)) {
                    $affinityChannel = Channel::getById($affinityId);
                    $inAffinityGroups = false;
                    if ($affinityChannel !== false) {
                        foreach ($groups as $g) {
                            if (Channel::inGroup($affinityChannel, $g)) {
                                $inAffinityGroups = true;
                                break;
                            }
                        }
                    }
                    if ($affinityChannel !== false && (int)$affinityChannel['status'] === 1 && Channel::supportsModel($affinityChannel, $model) && $inAffinityGroups) {
                        $channel = $affinityChannel;
                    } else {
                        Affinity::clear($user['id'], $model);
                    }
                }
            }
            if ($channel === false) {
                foreach ($groups as $g) {
                    $channel = Channel::select($model, $excludeIds, $g);
                    if ($channel !== false) {
                        $selectedGroup = $g;
                        break;
                    }
                }
            }
            if ($channel === false) {
                break;
            }
            $selectedAny = true;
            if ($selectedGroup === null) {
                $selectedGroup = $groups[0];
            }
            $lastSelectedGroup = $selectedGroup;
            $excludeIds[] = (int)$channel['id'];
            $channelFormat = ChannelType::format(isset($channel['type']) ? $channel['type'] : 'openai');
            $upstreamEndpoint = self::upstreamEndpoint($endpoint, $apiType, $requestFormat, $channelFormat, $isStream, $model);
            $result = self::forward($channel, $upstreamEndpoint, $rawBody, $isStream, $model, $requestFormat, $contentType, $allowConvert);
            if ($result['ok']) {
                $duration = (int)round((microtime(true) - $startMs) * 1000);
                $usage = isset($result['usage']) ? $result['usage'] : null;
                $normUsage = Converter::normalizeUsage($usage);
                $promptTokens = $normUsage['prompt_tokens'];
                $completionTokens = $normUsage['completion_tokens'];
                $cachedTokens = isset($normUsage['cached_tokens']) ? $normUsage['cached_tokens'] : 0;
                $cost = self::computeCost($apiType, $payload, $price, $promptTokens, $completionTokens, $cachedTokens) * Group::getUserGroupRatio($userGroup, $lastSelectedGroup);
                self::settle($user, $token, $channel, $model, $apiType, $promptTokens, $completionTokens, $cost, $duration, true, null, $rawBody, $estimatedCost);
                Channel::incrementSuccess((int)$channel['id']);
                Affinity::pin($user['id'], $model, (int)$channel['id']);
                if (!empty($result['body'])) {
                    $finalBody = Sensitive::enabled() ? self::maskResponseBody($result['body']) : $result['body'];
                    header('Content-Type: application/json; charset=utf-8');
                    echo $finalBody;
                }
                return null;
            }
            Channel::incrementFail((int)$channel['id']);
            Affinity::clear($user['id'], $model);
            self::maybeAutoDisable((int)$channel['id'], isset($result['http_code']) ? $result['http_code'] : null, isset($result['error']) ? $result['error'] : null);
            $lastAttempt = $result;
            if (!$result['retryable']) {
                break;
            }
            $httpCode = (int)($result['http_code'] ?? 0);
            if ($httpCode >= 400 && !self::isRetryableStatus($httpCode)) {
                break;
            }
        }

        $duration = (int)round((microtime(true) - $startMs) * 1000);
        if (!$selectedAny) {
            $errorMsg = '无可用的渠道（当前分组下无匹配渠道）';
            self::settle($user, $token, null, $model, $apiType, 0, 0, 0, $duration, false, $errorMsg, $rawBody, $estimatedCost);
            return self::openaiError('该模型当前无可用的渠道，请检查渠道分组与模型配置', 'insufficient_quota', 'no_available_channel', 404);
        }
        $errorMsg = '所有渠道均失败';
        if ($lastAttempt !== null && !empty($lastAttempt['error'])) {
            $errorMsg .= '：' . $lastAttempt['error'];
        }
        $lastChannel = $lastAttempt !== null && isset($channel) ? $channel : null;
        self::settle($user, $token, $lastChannel, $model, $apiType, 0, 0, 0, $duration, false, $errorMsg, $rawBody, $estimatedCost);
        if ($lastAttempt !== null && !empty($lastAttempt['body'])) {
            $upstreamBody = $lastAttempt['body'];
            $decoded = json_decode($upstreamBody, true);
            if (is_array($decoded) && isset($decoded['error'])) {
                $httpCode = isset($lastAttempt['http_code']) && $lastAttempt['http_code'] >= 400 && $lastAttempt['http_code'] < 600 ? $lastAttempt['http_code'] : 502;
                return Response::json($decoded, $httpCode);
            }
        }
        return self::openaiError('上游服务暂不可用', 'server_error', 'upstream_error', 502);
    }

    private static function authenticate()
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
        } elseif (!empty($_SERVER['HTTP_X_GOOG_API_KEY'])) {
            $rawKey = $_SERVER['HTTP_X_GOOG_API_KEY'];
        } elseif (isset($_GET['key'])) {
            $rawKey = $_GET['key'];
        }
        if ($rawKey === '') {
            return ['ok' => false, 'error' => 'unauthorized', 'message' => '缺少认证信息', 'http_code' => 401];
        }
        $result = Token::verify($rawKey);
        if (!$result['ok']) {
            return ['ok' => false, 'error' => $result['error'], 'message' => $result['message'], 'http_code' => in_array($result['error'], ['user_banned', 'insufficient_token_quota'], true) ? 403 : 401];
        }
        return ['ok' => true, 'token' => $result['token'], 'user' => $result['user']];
    }

    private static function estimateTokens($payload)
    {
        $text = '';
        if (isset($payload['prompt'])) {
            $text .= is_array($payload['prompt']) ? json_encode($payload['prompt']) : $payload['prompt'];
        }
        if (isset($payload['messages'])) {
            $text .= json_encode($payload['messages']);
        }
        if (isset($payload['input'])) {
            $text .= is_array($payload['input']) ? json_encode($payload['input']) : $payload['input'];
        }
        if (isset($payload['contents'])) {
            $text .= json_encode($payload['contents']);
        }
        if (isset($payload['documents'])) {
            $text .= json_encode($payload['documents']);
        }
        if (isset($payload['query'])) {
            $text .= json_encode($payload['query']);
        }
        return Billing::estimateTokens($text);
    }

    private static function estimateCost($apiType, $payload, $price)
    {
        if ($apiType === 'image') {
            $n = isset($payload['n']) ? max(1, (int)$payload['n']) : 1;
            return Billing::calculateImage($price, $n);
        }
        if ($apiType === 'audio' || $apiType === 'speech') {
            return 0.006;
        }
        if ($apiType === 'rerank') {
            return Billing::calculate($price, self::estimateTokens($payload), 0);
        }
        if ($apiType === 'moderation') {
            return Billing::calculate($price, self::estimateTokens($payload), 0);
        }
        return Billing::calculate($price, self::estimateTokens($payload), 0);
    }

    private static function computeCost($apiType, $payload, $price, $promptTokens, $completionTokens, $cachedTokens = 0)
    {
        if ($apiType === 'image') {
            $n = isset($payload['n']) ? max(1, (int)$payload['n']) : 1;
            return Billing::calculateImage($price, $n);
        }
        if ($apiType === 'audio' || $apiType === 'speech') {
            return 0.006;
        }
        return Billing::calculate($price, $promptTokens, $completionTokens, $cachedTokens);
    }

    private static function forward($channel, $endpoint, $body, &$isStream, $model = '', $requestFormat = 'openai', $contentType = 'application/json', $allowConvert = true)
    {
        $channelFormat = ChannelType::format(isset($channel['type']) ? $channel['type'] : 'openai');
        $isJson = strpos($contentType, 'multipart/form-data') === false;

        /* 模型映射：body 里的 model + URL 路径里的 model（Gemini）一起替换 */
        $mapped = '';
        if ($model !== '' && $isJson) {
            $mapped = Channel::mapModel($channel, $model);
            if ($mapped !== $model) {
                $payload = json_decode($body, true);
                if (is_array($payload)) {
                    $payload['model'] = $mapped;
                    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
        }

        /* 请求格式转换：openai⇄claude⇄gemini（仅 chat 类接口） */
        if ($isJson && $allowConvert && $requestFormat !== $channelFormat) {
            $payload = json_decode($body, true);
            if (is_array($payload)) {
                $converted = self::convertRequest($requestFormat, $channelFormat, $payload);
                if ($converted !== null) {
                    $body = json_encode($converted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
        }

        $url = Channel::buildUrl($channel, $endpoint);
        if ($mapped !== '' && $mapped !== $model && strpos($url, $model) !== false) {
            $url = str_replace($model, $mapped, $url);
        }
        $timeout = max(10, (int)config('relay.timeout', 120));

        $headers = Channel::headersFor($channel);
        if (!$isJson) {
            foreach ($headers as $i => $h) {
                if (stripos($h, 'Content-Type:') === 0) {
                    unset($headers[$i]);
                }
            }
            $headers[] = 'Content-Type: ' . $contentType;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => array_values($headers),
        ]);

        if ($isStream) {
            return self::forwardStream($ch, $channelFormat, $requestFormat, $model, $allowConvert);
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            return ['ok' => false, 'retryable' => true, 'error' => $curlError, 'http_code' => 0, 'body' => ''];
        }
        if ($httpCode >= 200 && $httpCode < 300) {
            $finalBody = $response;
            if ($isJson && $allowConvert && $requestFormat !== $channelFormat) {
                $finalBody = self::convertResponse($channelFormat, $requestFormat, $response);
            }
            $data = json_decode($finalBody, true);
            $usage = is_array($data) && isset($data['usage']) ? $data['usage'] : null;
            return ['ok' => true, 'http_code' => $httpCode, 'body' => $finalBody, 'usage' => $usage];
        }
        if ($isJson && $allowConvert && $requestFormat !== $channelFormat) {
            $response = self::convertResponse($channelFormat, $requestFormat, $response);
        }
        return ['ok' => false, 'retryable' => true, 'http_code' => $httpCode, 'body' => $response, 'error' => 'HTTP ' . $httpCode];
    }

    private static function forwardStream($ch, $channelFormat, $requestFormat, $model, $allowConvert = true)
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');

        $conv = null;
        if ($allowConvert && $requestFormat !== $channelFormat) {
            $conv = self::streamConverter($channelFormat, $requestFormat, $model);
        }

        $failed = false;
        $buffer = '';
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$failed, &$buffer, $conv) {
            static $checked = false;
            if (!$checked) {
                $checked = true;
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($code >= 400) {
                    $failed = true;
                }
            }
            if ($failed) {
                if (strlen($buffer) < 4194304) {
                    $buffer .= $data;
                }
                return strlen($data);
            }
            if (strlen($buffer) < 4194304) {
                $buffer .= $data;
            }
            if ($conv !== null) {
                $out = $conv['transform']($data);
                if ($out !== '') {
                    echo Sensitive::enabled() ? self::maskStream($out) : $out;
                    @ob_flush();
                    flush();
                }
            } else {
                echo Sensitive::enabled() ? self::maskStream($data) : $data;
                @ob_flush();
                flush();
            }
            return strlen($data);
        });
        curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($failed) {
            return ['ok' => false, 'retryable' => true, 'error' => '上游返回 HTTP ' . $httpCode, 'http_code' => $httpCode, 'body' => $buffer, 'streamed' => false];
        }
        if ($conv !== null) {
            $final = $conv['finish']();
            if ($final !== '') {
                echo $final;
                @ob_flush();
                flush();
            }
        }
        return ['ok' => true, 'http_code' => $httpCode, 'usage' => Converter::extractStreamUsage($buffer), 'streamed' => true];
    }

    /**
     * 按渠道格式重映射上游端点：claude/gemini 渠道与 openai 端点互转
     */
    private static function upstreamEndpoint($endpoint, $apiType, $requestFormat, $channelFormat, $isStream, $model)
    {
        if ($requestFormat === $channelFormat || !in_array($apiType, ['chat', 'completion', 'claude', 'gemini'], true)) {
            return $endpoint;
        }
        if ($requestFormat === 'claude' && $channelFormat === 'openai' && $endpoint === 'messages') {
            return 'chat/completions';
        }
        if ($requestFormat === 'openai' && $channelFormat === 'claude' && $endpoint === 'chat/completions') {
            return 'messages';
        }
        if ($requestFormat === 'openai' && $channelFormat === 'gemini' && $endpoint === 'chat/completions') {
            $action = $isStream ? 'streamGenerateContent' : 'generateContent';
            return 'models/' . $model . ':' . $action;
        }
        return $endpoint;
    }

    private static function convertRequest($from, $to, $payload)
    {
        if ($from === 'openai' && $to === 'claude') {
            return Converter::openaiToClaude($payload);
        }
        if ($from === 'claude' && $to === 'openai') {
            return Converter::claudeToOpenAI($payload);
        }
        if ($from === 'openai' && $to === 'gemini') {
            return Converter::openaiToGemini($payload);
        }
        return null;
    }

    private static function convertResponse($from, $to, $body)
    {
        if ($from === 'claude' && $to === 'openai') {
            return Converter::claudeResponseToOpenAI($body);
        }
        if ($from === 'gemini' && $to === 'openai') {
            return Converter::geminiResponseToOpenAI($body);
        }
        if ($from === 'openai' && $to === 'claude') {
            return Converter::openaiResponseToClaude($body);
        }
        return $body;
    }

    private static function streamConverter($from, $to, $model)
    {
        if ($from === 'claude' && $to === 'openai') {
            return Converter::makeClaudeStreamToOpenAI($model);
        }
        if ($from === 'openai' && $to === 'claude') {
            return Converter::makeOpenAIStreamToClaude($model);
        }
        if ($from === 'gemini' && $to === 'openai') {
            return Converter::makeGeminiStreamToOpenAI($model);
        }
        if ($from === 'openai' && $to === 'gemini') {
            return Converter::makeOpenAIStreamToGemini($model);
        }
        return null;
    }

    private static function maybeAutoDisable($channelId, $httpCode = null, $errorMsg = null)
    {
        if (!setting('auto_disable', config('relay.auto_disable', false))) {
            return;
        }
        /* 命中「立即停用」状态码或关键词则立刻停用，不等待阈值 */
        if ($httpCode !== null) {
            $codes = setting('auto_disable_status_codes', '');
            if ($codes !== '' && self::statusInList($httpCode, $codes)) {
                Channel::update($channelId, ['status' => 0]);
                write_log("channel #{$channelId} auto disabled (http {$httpCode} in status list)");
                return;
            }
        }
        if ($errorMsg !== null && $errorMsg !== '') {
            $keywords = setting('auto_disable_keywords', '');
            if ($keywords !== '') {
                $lower = mb_strtolower($errorMsg);
                foreach (explode(',', str_replace('，', ',', $keywords)) as $kw) {
                    $kw = trim($kw);
                    if ($kw !== '' && mb_strpos($lower, mb_strtolower($kw)) !== false) {
                        Channel::update($channelId, ['status' => 0]);
                        write_log("channel #{$channelId} auto disabled (keyword \"{$kw}\")");
                        return;
                    }
                }
            }
        }
        $threshold = max(1, (int)setting('auto_disable_threshold', config('relay.auto_disable_threshold', 100)));
        $channel = Channel::getById($channelId);
        if ($channel !== false && (int)$channel['fail_count'] >= $threshold) {
            Channel::update($channelId, ['status' => 0]);
            write_log("channel #{$channelId} auto disabled (fail_count={$channel['fail_count']})");
        }
    }

    /**
     * 判断 HTTP 状态码是否命中「可重试」列表；列表为空时默认 500-599 可重试
     */
    private static function isRetryableStatus($httpCode)
    {
        $list = trim(setting('retry_status_codes', ''));
        if ($list === '') {
            return $httpCode >= 500 && $httpCode <= 599;
        }
        return self::statusInList($httpCode, $list);
    }

    /**
     * 判断状态码是否命中逗号分隔的列表（支持区间，如 500,502-504,529）
     */
    private static function statusInList($code, $list)
    {
        $code = (int)$code;
        foreach (explode(',', str_replace('，', ',', $list)) as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            if (strpos($item, '-') !== false) {
                $parts = explode('-', $item);
                $lo = (int)trim($parts[0]);
                $hi = (int)trim(isset($parts[1]) ? $parts[1] : $parts[0]);
                if ($code >= $lo && $code <= $hi) {
                    return true;
                }
            } elseif ($code === (int)$item) {
                return true;
            }
        }
        return false;
    }

    private static function openaiError($message, $type, $code, $httpCode = 400, $retryAfter = 0)
    {
        if ($retryAfter > 0) {
            header('Retry-After: ' . $retryAfter);
        }
        return Response::openaiError($message, $type, $code, $httpCode);
    }

    private static function settle($user, $token, $channel, $model, $apiType, $promptTokens, $completionTokens, $cost, $duration, $status, $errorMsg, $raw, $estimatedCost)
    {
        $requestId = self::$requestId;
        $logData = [
            'user_id' => (int)$user['id'],
            'token_id' => (int)$token['id'],
            'channel_id' => $channel !== null ? (int)$channel['id'] : 0,
            'model' => $model,
            'type' => $apiType,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $promptTokens + $completionTokens,
            'cost' => $cost,
            'duration' => $duration,
            'status' => $status ? 1 : 0,
        ];
        if ($errorMsg !== null) {
            $logData['error_msg'] = mb_substr($errorMsg, 0, 1000);
        }
        $logData['request_body'] = null;
        DB::begin();
        try {
            if ($status) {
                if ($cost > 0 && !User::deductQuota((int)$user['id'], $cost)) {
                    throw new Exception('扣费失败：余额不足');
                }
                if ($cost > 0) {
                    Token::charge((int)$token['id'], $cost);
                } else {
                    Token::charge((int)$token['id'], 0);
                }
                User::incrementApiCount((int)$user['id']);
                if ($channel !== null && isset($channel['balance']) && $channel['balance'] !== null && (float)$channel['balance'] > 0) {
                    DB::query('UPDATE channels SET balance = balance - ? WHERE id = ?', [$cost, (int)$channel['id']]);
                    $newBalance = (float)DB::value('SELECT balance FROM channels WHERE id = ?', [(int)$channel['id']]);
                    if ($newBalance <= 0) {
                        Channel::update((int)$channel['id'], ['status' => 0]);
                        write_log("channel #{$channel['id']} {$channel['name']} 已自动停用：余额耗尽", 'balance');
                    }
                }
            }
            Log::write($logData);
            DB::commit();
        } catch (Exception $ex) {
            DB::rollback();
            write_log('settle error: ' . $ex->getMessage());
        }
    }

    /* ==================== 敏感词过滤辅助 ==================== */

    /**
     * 从请求 payload 提取用于敏感词检测的文本
     */
    private static function extractText($payload)
    {
        $text = '';
        if (isset($payload['messages']) && is_array($payload['messages'])) {
            foreach ($payload['messages'] as $msg) {
                if (!is_array($msg)) {
                    continue;
                }
                if (isset($msg['content'])) {
                    $text .= self::flattenContent($msg['content']) . "\n";
                }
                if (isset($msg['tool_calls']) && is_array($msg['tool_calls'])) {
                    foreach ($msg['tool_calls'] as $tc) {
                        if (is_array($tc) && isset($tc['function']['arguments'])) {
                            $text .= $tc['function']['arguments'] . "\n";
                        }
                    }
                }
            }
        }
        if (isset($payload['prompt'])) {
            $text .= (is_string($payload['prompt']) ? $payload['prompt'] : json_encode($payload['prompt'])) . "\n";
        }
        if (isset($payload['input'])) {
            $text .= (is_string($payload['input']) ? $payload['input'] : json_encode($payload['input'])) . "\n";
        }
        if (isset($payload['contents'])) {
            $text .= json_encode($payload['contents']) . "\n";
        }
        if (isset($payload['documents'])) {
            $text .= json_encode($payload['documents']) . "\n";
        }
        if (isset($payload['query'])) {
            $text .= (is_string($payload['query']) ? $payload['query'] : json_encode($payload['query'])) . "\n";
        }
        return $text;
    }

    private static function flattenContent($content)
    {
        if (is_string($content)) {
            return $content;
        }
        if (!is_array($content)) {
            return '';
        }
        $out = '';
        foreach ($content as $part) {
            if (is_string($part)) {
                $out .= $part . "\n";
                continue;
            }
            if (is_array($part)) {
                if (isset($part['text'])) {
                    $out .= $part['text'] . "\n";
                }
                if (isset($part['input_text'])) {
                    $out .= $part['input_text'] . "\n";
                }
            }
        }
        return $out;
    }

    /**
     * 非流式响应打码：递归掩码 content/text/transcript 等文本字段
     */
    private static function maskResponseBody($body)
    {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return $body;
        }
        self::maskTextFields($data);
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * SSE 流式数据打码（逐行处理 data: {...} 块）
     */
    private static function maskStream($data)
    {
        if (strpos($data, 'data:') === false) {
            return $data;
        }
        $lines = preg_split('/\r?\n/', $data);
        foreach ($lines as &$line) {
            if (strncmp($line, 'data: ', 6) !== 0 || trim($line) === 'data: [DONE]') {
                continue;
            }
            $json = json_decode(substr($line, 6), true);
            if (is_array($json)) {
                self::maskTextFields($json);
                $line = 'data: ' . json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            }
        }
        unset($line);
        return implode("\n", $lines);
    }

    private static function maskTextFields(&$node)
    {
        if (!is_array($node)) {
            return;
        }
        foreach ($node as $k => &$v) {
            if (is_array($v)) {
                self::maskTextFields($v);
            } elseif (is_string($v) && ($k === 'content' || $k === 'text' || $k === 'transcript')) {
                $v = Sensitive::mask($v);
            }
        }
        unset($v);
    }
}
