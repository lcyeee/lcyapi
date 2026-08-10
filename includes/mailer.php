<?php
/**
 * 邮件发送（纯 PHP SMTP 客户端）+ 验证码服务
 * 未配置 SMTP 时进入「日志模式」：验证码写入 data/logs/mail.log，方便本机无邮箱环境测试
 */
class Mailer
{
    const TYPE_EMAIL = 'email';
    const TYPE_FORGOT = 'forgot';

    public static function settings()
    {
        $s = settings_all();
        return [
            'host' => isset($s['smtp_host']) ? $s['smtp_host'] : '',
            'port' => (int)(isset($s['smtp_port']) ? $s['smtp_port'] : 465),
            'username' => isset($s['smtp_username']) ? $s['smtp_username'] : '',
            'password' => isset($s['smtp_password']) ? $s['smtp_password'] : '',
            'encryption' => isset($s['smtp_encryption']) ? $s['smtp_encryption'] : 'ssl',
            'auth' => isset($s['smtp_auth']) ? $s['smtp_auth'] : 'auto',
            'from' => isset($s['smtp_from']) ? $s['smtp_from'] : (isset($s['smtp_username']) ? $s['smtp_username'] : 'noreply@localhost'),
            'from_name' => isset($s['smtp_from_name']) ? $s['smtp_from_name'] : setting('site_name', config('site.name')),
        ];
    }

    public static function configured()
    {
        $cfg = self::settings();
        return $cfg['host'] !== '';
    }

