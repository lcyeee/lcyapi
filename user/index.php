<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require 'templates/header.php';

$today = date('Y-m-d');
$todayCost = (float)DB::value('SELECT COALESCE(SUM(cost),0) FROM logs WHERE user_id = ? AND status = 1 AND DATE(created_at) = ?', [Auth::id(), $today]);
$todayCount = (int)DB::value('SELECT COUNT(*) FROM logs WHERE user_id = ? AND DATE(created_at) = ?', [Auth::id(), $today]);
$tokenCount = (int)DB::value('SELECT COUNT(*) FROM tokens WHERE user_id = ? AND status = 1', [Auth::id()]);

$weekStart = date('Y-m-d', strtotime('-6 days'));
$weekRows = DB::fetchAll('SELECT DATE(created_at) AS d, COALESCE(SUM(cost),0) AS c FROM logs WHERE user_id = ? AND created_at >= ? GROUP BY DATE(created_at)', [Auth::id(), $weekStart . ' 00:00:00']);
$dayMap = [];
foreach ($weekRows as $row) {
    $dayMap[$row['d']] = (float)$row['c'];
}
$labels = [];
$costs = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $labels[] = date('m-d', strtotime($d));
    $costs[] = isset($dayMap[$d]) ? $dayMap[$d] : 0;
}
?>
<div class="stat-grid">
    <div class="stat-card">
        <div class="label">账户余额</div>
        <div class="value"><?php echo e(quota_display($user['quota'])); ?></div>
        <div class="sub">累计充值 <?php echo e(quota_display($user['total_quota'])); ?></div>
    </div>
    <div class="stat-card">
        <div class="label">今日消费</div>
        <div class="value"><?php echo e(quota_display($todayCost)); ?></div>
        <div class="sub">今日调用 <?php echo $todayCount; ?> 次</div>
    </div>
    <div class="stat-card">
        <div class="label">总调用次数</div>
        <div class="value"><?php echo number_format((int)$user['api_count']); ?></div>
        <div class="sub">已用额度 <?php echo e(quota_display($user['used_quota'])); ?></div>
    </div>
    <div class="stat-card">
        <div class="label">有效令牌</div>
        <div class="value"><?php echo $tokenCount; ?></div>
        <div class="sub"><a href="<?php echo base_url('user/tokens/index.php'); ?>">管理令牌</a></div>
    </div>
</div>

<div class="card">
    <div class="card-title">近 7 天消费趋势</div>
    <div class="chart-box">
        <canvas id="costChart"></canvas>
    </div>
</div>

<div class="card">
    <div class="card-title">快捷入口</div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="btn" href="<?php echo base_url('user/tokens/create.php'); ?>">创建令牌</a>
        <?php if (setting('self_use_mode', '0') !== '1') : ?>
            <a class="btn btn-success" href="<?php echo base_url('user/redeem/index.php'); ?>">兑换码充值</a>
        <?php endif; ?>
        <a class="btn btn-secondary" href="<?php echo base_url('user/logs/index.php'); ?>">查看使用记录</a>
        <a class="btn btn-warning" href="<?php echo base_url('user/profile/password.php'); ?>">修改密码</a>
    </div>
</div>

<?php if (setting('faq_enabled', '0') === '1') : ?>
    <?php
    $faqLines = array_filter(array_map('trim', explode("\n", str_replace("\r", '', setting('faq_content', '')))));
    if (count($faqLines) > 0) : ?>
        <div class="card">
            <div class="card-title">常见问题</div>
            <div class="detail-list">
                <?php foreach ($faqLines as $line) : ?>
                    <?php $parts = explode('|', $line, 2); ?>
                    <?php if (count($parts) === 2 && trim($parts[0]) !== '') : ?>
                        <div class="item">
                            <div class="k"><?php echo e(trim($parts[0])); ?></div>
                            <div class="v"><?php echo e(trim($parts[1])); ?></div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script src="<?php echo base_url('assets/js/chart.umd.min.js'); ?>"></script>
<script>
const ctx = document.getElementById('costChart');
const labels = <?php echo json_encode($labels); ?>;
const values = <?php echo json_encode($costs); ?>;
document.addEventListener('DOMContentLoaded', () => {
    const accent = window.LcyTheme ? LcyTheme.accent() : '#409EFF';
    const accentRgb = window.LcyTheme ? LcyTheme.accentRgb() : '64,158,255';
    Chart.defaults.color = (window.LcyTheme && LcyTheme.isDark()) ? '#9CA3AF' : '#6B7280';
    Chart.defaults.borderColor = 'rgba(148,163,184,.16)';
    new Chart(ctx, {
        type: 'line',
        data: { labels: labels, datasets: [{ label: '消费 ($)', data: values, borderColor: accent, backgroundColor: 'rgba(' + accentRgb + ',.1)', fill: true, tension: .35 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
});
</script>
<?php require 'templates/footer.php'; ?>