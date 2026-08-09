<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        session_flash('flash_error', '页面已过期，请重试');
        redirect(base_url('admin/system/index.php'));
    }
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'toggle_task' && $id > 0) {
        $task = DB::fetch('SELECT status FROM system_tasks WHERE id = ?', [$id]);
        if ($task !== false) {
            DB::update('system_tasks', ['status' => (int)$task['status'] === 1 ? 0 : 1], 'id = ?', [$id]);
            audit_log('system_task_toggle', "#{$id}");
            session_flash('flash_success', '任务状态已更新');
        }
    } elseif ($action === 'delete_task' && $id > 0) {
        DB::delete('system_tasks', 'id = ?', [$id]);
        audit_log('system_task_delete', "#{$id}");
        session_flash('flash_success', '任务已删除');
    } elseif ($action === 'run_task' && $id > 0) {
        $task = DB::fetch('SELECT * FROM system_tasks WHERE id = ?', [$id]);
        if ($task !== false) {
            $result = '';
            $fn = 'task_' . $task['type'];
            if (!function_exists($fn)) {
                $result = '未知任务类型';
            } else {
                try {
                    $fn($result);
                } catch (Throwable $ex) {
                    $result = '错误：' . $ex->getMessage();
                }
            }
            DB::update('system_tasks', ['last_run_at' => date('Y-m-d H:i:s'), 'last_result' => mb_substr(trim($result), 0, 500)], 'id = ?', [$id]);
            session_flash('flash_success', '已手动执行：' . trim($result));
        }
    } elseif ($action === 'save_task') {
        $name = mb_substr(trim($_POST['name'] ?? ''), 0, 50);
        $type = preg_replace('/[^a-z_]/', '', (string)($_POST['type'] ?? ''));
        $interval = max(60, (int)($_POST['interval'] ?? 3600));
        if ($name !== '' && $type !== '') {
            if ($id > 0) {
                DB::update('system_tasks', ['name' => $name, 'type' => $type, 'interval' => $interval, 'status' => 1], 'id = ?', [$id]);
                audit_log('system_task_update', "#{$id}");
                session_flash('flash_success', '任务已更新');
            } else {
                DB::insert('system_tasks', ['name' => $name, 'type' => $type, 'interval' => $interval, 'status' => 1]);
                audit_log('system_task_create', $name);
                session_flash('flash_success', '任务已创建');
            }
        }
    } elseif ($action === 'seed_tasks') {
        $defaults = [
            ['name' => '清理请求日志', 'type' => 'clean_logs', 'interval' => 86400],
            ['name' => '清理已用验证码', 'type' => 'clean_verifications', 'interval' => 86400],
            ['name' => '关闭超时支付订单', 'type' => 'close_expired_orders', 'interval' => 1800],
            ['name' => '过期订阅标记', 'type' => 'expire_subscriptions', 'interval' => 3600],
            ['name' => '清理过期会话', 'type' => 'expire_sessions', 'interval' => 86400],
        ];
        $created = 0;
        foreach ($defaults as $d) {
            if (DB::value('SELECT id FROM system_tasks WHERE type = ?', [$d['type']]) === null) {
                DB::insert('system_tasks', $d + ['status' => 1]);
                $created++;
            }
        }
        session_flash('flash_success', "已恢复默认任务（新增 {$created} 个）");
    } elseif ($action === 'delete_instance') {
        DB::delete('system_instances', 'id = ?', [$id]);
        audit_log('system_instance_delete', "#{$id}");
        session_flash('flash_success', '实例记录已删除');
    }
    redirect(base_url('admin/system/index.php'));
}

$tasks = DB::fetchAll('SELECT * FROM system_tasks ORDER BY id ASC');
$instances = DB::fetchAll('SELECT * FROM system_instances ORDER BY last_heartbeat DESC');
$taskTypes = [
    'clean_logs' => '清理请求/错误日志',
    'clean_verifications' => '清理已用验证码',
    'close_expired_orders' => '关闭超时支付订单',
    'expire_subscriptions' => '标记过期订阅',
    'expire_sessions' => '清理过期会话',
];
$pageTitle = '系统任务与实例';
?>
<?php require dirname(__DIR__) . '/templates/header.php'; ?>

