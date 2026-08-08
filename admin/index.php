<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '控制台';

$today = date('Y-m-d');
$todayCount = (int)DB::value('SELECT COUNT(*) FROM logs WHERE DATE(created_at) = ?', [$today]);
$todayCost = (float)DB::value('SELECT COALESCE(SUM(cost),0) FROM logs WHERE status = 1 AND DATE(created_at) = ?', [$today]);
$todayFail = (int)DB::value('SELECT COUNT(*) FROM logs WHERE status = 0 AND DATE(created_at) = ?', [$today]);
$userCount = (int)DB::value('SELECT COUNT(*) FROM users');
$channelCount = (int)DB::value('SELECT COUNT(*) FROM channels');
$activeChannelCount = (int)DB::value('SELECT COUNT(*) FROM channels WHERE status = 1');
$totalQuota = (float)DB::value('SELECT COALESCE(SUM(quota),0) FROM users');

$weekStart = date('Y-m-d', strtotime('-6 days'));
$weekRows = DB::fetchAll('SELECT DATE(created_at) AS d, COALESCE(SUM(cost),0) AS c, COUNT(*) AS n FROM logs WHERE created_at >= ? GROUP BY DATE(created_at)', [$weekStart . ' 00:00:00']);
$dayMap = [];
foreach ($weekRows as $row) {
    $dayMap[$row['d']] = ['c' => (float)$row['c'], 'n' => (int)$row['n']];
}
$labels = [];
$costs = [];
$counts = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $labels[] = date('m-d', strtotime($d));
    $costs[] = isset($dayMap[$d]) ? $dayMap[$d]['c'] : 0;
    $counts[] = isset($dayMap[$d]) ? $dayMap[$d]['n'] : 0;
}

$recentLogs = DB::fetchAll('SELECT l.*, u.username FROM logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 10');
$badChannels = DB::fetchAll('SELECT id, name, success_count, fail_count FROM channels WHERE fail_count > success_count AND fail_count > 10 ORDER BY fail_count DESC LIMIT 5');
?>
<div class="stat-grid">
    <div class="stat-card">
        <div class="label">今日调用</div>
        <div class="value"><?php echo number_format($todayCount); ?></div>
        <div class="sub">今日失败 <?php echo number_format($todayFail); ?> 次</div>
    </div>
    <div class="stat-card">
        <div class="label">今日消费</div>
        <div class="value">$<?php echo e(number_format($todayCost, 4)); ?></div>
    </div>
    <div class="stat-card">
        <div class="label">总用户数</div>
        <div class="value"><?php echo number_format($userCount); ?></div>
        <div class="sub">账户总余额 $<?php echo e(number_format($totalQuota, 2)); ?></div>
    </div>
    <div class="stat-card">
        <div class="label">渠道数</div>
        <div class="value"><?php echo number_format($channelCount); ?></div>
        <div class="sub">启用 <?php echo number_format($activeChannelCount); ?> 个</div>
    </div>
</div>

<div class="card">
    <div class="card-title">近 7 天调用趋势</div>
    <div class="chart-box" style="height:280px;">
        <canvas id="trendChart"></canvas>
    </div>
</div>

<?php if (!empty($badChannels)) : ?>
    <div class="alert alert-danger">
        <strong>渠道健康预警：</strong>
        <?php foreach ($badChannels as $bc) : ?>
            渠道 #<?php echo $bc['id']; ?>（<?php echo e($bc['name']); ?>）成功 <?php echo (int)$bc['success_count']; ?> / 失败 <?php echo (int)$bc['fail_count']; ?>；
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-title">最近调用记录</div>
    <table class="table">
        <thead><tr><th>ID</th><th>用户</th><th>模型</th><th>Tokens</th><th>费用</th><th>耗时</th><th>状态</th><th>时间</th></tr></thead>
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

<script src="<?php echo base_url('assets/js/chart.umd.min.js'); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [
                { label: '消费 ($)', data: <?php echo json_encode($costs); ?>, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,.1)', fill: true, tension: .35, yAxisID: 'y' },
                { label: '调用次数', data: <?php echo json_encode($counts); ?>, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.08)', fill: true, tension: .35, yAxisID: 'y1' }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, position: 'left', title: { display: true, text: '消费 ($)' } },
                y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: '次数' } }
            }
        }
    });
});
</script>
<?php require 'templates/footer.php'; ?>