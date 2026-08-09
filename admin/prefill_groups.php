<?php
/**
 * 预填充分组管理：渠道/模型创建时的下拉预设
 */
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '预填充分组';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'save') {
        $name = trim($_POST['name'] ?? '');
        $type = in_array($_POST['type'] ?? '', ['channel', 'model', 'group'], true) ? $_POST['type'] : 'channel';
        $data = trim($_POST['data'] ?? '');
        if ($name !== '' && $data !== '') {
            if ($id > 0) {
                DB::update('prefill_groups', ['name' => $name, 'type' => $type, 'data' => $data], 'id = ?', [$id]);
            } else {
                DB::insert('prefill_groups', ['name' => $name, 'type' => $type, 'data' => $data]);
            }
            session_flash('flash_success', '预填充分组已保存');
        } else {
            session_flash('flash_error', '名称和数据不能为空');
        }
    } elseif ($action === 'delete') {
        DB::delete('prefill_groups', 'id = ?', [$id]);
        session_flash('flash_success', '已删除');
    }
    redirect(base_url('admin/prefill_groups.php'));
}

$groups = DB::fetchAll('SELECT * FROM prefill_groups ORDER BY type, id ASC');
require dirname(__DIR__) . '/templates/header.php';
?>
<div class="card">
    <div class="card-title"><?php echo svg_icon('plus'); ?>新增预填充分组</div>
    <form method="post" action="<?php echo base_url('admin/prefill_groups.php'); ?>">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="action" value="save">
        <div class="form-group" style="display:flex;gap:12px;">
            <div style="flex:1;"><label>名称</label><input type="text" name="name" class="form-control" required placeholder="如：常用渠道"></div>
            <div style="flex:1;"><label>类型</label>
                <select name="type" class="form-control">
                    <option value="channel">渠道</option>
                    <option value="model">模型</option>
                    <option value="group">分组</option>
                </select>
            </div>
        </div>
        <div class="form-group"><label>数据（JSON 数组，每项包含 name/type/... 等字段）</label>
            <textarea name="data" class="form-control" rows="4" required placeholder='[{"name":"gpt-4o","type":"chat","input_price":0.0025,"output_price":0.01}]'></textarea></div>
        <button type="submit" class="btn">保存</button>
    </form>
</div>
<div class="card">
    <div class="card-title">预填充分组列表</div>
    <table class="table">
        <thead><tr><th>ID</th><th>名称</th><th>类型</th><th>数据项数</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($groups as $g) : $data = json_decode($g['data'], true); ?>
            <tr><td><?php echo $g['id']; ?></td><td><?php echo e($g['name']); ?></td><td><span class="badge badge-blue"><?php echo e($g['type']); ?></span></td>
                <td><?php echo is_array($data) ? count($data) : 0; ?></td>
                <td><form method="post" style="display:inline;" data-confirm-title="删除" data-confirm-msg="确定删除？" data-confirm-ok="删除">
                    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="id" value="<?php echo $g['id']; ?>">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn btn-sm btn-danger">删除</button>
                </form></td>
            </tr>
        <?php endforeach; if (empty($groups)): ?><tr><td colspan="5" class="text-center text-muted">暂无数据</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>