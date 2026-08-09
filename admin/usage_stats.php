<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '用量统计';

$days = max(1, min(365, (int)($_GET['days'] ?? 30)));
$data = UsageData::byDate($days);
$users = UsageData::byUser(20, $days);
require dirname(__DIR__) . '/templates/header.php';
?>
<div class="card">
    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <span><?php echo svg_icon('chart'); ?>每日用量趋势（近 <?php echo $days; ?> 天）</span>
        <div style="display:flex; gap:6px;">
            <?php foreach ([7=>'7天',30=>'30天',90=>'90天'] as $k=>$v) : ?>
                <a href="?days=<?php echo $k; ?>" class="btn btn-sm <?php echo $days===$k?'':'btn-secondary'; ?>"><?php echo $v; ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="chart-box" style="height:220px;">
        <canvas id="usageChart"></canvas>
    </div>
</div>
<div class="card">
    <div class="card-title">用户消费排行（近 30 天）</div>
    <table class="table">
        <thead><tr><th>#</th><th>用户</th><th>费用</th><th>调用次数</th></tr></thead>
        <tbody>
        <?php $i=0; foreach ($users as $u) : $i++; ?>
            <tr><td><?php echo $i; ?></td><td><?php echo e($u['username'] ?: ('#' . $u['user_id'])); ?></td><td>$<?php echo e(number_format((float)$u['cost'],6)); ?></td><td><?php echo number_format((int)$u['calls']); ?></td></tr>
        <?php endforeach; if (empty($users)): ?><tr><td colspan="4" class="text-center text-muted">暂无数据</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<script src="<?php echo base_url('assets/js/chart.umd.min.js'); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const accent = window.LcyTheme ? LcyTheme.accent() : '#409EFF';
    const accentRgb = window.LcyTheme ? LcyTheme.accentRgb() : '64,158,255';
    new Chart(document.getElementById('usageChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($data['labels']); ?>,
            datasets: [
                { label: '费用 ($)', data: <?php echo json_encode($data['costs']); ?>, backgroundColor: 'rgba('+accentRgb+',.6)', borderRadius: 4, yAxisID: 'y' },
                { label: '调用次数', data: <?php echo json_encode($data['calls']); ?>, backgroundColor: 'rgba(48,178,108,.5)', borderRadius: 4, yAxisID: 'y1' }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, position: 'left' }, y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } } }
        }
    });
});
</script>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>