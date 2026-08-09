<?php
/**
 * 性能指标：从 logs 表实时聚合（延迟/成功率/吞吐量），无需额外存储
 */
class PerfMetrics
{
    /**
     * 查询性能指标（按模型分组）
     * @param string $model 可选，空=全部
     * @param string $group 可选分组
     * @param int $hours 回溯小时
     */
    public static function query($model = '', $group = '', $hours = 24)
    {
        $since = date('Y-m-d H:i:s', time() - $hours * 3600);
        $where = ['created_at >= ?'];
        $params = [$since];
        if ($model !== '') {
            $where[] = 'model = ?';
            $params[] = $model;
        }
        $sql = 'SELECT model, COUNT(*) AS calls, SUM(CASE WHEN status=1 THEN 1 ELSE 0 END) AS success, SUM(duration) AS total_ms, SUM(prompt_tokens + completion_tokens) AS tokens FROM logs WHERE ' . implode(' AND ', $where) . ' GROUP BY model';
        $rows = DB::fetchAll($sql, $params);
        $out = [];
        foreach ($rows as $row) {
            $calls = (int)$row['calls'];
            $out[] = [
                'model' => $row['model'],
                'calls' => $calls,
                'success_rate' => $calls > 0 ? round((int)$row['success'] / $calls * 100, 1) : 0,
                'avg_latency_ms' => $calls > 0 ? round((int)$row['total_ms'] / $calls, 1) : 0,
                'throughput_tokens' => (int)$row['tokens'],
            ];
        }
        usort($out, function ($a, $b) { return $b['calls'] <=> $a['calls']; });
        return $out;
    }

    /**
     * 汇总面板数据
     */
    public static function summary($hours = 24)
    {
        $since = date('Y-m-d H:i:s', time() - $hours * 3600);
        $row = DB::fetch('SELECT COUNT(*) AS calls, COALESCE(AVG(duration),0) AS avg_ms, SUM(CASE WHEN status=1 THEN 1 ELSE 0 END) AS success FROM logs WHERE created_at >= ?', [$since]);
        $calls = (int)$row['calls'];
        return [
            'calls' => $calls,
            'avg_latency_ms' => $calls > 0 ? round((float)$row['avg_ms'], 1) : 0,
            'success_rate' => $calls > 0 ? round((int)$row['success'] / $calls * 100, 1) : 0,
        ];
    }
}