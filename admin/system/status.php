<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '系统占用';

$phpVersion = PHP_VERSION;
$phpSapi = php_sapi_name();
$phpOs = PHP_OS . ' / ' . PHP_OS_FAMILY;
$memoryLimit = ini_get('memory_limit');
$memoryUsage = function_exists('memory_get_usage') ? memory_get_usage(true) : 0;
$memoryPeak = function_exists('memory_get_peak_usage') ? memory_get_peak_usage(true) : 0;

/* 磁盘占用 */
$diskTotal = @disk_total_space(ROOT_PATH);
$diskFree = @disk_free_space(ROOT_PATH);

/* 系统负载 / CPU（尽力而为，失败显示不可用） */
$load = null;
if (function_exists('sys_getloadavg')) {
    $load = sys_getloadavg();
} elseif (PHP_OS_FAMILY === 'Windows') {
    $out = @shell_exec('wmic cpu get loadpercentage /value 2>&1');
    if (is_string($out) && preg_match('/LoadPercentage=(\d+)/', $out, $m)) {
        $load = [(int)$m[1], null, null];
    }
}

/* 目录大小（data/logs、cache、uploads）与文件缓存 */
function dir_size($path) {
    $size = 0;
    if (!is_dir($path)) {
        return 0;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile()) {
            $size += $f->getSize();
        }
    }
    return $size;
}
$sizeLogs = dir_size(LOG_PATH);
$sizeCache = dir_size(CACHE_PATH);
$sizeUploads = dir_size(UPLOAD_PATH);

