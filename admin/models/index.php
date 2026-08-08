<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '模型管理';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        session_flash('flash_error', '表单已过期，请重试');
        redirect(base_url('admin/models/index.php'));
    }
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'save') {
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'display_name' => trim($_POST['display_name'] ?? ''),
            'input_price' => (float)($_POST['input_price'] ?? 0),
            'output_price' => (float)($_POST['output_price'] ?? 0),
            'context_length' => max(1, (int)($_POST['context_length'] ?? 4096)),
            'max_output' => max(1, (int)($_POST['max_output'] ?? 2048)),
            'type' => in_array(($_POST['type'] ?? 'chat'), ['chat', 'completion', 'embedding', 'image', 'audio'], true) ? $_POST['type'] : 'chat',
            'enabled' => empty($_POST['enabled']) ? 0 : 1,
            'sort' => (int)($_POST['sort'] ?? 0),
        ];
        if ($data['name'] === '' || preg_match('/^[A-Za-z0-9_.\-\/:]{1,100}$/', $data['name']) !== 1) {
            session_flash('flash_error', '模型标识不合法（字母数字、点、下划线、短横线、斜杠）');
        } elseif ((int)$_POST['mid'] > 0) {
            Model::update((int)$_POST['mid'], $data);
            session_flash('flash_success', '模型已更新');
        } elseif (Model::create($data)) {
            session_flash('flash_success', '模型已创建');
        } else {
            session_flash('flash_error', '创建失败，模型可能已存在');
        }
        redirect(base_url('admin/models/index.php'));
    }
    if ($action === 'toggle') {
        $model = Model::getById($id);
        if ($model !== false) {
            Model::update($id, ['enabled' => $model['enabled'] ? 0 : 1]);
            session_flash('flash_success', '模型状态已更新');
        }
    } elseif ($action === 'delete') {
        if (Model::delete($id)) {
            session_flash('flash_success', '模型已删除');
        } else {
            session_flash('flash_error', '删除失败');
        }
    }
    redirect(base_url('admin/models/index.php'));
}

$models = Model::all();
$editId = (int)($_GET['edit'] ?? 0);
$edit = $editId > 0 ? Model::getById($editId) : false;
$m = $edit !== false ? $edit : ['id' => 0, 'name' => '', 'display_name' => '', 'input_price' => 0, 'output_price' => 0, 'context_length' => 4096, 'max_output' => 2048, 'type' => 'chat', 'enabled' => 1, 'sort' => 0];
?>
<?php require dirname(__DIR__) . '/templates/header.php'; ?>

<div class="card">
    <div class="card-title"><?php echo $edit !== false ? '编辑模型 #' . $edit['id'] : '新增模型'; ?></div>
    <form method="post" action="<?php echo base_url('admin/models/index.php'); ?>">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="mid" value="<?php echo (int)$m['id']; ?>">
        <div style="display:flex; gap:16px; flex-wrap:wrap;">
            <div class="form-group" style="flex:1; min-width:150px;">
                <label>模型标识</label>
                <input type="text" name="name" class="form-control" value="<?php echo e($m['name']); ?>" placeholder="gpt-4o" <?php echo $edit !== false ? 'readonly' : ''; ?>>
            </div>
            <div class="form-group" style="flex:1; min-width:150px;">
                <label>显示名称</label>
                <input type="text" name="display_name" class="form-control" value="<?php echo e($m['display_name']); ?>">
            </div>
            <div class="form-group" style="flex:1; min-width:150px;">
                <label>类型</label>
                <select name="type" class="form-control" <?php echo $edit !== false ? 'disabled' : ''; ?>>
                    <?php foreach (['chat' => '对话', 'completion' => '补全', 'embedding' => '向量', 'image' => '图像', 'audio' => '音频'] as $tv => $tn) : ?>
                        <option value="<?php echo $tv; ?>" <?php echo $m['type'] === $tv ? 'selected' : ''; ?>><?php echo $tn; ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($edit !== false) : ?><input type="hidden" name="type" value="<?php echo e($m['type']); ?>"><?php endif; ?>
            </div>
        </div>
        <div style="display:flex; gap:16px; flex-wrap:wrap;">
            <div class="form-group" style="flex:1; min-width:140px;">
                <label>输入价格（$ / 1K tokens）</label>
                <input type="number" name="input_price" step="0.000001" min="0" class="form-control" value="<?php echo e($m['input_price']); ?>">
            </div>
            <div class="form-group" style="flex:1; min-width:140px;">
                <label>输出价格（$ / 1K tokens）</label>
                <input type="number" name="output_price" step="0.000001" min="0" class="form-control" value="<?php echo e($m['output_price']); ?>">
            </div>
            <div class="form-group" style="flex:1; min-width:140px;">
                <label>上下文长度</label>
                <input type="number" name="context_length" min="1" class="form-control" value="<?php echo (int)$m['context_length']; ?>">
            </div>
            <div class="form-group" style="flex:1; min-width:140px;">
                <label>最大输出</label>
                <input type="number" name="max_output" min="1" class="form-control" value="<?php echo (int)$m['max_output']; ?>">
            </div>
            <div class="form-group" style="flex:1; min-width:100px;">
                <label>排序</label>
                <input type="number" name="sort" class="form-control" value="<?php echo (int)$m['sort']; ?>">
            </div>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="enabled" value="1" <?php echo $m['enabled'] ? 'checked' : ''; ?> style="width:auto;"> 启用模型</label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn"><?php echo $edit !== false ? '保存修改' : '新增模型'; ?></button>
            <?php if ($edit !== false) : ?><a class="btn btn-secondary" href="<?php echo base_url('admin/models/index.php'); ?>">取消编辑</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-title">模型列表（<?php echo count($models); ?>）</div>
    <table class="table">
        <thead>
            <tr><th>ID</th><th>模型</th><th>类型</th><th>输入价</th><th>输出价</th><th>上下文</th><th>最大输出</th><th>状态</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php if (empty($models)) : ?>
            <tr><td colspan="9" class="text-center text-muted">暂无模型</td></tr>
        <?php endif; ?>
        <?php foreach ($models as $model) : ?>
            <tr>
                <td><?php echo $model['id']; ?></td>
                <td><?php echo e($model['name']); ?><?php if ($model['display_name']) : ?> <span class="badge badge-gray"><?php echo e($model['display_name']); ?></span><?php endif; ?></td>
                <td><?php echo e($model['type']); ?></td>
                <td>$<?php echo e(number_format((float)$model['input_price'], 6)); ?></td>
                <td>$<?php echo e(number_format((float)$model['output_price'], 6)); ?></td>
                <td><?php echo number_format((int)$model['context_length']); ?></td>
                <td><?php echo number_format((int)$model['max_output']); ?></td>
                <td><?php echo $model['enabled'] ? '<span class="badge badge-green">启用</span>' : '<span class="badge badge-gray">停用</span>'; ?></td>
                <td style="white-space:nowrap;">
                    <a class="btn btn-sm" href="<?php echo base_url('admin/models/index.php?edit=' . $model['id']); ?>">编辑</a>
                    <form method="post" style="display:inline-block; margin-right:4px;">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="id" value="<?php echo $model['id']; ?>">
                        <button type="submit" name="action" value="toggle" class="btn btn-sm btn-warning"><?php echo $model['enabled'] ? '停用' : '启用'; ?></button>
                    </form>
                    <form method="post" style="display:inline-block;" onsubmit="return confirm('确定删除该模型？');">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="id" value="<?php echo $model['id']; ?>">
                        <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger">删除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>