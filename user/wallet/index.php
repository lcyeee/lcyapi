<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireLogin();

/* POST 处理必须在输出 HTML 之前，否则 redirect 的 Location 头无法生效 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        session_flash('flash_error', '页面已过期，请重试');
        redirect(base_url('user/wallet/index.php'));
    }
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'aff_transfer') {
        $result = Auth::transferAffQuota(Auth::id());
    } elseif ($action === 'checkin') {
        $result = Auth::checkin(Auth::id());
    } else {
        $result = ['ok' => false, 'msg' => '未知操作'];
    }
    session_flash($result['ok'] ? 'flash_success' : 'flash_error', $result['msg']);
    redirect(base_url('user/wallet/index.php'));
}

require dirname(__DIR__) . '/templates/header.php';

$user = Auth::user();
$recharges = DB::fetchAll('SELECT * FROM recharge_logs WHERE user_id = ? ORDER BY id DESC LIMIT 20', [Auth::id()]);
$affEnabled = setting('aff_enabled', '0') === '1';
$checkinEnabled = setting('checkin_enabled', '0') === '1';
$todayCheckin = DB::value('SELECT id FROM checkins WHERE user_id = ? AND checkin_date = ?', [Auth::id(), date('Y-m-d')]);
$checkinCount = (int)DB::value('SELECT COUNT(*) FROM checkins WHERE user_id = ?', [Auth::id()]);
$checkinStreak = (int)DB::value('SELECT checkin_streak FROM users WHERE id = ?', [Auth::id()]);
$invitedCount = (int)DB::value('SELECT COUNT(*) FROM users WHERE aff_by = ?', [Auth::id()]);
$invitedUsers = $invitedCount > 0 ? DB::fetchAll('SELECT username, nickname, created_at FROM users WHERE aff_by = ? ORDER BY id DESC LIMIT 10', [Auth::id()]) : [];
$affLink = base_url('user/register.php?aff=' . urlencode($user['aff_code'] ?: ''));
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

<?php if ($checkinEnabled || $affEnabled) : ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:var(--gap);">
    <?php if ($checkinEnabled) : ?>
        <div class="card">
            <div class="card-title"><?php echo svg_icon('check'); ?>每日签到</div>
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <div>
                    <div class="form-hint">累计签到 <?php echo $checkinCount; ?> 天<?php echo $checkinStreak > 0 ? '，当前连续 ' . $checkinStreak . ' 天' : ''; ?>，每次奖励 $<?php echo e(number_format((float)setting('checkin_reward', '0'), 4)); ?><?php $bonusStep = (float)setting('checkin_bonus_step', '0'); echo $bonusStep > 0 ? '，连续签到每日 +$' . number_format($bonusStep, 4) . '（封顶 7 天）' : ''; ?></div>
                </div>
                <?php if ($todayCheckin !== null) : ?>
                    <span class="badge badge-green">今日已签到</span>
                <?php else : ?>
                    <form method="post">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="action" value="checkin">
                        <button type="submit" class="btn"><?php echo svg_icon('zap'); ?>立即签到</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($affEnabled && setting('self_use_mode', '0') !== '1') : ?>
        <div class="card">
            <div class="card-title"><?php echo svg_icon('gift'); ?>邀请奖励</div>
            <div class="detail-list" style="margin-bottom:12px;">
                <div class="item"><div class="k">已邀请</div><div class="v"><?php echo $invitedCount; ?> 人</div></div>
                <div class="item"><div class="k">待转入收益</div><div class="v"><?php echo e(quota_display($user['aff_quota'])); ?></div></div>
                <div class="item"><div class="k">累计收益</div><div class="v"><?php echo e(quota_display($user['aff_history_quota'])); ?></div></div>
            </div>
            <div class="form-group">
                <label>我的邀请链接</label>
                <div class="with-prefix">
                    <span class="prefix">邀请码 <?php echo e($user['aff_code'] ?: '-'); ?></span>
                    <input type="text" class="form-control" id="affLink" value="<?php echo e($affLink); ?>" readonly>
                </div>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="button" class="btn btn-sm btn-secondary" data-copy-target="#affLink"><?php echo svg_icon('copy'); ?>复制链接</button>
                <?php if ((float)$user['aff_quota'] > 0) : ?>
                    <form method="post" style="display:inline-block;">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="action" value="aff_transfer">
                        <button type="submit" class="btn btn-sm">转入余额</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php if (count($invitedUsers) > 0) : ?>
                <div class="detail-list" style="margin-top:12px;">
                    <div class="item"><div class="k" style="font-weight:600;">最近被邀请</div><div class="v"></div></div>
                    <?php foreach ($invitedUsers as $iu) : ?>
                        <div class="item" style="padding:6px 0;">
                            <div class="k"><?php echo e($iu['nickname'] ?: $iu['username']); ?></div>
                            <div class="v"><?php echo e(date('Y-m-d', strtotime($iu['created_at']))); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    </div>
<?php endif; ?>

<?php
$epayEnabled = setting('epay_enabled', '0') === '1';
$stripeEnabled = setting('stripe_enabled', '0') === '1';
$payRatio = (float)setting('pay_ratio', '1');
$topupAmounts = setting('topup_amounts', '5,10,20,50,100');
$topupDiscount = (float)setting('topup_discount', '1');
$payOrders = DB::fetchAll('SELECT * FROM pay_orders WHERE user_id = ? ORDER BY id DESC LIMIT 10', [Auth::id()]);
?>

<?php if ($epayEnabled || $stripeEnabled) : ?>
<div class="card">
    <div class="card-title">在线充值</div>
    <div class="form-group">
        <label>充值金额（$）</label>
        <div class="amount-quick" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px;">
            <?php foreach (array_filter(array_map('trim', explode(',', $topupAmounts))) as $preset) : if ((float)$preset <= 0) continue; ?>
                <button type="button" class="btn btn-sm btn-secondary" data-amount="<?php echo (float)$preset; ?>">$<?php echo (float)$preset; ?></button>
            <?php endforeach; ?>
            <?php if (trim($topupAmounts) === '') : ?>
                <?php foreach ([5, 10, 20, 50, 100] as $preset) : ?>
                    <button type="button" class="btn btn-sm btn-secondary" data-amount="<?php echo $preset; ?>">$<?php echo $preset; ?></button>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <input type="number" name="pay_amount" id="payAmount" step="0.01" min="0.01" class="form-control" placeholder="输入金额（美元）">
        <div class="form-hint">到账额度 = 金额 × 充值倍率（<?php echo $payRatio; ?>）<?php if ($topupDiscount < 1) : ?> × 折扣率（<?php echo $topupDiscount; ?>，当前活动价）<?php endif; ?></div>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <?php if ($epayEnabled) : ?>
            <button type="button" class="btn" data-pay-submit="epay"><?php echo svg_icon('wallet'); ?>易支付充值</button>
        <?php endif; ?>
        <?php if ($stripeEnabled) : ?>
            <button type="button" class="btn" data-pay-submit="stripe"><?php echo svg_icon('wallet'); ?>Stripe 充值</button>
        <?php endif; ?>
    </div>
    <form method="post" action="<?php echo base_url('user/wallet/pay.php'); ?>" id="payForm" style="display:none;">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="amount" id="payFormAmount">
        <input type="hidden" name="provider" id="payFormProvider">
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var amountInput = document.getElementById('payAmount');
    var amountForm = document.getElementById('payFormAmount');
    var providerForm = document.getElementById('payFormProvider');
    var payForm = document.getElementById('payForm');
    document.querySelectorAll('[data-amount]').forEach(function (btn) {
        btn.addEventListener('click', function () { amountInput.value = btn.getAttribute('data-amount'); });
    });
    document.querySelectorAll('[data-pay-submit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var v = parseFloat(amountInput.value);
            if (!v || v <= 0) { alert('请输入充值金额'); return; }
            amountForm.value = v;
            providerForm.value = btn.getAttribute('data-pay-submit');
            payForm.submit();
        });
    });
});
</script>
<?php endif; ?>

<div class="card">
    <div class="card-title">充值记录</div>
    <?php if (!empty($payOrders)) : ?>
        <table class="table table-collapsible">
            <thead><tr><th>订单号</th><th>方式</th><th>金额</th><th>入账额度</th><th>状态</th><th>时间</th></tr></thead>
            <tbody>
            <?php foreach ($payOrders as $po) : ?>
                <tr>
                    <td data-label="订单号" style="font-family:monospace; font-size:12px;"><?php echo e($po['order_no']); ?></td>
                    <td data-label="方式"><span class="badge badge-blue"><?php echo e($po['provider']); ?></span></td>
                    <td data-label="金额">$<?php echo e(number_format((float)$po['amount'], 2)); ?></td>
                    <td data-label="入账额度">$<?php echo e(number_format((float)$po['quota'], 4)); ?></td>
                    <td data-label="状态">
                        <?php
                        $statusLabel = ['pending' => '待支付', 'paid' => '已到账', 'failed' => '失败', 'closed' => '已关闭'];
                        $statusClass = ['pending' => 'badge-orange', 'paid' => 'badge-green', 'failed' => 'badge-red', 'closed' => ''];
                        ?>
                        <span class="badge <?php echo isset($statusClass[$po['status']]) ? $statusClass[$po['status']] : ''; ?>"><?php echo isset($statusLabel[$po['status']]) ? $statusLabel[$po['status']] : e($po['status']); ?></span>
                    </td>
                    <td data-label="时间"><?php echo e($po['created_at']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <table class="table table-collapsible" <?php echo !empty($payOrders) ? 'style="display:none;"' : ''; ?>>
        <thead>
            <tr><th>ID</th><th>金额</th><th>方式</th><th>备注</th><th>时间</th></tr>
        </thead>
        <tbody>
        <?php if (empty($recharges)) : ?>
            <tr class="row-empty"><td colspan="5" class="text-center text-muted">暂无充值记录，可购买兑换码后 <a href="<?php echo base_url('user/redeem/index.php'); ?>">前往兑换</a></td></tr>
        <?php endif; ?>
        <?php foreach ($recharges as $recharge) : ?>
            <tr>
                <td data-label="ID"><?php echo $recharge['id']; ?></td>
                <td data-label="金额" class="<?php echo (float)$recharge['amount'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                    $<?php echo e(number_format((float)$recharge['amount'], 4)); ?>
                </td>
                <td data-label="方式"><span class="badge badge-blue"><?php echo e($recharge['type']); ?></span></td>
                <td data-label="备注"><?php echo e($recharge['remark'] ?: '-'); ?></td>
                <td data-label="时间"><?php echo e($recharge['created_at']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>