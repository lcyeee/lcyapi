<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '渠道管理';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        session_flash('flash_error', '表单已过期，请重试');
        redirect(base_url('admin/channels/index.php'));
    }
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $id = (int)($_POST['id'] ?? 0);
    $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
    if ($action === 'toggle') {
        $channel = Channel::getById($id);
        if ($channel !== false) {
            Channel::update($id, ['status' => $channel['status'] ? 0 : 1]);
            session_flash('flash_success', '渠道状态已更新');
            audit_log('channel_toggle', "#{$id}", $channel['name']);
        }
    } elseif ($action === 'delete') {
        if (Channel::delete($id)) {
            session_flash('flash_success', '渠道已删除');
            audit_log('channel_delete', "#{$id}");
        } else {
            session_flash('flash_error', '删除失败');
        }
    } elseif ($action === 'copy') {
        $channel = Channel::getById($id);
        if ($channel === false) {
            session_flash('flash_error', '渠道不存在');
        } else {
            $newId = Channel::create([
                'name' => mb_substr($channel['name'] . '（副本）', 0, 100),
                'type' => $channel['type'],
                'base_url' => $channel['base_url'],
                'api_key' => $channel['api_key'],
                'models' => $channel['models'],
                'weight' => (int)$channel['weight'],
                'priority' => (int)$channel['priority'],
                'status' => 0,
                'remark' => $channel['remark'],
            ]);
            if ($newId !== false) {
                session_flash('flash_success', '已复制为新渠道（默认停用），请编辑完善');
                audit_log('channel_copy', "#{$id} -> #{$newId}", $channel['name']);
                redirect(base_url('admin/channels/edit.php?id=' . (int)$newId));
            }
            session_flash('flash_error', '复制失败');
        }
    } elseif ($action === 'batch_enable' || $action === 'batch_disable') {
        if (empty($ids)) {
            session_flash('flash_error', '请先勾选渠道');
        } else {
            $status = $action === 'batch_enable' ? 1 : 0;
            $in = implode(',', $ids);
            DB::query('UPDATE channels SET status = ' . $status . ' WHERE id IN (' . $in . ')');
            session_flash('flash_success', '已' . ($status ? '启用' : '停用') . ' ' . count($ids) . ' 个渠道');
            audit_log('channel_batch_' . ($status ? 'enable' : 'disable'), null, 'ids=' . $in);
        }
    } elseif ($action === 'batch_delete') {
        if (empty($ids)) {
            session_flash('flash_error', '请先勾选渠道');
        } else {
            foreach ($ids as $cid) {
                Channel::delete((int)$cid);
            }
            session_flash('flash_success', '已删除 ' . count($ids) . ' 个渠道');
            audit_log('channel_batch_delete', null, 'ids=' . implode(',', $ids));
        }
    } elseif ($action === 'test') {
        $result = Channel::test($id);
        if (!empty($result['ok'])) {
            $msg = "测试成功：{$result['model']}，耗时 {$result['elapsed']}ms";
            if (!empty($result['usage'])) {
                $msg .= '，usage: ' . json_encode($result['usage'], JSON_UNESCAPED_UNICODE);
            }
            Channel::incrementSuccess($id);
            session_flash('flash_success', $msg);
        } else {
            $detail = isset($result['detail']) && $result['detail'] !== '' ? '；' . $result['detail'] : '';
            session_flash('flash_error', '测试失败：' . $result['message'] . $detail);
        }
    }
    redirect(base_url('admin/channels/index.php'));
}

$filterType = isset($_GET['type']) && $_GET['type'] !== '' ? $_GET['type'] : '';
$filterStatus = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;
$filterGroup = isset($_GET['group']) && trim($_GET['group']) !== '' ? trim($_GET['group']) : '';
$sql = 'SELECT * FROM channels WHERE 1=1';
$params = [];
if ($filterType !== '') {
    $sql .= ' AND type = ?';
    $params[] = $filterType;
}
if ($filterStatus !== null) {
    $sql .= ' AND status = ?';
    $params[] = $filterStatus;
}
if ($filterGroup !== '') {
    $sql .= ' AND (group = "" OR CONCAT(",", REPLACE(`group`,", ",""), ",") LIKE ?)';
    $params[] = '%,' . $filterGroup . ',%';
}
$sql .= ' ORDER BY priority DESC, id ASC';
$channels = DB::fetchAll($sql, $params);
$groupOptions = Group::allGroups();
?>
<?php require dirname(__DIR__) . '/templates/header.php'; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; gap:10px; flex-wrap:wrap;">
    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
        <form method="get" id="filterForm" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <select name="type" class="form-control" style="width:120px; height:32px;" onchange="this.form.submit()">
                <option value="">全部类型</option>
                <option value="openai" <?php echo $filterType === 'openai' ? 'selected' : ''; ?>>OpenAI</option>
                <option value="azure" <?php echo $filterType === 'azure' ? 'selected' : ''; ?>>Azure</option>
                <option value="custom" <?php echo $filterType === 'custom' ? 'selected' : ''; ?>>自定义</option>
            </select>
            <select name="status" class="form-control" style="width:120px; height:32px;" onchange="this.form.submit()">
                <option value="">全部状态</option>
                <option value="1" <?php echo $filterStatus === 1 ? 'selected' : ''; ?>>启用</option>
                <option value="0" <?php echo $filterStatus === 0 ? 'selected' : ''; ?>>停用</option>
            </select>
            <select name="group" class="form-control" style="width:130px; height:32px;" onchange="this.form.submit()">
                <option value="">全部分组</option>
                <?php foreach ($groupOptions as $gname) : ?>
                    <option value="<?php echo e($gname); ?>" <?php echo $filterGroup === $gname ? 'selected' : ''; ?>><?php echo e($gname); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($filterType !== '' || $filterStatus !== null || $filterGroup !== '') : ?>
                <a class="btn btn-sm btn-secondary" href="<?php echo base_url('admin/channels/index.php'); ?>">清空筛选</a>
            <?php endif; ?>
        </form>
        <form method="post" id="batchForm" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" id="batchAction" value="">
            <button type="button" class="btn btn-sm btn-success" onclick="submitBatch('batch_enable')"><?php echo svg_icon('check'); ?>批量启用</button>
            <button type="button" class="btn btn-sm btn-warning" onclick="submitBatch('batch_disable')">批量停用</button>
            <button type="button" class="btn btn-sm btn-danger" onclick="submitBatch('batch_delete')">批量删除</button>
            <span class="form-hint" style="margin:0;">勾选表格左侧复选框后操作</span>
        </form>
    </div>
    <a class="btn" href="<?php echo base_url('admin/channels/edit.php'); ?>"><?php echo svg_icon('plus'); ?>新建渠道</a>
