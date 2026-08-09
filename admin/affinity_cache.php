<?php
/**
 * Channels Affinity 缓存管理
 */
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '渠道亲和性缓存';

if (isset($_POST['clear']) && csrf_verify()) {
    DB::delete('channel_affinity', '1=1', []);
    session_flash('flash_success', '亲和性缓存已清空');
    redirect(base_url('admin/affinity_cache.php'));
}

$total = (int)DB::value('SELECT COUNT(*) FROM channel_affinity');
$stats = DB::fetchAll('SELECT ca.channel_id, c.name AS channel_name, COUNT(*) AS n, MAX(ca.pinned_at) AS last_pin FROM channel_affinity ca LEFT JOIN channels c ON c.id=ca.channel_id GROUP BY ca.channel_id ORDER BY n DESC LIMIT 20');
require dirname(__DIR__) . '/templates/header.php';
?>
<div class="card">
    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <span><?php echo svg_icon('server'); ?>渠道亲和性缓存（共 <?php echo $total; ?> 条记录）</span>
        <form method="post" style="display:inline;" data-confirm-title="清空缓存" data-confirm-msg="确定清空所有渠道亲和性缓存？" data-confirm-ok="清空">
            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="clear" value="1">
            <button type="submit" class="btn btn-sm btn-danger">清空缓存</button>
        </form>
    </div>
    <table class="table">
        <thead><tr><th>渠道 ID</th><th>渠道名称</th><th>绑定数</th><th>最后绑定</th></tr></thead>
        <tbody>
        <?php foreach ($stats as $s) : ?>
            <tr><td><?php echo $s['channel_id']; ?></td><td><?php echo e($s['channel_name'] ?: ('#' . $s['channel_id'])); ?></td><td><?php echo $s['n']; ?></td><td><?php echo e($s['last_pin']); ?></td></tr>
        <?php endforeach; if (empty($stats)): ?><tr><td colspan="4" class="text-center text-muted">暂无缓存数据</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>