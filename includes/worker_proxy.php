<?php
/**
 * Worker 模式：通过外部代理节点转发请求（SSRF 防护）
 */
class WorkerProxy
{
    public static function enabled()
    {
        return setting('worker_enabled', '0') === '1';
    }

    public static function workerUrl()
    {
        return rtrim(setting('worker_url', ''), '/');
    }

    public static function workerKey()
    {
        return setting('worker_key', '');
    }

    /**
     * 通过 Worker 转发出站请求
     */
    public static function forward($method, $url, $headers = [], $body = null)
    {
        if (!self::enabled()) {
            return null;
        }
        $workerUrl = self::workerUrl();
        if ($workerUrl === '') {
            return null;
        }
        $payload = json_encode([
            'method' => strtoupper($method),
            'url' => $url,
            'headers' => $headers,
            'body' => $body !== null ? base64_encode($body) : null,
        ], JSON_UNESCAPED_SLASHES);
        $ch = curl_init($workerUrl . '/proxy');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Worker-Key: ' . self::workerKey()],
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string)$resp, true);
        if ($code !== 200 || !is_array($json)) {
            return null;
        }
        return $json;
    }
}