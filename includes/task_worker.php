<?php
/**
 * 通用异步任务框架（Midjourney/Suno/视频共用）
 * 对齐 new-api model/task.go：
 * - task_id 对外暴露（task_ 前缀），upstream_task_id 存 private_data
 * - 状态机 NOT_START/SUBMITTED/QUEUED/IN_PROGRESS/FAILURE/SUCCESS
 * - quota 预扣 → 成功差额结算/失败退款（private_data 存计费快照）
 * - CAS 更新（updateWithStatus）防并发重复结算
 * - 超时任务自动退款（TaskRefundLegacyCutoff 语义：2026-02-22 之前的遗留任务不退款）
 */
class TaskWorker
{
    const PLATFORM_MIDJOURNEY = 'midjourney';
    const PLATFORM_SUNO = 'suno';
    const PLATFORM_VIDEO = 'video';

    const STATUS_NOT_START = 'NOT_START';
    const STATUS_SUBMITTED = 'SUBMITTED';
    const STATUS_QUEUED = 'QUEUED';
    const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    const STATUS_FAILURE = 'FAILURE';
    const STATUS_SUCCESS = 'SUCCESS';

    /* 2026-02-22 00:00:00 UTC 之前的任务视为遗留任务，超时不退款 */
    const REFUND_LEGACY_CUTOFF = 1771718400;

    public static function generateTaskId()
    {
        return 'task_' . bin2hex(random_bytes(16));
    }

    /**
     * 创建任务（不预扣，调用方自行预扣后传 quota）
     * $opts: group/channel_id/action/properties/private_data/data
     */
    public static function create($userId, $platform, $action, $quota = 0, $opts = [])
    {
        $taskId = !empty($opts['task_id']) ? $opts['task_id'] : self::generateTaskId();
        DB::insert('tasks', [
            'task_id' => $taskId,
            'platform' => $platform,
            'user_id' => (int)$userId,
            'group' => isset($opts['group']) ? $opts['group'] : null,
            'channel_id' => (int)($opts['channel_id'] ?? 0),
            'quota' => (float)$quota,
            'action' => $action,
            'status' => self::STATUS_NOT_START,
            'submit_time' => time(),
            'progress' => '0%',
            'properties' => isset($opts['properties']) ? json_encode($opts['properties'], JSON_UNESCAPED_UNICODE) : null,
            'private_data' => isset($opts['private_data']) ? json_encode($opts['private_data'], JSON_UNESCAPED_UNICODE) : null,
            'data' => isset($opts['data']) ? json_encode($opts['data'], JSON_UNESCAPED_UNICODE) : null,
        ]);
        return $taskId;
    }

    public static function findByTaskId($taskId, $userId = null)
    {
        if ($userId !== null) {
            return DB::fetch('SELECT * FROM tasks WHERE task_id = ? AND user_id = ?', [$taskId, (int)$userId]);
        }
        return DB::fetch('SELECT * FROM tasks WHERE task_id = ?', [$taskId]);
    }

    public static function findById($id)
    {
        return DB::fetch('SELECT * FROM tasks WHERE id = ?', [(int)$id]);
    }

    /**
     * CAS 状态更新：仅当任务当前处于 $fromStatus 时才更新，返回是否成功
     */
    public static function updateWithStatus($id, $fromStatus, $data)
    {
        $sets = [];
        $params = [];
        foreach ($data as $field => $value) {
            $sets[] = '`' . $field . '` = ?';
            $params[] = $value;
        }
        $params[] = $fromStatus;
        $params[] = (int)$id;
        $sql = 'UPDATE tasks SET ' . implode(',', $sets) . ', updated_at = NOW() WHERE status = ? AND id = ?';
        return DB::query($sql, $params)->rowCount() > 0;
    }

    public static function update($id, $data)
    {
        return DB::update('tasks', $data, 'id = ?', [(int)$id]) > 0;
    }

    public static function pending($platform = null, $limit = 50)
    {
        $where = ["status IN ('SUBMITTED','QUEUED','IN_PROGRESS')"];
        $params = [];
        if ($platform !== null) {
            $where[] = 'platform = ?';
            $params[] = $platform;
        }
        return DB::fetchAll('SELECT * FROM tasks WHERE ' . implode(' AND ', $where) . ' ORDER BY id ASC LIMIT ' . (int)$limit, $params);
    }

    public static function hasUnfinished($platform = null)
    {
        $where = ["status IN ('SUBMITTED','QUEUED','IN_PROGRESS')"];
        $params = [];
        if ($platform !== null) {
            $where[] = 'platform = ?';
            $params[] = $platform;
        }
        return DB::value('SELECT id FROM tasks WHERE ' . implode(' AND ', $where) . ' LIMIT 1', $params) !== null;
    }

