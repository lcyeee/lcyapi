<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/templates/header.php';

$user = Auth::user();
$recharges = DB::fetchAll('SELECT * FROM recharge_logs WHERE user_id = ? ORDER BY id DESC LIMIT 20', [Auth::id()]);
?>
<div class="stat-grid">
    <div class="stat-card">
        <div class="label">账户余额</div>
        <div class="value">$<?php echo e(number_format((float)$user['quota'], 4)); ?></div>
    </div>
    <div class="stat-card">
        <div class="label">累计充值</div>
        <div class="value">$<?php echo e(number_format((float)$user['total_quota'], 4)); ?></div>
    </div>
    <div class="stat-card">
        <div class="label">累计消耗</div>
        <div class="value">$<?php echo e(number_format((float)$user['used_quota'], 4)); ?></div>
    </div>
    <div class="stat-card">
        <div class="label">账户状态</div>
        <div class="value" style="font-size:18px;"><?php echo $user['status'] ? '<span class="badge badge-green">正常</span>' : '<span class="badge badge-red">已禁用</span>'; ?></div>
    </div>
</div>

<div class="card">
    <div class="card-title">充值记录</div>
    <table class="table">
        <thead>
            <tr><th>ID</th><th>金额</th><th>方式</th><th>备注</th><th>时间</th></tr>
        </thead>
        <tbody>
        <?php if (empty($recharges)) : ?>
            <tr><td colspan="5" class="text-center text-muted">暂无充值记录，可购买兑换码后 <a href="<?php echo base_url('user/redeem/index.php'); ?>">前往兑换</a></td></tr>
        <?php endif; ?>
        <?php foreach ($recharges as $recharge) : ?>
            <tr>
                <td><?php echo $recharge['id']; ?></td>
                <td class="<?php echo (float)$recharge['amount'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                    $<?php echo e(number_format((float)$recharge['amount'], 4)); ?>
                </td>
                <td><span class="badge badge-blue"><?php echo e($recharge['type']); ?></span></td>
                <td><?php echo e($recharge['remark'] ?: '-'); ?></td>
                <td><?php echo e($recharge['created_at']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>