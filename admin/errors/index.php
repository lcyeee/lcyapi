<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '错误日志';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$where = '';
$params = [];
if ($type !== '') {
    $where = ' WHERE type = ?';
    $params = [$type];
}
$total = (int)DB::value('SELECT COUNT(*) FROM error_logs' . $where, $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$logs = DB::fetchAll('SELECT * FROM error_logs' . $where . ' ORDER BY id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)(($page - 1) * $perPage), $params);
$types = DB::fetchAll('SELECT DISTINCT type FROM error_logs ORDER BY type');
?>
<?php require dirname(__DIR__) . '/templates/header.php'; ?>

<div class="card">
    <form method="get" style="display:flex; gap:10px; align-items:flex-end;">
        <div class="form-group" style="margin:0;">
            <label>类型</label>
            <select name="type" class="form-control" style="width:180px;">
                <option value="">全部</option>
                <?php foreach ($types as $t) : ?>
                    <option value="<?php echo e($t['type']); ?>" <?php echo $type === $t['type'] ? 'selected' : ''; ?>><?php echo e($t['type']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn">筛选</button>
        <a class="btn btn-secondary" href="<?php echo base_url('admin/errors/index.php'); ?>">重置</a>
    </form>
</div>

<div class="card">
    <div class="card-title">错误记录（共 <?php echo $total; ?> 条）</div>
    <table class="table">
        <thead>
            <tr><th>ID</th><th>类型</th><th>用户</th><th>渠道</th><th>模型</th><th>消息</th><th>时间</th></tr>
        </thead>
        <tbody>
        <?php if (empty($logs)) : ?>
            <tr><td colspan="7" class="text-center text-muted">暂无错误记录</td></tr>
        <?php endif; ?>
        <?php foreach ($logs as $log) : ?>
            <tr>
                <td><?php echo $log['id']; ?></td>
                <td><span class="badge badge-red"><?php echo e($log['type'] ?: '-'); ?></span></td>
                <td><?php echo $log['user_id'] ? ('#' . $log['user_id']) : '-'; ?></td>
                <td><?php echo $log['channel_id'] ? ('#' . $log['channel_id']) : '-'; ?></td>
                <td><?php echo e($log['model'] ?: '-'); ?></td>
                <td style="max-width:380px;"><div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo e($log['message']); ?>"><?php echo e($log['message']); ?></div></td>
                <td><?php echo e($log['created_at']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($pages > 1) : ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $pages; $i++) : ?>
                <a class="<?php echo $i === $page ? 'current' : ''; ?>" href="?page=<?php echo $i; ?><?php echo $type !== '' ? '&type=' . urlencode($type) : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>