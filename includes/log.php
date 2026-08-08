<?php
class Log
{
    public static function write($data)
    {
        $fields = ['user_id', 'token_id', 'channel_id', 'model', 'type', 'prompt_tokens', 'completion_tokens', 'total_tokens', 'cost', 'duration', 'status', 'error_msg', 'ip', 'user_agent', 'request_body'];
        $insert = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $insert[$field] = $data[$field];
            }
        }
        $insert['ip'] = isset($insert['ip']) ? $insert['ip'] : client_ip();
        $insert['user_agent'] = isset($insert['user_agent']) ? mb_substr($insert['user_agent'], 0, 255) : (isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null);
        return DB::insert('logs', $insert);
    }

    public static function writeError($data)
    {
        $fields = ['user_id', 'channel_id', 'model', 'type', 'message', 'request_data', 'response_data', 'ip'];
        $insert = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $insert[$field] = $data[$field];
            }
        }
        $insert['ip'] = isset($insert['ip']) ? $insert['ip'] : client_ip();
        return DB::insert('error_logs', $insert);
    }

    public static function getList($filters = [], $page = 1, $pageSize = 20)
    {
        $where = [];
        $params = [];
        if (isset($filters['user_id']) && $filters['user_id'] !== '') {
            $where[] = 'l.user_id = ?';
            $params[] = (int)$filters['user_id'];
        }
        if (isset($filters['token_id']) && $filters['token_id'] !== '') {
            $where[] = 'l.token_id = ?';
            $params[] = (int)$filters['token_id'];
        }
        if (isset($filters['channel_id']) && $filters['channel_id'] !== '') {
            $where[] = 'l.channel_id = ?';
            $params[] = (int)$filters['channel_id'];
        }
        if (isset($filters['model']) && $filters['model'] !== '') {
            $where[] = 'l.model = ?';
            $params[] = $filters['model'];
        }
        if (isset($filters['type']) && $filters['type'] !== '') {
            $where[] = 'l.type = ?';
            $params[] = $filters['type'];
        }
        if (array_key_exists('status', $filters) && $filters['status'] !== '') {
            $where[] = 'l.status = ?';
            $params[] = (int)$filters['status'];
        }
        if (isset($filters['start']) && $filters['start'] !== '') {
            $where[] = 'l.created_at >= ?';
            $params[] = $filters['start'] . ' 00:00:00';
        }
        if (isset($filters['end']) && $filters['end'] !== '') {
            $where[] = 'l.created_at <= ?';
            $params[] = $filters['end'] . ' 23:59:59';
        }
        $whereSql = empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);
        $offset = (max(1, (int)$page) - 1) * (int)$pageSize;
        $rows = DB::fetchAll(
            'SELECT l.*, u.username AS user_username FROM logs l LEFT JOIN users u ON u.id = l.user_id' . $whereSql . ' ORDER BY l.id DESC LIMIT ' . (int)$pageSize . ' OFFSET ' . $offset,
            $params
        );
        $total = (int)DB::value('SELECT COUNT(*) FROM logs l' . $whereSql, $params);
        return ['items' => $rows, 'total' => $total];
    }

    public static function getById($id, $userId = null)
    {
        $sql = 'SELECT l.*, u.username AS user_username FROM logs l LEFT JOIN users u ON u.id = l.user_id WHERE l.id = ?';
        $params = [(int)$id];
        if ($userId !== null) {
            $sql .= ' AND l.user_id = ?';
            $params[] = (int)$userId;
        }
        return DB::fetch($sql, $params);
    }

    public static function getErrorById($id)
    {
        return DB::fetch('SELECT * FROM error_logs WHERE id = ?', [(int)$id]);
    }

    public static function getErrorList($filters = [], $page = 1, $pageSize = 20)
    {
        $where = [];
        $params = [];
        if (isset($filters['channel_id']) && $filters['channel_id'] !== '') {
            $where[] = 'channel_id = ?';
            $params[] = (int)$filters['channel_id'];
        }
        if (isset($filters['model']) && $filters['model'] !== '') {
            $where[] = 'model = ?';
            $params[] = $filters['model'];
        }
        if (isset($filters['type']) && $filters['type'] !== '') {
            $where[] = 'type = ?';
            $params[] = $filters['type'];
        }
        $whereSql = empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);
        $offset = (max(1, (int)$page) - 1) * (int)$pageSize;
        $rows = DB::fetchAll('SELECT * FROM error_logs' . $whereSql . ' ORDER BY id DESC LIMIT ' . (int)$pageSize . ' OFFSET ' . $offset, $params);
        $total = (int)DB::value('SELECT COUNT(*) FROM error_logs' . $whereSql, $params);
        return ['items' => $rows, 'total' => $total];
    }

    public static function getStats($userId = null, $days = 7)
    {
        $where = '';
        $params = [];
        if ($userId !== null) {
            $where = ' WHERE user_id = ?';
            $params[] = (int)$userId;
        }
        $daily = DB::fetchAll(
            'SELECT DATE(created_at) AS d, COUNT(*) AS cnt, COALESCE(SUM(cost),0) AS cost FROM logs' . $where . ' AND created_at >= ? GROUP BY DATE(created_at) ORDER BY d ASC',
            array_merge($params, [date('Y-m-d', strtotime('-' . ((int)$days - 1) . ' days')) . ' 00:00:00'])
        );
        $modelStats = DB::fetchAll('SELECT model, COUNT(*) cnt, COALESCE(SUM(cost),0) cost FROM logs' . $where . ' GROUP BY model ORDER BY cnt DESC LIMIT 10', $params);
        $channelStats = DB::fetchAll(
            'SELECT l.channel_id, c.name, COUNT(*) cnt FROM logs l LEFT JOIN channels c ON c.id = l.channel_id' . $where . ' GROUP BY l.channel_id ORDER BY cnt DESC LIMIT 10',
            $params
        );
        return ['daily' => $daily, 'models' => $modelStats, 'channels' => $channelStats];
    }

    public static function getTodayCount($userId = null)
    {
        if ($userId !== null) {
            return (int)DB::value('SELECT COUNT(*) FROM logs WHERE user_id = ? AND DATE(created_at) = ?', [(int)$userId, date('Y-m-d')]);
        }
        return (int)DB::value('SELECT COUNT(*) FROM logs WHERE DATE(created_at) = ?', [date('Y-m-d')]);
    }

    public static function getTodayCost($userId = null)
    {
        if ($userId !== null) {
            return (float)DB::value('SELECT COALESCE(SUM(cost),0) FROM logs WHERE user_id = ? AND status = 1 AND DATE(created_at) = ?', [(int)$userId, date('Y-m-d')]);
        }
        return (float)DB::value('SELECT COALESCE(SUM(cost),0) FROM logs WHERE status = 1 AND DATE(created_at) = ?', [date('Y-m-d')]);
    }

    public static function export($filters = [])
    {
        $list = self::getList($filters, 1, 100000);
        $rows = $list['items'];
        $csv = "ID,用户,令牌ID,渠道ID,模型,类型,提示Tokens,补全Tokens,总Tokens,费用,耗时(ms),状态,错误,IP,创建时间\n";
        foreach ($rows as $row) {
            $line = [
                $row['id'],
                $row['user_username'],
                $row['token_id'],
                $row['channel_id'],
                $row['model'],
                $row['type'],
                $row['prompt_tokens'],
                $row['completion_tokens'],
                $row['total_tokens'],
                $row['cost'],
                $row['duration'],
                (int)$row['status'] === 1 ? '成功' : '失败',
                preg_replace('/[\r\n,]+/', ' ', (string)$row['error_msg']),
                $row['ip'],
                $row['created_at'],
            ];
            $csv .= implode(',', $line) . "\n";
        }
        return $csv;
    }
}