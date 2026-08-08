<?php
class Token
{
    const PREFIX = 'sk-';

    public static function generateKey()
    {
        return self::PREFIX . bin2hex(random_bytes(24));
    }

    public static function create($userId, $name, $remainQuota = -1.0, $expiredAt = null)
    {
        $key = self::generateKey();
        $data = [
            'user_id' => (int)$userId,
            'name' => mb_substr($name, 0, 100),
            'key' => $key,
            'hash' => hash('sha256', $key),
            'remain_quota' => (float)$remainQuota,
        ];
        if ($expiredAt !== null && $expiredAt !== '') {
            $data['expired_at'] = $expiredAt;
        }
        $id = DB::insert('tokens', $data);
        return $id ? ['id' => (int)$id, 'key' => $key] : false;
    }

    public static function findByHash($hash)
    {
        return DB::fetch('SELECT * FROM tokens WHERE hash = ?', [$hash]);
    }

    public static function getById($id, $userId = null)
    {
        $sql = 'SELECT * FROM tokens WHERE id = ?';
        $params = [(int)$id];
        if ($userId !== null) {
            $sql .= ' AND user_id = ?';
            $params[] = (int)$userId;
        }
        return DB::fetch($sql, $params);
    }

    public static function getByUser($userId)
    {
        return DB::fetchAll('SELECT * FROM tokens WHERE user_id = ? ORDER BY id DESC', [(int)$userId]);
    }

    public static function update($id, $data, $userId = null)
    {
        $token = self::getById($id, $userId);
        if ($token === false) {
            return false;
        }
        $fields = ['name', 'remain_quota', 'status', 'expired_at'];
        $update = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        if (empty($update)) {
            return true;
        }
        return DB::update('tokens', $update, 'id = ?', [(int)$id]) !== false;
    }

    public static function delete($id, $userId = null)
    {
        $token = self::getById($id, $userId);
        if ($token === false) {
            return false;
        }
        return DB::delete('tokens', 'id = ?', [(int)$id]);
    }

    public static function verify($rawKey)
    {
        $key = self::normalizeKey($rawKey);
        if ($key === '') {
            return ['ok' => false, 'error' => 'invalid_token', 'message' => '无效的认证信息'];
        }
        $token = self::findByHash(hash('sha256', $key));
        if ($token === false) {
            return ['ok' => false, 'error' => 'invalid_token', 'message' => '无效的令牌'];
        }
        if ((int)$token['status'] !== 1) {
            return ['ok' => false, 'error' => 'invalid_token', 'message' => '令牌已被禁用'];
        }
        if ($token['expired_at'] !== null && strtotime($token['expired_at']) < time()) {
            return ['ok' => false, 'error' => 'invalid_token', 'message' => '令牌已过期'];
        }
        if ((float)$token['remain_quota'] < 0 || (float)$token['remain_quota'] > 0) {
        } else {
            return ['ok' => false, 'error' => 'insufficient_token_quota', 'message' => '令牌额度已用完'];
        }
        $user = User::find((int)$token['user_id']);
        if ($user === false) {
            return ['ok' => false, 'error' => 'invalid_token', 'message' => '令牌所属用户不存在'];
        }
        if ((int)$user['status'] !== 1) {
            return ['ok' => false, 'error' => 'user_banned', 'message' => '账号已被禁用'];
        }
        return ['ok' => true, 'token' => $token, 'user' => $user];
    }

    public static function charge($tokenId, $cost)
    {
        $cost = (float)$cost;
        $token = self::getById($tokenId);
        if ($token === false) {
            return false;
        }
        $usedQuota = (float)$token['used_quota'] + $cost;
        $remain = (float)$token['remain_quota'];
        if ($remain >= 0) {
            $remain -= $cost;
            if ($remain < 0) {
                $remain = 0;
            }
        }
        return DB::update('tokens', [
            'used_quota' => $usedQuota,
            'remain_quota' => $remain,
            'used_count' => (int)$token['used_count'] + 1,
            'last_used_at' => date('Y-m-d H:i:s'),
            'last_used_ip' => client_ip(),
        ], 'id = ?', [(int)$tokenId]) !== false;
    }

    public static function touch($tokenId, $ip)
    {
        return DB::update('tokens', ['last_used_at' => date('Y-m-d H:i:s'), 'last_used_ip' => $ip], 'id = ?', [(int)$tokenId]);
    }

    public static function revokeAll($userId)
    {
        return DB::update('tokens', ['status' => 0], 'user_id = ?', [(int)$userId]) !== false;
    }

    public static function maskKey($key)
    {
        if (strlen($key) <= 8) {
            return $key;
        }
        return substr($key, 0, 10) . '••••' . substr($key, -4);
    }

    public static function normalizeKey($rawKey)
    {
        $key = trim((string)$rawKey);
        if ($key === '') {
            return '';
        }
        if (preg_match('/^Bearer\s+(.+)$/i', $key, $m)) {
            $key = trim($m[1]);
        }
        if (preg_match('/^sk-([0-9a-f]{48})$/i', $key, $m)) {
            return 'sk-' . strtolower($m[1]);
        }
        if (preg_match('/^[0-9a-f]{48}$/i', $key)) {
            return 'sk-' . strtolower($key);
        }
        return $key;
    }
}