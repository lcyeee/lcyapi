<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/templates/header.php';

$newKeyFlash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        session_flash('flash_error', '页面已过期，请重试');
        redirect(base_url('user/tokens/index.php'));
    }
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $quota = (float)($_POST['remain_quota'] ?? -1);
        $expired = trim($_POST['expired_at'] ?? '');
        if ($name === '') {
            session_flash('flash_error', '请输入令牌名称');
            redirect(base_url('user/tokens/index.php'));
        }
        $result = Token::create(Auth::id(), $name, $quota, $expired !== '' ? $expired : null);
        if ($result !== false) {
            $newKeyFlash = $result['key'];
        } else {
            session_flash('flash_error', '创建失败');
        }
    } elseif ($action === 'toggle') {
        Token::update((int)($_POST['id'] ?? 0), ['status' => 0], Auth::id());
        session_flash('flash_success', '令牌已停用');
        redirect(base_url('user/tokens/index.php'));
    } elseif ($action === 'delete') {
        if (Token::delete((int)($_POST['id'] ?? 0), Auth::id())) {
            session_flash('flash_success', '令牌已删除');
        }
        redirect(base_url('user/tokens/index.php'));
    }
}

$tokens = Token::getByUser(Auth::id());
?>
<div class="card">
    <div class="card-title">创建令牌</div>
    <?php if ($newKeyFlash !== '') : ?>
        <div class="alert alert-info">
            新令牌已生成：<code><?php echo e($newKeyFlash); ?></code>（仅显示这一次，请妥善保管）
        </div>
    <?php endif; ?>
    <form method="post" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-group" style="margin:0;">
            <label>令牌名称</label>
            <input type="text" name="name" class="form-control" style="width:180px;" placeholder="例如：开发环境">
        </div>
        <div class="form-group" style="margin:0;">
            <label>令牌额度（$，留空不限制）</label>
            <input type="number" name="remain_quota" step="0.0001" class="form-control" style="width:160px;">
        </div>
        <div class="form-group" style="margin:0;">
            <label>过期时间（可留空）</label>
            <input type="datetime-local" name="expired_at" class="form-control" style="width:200px;">
        </div>
        <button type="submit" class="btn">创建</button>
    </form>
</div>

<div class="card">
    <div class="card-title">我的令牌（<?php echo count($tokens); ?>）</div>
    <table class="table">
        <thead>
            <tr><th>ID</th><th>名称</th><th>密钥</th><th>剩余额度</th><th>已用</th>
                <th>次数</th><th>过期时间</th><th>状态</th><th>最后使用</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php if (empty($tokens)) : ?>
            <tr><td colspan="10" class="text-center text-muted">暂无令牌，创建第一个吧</td></tr>
        <?php endif; ?>
        <?php foreach ($tokens as $token) : ?>
            <tr>
                <td><?php echo $token['id']; ?></td>
                <td><?php echo e($token['name']); ?></td>
                <td><code><?php echo e(Token::maskKey($token['key'])); ?></code></td>
                <td><?php echo (float)$token['remain_quota'] < 0 ? '不限' : '$' . e(number_format((float)$token['remain_quota'], 4)); ?></td>
                <td>$<?php echo e(number_format((float)$token['used_quota'], 4)); ?></td>
                <td><?php echo number_format((int)$token['used_count']); ?></td>
                <td><?php echo $token['expired_at'] ? e($token['expired_at']) : '-'; ?></td>
                <td><?php echo $token['status'] ? '<span class="badge badge-green">启用</span>' : '<span class="badge badge-gray">已停用</span>'; ?></td>
                <td><?php echo $token['last_used_at'] ? e($token['last_used_at']) : '-'; ?></td>
                <td style="white-space:nowrap;">
                    <?php if ($token['status']) : ?>
                        <form method="post" style="display:inline-block; margin-right:4px;">
                            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?php echo $token['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-warning">停用</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" style="display:inline-block;" onsubmit="return confirm('确定删除该令牌？')">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $token['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>