<?php
/**
 * Suno 音乐生成客户端（对齐 new-api /suno 路由 + dto/suno.go）
 * 提交到外部 Suno-GoAPI 服务，任务复用 tasks 表（platform=suno），cron 轮询兜底
 */
class Suno
{
    const ACTIONS = ['MUSIC', 'LYRICS'];

    public static function enabled()
    {
        return setting('suno_enabled', '0') === '1';
    }

    public static function proxyUrl()
    {
        return rtrim(setting('suno_proxy_url', ''), '/');
    }

    public static function apiKey()
    {
        return setting('suno_api_key', '');
    }

    private static function mapStatus($status)
    {
        switch (strtolower((string)$status)) {
            case 'submitted': return TaskWorker::STATUS_SUBMITTED;
            case 'queueing': return TaskWorker::STATUS_QUEUED;
            case 'processing': return TaskWorker::STATUS_IN_PROGRESS;
            case 'success': return TaskWorker::STATUS_SUCCESS;
            case 'failed': return TaskWorker::STATUS_FAILURE;
            default: return TaskWorker::STATUS_NOT_START;
        }
    }

    /**
     * 提交任务：POST {proxy}/suno/submit/{action}，body = GoAPI 格式（custom_mode+input+notify_hook）
     * $params: prompt/title/tags/mv/continue_at/task_id/continue_clip_id/make_instrumental/gpt_description_prompt
     */
    public static function submit($userId, $action, $params = [], $opts = [])
    {
        if (!self::enabled()) {
            return ['ok' => false, 'msg' => 'Suno 未启用'];
        }
        $url = self::proxyUrl();
        if ($url === '') {
            return ['ok' => false, 'msg' => 'Suno 代理地址未配置'];
        }
        $action = strtoupper((string)$action);
        if (!in_array($action, self::ACTIONS, true)) {
            return ['ok' => false, 'msg' => '不支持的 Suno 操作: ' . $action];
        }
        $body = ['custom_mode' => !empty($params['custom_mode']) ? true : false, 'input' => $params];
        $notifyHook = rtrim(setting('suno_notify_hook', ''), '/');
        if ($notifyHook !== '') {
            $body['notify_hook'] = $notifyHook;
        }
        $ch = curl_init($url . '/suno/submit/' . strtolower($action));
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . self::apiKey()],
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string)$resp, true);
        if ($code < 200 || $code >= 300 || !is_array($json)) {
            return ['ok' => false, 'msg' => '提交失败：HTTP ' . $code];
        }
        $data = isset($json['data']) && is_array($json['data']) ? $json['data'] : [];
        $taskId = (string)($data['task_id'] ?? '');
        if ($taskId === '') {
            return ['ok' => false, 'msg' => '提交失败：上游未返回任务 ID'];
        }
        $quota = (float)($opts['quota'] ?? 0);
        TaskWorker::create((int)$userId, TaskWorker::PLATFORM_SUNO, $action, $quota, [
            'group' => $opts['group'] ?? null,
            'channel_id' => (int)($opts['channel_id'] ?? 0),
            'task_id' => $taskId,
            'properties' => ['input' => $params],
            'private_data' => ['billing_source' => $opts['billing_source'] ?? 'wallet'],
            'data' => ['params' => $params],
        ]);
        DB::update('tasks', ['status' => TaskWorker::STATUS_SUBMITTED], 'task_id = ?', [$taskId]);
        return ['ok' => true, 'task_id' => $taskId];
    }

    /**
     * 上游状态应用到 tasks 表（回调/轮询共用）
     */
    public static function applyUpstreamStatus($userId, $taskId, $upstream)
    {
        $task = DB::fetch('SELECT * FROM tasks WHERE task_id = ? AND platform = ? AND user_id = ?', [(string)$taskId, TaskWorker::PLATFORM_SUNO, (int)$userId]);
        if ($task === false) {
            return false;
        }
        $status = self::mapStatus((string)($upstream['status'] ?? ''));
        $clips = isset($upstream['clips']) && is_array($upstream['clips']) ? $upstream['clips'] : [];
        $data = ['status' => $status, 'progress' => $status === TaskWorker::STATUS_SUCCESS ? '100%' : ($status === TaskWorker::STATUS_IN_PROGRESS ? '50%' : ($status === TaskWorker::STATUS_SUBMITTED || $status === TaskWorker::STATUS_QUEUED ? '0%' : $task['progress']))];
        if (!empty($upstream['start_time'])) {
            $data['start_time'] = (int)$upstream['start_time'];
        }
        if (!empty($upstream['finish_time'])) {
            $data['finish_time'] = (int)$upstream['finish_time'];
        }
        $failReason = (string)($upstream['fail_reason'] ?? '');
        if ($failReason !== '') {
            $data['fail_reason'] = mb_substr($failReason, 0, 500);
        }
        if (!empty($clips)) {
            $data['data'] = json_encode(['clips' => $clips], JSON_UNESCAPED_UNICODE);
        }
        if ($status === TaskWorker::STATUS_SUCCESS) {
            $resultUrl = '';
            foreach ($clips as $clip) {
                if (!empty($clip['audio_url'])) {
                    $resultUrl = $clip['audio_url'];
                    break;
                }
            }
            TaskWorker::succeed((int)$task['id'], null, ['data' => json_encode(['clips' => $clips], JSON_UNESCAPED_UNICODE), 'private_data' => json_encode(['result_url' => $resultUrl], JSON_UNESCAPED_UNICODE)]);
        } elseif ($status === TaskWorker::STATUS_FAILURE) {
            TaskWorker::fail((int)$task['id'], $data['fail_reason'] ?? '生成失败', true);
        } else {
            TaskWorker::update((int)$task['id'], $data);
        }
        return true;
    }

    /**
     * 查询单个任务：GET {proxy}/suno/fetch/{taskId}
     */
    public static function fetchById($userId, $taskId)
    {
        $url = self::proxyUrl();
        $ch = curl_init($url . '/suno/fetch/' . urlencode((string)$taskId));
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . self::apiKey()],
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string)$resp, true);
        if ($code < 200 || $code >= 300 || !is_array($json)) {
            return ['ok' => false];
        }
        $data = isset($json['data']) && is_array($json['data']) ? $json['data'] : [];
        self::applyUpstreamStatus($userId, $taskId, $data);
        return ['ok' => true, 'json' => $data];
    }

    /**
     * cron 轮询所有未完成 Suno 任务（回调兜底）
     */
    public static function pollPending()
    {
        if (!self::enabled()) {
            return 0;
        }
        $rows = TaskWorker::pending(TaskWorker::PLATFORM_SUNO, 20);
        $updated = 0;
        foreach ($rows as $row) {
            $res = self::fetchById((int)$row['user_id'], $row['task_id']);
            if ($res['ok']) {
                $updated++;
            }
        }
        return $updated;
    }

    /**
     * 批量转 SunoDataResponse（任务列表）
     */
    public static function toDto($task)
    {
        $data = json_decode((string)$task['data'], true);
        $clips = is_array($data) && isset($data['clips']) ? $data['clips'] : [];
        $status = strtolower((string)$task['status']);
        if ($status === 'not_start') {
            $status = 'submitted';
        }
        return [
            'task_id' => $task['task_id'],
            'action' => $task['action'],
            'status' => $status,
            'fail_reason' => $task['fail_reason'],
            'submit_time' => (int)$task['submit_time'],
            'start_time' => (int)$task['start_time'],
            'finish_time' => (int)$task['finish_time'],
            'data' => ['clips' => $clips],
        ];
    }
}
