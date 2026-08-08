<?php
if (!defined('ROOT_PATH')) {
    require dirname(__DIR__, 2) . '/includes/bootstrap.php';
}
Admin::requireAdmin();
$pageTitle = isset($pageTitle) ? $pageTitle : '管理后台';
$adminUser = Auth::user();
$requestPath = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
function admin_nav_active($needle, $requestPath)
{
    return strpos($requestPath, $needle) !== false ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($pageTitle); ?> - <?php echo e(setting('site_name', config('site.name'))); ?> 后台</title>
<link rel="stylesheet" href="<?php echo base_url('assets/css/common.css'); ?>">
<link rel="stylesheet" href="<?php echo base_url('assets/css/admin.css'); ?>">
</head>
<body>
<div class="admin-layout">
    <aside class="admin-sidebar">
        <div class="brand"><?php echo e(setting('site_name', config('site.name'))); ?> <span>后台</span></div>
        <nav class="menu">
            <div class="group-title">概览</div>
            <a class="<?php echo admin_nav('/admin/index.php', $requestPath); ?>" href="<?php echo base_url('admin/index.php'); ?>">控制台</a>
            <div class="group-title">管理</div>
            <a class="<?php echo admin_nav('/admin/users/', $requestPath); ?>" href="<?php echo base_url('admin/users/index.php'); ?>">用户管理</a>
            <a class="<?php echo admin_nav('/admin/channels/', $requestPath); ?>" href="<?php echo base_url('admin/channels/index.php'); ?>">渠道管理</a>
            <a class="<?php echo admin_nav('/admin/models/', $requestPath); ?>" href="<?php echo base_url('admin/models/index.php'); ?>">模型管理</a>
            <a class="<?php echo admin_nav('/admin/tokens/', $requestPath); ?>" href="<?php echo base_url('admin/tokens/index.php'); ?>">令牌管理</a>
            <div class="group-title">日志</div>
            <a class="<?php echo admin_nav('/admin/logs/', $requestPath); ?>" href="<?php echo base_url('admin/logs/index.php'); ?>">使用日志</a>
            <a class="<?php echo admin_nav('/admin/errors/', $requestPath); ?>" href="<?php echo base_url('admin/errors/index.php'); ?>">错误日志</a>
            <div class="group-title">运营</div>
            <a class="<?php echo admin_nav('/admin/codes/', $requestPath); ?>" href="<?php echo base_url('admin/codes/index.php'); ?>">兑换码</a>
            <a class="<?php echo admin_nav('/admin/settings/', $requestPath); ?>" href="<?php echo base_url('admin/settings/index.php'); ?>">系统设置</a>
        </nav>
    </aside>
    <div class="admin-main">
        <div class="admin-topbar">
            <span>欢迎回来，<?php echo e($adminUser['nickname'] ?: $adminUser['username']); ?></span>
            <div>
                <a href="<?php echo base_url('user/index.php'); ?>" style="margin-right:12px;">前台</a>
                <a href="<?php echo base_url('admin/logout.php'); ?>" class="btn btn-sm btn-secondary">退出</a>
            </div>
        </div>
        <div class="admin-content">
            <div class="admin-page-title"><?php echo e($pageTitle); ?></div>
            <?php
            $adminFlashError = session_flash('flash_error');
            $adminFlashSuccess = session_flash('flash_success');
            ?>
            <?php if ($adminFlashError !== '') : ?>
                <div class="alert alert-danger"><?php echo e($adminFlashError); ?></div>
            <?php endif; ?>
            <?php if ($adminFlashSuccess !== '') : ?>
                <div class="alert alert-success"><?php echo e($adminFlashSuccess); ?></div>
            <?php endif; ?>