<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '性能指标';

$hours = max(1, min(168, (int)($_GET['hours'] ?? 24)));
$metrics = PerfMetrics::query('', '', $hours);
$summary = PerfMetrics::summary($hours);
require dirname(__DIR__) . '/templates/header.php';
?>
<div class="card">
    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <span><?php echo svg_icon('chart'); ?>请求性能指标（近 <?php echo $hours; ?> 小时）</span>
        <div style="display:flex; gap:6px;">
            <?php foreach ([1=>'1h',6=>'6h',24=>'24h',72=>'3d',168=>'7d'] as $k=>$v) : ?>
                <a href="?hours=<?php echo $k; ?>" class="btn btn-sm <?php echo $hours===$k?'':'btn-secondary'; ?>"><?php echo $v; ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="stat-grid" style="margin-bottom:16px;">
        <div class="stat-card"><div class="label">总请求</div><div class="value"><?php echo number_format($summary['calls']); ?></div></div>
        <div class="stat-card"><div class="label">成功率</div><div class="value"><?php echo $summary['success_rate']; ?>%</div></div>
        <div class="stat-card"><div class="label">平均延迟</div><div class="value"><?php echo $summary['avg_latency_ms']; ?>ms</div></div>
    </div>
    <table class="table">
        <thead><tr><th>模型</th><th>调用次数</th><th>成功率</th><th>平均延迟</th><th>吞吐量(Tokens)</th></tr></thead>
        <tbody>
        <?php foreach ($metrics as $m) : ?>
            <tr>
                <td><?php echo e($m['model']); ?></td>
                <td><?php echo number_format($m['calls']); ?></td>
                <td><span class="badge <?php echo $m['success_rate']>=90?'badge-green':($m['success_rate']>=50?'badge-yellow':'badge-red'); ?>"><?php echo $m['success_rate']; ?>%</span></td>
                <td><?php echo $m['avg_latency_ms']; ?>ms</td>
                <td><?php echo number_format($m['throughput_tokens']); ?></td>
            </tr>
        <?php endforeach; if (empty($metrics)): ?><tr><td colspan="5" class="text-center text-muted">暂无数据</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>