<?php
/**
 * 请求体磁盘缓存：大请求体写入临时文件，释放内存
 */
class BodyStorage
{
    const THRESHOLD = 1048576; /* 1MB 以上写磁盘 */

    public static function store($data)
    {
        if (strlen($data) < self::THRESHOLD) {
            return ['type' => 'memory', 'data' => $data];
        }
        $file = tempnam(sys_get_temp_dir(), 'lcybody_');
        if ($file === false) {
            return ['type' => 'memory', 'data' => $data];
        }
        file_put_contents($file, $data);
        return ['type' => 'disk', 'file' => $file];
    }

    public static function read($storage)
    {
        if ($storage['type'] === 'memory') {
            return $storage['data'];
        }
        return is_file($storage['file']) ? file_get_contents($storage['file']) : '';
    }

    public static function cleanup($storage)
    {
        if ($storage['type'] === 'disk' && is_file($storage['file'])) {
            @unlink($storage['file']);
        }
    }
}