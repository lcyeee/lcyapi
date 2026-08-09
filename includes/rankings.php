<?php
class Rankings
{
    /**
     * 获取排行榜数据
     * @param string $period today|week|month|year
     * @return array ['models'=>[], 'vendors'=>[], 'top_movers'=>[], 'top_droppers'=>[]]
     */
    public static function get($period = 'week')
    {
        $since = self::periodStart($period);
        $cacheKey = 'rankings:' . $period;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }
        $logs = DB::fetchAll('SELECT model, cost, prompt_tokens, completion_tokens, status, channel_id FROM logs WHERE created_at >= ? AND status = 1', [$since]);
        $models = [];
        $channels = [];
        $totalCost = 0;
        $totalTokens = 0;
        foreach ($logs as $log) {
            $m = $log['model'];
            if (!isset($models[$m])) {
                $models[$m] = ['cost' => 0, 'tokens' => 0, 'calls' => 0, 'channels' => []];
            }
            $models[$m]['cost'] += (float)$log['cost'];
            $models[$m]['tokens'] += (int)$log['prompt_tokens'] + (int)$log['completion_tokens'];
            $models[$m]['calls']++;
            $cid = (int)$log['channel_id'];
            if ($cid > 0) {
                $models[$m]['channels'][$cid] = true;
            }
            $totalCost += (float)$log['cost'];
            $totalTokens += (int)$log['prompt_tokens'] + (int)$log['completion_tokens'];
        }
        $vendorMap = [];
        $vendorModels = [];
        foreach (ChannelType::all() as $key => $cfg) {
            $vendorMap[$key] = $cfg['name'];
        }
        $modelList = [];
        foreach ($models as $name => $data) {
            $vendor = 'other';
            foreach ($vendorMap as $key => $vname) {
                if (stripos($name, $key) !== false || stripos($name, str_replace('_', '', $key)) !== false) {
                    $vendor = $vname;
                    break;
                }
            }
            if (!isset($vendorModels[$vendor])) {
                $vendorModels[$vendor] = ['cost' => 0, 'tokens' => 0, 'calls' => 0, 'models' => []];
            }
            $vendorModels[$vendor]['cost'] += $data['cost'];
            $vendorModels[$vendor]['tokens'] += $data['tokens'];
            $vendorModels[$vendor]['calls'] += $data['calls'];
            $vendorModels[$vendor]['models'][] = $name;
            $modelList[] = [
                'name' => $name,
                'cost' => round($data['cost'], 6),
                'tokens' => $data['tokens'],
                'calls' => $data['calls'],
                'share' => $totalCost > 0 ? round($data['cost'] / $totalCost * 100, 1) : 0,
                'vendor' => $vendor,
                'channels' => count($data['channels']),
            ];
        }
        usort($modelList, function ($a, $b) { return $b['cost'] <=> $a['cost']; });
        $vendorList = [];
        foreach ($vendorModels as $name => $data) {
            $vendorList[] = [
                'name' => $name,
                'cost' => round($data['cost'], 6),
                'tokens' => $data['tokens'],
                'calls' => $data['calls'],
                'share' => $totalCost > 0 ? round($data['cost'] / $totalCost * 100, 1) : 0,
                'model_count' => count($data['models']),
            ];
        }
        usort($vendorList, function ($a, $b) { return $b['cost'] <=> $a['cost']; });
        $result = [
            'models' => array_slice($modelList, 0, 50),
            'vendors' => array_slice($vendorList, 0, 20),
            'total_cost' => round($totalCost, 6),
            'total_tokens' => $totalTokens,
            'total_calls' => count($logs),
        ];
        Cache::set($cacheKey, $result, 300);
        return $result;
    }

    private static function periodStart($period)
    {
        switch ($period) {
            case 'today': return date('Y-m-d 00:00:00');
            case 'week': return date('Y-m-d 00:00:00', strtotime('-7 days'));
            case 'month': return date('Y-m-d 00:00:00', strtotime('-30 days'));
            case 'year': return date('Y-m-d 00:00:00', strtotime('-365 days'));
            default: return date('Y-m-d 00:00:00', strtotime('-7 days'));
        }
    }
}