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
        $fields = ['username', 'email', 'password', 'nickname', 'avatar', 'role', 'quota', 'status', 'aff_code', 'aff_by', 'group'];
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
        if (!isset($insert['aff_code'])) {
            $insert['aff_code'] = self::genAffCode();
        }
        try {
            return DB::insert('users', $insert);
        } catch (Exception $ex) {
            return false;
        }
    }

    /**
     * 生成唯一邀请码（8 位大写字母数字）
     */
    public static function genAffCode()
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        for ($i = 0; $i < 20; $i++) {
            $code = '';
            for ($j = 0; $j < 8; $j++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $exists = DB::value('SELECT id FROM users WHERE aff_code = ?', [$code]);
            if ($exists === false) {
                return $code;
            }
        }
        return strtoupper(bin2hex(random_bytes(4)));
    }

    public static function findByAffCode($code)
    {
        $code = strtoupper(trim((string)$code));
        if ($code === '') {
            return false;
        }
        return DB::fetch('SELECT * FROM users WHERE aff_code = ?', [$code]);
    }

    public static function update($id, $data)
    {
        $fields = ['username', 'email', 'email_verified', 'password', 'nickname', 'avatar', 'role', 'quota', 'used_quota', 'total_quota', 'status', 'group', 'totp_secret', 'totp_enabled', 'backup_codes'];
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
            DB::delete('checkins', 'user_id = ?', [(int)$id]);
            /* 解除下级邀请关系，避免悬挂外键引用 */
            DB::query('UPDATE users SET aff_by = NULL WHERE aff_by = ?', [(int)$id]);
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
        $row = DB::fetch('SELECT quota FROM users WHERE id = ? FOR UPDATE', [(int)$id]);
        if ($row === false || (float)$row['quota'] + 1e-9 < $amount) {
            return false;
        }
        DB::query('UPDATE users SET quota = quota - ?, used_quota = used_quota + ? WHERE id = ?', [$amount, $amount, (int)$id]);
        return true;
    }

    public static function addQuota($id, $amount, $type = 'admin', $remark = '', $operatorId = null, $redemptionId = null)
    {
        $amount = (float)$amount;
        $outer = DB::inTransaction();
        if (!$outer) {
            DB::begin();
        }
        try {
            DB::query('UPDATE users SET quota = quota + ?, total_quota = total_quota + ? WHERE id = ?', [$amount, $amount, (int)$id]);
            DB::insert('recharge_logs', [
                'user_id' => (int)$id,
                'amount' => $amount,
                'type' => $type,
                'redemption_id' => $redemptionId !== null ? (int)$redemptionId : null,
                'operator_id' => $operatorId !== null ? (int)$operatorId : null,
                'remark' => $remark !== '' ? mb_substr($remark, 0, 255) : null,
            ]);
            if (!$outer) {
                DB::commit();
            }
            return true;
        } catch (Exception $ex) {
            if (!$outer) {
                DB::rollback();
            }
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