    public static function getByUser($userId, $page = 1, $pageSize = 20, $filters = [])
    {
        $where = ['user_id = ?'];
        $params = [(int)$userId];
        if (!empty($filters['platform'])) {
            $where[] = 'platform = ?';
            $params[] = $filters['platform'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }
        $offset = max(0, ((int)$page - 1) * (int)$pageSize);
        return DB::fetchAll('SELECT * FROM tasks WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT ' . (int)$pageSize . ' OFFSET ' . $offset, $params);
    }

    public static function countByUser($userId, $filters = [])
    {
        $where = ['user_id = ?'];
        $params = [(int)$userId];
        if (!empty($filters['platform'])) {
            $where[] = 'platform = ?';
            $params[] = $filters['platform'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        return (int)DB::value('SELECT COUNT(*) FROM tasks WHERE ' . implode(' AND ', $where), $params);
    }

    /**
     * 失败/超时退款（CAS：仅 NOT_START/SUBMITTED/QUEUED/IN_PROGRESS → FAILURE）
     * $refund: 是否退款（遗留任务/已结算过的不退）
     */
    public static function fail($id, $reason, $refund = true)
    {
        $task = self::findById($id);
        if ($task === false) {
            return false;
        }
        $data = ['status' => self::STATUS_FAILURE, 'fail_reason' => mb_substr($reason, 0, 500), 'finish_time' => time(), 'progress' => '0%'];
        if (!self::updateWithStatus($id, $task['status'], $data)) {
            return false;
        }
        if ($refund && (float)$task['quota'] > 0 && (int)$task['submit_time'] >= self::REFUND_LEGACY_CUTOFF) {
            $billed = self::wasBilled($task);
            if (!$billed) {
                User::addQuota((int)$task['user_id'], (float)$task['quota'], 'refund', self::platformName($task['platform']) . ' 任务失败退款');
                self::markRefunded($id);
            }
        }
        return true;
    }

    /**
     * 成功结算：如果预扣 quota 与快照不同，多退少补
     * $actualCost: 最终应扣费用
     */
    public static function succeed($id, $actualCost = null, $extra = [])
    {
        $task = self::findById($id);
        if ($task === false) {
            return false;
        }
        $data = ['status' => self::STATUS_SUCCESS, 'finish_time' => time(), 'progress' => '100%'];
        foreach ($extra as $k => $v) {
            $data[$k] = $v;
        }
        if (!self::updateWithStatus($id, $task['status'], $data)) {
            return false;
        }
        /* 差额结算：actualCost 为空则按预扣额结算 */
        $cost = $actualCost !== null ? (float)$actualCost : (float)$task['quota'];
        $pre = (float)$task['quota'];
        if ($cost > $pre) {
            $diff = $cost - $pre;
            User::deductQuota((int)$task['user_id'], $diff);
            DB::update('tasks', ['quota' => $cost], 'id = ?', [(int)$id]);
        } elseif ($cost < $pre && $pre > 0) {
            $diff = $pre - $cost;
            User::addQuota((int)$task['user_id'], $diff, 'refund', self::platformName($task['platform']) . ' 任务差额退款');
            DB::update('tasks', ['quota' => $cost], 'id = ?', [(int)$id]);
        }
        return true;
    }

    /**
     * 超时任务扫描：超过 $hours 小时未完成 → 失败+退款（cron 调用）
     */
    public static function failTimedOut($hours = 2, $limit = 50)
    {
        $cutoff = time() - $hours * 3600;
        $tasks = DB::fetchAll('SELECT * FROM tasks WHERE status IN ("SUBMITTED","QUEUED","IN_PROGRESS") AND submit_time < ? ORDER BY id ASC LIMIT ' . (int)$limit, [$cutoff]);
        $count = 0;
        foreach ($tasks as $task) {
            if (self::fail((int)$task['id'], '任务超时未完成（超过 ' . $hours . ' 小时）', true)) {
                $count++;
            }
        }
        return $count;
    }

    private static function wasBilled($task)
    {
        $pd = json_decode((string)$task['private_data'], true);
        return is_array($pd) && !empty($pd['refunded']);
    }

    private static function markRefunded($id)
    {
        $task = self::findById($id);
        if ($task === false) {
            return;
        }
        $pd = json_decode((string)$task['private_data'], true);
        if (!is_array($pd)) {
            $pd = [];
        }
        $pd['refunded'] = true;
        DB::update('tasks', ['private_data' => json_encode($pd, JSON_UNESCAPED_UNICODE), 'quota' => 0], 'id = ?', [(int)$id]);
    }

    public static function platformName($platform)
    {
        switch ($platform) {
            case self::PLATFORM_MIDJOURNEY: return 'Midjourney';
            case self::PLATFORM_SUNO: return 'Suno';
            case self::PLATFORM_VIDEO: return '视频';
            default: return $platform;
        }
    }

    /**
     * 状态机转 OpenAI 视频状态
     */
    public static function toVideoStatus($status)
    {
        switch ($status) {
            case self::STATUS_SUBMITTED:
            case self::STATUS_QUEUED:
                return 'queued';
            case self::STATUS_IN_PROGRESS:
                return 'in_progress';
            case self::STATUS_SUCCESS:
                return 'completed';
            case self::STATUS_FAILURE:
                return 'failed';
            default:
                return 'unknown';
        }
    }

    /**
     * 任务转 OpenAI 视频对象
     */
    public static function toOpenAIVideo($task)
    {
        $props = json_decode((string)$task['properties'], true);
        $pd = json_decode((string)$task['private_data'], true);
        $resultUrl = '';
        if (is_array($pd) && !empty($pd['result_url'])) {
            $resultUrl = $pd['result_url'];
        } elseif (is_array($props) && !empty($props['result_url'])) {
            $resultUrl = $props['result_url'];
        }
        return [
            'id' => $task['task_id'],
            'status' => self::toVideoStatus($task['status']),
            'model' => is_array($props) && !empty($props['origin_model_name']) ? $props['origin_model_name'] : '',
            'created_at' => (int)$task['submit_time'],
            'completed_at' => (int)$task['finish_time'],
            'metadata' => ['url' => $resultUrl],
        ];
    }

    /**
     * 生成计费快照（供轮询阶段差额结算）
     */
    public static function billingSnapshot($modelPrice, $groupRatio, $modelRatio, $otherRatios, $originModelName, $perCallBilling = false)
    {
        return [
            'model_price' => (float)$modelPrice,
            'group_ratio' => (float)$groupRatio,
            'model_ratio' => (float)$modelRatio,
            'other_ratios' => $otherRatios,
            'origin_model_name' => $originModelName,
            'per_call_billing' => (bool)$perCallBilling,
        ];
    }
}
