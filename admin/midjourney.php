<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '绘图日志（Midjourney）';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$userId = isset($_GET['user_id']) ? trim($_GET['user_id']) : '';
$where = [];
$params = [];
if ($status !== '') {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($userId !== '') {
    $where[] = 'user_id = ?';
    $params[] = (int)$userId;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$total = (int)DB::value('SELECT COUNT(*) FROM midjourneys' . $whereSql, $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$rows = DB::fetchAll('SELECT * FROM midjourneys' . $whereSql . ' ORDER BY id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)(($page - 1) * $perPage), $params);
$statuses = ['SUBMITTED', 'QUEUED', 'IN_PROGRESS', 'SUCCESS', 'FAILURE'];
?>
<?php require __DIR__ . '/templates/header.php'; ?>

<div class="card">
    <form method="get" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
        <div class="form-group" style="margin:0;">
            <label>状态</label>
            <select name="status" class="form-control" style="width:160px;">
                <option value="">全部</option>
                <?php foreach ($statuses as $st) : ?>
                    <option value="<?php echo e($st); ?>" <?php echo $status === $st ? 'selected' : ''; ?>><?php echo e($st); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>用户 ID</label>
            <input type="number" name="user_id" class="form-control" style="width:140px;" value="<?php echo e($userId); ?>" min="1">
        </div>
        <button type="submit" class="btn"><?php echo svg_icon('search'); ?>筛选</button>
        <a class="btn btn-secondary" href="<?php echo base_url('admin/midjourney.php'); ?>"><?php echo svg_icon('refresh'); ?>重置</a>
    </form>
</div>

<div class="card">
    <div class="card-title">绘图任务（共 <?php echo $total; ?> 条）</div>
    <table class="table">
        <thead>
            <tr><th>ID</th><th>用户</th><th>操作</th><th>提示词</th><th>状态</th><th>进度</th><th>图片</th><th>费用</th><th>提交时间</th><th>完成时间</th></tr>
        </thead>
        <tbody>
        <?php if (empty($rows)) : ?>
            <tr><td colspan="10" class="text-center text-muted">暂无绘图任务</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row) : ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><a href="<?php echo base_url('admin/users/index.php?q=' . urlencode($row['user_id'])); ?>">#<?php echo $row['user_id']; ?></a></td>
                <td><span class="badge"><?php echo e($row['action']); ?></span></td>
                <td style="max-width:300px;"><div class="detail-clickable" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" data-modal-detail="<?php echo e($row['prompt']); ?>" data-modal-detail-title="提示词 #<?php echo $row['id']; ?>"><?php echo e($row['prompt']); ?></div></td>
                <td>
                    <?php
                    $badgeClass = 'badge';
                    if ($row['status'] === 'SUCCESS') { $badgeClass = 'badge badge-green'; }
                    elseif ($row['status'] === 'FAILURE') { $badgeClass = 'badge badge-red'; }
                    ?>
                    <span class="<?php echo $badgeClass; ?>"><?php echo e($row['status']); ?></span>
                </td>
                <td><?php echo e($row['progress'] ?: '-'); ?></td>
                <td>
                    <?php if (!empty($row['image_url'])) : ?>
                        <a href="<?php echo e($row['image_url']); ?>" target="_blank" rel="noopener">查看</a>
                    <?php else : ?>
                        -
                    <?php endif; ?>
                </td>
                <td><?php echo Billing::formatCost($row['quota']); ?></td>
                <td><?php echo $row['submit_time'] ? date('Y-m-d H:i:s', (int)$row['submit_time']) : '-'; ?></td>
                <td><?php echo $row['finish_time'] ? date('Y-m-d H:i:s', (int)$row['finish_time']) : '-'; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($pages > 1) : ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $pages; $i++) : ?>
                <a class="<?php echo $i === $page ? 'current' : ''; ?>" href="?page=<?php echo $i; ?><?php echo $status !== '' ? '&status=' . urlencode($status) : ''; ?><?php echo $userId !== '' ? '&user_id=' . urlencode($userId) : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
