<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '用户管理';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        session_flash('flash_error', '表单已过期，请重试');
        redirect(base_url('admin/users/index.php'));
    }
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $id = (int)($_POST['id'] ?? 0);
    $user = User::find($id);
    if ($user === false) {
        session_flash('flash_error', '用户不存在');
        redirect(base_url('admin/users/index.php'));
    }
    if ($action === 'toggle') {
        if ((int)$id === Auth::id() && $user['role'] === 'admin') {
            session_flash('flash_error', '不能停用自己的账号');
        } else {
            User::update($id, ['status' => $user['status'] ? 0 : 1]);
            session_flash('flash_success', '用户状态已更新');
        }
    } elseif ($action === 'promote') {
        if ((int)$id === Auth::id()) {
            session_flash('flash_error', '不能修改自己的角色');
        } else {
            User::update($id, ['role' => $user['role'] === 'admin' ? 'user' : 'admin']);
            session_flash('flash_success', '用户角色已更新');
        }
    } elseif ($action === 'quota') {
        $delta = (float)($_POST['delta'] ?? 0);
        if ($delta == 0) {
            session_flash('flash_error', '额度变更数量不能为 0');
        } else {
            if ($delta > 0) {
                User::addQuota($id, $delta, 'admin', '后台调整', Auth::id());
            } else {
                User::deductQuota($id, abs($delta));
            }
            session_flash('flash_success', '额度已调整：' . ($delta > 0 ? '+' : '') . number_format($delta, 4));
        }
    } elseif ($action === 'reset_pass') {
        $newPass = trim($_POST['new_pass'] ?? '');
        if (strlen($newPass) < 6 || strlen($newPass) > 64) {
            session_flash('flash_error', '密码长度需在 6-64 位之间');
        } else {
            User::update($id, ['password' => Auth::hashPassword($newPass)]);
            session_flash('flash_success', '密码已重置');
        }
    }
    redirect(base_url('admin/users/index.php'));
}

$keyword = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$where = '';
$params = [];
if ($keyword !== '') {
    $where = ' WHERE username LIKE ? OR email LIKE ? OR nickname LIKE ?';
    $like = '%' . $keyword . '%';
    $params = [$like, $like, $like];
}
$total = (int)DB::value('SELECT COUNT(*) FROM users' . $where, $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$users = DB::fetchAll('SELECT * FROM users' . $where . ' ORDER BY id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)(($page - 1) * $perPage), $params);
?>
<?php require dirname(__DIR__) . '/templates/header.php'; ?>

<div class="card">
    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center;">
        <span>用户列表（共 <?php echo $total; ?> 人）</span>
        <form method="get" style="display:flex; gap:8px; align-items:center;">
            <input type="text" name="q" class="form-control" style="width:220px;" value="<?php echo e($keyword); ?>" placeholder="用户名 / 邮箱 / 昵称">
            <button type="submit" class="btn btn-sm"><?php echo svg_icon('search'); ?>搜索</button>
        </form>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th><th>用户名</th><th>角色</th><th>余额</th><th>已用</th>
                <th>累计充值</th><th>调用次数</th><th>状态</th><th>注册时间</th><th>操作</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($users)) : ?>
            <tr><td colspan="10" class="text-center text-muted">暂无用户</td></tr>
        <?php endif; ?>
        <?php foreach ($users as $u) : ?>
            <tr>
                <td><?php echo $u['id']; ?></td>
                <td><?php echo e($u['username']); ?>
                    <?php if ($u['email']) : ?><div class="form-hint"><?php echo e($u['email']); ?></div><?php endif; ?>
                </td>
                <td><?php echo $u['role'] === 'admin' ? '<span class="badge badge-yellow">管理员</span>' : '<span class="badge badge-gray">用户</span>'; ?></td>
                <td>$<?php echo e(number_format((float)$u['quota'], 4)); ?></td>
                <td>$<?php echo e(number_format((float)$u['used_quota'], 4)); ?></td>
                <td>$<?php echo e(number_format((float)$u['total_quota'], 4)); ?></td>
                <td><?php echo number_format((int)$u['api_count']); ?></td>
                <td><?php echo $u['status'] ? '<span class="badge badge-green">正常</span>' : '<span class="badge badge-red">禁用</span>'; ?></td>
                <td><?php echo e($u['created_at']); ?></td>
                <td style="white-space:nowrap;">
                    <form method="post" style="display:inline-block; margin-right:4px;">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                        <button type="submit" name="action" value="toggle" class="btn btn-sm <?php echo $u['status'] ? 'btn-warning' : 'btn-success'; ?>"><?php echo $u['status'] ? '禁用' : '启用'; ?></button>
                    </form>
                    <?php if ((int)$u['id'] !== Auth::id()) : ?>
                        <form method="post" style="display:inline-block; margin-right:4px;">
                            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                            <button type="submit" name="action" value="promote" class="btn btn-sm btn-secondary"><?php echo $u['role'] === 'admin' ? '降为用户' : '设为管理员'; ?></button>
                        </form>
                    <?php endif; ?>
                    <a class="btn btn-sm btn-outline" onclick="toggleRow(<?php echo $u['id']; ?>)" href="javascript:void(0)">调整额度</a>
                </td>
            </tr>
            <tr id="ops-<?php echo $u['id']; ?>" style="display:none;">
                <td colspan="10">
                    <div style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap;">
                        <form method="post" style="display:flex; gap:8px; align-items:flex-end;">
                            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                            <input type="hidden" name="action" value="quota">
                            <div>
                                <label>额度调整（可正可负）</label>
                                <input type="number" name="delta" step="0.0001" class="form-control" style="width:140px;" placeholder="如 5 或 -5">
                            </div>
                            <button type="submit" class="btn btn-sm">确认</button>
                        </form>
                        <form method="post" style="display:flex; gap:8px; align-items:flex-end;">
                            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                            <input type="hidden" name="action" value="reset_pass">
                            <div>
                                <label>重置密码</label>
                                <input type="text" name="new_pass" class="form-control" style="width:140px;" placeholder="新密码">
                            </div>
                            <button type="submit" class="btn btn-sm">确认</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($pages > 1) : ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $pages; $i++) : ?>
                <a class="<?php echo $i === $page ? 'current' : ''; ?>" href="?page=<?php echo $i; ?>&q=<?php echo e($keyword); ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleRow(id) {
    const el = document.getElementById('ops-' + id);
    el.style.display = el.style.display === 'none' ? '' : 'none';
}
</script>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>