/* MySQL 版本与连接状态 */
$mysqlVersion = '';
$mysqlPing = false;
$dbSize = 0;
$dbTables = 0;
try {
    $pdo = DB::getInstance();
    $mysqlPing = $pdo->getAttribute(PDO::ATTR_SERVER_INFO) !== false || $pdo->query('SELECT 1')->fetch() !== false;
    $mysqlVersion = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
    $dbTables = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
    $dbSize = (float)$pdo->query("SELECT COALESCE(SUM(data_length + index_length),0) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
} catch (Throwable $e) {
    $mysqlPing = false;
}

/* 今日 API 调用统计 */
$today = date('Y-m-d');
$todayCount = (int)DB::value('SELECT COUNT(*) FROM logs WHERE DATE(created_at) = ?', [$today]);
$todayCost = (float)DB::value('SELECT COALESCE(SUM(cost),0) FROM logs WHERE status = 1 AND DATE(created_at) = ?', [$today]);

/* 运行时长：取最早的日志/安装时间 */
$uptime = '';
$firstLog = DB::value('SELECT MIN(created_at) FROM logs');
$installedAt = DB::value("SELECT updated_at FROM settings WHERE `key` = 'installed'");
$since = $firstLog ?: ($installedAt ?: null);
if ($since) {
    $seconds = max(0, time() - strtotime($since));
    $uptime = intdiv($seconds, 86400) . ' 天 ' . intdiv($seconds % 86400, 3600) . ' 小时 ' . intdiv($seconds % 3600, 60) . ' 分';
}

function fmt_bytes($b) {
    if ($b === null) {
        return '-';
    }
    $b = (float)$b;
    if ($b >= 1073741824) {
        return round($b / 1073741824, 2) . ' GB';
    }
    if ($b >= 1048576) {
        return round($b / 1048576, 2) . ' MB';
    }
    if ($b >= 1024) {
        return round($b / 1024, 1) . ' KB';
    }
    return round($b, 1) . ' B';
}
function fmt_percent($used, $total) {
    if ($total <= 0) {
        return 0;
    }
    return min(100, round($used / $total * 100, 1));
}
?>
<?php require dirname(__DIR__) . '/templates/header.php'; ?>

<div class="card">
    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <span><?php echo svg_icon('chart'); ?>服务器资源占用</span>
        <a class="btn btn-sm" href="<?php echo base_url('admin/system/status.php'); ?>"><?php echo svg_icon('refresh'); ?>刷新</a>
    </div>

    <div class="stat-grid" style="margin-bottom:16px;">
        <div class="stat-card">
            <div class="label">PHP 内存使用</div>
            <div class="value"><?php echo e(fmt_bytes($memoryUsage)); ?></div>
            <div class="sub">峰值 <?php echo e(fmt_bytes($memoryPeak)); ?> / 上限 <?php echo e($memoryLimit); ?></div>
        </div>
        <div class="stat-card">
            <div class="label">磁盘占用</div>
            <div class="value"><?php echo e(fmt_bytes($diskTotal !== false ? $diskTotal - $diskFree : null)); ?></div>
            <div class="sub">总计 <?php echo e(fmt_bytes($diskTotal)); ?>（<?php echo is_array($load) ? '' : ''; ?>剩余 <?php echo e(fmt_bytes($diskFree)); ?>）</div>
        </div>
        <div class="stat-card">
            <div class="label">数据库大小</div>
            <div class="value"><?php echo e(fmt_bytes($dbSize)); ?></div>
            <div class="sub"><?php echo (int)$dbTables; ?> 张表</div>
        </div>
        <div class="stat-card">
            <div class="label">今日调用</div>
            <div class="value"><?php echo number_format($todayCount); ?></div>
            <div class="sub">今日消费 $<?php echo e(number_format($todayCost, 4)); ?></div>
        </div>
    </div>

    <table class="table">
        <tbody>
            <tr><th style="width:220px;">PHP 版本</th><td><?php echo e($phpVersion); ?> <span class="badge badge-blue"><?php echo e($phpSapi); ?></span></td></tr>
            <tr><th>操作系统</th><td><?php echo e($phpOs); ?></td></tr>
            <tr><th>PHP 内存上限</th><td><?php echo e($memoryLimit); ?></td></tr>
            <tr><th>系统负载 / CPU</th><td>
                <?php if (is_array($load) && $load[0] !== null) : ?>
                    <?php echo e($load[0]); ?><?php echo $load[1] !== null ? ' / ' . e($load[1]) . ' / ' . e($load[2]) : ''; ?>
                <?php else : ?>
                    <span class="text-muted">不可用（当前环境不支持读取）</span>
                <?php endif; ?>
            </td></tr>
            <tr><th>磁盘使用率</th><td>
                <?php if ($diskTotal !== false && $diskTotal > 0) : ?>
                    <?php $dp = fmt_percent($diskTotal - $diskFree, $diskTotal); ?>
                    <div class="progress"><div style="width:<?php echo $dp; ?>%;"></div></div>
                    <span class="form-hint"><?php echo $dp; ?>%（已用 <?php echo e(fmt_bytes($diskTotal - $diskFree)); ?> / <?php echo e(fmt_bytes($diskTotal)); ?>）</span>
                <?php else : ?>
                    <span class="text-muted">不可用</span>
                <?php endif; ?>
            </td></tr>
            <tr><th>data/logs 目录</th><td><?php echo e(fmt_bytes($sizeLogs)); ?></td></tr>
            <tr><th>data/cache 目录</th><td><?php echo e(fmt_bytes($sizeCache)); ?></td></tr>
            <tr><th>data/uploads 目录</th><td><?php echo e(fmt_bytes($sizeUploads)); ?></td></tr>
            <tr><th>MySQL 连接</th><td>
                <?php if ($mysqlPing) : ?><span class="badge badge-green">正常</span> <?php echo e($mysqlVersion); ?><?php else : ?><span class="badge badge-red">断开</span><?php endif; ?>
            </td></tr>
            <tr><th>运行时长（估算）</th><td><?php echo $uptime !== '' ? e($uptime) : '-'; ?></td></tr>
        </tbody>
    </table>
</div>

<style>
.progress { height: 8px; background: var(--card-2); border:1px solid var(--border); border-radius:999px; overflow:hidden; max-width:320px; margin-bottom:6px; }
.progress > div { height:100%; background: linear-gradient(90deg, var(--accent), var(--accent-2)); border-radius:999px; }
</style>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>