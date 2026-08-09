<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '令牌管理';

$newKeyFlash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        session_flash('flash_error', '表单已过期，请重试');
        redirect(base_url('admin/tokens/index.php'));
    }
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'create') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '令牌');
        $quota = (float)($_POST['remain_quota'] ?? -1);
        $expired = trim($_POST['expired_at'] ?? '');
        $group = trim($_POST['group'] ?? '');
        $autoGroups = trim($_POST['auto_groups'] ?? '');
        $autoList = $autoGroups !== '' ? array_filter(array_map('trim', explode(',', $autoGroups))) : [];
        if ($userId <= 0 || User::find($userId) === false) {
            session_flash('flash_error', '用户不存在');
        } elseif ($name === '') {
            session_flash('flash_error', '令牌名称不能为空');
        } elseif ($group !== '' && $group !== 'auto' && !Group::isUserSelectableGroup($group)) {
            session_flash('flash_error', '分组「' . $group . '」不存在或不可用');
        } elseif ($group === 'auto' && count($autoList) > Group::maxTokenAutoGroups()) {
            session_flash('flash_error', '自动分组数量超过上限（最多 ' . Group::maxTokenAutoGroups() . ' 个）');
        } else {
            $result = Token::create($userId, $name, $quota, $expired !== '' ? $expired : null, null, $group !== '' ? $group : 'default', null, $autoGroups !== '' ? $autoGroups : null);
            if ($result !== false) {
                session_flash('flash_success', '令牌已创建');
                $_SESSION['flash_token_key'] = $result['key'];
                audit_log('token_create', "#{$result['id']}", "用户={$userId} 名称={$name}");
                redirect(base_url('admin/tokens/index.php'));
            }
            session_flash('flash_error', '创建失败');
        }
    } elseif ($action === 'toggle') {
        $token = Token::getById($id);
        if ($token !== false) {
            Token::update($id, ['status' => $token['status'] ? 0 : 1]);
            session_flash('flash_success', '令牌状态已更新');
            audit_log('token_toggle', "#{$id}", $token['name']);
        }
    } elseif ($action === 'delete') {
        if (Token::delete($id)) {
            session_flash('flash_success', '令牌已删除');
            audit_log('token_delete', "#{$id}");
        } else {
            session_flash('flash_error', '删除失败');
        }
    } elseif ($action === 'set_quota') {
        $quota = (float)($_POST['remain_quota'] ?? -1);
        if (Token::update($id, ['remain_quota' => $quota])) {
            session_flash('flash_success', '令牌额度已更新');
            audit_log('token_quota', "#{$id}", "额度={$quota}");
        }
    } elseif ($action === 'copy_keys') {
        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
        if (!empty($ids)) {
            $in = implode(',', $ids);
            $keys = DB::fetchAll('SELECT `key`, name FROM tokens WHERE id IN (' . $in . ') ORDER BY FIELD(id,' . $in . ')');
            $_SESSION['flash_copy_keys'] = $keys;
            session_flash('flash_success', '已复制 ' . count($keys) . ' 个令牌密钥');
            audit_log('token_copy_keys', null, 'ids=' . $in);
        } else {
            session_flash('flash_error', '请先勾选令牌');
        }
    }
    redirect(base_url('admin/tokens/index.php'));
}

if (isset($_SESSION['flash_copy_keys'])) {
    $copyKeys = $_SESSION['flash_copy_keys'];
    unset($_SESSION['flash_copy_keys']);
}

if (isset($_SESSION['flash_token_key'])) {
    $newKeyFlash = $_SESSION['flash_token_key'];
    unset($_SESSION['flash_token_key']);
}

