<?php
/**
 * TOTP（RFC 6238）一次性验证码 + 2FA 备份码
 * 基于 time()/30 秒窗口 + Base32 密钥，兼容 Google Authenticator / Authy
 */
class TOTP
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret($length = 16)
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::ALPHABET[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * 生成 otpauth:// 链接（供认证器手动添加）
     */
    public static function otpauthUrl($secret, $label, $issuer = 'lcyapi')
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $label)
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }

    /**
     * 生成指定时刻的 6 位验证码
     */
    public static function code($secret, $timestamp = null)
    {
        $timestamp = $timestamp === null ? time() : (int)$timestamp;
        $counter = (int)floor($timestamp / 30);
        $packed = pack('N2', 0, $counter);
        $key = self::base32Decode($secret);
        $hash = hash_hmac('sha1', $packed, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);
        $otp = $binary % 1000000;
        return str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * 校验验证码：允许前后各 1 个时间窗口（共 3 个），容忍时钟偏差
     */
    public static function verify($secret, $code)
    {
        $code = trim((string)$code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $now = time();
        for ($offset = -1; $offset <= 1; $offset++) {
            if (hash_equals(self::code($secret, $now + $offset * 30), $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate and Store Backup Codes in independent table
     */
    public static function generateBackupCodes($userId, $count = 8)
    {
        /* 删除旧码 */
        DB::delete('backup_codes', 'user_id = ?', [(int)$userId]);
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
            $codes[] = $code;
            DB::insert('backup_codes', ['user_id' => (int)$userId, 'code_hash' => password_hash($code, PASSWORD_DEFAULT)]);
        }
        return $codes;
    }

    /**
     * Consume Backup Code from independent table
     */
    public static function consumeBackupCode($userId, $code)
    {
        $code = strtoupper(trim($code));
        $rows = DB::fetchAll('SELECT id, code_hash FROM backup_codes WHERE user_id = ? AND is_used = 0', [(int)$userId]);
        foreach ($rows as $row) {
            if (password_verify($code, $row['code_hash'])) {
                DB::update('backup_codes', ['is_used' => 1], 'id = ?', [(int)$row['id']]);
                return true;
            }
        }
        return false;
    }

    /**
     * Get remaining backup codes count
     */
    public static function remainingBackupCodes($userId)
    {
        return (int)DB::value('SELECT COUNT(*) FROM backup_codes WHERE user_id = ? AND is_used = 0', [(int)$userId]);
    }

    private static function base32Decode($secret)
    {
        $secret = strtoupper(str_replace(' ', '', $secret));
        $buffer = 0;
        $bits = 0;
        $output = '';
        for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
            $pos = strpos(self::ALPHABET, $secret[$i]);
            if ($pos === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $pos;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $output .= chr(($buffer >> $bits) & 0xFF);
            }
        }
        return $output;
    }
}
