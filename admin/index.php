<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '控制台';
require 'templates/header.php';

$today = date('Y-m-d');
$todayCount = (int)DB::value('SELECT COUNT(*) FROM logs WHERE DATE(created_at) = ?', [$today]);
$todayCost = (float)DB::value('SELECT COALESCE(SUM(cost),0) FROM logs WHERE status = 1 AND DATE(created_at) = ?', [$today]);
$userCount = (int)DB::value('SELECT COUNT(*) FROM users');
$channelCount = (int)DB::value('SELECT COUNT(*) FROM channels');
$recentLogs = DB::fetchAll('SELECT l.*, u.username FROM logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 10');
?>
<div class="stat-grid">
    <div class="stat-card"><div class="label">今日调用</div><div class="value"><?php echo number_format($todayCount); ?></div></div>
    <div class="stat-card"><div class="label">今日消费</div><div class="value">$<?php echo e(number_format($todayCost, 4)); ?></div></div>
    <div class="stat-card"><div class="label">总用户数</div><div class="value"><?php echo number_format($userCount); ?></div></div>
    <div class="stat-card"><div class="label">渠道数</div><div class="value"><?php echo number_format($channelCount); ?></div></div>
</div>
<div class="card">
    <div class="card-title">最近调用记录</div>
    <table class="table">
        <thead><tr><th>ID</th><th>用户</th><th>模型</th><th>Token</th><th>费用</th><th>耗时</th><th>状态</th><th>时间</th></tr></thead>
        <tbody>
        <?php if (empty($recentLogs)) : ?>
            <tr><td colspan="8" class="text-center text-muted">暂无数据</td></tr>
        <?php endif; ?>
        <?php foreach ($recentLogs as $log) : ?>
            <tr>
                <td><?php echo $log['id']; ?></td>
                <td><?php echo e($log['username'] ?: ('#' . $log['user_id'])); ?></td>
                <td><?php echo e($log['model']); ?></td>
                <td><?php echo e(number_format($log['total_tokens'])); ?></td>
                <td>$<?php echo e(number_format((float)$log['cost'], 6)); ?></td>
                <td><?php echo format_elapsed($log['duration']); ?></td>
                <td><?php echo $log['status'] ? '<span class="badge badge-green">成功</span>' : '<span class="badge badge-red">失败</span>'; ?></td>
                <td><?php echo e($log['created_at']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require 'templates/footer.php'; ?>