</div>

<table class="table">
    <thead>
        <tr>
            <th style="width:36px;"><input type="checkbox" id="checkAll" onclick="toggleAll(this)"></th>
            <th>ID</th><th>名称</th><th>类型</th><th>地址</th><th>模型</th><th>分组</th><th>权重</th>
            <th>优先级</th><th>状态</th><th>成功/失败</th><th>最后使用</th><th>操作</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($channels)) : ?>
        <tr><td colspan="13" class="text-center text-muted">暂无渠道，点击右上角创建</td></tr>
    <?php endif; ?>
    <?php foreach ($channels as $channel) : ?>
        <tr>
            <td><input type="checkbox" class="ch-row" value="<?php echo $channel['id']; ?>"></td>
            <td><?php echo $channel['id']; ?></td>
            <td><?php echo e($channel['name']); ?></td>
            <td><span class="badge badge-blue"><?php echo e($channel['type']); ?></span></td>
            <td style="max-width:220px; word-break:break-all;"><?php echo e($channel['base_url']); ?></td>
            <td><?php echo e($channel['models'] ?: '全部'); ?></td>
            <td><?php echo e($channel['group'] ?: '全部'); ?></td>
            <td><?php echo (int)$channel['weight']; ?></td>
            <td><?php echo (int)$channel['priority']; ?></td>
            <td>
                <?php echo $channel['status'] ? '<span class="badge badge-green">启用</span>' : '<span class="badge badge-gray">停用</span>'; ?>
            </td>
            <td>
                <span class="badge badge-green"><?php echo (int)$channel['success_count']; ?></span>
                <span class="badge <?php echo $channel['fail_count'] > $channel['success_count'] ? 'badge-red' : 'badge-gray'; ?>"><?php echo (int)$channel['fail_count']; ?></span>
            </td>
            <td><?php echo e($channel['last_use_at'] ?: '-'); ?></td>
            <td style="white-space:nowrap;">
                <form method="post" style="display:inline-block; margin-right:4px;">
                    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="id" value="<?php echo $channel['id']; ?>">
                    <button type="submit" name="action" value="test" class="btn btn-sm btn-secondary">测试</button>
                </form>
                <a class="btn btn-sm" href="<?php echo base_url('admin/channels/edit.php?id=' . $channel['id']); ?>">编辑</a>
                <form method="post" style="display:inline-block; margin-right:4px;">
                    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="id" value="<?php echo $channel['id']; ?>">
                    <button type="submit" name="action" value="copy" class="btn btn-sm btn-secondary">复制</button>
                </form>
                <form method="post" style="display:inline-block; margin-right:4px;">
                    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="id" value="<?php echo $channel['id']; ?>">
                    <button type="submit" name="action" value="toggle" class="btn btn-sm btn-warning"><?php echo $channel['status'] ? '停用' : '启用'; ?></button>
                </form>
                <form method="post" style="display:inline-block;" data-confirm-title="删除渠道" data-confirm-msg="确定删除该渠道？删除后不可恢复。" data-confirm-ok="删除">
                    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="id" value="<?php echo $channel['id']; ?>">
                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger">删除</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<script>
function toggleAll(box) {
    document.querySelectorAll('.ch-row').forEach(function (c) { c.checked = box.checked; });
}
function submitBatch(action) {
    var ids = [];
    document.querySelectorAll('.ch-row:checked').forEach(function (c) { ids.push(c.value); });
    if (!ids.length) { LcyModal.alert({ title: '批量操作', message: '请先勾选至少一个渠道' }); return; }
    var labels = { batch_enable: ['批量启用渠道', '确定启用已勾选的 ', '启用'], batch_disable: ['批量停用渠道', '确定停用已勾选的 ', '停用'], batch_delete: ['批量删除渠道', '确定删除已勾选的 ', '删除'] };
    var cfg = labels[action] || ['批量操作', '确定对已勾选的 ', '确定'];
    LcyModal.open({
        title: cfg[0],
        message: cfg[1] + ids.length + ' 个渠道？' + (action === 'batch_delete' ? '该操作不可恢复。' : ''),
        confirmText: cfg[2],
        danger: action === 'batch_delete',
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