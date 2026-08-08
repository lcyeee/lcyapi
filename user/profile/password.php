<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/templates/header.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = '页面已过期，请重试';
    } else {
        $result = Auth::changePassword(
            Auth::id(),
            $_POST['old_password'] ?? '',
            $_POST['new_password'] ?? ''
        );
        if ($result['ok']) {
            session_flash('flash_success', '密码修改成功，请重新登录');
            Auth::logout();
            redirect(base_url('user/login.php'));
        }
        $errors[] = $result['msg'];
    }
}
?>
<div class="card" style="max-width:520px;">
    <div class="card-title">修改密码</div>
    <?php foreach ($errors as $err) : ?>
        <div class="alert alert-danger"><?php echo e($err); ?></div>
    <?php endforeach; ?>
    <form method="post" action="<?php echo base_url('user/profile/password.php'); ?>">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <div class="form-group">
            <label>原密码</label>
            <input type="password" name="old_password" class="form-control" required autocomplete="current-password">
        </div>
        <div class="form-group">
            <label>新密码（6-64 位）</label>
            <input type="password" name="new_password" class="form-control" required minlength="6" autocomplete="new-password">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">修改密码</button>
        </div>
    </form>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>