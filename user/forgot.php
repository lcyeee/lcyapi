<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
Auth::guestOnly();

$error = '';
$info = '';
$step = isset($_GET['step']) ? $_GET['step'] : '1';
$email = isset($_GET['email']) ? strtolower(trim($_GET['email'])) : (isset($_POST['email']) ? strtolower(trim($_POST['email'])) : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_verify()) {
        $error = '页面已过期，请重试';
    } elseif (!turnstile_verify()) {
        $error = '人机验证未通过，请重试';
    } elseif ($action === 'send') {
        $result = Auth::sendForgotCode($email);
        if ($result['ok']) {
            $step = '2';
            $info = !empty($result['dev']) ? '验证码已生成（未配置 SMTP，已写入 data/logs/mail.log，可直接查看）' : '验证码已发送到您的邮箱，请查收';
        } else {
            $error = $result['msg'];
        }
    } elseif ($action === 'reset') {
        $code = trim($_POST['code'] ?? '');
        $password = $_POST['password'] ?? '';
        $result = Auth::resetPassword($email, $code, $password);
        if ($result['ok']) {
            session_flash('flash_success', '密码已重置，请使用新密码登录');
            redirect(base_url('user/login.php'));
        }
        $error = $result['msg'];
    }
}
$pageTitle = '找回密码';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>找回密码 - <?php echo e(setting('site_name', config('site.name'))); ?></title>
<?php echo theme_head_scripts(); ?>
<link rel="stylesheet" href="<?php echo base_url('assets/css/common.css'); ?>">
</head>
<body>
<div class="login-wrap">
    <button type="button" class="icon-btn login-theme-toggle" data-theme-toggle title="切换明暗模式"><?php echo svg_icon('moon'); ?></button>
    <div class="login-card">
        <h1><?php echo e(setting('site_name', config('site.name'))); ?></h1>
        <div class="sub"><?php echo $step === '2' ? '输入验证码并设置新密码' : '通过邮箱找回密码'; ?></div>
        <?php if ($error !== '') : ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>
        <?php if ($info !== '') : ?>
            <div class="alert alert-success"><?php echo e($info); ?></div>
        <?php endif; ?>
        <?php if ($step === '2') : ?>
            <form method="post" action="<?php echo base_url('user/forgot.php?step=2'); ?>">
                <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="action" value="reset">
                <input type="hidden" name="email" value="<?php echo e($email); ?>">
                <div class="form-group">
                    <label>邮箱</label>
                    <input type="email" class="form-control" value="<?php echo e($email); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>邮箱验证码</label>
                    <input type="text" name="code" class="form-control" required maxlength="6" inputmode="numeric" placeholder="6 位数字验证码">
                </div>
                <div class="form-group">
                    <label>新密码</label>
                    <input type="password" name="password" class="form-control" required minlength="6" maxlength="64">
                    <div class="form-hint">至少 6 位</div>
                </div>
                <button type="submit" class="btn" style="width:100%;">重置密码</button>
                <div class="extra">
                    <a href="<?php echo base_url('user/forgot.php'); ?>">重新发送</a>
                    <a href="<?php echo base_url('user/login.php'); ?>">返回登录</a>
                </div>
            </form>
        <?php else : ?>
            <form method="post" action="<?php echo base_url('user/forgot.php'); ?>">
                <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="action" value="send">
                <div class="form-group">
                    <label>注册邮箱</label>
                    <input type="email" name="email" class="form-control" required autofocus value="<?php echo e($email); ?>" placeholder="请输入注册时使用的邮箱">
                </div>
                <?php echo turnstile_widget(); ?>
                <button type="submit" class="btn" style="width:100%;">发送验证码</button>
                <div class="extra">
                    <span></span>
                    <a href="<?php echo base_url('user/login.php'); ?>">返回登录</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
