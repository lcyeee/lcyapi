<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$pending = isset($_SESSION['lcyapi_2fa_pending']) ? (int)$_SESSION['lcyapi_2fa_pending'] : 0;
if ($pending <= 0) {
    redirect(base_url('user/login.php'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = '页面已过期，请重试';
    } else {
        $result = Auth::verify2fa($pending, trim($_POST['code'] ?? ''));
        if ($result['ok']) {
            redirect(base_url('user/index.php'));
        }
        $error = $result['msg'];
    }
}
$pageTitle = '两步验证';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>两步验证 - <?php echo e(setting('site_name', config('site.name'))); ?></title>
<?php echo theme_head_scripts(); ?>
<link rel="stylesheet" href="<?php echo base_url('assets/css/common.css'); ?>">
</head>
<body>
<div class="login-wrap">
    <button type="button" class="icon-btn login-theme-toggle" data-theme-toggle title="切换明暗模式"><?php echo svg_icon('moon'); ?></button>
    <div class="login-card">
        <h1><?php echo e(setting('site_name', config('site.name'))); ?></h1>
        <div class="sub">两步验证</div>
        <?php if ($error !== '') : ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>
        <p style="color:var(--text-2); margin:0 0 16px;">请输入身份验证器中的 6 位动态验证码，或使用一次性备份码。</p>
        <form method="post" action="<?php echo base_url('user/twofa.php'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
            <div class="form-group">
                <label>验证码</label>
                <input type="text" name="code" class="form-control" required maxlength="10" autofocus autocomplete="one-time-code" placeholder="6 位验证码或备份码">
            </div>
            <button type="submit" class="btn" style="width:100%;">验 证</button>
            <div class="extra">
                <a href="<?php echo base_url('user/login.php'); ?>">返回重新登录</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
