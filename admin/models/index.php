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
            'description' => mb_substr(trim($_POST['description'] ?? ''), 0, 1000),
            'tags' => mb_substr(trim($_POST['tags'] ?? ''), 0, 255),
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
            audit_log('model_save', "#{$_POST['mid']}", $data['name']);
        } elseif (Model::create($data)) {
            session_flash('flash_success', '模型已创建');
            audit_log('model_save', null, $data['name']);
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
            audit_log('model_toggle', "#{$id}", $model['name']);
        }
    } elseif ($action === 'delete') {
        if (Model::delete($id)) {
            session_flash('flash_success', '模型已删除');
            audit_log('model_delete', "#{$id}");
        } else {
            session_flash('flash_error', '删除失败');
        }
    } elseif ($action === 'batch_save') {
        $prices = isset($_POST['input_price']) && is_array($_POST['input_price']) ? $_POST['input_price'] : [];
        $outs = isset($_POST['output_price']) && is_array($_POST['output_price']) ? $_POST['output_price'] : [];
        $ctx = isset($_POST['context_length']) && is_array($_POST['context_length']) ? $_POST['context_length'] : [];
        $maxout = isset($_POST['max_output']) && is_array($_POST['max_output']) ? $_POST['max_output'] : [];
        $count = 0;
        foreach ($prices as $mid => $price) {
            $mid = (int)$mid;
            if ($mid <= 0 || Model::getById($mid) === false) {
                continue;
            }
            Model::update($mid, [
                'input_price' => max(0, (float)$price),
                'output_price' => max(0, (float)($outs[$mid] ?? 0)),
                'context_length' => max(1, (int)($ctx[$mid] ?? 4096)),
                'max_output' => max(1, (int)($maxout[$mid] ?? 2048)),
            ]);
            $count++;
        }
        session_flash('flash_success', '已批量更新 ' . $count . ' 个模型');
        audit_log('model_batch_save', null, 'count=' . $count);
        redirect(base_url('admin/models/index.php'));
    } elseif ($action === 'save_missing') {
        $names = isset($_POST['models']) && is_array($_POST['models']) ? $_POST['models'] : [];
        $ins = isset($_POST['input_price']) && is_array($_POST['input_price']) ? $_POST['input_price'] : [];
        $outs = isset($_POST['output_price']) && is_array($_POST['output_price']) ? $_POST['output_price'] : [];
        $created = 0;
        foreach ($names as $name) {
            $name = trim((string)$name);
            if ($name === '' || preg_match('/^[A-Za-z0-9_.\-\/:]{1,100}$/', $name) !== 1) {
                continue;
            }
            if (Model::create([
                'name' => $name,
                'input_price' => max(0, (float)($ins[$name] ?? 0)),
                'output_price' => max(0, (float)($outs[$name] ?? 0)),
                'context_length' => 4096,
                'max_output' => 2048,
                'type' => 'chat',
                'enabled' => 1,
            ])) {
                $created++;
            }
        }
        session_flash('flash_success', '已录入 ' . $created . ' 个缺失模型');
        audit_log('model_save_missing', null, 'count=' . $created);
        redirect(base_url('admin/models/index.php'));
    }
    redirect(base_url('admin/models/index.php'));
}

$models = Model::all();
$keyword = trim($_GET['q'] ?? '');
if ($keyword !== '') {
    $kw = $keyword;
    $models = array_values(array_filter($models, function ($m) use ($kw) {
        return stripos($m['name'], $kw) !== false
            || ($m['display_name'] && stripos($m['display_name'], $kw) !== false)
            || ($m['tags'] && stripos($m['tags'], $kw) !== false);
    }));
}
$editId = (int)($_GET['edit'] ?? 0);
$edit = $editId > 0 ? Model::getById($editId) : false;
$m = $edit !== false ? $edit : ['id' => 0, 'name' => '', 'display_name' => '', 'description' => '', 'tags' => '', 'input_price' => 0, 'output_price' => 0, 'context_length' => 4096, 'max_output' => 2048, 'type' => 'chat', 'enabled' => 1, 'sort' => 0];

