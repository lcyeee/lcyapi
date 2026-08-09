<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '使用日志';

/* 日志清理：删除 N 天前的调用记录 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cleanup') {
    if (!csrf_verify()) {
        session_flash('flash_error', '表单已过期，请重试');
        redirect(base_url('admin/logs/index.php'));
    }
    $days = max(1, (int)($_POST['days'] ?? 30));
    $stmt = DB::query('DELETE FROM logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$days]);
    $n = $stmt->rowCount();
    session_flash('flash_success', "已清理 {$n} 条 {$days} 天前的调用记录");
    audit_log('log_cleanup', null, "保留天数={$days} 清理数量={$n}");
    redirect(base_url('admin/logs/index.php'));
}

$status = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;
$model = isset($_GET['model']) ? trim($_GET['model']) : '';
$userKw = isset($_GET['user']) ? trim($_GET['user']) : '';
$from = isset($_GET['from']) ? trim($_GET['from']) : '';
$to = isset($_GET['to']) ? trim($_GET['to']) : '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where = [];
$params = [];
if ($status !== null) {
    $where[] = 'l.status = ?';
    $params[] = $status;
}
if ($model !== '') {
    $where[] = 'l.model = ?';
    $params[] = $model;
}
if ($userKw !== '') {
    $where[] = 'u.username LIKE ?';
    $params[] = '%' . $userKw . '%';
}
if ($from !== '') {
    $where[] = 'l.created_at >= ?';
    $params[] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = 'l.created_at <= ?';
    $params[] = $to . ' 23:59:59';
}
$whereSql = empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);
$join = ' FROM logs l LEFT JOIN users u ON u.id = l.user_id LEFT JOIN channels c ON c.id = l.channel_id' . $whereSql;

/* 导出 CSV（当前筛选条件全量） */
if (isset($_GET['export'])) {
    $rows = DB::fetchAll('SELECT l.*, u.username, c.name AS channel_name' . $join . ' ORDER BY l.id DESC', $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="logs-' . date('Ymd-His') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', '用户', '渠道', '模型', '类型', '提示 Tokens', '输出 Tokens', '总 Tokens', '费用(USD)', '耗时(ms)', '状态', '错误', '时间']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['id'],
            $row['username'],
            $row['channel_name'],
            $row['model'],
            $row['type'],
            (int)$row['prompt_tokens'],
            (int)$row['completion_tokens'],
            (int)$row['total_tokens'],
            (float)$row['cost'],
            (int)$row['duration'],
            $row['status'] ? '成功' : '失败',
            $row['error_msg'],
            $row['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

$total = (int)DB::value('SELECT COUNT(*)' . $join, $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$logs = DB::fetchAll('SELECT l.*, u.username, c.name AS channel_name' . $join . ' ORDER BY l.id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)(($page - 1) * $perPage), $params);
$models = DB::fetchAll('SELECT DISTINCT model FROM logs ORDER BY model');
?>
<?php require dirname(__DIR__) . '/templates/header.php'; ?>

<div class="card">
    <form method="get" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
        <div class="form-group" style="margin:0;">
            <label>状态</label>
            <select name="status" class="form-control" style="width:110px;">
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
        <div class="form-group" style="margin:0;">
            <label>用户名</label>
            <input type="text" name="user" class="form-control" style="width:140px;" value="<?php echo e($userKw); ?>">
        </div>
        <div class="form-group" style="margin:0;">
            <label>开始日期</label>
            <input type="date" name="from" class="form-control" style="width:150px;" value="<?php echo e($from); ?>">
        </div>
        <div class="form-group" style="margin:0;">
            <label>结束日期</label>
            <input type="date" name="to" class="form-control" style="width:150px;" value="<?php echo e($to); ?>">
        </div>
        <button type="submit" class="btn"><?php echo svg_icon('search'); ?>筛选</button>
        <a class="btn btn-secondary" href="<?php echo base_url('admin/logs/index.php'); ?>"><?php echo svg_icon('refresh'); ?>重置</a>
        <a class="btn btn-secondary" href="<?php echo base_url('admin/logs/index.php?export=1' . ($status !== null ? '&status=' . $status : '') . ($model !== '' ? '&model=' . urlencode($model) : '') . ($userKw !== '' ? '&user=' . urlencode($userKw) : '') . ($from !== '' ? '&from=' . $from : '') . ($to !== '' ? '&to=' . $to : '')); ?>"><?php echo svg_icon('download'); ?>导出 CSV</a>
    </form>
</div>

<div class="card">
    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <span>调用记录（共 <?php echo $total; ?> 条）</span>
        <form method="post" style="display:flex; gap:8px; align-items:center;" data-confirm-title="清理历史日志" data-confirm-msg="将删除指定天数之前的所有调用记录，此操作不可恢复，确定继续？" data-confirm-ok="清理">
            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="cleanup">
            <span class="text-muted" style="font-size:12.5px; font-weight:normal;">保留最近</span>
            <input type="number" name="days" min="1" max="3650" class="form-control" style="width:80px; height:32px;" value="30">
            <span class="text-muted" style="font-size:12.5px; font-weight:normal;">天</span>
            <button type="submit" class="btn btn-sm btn-secondary"><?php echo svg_icon('trash'); ?>清理</button>
        </form>
    </div>
    <table class="table">
        <thead>
            <tr><th>ID</th><th>用户</th><th>令牌</th><th>渠道</th><th>模型</th><th>Tokens</th>
                <th>费用</th><th>耗时</th><th>状态</th><th>IP</th><th>时间</th></tr>
        </thead>
        <tbody>
        <?php if (empty($logs)) : ?>
            <tr><td colspan="11" class="text-center text-muted">暂无记录</td></tr>
        <?php endif; ?>
        <?php foreach ($logs as $log) : ?>
            <tr>
                <td><?php echo $log['id']; ?></td>
                <td><?php echo e($log['username'] ?: ('#' . $log['user_id'])); ?></td>
                <td>#<?php echo $log['token_id']; ?></td>
                <td><?php echo e($log['channel_name'] ?: ('#' . $log['channel_id'])); ?></td>
                <td><?php echo e($log['model']); ?></td>
                <td><?php echo number_format((int)$log['total_tokens']); ?>（<?php echo (int)$log['prompt_tokens']; ?>/<?php echo (int)$log['completion_tokens']; ?>）</td>
                <td>$<?php echo e(number_format((float)$log['cost'], 6)); ?></td>
                <td><?php echo format_elapsed($log['duration']); ?></td>
                <td><?php echo $log['status'] ? '<span class="badge badge-green">成功</span>' : '<span class="badge badge-red">失败</span>'; ?></td>
                <td><?php echo e($log['ip']); ?></td>
                <td><?php echo e($log['created_at']); ?></td>
            </tr>
            <?php if (!$log['status'] && !empty($log['error_msg'])) : ?>
                <tr><td colspan="11" style="font-size:12px; padding-top:0;"><span class="detail-clickable" style="color:var(--red-text); display:inline-block; max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; vertical-align:bottom;" data-modal-detail="<?php echo e($log['error_msg']); ?>" data-modal-detail-title="错误详情 #<?php echo $log['id']; ?>">错误：<?php echo e($log['error_msg']); ?></span></td></tr>
            <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($pages > 1) : ?>
        <div class="pagination">
            <?php
            $qs = http_build_query(array_filter(['status' => $status, 'model' => $model, 'user' => $userKw, 'from' => $from, 'to' => $to]));
            for ($i = 1; $i <= $pages; $i++) :
                $href = '?page=' . $i . ($qs !== '' ? '&' . $qs : '');
            ?>
                <a class="<?php echo $i === $page ? 'current' : ''; ?>" href="<?php echo $href; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>