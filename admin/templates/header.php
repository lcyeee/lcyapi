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
        <a class="brand" href="<?php echo base_url('admin/index.php'); ?>">
            <span class="brand-logo"><?php echo svg_icon('zap'); ?></span>
            <span class="brand-name"><?php echo e(setting('site_name', config('site.name'))); ?> 后台</span>
        </a>
        <nav class="menu">
            <div class="menu-group" data-group="overview">
                <button type="button" class="group-title" aria-expanded="true"><span class="gt">概览</span><?php echo svg_icon('chevron', 'i group-arrow'); ?></button>
                <div class="group-body">
                    <a class="<?php echo admin_nav('/admin/index.php', $requestPath); ?>" href="<?php echo base_url('admin/index.php'); ?>" title="控制台"><?php echo svg_icon('home'); ?><span class="lbl">控制台</span></a>
                </div>
            </div>
            <div class="menu-group" data-group="manage">
                <button type="button" class="group-title" aria-expanded="true"><span class="gt">管理</span><?php echo svg_icon('chevron', 'i group-arrow'); ?></button>
                <div class="group-body">
                    <a class="<?php echo admin_nav('/admin/users/', $requestPath); ?>" href="<?php echo base_url('admin/users/index.php'); ?>" title="用户管理"><?php echo svg_icon('users'); ?><span class="lbl">用户管理</span></a>
                    <a class="<?php echo admin_nav('/admin/channels/', $requestPath); ?>" href="<?php echo base_url('admin/channels/index.php'); ?>" title="渠道管理"><?php echo svg_icon('channel'); ?><span class="lbl">渠道管理</span></a>
                    <a class="<?php echo admin_nav('/admin/models/', $requestPath); ?>" href="<?php echo base_url('admin/models/index.php'); ?>" title="模型管理"><?php echo svg_icon('cpu'); ?><span class="lbl">模型管理</span></a>
                    <a class="<?php echo admin_nav('/admin/tokens/', $requestPath); ?>" href="<?php echo base_url('admin/tokens/index.php'); ?>" title="令牌管理"><?php echo svg_icon('key'); ?><span class="lbl">令牌管理</span></a>
                    <a class="<?php echo admin_nav('/admin/groups/', $requestPath); ?>" href="<?php echo base_url('admin/groups/index.php'); ?>" title="分组管理"><?php echo svg_icon('ratio'); ?><span class="lbl">分组管理</span></a>
                </div>
            </div>
            <div class="menu-group" data-group="logs">
                <button type="button" class="group-title" aria-expanded="true"><span class="gt">日志</span><?php echo svg_icon('chevron', 'i group-arrow'); ?></button>
                <div class="group-body">
                    <a class="<?php echo admin_nav('/admin/logs/', $requestPath); ?>" href="<?php echo base_url('admin/logs/index.php'); ?>" title="使用日志"><?php echo svg_icon('list'); ?><span class="lbl">使用日志</span></a>
                    <a class="<?php echo admin_nav('/admin/errors/', $requestPath); ?>" href="<?php echo base_url('admin/errors/index.php'); ?>" title="错误日志"><?php echo svg_icon('alert'); ?><span class="lbl">错误日志</span></a>
                    <a class="<?php echo admin_nav('/admin/audit/', $requestPath); ?>" href="<?php echo base_url('admin/audit/index.php'); ?>" title="操作审计"><?php echo svg_icon('shield'); ?><span class="lbl">操作审计</span></a>
                    <a class="<?php echo admin_nav('/admin/login-logs/', $requestPath); ?>" href="<?php echo base_url('admin/login-logs/index.php'); ?>" title="登录日志"><?php echo svg_icon('check'); ?><span class="lbl">登录日志</span></a>
                </div>
            </div>
            <div class="menu-group" data-group="ops">
                <button type="button" class="group-title" aria-expanded="true"><span class="gt">运营</span><?php echo svg_icon('chevron', 'i group-arrow'); ?></button>
                <div class="group-body">
                    <a class="<?php echo admin_nav('/admin/codes/', $requestPath); ?>" href="<?php echo base_url('admin/codes/index.php'); ?>" title="兑换码"><?php echo svg_icon('gift'); ?><span class="lbl">兑换码</span></a>
                    <a class="<?php echo admin_nav('/admin/subscriptions/', $requestPath); ?>" href="<?php echo base_url('admin/subscriptions/index.php'); ?>" title="订阅套餐"><?php echo svg_icon('crown'); ?><span class="lbl">订阅套餐</span></a>
                    <div class="sub-group" data-sub="system">
                        <button type="button" class="sub-title"><span class="gt">系统</span><?php echo svg_icon('chevron', 'i group-arrow'); ?></button>
                        <div class="sub-body">
                            <a class="<?php echo admin_nav('/admin/system/index.php', $requestPath); ?>" href="<?php echo base_url('admin/system/index.php'); ?>" title="系统任务/实例"><?php echo svg_icon('server'); ?><span class="lbl">系统任务/实例</span></a>
                            <a class="<?php echo admin_nav('/admin/system/status.php', $requestPath); ?>" href="<?php echo base_url('admin/system/status.php'); ?>" title="系统占用"><?php echo svg_icon('chart'); ?><span class="lbl">系统占用</span></a>
                            <a class="<?php echo admin_nav('/admin/system/sessions.php', $requestPath); ?>" href="<?php echo base_url('admin/system/sessions.php'); ?>" title="会话管理"><?php echo svg_icon('lock'); ?><span class="lbl">会话管理</span></a>
                        </div>
                    </div>
                    <a class="<?php echo admin_nav('/admin/rankings/', $requestPath); ?>" href="<?php echo base_url('admin/rankings/index.php'); ?>" title="排行榜"><?php echo svg_icon('chart'); ?><span class="lbl">排行榜</span></a>
                    <a class="<?php echo admin_nav('/admin/perf_metrics.php', $requestPath); ?>" href="<?php echo base_url('admin/perf_metrics.php'); ?>" title="性能指标"><?php echo svg_icon('cpu'); ?><span class="lbl">性能指标</span></a>
                    <a class="<?php echo admin_nav('/admin/usage_stats.php', $requestPath); ?>" href="<?php echo base_url('admin/usage_stats.php'); ?>" title="用量统计"><?php echo svg_icon('dollar'); ?><span class="lbl">用量统计</span></a>
                    <a class="<?php echo admin_nav('/admin/midjourney.php', $requestPath); ?>" href="<?php echo base_url('admin/midjourney.php'); ?>" title="绘图日志"><?php echo svg_icon('image'); ?><span class="lbl">绘图日志</span></a>
                </div>
            </div>
            <div class="menu-group" data-group="settings">
                <button type="button" class="group-title" aria-expanded="true"><span class="gt">系统设置</span><?php echo svg_icon('chevron', 'i group-arrow'); ?></button>
                <div class="group-body">
                    <a class="<?php echo admin_nav('/admin/settings/', $requestPath); ?>" href="<?php echo base_url('admin/settings/index.php'); ?>" title="系统设置"><?php echo svg_icon('settings'); ?><span class="lbl">系统设置</span></a>
                </div>
            </div>
            <div class="menu-group" data-group="supplier">
                <button type="button" class="group-title" aria-expanded="true"><span class="gt">扩展</span><?php echo svg_icon('chevron', 'i group-arrow'); ?></button>
                <div class="group-body">
                    <a class="<?php echo admin_nav('/admin/oauth_bindings.php', $requestPath); ?>" href="<?php echo base_url('admin/oauth_bindings.php'); ?>" title="OAuth 绑定"><?php echo svg_icon('globe'); ?><span class="lbl">OAuth 绑定</span></a>
                    <a class="<?php echo admin_nav('/admin/chat_presets.php', $requestPath); ?>" href="<?php echo base_url('admin/chat_presets.php'); ?>" title="聊天预设"><?php echo svg_icon('send'); ?><span class="lbl">聊天预设</span></a>
                    <a class="<?php echo admin_nav('/admin/prefill_groups.php', $requestPath); ?>" href="<?php echo base_url('admin/prefill_groups.php'); ?>" title="预填充分组"><?php echo svg_icon('ratio'); ?><span class="lbl">预填充分组</span></a>
                    <a class="<?php echo admin_nav('/admin/twofa_stats.php', $requestPath); ?>" href="<?php echo base_url('admin/twofa_stats.php'); ?>" title="2FA 统计"><?php echo svg_icon('lock'); ?><span class="lbl">2FA 统计</span></a>
                </div>
            </div>
        </nav>
    </aside>
    <div class="sidebar-mask" id="sidebarMask"></div>
    <div class="admin-main">
        <div class="admin-topbar">
            <div class="topbar-left">
                <button type="button" class="icon-btn menu-toggle" id="menuToggle" aria-label="打开菜单"><?php echo svg_icon('menu'); ?></button>
                <button type="button" class="icon-btn" id="sidebarCollapse" title="折叠/展开侧边栏"><?php echo svg_icon('panel'); ?></button>
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
                    /* 菜单分组/子分组折叠（默认折叠，localStorage 记忆展开项，当前激活自动展开） */
                    var groups = sidebar.querySelectorAll('.menu-group');
                    var storedExpanded = [];
                    try {
                        storedExpanded = (localStorage.getItem('lcyapi_menu_expanded') || '').split(',').filter(Boolean);
                    } catch (e) { /* 忽略隐私模式 */ }
                    function saveExpanded() {
                        try { localStorage.setItem('lcyapi_menu_expanded', storedExpanded.join(',')); } catch (e2) { /* 忽略 */ }
                    }
                    function bindToggle(el, container, key) {
                        var collapsed = storedExpanded.indexOf(key) === -1 && !container.querySelector('a.active');
                        container.classList.toggle('collapsed', collapsed);
                        el.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                        el.addEventListener('click', function () {
                            var nowCollapsed = !container.classList.contains('collapsed');
                            container.classList.toggle('collapsed', nowCollapsed);
                            el.setAttribute('aria-expanded', nowCollapsed ? 'false' : 'true');
                            var idx = storedExpanded.indexOf(key);
                            if (!nowCollapsed) {
                                if (idx === -1) { storedExpanded.push(key); }
                            } else if (idx !== -1) {
                                storedExpanded.splice(idx, 1);
                            }
                            saveExpanded();
                        });
                    }
                    groups.forEach(function (g) {
                        var key = g.getAttribute('data-group') || '';
                        var title = g.querySelector('.group-title');
                        if (title) { bindToggle(title, g, key); }
                        g.querySelectorAll('.sub-group').forEach(function (sg) {
                            var subKey = key + ':' + (sg.getAttribute('data-sub') || '');
                            var st = sg.querySelector('.sub-title');
                            if (st) { bindToggle(st, sg, subKey); }
                        });
                    });
                    /* 桌面侧边栏图标折叠模式（localStorage 记忆，移动端不生效） */
                    var layout = document.querySelector('.admin-layout');
                    if (layout && window.innerWidth > 992) {
                        var sidebarCollapsed = false;
                        try { sidebarCollapsed = localStorage.getItem('lcyapi_sidebar_collapsed') === '1'; } catch (e3) { /* 忽略 */ }
                        if (sidebarCollapsed) {
                            sidebar.classList.add('collapsed');
                            layout.classList.add('sidebar-collapsed');
                        }
                    }
                    var collapseBtn = document.getElementById('sidebarCollapse');
                    if (collapseBtn && layout) {
                        collapseBtn.addEventListener('click', function () {
                            sidebar.classList.toggle('collapsed');
                            layout.classList.toggle('sidebar-collapsed');
                            try {
                                localStorage.setItem('lcyapi_sidebar_collapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
                            } catch (e4) { /* 忽略 */ }
                        });
                    }
                });
            </script>
