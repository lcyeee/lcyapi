<?php
class Relay
{
    private static $requestId = '';

    public static function handle($endpoint, $apiType)
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
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return self::openaiError('请求体不是合法的 JSON', 'invalid_request_error', 'invalid_json', 400);
        }

        $model = isset($payload['model']) ? (string)$payload['model'] : '';
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

        $isStream = !empty($payload['stream']) && in_array($apiType, ['chat', 'completion'], true);
        $maxAttempts = 1 + max(0, (int)setting('retry_count', config('relay.retry_count', 0)));
        $excludeIds = [];
        $startMs = microtime(true);
        $lastAttempt = null;

        /* 分组：令牌组（支持 auto 自动分组）优先，其次用户分组 */
        $groups = Group::resolveTokenGroups($token, $userGroup);
        $lastSelectedGroup = '';

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $selectedGroup = null;
            $channel = false;
            foreach ($groups as $g) {
                $channel = Channel::select($model, $excludeIds, $g);
                if ($channel !== false) {
                    $selectedGroup = $g;
                    break;
                }
            }
            if ($channel === false) {
                break;
            }
            $lastSelectedGroup = $selectedGroup;
            $excludeIds[] = (int)$channel['id'];
            $result = self::forward($channel, $endpoint, $rawBody, $isStream, $model);
            if ($result['ok']) {
                $duration = (int)round((microtime(true) - $startMs) * 1000);
                $usage = isset($result['usage']) ? $result['usage'] : null;
                $promptTokens = isset($usage['prompt_tokens']) ? (int)$usage['prompt_tokens'] : 0;
                $completionTokens = isset($usage['completion_tokens']) ? (int)$usage['completion_tokens'] : 0;
                $cost = self::computeCost($apiType, $payload, $price, $promptTokens, $completionTokens) * Group::getUserGroupRatio($userGroup, $lastSelectedGroup);
                self::settle($user, $token, $channel, $model, $apiType, $promptTokens, $completionTokens, $cost, $duration, true, null, $rawBody, $estimatedCost);
                Channel::incrementSuccess((int)$channel['id']);
                if (!empty($result['body'])) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo $result['body'];
                }
                return null;
            }
            Channel::incrementFail((int)$channel['id']);
            self::maybeAutoDisable((int)$channel['id']);
            $lastAttempt = $result;
            if (!$result['retryable']) {
                break;
            }
        }

        $duration = (int)round((microtime(true) - $startMs) * 1000);
        if ($attempt === 0) {
            $errorMsg = '无可用的渠道（当前分组下无匹配渠道）';
            self::settle($user, $token, null, $model, $apiType, 0, 0, 0, $duration, false, $errorMsg, $rawBody, $estimatedCost);
            return self::openaiError('该模型当前无可用的渠道，请检查渠道分组与模型配置', 'insufficient_quota', 'no_available_channel', 404);
        }
        $errorMsg = '所有渠道均失败';
        if ($lastAttempt !== null && !empty($lastAttempt['error'])) {
            $errorMsg .= '：' . $lastAttempt['error'];
        }
        self::settle($user, $token, isset($channel) ? $channel : null, $model, $apiType, 0, 0, 0, $duration, false, $errorMsg, $rawBody, $estimatedCost);
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
        return Billing::calculate($price, self::estimateTokens($payload), 0);
    }

    private static function computeCost($apiType, $payload, $price, $promptTokens, $completionTokens)
    {
        if ($apiType === 'image') {
            $n = isset($payload['n']) ? max(1, (int)$payload['n']) : 1;
            return Billing::calculateImage($price, $n);
        }
        if ($apiType === 'audio' || $apiType === 'speech') {
            return 0.006;
        }
        return Billing::calculate($price, $promptTokens, $completionTokens);
    }

private static function forward($channel, $endpoint, $body, &$isStream, $model = '')
    {
        $url = Channel::buildUrl($channel, $endpoint);
        $timeout = max(10, (int)config('relay.timeout', 120));
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json, text/event-stream',
            'User-Agent: lcyapi/1.0',
        ];
        $apiKey = isset($channel['api_key']) ? $channel['api_key'] : '';
        if (isset($channel['type']) && $channel['type'] === 'azure') {
            $headers[] = 'api-key: ' . $apiKey;
        } else {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }
        foreach (Channel::extraHeaders($channel) as $extra) {
            $headers[] = $extra;
        }
        if ($model !== '') {
            $mapped = Channel::mapModel($channel, $model);
            if ($mapped !== $model) {
                $payload = json_decode($body, true);
                if (is_array($payload)) {
                    $payload['model'] = $mapped;
                    $body = json_encode($payload);
                }
            }
        }
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($isStream) {
            return self::forwardStream($ch);
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
            $data = json_decode($response, true);
            $usage = is_array($data) && isset($data['usage']) ? $data['usage'] : null;
            return ['ok' => true, 'http_code' => $httpCode, 'body' => $response, 'usage' => $usage];
        }
        return ['ok' => false, 'retryable' => true, 'http_code' => $httpCode, 'body' => $response, 'error' => 'HTTP ' . $httpCode];
    }

    private static function forwardStream($ch)
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');

        $failed = false;
        $buffer = '';
        $usage = null;
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$failed, &$buffer) {
            static $checked = false;
            if (!$checked) {
                $checked = true;
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($code >= 400) {
                    $failed = true;
                }
            }
            if ($failed) {
                return strlen($data);
            }
            if (strlen($buffer) < 4194304) {
                $buffer .= $data;
            }
            echo $data;
            @ob_flush();
            flush();
            return strlen($data);
        });
        curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($failed) {
            return ['ok' => false, 'retryable' => true, 'error' => '上游返回 HTTP ' . $httpCode, 'http_code' => $httpCode, 'body' => $buffer, 'streamed' => false];
        }
        return ['ok' => true, 'http_code' => $httpCode, 'usage' => self::extractUsage($buffer), 'streamed' => true];
    }

    private static function extractUsage($buffer)
    {
        $usage = null;
        $chunks = explode("\n\n", $buffer);
        foreach ($chunks as $chunk) {
            if (preg_match('/^data: (.+)$/m', $chunk, $m)) {
                $json = json_decode($m[1], true);
                if (is_array($json) && isset($json['usage'])) {
                    $usage = $json['usage'];
                }
            }
        }
        return $usage;
    }

    private static function maybeAutoDisable($channelId)
    {
        if (!setting('auto_disable', config('relay.auto_disable', false))) {
            return;
        }
        $threshold = max(1, (int)setting('auto_disable_threshold', config('relay.auto_disable_threshold', 100)));
        $channel = Channel::getById($channelId);
        if ($channel !== false && (int)$channel['fail_count'] >= $threshold) {
            Channel::update($channelId, ['status' => 0]);
            write_log("channel #{$channelId} auto disabled (fail_count={$channel['fail_count']})");
        }
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
            }
            Log::write($logData);
            DB::commit();
        } catch (Exception $ex) {
            DB::rollback();
            write_log('settle error: ' . $ex->getMessage());
        }
    }
}