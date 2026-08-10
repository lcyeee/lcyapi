<?php
if (!defined('ROOT_PATH')) {
    require dirname(__DIR__, 2) . '/includes/bootstrap.php';
}
Auth::requireLogin();
$pageTitle = isset($pageTitle) ? $pageTitle : '个人中心';
$user = Auth::user();
$requestPath = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
$selfUse = setting('self_use_mode', '0') === '1';
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
            <a class="<?php echo nav_active('/user/index.php', $requestPath); ?>" href="<?php echo base_url('user/index.php'); ?>"><?php echo svg_icon('home'); ?><span class="lbl">个人中心</span></a>
            <a class="<?php echo nav_active('/user/tokens/', $requestPath); ?>" href="<?php echo base_url('user/tokens/index.php'); ?>"><?php echo svg_icon('key'); ?><span class="lbl">令牌管理</span></a>
            <a class="<?php echo nav_active('/user/logs/', $requestPath); ?>" href="<?php echo base_url('user/logs/index.php'); ?>"><?php echo svg_icon('list'); ?><span class="lbl">使用记录</span></a>
            <a class="<?php echo nav_active('/user/wallet/', $requestPath); ?>" href="<?php echo base_url('user/wallet/index.php'); ?>"><?php echo svg_icon('wallet'); ?><span class="lbl">钱包</span></a>
            <?php if (!$selfUse) : ?>
                <a class="<?php echo nav_active('/user/pricing/', $requestPath); ?>" href="<?php echo base_url('user/pricing/index.php'); ?>"><?php echo svg_icon('cpu'); ?><span class="lbl">模型价格</span></a>
            <?php endif; ?>
            <?php if (Auth::isAdmin()) : ?>
                <a class="admin-entry" href="<?php echo base_url('admin/index.php'); ?>"><?php echo svg_icon('shield'); ?><span class="lbl">后台</span></a>
            <?php endif; ?>
        </nav>
        <div class="user-menu">
            <span class="uname"><?php echo e($user['nickname'] ?: $user['username']); ?></span>
            <span class="badge badge-blue"><?php echo e(quota_display($user['quota'])); ?></span>
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
                    <div class="menu-group" data-group="overview">
                        <button type="button" class="group-title" aria-expanded="true"><span class="gt">概览</span><?php echo svg_icon('chevron', 'i group-arrow'); ?></button>
                        <div class="group-body">
                            <a class="<?php echo nav_active('/user/index.php', $requestPath); ?>" href="<?php echo base_url('user/index.php'); ?>"><?php echo svg_icon('home'); ?><span class="lbl">个人中心</span></a>
                        </div>
                    </div>
                    <div class="menu-group" data-group="account">
                        <button type="button" class="group-title" aria-expanded="true"><span class="gt">账号</span><?php echo svg_icon('chevron', 'i group-arrow'); ?></button>
                        <div class="group-body">
                            <a class="<?php echo nav_active('/user/profile/', $requestPath); ?>" href="<?php echo base_url('user/profile/index.php'); ?>"><?php echo svg_icon('user'); ?><span class="lbl">个人资料</span></a>
                            <a class="<?php echo nav_active('/user/profile/security', $requestPath); ?>" href="<?php echo base_url('user/profile/security.php'); ?>"><?php echo svg_icon('lock'); ?><span class="lbl">账号安全</span></a>
                            <a class="<?php echo nav_active('/user/sessions.php', $requestPath); ?>" href="<?php echo base_url('user/sessions.php'); ?>"><?php echo svg_icon('globe'); ?><span class="lbl">会话管理</span></a>
                            <a class="<?php echo nav_active('/user/appearance/', $requestPath); ?>" href="<?php echo base_url('user/appearance/index.php'); ?>"><?php echo svg_icon('eye'); ?><span class="lbl">外观主题</span></a>
                        </div>
                    </div>
                    <div class="menu-group" data-group="usage">
                        <button type="button" class="group-title" aria-expanded="true"><span class="gt">使用</span><?php echo svg_icon('chevron', 'i group-arrow'); ?></button>
                        <div class="group-body">
                            <a class="<?php echo nav_active('/user/tokens/', $requestPath); ?>" href="<?php echo base_url('user/tokens/index.php'); ?>"><?php echo svg_icon('key'); ?><span class="lbl">令牌管理</span></a>
                            <a class="<?php echo nav_active('/user/logs/', $requestPath); ?>" href="<?php echo base_url('user/logs/index.php'); ?>"><?php echo svg_icon('list'); ?><span class="lbl">使用记录</span></a>
                            <a class="<?php echo nav_active('/user/wallet/', $requestPath); ?>" href="<?php echo base_url('user/wallet/index.php'); ?>"><?php echo svg_icon('wallet'); ?><span class="lbl">钱包</span></a>
                <?php if (!$selfUse) : ?>
                            <a class="<?php echo nav_active('/user/redeem/', $requestPath); ?>" href="<?php echo base_url('user/redeem/index.php'); ?>"><?php echo svg_icon('gift'); ?><span class="lbl">兑换码充值</span></a>
                <?php endif; ?>
                            <a class="<?php echo nav_active('/user/subscriptions/', $requestPath); ?>" href="<?php echo base_url('user/subscriptions/index.php'); ?>"><?php echo svg_icon('crown'); ?><span class="lbl">订阅套餐</span></a>
                        </div>
                    </div>
                    <div class="menu-group" data-group="tools">
                        <button type="button" class="group-title" aria-expanded="true"><span class="gt">工具</span><?php echo svg_icon('chevron', 'i group-arrow'); ?></button>
                        <div class="group-body">
                            <a class="<?php echo nav_active('/user/playground/', $requestPath); ?>" href="<?php echo base_url('user/playground/index.php'); ?>"><?php echo svg_icon('message'); ?><span class="lbl">Playground 测试</span></a>
                <?php if (!$selfUse) : ?>
                            <a class="<?php echo nav_active('/user/pricing/', $requestPath); ?>" href="<?php echo base_url('user/pricing/index.php'); ?>"><?php echo svg_icon('cpu'); ?><span class="lbl">模型价格</span></a>
                <?php endif; ?>
                        </div>
                    </div>
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
                    <div class="alert alert-warning"><?php echo svg_icon('alert'); ?>您的余额已低于 <?php echo e(quota_display($quotaThreshold)); ?>，请及时充值以免影响使用。</div>
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
                        /* 菜单分组折叠（默认折叠，localStorage 记忆展开的分组，当前激活分组自动展开） */
                        var groups = document.querySelectorAll('.sidebar .menu-group');
                        var storedExpanded = [];
                        try {
                            storedExpanded = (localStorage.getItem('lcyapi_user_menu_expanded') || '').split(',').filter(Boolean);
                        } catch (e) { /* 忽略隐私模式 */ }
                        groups.forEach(function (g) {
                            var title = g.querySelector('.group-title');
                            var key = g.getAttribute('data-group') || '';
                            var collapsed = storedExpanded.indexOf(key) === -1 && !g.querySelector('a.active');
                            g.classList.toggle('collapsed', collapsed);
                            if (title) {
                                title.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                                title.addEventListener('click', function () {
                                    var nowCollapsed = !g.classList.contains('collapsed');
                                    g.classList.toggle('collapsed', nowCollapsed);
                                    title.setAttribute('aria-expanded', nowCollapsed ? 'false' : 'true');
                                    var idx = storedExpanded.indexOf(key);
                                    if (!nowCollapsed) {
                                        if (idx === -1) { storedExpanded.push(key); }
                                    } else if (idx !== -1) {
                                        storedExpanded.splice(idx, 1);
                                    }
                                    try {
                                        localStorage.setItem('lcyapi_user_menu_expanded', storedExpanded.join(','));
                                    } catch (e2) { /* 忽略 */ }
                                });
                            }
                        });
                    });
                </script>
