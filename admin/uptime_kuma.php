<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = 'Uptime Kuma 状态';

$monitorUrl = setting('uptime_kuma_url', '');
$group = setting('uptime_kuma_group', '');
$status = null;
$error = '';
if ($monitorUrl !== '') {
    $ch = curl_init(rtrim($monitorUrl, '/') . '/api/status-page/heartbeat/' . urlencode($group));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200) {
        $status = json_decode((string)$resp, true);
    } else {
        $error = '无法获取状态（HTTP ' . $code . '）';
    }
}
require dirname(__DIR__) . '/templates/header.php';
?>
<div class="card">
    <div class="card-title"><?php echo svg_icon('server'); ?>Uptime Kuma 状态</div>
    <?php if ($monitorUrl === '') : ?>
        <div class="alert alert-warning">尚未配置 Uptime Kuma 状态页。请在系统设置中填写状态页 URL 与分组 ID。</div>
    <?php elseif ($error !== '') : ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>
    <?php if (is_array($status)) : ?>
        <table class="table">
            <thead><tr><th>监控项</th><th>状态</th><th>Uptime</th><th>延迟</th><th>最后检查</th></tr></thead>
            <tbody>
            <?php foreach ($status as $heartbeat) : ?>
                <?php if (isset($heartbeat['monitor'])) : ?>
                <tr>
                    <td><?php echo e($heartbeat['monitor']['name']); ?></td>
                    <td><span class="badge <?php echo $heartbeat['status']===2?'badge-green':($heartbeat['status']===0?'badge-red':'badge-yellow'); ?>"><?php echo $heartbeat['status']===2?'在线':($heartbeat['status']===0?'离线':'未知'); ?></span></td>
                    <td><?php echo isset($heartbeat['uptime'])?round($heartbeat['uptime'],2).'%':'-'; ?></td>
                    <td><?php echo isset($heartbeat['ping'])?$heartbeat['ping'].'ms':'-'; ?></td>
                    <td><?php echo isset($heartbeat['time'])?date('Y-m-d H:i:s', $heartbeat['time']):'-'; ?></td>
                </tr>
                <?php endif; ?>
            <?php endforeach; if (empty($status)): ?><tr><td colspan="5" class="text-center text-muted">暂无监控数据</td></tr><?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>