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
     * 生成 8 个一次性备份码（返回明文数组；调用方存哈希）
     */
    public static function generateBackupCodes($count = 8)
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        }
        return $codes;
    }

    /**
     * 备份码哈希校验：找到匹配则移除该码（一次性）
     */
    public static function consumeBackupCode($userId, $code)
    {
        $user = User::find((int)$userId);
        if ($user === false || empty($user['backup_codes'])) {
            return false;
        }
        $hashes = json_decode($user['backup_codes'], true);
        if (!is_array($hashes)) {
            return false;
        }
        $code = strtoupper(trim($code));
        foreach ($hashes as $i => $hash) {
            if (password_verify($code, $hash)) {
                array_splice($hashes, $i, 1);
                User::update((int)$userId, ['backup_codes' => json_encode(array_values($hashes))]);
                return true;
            }
        }
        return false;
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
