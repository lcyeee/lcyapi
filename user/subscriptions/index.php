<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/templates/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        session_flash('flash_error', '页面已过期，请重试');
        redirect(base_url('user/subscriptions/index.php'));
    }
    $planId = (int)($_POST['plan_id'] ?? 0);
    $plan = DB::fetch('SELECT * FROM subscription_plans WHERE id = ? AND status = 1', [$planId]);
    if ($plan === false) {
        session_flash('flash_error', '套餐不存在或已下架');
        redirect(base_url('user/subscriptions/index.php'));
    }
    $userId = Auth::id();
    $result = Subscription::activate($userId, $planId);
    if ($result['ok']) {
        session_flash('flash_success', '开通成功：套餐「' . $plan['name'] . '」，订阅额度 ' . quota_display((float)$plan['quota']) . ' 已到账');
    } else {
        session_flash('flash_error', '开通失败：' . $result['msg']);
    }
    redirect(base_url('user/subscriptions/index.php'));
}

$activeSub = DB::fetch('SELECT us.*, p.name AS plan_name FROM user_subscriptions us LEFT JOIN subscription_plans p ON p.id = us.plan_id WHERE us.user_id = ? AND us.status = 1 ORDER BY us.id DESC LIMIT 1', [Auth::id()]);
$history = DB::fetchAll('SELECT us.*, p.name AS plan_name FROM user_subscriptions us LEFT JOIN subscription_plans p ON p.id = us.plan_id WHERE us.user_id = ? ORDER BY us.id DESC LIMIT 10', [Auth::id()]);
$plans = DB::fetchAll('SELECT * FROM subscription_plans WHERE status = 1 ORDER BY sort ASC, id ASC');
?>
<div class="card" style="max-width:640px;">
    <div class="card-title">我的订阅</div>
    <?php if ($activeSub !== false) : ?>
        <div class="alert alert-success">
            <?php echo svg_icon('check'); ?>
            当前订阅：<strong><?php echo e($activeSub['plan_name']); ?></strong>，有效期至 <strong><?php echo e($activeSub['end_at']); ?></strong>，可用订阅额度 <strong><?php echo e(quota_display((float)$activeSub['quota_left'])); ?></strong>
        </div>
        <?php if ($activeSub['upgrade_group'] !== null && $activeSub['upgrade_group'] !== '') : ?>
            <div class="alert alert-info">订阅期间已升级到分组「<?php echo e($activeSub['upgrade_group']); ?>」<?php echo $activeSub['downgrade_group'] !== null && $activeSub['downgrade_group'] !== '' ? '，到期后降级为「' . e($activeSub['downgrade_group']) . '」' : ''; ?></div>
        <?php endif; ?>
    <?php else : ?>
        <div class="alert alert-info">暂无有效订阅。订阅后的额度将进入「订阅额度池」，API 调用优先从订阅池扣费。</div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-title">选购套餐</div>
    <?php if (empty($plans)) : ?>
        <p class="text-muted">暂无上架套餐，请稍后再来。</p>
    <?php endif; ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:14px;">
        <?php foreach ($plans as $plan) : ?>
            <div class="plan-card" style="background:var(--card-2); border:1px solid var(--border); border-radius:14px; padding:18px; display:flex; flex-direction:column; gap:10px;">
                <div style="font-size:17px; font-weight:600;"><?php echo e($plan['name']); ?></div>
                <?php if ($plan['subtitle']) : ?><div style="color:var(--text-2); font-size:13px;"><?php echo e($plan['subtitle']); ?></div><?php elseif ($plan['description']) : ?><div style="color:var(--text-2); font-size:13px;"><?php echo e($plan['description']); ?></div><?php endif; ?>
                <div style="font-size:24px; font-weight:700;"><?php echo e(quota_display((float)$plan['price'])); ?><span style="font-size:13px; color:var(--text-2); font-weight:400;"> / <?php echo (int)$plan['days']; ?>天</span></div>
                <div style="color:var(--text-2); font-size:13px;">订阅额度 <?php echo e(quota_display((float)$plan['quota'])); ?>，API 调用优先扣订阅池</div>
                <?php if ($plan['upgrade_group'] !== '') : ?>
                    <div style="color:var(--text-2); font-size:13px;">购买后升级分组：<span class="badge"><?php echo e($plan['upgrade_group']); ?></span></div>
                <?php endif; ?>
                <?php if ((int)$plan['max_purchase_per_user'] > 0) : ?>
                    <div style="color:var(--text-2); font-size:12px;">该套餐限购 <?php echo (int)$plan['max_purchase_per_user']; ?> 次</div>
                <?php endif; ?>
                <form method="post" action="<?php echo base_url('user/subscriptions/index.php'); ?>" style="margin-top:auto;"
                      data-confirm-title="开通套餐" data-confirm-msg="确认开通「<?php echo e($plan['name']); ?>」？额度将立即到账。" data-confirm-ok="立即开通">
                    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="plan_id" value="<?php echo (int)$plan['id']; ?>">
                    <button type="submit" class="btn" style="width:100%;">立即开通</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <div class="card-title">开通记录</div>
    <?php if (empty($history)) : ?>
        <p class="text-muted">暂无开通记录。</p>
    <?php else : ?>
        <table class="table table-collapsible">
            <thead><tr><th>套餐</th><th>开始</th><th>到期</th><th>状态</th></tr></thead>
            <tbody>
            <?php foreach ($history as $h) : ?>
                <tr>
                    <td data-label="套餐"><?php echo e($h['plan_name'] ?: '#' . $h['plan_id']); ?></td>
                    <td data-label="开始"><?php echo e($h['start_at']); ?></td>
                    <td data-label="到期"><?php echo e($h['end_at']); ?></td>
                    <td data-label="状态"><?php echo $h['status'] ? '<span class="badge badge-green">有效</span>' : '<span class="badge badge-gray">已过期</span>'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
