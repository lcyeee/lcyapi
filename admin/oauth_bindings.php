<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '用户 OAuth 绑定管理';

if (isset($_POST['unbind']) && csrf_verify()) {
    $uid = (int)$_POST['uid'];
    $provider = $_POST['provider'] ?? '';
    DB::delete('oauth_bindings', 'user_id = ? AND provider = ?', [$uid, $provider]);
    audit_log('admin_unbind_oauth', "user#$uid", $provider);
    session_flash('flash_success', '已解除绑定');
    redirect(base_url('admin/oauth_bindings.php'));
}

$q = trim($_GET['q'] ?? '');
$where = '1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (u.username LIKE ? OR u.email LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
$bindings = DB::fetchAll('SELECT ob.*, u.username FROM oauth_bindings ob LEFT JOIN users u ON u.id=ob.user_id WHERE ' . $where . ' ORDER BY ob.id DESC LIMIT 200', $params);
require __DIR__ . '/templates/header.php';
?>
<div class="card">
    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <span><?php echo svg_icon('globe'); ?>OAuth 绑定管理（共 <?php echo count($bindings); ?> 条）</span>
        <form method="get" style="display:flex; gap:8px;">
            <input type="text" name="q" class="form-control" style="width:200px;" value="<?php echo e($q); ?>" placeholder="用户名 / 邮箱">
            <button type="submit" class="btn btn-sm">搜索</button>
        </form>
    </div>
    <table class="table">
        <thead><tr><th>ID</th><th>用户</th><th>提供商</th><th>OpenID</th><th>绑定名</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($bindings as $b) : ?>
            <tr>
                <td><?php echo $b['id']; ?></td>
                <td><?php echo e($b['username'] ?: ('#' . $b['user_id'])); ?></td>
                <td><span class="badge badge-blue"><?php echo e($b['provider']); ?></span></td>
                <td><?php echo e($b['openid']); ?></td>
                <td><?php echo e($b['username'] ?: '-'); ?></td>
                <td>
                    <form method="post" style="display:inline;" data-confirm-title="解除绑定" data-confirm-msg="确定解除该 OAuth 绑定？" data-confirm-ok="解除">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="uid" value="<?php echo $b['user_id']; ?>">
                        <input type="hidden" name="provider" value="<?php echo e($b['provider']); ?>">
                        <input type="hidden" name="unbind" value="1">
                        <button type="submit" class="btn btn-sm btn-danger">解除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; if (empty($bindings)): ?><tr><td colspan="6" class="text-center text-muted">暂无绑定</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>