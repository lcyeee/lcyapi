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
    $user = $id > 0 ? User::find($id) : false;
    if ($id > 0 && $user === false) {
        session_flash('flash_error', '用户不存在');
        redirect(base_url('admin/users/index.php'));
    }
    if ($action === 'toggle') {
        $toDisable = (int)$user['status'] === 1;
        if ((int)$id === Auth::id() && $user['role'] === 'admin') {
            session_flash('flash_error', '不能停用自己的账号');
        } else {
            if ($toDisable) {
                DB::delete('user_sessions', 'user_id = ?', [$id]);
            }
            User::update($id, ['status' => $user['status'] ? 0 : 1]);
            session_flash('flash_success', '用户状态已更新' . ($toDisable ? '，已强制下线' : ''));
            audit_log('user_toggle', "#{$id}", $user['username'] . ($toDisable ? '（封禁，清退会话）' : ''));
        }
    } elseif ($action === 'promote') {
        if ((int)$id === Auth::id()) {
            session_flash('flash_error', '不能修改自己的角色');
        } else {
            User::update($id, ['role' => $user['role'] === 'admin' ? 'user' : 'admin']);
            session_flash('flash_success', '用户角色已更新');
            audit_log('user_promote', "#{$id}", $user['username'] . ' -> ' . ($user['role'] === 'admin' ? 'user' : 'admin'));
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
            audit_log('user_quota', "#{$id}", $user['username'] . ' 调整=' . $delta);
        }
    } elseif ($action === 'group') {
        $newGroup = mb_substr(trim($_POST['group'] ?? ''), 0, 32);
        if ($newGroup === '') {
            session_flash('flash_error', '分组不能为空');
        } else {
            User::update($id, ['group' => $newGroup]);
            session_flash('flash_success', '用户分组已更新：' . $newGroup);
            audit_log('user_group', "#{$id}", $user['username'] . ' -> ' . $newGroup);
        }
    } elseif ($action === 'reset_pass') {
        $newPass = trim($_POST['new_pass'] ?? '');
        if (strlen($newPass) < 6 || strlen($newPass) > 64) {
            session_flash('flash_error', '密码长度需在 6-64 位之间');
        } else {
            User::update($id, ['password' => Auth::hashPassword($newPass)]);
            session_flash('flash_success', '密码已重置');
            audit_log('user_reset_pass', "#{$id}", $user['username']);
        }
    } elseif ($action === 'delete') {
        if ((int)$id === Auth::id()) {
            session_flash('flash_error', '不能删除自己的账号');
        } elseif (User::delete($id)) {
            session_flash('flash_success', '用户已删除');
            audit_log('user_delete', "#{$id}", $user['username']);
        } else {
            session_flash('flash_error', '删除失败');
        }
    } elseif ($action === 'batch_delete') {
        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
        $ids = array_filter($ids, function ($v) { return $v !== Auth::id(); });
        if (empty($ids)) {
            session_flash('flash_error', '请先勾选要删除的用户（不能删除自己）');
        } else {
            $deleted = 0;
            foreach ($ids as $uid) {
                if (User::delete((int)$uid)) {
                    $deleted++;
                }
            }
            session_flash('flash_success', '已删除 ' . $deleted . ' 个用户');
            audit_log('user_batch_delete', null, 'ids=' . implode(',', $ids));
        }
    } elseif ($action === 'batch_quota') {
        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
        $delta = (float)($_POST['delta'] ?? 0);
        if (empty($ids)) {
            session_flash('flash_error', '请先勾选用户');
        } elseif ($delta == 0) {
            session_flash('flash_error', '额度变更数量不能为 0');
        } else {
            $okCount = 0;
            foreach ($ids as $uid) {
                if ($delta > 0) {
                    User::addQuota((int)$uid, $delta, 'admin', '批量调整', Auth::id()) ? $okCount++ : 0;
                } else {
                    User::deductQuota((int)$uid, abs($delta)) ? $okCount++ : 0;
                }
            }
            session_flash('flash_success', '已为 ' . $okCount . ' 个用户调整额度 ' . ($delta > 0 ? '+' : '') . number_format($delta, 4));
            audit_log('user_batch_quota', null, 'ids=' . implode(',', $ids) . ' delta=' . $delta);
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
    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <span>用户列表（共 <?php echo $total; ?> 人）</span>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <form method="post" id="batchForm" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" id="batchAction" value="">
                <div style="display:flex; gap:4px; align-items:center;">
                    <label class="form-hint" style="margin:0;">批量调整额度：</label>
                    <input type="number" name="delta" step="0.0001" class="form-control" style="width:110px; height:32px;" value="0">
                </div>
                <button type="button" class="btn btn-sm btn-secondary" onclick="submitBatch('batch_quota')">批量加/减</button>
                <button type="button" class="btn btn-sm btn-danger" onclick="submitBatch('batch_delete', true)">批量删除</button>
                <span class="form-hint" style="margin:0;">勾选用户后操作</span>
            </form>
            <form method="get" style="display:flex; gap:8px; align-items:center;">
                <input type="text" name="q" class="form-control" style="width:220px;" value="<?php echo e($keyword); ?>" placeholder="用户名 / 邮箱 / 昵称">
                <button type="submit" class="btn btn-sm"><?php echo svg_icon('search'); ?>搜索</button>
            </form>
        </div>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th style="width:36px;"><input type="checkbox" id="checkAll" onclick="toggleAll(this)"></th>
                <th>ID</th><th>用户名</th><th>角色</th><th>分组</th><th>余额</th><th>已用</th>
                <th>累计充值</th><th>调用次数</th><th>状态</th><th>注册时间</th><th>操作</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($users)) : ?>
            <tr><td colspan="12" class="text-center text-muted">暂无用户</td></tr>
        <?php endif; ?>
        <?php foreach ($users as $u) : ?>
            <tr>
                <td><input type="checkbox" class="us-row" value="<?php echo $u['id']; ?>"></td>
                <td><?php echo $u['id']; ?></td>
                <td><?php echo e($u['username']); ?>
                    <?php if ($u['email']) : ?><div class="form-hint"><?php echo e($u['email']); ?></div><?php endif; ?>
                </td>
                <td><?php echo $u['role'] === 'admin' ? '<span class="badge badge-yellow">管理员</span>' : '<span class="badge badge-gray">用户</span>'; ?></td>
                <td><span class="badge badge-blue"><?php echo e($u['group'] ?? 'default'); ?></span></td>
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
                    <?php if ((int)$u['id'] !== Auth::id()) : ?>
                        <form method="post" style="display:inline-block; margin-left:4px;" data-confirm-title="删除用户" data-confirm-msg="确定删除用户「<?php echo e($u['username']); ?>」？其令牌与数据将被清除。" data-confirm-ok="删除">
                            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                            <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger">删除</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <tr id="ops-<?php echo $u['id']; ?>" style="display:none;">
                <td colspan="12">
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
                            <input type="hidden" name="action" value="group">
                            <div>
                                <label>修改分组</label>
                                <input type="text" name="group" class="form-control" style="width:120px;" value="<?php echo e($u['group'] ?? 'default'); ?>">
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
function toggleAll(box) {
    document.querySelectorAll('.us-row').forEach(function (c) { c.checked = box.checked; });
}
function submitBatch(action, danger) {
    var ids = [];
    document.querySelectorAll('.us-row:checked').forEach(function (c) { ids.push(c.value); });
    if (!ids.length) { LcyModal.alert({ title: '批量操作', message: '请先勾选至少一个用户' }); return; }
    var msg = action === 'batch_quota'
        ? '确定为所选 ' + ids.length + ' 个用户调整额度？'
        : '确定删除所选 ' + ids.length + ' 个用户？其令牌与数据将被清除，该操作不可恢复。';
    LcyModal.open({
        title: action === 'batch_quota' ? '批量调整额度' : '批量删除用户',
        message: msg,
        confirmText: action === 'batch_quota' ? '调整' : '删除',
        danger: !!danger,
        onConfirm: function () {
            var form = document.getElementById('batchForm');
            document.getElementById('batchAction').value = action;
            form.querySelectorAll('input[name="ids[]"]').forEach(function (h) { h.remove(); });
            ids.forEach(function (v) {
                var h = document.createElement('input');
                h.type = 'hidden'; h.name = 'ids[]'; h.value = v;
                form.appendChild(h);
            });
            form.submit();
        }
    });
}
</script>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>