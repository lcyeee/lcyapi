<?php
/**
 * Midjourney 代理（对齐 new-api 的简化版）
 * 通过 Midjourney-Proxy 服务提交/查询任务，cron 轮询更新
 */
class Midjourney
{
    const ACTIONS = ['IMAGINE', 'DESCRIBE', 'BLEND', 'CHANGE', 'ZOOM', 'SHORTEN', 'UPLOAD', 'SWAP_FACE'];

    public static function enabled()
    {
        return setting('midjourney_enabled', '0') === '1';
    }

    public static function proxyUrl()
    {
        return rtrim(setting('midjourney_proxy_url', ''), '/');
    }

    public static function apiKey()
    {
        return setting('midjourney_api_key', '');
    }

    /**
     * 提交任务
     */
    public static function submit($userId, $action, $prompt, $params = [])
    {
        if (!self::enabled()) {
            return ['ok' => false, 'msg' => 'Midjourney 未启用'];
        }
        $url = self::proxyUrl();
        if ($url === '') {
            return ['ok' => false, 'msg' => 'Midjourney 代理地址未配置'];
        }
        $body = array_merge(['action' => strtoupper($action), 'prompt' => $prompt], $params);
        $ch = curl_init($url . '/mj/submit/imagine');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'mj-api-secret: ' . self::apiKey()],
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string)$resp, true);
        if ($code < 200 || $code >= 300 || empty($json['result'])) {
            return ['ok' => false, 'msg' => '提交失败：' . (isset($json['description']) ? $json['description'] : 'HTTP ' . $code)];
        }
        $id = DB::insert('tasks', [
            'user_id' => (int)$userId,
            'platform' => 'midjourney',
            'action' => strtoupper($action),
            'upstream_task_id' => (string)$json['result'],
            'status' => 'pending',
            'data' => json_encode(['prompt' => $prompt], JSON_UNESCAPED_UNICODE),
        ]);
        return ['ok' => true, 'task_id' => $id, 'upstream_id' => (string)$json['result']];
    }

    /**
     * 查询任务状态（供 cron 轮询）
     */
    public static function fetchTask($upstreamId)
    {
        $url = self::proxyUrl();
        $ch = curl_init($url . '/mj/task/' . urlencode((string)$upstreamId) . '/fetch');
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['mj-api-secret: ' . self::apiKey()],
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string)$resp, true);
        if ($code < 200 || $code >= 300 || !is_array($json)) {
            return ['ok' => false];
        }
        return ['ok' => true, 'json' => $json];
    }

    /**
     * cron 轮询所有未完成任务
     */
    public static function pollPending()
    {
        if (!self::enabled()) {
            return 0;
        }
        $tasks = DB::fetchAll("SELECT * FROM tasks WHERE platform='midjourney' AND status IN ('pending','processing') LIMIT 20");
        $updated = 0;
        foreach ($tasks as $task) {
            $res = self::fetchTask($task['upstream_task_id']);
            if (!$res['ok']) {
                continue;
            }
            $j = $res['json'];
            $status = $j['status'] ?? '';
            $map = ['SUCCESS' => 'success', 'FAILURE' => 'failed', 'IN_PROGRESS' => 'processing', 'QUEUED' => 'pending'];
            if (isset($map[$status])) {
                $newStatus = $map[$status];
                if ($newStatus === 'success') {
                    $imageUrl = $j['imageUrl'] ?? ($j['image_url'] ?? '');
                    DB::update('tasks', ['status' => $newStatus, 'progress' => 100, 'data' => json_encode(['image_url' => $imageUrl])], 'id = ?', [(int)$task['id']]);
                    Midjourney::refundOnSuccess((int)$task['id']);
                } elseif ($newStatus === 'failed') {
                    DB::update('tasks', ['status' => $newStatus, 'data' => json_encode(['error' => $j['failReason'] ?? '生成失败'])], 'id = ?', [(int)$task['id']]);
                    Midjourney::refund((int)$task['id']);
                } else {
                    DB::update('tasks', ['status' => $newStatus, 'progress' => (int)($j['progress'] ?? 0)], 'id = ?', [(int)$task['id']]);
                }
                $updated++;
            }
        }
        return $updated;
    }

    private static function refundOnSuccess($taskId)
    {
        return;
    }

    private static function refund($taskId)
    {
        $task = DB::fetch('SELECT * FROM tasks WHERE id = ?', [(int)$taskId]);
        if ($task === false) {
            return;
        }
        $cost = (float)$task['quota_used'];
        if ($cost > 0) {
            User::addQuota((int)$task['user_id'], $cost, 'refund', 'Midjourney 任务失败退款');
        }
    }
}