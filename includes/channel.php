<?php
class Channel
{
    public static function all($status = null)
    {
        if ($status === null) {
            return DB::fetchAll('SELECT * FROM channels ORDER BY priority DESC, id ASC');
        }
        return DB::fetchAll('SELECT * FROM channels WHERE status = ? ORDER BY priority DESC, id ASC', [(int)$status]);
    }

    public static function getById($id)
    {
        return DB::fetch('SELECT * FROM channels WHERE id = ?', [(int)$id]);
    }

    public static function create($data)
    {
        $fields = ['name', 'type', 'base_url', 'api_key', 'models', 'group', 'model_mapping', 'extra_headers', 'weight', 'priority', 'status', 'remark'];
        $insert = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $insert[$field] = $data[$field];
            }
        }
        if (!isset($insert['group'])) {
            $insert['group'] = '';
        }
        if (!isset($insert['type'])) {
            $insert['type'] = 'openai';
        }
        if (!isset($insert['weight']) || (int)$insert['weight'] < 1) {
            $insert['weight'] = 1;
        }
        if (!isset($insert['priority'])) {
            $insert['priority'] = 0;
        }
        if (!isset($insert['status'])) {
            $insert['status'] = 1;
        }
        try {
            $id = DB::insert('channels', $insert);
            self::clearCache();
            return $id;
        } catch (Exception $ex) {
            return false;
        }
    }

    public static function update($id, $data)
    {
        $fields = ['name', 'type', 'base_url', 'api_key', 'models', 'group', 'model_mapping', 'extra_headers', 'weight', 'priority', 'status', 'remark', 'last_use_at'];
        $update = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        $result = true;
        if (!empty($update)) {
            $result = DB::update('channels', $update, 'id = ?', [(int)$id]) !== false;
        }
        self::clearCache();
        return $result;
    }

    public static function delete($id)
    {
        $channel = self::getById($id);
        if ($channel === false) {
            return false;
        }
        self::clearCache();
        return DB::delete('channels', 'id = ?', [(int)$id]);
    }

    public static function getModels($id)
    {
        $channel = self::getById($id);
        if ($channel === false || empty($channel['models'])) {
            return [];
        }
        $models = array_filter(array_map('trim', explode(',', $channel['models'])));
        return array_values($models);
    }

    public static function supportsModel($channel, $model)
    {
        if (empty($channel['models'])) {
            return true;
        }
        $models = array_filter(array_map('trim', explode(',', $channel['models'])));
        foreach ($models as $m) {
            if ($m === $model) {
                return true;
            }
            if (substr($m, -1) === '*' && strpos($model, rtrim($m, '*')) === 0) {
                return true;
            }
        }
        return false;
    }

    public static function candidates($model, $excludeIds = [], $group = '')
    {
        $cacheKey = 'channels:available:' . $model . ':' . ($group !== '' ? $group : '*');
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $candidates = $cached;
        } else {
            $candidates = [];
            $channels = self::all(1);
            foreach ($channels as $channel) {
                if (!self::inGroup($channel, $group)) {
                    continue;
                }
                if (self::supportsModel($channel, $model)) {
                    $candidates[] = $channel;
                }
            }
            Cache::set($cacheKey, $candidates, 300);
        }
        if (!empty($excludeIds)) {
            $candidates = array_values(array_filter($candidates, function ($c) use ($excludeIds) {
                return !in_array((int)$c['id'], $excludeIds, true);
            }));
        }
        return $candidates;
    }

    /**
     * 渠道分组匹配：渠道 group 为空表示服务所有分组；否则需包含请求分组
     */
    public static function inGroup($channel, $group)
    {
        $groups = trim((string)($channel['group'] ?? ''));
        if ($groups === '') {
            return true;
        }
        if ($group === '') {
            return true;
        }
        $list = array_filter(array_map('trim', explode(',', $groups)));
        return in_array($group, $list, true);
    }

    /**
     * 模型映射：将客户端模型映射到上游模型（JSON：{客户端模型:上游模型}）
     * 支持 exact 与 * 前缀匹配：如 {"gpt-4o":"gpt-4o-0613","*":"gpt-3.5-turbo"}（顺序：先精确后通配）
     */
    public static function mapModel($channel, $model)
    {
        $mapping = isset($channel['model_mapping']) ? trim((string)$channel['model_mapping']) : '';
        if ($mapping === '') {
            return $model;
        }
        $map = json_decode($mapping, true);
        if (!is_array($map) || empty($map)) {
            return $model;
        }
        if (isset($map[$model])) {
            return (string)$map[$model];
        }
        foreach ($map as $pattern => $target) {
            if ($pattern === '*') {
                continue;
            }
            if (substr((string)$pattern, -1) === '*' && strncmp($model, rtrim((string)$pattern, '*'), strlen(rtrim((string)$pattern, '*'))) === 0) {
                return str_replace('*', substr($model, strlen(rtrim((string)$pattern, '*'))), (string)$target);
            }
        }
        if (isset($map['*'])) {
            return (string)$map['*'];
        }
        return $model;
    }

    /**
     * 解析渠道附加请求头 JSON
     */
    public static function extraHeaders($channel)
    {
        $raw = isset($channel['extra_headers']) ? trim((string)$channel['extra_headers']) : '';
        if ($raw === '') {
            return [];
        }
        $json = json_decode($raw, true);
        if (!is_array($json) || empty($json)) {
            return [];
        }
        $headers = [];
        foreach ($json as $name => $value) {
            $name = trim((string)$name);
            if ($name !== '') {
                $headers[] = $name . ': ' . (string)$value;
            }
        }
        return $headers;
    }

    public static function select($model, $excludeIds = [], $group = '')
    {
        $candidates = self::candidates($model, $excludeIds, $group);
        if (empty($candidates)) {
            return false;
        }
        $byPriority = [];
        foreach ($candidates as $channel) {
            $byPriority[(int)$channel['priority']][] = $channel;
        }
        krsort($byPriority, SORT_NUMERIC);
        foreach ($byPriority as $group) {
            $totalWeight = 0;
            foreach ($group as $channel) {
                $totalWeight += max(1, (int)$channel['weight']);
            }
            $rand = random_int(1, $totalWeight);
            foreach ($group as $channel) {
                $rand -= max(1, (int)$channel['weight']);
                if ($rand <= 0) {
                    return $channel;
                }
            }
        }
        return $candidates[0];
    }

