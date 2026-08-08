<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '编辑渠道';

$id = (int)($_GET['id'] ?? 0);
$channel = $id > 0 ? Channel::getById($id) : false;
if ($id > 0 && $channel === false) {
    session_flash('flash_error', '渠道不存在');
    redirect(base_url('admin/channels/index.php'));
}
$isNew = $channel === false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        session_flash('flash_error', '表单已过期，请重试');
        redirect(base_url('admin/channels/edit.php'));
    }
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'type' => in_array(($_POST['type'] ?? 'openai'), ['openai', 'azure', 'custom'], true) ? $_POST['type'] : 'openai',
        'base_url' => rtrim(trim($_POST['base_url'] ?? ''), '/'),
        'api_key' => trim($_POST['api_key'] ?? ''),
        'models' => trim($_POST['models'] ?? ''),
        'weight' => max(1, (int)($_POST['weight'] ?? 1)),
        'priority' => (int)($_POST['priority'] ?? 0),
        'status' => empty($_POST['status']) ? 0 : 1,
        'remark' => mb_substr(trim($_POST['remark'] ?? ''), 0, 255),
    ];
    if ($data['name'] === '') {
        session_flash('flash_error', '渠道名称不能为空');
        redirect(base_url('admin/channels/edit.php' . ($id ? '?id=' . $id : '')));
    }
    if ($data['base_url'] === '' || filter_var($data['base_url'], FILTER_VALIDATE_URL) === false) {
        session_flash('flash_error', '渠道地址不合法（需以 http:// 或 https:// 开头）');
        redirect(base_url('admin/channels/edit.php' . ($id ? '?id=' . $id : '')));
    }
    if ($data['api_key'] === '') {
        session_flash('flash_error', 'API Key 不能为空');
        redirect(base_url('admin/channels/edit.php' . ($id ? '?id=' . $id : '')));
    }
    if ($channel !== false) {
        if ($_POST['api_key'] === '******') {
            unset($data['api_key']);
        }
        Channel::update($id, $data);
        session_flash('flash_success', '渠道已更新');
    } else {
        $newId = Channel::create($data);
        if ($newId !== false) {
            session_flash('flash_success', '渠道已创建');
        } else {
            session_flash('flash_error', '创建失败，请检查数据');
            redirect(base_url('admin/channels/edit.php'));
        }
    }
    redirect(base_url('admin/channels/index.php'));
}

$name = $channel ? $channel['name'] : '';
$type = $channel ? $channel['type'] : 'openai';
$baseUrl = $channel ? $channel['base_url'] : '';
$models = $channel ? $channel['models'] : '';
$weight = $channel ? (int)$channel['weight'] : 1;
$priority = $channel ? (int)$channel['priority'] : 0;
$status = $channel ? (int)$channel['status'] : 1;
$remark = $channel ? $channel['remark'] : '';
?>
<?php require dirname(__DIR__) . '/templates/header.php'; ?>

<form method="post" action="<?php echo base_url('admin/channels/edit.php' . ($id ? '?id=' . $id : '')); ?>">
    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
    <div class="card" style="max-width:720px;">
        <div class="card-title"><?php echo $isNew ? '新建渠道' : '编辑渠道 #' . $id; ?></div>

        <div class="form-group">
            <label>渠道名称</label>
            <input type="text" name="name" class="form-control" value="<?php echo e($name); ?>" placeholder="例如：OpenAI 官方">
        </div>
        <div class="form-group">
            <label>渠道类型</label>
            <select name="type" class="form-control">
                <option value="openai" <?php echo $type === 'openai' ? 'selected' : ''; ?>>OpenAI</option>
                <option value="azure" <?php echo $type === 'azure' ? 'selected' : ''; ?>>Azure</option>
                <option value="custom" <?php echo $type === 'custom' ? 'selected' : ''; ?>>自定义（OpenAI 兼容）</option>
            </select>
        </div>
        <div class="form-group">
            <label>渠道地址</label>
            <input type="text" name="base_url" class="form-control" value="<?php echo e($baseUrl); ?>" placeholder="https://api.openai.com">
            <div class="form-hint">支持带 /v1 结尾，例如 https://api.openai.com/v1</div>
        </div>
        <div class="form-group">
            <label>API Key</label>
            <input type="text" name="api_key" class="form-control" value="<?php echo $channel ? '******' : ''; ?>" placeholder="sk-xxx">
            <div class="form-hint"><?php echo $channel ? '留空或保持 ****** 表示不修改' : '渠道的上游密钥'; ?></div>
        </div>
        <div class="form-group">
            <label>支持的模型（逗号分隔，支持通配符，留空=全部）</label>
            <textarea name="models" class="form-control" rows="3" placeholder="gpt-4o,gpt-4o-mini,text-embedding-3-small,image-*"><?php echo e($models); ?></textarea>
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;">
                <label>权重</label>
                <input type="number" name="weight" class="form-control" value="<?php echo $weight; ?>" min="1">
            </div>
            <div style="flex:1;">
                <label>优先级（越大越优先）</label>
                <input type="number" name="priority" class="form-control" value="<?php echo $priority; ?>">
            </div>
        </div>
        <div class="form-group">
            <label>备注</label>
            <input type="text" name="remark" class="form-control" value="<?php echo e($remark); ?>">
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="status" value="1" <?php echo $status ? 'checked' : ''; ?> style="width:auto;"> 启用渠道</label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn"><?php echo $channel ? '保存' : '创建'; ?></button>
            <a href="<?php echo base_url('admin/channels/index.php'); ?>" class="btn btn-secondary">返回</a>
        </div>
    </div>
</form>

<?php require dirname(__DIR__) . '/templates/footer.php'; ?>