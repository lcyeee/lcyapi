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
    if ($action === 'toggle') {
        $channel = Channel::getById($id);
        if ($channel !== false) {
            Channel::update($id, ['status' => $channel['status'] ? 0 : 1]);
            session_flash('flash_success', '渠道状态已更新');
        }
    } elseif ($action === 'delete') {
        if (Channel::delete($id)) {
            session_flash('flash_success', '渠道已删除');
        } else {
            session_flash('flash_error', '删除失败');
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

$channels = Channel::all();
?>
<?php require dirname(__DIR__) . '/templates/header.php'; ?>

<div style="display:flex; justify-content:flex-end; margin-bottom:14px;">
    <a class="btn" href="<?php echo base_url('admin/channels/edit.php'); ?>">+ 新建渠道</a>
</div>

<table class="table">
    <thead>
        <tr>
            <th>ID</th><th>名称</th><th>类型</th><th>地址</th><th>模型</th><th>权重</th>
            <th>优先级</th><th>状态</th><th>成功/失败</th><th>最后使用</th><th>操作</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($channels)) : ?>
        <tr><td colspan="11" class="text-center text-muted">暂无渠道，点击右上角创建</td></tr>
    <?php endif; ?>
    <?php foreach ($channels as $channel) : ?>
        <tr>
            <td><?php echo $channel['id']; ?></td>
            <td><?php echo e($channel['name']); ?></td>
            <td><span class="badge badge-blue"><?php echo e($channel['type']); ?></span></td>
            <td style="max-width:220px; word-break:break-all;"><?php echo e($channel['base_url']); ?></td>
            <td><?php echo e($channel['models'] ?: '全部'); ?></td>
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
                    <button type="submit" name="action" value="toggle" class="btn btn-sm btn-warning"><?php echo $channel['status'] ? '停用' : '启用'; ?></button>
                </form>
                <form method="post" style="display:inline-block;" onsubmit="return confirm('确定删除该渠道？');">
                    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="id" value="<?php echo $channel['id']; ?>">
                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger">删除</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php require dirname(__DIR__) . '/templates/footer.php'; ?>