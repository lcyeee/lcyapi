<?php
class Cache
{
    private static $enabled = true;
    private static $ttl = 3600;
    private static $path = '';

    public static function setConfig($config)
    {
        self::$enabled = $config['enabled'];
        self::$ttl = $config['ttl'];
        self::$path = $config['path'];
    }

    public static function getFilePath($key)
    {
        return self::$path . '/' . sha1($key) . '.cache';
    }

    public static function get($key)
    {
        if (!self::$enabled) {
            return false;
        }
        $file = self::getFilePath($key);
        if (!is_file($file)) {
            return false;
        }
        if (time() - filemtime($file) > self::$ttl) {
            @unlink($file);
            return false;
        }
        $data = @file_get_contents($file);
        if ($data === false) {
            return false;
        }
        $value = @json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        return $value;
    }

    public static function set($key, $value, $ttl = 0)
    {
        if (!self::$enabled) {
            return false;
        }
        $saveTtl = $ttl > 0 ? $ttl : self::$ttl;
        $data = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($data === false) {
            return false;
        }
        $file = self::getFilePath($key);
        if (@file_put_contents($file, $data, LOCK_EX) === false) {
            return false;
        }
        if ($saveTtl != self::$ttl) {
            @touch($file, time() - (self::$ttl - $saveTtl));
        }
        return true;
    }

    public static function delete($key)
    {
        $file = self::getFilePath($key);
        return is_file($file) ? @unlink($file) : true;
    }

    public static function has($key)
    {
        if (!self::$enabled) {
            return false;
        }
        $file = self::getFilePath($key);
        return is_file($file) && (time() - filemtime($file)) <= self::$ttl;
    }

    public static function clear()
    {
        $files = glob(self::$path . '/*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
        return true;
    }

    public static function deletePrefix($prefix)
    {
        $prefixHash = sha1($prefix);
        $files = scandir(self::$path);
        foreach ($files as $file) {
            if (strpos($file, $prefixHash) === 0 && substr($file, -6) === '.cache') {
                @unlink(self::$path . '/' . $file);
            }
        }
        return true;
    }

    public static function remember($key, $ttl, $callback)
    {
        $cached = self::get($key);
        if ($cached !== false) {
            return $cached;
        }
        $value = $callback();
        if ($value !== null && $value !== false) {
            self::set($key, $value, $ttl);
        }
        return $value;
    }
}