<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '兑换码管理';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        session_flash('flash_error', '表单已过期，请重试');
        redirect(base_url('admin/codes/index.php'));
    }
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'generate') {
        $count = min(500, max(1, (int)($_POST['count'] ?? 1)));
        $quota = (float)($_POST['quota'] ?? 0);
        $remark = mb_substr(trim($_POST['remark'] ?? ''), 0, 255);
        $batch = 'B' . date('YmdHis');
        if ($quota <= 0) {
            session_flash('flash_error', '兑换额度必须大于 0');
            redirect(base_url('admin/codes/index.php'));
        }
        $created = 0;
        $tries = 0;
        while ($created < $count && $tries < $count * 20) {
            $tries++;
            $code = strtoupper(substr(bin2hex(random_bytes(8)), 0, 8) . '-' . substr(bin2hex(random_bytes(4)), 0, 4) . '-' . substr(bin2hex(random_bytes(4)), 0, 4));
            try {
                DB::insert('redemptions', ['code' => $code, 'quota' => $quota, 'status' => 1, 'batch' => $batch, 'remark' => $remark]);
                $created++;
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate') === false) {
                    break;
                }
            }
        }
        session_flash('flash_success', "已生成 {$created} 个兑换码（批次 {$batch}）");
        redirect(base_url('admin/codes/index.php?batch=' . urlencode($batch)));
    } elseif ($action === 'disable') {
        $id = (int)($_POST['id'] ?? 0);
        DB::update('redemptions', ['status' => 0], 'id = ?', [$id]);
        session_flash('flash_success', '兑换码已停用');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        DB::delete('redemptions', 'id = ?', [$id]);
        session_flash('flash_success', '兑换码已删除');
    }
    redirect(base_url('admin/codes/index.php'));
}

$batch = isset($_GET['batch']) ? trim($_GET['batch']) : '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$where = '';
$params = [];
if ($batch !== '') {
    $where = ' WHERE batch = ?';
    $params = [$batch];
}
$total = (int)DB::value('SELECT COUNT(*) FROM redemptions' . $where, $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$codes = DB::fetchAll('SELECT r.*, u.username AS used_by_name FROM redemptions r LEFT JOIN users u ON u.id = r.used_by' . $where . ' ORDER BY r.id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)(($page - 1) * $perPage), $params);
?>
<?php require dirname(__DIR__) . '/templates/header.php'; ?>

<div class="card">
    <div class="card-title">生成兑换码</div>
    <form method="post" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="action" value="generate">
        <div class="form-group" style="margin:0;">
            <label>生成数量</label>
            <input type="number" name="count" min="1" max="500" class="form-control" style="width:110px;" value="10">
        </div>
        <div class="form-group" style="margin:0;">
            <label>每个额度（$）</label>
            <input type="number" name="quota" step="0.0001" min="0.0001" class="form-control" style="width:140px;" value="1">
        </div>
        <div class="form-group" style="margin:0;">
            <label>备注</label>
            <input type="text" name="remark" class="form-control" style="width:220px;" placeholder="如：活动赠送">
        </div>
        <button type="submit" class="btn">生成</button>
    </form>
</div>

<div class="card">
    <div class="card-title">兑换码列表（共 <?php echo $total; ?> 个<?php echo $batch !== '' ? '，批次 ' . e($batch) : ''; ?>）</div>
    <table class="table">
        <thead>
            <tr><th>ID</th><th>兑换码</th><th>额度</th><th>状态</th><th>使用者</th><th>使用时间</th><th>批次</th><th>备注</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php if (empty($codes)) : ?>
            <tr><td colspan="9" class="text-center text-muted">暂无兑换码</td></tr>
        <?php endif; ?>
        <?php foreach ($codes as $code) : ?>
            <tr>
                <td><?php echo $code['id']; ?></td>
                <td><code><?php echo e($code['code']); ?></code></td>
                <td>$<?php echo e(number_format((float)$code['quota'], 4)); ?></td>
                <td>
                    <?php if ($code['status'] == 1) : ?>
                        <?php if ($code['used_at']) : ?><span class="badge badge-blue">已使用</span>
                        <?php else : ?><span class="badge badge-green">可用</span><?php endif; ?>
                    <?php else : ?><span class="badge badge-gray">停用</span><?php endif; ?>
                </td>
                <td><?php echo e($code['used_by_name'] ?: ($code['used_by'] ? '#' . $code['used_by'] : '-')); ?></td>
                <td><?php echo e($code['used_at'] ?: '-'); ?></td>
                <td><?php echo e($code['batch'] ?: '-'); ?></td>
                <td><?php echo e($code['remark'] ?: '-'); ?></td>
                <td style="white-space:nowrap;">
                    <?php if ($code['status'] == 1 && !$code['used_at']) : ?>
                        <form method="post" style="display:inline-block; margin-right:4px;">
                            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="id" value="<?php echo $code['id']; ?>">
                            <button type="submit" name="action" value="disable" class="btn btn-sm btn-warning">停用</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" style="display:inline-block;" onsubmit="return confirm('确定删除？');">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="id" value="<?php echo $code['id']; ?>">
                        <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger">删除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($pages > 1) : ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $pages; $i++) : ?>
                <a class="<?php echo $i === $page ? 'current' : ''; ?>" href="?page=<?php echo $i; ?><?php echo $batch !== '' ? '&batch=' . urlencode($batch) : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>