public static function test($id)
    {
        $channel = self::getById($id);
        if ($channel === false) {
            return ['ok' => false, 'message' => '渠道不存在'];
        }
        $models = self::getModels($id);
        $testModel = !empty($models) ? $models[0] : 'gpt-3.5-turbo';
        $body = json_encode(['model' => $testModel, 'messages' => [['role' => 'user', 'content' => 'ping']], 'max_tokens' => 5]);
        $url = self::buildUrl($channel, 'chat/completions');
        $start = microtime(true);
        $result = self::httpPost($channel, $url, $body, 30);
        $elapsed = (int)round((microtime(true) - $start) * 1000);
        if ($result['http_code'] >= 200 && $result['http_code'] < 300) {
            $json = json_decode($result['body'], true);
            return ['ok' => true, 'model' => $testModel, 'elapsed' => $elapsed, 'usage' => isset($json['usage']) ? $json['usage'] : null];
        }
        return ['ok' => false, 'message' => 'HTTP ' . $result['http_code'], 'detail' => substr($result['body'], 0, 500)];
    }

    public static function buildUrl($channel, $path)
    {
        $base = rtrim((string)$channel['base_url'], '/');
        if ($base === '') {
            return '';
        }
        if (starts_with($path, 'http')) {
            return $path;
        }
        $path = ltrim($path, '/');
        if (preg_match('#/v\d+/?$#i', $base)) {
            return $base . '/' . $path;
        }
        return $base . '/v1/' . $path;
    }

    public static function http($channel, $body, $timeout = 120)
    {
        return self::httpPost($channel, self::buildUrl($channel, 'chat/completions'), $body, $timeout);
    }

    public static function httpPost($channel, $url, $body, $timeout = 120)
    {
        $timeout = max(5, (int)$timeout);
        $headers = self::headersFor($channel);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            return ['ok' => false, 'http_code' => 0, 'body' => '', 'error' => $error];
        }
        return ['ok' => $code >= 200 && $code < 300, 'http_code' => $code, 'body' => $response];
    }

    public static function httpGet($channel, $url, $timeout = 30)
    {
        $timeout = max(5, (int)$timeout);
        $headers = self::headersFor($channel);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            return ['ok' => false, 'http_code' => 0, 'body' => '', 'error' => $error];
        }
        return ['ok' => $code >= 200 && $code < 300, 'http_code' => $code, 'body' => $response];
    }

    /**
     * 从上游获取模型列表（OpenAI 兼容 GET /v1/models）
     * $channel 可以是数据库行，也可以是临时的 base_url/api_key 组合（新建渠道尚未保存时）
     */
    public static function fetchRemoteModels($channel, $timeout = 20)
    {
        $url = self::buildUrl($channel, 'models');
        if ($url === '') {
            return ['ok' => false, 'message' => '渠道地址为空'];
        }
        $result = self::httpGet($channel, $url, $timeout);
        if (empty($result['ok'])) {
            $msg = isset($result['error']) && $result['error'] !== '' ? $result['error'] : 'HTTP ' . $result['http_code'];
            return ['ok' => false, 'message' => $msg, 'detail' => substr((string)$result['body'], 0, 300)];
        }
        $json = json_decode($result['body'], true);
        if (!is_array($json) || !isset($json['data']) || !is_array($json['data'])) {
            return ['ok' => false, 'message' => '上游返回格式不符合 OpenAI 规范'];
        }
        $ids = [];
        foreach ($json['data'] as $item) {
            if (is_array($item) && isset($item['id']) && trim((string)$item['id']) !== '') {
                $ids[] = trim((string)$item['id']);
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids);
        return ['ok' => true, 'models' => $ids];
    }

    private static function headersFor($channel)
    {
        $type = isset($channel['type']) ? $channel['type'] : 'openai';
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json, text/event-stream',
            'User-Agent: lcyapi/1.0',
        ];
        if ($type === 'azure') {
            $headers[] = 'api-key: ' . $channel['api_key'];
        } else {
            $headers[] = 'Authorization: Bearer ' . $channel['api_key'];
        }
        foreach (self::extraHeaders($channel) as $extra) {
            $headers[] = $extra;
        }
        return $headers;
    }

    public static function incrementSuccess($id)
    {
        return DB::query('UPDATE channels SET success_count = success_count + 1, last_use_at = NOW() WHERE id = ?', [(int)$id])->rowCount() > 0;
    }

    public static function incrementFail($id)
    {
        return DB::query('UPDATE channels SET fail_count = fail_count + 1, last_use_at = NOW() WHERE id = ?', [(int)$id])->rowCount() > 0;
    }

    public static function getCacheKey()
    {
        return 'channels:all';
    }

    public static function clearCache()
    {
        Cache::deletePrefix('channels:');
    }
}