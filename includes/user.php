<?php
class User
{
    public static function find($id)
    {
        return DB::fetch('SELECT * FROM users WHERE id = ?', [(int)$id]);
    }

    public static function findByUsername($username)
    {
        return DB::fetch('SELECT * FROM users WHERE username = ?', [$username]);
    }

    public static function findByEmail($email)
    {
        return DB::fetch('SELECT * FROM users WHERE email = ?', [$email]);
    }

    public static function create($data)
    {
        $fields = ['username', 'email', 'password', 'nickname', 'avatar', 'role', 'quota', 'status'];
        $insert = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $insert[$field] = $data[$field];
            }
        }
        if (!isset($insert['quota'])) {
            $insert['quota'] = 0.0000;
        }
        if (!isset($insert['role'])) {
            $insert['role'] = 'user';
        }
        if (!isset($insert['status'])) {
            $insert['status'] = 1;
        }
        try {
            return DB::insert('users', $insert);
        } catch (Exception $ex) {
            return false;
        }
    }

    public static function update($id, $data)
    {
        $fields = ['username', 'email', 'password', 'nickname', 'avatar', 'role', 'quota', 'used_quota', 'total_quota', 'status'];
        $update = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        if (empty($update)) {
            return true;
        }
        return DB::update('users', $update, 'id = ?', [(int)$id]) !== false;
    }

    public static function delete($id)
    {
        DB::begin();
        try {
            DB::delete('users', 'id = ?', [(int)$id]);
            DB::delete('tokens', 'user_id = ?', [(int)$id]);
            DB::delete('recharge_logs', 'user_id = ?', [(int)$id]);
            DB::commit();
            return true;
        } catch (Exception $ex) {
            DB::rollback();
            return false;
        }
    }

    public static function deductQuota($id, $amount)
    {
        $amount = (float)$amount;
        if ($amount <= 0) {
            return true;
        }
        $stmt = DB::query(
            'UPDATE users SET quota = quota - :amt, used_quota = used_quota + :amt WHERE id = :id AND quota >= :amt2',
            ['amt' => $amount, 'amt2' => $amount, 'id' => (int)$id]
        );
        return $stmt->rowCount() > 0;
    }

    public static function addQuota($id, $amount, $type = 'admin', $remark = '', $operatorId = null, $redemptionId = null)
    {
        $amount = (float)$amount;
        DB::begin();
        try {
            DB::query('UPDATE users SET quota = quota + :amt, total_quota = total_quota + :amt WHERE id = :id', [
                'amt' => $amount,
                'id' => (int)$id,
            ]);
            DB::insert('recharge_logs', [
                'user_id' => (int)$id,
                'amount' => $amount,
                'type' => $type,
                'redemption_id' => $redemptionId !== null ? (int)$redemptionId : null,
                'operator_id' => $operatorId !== null ? (int)$operatorId : null,
                'remark' => $remark !== '' ? mb_substr($remark, 0, 255) : null,
            ]);
            DB::commit();
            return true;
        } catch (Exception $ex) {
            DB::rollback();
            write_log('addQuota error: ' . $ex->getMessage());
            return false;
        }
    }

    public static function updateLastLogin($id, $ip)
    {
        return DB::update('users', ['last_login_at' => date('Y-m-d H:i:s'), 'last_login_ip' => $ip], 'id = ?', [(int)$id]) !== false;
    }

    public static function incrementApiCount($id)
    {
        return DB::query('UPDATE users SET api_count = api_count + 1 WHERE id = ?', [(int)$id])->rowCount() > 0;
    }

    public static function count($status = null, $search = '')
    {
        $where = [];
        $params = [];
        if ($status !== null) {
            $where[] = 'status = ?';
            $params[] = (int)$status;
        }
        if ($search !== '') {
            $where[] = '(username LIKE ? OR email LIKE ? OR nickname LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $sql = 'SELECT COUNT(*) FROM users' . (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where));
        return (int)DB::value($sql, $params);
    }

    public static function all($page = 1, $pageSize = 20, $search = '', $status = null, $role = '')
    {
        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = '(username LIKE ? OR email LIKE ? OR nickname LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($status !== null) {
            $where[] = 'status = ?';
            $params[] = (int)$status;
        }
        if ($role !== '') {
            $where[] = 'role = ?';
            $params[] = $role;
        }
        $whereSql = empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);
        $offset = ((int)$page - 1) * (int)$pageSize;
        $sql = 'SELECT * FROM users' . $whereSql . ' ORDER BY id DESC LIMIT ' . (int)$pageSize . ' OFFSET ' . $offset;
        return DB::fetchAll($sql, $params);
    }

    public static function search($keyword)
    {
        if ($keyword === '') {
            return [];
        }
        $like = '%' . $keyword . '%';
        return DB::fetchAll('SELECT id, username, nickname, email, role FROM users WHERE username LIKE ? OR email LIKE ? LIMIT 20', [$like, $like]);
    }
}