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
            if (Auth::isAdmin()) {
                redirect(base_url('admin/index.php'));
            }
            redirect(base_url('user/index.php'));
        }
        $error = $result['reason'];
    }
}
$pageTitle = '后台登录';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>后台登录 - <?php echo e(setting('site_name', config('site.name'))); ?></title>
<link rel="stylesheet" href="<?php echo base_url('assets/css/common.css'); ?>">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <h1><?php echo e(setting('site_name', config('site.name'))); ?></h1>
        <div class="sub">管理员后台</div>
        <?php if ($error !== '') : ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>
        <form method="post" action="<?php echo base_url('admin/login.php'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn" style="width:100%;">登 录</button>
            <div class="extra">
                <span></span>
                <a href="<?php echo base_url('user/login.php'); ?>">用户入口</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>