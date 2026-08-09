<?php
if (!defined('ROOT_PATH')) {
    require dirname(__DIR__, 2) . '/includes/bootstrap.php';
}
Admin::requireAdmin();
$pageTitle = isset($pageTitle) ? $pageTitle : '管理后台';
$adminUser = Auth::user();
$requestPath = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
function admin_nav($needle, $requestPath)
{
    return strpos($requestPath, $needle) !== false ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?php echo e($pageTitle); ?> - <?php echo e(setting('site_name', config('site.name'))); ?> 后台</title>
<?php echo theme_head_scripts(); ?>
<link rel="stylesheet" href="<?php echo base_url('assets/css/common.css'); ?>">
<link rel="stylesheet" href="<?php echo base_url('assets/css/admin.css'); ?>">
</head>
<body>
<div class="admin-layout">
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="brand">
            <span class="brand-logo"><?php echo svg_icon('zap'); ?></span>
            <?php echo e(setting('site_name', config('site.name'))); ?><span>后台</span>
        </div>
        <nav class="menu">
            <div class="group-title">概览</div>
            <a class="<?php echo admin_nav('/admin/index.php', $requestPath); ?>" href="<?php echo base_url('admin/index.php'); ?>"><?php echo svg_icon('home'); ?>控制台</a>
            <div class="group-title">管理</div>
            <a class="<?php echo admin_nav('/admin/users/', $requestPath); ?>" href="<?php echo base_url('admin/users/index.php'); ?>"><?php echo svg_icon('users'); ?>用户管理</a>
            <a class="<?php echo admin_nav('/admin/channels/', $requestPath); ?>" href="<?php echo base_url('admin/channels/index.php'); ?>"><?php echo svg_icon('channel'); ?>渠道管理</a>
            <a class="<?php echo admin_nav('/admin/models/', $requestPath); ?>" href="<?php echo base_url('admin/models/index.php'); ?>"><?php echo svg_icon('cpu'); ?>模型管理</a>
            <a class="<?php echo admin_nav('/admin/tokens/', $requestPath); ?>" href="<?php echo base_url('admin/tokens/index.php'); ?>"><?php echo svg_icon('key'); ?>令牌管理</a>
            <a class="<?php echo admin_nav('/admin/groups/', $requestPath); ?>" href="<?php echo base_url('admin/groups/index.php'); ?>"><?php echo svg_icon('ratio'); ?>分组管理</a>
            <div class="group-title">日志</div>
            <a class="<?php echo admin_nav('/admin/logs/', $requestPath); ?>" href="<?php echo base_url('admin/logs/index.php'); ?>"><?php echo svg_icon('list'); ?>使用日志</a>
            <a class="<?php echo admin_nav('/admin/errors/', $requestPath); ?>" href="<?php echo base_url('admin/errors/index.php'); ?>"><?php echo svg_icon('alert'); ?>错误日志</a>
            <a class="<?php echo admin_nav('/admin/audit/', $requestPath); ?>" href="<?php echo base_url('admin/audit/index.php'); ?>"><?php echo svg_icon('shield'); ?>操作审计</a>
            <a class="<?php echo admin_nav('/admin/login-logs/', $requestPath); ?>" href="<?php echo base_url('admin/login-logs/index.php'); ?>"><?php echo svg_icon('check'); ?>登录日志</a>
            <div class="group-title">运营</div>
            <a class="<?php echo admin_nav('/admin/codes/', $requestPath); ?>" href="<?php echo base_url('admin/codes/index.php'); ?>"><?php echo svg_icon('gift'); ?>兑换码</a>
            <a class="<?php echo admin_nav('/admin/subscriptions/', $requestPath); ?>" href="<?php echo base_url('admin/subscriptions/index.php'); ?>"><?php echo svg_icon('crown'); ?>订阅套餐</a>
            <a class="<?php echo admin_nav('/admin/system/', $requestPath); ?>" href="<?php echo base_url('admin/system/index.php'); ?>"><?php echo svg_icon('server'); ?>系统任务/实例</a>
            <a class="<?php echo admin_nav('/admin/settings/', $requestPath); ?>" href="<?php echo base_url('admin/settings/index.php'); ?>"><?php echo svg_icon('settings'); ?>系统设置</a>
        </nav>
    </aside>
    <div class="sidebar-mask" id="sidebarMask"></div>
    <div class="admin-main">
        <div class="admin-topbar">
            <div class="topbar-left">
                <button type="button" class="icon-btn menu-toggle" id="menuToggle" aria-label="打开菜单"><?php echo svg_icon('menu'); ?></button>
                <span class="greeting">欢迎回来，<?php echo e($adminUser['nickname'] ?: $adminUser['username']); ?></span>
            </div>
            <div class="topbar-right">
                <button type="button" class="icon-btn" data-theme-toggle title="切换明暗模式"><?php echo svg_icon('moon'); ?></button>
                <a class="icon-btn" href="<?php echo base_url('user/index.php'); ?>" title="进入前台"><?php echo svg_icon('globe'); ?></a>
                <a href="<?php echo base_url('admin/logout.php'); ?>" class="btn btn-sm btn-secondary"><?php echo svg_icon('logout'); ?>退出</a>
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
            <script>
                /* 表格自动包裹横向滚动容器（移动端防重叠）+ 侧边栏抽屉 */
                document.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('table.table').forEach(function (t) {
                        if (t.parentNode && t.parentNode.classList.contains('table-wrap')) { return; }
                        var w = document.createElement('div');
                        w.className = 'table-wrap';
                        t.parentNode.insertBefore(w, t);
                        w.appendChild(t);
                    });
                    var sidebar = document.getElementById('adminSidebar');
                    var mask = document.getElementById('sidebarMask');
                    var toggle = document.getElementById('menuToggle');
                    if (!sidebar || !toggle) { return; }
                    toggle.addEventListener('click', function () {
                        sidebar.classList.add('open');
                        if (mask) { mask.classList.add('show'); }
                    });
                    if (mask) {
                        mask.addEventListener('click', function () {
                            sidebar.classList.remove('open');
                            mask.classList.remove('show');
                        });
                    }
                    sidebar.querySelectorAll('a').forEach(function (a) {
                        a.addEventListener('click', function () {
                            if (window.innerWidth <= 992) {
                                sidebar.classList.remove('open');
                                if (mask) { mask.classList.remove('show'); }
                            }
                        });
                    });
                });
            </script>
