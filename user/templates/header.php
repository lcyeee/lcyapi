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
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?php echo e($pageTitle); ?> - <?php echo e(setting('site_name', config('site.name'))); ?></title>
<?php echo theme_head_scripts(); ?>
<link rel="stylesheet" href="<?php echo base_url('assets/css/common.css'); ?>">
<link rel="stylesheet" href="<?php echo base_url('assets/css/user.css'); ?>">
</head>
<body>
<div class="topbar">
    <div class="container">
        <a class="brand" href="<?php echo base_url('user/index.php'); ?>">
            <span class="brand-logo"><?php echo svg_icon('zap'); ?></span>
            <?php echo e(setting('site_name', config('site.name'))); ?>
        </a>
        <nav>
            <a class="<?php echo nav_active('/user/index.php', $requestPath); ?>" href="<?php echo base_url('user/index.php'); ?>"><?php echo svg_icon('home'); ?>个人中心</a>
            <a class="<?php echo nav_active('/user/tokens/', $requestPath); ?>" href="<?php echo base_url('user/tokens/index.php'); ?>"><?php echo svg_icon('key'); ?>令牌管理</a>
            <a class="<?php echo nav_active('/user/logs/', $requestPath); ?>" href="<?php echo base_url('user/logs/index.php'); ?>"><?php echo svg_icon('list'); ?>使用记录</a>
            <a class="<?php echo nav_active('/user/wallet/', $requestPath); ?>" href="<?php echo base_url('user/wallet/index.php'); ?>"><?php echo svg_icon('wallet'); ?>钱包</a>
            <a class="<?php echo nav_active('/user/pricing/', $requestPath); ?>" href="<?php echo base_url('user/pricing/index.php'); ?>"><?php echo svg_icon('cpu'); ?>模型价格</a>
            <?php if (Auth::isAdmin()) : ?>
                <a class="admin-entry" href="<?php echo base_url('admin/index.php'); ?>"><?php echo svg_icon('shield'); ?>后台</a>
            <?php endif; ?>
        </nav>
        <div class="user-menu">
            <span class="uname"><?php echo e($user['nickname'] ?: $user['username']); ?></span>
            <span class="badge badge-blue">$<?php echo e(number_format((float)$user['quota'], 4)); ?></span>
            <button type="button" class="icon-btn" data-theme-toggle title="切换明暗模式"><?php echo svg_icon('moon'); ?></button>
            <a href="<?php echo base_url('user/logout.php'); ?>" class="btn btn-sm btn-secondary"><?php echo svg_icon('logout'); ?>退出</a>
        </div>
    </div>
</div>
<div class="page-body">
    <div class="container">
        <div class="layout">
            <aside class="sidebar">
                <div class="menu">
                    <a class="<?php echo nav_active('/user/index.php', $requestPath); ?>" href="<?php echo base_url('user/index.php'); ?>"><?php echo svg_icon('home'); ?>个人中心</a>
                    <a class="<?php echo nav_active('/user/profile/', $requestPath); ?>" href="<?php echo base_url('user/profile/index.php'); ?>"><?php echo svg_icon('user'); ?>个人资料</a>
                    <a class="<?php echo nav_active('/user/tokens/', $requestPath); ?>" href="<?php echo base_url('user/tokens/index.php'); ?>"><?php echo svg_icon('key'); ?>令牌管理</a>
                    <a class="<?php echo nav_active('/user/logs/', $requestPath); ?>" href="<?php echo base_url('user/logs/index.php'); ?>"><?php echo svg_icon('list'); ?>使用记录</a>
                    <a class="<?php echo nav_active('/user/wallet/', $requestPath); ?>" href="<?php echo base_url('user/wallet/index.php'); ?>"><?php echo svg_icon('wallet'); ?>钱包</a>
                    <a class="<?php echo nav_active('/user/redeem/', $requestPath); ?>" href="<?php echo base_url('user/redeem/index.php'); ?>"><?php echo svg_icon('gift'); ?>兑换码充值</a>
                    <a class="<?php echo nav_active('/user/pricing/', $requestPath); ?>" href="<?php echo base_url('user/pricing/index.php'); ?>"><?php echo svg_icon('cpu'); ?>模型价格</a>
                    <a class="<?php echo nav_active('/user/appearance/', $requestPath); ?>" href="<?php echo base_url('user/appearance/index.php'); ?>"><?php echo svg_icon('eye'); ?>外观主题</a>
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
                <?php $siteNotice = setting('notice', ''); ?>
                <?php if ($siteNotice !== '') : ?>
                    <div class="alert alert-info"><?php echo svg_icon('info'); ?><?php echo nl2br(e($siteNotice)); ?></div>
                <?php endif; ?>
                <?php $quotaThreshold = (float)setting('quota_remind_threshold', '0'); ?>
                <?php if ($quotaThreshold > 0 && (float)$user['quota'] < $quotaThreshold) : ?>
                    <div class="alert alert-warning"><?php echo svg_icon('alert'); ?>您的余额已低于 $<?php echo e(number_format($quotaThreshold, 4)); ?>，请及时充值以免影响使用。</div>
                <?php endif; ?>
                <script>
                    /* 表格自动包裹横向滚动容器（移动端防重叠） */
                    document.addEventListener('DOMContentLoaded', function () {
                        document.querySelectorAll('table.table').forEach(function (t) {
                            if (t.parentNode && t.parentNode.classList.contains('table-wrap')) { return; }
                            var w = document.createElement('div');
                            w.className = 'table-wrap';
                            t.parentNode.insertBefore(w, t);
                            w.appendChild(t);
                        });
                    });
                </script>
