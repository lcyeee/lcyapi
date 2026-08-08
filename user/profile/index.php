<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/templates/header.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = '页面已过期，请重试';
    } else {
        $nickname = mb_substr(trim($_POST['nickname'] ?? ''), 0, 50);
        $email = trim($_POST['email'] ?? '');
        $data = ['nickname' => $nickname !== '' ? $nickname : null];
        if ($email !== '') {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = '邮箱格式不正确';
            } else {
                $exists = DB::fetch('SELECT id FROM users WHERE email = ? AND id != ?', [$email, Auth::id()]);
                if ($exists !== false) {
                    $errors[] = '该邮箱已被使用';
                } else {
                    $data['email'] = $email;
                }
            }
        } else {
            $data['email'] = null;
        }
        if (empty($errors)) {
            User::update(Auth::id(), $data);
            session_flash('flash_success', '个人资料已更新');
            redirect(base_url('user/profile/index.php'));
        }
    }
}
$user = Auth::user();
?>
<div class="card" style="max-width:520px;">
    <div class="card-title">个人资料</div>
    <?php foreach ($errors as $err) : ?>
        <div class="alert alert-danger"><?php echo e($err); ?></div>
    <?php endforeach; ?>
    <form method="post" action="<?php echo base_url('user/profile/index.php'); ?>">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <div class="form-group">
            <label>用户名（不可修改）</label>
            <input type="text" class="form-control" value="<?php echo e($user['username']); ?>" disabled>
        </div>
        <div class="form-group">
            <label>昵称</label>
            <input type="text" name="nickname" class="form-control" value="<?php echo e($user['nickname']); ?>">
        </div>
        <div class="form-group">
            <label>邮箱</label>
            <input type="text" name="email" class="form-control" value="<?php echo e($user['email']); ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">保存</button>
            <a href="<?php echo base_url('user/index.php'); ?>" class="btn btn-secondary">返回</a>
        </div>
    </form>
    <hr style="border:none; border-top:1px solid var(--border); margin:20px 0;">
    <div class="detail-list">
        <div class="item"><div class="k">注册时间</div><div class="v"><?php echo e($user['created_at']); ?></div></div>
        <div class="item"><div class="k">最后登录</div><div class="v"><?php echo e($user['last_login_at'] ?: '-'); ?>（<?php echo e($user['last_login_ip'] ?: '-'); ?>）</div></div>
        <div class="item"><div class="k">累计调用</div><div class="v"><?php echo number_format((int)$user['api_count']); ?> 次</div></div>
        <div class="item"><div class="k">角色</div><div class="v"><?php echo $user['role'] === 'admin' ? '管理员' : '普通用户'; ?></div></div>
    </div>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>