/* 缺失模型检测：渠道配置了但 models 表没有的模型 */
$channels = DB::fetchAll('SELECT id, name, models FROM channels WHERE status = 1');
$existingNames = [];
foreach ($models as $model) {
    $existingNames[strtolower($model['name'])] = true;
}
$missingModels = [];
foreach ($channels as $ch) {
    if (empty($ch['models'])) {
        continue;
    }
    foreach (array_filter(array_map('trim', explode(',', $ch['models']))) as $mm) {
        if ($mm !== '' && !isset($existingNames[strtolower($mm)])) {
            $missingModels[$mm] = ['channel_id' => (int)$ch['id'], 'channel_name' => $ch['name']];
        }
    }
}
ksort($missingModels);
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
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">启用模型</label>
            <label class="ios-switch"><input type="checkbox" name="enabled" value="1" <?php echo $m['enabled'] ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div style="display:flex; gap:16px; flex-wrap:wrap;">
            <div class="form-group" style="flex:1; min-width:220px;">
                <label>模型描述（前台价格页展示）</label>
                <input type="text" name="description" class="form-control" value="<?php echo e($m['description']); ?>" placeholder="例如：综合能力强的轻量模型">
            </div>
            <div class="form-group" style="flex:1; min-width:180px;">
                <label>标签（逗号分隔）</label>
                <input type="text" name="tags" class="form-control" value="<?php echo e($m['tags']); ?>" placeholder="热门,新上">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn"><?php echo $edit !== false ? '保存修改' : '新增模型'; ?></button>
            <?php if ($edit !== false) : ?><a class="btn btn-secondary" href="<?php echo base_url('admin/models/index.php'); ?>">取消编辑</a><?php endif; ?>
        </div>
    </form>
</div>

