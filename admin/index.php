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
$channelTop = DB::fetchAll('SELECT l.channel_id, c.name AS channel_name, COUNT(*) AS n, COALESCE(SUM(l.cost),0) AS c FROM logs l LEFT JOIN channels c ON c.id = l.channel_id WHERE l.status = 1 AND l.created_at >= ? AND l.channel_id > 0 GROUP BY l.channel_id ORDER BY c DESC LIMIT 8', [$weekStart . ' 00:00:00']);

/* 性能健康：24h 成功率/延迟/吞吐量 */
$health = PerfMetrics::summary(24);
$healthTop = PerfMetrics::query('', '', 24);

/* 用户消费分析（Top 用户） */
$userTop = UsageData::byUser(10, 7);

/* 余额健康度：可用天数估算 */
$avgDailyCost = $todayCost > 0 ? $todayCost : (float)DB::value('SELECT COALESCE(AVG(daily),0) FROM (SELECT DATE(created_at) AS d, SUM(cost) AS daily FROM logs WHERE status=1 GROUP BY DATE(created_at)) t');
$runwayDays = $avgDailyCost > 0 ? round($totalQuota / $avgDailyCost, 1) : 999;
$runwayStatus = $runwayDays >= 30 ? 'good' : ($runwayDays >= 7 ? 'warn' : 'danger');

/* 设置引导数据：是否已创建Key/充值/有调用 */
$hasToken = (int)DB::value('SELECT COUNT(*) FROM tokens') > 0;
$hasRecharge = (int)DB::value('SELECT COUNT(*) FROM recharge_logs') > 0;
$hasCall = $todayCount > 0 || (int)DB::value('SELECT COUNT(*) FROM logs') > 0;
$setupSteps = [
    ['创建 API Key', $hasToken, 'user/tokens/index.php'],
    ['充值额度', $hasRecharge, 'user/wallet/index.php'],
    ['发送首个请求', $hasCall, 'user/playground/index.php'],
];
?> 
<?php require __DIR__ . '/templates/header.php'; ?>
<?php require dirname(__DIR__) . '/includes/dashboard_panels.php'; ?>
<?php dashboard_panels(); ?>

<?php if (!$hasToken || !$hasRecharge || !$hasCall) : ?>
<div class="card" style="margin-bottom:16px;">
    <div class="card-title"><?php echo svg_icon('send'); ?>快速开始（设置引导）</div>
    <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
        <?php foreach ($setupSteps as $i => $step) : ?>
        <a href="<?php echo base_url($step[2]); ?>" class="btn <?php echo $step[1] ? 'btn-secondary' : ''; ?>" style="flex:1; min-width:180px; text-align:left;">
            <span class="badge <?php echo $step[1] ? 'badge-green' : 'badge-blue'; ?>" style="margin-right:8px;"><?php echo $step[1] ? '✓' : ($i + 1); ?></span>
            <?php echo e($step[0]); ?>
        </a>
        <?php endforeach; ?>
        <div class="form-hint" style="margin:0;">完成全部步骤即可正常使用 API</div>
    </div>
</div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat-card">
        <div class="label">余额健康度</div>
        <div class="value" style="color:<?php echo $runwayStatus === 'good' ? 'var(--green)' : ($runwayStatus === 'warn' ? 'var(--yellow)' : 'var(--red)'); ?>;">
            <?php echo $runwayDays >= 999 ? '∞' : $runwayDays . ' 天'; ?>
        </div>
        <div class="sub">按近 <?php echo $avgDailyCost > 0 ? '7' : '1'; ?> 天日均消耗估算</div>
    </div>
    <div class="stat-card">
        <div class="label">24h 成功率</div>
        <div class="value"><?php echo $health['success_rate']; ?>%</div>
        <div class="sub">平均延迟 <?php echo $health['avg_latency_ms']; ?>ms</div>
    </div>
    <div class="stat-card">
        <div class="label">24h 调用量</div>
        <div class="value"><?php echo number_format($health['calls']); ?></div>
        <div class="sub">总请求数</div>
    </div>
</div>

<div class="card">
    <div class="card-title">近 7 天调用趋势</div>
    <div class="chart-box" style="height:280px;">
        <canvas id="trendChart"></canvas>
    </div>
</div>