    /**
     * 发送邮件。$body 为 HTML。
     * 返回 ['ok'=>true] 或 ['ok'=>false,'msg'=>...]
     */
    public static function send($to, $subject, $body)
    {
        $cfg = self::settings();
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'msg' => '收件人邮箱格式不正确'];
        }
        if (!$cfg['host']) {
            /* 日志模式：未配置 SMTP 时记录到 mail.log 便于本机测试 */
            @file_put_contents(LOG_PATH . '/mail.log', '[' . date('Y-m-d H:i:s') . '] TO=' . $to . ' SUBJECT=' . $subject . ' BODY=' . $body . PHP_EOL, FILE_APPEND | LOCK_EX);
            write_log('mail(dev) to ' . $to . ' subject=' . $subject, 'mail');
            return ['ok' => true, 'dev' => true];
        }
        return self::smtpSend($cfg, $to, $subject, $body);
    }

    private static function smtpSend($cfg, $to, $subject, $bodyHtml)
    {
        $errno = 0;
        $errstr = '';
        $remote = ($cfg['encryption'] === 'ssl' ? 'ssl://' : '') . $cfg['host'] . ':' . $cfg['port'];
        $fp = @fsockopen($remote, $cfg['port'], $errno, $errstr, 10);
        if (!$fp) {
            return ['ok' => false, 'msg' => '无法连接 SMTP 服务器（' . $errstr . '）'];
        }
        $read = function ($fp) {
            $line = '';
            while (($s = fgets($fp, 515)) !== false) {
                $line .= $s;
                if (isset($s[3]) && $s[3] === ' ') {
                    break;
                }
            }
            return $line;
        };
        $cmd = function ($fp, $cmd, $expect = 250) use ($read) {
            fwrite($fp, $cmd . "\r\n");
            $resp = $read($fp);
            $code = (int)substr($resp, 0, 3);
            return ['ok' => $code === $expect, 'code' => $code, 'resp' => trim($resp)];
        };
        $first = $read($fp);
        if ((int)substr($first, 0, 3) !== 220) {
            fclose($fp);
            return ['ok' => false, 'msg' => 'SMTP 握手失败'];
        }
        $ehlo = function ($fp) use ($read) {
            fwrite($fp, 'EHLO ' . 'lcyapi.local' . "\r\n");
            $resp = '';
            while (($s = fgets($fp, 515)) !== false) {
                $resp .= $s;
                if (isset($s[3]) && $s[3] === ' ') {
                    break;
                }
            }
            return $resp;
        };
        if ($cfg['encryption'] === 'tls') {
            $r = $cmd($fp, 'EHLO ' . ($cfg['host'] ?: 'localhost'));
            if (!$r['ok'] || !@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($fp);
                return ['ok' => false, 'msg' => 'SMTP 无法启用 TLS'];
            }
            $ehloResp = $ehlo($fp);
        } else {
            $ehloResp = $ehlo($fp);
            if (strpos($ehloResp, '250') !== 0) {
                fclose($fp);
                return ['ok' => false, 'msg' => 'SMTP EHLO 失败'];
            }
        }
        $capabilities = strtoupper($ehloResp);
        if ($cfg['username'] !== '') {
            $auth = !empty($cfg['auth']) ? $cfg['auth'] : 'auto';
            $authOk = false;
            $authErr = '';
            if ($auth === 'plain' || $auth === 'auto' && strpos($capabilities, 'AUTH') !== false) {
                $r = $cmd($fp, 'AUTH PLAIN ' . base64_encode("\0" . $cfg['username'] . "\0" . $cfg['password']));
                if ($r['ok']) {
                    $authOk = true;
                } elseif ($auth === 'plain') {
                    $authErr = 'SMTP AUTH PLAIN 失败：' . $r['resp'];
                }
            }
            if (!$authOk && ($auth === 'login' || $auth === 'auto')) {
                $r = $cmd($fp, 'AUTH LOGIN', 334);
                if ($r['ok']) {
                    $r = $cmd($fp, base64_encode($cfg['username']), 334);
                    if ($r['ok']) {
                        $r = $cmd($fp, base64_encode($cfg['password']));
                        $authOk = $r['ok'];
                    } else {
                        $authErr = 'SMTP 用户名被拒绝';
                    }
                } else {
                    $authErr = 'SMTP 不支持 AUTH LOGIN';
                }
            }
            if (!$authOk) {
                fclose($fp);
                return ['ok' => false, 'msg' => $authErr !== '' ? $authErr : 'SMTP 密码错误'];
            }
        }
        $from = $cfg['from'] !== '' ? $cfg['from'] : $cfg['username'];
        $fromName = $cfg['from_name'] !== '' ? $cfg['from_name'] : 'lcyapi';
        $r = $cmd($fp, 'MAIL FROM:<' . $from . '>');
        if (!$r['ok']) {
            fclose($fp);
            return ['ok' => false, 'msg' => 'SMTP MAIL FROM 失败'];
        }
        $r = $cmd($fp, 'RCPT TO:<' . $to . '>');
        if (!$r['ok']) {
            fclose($fp);
            return ['ok' => false, 'msg' => 'SMTP RCPT TO 失败'];
        }
        $r = $cmd($fp, 'DATA', 354);
        if (!$r['ok']) {
            fclose($fp);
            return ['ok' => false, 'msg' => 'SMTP DATA 失败'];
        }
        $boundary = '=_lcy_' . bin2hex(random_bytes(8));
        $message = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <" . $from . ">\r\n";
        $message .= "To: <" . $to . ">\r\n";
        $message .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"\r\n";
        $message .= "Date: " . date('r') . "\r\n";
        $message .= "Message-ID: <" . bin2hex(random_bytes(16)) . "@lcyapi>\r\n";
        $message .= "\r\n";
        $message .= "--" . $boundary . "\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml))));
        $message .= "--" . $boundary . "\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($bodyHtml));
        $message .= "--" . $boundary . "--\r\n.\r\n";
        fwrite($fp, $message);
        $r = $read($fp);
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        if ((int)substr($r, 0, 3) !== 250) {
            return ['ok' => false, 'msg' => 'SMTP 投递失败'];
        }
        return ['ok' => true];
    }

    /**
     * 生成并发送验证码，验证码存 verifications 表
     * $type: email（验证邮箱）/ forgot（找回密码）
     */
    public static function sendVerificationCode($email, $type = self::TYPE_EMAIL, $minutes = 10)
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'msg' => '邮箱格式不正确'];
        }
        /* 防刷：同一邮箱 60 秒内只允许一次 */
        $last = DB::value('SELECT created_at FROM verifications WHERE email = ? AND type = ? ORDER BY id DESC LIMIT 1', [$email, $type]);
        if ($last !== null && strtotime($last) > time() - 60) {
            return ['ok' => false, 'msg' => '发送过于频繁，请 60 秒后再试'];
        }
        $code = (string)random_int(100000, 999999);
        DB::query('UPDATE verifications SET used = 1 WHERE email = ? AND type = ? AND used = 0', [$email, $type]);
        DB::insert('verifications', [
            'email' => $email,
            'type' => $type,
            'code' => $code,
            'expires_at' => date('Y-m-d H:i:s', time() + $minutes * 60),
        ]);
        $siteName = setting('site_name', config('site.name'));
        $subject = $type === self::TYPE_FORGOT ? '找回密码验证码' : '邮箱验证码';
        $body = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;padding:20px;border:1px solid #e5e7eb;border-radius:12px;">'
            . '<h2 style="color:#111827;margin:0 0 12px;">' . e($siteName) . '</h2>'
            . '<p style="color:#4b5563;">您的验证码是：</p>'
            . '<div style="font-size:32px;font-weight:700;letter-spacing:8px;color:#2563eb;padding:16px 0;">' . $code . '</div>'
            . '<p style="color:#4b5563;">' . $minutes . ' 分钟内有效，请勿泄露给他人。</p>'
            . '</div>';
        $r = self::send($email, $subject, $body);
        if (!$r['ok']) {
            return $r;
        }
        return ['ok' => true, 'dev' => !empty($r['dev'])];
    }

    /**
     * 校验验证码（校验即标记 used，防止重放）
     */
    public static function verifyCode($email, $type, $code)
    {
        $email = strtolower(trim($email));
        $code = trim((string)$code);
        if ($email === '' || $code === '') {
            return false;
        }
        $row = DB::fetch('SELECT * FROM verifications WHERE email = ? AND type = ? AND code = ? AND used = 0 ORDER BY id DESC LIMIT 1', [$email, $type, $code]);
        if ($row === false) {
            return false;
        }
        if (strtotime($row['expires_at']) < time()) {
            return false;
        }
        DB::query('UPDATE verifications SET used = 1 WHERE id = ?', [(int)$row['id']]);
        return true;
    }
}
