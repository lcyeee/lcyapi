<?php
/**
 * 渠道亲和性：同一用户 + 模型在会话期内尽量固定同一渠道，
 * 保证多轮对话上下文连续、账单一致（lcyapi affinity 的轻量实现）
 */
class Affinity
{
    const TTL = 3600; /* 亲和记录有效期 1 小时 */

    public static function get($userId, $model)
    {
        $row = DB::fetch(
            'SELECT channel_id, pinned_at FROM channel_affinity WHERE user_id = ? AND model = ?',
            [(int)$userId, mb_substr((string)$model, 0, 100)]
        );
        if ($row === false) {
            return 0;
        }
        if (strtotime($row['pinned_at']) + self::TTL < time()) {
            self::clear($userId, $model);
            return 0;
        }
        return (int)$row['channel_id'];
    }

    public static function pin($userId, $model, $channelId)
    {
        DB::query(
            'INSERT INTO channel_affinity (user_id, model, channel_id, pinned_at) VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE channel_id = VALUES(channel_id), pinned_at = NOW()',
            [(int)$userId, mb_substr((string)$model, 0, 100), (int)$channelId]
        );
    }

    public static function clear($userId, $model)
    {
        DB::delete('channel_affinity', 'user_id = ? AND model = ?', [(int)$userId, mb_substr((string)$model, 0, 100)]);
    }
}