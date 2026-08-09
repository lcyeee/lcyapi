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
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>