<?php if (!empty($missingModels)) : ?>
<div class="card" style="border-color:var(--danger);">
    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap;">
        <span style="color:var(--danger);"><?php echo svg_icon('alert'); ?>缺失模型检测：<?php echo count($missingModels); ?> 个模型已在渠道配置但未录入价格表</span>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <button type="button" class="btn btn-sm" id="checkAllMissing" onclick="checkMissing(true)">全选</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="checkMissing(false)">取消全选</button>
        </div>
    </div>
    <form method="post" action="<?php echo base_url('admin/models/index.php'); ?>" id="missingForm">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="action" value="save_missing">
        <table class="table">
            <thead><tr><th style="width:36px;"><input type="checkbox" onclick="checkMissing(this.checked)"></th><th>模型</th><th>来源渠道</th><th>输入价（$ / 1K）</th><th>输出价（$ / 1K）</th></tr></thead>
            <tbody>
            <?php foreach ($missingModels as $mmName => $src) : ?>
                <tr>
                    <td><input type="checkbox" class="ch-missing" name="models[]" value="<?php echo e($mmName); ?>" checked></td>
                    <td><?php echo e($mmName); ?></td>
                    <td><a href="<?php echo base_url('admin/channels/edit.php?id=' . $src['channel_id']); ?>"><?php echo e($src['channel_name']); ?></a></td>
                    <td><input type="number" step="0.000001" min="0" name="input_price[<?php echo e($mmName); ?>]" class="form-control" style="width:110px; height:32px;" value="0"></td>
                    <td><input type="number" step="0.000001" min="0" name="output_price[<?php echo e($mmName); ?>]" class="form-control" style="width:110px; height:32px;" value="0"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="form-actions">
            <button type="submit" class="btn">一键录入所选模型</button>
            <span class="form-hint">录入后即可正常计费；价格可在下方列表批量修改</span>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <span>模型列表（<?php echo count($models); ?> 个<?php echo $keyword !== '' ? '，关键词：' . e($keyword) : ''; ?>）</span>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <a class="btn btn-sm btn-secondary" href="<?php echo base_url('admin/models/sync.php'); ?>"><?php echo svg_icon('refresh'); ?>从渠道同步模型</a>
            <form method="get" style="display:flex; gap:8px; align-items:center;">
                <input type="text" name="q" class="form-control" style="width:220px;" value="<?php echo e($keyword); ?>" placeholder="模型 / 显示名 / 标签">
                <button type="submit" class="btn btn-sm"><?php echo svg_icon('search'); ?>搜索</button>
            </form>
        </div>
    </div>
    <form method="post" action="<?php echo base_url('admin/models/index.php'); ?>" id="batchPriceForm">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="action" value="batch_save">
        <table class="table">
            <thead>
                <tr><th>ID</th><th>模型</th><th>类型</th><th>输入价</th><th>输出价</th><th>上下文</th><th>最大输出</th><th>状态</th><th>操作</th></tr>
            </thead>
            <tbody>
            <?php if (empty($models)) : ?>
                <tr><td colspan="9" class="text-center text-muted"><?php echo $keyword !== '' ? '没有匹配的模型' : '暂无模型'; ?></td></tr>
            <?php endif; ?>
            <?php foreach ($models as $model) : ?>
                <tr>
                    <td><?php echo $model['id']; ?></td>
                    <td><?php echo e($model['name']); ?><?php if ($model['display_name']) : ?> <span class="badge badge-gray"><?php echo e($model['display_name']); ?></span><?php endif; ?><?php if ($model['tags']) : ?><div class="form-hint"><?php echo e($model['tags']); ?></div><?php endif; ?></td>
                    <td><?php echo e($model['type']); ?></td>
                    <td><input type="number" step="0.000001" min="0" name="input_price[<?php echo (int)$model['id']; ?>]" class="form-control" style="width:110px; height:32px;" value="<?php echo e($model['input_price']); ?>"></td>
                    <td><input type="number" step="0.000001" min="0" name="output_price[<?php echo (int)$model['id']; ?>]" class="form-control" style="width:110px; height:32px;" value="<?php echo e($model['output_price']); ?>"></td>
                    <td><input type="number" min="1" name="context_length[<?php echo (int)$model['id']; ?>]" class="form-control" style="width:100px; height:32px;" value="<?php echo (int)$model['context_length']; ?>"></td>
                    <td><input type="number" min="1" name="max_output[<?php echo (int)$model['id']; ?>]" class="form-control" style="width:100px; height:32px;" value="<?php echo (int)$model['max_output']; ?>"></td>
                    <td><?php echo $model['enabled'] ? '<span class="badge badge-green">启用</span>' : '<span class="badge badge-gray">停用</span>'; ?></td>
                    <td style="white-space:nowrap;">
                        <a class="btn btn-sm" href="<?php echo base_url('admin/models/index.php?edit=' . $model['id']); ?>">编辑</a>
                        <form method="post" style="display:inline-block; margin-right:4px;">
                            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="id" value="<?php echo $model['id']; ?>">
                            <button type="submit" name="action" value="toggle" class="btn btn-sm btn-warning"><?php echo $model['enabled'] ? '停用' : '启用'; ?></button>
                        </form>
                        <form method="post" style="display:inline-block;" data-confirm-title="删除模型" data-confirm-msg="确定删除该模型？删除后不可恢复。" data-confirm-ok="删除">
                            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="id" value="<?php echo $model['id']; ?>">
                            <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger">删除</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!empty($models)) : ?>
            <div class="form-actions">
                <button type="submit" class="btn">保存批量修改</button>
            </div>
        <?php endif; ?>
    </form>
</div>
<script>
function checkMissing(checked) {
    document.querySelectorAll('.ch-missing').forEach(function (c) { c.checked = checked; });
}
</script>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>