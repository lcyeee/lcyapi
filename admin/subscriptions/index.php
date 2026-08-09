<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = '页面已过期，请重试';
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        if ($action === 'save') {
            $name = mb_substr(trim($_POST['name'] ?? ''), 0, 50);
            $description = mb_substr(trim($_POST['description'] ?? ''), 0, 255);
            $quota = max(0, (float)($_POST['quota'] ?? 0));
            $price = max(0, (float)($_POST['price'] ?? 0));
            $days = max(1, (int)($_POST['days'] ?? 30));
            $status = empty($_POST['status']) ? 0 : 1;
            $sort = (int)($_POST['sort'] ?? 0);
            if ($name === '') {
                $error = '套餐名称不能为空';
            } else {
                if ($id > 0) {
                    DB::update('subscription_plans', compact('name', 'description', 'quota', 'price', 'days', 'status', 'sort'), 'id = ?', [$id]);
                    audit_log('plan_update', "#{$id} " . $name);
                    session_flash('flash_success', '套餐已更新');
                } else {
                    DB::insert('subscription_plans', compact('name', 'description', 'quota', 'price', 'days', 'status', 'sort'));
                    audit_log('plan_create', $name);
                    session_flash('flash_success', '套餐已创建');
                }
                redirect(base_url('admin/subscriptions/index.php'));
            }
        } elseif ($action === 'toggle') {
            if ($id > 0) {
                $plan = DB::fetch('SELECT status FROM subscription_plans WHERE id = ?', [$id]);
                if ($plan !== false) {
                    DB::update('subscription_plans', ['status' => (int)$plan['status'] === 1 ? 0 : 1], 'id = ?', [$id]);
                    audit_log('plan_toggle', "#{$id}");
                }
            }
        } elseif ($action === 'delete') {
            if ($id > 0) {
                DB::delete('subscription_plans', 'id = ?', [$id]);
                audit_log('plan_delete', "#{$id}");
            }
        } elseif ($action === 'expire_sub') {
            DB::update('user_subscriptions', ['status' => 0], 'id = ? AND user_id > 0', [$id]);
            session_flash('flash_success', '订阅已标记过期');
        }
        if ($action !== 'save') {
            redirect(base_url('admin/subscriptions/index.php'));
        }
    }
}

$plans = DB::fetchAll('SELECT * FROM subscription_plans ORDER BY sort ASC, id ASC');
$subs = DB::fetchAll('SELECT us.*, p.name AS plan_name, u.username FROM user_subscriptions us LEFT JOIN subscription_plans p ON p.id = us.plan_id LEFT JOIN users u ON u.id = us.user_id ORDER BY us.id DESC LIMIT 100');
$editPlan = false;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($plans as $p) {
        if ((int)$p['id'] === $editId) {
            $editPlan = $p;
            break;
        }
    }
}
$pageTitle = '订阅套餐';
?>
<?php require dirname(__DIR__) . '/templates/header.php'; ?>

