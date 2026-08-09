<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

if (!setting('register_enabled', '1')) {
    redirect(base_url('user/login.php'));
}
Auth::guestOnly();

$error = '';
/* 邀请码：URL 参数优先，其次保留表单提交值 */
$affCode = isset($_GET['aff']) ? strtoupper(trim($_GET['aff'])) : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = '页面已过期，请重试';
    } elseif (!turnstile_verify()) {
        $error = '人机验证未通过，请重试';
    } else {
        $affCode = strtoupper(trim($_POST['aff'] ?? $affCode));
        $data = [
            'username' => isset($_POST['username']) ? trim($_POST['username']) : '',
            'email' => isset($_POST['email']) ? trim($_POST['email']) : '',
            'password' => isset($_POST['password']) ? $_POST['password'] : '',
            'aff_code' => $affCode,
        ];
        $result = Auth::register($data);
        if ($result['ok']) {
            redirect(base_url('user/index.php'));
        }
        $error = $result['msg'];
    }
}
$pageTitle = '注册';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>用户注册 - <?php echo e(setting('site_name', config('site.name'))); ?></title>
<?php echo theme_head_scripts(); ?>
<link rel="stylesheet" href="<?php echo base_url('assets/css/common.css'); ?>">
</head>
<body>
<div class="login-wrap">
    <button type="button" class="icon-btn login-theme-toggle" data-theme-toggle title="切换明暗模式"><?php echo svg_icon('moon'); ?></button>
    <div class="login-card">
        <h1><?php echo e(setting('site_name', config('site.name'))); ?></h1>
        <div class="sub">创建新账号</div>
        <?php if ($error !== '') : ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>
        <form method="post" action="<?php echo base_url('user/register.php'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="username" class="form-control" required minlength="3" maxlength="50" value="<?php echo e(isset($_POST['username']) ? $_POST['username'] : ''); ?>">
                <div class="form-hint">3-50 位，仅限字母、数字、下划线、横线</div>
            </div>
            <div class="form-group">
                <label>邮箱<?php echo setting('email_verify_required', '0') === '1' ? '（必填，用于验证）' : '（选填）'; ?></label>
                <input type="email" name="email" class="form-control" <?php echo setting('email_verify_required', '0') === '1' ? 'required ' : ''; ?>value="<?php echo e(isset($_POST['email']) ? $_POST['email'] : ''); ?>">
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" class="form-control" required minlength="6" maxlength="64">
                <div class="form-hint">至少 6 位</div>
            </div>
            <?php if (setting('aff_enabled', '0') === '1') : ?>
                <div class="form-group">
                    <label>邀请码（选填）</label>
                    <input type="text" name="aff" class="form-control" value="<?php echo e($affCode); ?>" placeholder="有邀请码可填写">
                </div>
            <?php endif; ?>
            <?php echo turnstile_widget(); ?>
            <button type="submit" class="btn" style="width:100%;">注 册</button>
            <div class="extra">
                <a href="<?php echo base_url('user/login.php'); ?>">已有账号，去登录</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>