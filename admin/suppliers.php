<?php
/**
 * 供应商/模型部署管理
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '供应商与部署';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    session_flash('flash_error', '表单已过期');
    redirect(base_url('admin/suppliers.php'));
}

/* 供应商 CRUD */
$action = $_POST['action'] ?? '';
$sid = (int)($_POST['sid'] ?? 0);
if ($action === 'save_supplier') {
    $name = trim($_POST['name'] ?? '');
    $apiUrl = trim($_POST['api_url'] ?? '');
    $apiKey = trim($_POST['api_key'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($name === '') {
        session_flash('flash_error', '供应商名称不能为空');
    } else {
        $data = ['name' => $name, 'api_url' => $apiUrl, 'api_key' => $apiKey, 'description' => $desc];
        if ($sid > 0) {
            DB::update('suppliers', $data, 'id = ?', [$sid]);
        } else {
            $sid = DB::insert('suppliers', $data);
        }
        session_flash('flash_success', '供应商已保存');
        audit_log('supplier_save', "#$sid", $name);
    }
    redirect(base_url('admin/suppliers.php'));
} elseif ($action === 'delete_supplier') {
    DB::delete('suppliers', 'id = ?', [$sid]);
    DB::delete('deployments', 'supplier_id = ?', [$sid]);
    session_flash('flash_success', '供应商已删除');
    audit_log('supplier_delete', "#$sid");
    redirect(base_url('admin/suppliers.php'));
} elseif ($action === 'save_deployment') {
    $supplierId = (int)($_POST['supplier_id'] ?? 0);
    $model = trim($_POST['model'] ?? '');
    $endpoint = trim($_POST['endpoint'] ?? '');
    $status = empty($_POST['status']) ? 0 : 1;
    if ($model === '' || $supplierId <= 0) {
        session_flash('flash_error', '参数不完整');
    } else {
        $did = (int)($_POST['did'] ?? 0);
        $data = ['supplier_id' => $supplierId, 'model' => $model, 'endpoint' => $endpoint, 'status' => $status];
        if ($did > 0) {
            DB::update('deployments', $data, 'id = ?', [$did]);
        } else {
            $did = DB::insert('deployments', $data);
        }
        session_flash('flash_success', '部署已保存');
        audit_log('deployment_save', "#$did", $model);
    }
    redirect(base_url('admin/suppliers.php'));
} elseif ($action === 'delete_deployment') {
    DB::delete('deployments', 'id = ?', [(int)($_POST['did'] ?? 0)]);
    session_flash('flash_success', '部署已删除');
    redirect(base_url('admin/suppliers.php'));
}

$suppliers = DB::fetchAll('SELECT * FROM suppliers ORDER BY id ASC');
$deployments = DB::fetchAll('SELECT d.*, s.name AS supplier_name FROM deployments d LEFT JOIN suppliers s ON s.id=d.supplier_id ORDER BY d.id ASC');
require __DIR__ . '/templates/header.php';
?>
<div class="card">
    <div class="card-title"><?php echo svg_icon('plus'); ?>新增/编辑供应商</div>
    <form method="post" action="<?php echo base_url('admin/suppliers.php'); ?>">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="action" value="save_supplier">
        <div class="form-group"><label>名称</label><input type="text" name="name" class="form-control" required placeholder="例如：MyProvider"></div>
        <div class="form-group"><label>API 地址</label><input type="text" name="api_url" class="form-control" placeholder="https://api.example.com"></div>
        <div class="form-group"><label>API Key</label><input type="text" name="api_key" class="form-control" placeholder="sk-xxx"></div>
        <div class="form-group"><label>描述</label><textarea name="description" class="form-control" rows="2"></textarea></div>
        <button type="submit" class="btn">保存</button>
    </form>
</div>

<div class="card">
    <div class="card-title">供应商列表</div>
    <table class="table">
        <thead><tr><th>ID</th><th>名称</th><th>API 地址</th><th>描述</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($suppliers as $s) : ?>
            <tr><td><?php echo $s['id']; ?></td><td><?php echo e($s['name']); ?></td><td><?php echo e($s['api_url']); ?></td><td><?php echo e($s['description']); ?></td>
                <td style="white-space:nowrap;">
                    <a class="btn btn-sm" href="#edit" onclick="editSupplier(<?php echo $s['id']; ?>, '<?php echo e($s['name']); ?>', '<?php echo e($s['api_url']); ?>', '<?php echo e($s['api_key']); ?>', '<?php echo e($s['description']); ?>')">编辑</a>
                    <form method="post" style="display:inline;" data-confirm-title="删除供应商" data-confirm-msg="删除供应商将同时删除其所有部署，确定？" data-confirm-ok="删除">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="sid" value="<?php echo $s['id']; ?>">
                        <input type="hidden" name="action" value="delete_supplier">
                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; if (empty($suppliers)): ?><tr><td colspan="5" class="text-center text-muted">暂无供应商</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <div class="card-title"><?php echo svg_icon('plus'); ?>新增部署</div>
    <form method="post" action="<?php echo base_url('admin/suppliers.php'); ?>">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="action" value="save_deployment">
        <div class="form-group">
            <label>供应商</label>
            <select name="supplier_id" class="form-control">
                <?php foreach ($suppliers as $s) : ?><option value="<?php echo $s['id']; ?>"><?php echo e($s['name']); ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>模型名</label><input type="text" name="model" class="form-control" required placeholder="gpt-4o"></div>
        <div class="form-group"><label>端点路径</label><input type="text" name="endpoint" class="form-control" placeholder="/v1/chat/completions"></div>
        <div class="form-group" style="display:flex;align-items:center;gap:10px;"><label class="ios-switch"><input type="checkbox" name="status" value="1" checked><span></span></label><span>启用</span></div>
        <button type="submit" class="btn">保存</button>
    </form>
</div>

<div class="card">
    <div class="card-title">部署列表</div>
    <table class="table">
        <thead><tr><th>ID</th><th>供应商</th><th>模型</th><th>端点</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($deployments as $d) : ?>
            <tr><td><?php echo $d['id']; ?></td><td><?php echo e($d['supplier_name'] ?? ('#' . $d['supplier_id'])); ?></td><td><?php echo e($d['model']); ?></td><td><?php echo e($d['endpoint']); ?></td>
                <td><?php echo $d['status'] ? '<span class="badge badge-green">启用</span>' : '<span class="badge badge-gray">停用</span>'; ?></td>
                <td>
                    <form method="post" style="display:inline;" data-confirm-title="删除部署" data-confirm-msg="确定删除？" data-confirm-ok="删除">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="did" value="<?php echo $d['id']; ?>">
                        <input type="hidden" name="action" value="delete_deployment">
                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; if (empty($deployments)): ?><tr><td colspan="6" class="text-center text-muted">暂无部署</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<script>
function editSupplier(id, name, url, key, desc) {
    var form = document.querySelector('form input[name="action"][value="save_supplier"]').closest('form');
    form.querySelector('input[name="sid"]') || (function(){ var h = document.createElement('input'); h.type = 'hidden'; h.name = 'sid'; form.appendChild(h); })();
    form.querySelector('input[name="sid"]').value = id;
    form.querySelector('input[name="name"]').value = name;
    form.querySelector('input[name="api_url"]').value = url;
    form.querySelector('input[name="api_key"]').value = key;
    if (form.querySelector('textarea[name="description"]')) form.querySelector('textarea[name="description"]').value = desc;
    form.querySelector('button[type="submit"]').textContent = '更新';
    form.closest('.card').scrollIntoView({ behavior: 'smooth' });
}
</script>
<?php require __DIR__ . '/templates/footer.php'; ?>