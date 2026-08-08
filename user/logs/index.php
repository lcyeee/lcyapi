<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/templates/header.php';

$status = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;
$model = isset($_GET['model']) ? trim($_GET['model']) : '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where = ['user_id = ?'];
$params = [Auth::id()];
if ($status !== null) {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($model !== '') {
    $where[] = 'model = ?';
    $params[] = $model;
}
$whereSql = ' WHERE ' . implode(' AND ', $where);
$total = (int)DB::value('SELECT COUNT(*) FROM logs' . $whereSql, $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$logs = DB::fetchAll('SELECT * FROM logs' . $whereSql . ' ORDER BY id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)(($page - 1) * $perPage), $params);
$models = DB::fetchAll('SELECT DISTINCT model FROM logs WHERE user_id = ? ORDER BY model', [Auth::id()]);
?>
<div class="card">
    <form method="get" style="display:flex; gap:10px; align-items:flex-end;">
        <div class="form-group" style="margin:0;">
            <label>状态</label>
            <select name="status" class="form-control" style="width:100px;">
                <option value="">全部</option>
                <option value="1" <?php echo $status === 1 ? 'selected' : ''; ?>>成功</option>
                <option value="0" <?php echo $status === 0 ? 'selected' : ''; ?>>失败</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>模型</label>
            <select name="model" class="form-control" style="width:180px;">
                <option value="">全部</option>
                <?php foreach ($models as $pm) : ?>
                    <option value="<?php echo e($pm['model']); ?>" <?php echo $model === $pm['model'] ? 'selected' : ''; ?>><?php echo e($pm['model']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn"><?php echo svg_icon('search'); ?>筛选</button>
        <a class="btn btn-secondary" href="<?php echo base_url('user/logs/index.php'); ?>"><?php echo svg_icon('refresh'); ?>重置</a>
    </form>
</div>

<div class="card">
    <div class="card-title">我的使用记录（共 <?php echo $total; ?> 条）</div>
    <table class="table">
        <thead>
            <tr><th>ID</th><th>模型</th><th>Tokens</th><th>费用</th><th>耗时</th><th>状态</th><th>时间</th></tr>
        </thead>
        <tbody>
        <?php if (empty($logs)) : ?>
            <tr><td colspan="7" class="text-center text-muted">暂无记录</td></tr>
        <?php endif; ?>
        <?php foreach ($logs as $log) : ?>
            <tr>
                <td><?php echo $log['id']; ?></td>
                <td><?php echo e($log['model']); ?></td>
                <td><?php echo number_format((int)$log['total_tokens']); ?>（<?php echo (int)$log['prompt_tokens']; ?>/<?php echo (int)$log['completion_tokens']; ?>）</td>
                <td>$<?php echo e(number_format((float)$log['cost'], 6)); ?></td>
                <td><?php echo format_elapsed($log['duration']); ?></td>
                <td><?php echo $log['status'] ? '<span class="badge badge-green">成功</span>' : '<span class="badge badge-red">失败</span>'; ?></td>
                <td><?php echo e($log['created_at']); ?></td>
            </tr>
            <?php if (!$log['status'] && !empty($log['error_msg'])) : ?>
                <tr><td colspan="7" style="color:var(--red-text); font-size:12px;">错误：<?php echo e($log['error_msg']); ?></td></tr>
            <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($pages > 1) : ?>
        <div class="pagination">
            <?php
            $qs = http_build_query(array_filter(['status' => $status, 'model' => $model]));
            for ($i = 1; $i <= $pages; $i++) :
                $href = '?page=' . $i . ($qs !== '' ? '&' . $qs : '');
            ?>
                <a class="<?php echo $i === $page ? 'current' : ''; ?>" href="<?php echo $href; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>