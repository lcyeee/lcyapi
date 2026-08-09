<?php
/**
 * 用量数据统计：按日期/用户聚合额度消耗
 */
class UsageData
{
    /**
     * 按日期聚合（管理端）
     */
    public static function byDate($days = 30)
    {
        $since = date('Y-m-d', strtotime('-' . max(1, $days) . ' days'));
        $rows = DB::fetchAll('SELECT DATE(created_at) AS d, COALESCE(SUM(cost),0) AS cost, COUNT(*) AS calls, COALESCE(SUM(prompt_tokens+completion_tokens),0) AS tokens FROM logs WHERE created_at >= ? AND status=1 GROUP BY DATE(created_at) ORDER BY d ASC', [$since . ' 00:00:00']);
        $map = [];
        foreach ($rows as $row) {
            $map[$row['d']] = ['cost' => (float)$row['cost'], 'calls' => (int)$row['calls'], 'tokens' => (int)$row['tokens']];
        }
        $labels = [];
        $costs = [];
        $calls = [];
        for ($i = max(1, $days) - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime('-' . $i . ' days'));
            $labels[] = date('m-d', strtotime($d));
            $costs[] = isset($map[$d]) ? $map[$d]['cost'] : 0;
            $calls[] = isset($map[$d]) ? $map[$d]['calls'] : 0;
        }
        return ['labels' => $labels, 'costs' => $costs, 'calls' => $calls];
    }

    /**
     * 按用户聚合（管理端）
     */
    public static function byUser($limit = 20, $days = 30)
    {
        $since = date('Y-m-d', strtotime('-30 days'));
        $rows = DB::fetchAll('SELECT l.user_id, u.username, COALESCE(SUM(l.cost),0) AS cost, COUNT(*) AS calls FROM logs l LEFT JOIN users u ON u.id=l.user_id WHERE l.created_at >= ? AND l.status=1 GROUP BY l.user_id ORDER BY cost DESC LIMIT ?', [$since . ' 00:00:00', (int)$limit]);
        return $rows;
    }

    /**
     * 当前用户按日用量
     */
    public static function selfByDate($userId, $days = 30)
    {
        $since = date('Y-m-d', strtotime('-' . max(1, $days) . ' days'));
        $rows = DB::fetchAll('SELECT DATE(created_at) AS d, COALESCE(SUM(cost),0) AS cost, COUNT(*) AS calls FROM logs WHERE created_at >= ? AND user_id=? AND status=1 GROUP BY DATE(created_at) ORDER BY d ASC', [$since . ' 00:00:00', (int)$userId]);
        $map = [];
        foreach ($rows as $row) {
            $map[$row['d']] = ['cost' => (float)$row['cost'], 'calls' => (int)$row['calls']];
        }
        $labels = [];
        $costs = [];
        $calls = [];
        for ($i = max(1, $days) - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime('-' . $i . ' days'));
            $labels[] = date('m-d', strtotime($d));
            $costs[] = isset($map[$d]) ? $map[$d]['cost'] : 0;
            $calls[] = isset($map[$d]) ? $map[$d]['calls'] : 0;
        }
        return ['labels' => $labels, 'costs' => $costs, 'calls' => $calls];
    }
}