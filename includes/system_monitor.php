<?php
/**
 * 系统监控：采集 CPU/内存/磁盘使用率（尽力而为，Windows 通过系统命令）
 */
class SystemMonitor
{
    private static $cached = null;
    private static $cachedAt = 0;

    public static function snapshot()
    {
        if (self::$cached !== null && time() - self::$cachedAt < 5) {
            return self::$cached;
        }
        $snap = [
            'cpu' => null,
            'memory_total' => null,
            'memory_used' => null,
            'disk_total' => null,
            'disk_free' => null,
            'time' => time(),
        ];
        /* 磁盘 */
        $diskTotal = @disk_total_space(ROOT_PATH);
        $diskFree = @disk_free_space(ROOT_PATH);
        if ($diskTotal !== false) {
            $snap['disk_total'] = (float)$diskTotal;
            $snap['disk_free'] = (float)$diskFree;
        }
        /* CPU（Windows: wmic 尽力获取） */
        if (PHP_OS_FAMILY === 'Windows') {
            $out = @shell_exec('wmic cpu get loadpercentage /value 2>&1');
            if (is_string($out) && preg_match('/LoadPercentage=(\d+)/', $out, $m)) {
                $snap['cpu'] = (int)$m[1];
            }
        } elseif (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            if ($load !== false) {
                $cores = max(1, (int)@shell_exec('nproc 2>/dev/null'));
                $snap['cpu'] = (int)round($load[0] / $cores * 100);
            }
        }
        /* 内存 */
        if (PHP_OS_FAMILY === 'Linux' && is_file('/proc/meminfo')) {
            $mem = @file_get_contents('/proc/meminfo');
            if ($mem !== false) {
                $total = 0;
                $available = 0;
                if (preg_match('/MemTotal:\s+(\d+)/', $mem, $m)) {
                    $total = (int)$m[1] * 1024;
                }
                if (preg_match('/MemAvailable:\s+(\d+)/', $mem, $m)) {
                    $available = (int)$m[1] * 1024;
                }
                $snap['memory_total'] = $total;
                $snap['memory_used'] = $total - $available;
            }
        }
        self::$cached = $snap;
        self::$cachedAt = time();
        return $snap;
    }
}