<?php if (!empty($channelTop)) : ?>
<div class="card">
    <div class="card-title">渠道费用排行（近 7 天，按成功请求计费）</div>
    <div class="chart-box" style="height:<?php echo max(180, count($channelTop) * 34); ?>px;">
        <canvas id="channelTopChart"></canvas>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($badChannels)) : ?>
    <div class="alert alert-danger">
        <strong>渠道健康预警：</strong>
        <?php foreach ($badChannels as $bc) : ?>
            渠道 #<?php echo $bc['id']; ?>（<?php echo e($bc['name']); ?>）成功 <?php echo (int)$bc['success_count']; ?> / 失败 <?php echo (int)$bc['fail_count']; ?>；
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-title">用户消费排行（近 7 天）</div>
    <div class="chart-box" style="height:220px;">
        <canvas id="userTopChart"></canvas>
    </div>
</div>

<div class="card">
    <div class="card-title">curl 调用示例</div>
    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
        <code style="flex:1; padding:10px 14px; background:var(--card-2); border:1px solid var(--border); border-radius:8px; font-size:12px; word-break:break-all; user-select:all;">curl http://<?php echo e($_SERVER['HTTP_HOST']); ?>/v1/chat/completions -H "Authorization: Bearer sk-你的密钥" -H 'Content-Type: application/json' -d '{"model":"<?php echo e(DB::value('SELECT name FROM models WHERE enabled=1 LIMIT 1') ?: 'gpt-4o-mini'); ?>","messages":[{"role":"user","content":"你好"}]}'</code>
        <button type="button" class="btn btn-sm" onclick="copyCurl(this)">复制</button>
    </div>
</div>

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
    const accent = window.LcyTheme ? LcyTheme.accent() : '#409EFF';
    const accentRgb = window.LcyTheme ? LcyTheme.accentRgb() : '64,158,255';
    Chart.defaults.color = (window.LcyTheme && LcyTheme.isDark()) ? '#9CA3AF' : '#6B7280';
    Chart.defaults.borderColor = 'rgba(148,163,184,.16)';
    const green = getComputedStyle(document.documentElement).getPropertyValue('--green').trim() || '#30B26C';
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [
                { label: '消费 ($)', data: <?php echo json_encode($costs); ?>, borderColor: accent, backgroundColor: 'rgba(' + accentRgb + ',.1)', fill: true, tension: .35, yAxisID: 'y' },
                { label: '调用次数', data: <?php echo json_encode($counts); ?>, borderColor: green, backgroundColor: 'rgba(48,178,108,.08)', fill: true, tension: .35, yAxisID: 'y1' }
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
    <?php if (!empty($channelTop)) : ?>
    const cNames = <?php echo json_encode(array_map(function ($r2) {
        return ($r2['channel_name'] !== null ? $r2['channel_name'] : '#' . $r2['channel_id']);
    }, $channelTop)); ?>;
    const cCosts = <?php echo json_encode(array_map(function ($r2) {
        return round((float)$r2['c'], 6);
    }, $channelTop)); ?>;
    const cCounts = <?php echo json_encode(array_map(function ($r2) {
        return (int)$r2['n'];
    }, $channelTop)); ?>;
    new Chart(document.getElementById('channelTopChart'), {
        type: 'bar',
        data: {
            labels: cNames,
            datasets: [
                { label: '费用 ($)', data: cCosts, backgroundColor: 'rgba(' + accentRgb + ',.75)', borderRadius: 6 }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { afterLabel: function (item) { return '调用 ' + cCounts[item.dataIndex] + ' 次'; } } } },
            scales: { x: { beginAtZero: true, title: { display: true, text: '费用 ($)' } } }
        }
    });
    <?php endif; ?>
    <?php if (!empty($userTop)) : ?>
    const uNames = <?php echo json_encode(array_map(function ($u) { return $u['username'] ?: ('#' . $u['user_id']); }, $userTop)); ?>;
    const uCosts = <?php echo json_encode(array_map(function ($u) { return round((float)$u['cost'], 6); }, $userTop)); ?>;
    new Chart(document.getElementById('userTopChart'), {
        type: 'bar',
        data: { labels: uNames, datasets: [{ label: '费用 ($)', data: uCosts, backgroundColor: 'rgba(48,178,108,.6)', borderRadius: 4 }] },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, title: { display: true, text: '费用 ($)' } } } }
    });
    <?php endif; ?>
});
function copyCurl(btn) {
    var code = btn.parentNode.querySelector('code');
    if (navigator.clipboard) { navigator.clipboard.writeText(code.textContent); }
    btn.textContent = '已复制';
    setTimeout(function () { btn.textContent = '复制'; }, 2000);
}
</script>
<?php require __DIR__ . '/templates/footer.php'; ?>