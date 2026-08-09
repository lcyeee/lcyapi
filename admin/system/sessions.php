<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '登录会话管理';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        session_flash('flash_error', '表单已过期');
        redirect(base_url('admin/system/sessions.php'));
    }
    $action = $_POST['action'] ?? '';
    $sid = (int)$_POST['id'] ?? 0;
    if ($action === 'revoke') {
        DB::delete('user_sessions', 'id = ?', [$sid]);
        audit_log('session_revoke', "#$sid");
        session_flash('flash_success', '会话已撤销');
    } elseif ($action === 'revoke_all') {
        DB::query('DELETE FROM user_sessions');
        audit_log('session_revoke_all', null, '全部会话已撤销');
        session_flash('flash_success', '全部会话已撤销');
    }
    redirect(base_url('admin/system/sessions.php'));
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$total = (int)DB::value('SELECT COUNT(*) FROM user_sessions');
$sessions = DB::fetchAll('SELECT s.*, u.username FROM user_sessions s LEFT JOIN users u ON u.id=s.user_id ORDER BY s.last_active_at DESC LIMIT ? OFFSET ?', [$perPage, ($page-1)*$perPage]);
require dirname(__DIR__) . '/templates/header.php';
?>
<div class="card">
    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <span><?php echo svg_icon('clock'); ?>登录会话（共 <?php echo $total; ?> 条）</span>
        <form method="post" style="display:inline;" data-confirm-title="撤销全部会话" data-confirm-msg="确定撤销所有会话？" data-confirm-ok="确定">
            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="revoke_all">
            <button type="submit" class="btn btn-sm btn-danger">撤销全部</button>
        </form>
    </div>
    <table class="table">
        <thead><tr><th>ID</th><th>用户</th><th>设备</th><th>IP</th><th>最后活动</th><th>创建时间</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($sessions as $s) : ?>
            <tr>
                <td><?php echo $s['id']; ?></td>
                <td><?php echo e($s['username'] ?: ('#' . $s['user_id'])); ?></td>
                <td><?php echo e($s['device'] ?: '-'); ?></td>
                <td><?php echo e($s['ip'] ?: '-'); ?></td>
                <td><?php echo e($s['last_active_at']); ?></td>
                <td><?php echo e($s['created_at']); ?></td>
                <td>
                    <form method="post" style="display:inline;" data-confirm-title="撤销会话" data-confirm-msg="确定撤销该会话？" data-confirm-ok="撤销">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                        <input type="hidden" name="action" value="revoke">
                        <button type="submit" class="btn btn-sm btn-danger">撤销</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; if (empty($sessions)): ?><tr><td colspan="7" class="text-center text-muted">暂无会话</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php if ($total > $perPage) : ?>
        <div class="form-actions" style="justify-content:center;">
            <?php for ($i=1; $i<=ceil($total/$perPage); $i++) : ?>
                <a href="?page=<?php echo $i; ?>" class="btn btn-sm <?php echo $i===$page?'':'btn-secondary'; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>