<div class="card">
    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <span>系统任务（<?php echo count($tasks); ?> 个）</span>
        <div style="display:flex; gap:8px;">
            <form method="post">
                <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="seed_tasks">
                <button type="submit" class="btn btn-sm btn-secondary">恢复默认任务</button>
            </form>
            <a class="btn btn-sm" href="#taskForm">新建任务</a>
        </div>
    </div>
    <div class="form-hint" style="margin-top:0;">建议在服务器上配置计划任务（如每 10 分钟）调用：<code style="word-break:break-all;">E:\tools\php82\php.exe tools\cron.php</code> 或通过 HTTP 访问 <code><?php echo e(base_url('tools/cron.php')); ?></code>（配置 cron_secret 后需带 ?key=）。</div>
    <table class="table">
        <thead><tr><th>ID</th><th>名称</th><th>类型</th><th>间隔</th><th>上次执行</th><th>结果</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
        <?php if (empty($tasks)) : ?>
            <tr><td colspan="8" class="text-center text-muted">暂无任务，点击「恢复默认任务」</td></tr>
        <?php endif; ?>
        <?php foreach ($tasks as $task) : ?>
            <tr>
                <td><?php echo $task['id']; ?></td>
                <td><?php echo e($task['name']); ?></td>
                <td><span class="badge badge-blue"><?php echo isset($taskTypes[$task['type']]) ? $taskTypes[$task['type']] : e($task['type']); ?></span></td>
                <td><?php echo $task['interval'] >= 3600 ? round($task['interval'] / 3600, 1) . ' 小时' : round($task['interval'] / 60) . ' 分钟'; ?></td>
                <td><?php echo e($task['last_run_at'] ?: '从未'); ?></td>
                <td style="max-width:260px; word-break:break-all;"><?php echo e($task['last_result'] ?: '-'); ?></td>
                <td><?php echo $task['status'] ? '<span class="badge badge-green">启用</span>' : '<span class="badge badge-gray">停用</span>'; ?></td>
                <td style="white-space:nowrap;">
                    <form method="post" style="display:inline-block; margin-right:4px;">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="action" value="run_task">
                        <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-secondary">立即执行</button>
                    </form>
                    <form method="post" style="display:inline-block; margin-right:4px;">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="action" value="toggle_task">
                        <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-warning"><?php echo $task['status'] ? '停用' : '启用'; ?></button>
                    </form>
                    <form method="post" style="display:inline-block;" data-confirm-title="删除任务" data-confirm-msg="删除后需重新配置。" data-confirm-ok="删除">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="action" value="delete_task">
                        <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card" id="taskForm">
    <div class="card-title">新建任务</div>
    <form method="post" action="<?php echo base_url('admin/system/index.php'); ?>" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="action" value="save_task">
        <div style="flex:1; min-width:180px;">
            <label>任务名称</label>
            <input type="text" name="name" class="form-control" required placeholder="如：清理日志">
        </div>
        <div style="flex:1; min-width:180px;">
            <label>任务类型</label>
            <select name="type" class="form-control">
                <?php foreach ($taskTypes as $tv => $tn) : ?>
                    <option value="<?php echo $tv; ?>"><?php echo $tn; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="width:140px;">
            <label>间隔（分钟）</label>
            <input type="number" name="interval" min="1" class="form-control" value="60">
        </div>
        <button type="submit" class="btn">创建</button>
    </form>
</div>

<div class="card">
    <div class="card-title">系统实例（<?php echo count($instances); ?> 个节点）</div>
    <div class="form-hint" style="margin-top:0;">实例每次收到 API 请求时上报心跳；超过 3 分钟未上报视为离线。</div>
    <table class="table">
        <thead><tr><th>节点</th><th>IP</th><th>最后心跳</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
        <?php if (empty($instances)) : ?>
            <tr><td colspan="5" class="text-center text-muted">暂无实例上报，收到 API 请求后自动注册</td></tr>
        <?php endif; ?>
        <?php foreach ($instances as $ins) : ?>
            <?php $online = strtotime($ins['last_heartbeat']) > time() - 180; ?>
            <tr>
                <td><?php echo e($ins['node_name']); ?></td>
                <td><?php echo e($ins['ip'] ?: '-'); ?></td>
                <td><?php echo e($ins['last_heartbeat']); ?></td>
                <td><?php echo $online ? '<span class="badge badge-green">在线</span>' : '<span class="badge badge-gray">离线</span>'; ?></td>
                <td>
                    <form method="post" style="display:inline-block;" data-confirm-title="删除实例" data-confirm-msg="删除后该节点下次请求会重新上报。" data-confirm-ok="删除">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="action" value="delete_instance">
                        <input type="hidden" name="id" value="<?php echo $ins['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
