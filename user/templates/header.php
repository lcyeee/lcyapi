<?php
if (!defined('ROOT_PATH')) {
    require dirname(__DIR__, 2) . '/includes/bootstrap.php';
}
Auth::requireLogin();
$pageTitle = isset($pageTitle) ? $pageTitle : '个人中心';
$user = Auth::user();
$requestPath = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
function nav_active($needle, $requestPath)
{
    return strpos($requestPath, $needle) !== false ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($pageTitle); ?> - <?php echo e(setting('site_name', config('site.name'))); ?></title>
<link rel="stylesheet" href="<?php echo base_url('assets/css/common.css'); ?>">
<link rel="stylesheet" href="<?php echo base_url('assets/css/user.css'); ?>">
</head>
<body>
<div class="topbar">
    <div class="container">
        <a class="brand" href="<?php echo base_url('user/index.php'); ?>"><?php echo e(setting('site_name', config('site.name'))); ?></a>
        <nav>
            <a class="<?php echo nav_active('/user/index.php', $requestPath); ?>" href="<?php echo base_url('user/index.php'); ?>">个人中心</a>
            <a class="<?php echo nav_active('/user/tokens/', $requestPath); ?>" href="<?php echo base_url('user/tokens/index.php'); ?>">令牌管理</a>
            <a class="<?php echo nav_active('/user/logs/', $requestPath); ?>" href="<?php echo base_url('user/logs/index.php'); ?>">使用记录</a>
            <a class="<?php echo nav_active('/user/wallet/', $requestPath); ?>" href="<?php echo base_url('user/wallet/index.php'); ?>">钱包</a>
            <?php if (Auth::isAdmin()) : ?>
                <a href="<?php echo base_url('admin/index.php'); ?>" style="color:#f59e0b;">后台</a>
            <?php endif; ?>
        </nav>
        <div class="user-menu">
            <span><?php echo e($user['nickname'] ?: $user['username']); ?></span>
            <span class="badge badge-blue">$<?php echo e(number_format((float)$user['quota'], 4)); ?></span>
            <a href="<?php echo base_url('user/logout.php'); ?>" class="btn btn-sm btn-secondary">退出</a>
        </div>
    </div>
</div>
<div class="page-body">
    <div class="container">
        <div class="layout">
            <aside class="sidebar">
                <div class="menu">
                    <a class="<?php echo nav_active('/user/index.php', $requestPath); ?>" href="<?php echo base_url('user/index.php'); ?>">个人中心</a>
                    <a class="<?php echo nav_active('/user/profile/', $requestPath); ?>" href="<?php echo base_url('user/profile/index.php'); ?>">个人资料</a>
                    <a class="<?php echo nav_active('/user/tokens/', $requestPath); ?>" href="<?php echo base_url('user/tokens/index.php'); ?>">令牌管理</a>
                    <a class="<?php echo nav_active('/user/logs/', $requestPath); ?>" href="<?php echo base_url('user/logs/index.php'); ?>">使用记录</a>
                    <a class="<?php echo nav_active('/user/wallet/', $requestPath); ?>" href="<?php echo base_url('user/wallet/index.php'); ?>">钱包</a>
                    <a class="<?php echo nav_active('/user/redeem/', $requestPath); ?>" href="<?php echo base_url('user/redeem/index.php'); ?>">兑换码充值</a>
                </div>
            </aside>
            <main class="main">
                <?php
                $flashError = session_flash('flash_error');
                $flashSuccess = session_flash('flash_success');
                ?>
                <?php if ($flashError !== '') : ?>
                    <div class="alert alert-danger"><?php echo e($flashError); ?></div>
                <?php endif; ?>
                <?php if ($flashSuccess !== '') : ?>
                    <div class="alert alert-success"><?php echo e($flashSuccess); ?></div>
                <?php endif; ?>