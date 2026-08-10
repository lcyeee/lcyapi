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
            'top_movers' => self::topMovers($period),
            'top_droppers' => self::topDroppers($period),
            'history' => self::history($period),
        ];
        Cache::set($cacheKey, $result, 300);
        return $result;
    }

    /**
     * 排名上升最快的模型（对比上一周期）
     */
    private static function topMovers($period)
    {
        $cur = self::rankByCost($period);
        list($from, $to) = self::prevPeriod($period);
        $prev = self::rankByRange($from, $to);
        $deltas = [];
        $rankOf = function ($map) {
            $out = [];
            $i = 0;
            foreach ($map as $name => $cost) {
                $out[$name] = ++$i;
            }
            return $out;
        };
        $curRanks = $rankOf($cur);
        $prevRanks = $rankOf($prev);
        foreach ($curRanks as $name => $r) {
            if (isset($prevRanks[$name]) && $prevRanks[$name] > 0) {
                $delta = $prevRanks[$name] - $r;
                if ($delta > 0) {
                    $deltas[$name] = $delta;
                }
            }
        }
        arsort($deltas);
        $out = [];
        foreach (array_slice($deltas, 0, 6, true) as $name => $delta) {
            $out[] = ['name' => $name, 'delta' => $delta, 'direction' => 'up'];
        }
        return $out;
    }

    /**
     * 排名下降最快的模型（对比上一周期）
     */
    private static function topDroppers($period)
    {
        $cur = self::rankByCost($period);
        list($from, $to) = self::prevPeriod($period);
        $prev = self::rankByRange($from, $to);
        $deltas = [];
        $rankOf = function ($map) {
            $out = [];
            $i = 0;
            foreach ($map as $name => $cost) {
                $out[$name] = ++$i;
            }
            return $out;
        };
        $curRanks = $rankOf($cur);
        $prevRanks = $rankOf($prev);
        foreach ($curRanks as $name => $r) {
            if (isset($prevRanks[$name]) && $prevRanks[$name] > 0) {
                $delta = $r - $prevRanks[$name];
                if ($delta > 0) {
                    $deltas[$name] = $delta;
                }
            }
        }
        arsort($deltas);
        $out = [];
        foreach (array_slice($deltas, 0, 6, true) as $name => $delta) {
            $out[] = ['name' => $name, 'delta' => $delta, 'direction' => 'down'];
        }
        return $out;
    }

    /**
     * 历史趋势：按时间桶聚合 Top 模型用量
     */
    private static function history($period)
    {
        $since = self::periodStart($period);
        $now = time();
        $bucketCount = 7;
        $topModels = array_slice(array_keys(self::rankByCost($period)), 0, 10);
        $interval = max(1, floor(($now - strtotime($since)) / $bucketCount));
        $buckets = [];
        for ($i = 0; $i < $bucketCount; $i++) {
            $label = date('m-d', $since == date('Y-m-d 00:00:00', $now) ? $now - ($bucketCount - 1 - $i) * $interval : strtotime($since) + $i * $interval);
            $values = [];
            foreach ($topModels as $m) { $values[$m] = 0.0; }
            $buckets[] = ['label' => $label, 'values' => $values];
        }
        $rows = DB::fetchAll('SELECT model, DATE(created_at) AS d, COALESCE(SUM(cost),0) AS c FROM logs WHERE created_at >= ? AND status=1 GROUP BY model, DATE(created_at)', [$since]);
        foreach ($rows as $row) {
            if (!isset($buckets[0]['values'][$row['model']])) {
                continue;
            }
            $idx = (int)floor((strtotime($row['d']) - strtotime($since)) / max(1, $interval));
            if ($idx < 0) { $idx = 0; }
            if ($idx >= $bucketCount) { $idx = $bucketCount - 1; }
            $buckets[$idx]['values'][$row['model']] = (float)$row['c'];
        }
        return $buckets;
    }

    private static function rankByCost($period)
    {
        $since = self::periodStart($period);
        $rows = DB::fetchAll('SELECT model, COALESCE(SUM(cost),0) AS c FROM logs WHERE created_at >= ? AND status=1 GROUP BY model ORDER BY c DESC', [$since]);
        $map = [];
        foreach ($rows as $r) { $map[$r['model']] = (float)$r['c']; }
        return $map;
    }

    private static function prevPeriod($period)
    {
        $end = strtotime(date('Y-m-d 00:00:00'));
        switch ($period) {
            case 'today':
                $start = $end - 86400;
                break;
            case 'month':
                $start = $end - 30 * 86400;
                break;
            case 'year':
                $start = $end - 365 * 86400;
                break;
            case 'week':
            default:
                $start = $end - 7 * 86400;
                break;
        }
        return [date('Y-m-d 00:00:00', $start), date('Y-m-d 23:59:59', $end - 1)];
    }

    /**
     * 上一周期费用排行（按时间段查询）
     */
    private static function rankByRange($from, $to)
    {
        $rows = DB::fetchAll('SELECT model, COALESCE(SUM(cost),0) AS c FROM logs WHERE created_at >= ? AND created_at < ? AND status=1 GROUP BY model ORDER BY c DESC', [$from, $to]);
        $map = [];
        foreach ($rows as $r) { $map[$r['model']] = (float)$r['c']; }
        return $map;
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