$keyword = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$where = '';
$params = [];
if ($keyword !== '') {
    $where = ' WHERE t.name LIKE ? OR u.username LIKE ? OR t.`key` LIKE ?';
    $like = '%' . $keyword . '%';
    $params = [$like, $like, $like];
}
$total = (int)DB::value('SELECT COUNT(*) FROM tokens t LEFT JOIN users u ON u.id = t.user_id' . $where, $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$tokens = DB::fetchAll('SELECT t.*, u.username FROM tokens t LEFT JOIN users u ON u.id = t.user_id' . $where . ' ORDER BY t.id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)(($page - 1) * $perPage), $params);
?>
<?php require dirname(__DIR__) . '/templates/header.php'; ?>

<?php if ($newKeyFlash !== '') : ?>
    <div class="alert alert-info">
        新令牌已生成：<code><?php echo e($newKeyFlash); ?></code>（仅显示这一次，请妥善保管）
    </div>
<?php endif; ?>

<?php if (!empty($copyKeys)) : ?>
    <div class="alert alert-info">
        <div style="margin-bottom:8px;">已生成 <?php echo count($copyKeys); ?> 个密钥（仅显示这一次，请妥善保管）：</div>
        <?php foreach ($copyKeys as $ck) : ?>
            <code style="display:block; margin:4px 0;" data-copy-target="copyKey-<?php echo e($ck['name']); ?>"><?php echo e($ck['name']); ?>：<?php echo e($ck['key']); ?></code>
        <?php endforeach; ?>
        <button type="button" class="btn btn-sm btn-secondary" data-copy-all>复制全部</button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-title">新建令牌</div>
    <form method="post" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-group" style="margin:0;">
            <label>所属用户 ID</label>
            <input type="number" name="user_id" class="form-control" style="width:110px;" value="<?php echo e($_POST['user_id'] ?? ''); ?>" required>
        </div>
        <div class="form-group" style="margin:0;">
            <label>令牌名称</label>
            <input type="text" name="name" class="form-control" style="width:160px;" value="<?php echo e($_POST['name'] ?? ''); ?>" placeholder="默认令牌">
        </div>
        <div class="form-group" style="margin:0;">
            <label>令牌额度（$，留空=-1 不限制）</label>
            <input type="number" name="remain_quota" step="0.0001" class="form-control" style="width:160px;" value="<?php echo e($_POST['remain_quota'] ?? ''); ?>">
        </div>
        <div class="form-group" style="margin:0;">
            <label>分组</label>
            <select name="group" class="form-control" style="width:140px;">
                <?php foreach (Group::usableGroups() as $gname2 => $gdesc2) : ?>
                    <option value="<?php echo e($gname2); ?>"><?php echo e($gname2); ?></option>
                <?php endforeach; ?>
                <option value="auto" <?php echo Group::defaultUseAutoGroup() ? 'selected' : ''; ?>>auto（自动分组）</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>自动分组（逗号分隔，auto 时留空=全局）</label>
            <input type="text" name="auto_groups" class="form-control" style="width:170px;" value="">
        </div>
        <div class="form-group" style="margin:0;">
            <label>过期时间（可留空）</label>
            <input type="datetime-local" name="expired_at" class="form-control" style="width:200px;" value="">
        </div>
        <button type="submit" class="btn">创建令牌</button>
    </form>
</div>

<div class="card">
    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <span>令牌列表（共 <?php echo $total; ?> 个）</span>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <form method="post" id="copyKeysForm" style="display:flex; gap:8px; align-items:center; margin:0;">
                <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="copy_keys">
                <button type="button" class="btn btn-sm btn-secondary" onclick="submitCopyKeys()"><?php echo svg_icon('copy'); ?>复制所选密钥</button>
            </form>
            <form method="get" style="display:flex; gap:8px; align-items:center;">
                <input type="text" name="q" class="form-control" style="width:220px;" value="<?php echo e($keyword); ?>" placeholder="名称 / 用户 / 密钥片段">
                <button type="submit" class="btn btn-sm"><?php echo svg_icon('search'); ?>搜索</button>
            </form>
        </div>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th style="width:36px;"><input type="checkbox" id="checkAll" onclick="toggleAll(this)"></th>
                <th>ID</th><th>名称</th><th>用户</th><th>分组</th><th>密钥</th><th>剩余额度</th><th>已用</th>
                <th>次数</th><th>过期时间</th><th>状态</th><th>最后使用</th><th>操作</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($tokens)) : ?>
            <tr><td colspan="13" class="text-center text-muted">暂无令牌</td></tr>
        <?php endif; ?>
        <?php foreach ($tokens as $token) : ?>
            <tr>
                <td><input type="checkbox" class="tk-row" value="<?php echo $token['id']; ?>"></td>
                <td><?php echo $token['id']; ?></td>
                <td><?php echo e($token['name']); ?></td>
                <td><?php echo e($token['username'] ?: ('#' . $token['user_id'])); ?></td>
                <td><span class="badge badge-blue"><?php echo e($token['group'] ?? 'default'); ?></span></td>
                <td><span class="code-text"><?php echo e(Token::maskKey($token['key'])); ?></span></td>
                <td><?php echo (float)$token['remain_quota'] < 0 ? '不限' : '$' . e(number_format((float)$token['remain_quota'], 4)); ?></td>
                <td>$<?php echo e(number_format((float)$token['used_quota'], 4)); ?></td>
                <td><?php echo number_format((int)$token['used_count']); ?></td>
                <td><?php echo $token['expired_at'] ? e($token['expired_at']) : '-'; ?></td>
                <td><?php echo $token['status'] ? '<span class="badge badge-green">启用</span>' : '<span class="badge badge-gray">停用</span>'; ?></td>
                <td><?php echo $token['last_used_at'] ? e($token['last_used_at']) : '-'; ?></td>
                <td style="white-space:nowrap;">
                    <form method="post" style="display:inline-block; margin-right:4px;">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="id" value="<?php echo $token['id']; ?>">
                        <button type="submit" name="action" value="toggle" class="btn btn-sm btn-warning"><?php echo $token['status'] ? '停用' : '启用'; ?></button>
                    </form>
                    <form method="post" style="display:inline-block; margin-right:4px;" data-confirm-title="删除令牌" data-confirm-msg="确定删除该令牌？删除后立即失效。" data-confirm-ok="删除">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="id" value="<?php echo $token['id']; ?>">
                        <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger">删除</button>
                    </form>
                    <a class="btn btn-sm btn-outline" onclick="document.getElementById('quota-<?php echo $token['id']; ?>').style.display='';" href="javascript:void(0)">改额度</a>
                    <form method="post" id="quota-<?php echo $token['id']; ?>" style="display:none; margin-left:6px;">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="id" value="<?php echo $token['id']; ?>">
                        <input type="hidden" name="action" value="set_quota">
                        <input type="number" name="remain_quota" step="0.0001" class="form-control" style="width:120px; display:inline-block;" value="<?php echo e($token['remain_quota']); ?>">
                        <button type="submit" class="btn btn-sm">保存</button>
                    </form>
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
function toggleAll(box) {
    document.querySelectorAll('.tk-row').forEach(function (c) { c.checked = box.checked; });
}
function submitCopyKeys() {
    var ids = [];
    document.querySelectorAll('.tk-row:checked').forEach(function (c) { ids.push(c.value); });
    if (!ids.length) { LcyModal.alert({ title: '复制密钥', message: '请先勾选至少一个令牌' }); return; }
    LcyModal.open({
        title: '复制密钥',
        message: '将显示所选 ' + ids.length + ' 个令牌的完整密钥（仅显示一次），确定继续？',
        confirmText: '复制',
        onConfirm: function () {
            var form = document.getElementById('copyKeysForm');
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