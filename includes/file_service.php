<?php
/**
 * 文件服务：下载/解码/缓存文件（URL/Base64 来源）
 */
class FileService
{
    public static function download($url, $maxSize = 10485760)
    {
        $cacheKey = 'file:' . sha1($url);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'lcyapi/1.0',
        ]);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        curl_close($ch);
        if ($code !== 200 || $size > $maxSize) {
            return null;
        }
        $mime = self::detectMimeType($data);
        Cache::set($cacheKey, ['data' => $data, 'mime' => $mime], 3600);
        return ['data' => $data, 'mime' => $mime];
    }

    public static function decodeBase64($dataUri)
    {
        if (!preg_match('/^data:([^;]+);base64,(.+)$/', $dataUri, $m)) {
            return null;
        }
        return ['data' => base64_decode($m[2]), 'mime' => $m[1]];
    }

    public static function detectMimeType($data)
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($data);
        return $mime ?: 'application/octet-stream';
    }

    public static function getImageDimensions($data)
    {
        $im = @imagecreatefromstring($data);
        if ($im === false) {
            return null;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        imagedestroy($im);
        return ['width' => $w, 'height' => $h];
    }
}