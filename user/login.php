<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
Auth::guestOnly();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = '页面已过期，请重试';
    } else {
        $result = Auth::login(
            isset($_POST['username']) ? trim($_POST['username']) : '',
            isset($_POST['password']) ? $_POST['password'] : ''
        );
        if ($result['ok']) {
            redirect(base_url('user/index.php'));
        }
        $error = $result['reason'];
    }
}
$pageTitle = '登录';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>用户登录 - <?php echo e(setting('site_name', config('site.name'))); ?></title>
<?php echo theme_head_scripts(); ?>
<link rel="stylesheet" href="<?php echo base_url('assets/css/common.css'); ?>">
</head>
<body>
<div class="login-wrap">
    <button type="button" class="icon-btn login-theme-toggle" data-theme-toggle title="切换明暗模式"><?php echo svg_icon('moon'); ?></button>
    <div class="login-card">
        <h1><?php echo e(setting('site_name', config('site.name'))); ?></h1>
        <div class="sub">用户登录</div>
        <?php if ($error !== '') : ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>
        <form method="post" action="<?php echo base_url('user/login.php'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="username" class="form-control" required autofocus value="<?php echo e(isset($_POST['username']) ? $_POST['username'] : ''); ?>">
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn" style="width:100%;">登 录</button>
            <div class="extra">
                <span></span>
                <?php if (setting('register_enabled', '1')) : ?>
                    <a href="<?php echo base_url('user/register.php'); ?>">注册账号</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
</body>
</html>