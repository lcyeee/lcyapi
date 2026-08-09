<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '登录日志';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        session_flash('flash_error', '页面已过期，请重试');
        redirect(base_url('admin/login-logs/index.php'));
    }
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'delete') {
        DB::delete('login_logs', 'id = ?', [$id]);
        session_flash('flash_success', '登录记录已删除');
        audit_log('login_log_delete', "#{$id}");
    } elseif ($action === 'cleanup') {
        $days = max(1, min(3650, (int)($_POST['days'] ?? 30)));
        DB::query('DELETE FROM login_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$days]);
        session_flash('flash_success', '已清理 ' . $days . ' 天前的登录记录');
        audit_log('login_log_cleanup', null, "days={$days}");
    }
    redirect(base_url('admin/login-logs/index.php'));
}

$status = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;
$userKw = trim($_GET['user'] ?? '');
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where = [];
$params = [];
if ($status !== null) {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($userKw !== '') {
    $where[] = 'username LIKE ?';
    $params[] = '%' . $userKw . '%';
}
if ($from !== '') {
    $where[] = 'created_at >= ?';
    $params[] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = 'created_at <= ?';
    $params[] = $to . ' 23:59:59';
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$total = (int)DB::value('SELECT COUNT(*) FROM login_logs' . $whereSql, $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$logs = DB::fetchAll('SELECT * FROM login_logs' . $whereSql . ' ORDER BY id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)(($page - 1) * $perPage), $params);
?>
<?php require dirname(__DIR__) . '/templates/header.php'; ?>

<form method="get" style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; margin-bottom:14px;">
    <div class="form-group" style="margin:0;">
        <label>状态</label>
        <select name="status" class="form-control" style="width:110px;">
            <option value="">全部</option>
            <option value="1" <?php echo $status === 1 ? 'selected' : ''; ?>>成功</option>
            <option value="0" <?php echo $status === 0 ? 'selected' : ''; ?>>失败</option>
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
    <?php if ($status !== null || $userKw !== '' || $from !== '' || $to !== '') : ?>
        <a class="btn btn-secondary" href="<?php echo base_url('admin/login-logs/index.php'); ?>">清空</a>
    <?php endif; ?>
</form>

<form method="post" class="card" style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap;">
    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
    <input type="hidden" name="action" value="cleanup">
    <div class="form-group" style="margin:0;">
        <label>清理 N 天前的记录</label>
        <input type="number" name="days" min="1" max="3650" class="form-control" style="width:80px;" value="30">
    </div>
    <button type="submit" class="btn btn-sm btn-secondary"><?php echo svg_icon('trash'); ?>清理</button>
</form>

<div class="card">
    <div class="card-title">登录记录（共 <?php echo $total; ?> 条）</div>
    <table class="table">
        <thead>
            <tr><th>ID</th><th>时间</th><th>用户名</th><th>IP</th><th>UA</th><th>状态</th><th>原因</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php if (empty($logs)) : ?>
            <tr><td colspan="8" class="text-center text-muted">暂无登录记录</td></tr>
        <?php endif; ?>
        <?php foreach ($logs as $log) : ?>
            <tr>
                <td><?php echo $log['id']; ?></td>
                <td><?php echo e($log['created_at']); ?></td>
                <td><?php echo e($log['username']); ?></td>
                <td><?php echo e($log['ip'] ?: '-'); ?></td>
                <td style="max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo e($log['user_agent']); ?>"><?php echo e($log['user_agent'] ?: '-'); ?></td>
                <td><?php echo $log['status'] ? '<span class="badge badge-green">成功</span>' : '<span class="badge badge-red">失败</span>'; ?></td>
                <td><?php echo e($log['reason'] ?: '-'); ?></td>
                <td>
                    <form method="post" style="display:inline-block;" data-confirm-title="删除记录" data-confirm-msg="确定删除该登录记录？" data-confirm-ok="删除">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $log['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($pages > 1) : ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $pages; $i++) : ?>
                <a class="<?php echo $i === $page ? 'current' : ''; ?>" href="?page=<?php echo $i; ?>&status=<?php echo $status === null ? '' : $status; ?>&user=<?php echo e($userKw); ?>&from=<?php echo e($from); ?>&to=<?php echo e($to); ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>