<?php if ($error !== '') : ?>
    <div class="alert alert-danger"><?php echo e($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-title">套餐列表（<?php echo count($plans); ?> 个）</div>
    <table class="table">
        <thead><tr><th>ID</th><th>名称</th><th>说明</th><th>周期额度</th><th>价格</th><th>有效期</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
        <?php if (empty($plans)) : ?>
            <tr><td colspan="8" class="text-center text-muted">暂无套餐，请在右侧新建</td></tr>
        <?php endif; ?>
        <?php foreach ($plans as $plan) : ?>
            <tr>
                <td><?php echo $plan['id']; ?></td>
                <td><?php echo e($plan['name']); ?></td>
                <td><?php echo e($plan['description'] ?: '-'); ?></td>
                <td>$<?php echo e(number_format((float)$plan['quota'], 4)); ?></td>
                <td>$<?php echo e(number_format((float)$plan['price'], 2)); ?></td>
                <td><?php echo $plan['days']; ?> 天</td>
                <td><?php echo $plan['status'] ? '<span class="badge badge-green">上架</span>' : '<span class="badge badge-gray">下架</span>'; ?></td>
                <td style="white-space:nowrap;">
                    <a class="btn btn-sm" href="<?php echo base_url('admin/subscriptions/index.php?edit=' . $plan['id']); ?>">编辑</a>
                    <form method="post" style="display:inline-block; margin-right:4px;">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?php echo $plan['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-warning"><?php echo $plan['status'] ? '下架' : '上架'; ?></button>
                    </form>
                    <form method="post" style="display:inline-block;" data-confirm-title="删除套餐" data-confirm-msg="删除后不可恢复，已购买用户的订阅记录保留。" data-confirm-ok="删除">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $plan['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <div class="card-title"><?php echo $editPlan !== false ? '编辑套餐：' . e($editPlan['name']) : '新建套餐'; ?></div>
    <form method="post" action="<?php echo base_url('admin/subscriptions/index.php'); ?>" style="max-width:520px;">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?php echo $editPlan !== false ? (int)$editPlan['id'] : 0; ?>">
        <div class="form-group">
            <label>套餐名称</label>
            <input type="text" name="name" class="form-control" required value="<?php echo e($editPlan !== false ? $editPlan['name'] : ''); ?>">
        </div>
        <div class="form-group">
            <label>说明</label>
            <input type="text" name="description" class="form-control" value="<?php echo e($editPlan !== false ? $editPlan['description'] : ''); ?>">
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;">
                <label>周期额度（$）</label>
                <input type="number" name="quota" step="0.0001" min="0" class="form-control" value="<?php echo e($editPlan !== false ? $editPlan['quota'] : '0'); ?>">
            </div>
            <div style="flex:1;">
                <label>价格（$）</label>
                <input type="number" name="price" step="0.01" min="0" class="form-control" value="<?php echo e($editPlan !== false ? $editPlan['price'] : '0'); ?>">
            </div>
            <div style="flex:1;">
                <label>有效期（天）</label>
                <input type="number" name="days" min="1" class="form-control" value="<?php echo e($editPlan !== false ? $editPlan['days'] : '30'); ?>">
            </div>
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;">
                <label>排序（小在前）</label>
                <input type="number" name="sort" class="form-control" value="<?php echo e($editPlan !== false ? $editPlan['sort'] : '0'); ?>">
            </div>
            <div style="flex:1; display:flex; align-items:center; gap:8px;">
                <label class="ios-switch"><input type="checkbox" name="status" value="1" <?php echo $editPlan === false || (int)$editPlan['status'] === 1 ? 'checked' : ''; ?>><span></span></label>
                <span>上架</span>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">保存</button>
            <?php if ($editPlan !== false) : ?>
                <a class="btn btn-secondary" href="<?php echo base_url('admin/subscriptions/index.php'); ?>">取消编辑</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-title">用户订阅（<?php echo count($subs); ?> 条，最近 100）</div>
    <table class="table">
        <thead><tr><th>ID</th><th>用户</th><th>套餐</th><th>开始</th><th>到期</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
        <?php if (empty($subs)) : ?>
            <tr><td colspan="7" class="text-center text-muted">暂无用户订阅</td></tr>
        <?php endif; ?>
        <?php foreach ($subs as $sub) : ?>
            <tr>
                <td><?php echo $sub['id']; ?></td>
                <td><?php echo e($sub['username'] ?: '#' . $sub['user_id']); ?></td>
                <td><?php echo e($sub['plan_name'] ?: '#' . $sub['plan_id']); ?></td>
                <td><?php echo e($sub['start_at']); ?></td>
                <td><?php echo e($sub['end_at']); ?></td>
                <td><?php echo $sub['status'] ? '<span class="badge badge-green">有效</span>' : '<span class="badge badge-gray">已过期</span>'; ?></td>
                <td>
                    <?php if ($sub['status']) : ?>
                        <form method="post" style="display:inline-block;">
                            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="action" value="expire_sub">
                            <input type="hidden" name="id" value="<?php echo $sub['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-warning">标记过期</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
