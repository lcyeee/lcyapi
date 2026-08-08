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
        $fields = ['name', 'type', 'base_url', 'api_key', 'models', 'weight', 'priority', 'status', 'remark'];
        $insert = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $insert[$field] = $data[$field];
            }
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
        $fields = ['name', 'type', 'base_url', 'api_key', 'models', 'weight', 'priority', 'status', 'remark', 'last_use_at'];
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

    public static function candidates($model, $excludeIds = [])
    {
        $cacheKey = 'channels:available:' . $model;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $candidates = $cached;
        } else {
            $candidates = [];
            $channels = self::all(1);
            foreach ($channels as $channel) {
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

    public static function select($model, $excludeIds = [])
    {
        $candidates = self::candidates($model, $excludeIds);
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
        $timeout = max(5, (int)$timeout);
        $headers = self::headersFor($channel);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::buildUrl($channel, 'chat/completions'),
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

    private static function headersFor($channel)
    {
        $type = isset($channel['type']) ? $channel['type'] : 'openai';
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json, text/event-stream',
            'User-Agent: new-api-php/1.0',
        ];
        if ($type === 'azure') {
            $headers[] = 'api-key: ' . $channel['api_key'];
        } else {
            $headers[] = 'Authorization: Bearer ' . $channel['api_key'];
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