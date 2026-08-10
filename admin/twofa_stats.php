<?php
/**
 * 2FA 统计与管理员管理
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '2FA 统计';

if (isset($_POST['reset_2fa']) && csrf_verify()) {
    $uid = (int)$_POST['uid'];
    DB::update('users', ['totp_enabled' => 0, 'totp_secret' => null, 'backup_codes' => null], 'id = ?', [$uid]);
    DB::delete('backup_codes', 'user_id = ?', [$uid]);
    audit_log('admin_reset_2fa', "user#$uid");
    session_flash('flash_success', '已重置用户 2FA');
    redirect(base_url('admin/twofa_stats.php'));
}

$stats = [
    'enabled' => (int)DB::value("SELECT COUNT(*) FROM users WHERE totp_enabled = 1"),
    'total' => (int)DB::value("SELECT COUNT(*) FROM users WHERE role != 'admin'"),
];
$users = DB::fetchAll("SELECT id, username, totp_enabled, email FROM users WHERE totp_enabled = 1 ORDER BY id DESC LIMIT 100");
require __DIR__ . '/templates/header.php';
?>
<div class="card">
    <div class="card-title">2FA 启用统计</div>
    <div class="stat-grid" style="margin-bottom:16px;">
        <div class="stat-card"><div class="label">已启用 2FA</div><div class="value"><?php echo $stats['enabled']; ?></div></div>
        <div class="stat-card"><div class="label">总用户数</div><div class="value"><?php echo $stats['total']; ?></div></div>
        <div class="stat-card"><div class="label">启用率</div><div class="value"><?php echo $stats['total'] > 0 ? round($stats['enabled'] / $stats['total'] * 100, 1) : 0; ?>%</div></div>
    </div>
</div>
<div class="card">
    <div class="card-title">已启用 2FA 的用户</div>
    <table class="table">
        <thead><tr><th>ID</th><th>用户名</th><th>邮箱</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u) : ?>
            <tr><td><?php echo $u['id']; ?></td><td><?php echo e($u['username']); ?></td><td><?php echo e($u['email'] ?: '-'); ?></td>
                <td><form method="post" style="display:inline;" data-confirm-title="重置 2FA" data-confirm-msg="确定重置该用户的 2FA？" data-confirm-ok="重置">
                    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="uid" value="<?php echo $u['id']; ?>">
                    <input type="hidden" name="reset_2fa" value="1">
                    <button type="submit" class="btn btn-sm btn-warning">重置 2FA</button>
                </form></td>
            </tr>
        <?php endforeach; if (empty($users)): ?><tr><td colspan="4" class="text-center text-muted">暂无用户启用 2FA</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>