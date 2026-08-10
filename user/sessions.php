<?php
/**
 * 用户端登录会话管理
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();
$user = Auth::user();
$pageTitle = '登录会话';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'revoke') {
        $sid = (int)$_POST['id'];
        $sess = DB::fetch('SELECT * FROM user_sessions WHERE id = ? AND user_id = ?', [$sid, $user['id']]);
        if ($sess !== false) {
            DB::delete('user_sessions', 'id = ?', [$sid]);
            session_flash('flash_success', '会话已撤销');
        }
    } elseif ($action === 'revoke_others') {
        DB::delete('user_sessions', 'user_id = ? AND id != ?', [$user['id'], (int)$_SESSION['session_id']]);
        session_flash('flash_success', '其他设备已退出');
    }
    redirect(base_url('user/sessions.php'));
}

$sessions = DB::fetchAll('SELECT * FROM user_sessions WHERE user_id = ? ORDER BY last_active_at DESC', [$user['id']]);
require 'templates/header.php';
?>
<div class="card">
    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <span><?php echo svg_icon('clock'); ?>登录会话（共 <?php echo count($sessions); ?> 个活跃会话）</span>
        <?php if (count($sessions) > 1) : ?>
        <form method="post" style="display:inline;" data-confirm-title="退出其他设备" data-confirm-msg="确定退出所有其他设备？" data-confirm-ok="退出">
            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="revoke_others">
            <button type="submit" class="btn btn-sm btn-warning">退出其他设备</button>
        </form>
        <?php endif; ?>
    </div>
    <table class="table">
        <thead><tr><th>设备</th><th>IP</th><th>最后活动</th><th>创建时间</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($sessions as $s) : ?>
            <tr>
                <td><?php echo e($s['device'] ?: '-'); ?><?php echo (int)$s['id'] === (int)($_SESSION['session_id'] ?? 0) ? ' <span class="badge badge-green">当前</span>' : ''; ?></td>
                <td><?php echo e($s['ip'] ?: '-'); ?></td>
                <td><?php echo e($s['last_active_at']); ?></td>
                <td><?php echo e($s['created_at']); ?></td>
                <td>
                    <?php if ((int)$s['id'] !== (int)($_SESSION['session_id'] ?? 0)) : ?>
                    <form method="post" style="display:inline;" data-confirm-title="撤销会话" data-confirm-msg="确定撤销该会话？" data-confirm-ok="撤销">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                        <input type="hidden" name="action" value="revoke">
                        <button type="submit" class="btn btn-sm btn-danger">撤销</button>
                    </form>
                    <?php else : ?>
                    <span class="text-muted">当前设备</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; if (empty($sessions)): ?><tr><td colspan="5" class="text-center text-muted">暂无会话</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php require 'templates/footer.php'; ?>