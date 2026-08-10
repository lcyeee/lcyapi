<?php
/**
 * Midjourney 客户端（对齐 new-api relay/mjproxy_handler.go + model/midjourney.go）
 * 提交给外部 Midjourney-Proxy 服务，回调 /mj/notify 驱动状态更新（cron 轮询兜底）
 * 计费：TaskWorker 预扣 → 成功差额结算/失败退款
 */
class Midjourney
{
    const ACTIONS = ['IMAGINE', 'DESCRIBE', 'BLEND', 'CHANGE', 'ZOOM', 'SHORTEN', 'UPLOAD', 'SWAP_FACE', 'EDITS', 'VIDEO', 'MODAL', 'ACTION', 'SIMPLE_CHANGE'];

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
     * 提交任务到 MJ-Proxy，返回 ['ok'=>true,'task_id'=>对外ID,'mj_id'=>上游ID] 或错误
     * $params: 额外参数（base64Array/notifyHook/state/remix 等）
     * $opts: group/channel_id/quota/private_data 等任务框架参数
     */
    public static function submit($userId, $action, $prompt, $params = [], $opts = [])
    {
        if (!self::enabled()) {
            return ['ok' => false, 'msg' => 'Midjourney 未启用'];
        }
        $url = self::proxyUrl();
        if ($url === '') {
            return ['ok' => false, 'msg' => 'Midjourney 代理地址未配置'];
        }
        $action = strtoupper((string)$action);
        if (!in_array($action, self::ACTIONS, true)) {
            return ['ok' => false, 'msg' => '不支持的 MJ 操作: ' . $action];
        }
        $notifyHook = rtrim(setting('midjourney_notify_hook', ''), '/');
        $body = array_merge(['action' => $action, 'prompt' => $prompt], $params);
        if (setting('midjourney_mode_clear', '0') === '1') {
            $body['prompt'] = preg_replace('/--(fast|relax|turbo)\b/i', '', $body['prompt']);
            $body['prompt'] = trim(preg_replace('/\s+/', ' ', $body['prompt']));
        }
        if ($notifyHook !== '') {
            $body['notifyHook'] = $notifyHook;
        }
        $ch = curl_init($url . '/mj/submit/' . strtolower($action));
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'mj-api-secret: ' . self::apiKey()],
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string)$resp, true);
        if ($code < 200 || $code >= 300) {
            return ['ok' => false, 'msg' => '提交失败：' . (isset($json['description']) ? $json['description'] : 'HTTP ' . $code)];
        }
        /* code==21/22（任务已存在/排队中）改写为成功返回 */
        $codeVal = (int)($json['code'] ?? 0);
        $mjId = (string)($json['result'] ?? '');
        if (empty($mjId)) {
            return ['ok' => false, 'msg' => '提交失败：上游未返回任务 ID'];
        }
        /* 按次计费：inPaint/customZoom 不扣费由调用方处理，这里统一走任务框架 */
        $quota = (float)($opts['quota'] ?? 0);
        $privateData = array_merge([
            'upstream_task_id' => $mjId,
            'billing_source' => $opts['billing_source'] ?? 'wallet',
        ], isset($opts['private_data']) ? $opts['private_data'] : []);
        $taskId = TaskWorker::create((int)$userId, TaskWorker::PLATFORM_MIDJOURNEY, $action, $quota, [
            'group' => $opts['group'] ?? null,
            'channel_id' => (int)($opts['channel_id'] ?? 0),
            'task_id' => isset($opts['task_id']) ? $opts['task_id'] : null,
            'properties' => ['input' => $prompt],
            'private_data' => $privateData,
            'data' => ['prompt' => $prompt, 'params' => $params],
        ]);
        /* midjourneys 表双写（绘图日志用） */
        DB::insert('midjourneys', [
            'user_id' => (int)$userId,
            'action' => $action,
            'mj_id' => $mjId,
            'prompt' => $prompt,
            'state' => isset($params['state']) ? (string)$params['state'] : null,
            'submit_time' => time(),
            'status' => 'SUBMITTED',
            'channel_id' => (int)($opts['channel_id'] ?? 0),
            'quota' => $quota,
        ]);
        return ['ok' => true, 'task_id' => $taskId, 'mj_id' => $mjId, 'code' => $codeVal];
    }

    /**
     * 查询任务详情（上游）
     */
    public static function fetchTask($mjId)
    {
        $url = self::proxyUrl();
        $ch = curl_init($url . '/mj/task/' . urlencode((string)$mjId) . '/fetch');
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
     * 根据上游返回更新本地任务状态（回调与轮询共用）
     * $upstream: MJ-Proxy 回调/fetch 返回的 JSON
     */
    public static function applyUpstreamStatus($upstream)
    {
        $mjId = (string)($upstream['id'] ?? $upstream['mjId'] ?? $upstream['taskId'] ?? '');
        if ($mjId === '') {
            return false;
        }
        $row = DB::fetch('SELECT * FROM midjourneys WHERE mj_id = ? ORDER BY id DESC LIMIT 1', [$mjId]);
        if ($row === false) {
            return false;
        }
        $status = strtoupper((string)($upstream['status'] ?? ''));
        $progress = (string)($upstream['progress'] ?? $row['progress']);
        $data = [
            'code' => (int)($upstream['code'] ?? $row['code']),
            'status' => $status !== '' ? $status : $row['status'],
            'progress' => $progress,
            'prompt_en' => isset($upstream['promptEn']) ? (string)$upstream['promptEn'] : $row['prompt_en'],
            'description' => isset($upstream['description']) ? (string)$upstream['description'] : $row['description'],
            'buttons' => isset($upstream['buttons']) ? json_encode($upstream['buttons'], JSON_UNESCAPED_UNICODE) : $row['buttons'],
            'properties' => isset($upstream['properties']) ? json_encode($upstream['properties'], JSON_UNESCAPED_UNICODE) : $row['properties'],
        ];
        if (!empty($upstream['imageUrl'])) {
            $data['image_url'] = (string)$upstream['imageUrl'];
        }
        if (!empty($upstream['videoUrl'])) {
            $data['video_url'] = (string)$upstream['videoUrl'];
        }
        if (!empty($upstream['failReason'])) {
            $data['fail_reason'] = mb_substr((string)$upstream['failReason'], 0, 500);
        }
        if (!empty($upstream['startTime'])) {
            $data['start_time'] = (int)$upstream['startTime'];
        }
        if (!empty($upstream['finishTime'])) {
            $data['finish_time'] = (int)$upstream['finishTime'];
        }
        if ($status === 'SUCCESS' || (int)$progress === 100) {
            $data['status'] = 'SUCCESS';
            $data['progress'] = '100%';
        } elseif ($status === 'FAILURE') {
            $data['progress'] = '0%';
        }
        DB::update('midjourneys', $data, 'id = ?', [(int)$row['id']]);

        /* 同步 tasks 表并结算 */
        $task = DB::fetch('SELECT * FROM tasks WHERE platform = ? AND private_data LIKE ? ORDER BY id DESC LIMIT 1', [TaskWorker::PLATFORM_MIDJOURNEY, '%' . $mjId . '%']);
        if ($task === false) {
            return true;
        }
        if ($data['status'] === 'SUCCESS') {
            $imageUrl = $data['image_url'] ?? '';
            TaskWorker::succeed((int)$task['id'], null, ['data' => json_encode(['image_url' => $imageUrl, 'video_url' => $data['video_url'] ?? ''], JSON_UNESCAPED_UNICODE)]);
        } elseif ($data['status'] === 'FAILURE') {
            TaskWorker::fail((int)$task['id'], $data['fail_reason'] ?? '生成失败', true);
        } else {
            TaskWorker::update((int)$task['id'], ['status' => $data['status'], 'progress' => $data['progress']]);
        }
        return true;
    }

    /**
     * 回调入口 /mj/notify：MJ-Proxy POST 通知
     */
    public static function handleNotify()
    {
        $raw = file_get_contents('php://input');
        $json = json_decode((string)$raw, true);
        if (!is_array($json)) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid payload']);
            return;
        }
        self::applyUpstreamStatus($json);
        echo json_encode(['code' => 1, 'description' => 'ok']);
    }

    /**
     * cron 轮询所有未完成任务（回调兜底）
     */
    public static function pollPending()
    {
        if (!self::enabled()) {
            return 0;
        }
        $rows = DB::fetchAll("SELECT * FROM midjourneys WHERE status NOT IN ('SUCCESS','FAILURE') ORDER BY id ASC LIMIT 20");
        $updated = 0;
        foreach ($rows as $row) {
            $res = self::fetchTask($row['mj_id']);
            if (!$res['ok']) {
                continue;
            }
            if (self::applyUpstreamStatus($res['json'])) {
                $updated++;
            }
        }
        return $updated;
    }

    /**
     * 查询用户绘图任务
     */
    public static function getByUser($userId, $page = 1, $pageSize = 20, $filters = [])
    {
        $where = ['user_id = ?'];
        $params = [(int)$userId];
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }
        $offset = max(0, ((int)$page - 1) * (int)$pageSize);
        return DB::fetchAll('SELECT * FROM midjourneys WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT ' . (int)$pageSize . ' OFFSET ' . $offset, $params);
    }

    public static function countByUser($userId, $filters = [])
    {
        $where = ['user_id = ?'];
        $params = [(int)$userId];
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        return (int)DB::value('SELECT COUNT(*) FROM midjourneys WHERE ' . implode(' AND ', $where), $params);
    }

    public static function getById($id, $userId = null)
    {
        if ($userId !== null) {
            return DB::fetch('SELECT * FROM midjourneys WHERE id = ? AND user_id = ?', [(int)$id, (int)$userId]);
        }
        return DB::fetch('SELECT * FROM midjourneys WHERE id = ?', [(int)$id]);
    }

    /**
     * 本地行转对外 DTO（对齐 new-api dto/midjourney.go 返回结构）
     */
    public static function toDto($row)
    {
        $buttons = json_decode((string)$row['buttons'], true);
        $properties = json_decode((string)$row['properties'], true);
        return [
            'id' => (int)$row['id'],
            'code' => (int)$row['code'],
            'userId' => (int)$row['user_id'],
            'action' => $row['action'],
            'mjId' => $row['mj_id'],
            'prompt' => $row['prompt'],
            'promptEn' => $row['prompt_en'],
            'description' => $row['description'],
            'submitTime' => (int)$row['submit_time'],
            'startTime' => (int)$row['start_time'],
            'finishTime' => (int)$row['finish_time'],
            'imageUrl' => $row['image_url'],
            'videoUrl' => $row['video_url'],
            'status' => $row['status'],
            'progress' => $row['progress'],
            'failReason' => $row['fail_reason'],
            'channelId' => (int)$row['channel_id'],
            'buttons' => is_array($buttons) ? $buttons : [],
            'properties' => is_array($properties) ? $properties : new stdClass(),
            'quota' => (float)$row['quota'],
        ];
    }
}
