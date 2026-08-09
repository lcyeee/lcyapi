<?php
/**
 * 任务轮询引擎：通用异步任务框架（视频/Midjourney/Suno 共用）
 */
class TaskWorker
{
    const PLATFORM_MIDJOURNEY = 'midjourney';
    const PLATFORM_SUNO = 'suno';
    const PLATFORM_VIDEO = 'video';

    /**
     * 创建任务
     */
    public static function create($userId, $platform, $action, $upstreamId, $quota = 0)
    {
        return DB::insert('tasks', [
            'user_id' => (int)$userId,
            'platform' => $platform,
            'action' => $action,
            'upstream_task_id' => $upstreamId,
            'status' => 'pending',
            'quota_used' => (float)$quota,
            'data' => '{}',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 更新任务状态
     */
    public static function update($id, $data)
    {
        return DB::update('tasks', $data, 'id = ?', [(int)$id]);
    }

    /**
     * 查询未完成任务
     */
    public static function pending($platform = null, $limit = 50)
    {
        $where = ["status IN ('pending','processing')"];
        $params = [];
        if ($platform !== null) {
            $where[] = 'platform = ?';
            $params[] = $platform;
        }
        return DB::fetchAll('SELECT * FROM tasks WHERE ' . implode(' AND ', $where) . ' ORDER BY id ASC LIMIT ' . (int)$limit, $params);
    }

    /**
     * 任务完成退款
     */
    public static function refund($taskId)
    {
        $task = DB::fetch('SELECT * FROM tasks WHERE id = ?', [(int)$taskId]);
        if ($task === false || (float)$task['quota_used'] <= 0) {
            return;
        }
        User::addQuota((int)$task['user_id'], (float)$task['quota_used'], 'refund', $task['platform'] . ' 任务退款');
        DB::update('tasks', ['quota_used' => 0], 'id = ?', [(int)$taskId]);
    }
}