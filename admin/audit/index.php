<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '操作审计';

$kw = isset($_GET['kw']) ? trim($_GET['kw']) : '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where = '';
$params = [];
if ($kw !== '') {
    $where = ' WHERE a.action LIKE ? OR a.target LIKE ? OR a.detail LIKE ? OR u.username LIKE ?';
    $like = '%' . $kw . '%';
    $params = [$like, $like, $like, $like];
}
$total = (int)DB::value('SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id = a.admin_id' . $where, $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$rows = DB::fetchAll('SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON u.id = a.admin_id' . $where . ' ORDER BY a.id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)(($page - 1) * $perPage), $params);

/* 操作类型的中文标签 */
$actionLabels = [
    'settings_save' => '保存设置',
    'channel_create' => '新建渠道',
    'channel_update' => '编辑渠道',
    'channel_delete' => '删除渠道',
    'channel_copy' => '复制渠道',
    'channel_toggle' => '渠道启停',
    'channel_batch_enable' => '批量启用渠道',
    'channel_batch_disable' => '批量停用渠道',
    'channel_test' => '测试渠道',
    'user_toggle' => '用户启停',
    'user_promote' => '角色变更',
    'user_quota' => '调整额度',
    'user_reset_pass' => '重置密码',
    'user_delete' => '删除用户',
    'model_save' => '模型定价',
    'model_delete' => '删除模型',
    'token_create' => '新建令牌',
    'token_update' => '编辑令牌',
    'token_delete' => '删除令牌',
    'token_toggle' => '令牌启停',
    'token_quota' => '令牌额度',
    'model_toggle' => '模型启停',
    'code_generate' => '生成兑换码',
    'code_disable' => '停用兑换码',
    'code_delete' => '删除兑换码',
    'code_cleanup' => '清理兑换码',
    'log_cleanup' => '清理日志',
];
?>
<?php require dirname(__DIR__) . '/templates/header.php'; ?>

<div class="card">
    <form method="get" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
        <div class="form-group" style="margin:0;">
            <label>关键词（操作/对象/详情/管理员）</label>
            <input type="text" name="kw" class="form-control" style="width:260px;" value="<?php echo e($kw); ?>" placeholder="如：channel_delete">
        </div>
        <button type="submit" class="btn"><?php echo svg_icon('search'); ?>筛选</button>
        <a class="btn btn-secondary" href="<?php echo base_url('admin/audit/index.php'); ?>"><?php echo svg_icon('refresh'); ?>重置</a>
    </form>
</div>

<div class="card">
    <div class="card-title">审计记录（共 <?php echo $total; ?> 条）</div>
    <table class="table">
        <thead>
            <tr><th>ID</th><th>时间</th><th>管理员</th><th>操作</th><th>对象</th><th>详情</th><th>IP</th></tr>
        </thead>
        <tbody>
        <?php if (empty($rows)) : ?>
            <tr><td colspan="7" class="text-center text-muted">暂无审计记录</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row) : ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo e($row['created_at']); ?></td>
                <td><?php echo e($row['username'] ?: ($row['admin_id'] ? '#' . $row['admin_id'] : '系统')); ?></td>
                <td>
                    <span class="badge badge-blue"><?php echo e($row['action']); ?></span>
                    <?php if (isset($actionLabels[$row['action']])) : ?>
                        <span class="text-muted" style="font-size:12px; margin-left:4px;"><?php echo e($actionLabels[$row['action']]); ?></span>
                    <?php endif; ?>
                </td>
                <td><?php echo e($row['target'] ?: '-'); ?></td>
                <td>
                    <?php if (!empty($row['detail'])) : ?>
                        <span class="detail-clickable" data-modal-detail="<?php echo e($row['detail']); ?>" data-modal-detail-title="审计详情 #<?php echo $row['id']; ?>"><?php echo e(mb_substr($row['detail'], 0, 60)); ?><?php echo mb_strlen($row['detail']) > 60 ? '…' : ''; ?></span>
                    <?php else : ?>-<?php endif; ?>
                </td>
                <td><?php echo e($row['ip'] ?: '-'); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($pages > 1) : ?>
        <div class="pagination">
            <?php
            $qs = http_build_query(array_filter(['kw' => $kw]));
            for ($i = 1; $i <= $pages; $i++) :
                $href = '?page=' . $i . ($qs !== '' ? '&' . $qs : '');
            ?>
                <a class="<?php echo $i === $page ? 'current' : ''; ?>" href="<?php echo $href; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
