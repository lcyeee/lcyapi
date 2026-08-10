<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '排行榜';
$period = isset($_GET['period']) && in_array($_GET['period'], ['today','week','month','year']) ? $_GET['period'] : 'week';
$data = Rankings::get($period);
require dirname(__DIR__) . '/templates/header.php';
?>
<div class="card">
    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <span><?php echo svg_icon('chart'); ?>模型/厂商排行榜</span>
        <div style="display:flex; gap:6px;">
            <?php foreach (['today'=>'今天','week'=>'本周','month'=>'本月','year'=>'全年'] as $k=>$v) : ?>
                <a class="btn btn-sm <?php echo $period===$k?'':'btn-secondary'; ?>" href="?period=<?php echo $k; ?>"><?php echo $v; ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="stat-grid" style="margin-bottom:16px;">
        <div class="stat-card"><div class="label">总调用</div><div class="value"><?php echo number_format($data['total_calls']); ?></div></div>
        <div class="stat-card"><div class="label">总消耗</div><div class="value">$<?php echo e(number_format($data['total_cost'],4)); ?></div></div>
        <div class="stat-card"><div class="label">总 Tokens</div><div class="value"><?php echo number_format($data['total_tokens']); ?></div></div>
    </div>
</div>
<div class="card">
    <div class="card-title">模型排行（按费用）</div>
    <table class="table">
        <thead><tr><th>#</th><th>模型</th><th>厂商</th><th>费用</th><th>调用次数</th><th>Tokens</th><th>占比</th><th>渠道数</th></tr></thead>
        <tbody>
        <?php $i=0; foreach ($data['models'] as $m) : $i++; ?>
            <tr>
                <td><?php echo $i; ?></td>
                <td><?php echo e($m['name']); ?></td>
                <td><span class="badge badge-blue"><?php echo e($m['vendor']); ?></span></td>
                <td>$<?php echo e(number_format($m['cost'],6)); ?></td>
                <td><?php echo number_format($m['calls']); ?></td>
                <td><?php echo number_format($m['tokens']); ?></td>
                <td><?php echo $m['share']; ?>%</td>
                <td><?php echo $m['channels']; ?></td>
            </tr>
        <?php endforeach; if (empty($data['models'])): ?><tr><td colspan="8" class="text-center text-muted">暂无数据</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<div class="card">
    <div class="card-title">厂商排行</div>
    <table class="table">
        <thead><tr><th>#</th><th>厂商</th><th>费用</th><th>调用次数</th><th>Tokens</th><th>占比</th><th>模型数</th></tr></thead>
        <tbody>
        <?php $i=0; foreach ($data['vendors'] as $v) : $i++; ?>
            <tr>
                <td><?php echo $i; ?></td>
                <td><?php echo e($v['name']); ?></td>
                <td>$<?php echo e(number_format($v['cost'],6)); ?></td>
                <td><?php echo number_format($v['calls']); ?></td>
                <td><?php echo number_format($v['tokens']); ?></td>
                <td><?php echo $v['share']; ?>%</td>
                <td><?php echo $v['model_count']; ?></td>
            </tr>
        <?php endforeach; if (empty($data['vendors'])): ?><tr><td colspan="7" class="text-center text-muted">暂无数据</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<div class="card">
    <div class="card-title">涨跌榜（排名对比上期）</div>
    <div style="display:flex; gap:24px; flex-wrap:wrap;">
        <div style="flex:1; min-width:280px;">
            <div class="card-title" style="color:var(--success);">上升最快</div>
            <?php if (empty($data['top_movers'])): ?><p class="text-muted">暂无数据</p><?php endif; ?>
            <?php foreach ($data['top_movers'] as $m) : ?>
                <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid var(--border);">
                    <span><?php echo e($m['name']); ?></span>
                    <span style="color:var(--success);">▲ <?php echo (int)$m['delta']; ?> 名</span>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="flex:1; min-width:280px;">
            <div class="card-title" style="color:var(--danger);">下降最快</div>
            <?php if (empty($data['top_droppers'])): ?><p class="text-muted">暂无数据</p><?php endif; ?>
            <?php foreach ($data['top_droppers'] as $m) : ?>
                <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid var(--border);">
                    <span><?php echo e($m['name']); ?></span>
                    <span style="color:var(--danger);">▼ <?php echo (int)$m['delta']; ?> 名</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-title">历史趋势（Top 10 模型按时间桶费用，$）</div>
    <table class="table">
        <thead><tr><th>时间</th><?php foreach (array_keys($data['history'][0]['values'] ?? []) as $h) : ?><th><?php echo e($h); ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($data['history'] as $bucket) : ?>
            <tr>
                <td><?php echo e($bucket['label']); ?></td>
                <?php if (empty($bucket['values'])): ?><td colspan="<?php echo max(1, count($data['history'][0]['values'] ?? [])); ?>" class="text-muted">-</td><?php endif; ?>
                <?php foreach ($bucket['values'] as $h => $c) : ?>
                    <td><?php echo e(number_format($c, 4)); ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>