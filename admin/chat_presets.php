<?php
/**
 * 聊天预设管理 - 配置外部聊天客户端（LobeChat 等 iframe 嵌入）
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '聊天预设';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        session_flash('flash_error', '表单已过期');
        redirect(base_url('admin/chat_presets.php'));
    }
    $action = $_POST['action'] ?? '';
    $list = ChatPreset::all();
    if ($action === 'save') {
        $preset = [
            'id' => bin2hex(random_bytes(8)),
            'name' => trim($_POST['name'] ?? ''),
            'type' => in_array($_POST['type'] ?? '', ['web', 'external'], true) ? $_POST['type'] : 'web',
            'url' => trim($_POST['url'] ?? ''),
            'enabled' => empty($_POST['enabled']) ? 0 : 1,
        ];
        $list[] = $preset;
        ChatPreset::save($list);
        session_flash('flash_success', '预设已添加');
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        $list = array_values(array_filter($list, function ($p) use ($id) { return ($p['id'] ?? '') !== $id; }));
        ChatPreset::save($list);
        session_flash('flash_success', '预设已删除');
    }
    redirect(base_url('admin/chat_presets.php'));
}

$presets = ChatPreset::all();
require __DIR__ . '/templates/header.php';
?>
<div class="card">
    <div class="card-title"><?php echo svg_icon('send'); ?>新增聊天预设</div>
    <form method="post" action="<?php echo base_url('admin/chat_presets.php'); ?>">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="action" value="save">
        <div class="form-group">
            <label>名称</label>
            <input type="text" name="name" class="form-control" required placeholder="LobeChat">
        </div>
        <div class="form-group">
            <label>类型</label>
            <select name="type" class="form-control">
                <option value="web">Web（iframe 嵌入）</option>
                <option value="external">External（外部客户端）</option>
            </select>
        </div>
        <div class="form-group">
            <label>URL（使用 {api_key} / {server_url} 占位符）</label>
            <input type="text" name="url" class="form-control" required placeholder="https://lobechat.com/chat?api_key={api_key}&api_url={server_url}">
            <div class="form-hint">{api_key} 会被替换为用户的 API Key，{server_url} 替换为服务器地址</div>
        </div>
        <div class="form-group" style="display:flex; align-items:center; gap:10px;">
            <label class="ios-switch"><input type="checkbox" name="enabled" value="1" checked><span></span></label>
            <span>启用</span>
        </div>
        <button type="submit" class="btn">添加</button>
    </form>
</div>
<div class="card">
    <div class="card-title">已有预设</div>
    <table class="table">
        <thead><tr><th>名称</th><th>类型</th><th>URL</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($presets as $p) : ?>
            <tr>
                <td><?php echo e($p['name'] ?? ''); ?></td>
                <td><span class="badge badge-blue"><?php echo e($p['type'] ?? 'web'); ?></span></td>
                <td style="max-width:300px;word-break:break-all;"><?php echo e($p['url'] ?? ''); ?></td>
                <td><?php echo empty($p['enabled']) ? '<span class="badge badge-gray">停用</span>' : '<span class="badge badge-green">启用</span>'; ?></td>
                <td>
                    <form method="post" style="display:inline;" data-confirm-title="删除预设" data-confirm-msg="确定删除？" data-confirm-ok="删除">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="id" value="<?php echo e($p['id'] ?? ''); ?>">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; if (empty($presets)): ?><tr><td colspan="5" class="text-center text-muted">暂无预设</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>