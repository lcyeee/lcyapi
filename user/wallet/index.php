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
$invitedCount = (int)DB::value('SELECT COUNT(*) FROM users WHERE aff_by = ?', [Auth::id()]);
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
                    <div class="form-hint">累计签到 <?php echo $checkinCount; ?> 天，每次奖励 $<?php echo e(number_format((float)setting('checkin_reward', '0'), 4)); ?></div>
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
    <?php if ($affEnabled) : ?>
        <div class="card">
            <div class="card-title"><?php echo svg_icon('gift'); ?>邀请奖励</div>
            <div class="detail-list" style="margin-bottom:12px;">
                <div class="item"><div class="k">已邀请</div><div class="v"><?php echo $invitedCount; ?> 人</div></div>
                <div class="item"><div class="k">待转入收益</div><div class="v">$<?php echo e(number_format((float)$user['aff_quota'], 4)); ?></div></div>
                <div class="item"><div class="k">累计收益</div><div class="v">$<?php echo e(number_format((float)$user['aff_history_quota'], 4)); ?></div></div>
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
        </div>
    <?php endif; ?>
    </div>
<?php endif; ?>

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