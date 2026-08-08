<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '系统设置';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        session_flash('flash_error', '表单已过期，请重试');
        redirect(base_url('admin/settings/index.php'));
    }
    $name = mb_substr(trim($_POST['site_name'] ?? ''), 0, 50);
    $desc = mb_substr(trim($_POST['site_description'] ?? ''), 0, 200);
    $register = empty($_POST['register_enabled']) ? '0' : '1';
    $defaultQuota = (float)($_POST['default_quota'] ?? 0);
    $loginAttempts = max(1, (int)($_POST['login_attempts'] ?? 5));
    $loginLockMinutes = max(1, (int)($_POST['login_lock_time'] ?? 15));
    $rateLimit = max(1, (int)($_POST['api_rate_limit'] ?? 60));
    $rateWindow = max(1, (int)($_POST['api_rate_window'] ?? 60));
    $relayTimeout = max(10, (int)($_POST['relay_timeout'] ?? 120));
    $retryCount = max(0, (int)($_POST['retry_count'] ?? 0));
    $autoDisable = empty($_POST['auto_disable']) ? '0' : '1';
    $autoDisableThreshold = max(1, (int)($_POST['auto_disable_threshold'] ?? 100));

    if ($name === '') {
        session_flash('flash_error', '站点名称不能为空');
        redirect(base_url('admin/settings/index.php'));
    }

    setting_set('site_name', $name);
    setting_set('site_description', $desc);
    setting_set('register_enabled', $register);
    setting_set('default_quota', (string)$defaultQuota);
    setting_set('login_attempts', (string)$loginAttempts);
    setting_set('login_lock_time', (string)($loginLockMinutes * 60));
    setting_set('api_rate_limit', (string)$rateLimit);
    setting_set('api_rate_window', (string)$rateWindow);
    setting_set('relay_timeout', (string)$relayTimeout);
    setting_set('retry_count', (string)$retryCount);
    setting_set('auto_disable', $autoDisable);
    setting_set('auto_disable_threshold', (string)$autoDisableThreshold);

    session_flash('flash_success', '设置已保存');
    redirect(base_url('admin/settings/index.php'));
}

$s = settings_all();
$siteName = isset($s['site_name']) ? $s['site_name'] : config('site.name');
$siteDesc = isset($s['site_description']) ? $s['site_description'] : config('site.description', '');
$registerEnabled = isset($s['register_enabled']) ? $s['register_enabled'] : (config('site.register_enabled') ? '1' : '0');
$defaultQuota = isset($s['default_quota']) ? $s['default_quota'] : config('site.default_quota', 0);
$loginAttempts = isset($s['login_attempts']) ? $s['login_attempts'] : config('security.login_attempts', 5);
$loginLockMinutes = (int)round((int)(isset($s['login_lock_time']) ? $s['login_lock_time'] : config('security.login_lock_time', 900)) / 60);
$rateLimit = isset($s['api_rate_limit']) ? $s['api_rate_limit'] : config('security.api_rate_limit', 60);
$rateWindow = isset($s['api_rate_window']) ? $s['api_rate_window'] : config('security.api_rate_window', 60);
$relayTimeout = isset($s['relay_timeout']) ? $s['relay_timeout'] : config('relay.timeout', 120);
$retryCount = isset($s['retry_count']) ? $s['retry_count'] : config('relay.retry_count', 0);
$autoDisable = isset($s['auto_disable']) ? $s['auto_disable'] : (config('relay.auto_disable') ? '1' : '0');
$autoDisableThreshold = isset($s['auto_disable_threshold']) ? $s['auto_disable_threshold'] : config('relay.auto_disable_threshold', 100);
?>
<?php require dirname(__DIR__) . '/templates/header.php'; ?>

<div class="card" style="max-width:720px;">
    <div class="card-title"><?php echo svg_icon('eye'); ?>外观主题（仅影响本机显示）</div>
    <div class="form-group">
        <label>明暗模式</label>
        <div class="theme-mode-group">
            <button type="button" class="theme-mode-btn" data-theme-mode="auto"><?php echo svg_icon('refresh'); ?>跟随系统</button>
            <button type="button" class="theme-mode-btn" data-theme-mode="light"><?php echo svg_icon('sun'); ?>亮色</button>
            <button type="button" class="theme-mode-btn" data-theme-mode="dark"><?php echo svg_icon('moon'); ?>暗色</button>
        </div>
    </div>
    <div class="form-group">
        <label>预设配色</label>
        <div class="theme-presets">
            <button type="button" class="theme-preset" data-theme-preset="ice"><span class="dot" style="background:#409EFF;"></span>浅冰蓝</button>
            <button type="button" class="theme-preset" data-theme-preset="white"><span class="dot" style="background:#5B8DEF;"></span>极简白</button>
            <button type="button" class="theme-preset" data-theme-preset="mint"><span class="dot" style="background:#34C78B;"></span>薄荷绿</button>
            <button type="button" class="theme-preset" data-theme-preset="lilac"><span class="dot" style="background:#8B7CF6;"></span>淡紫</button>
            <button type="button" class="theme-preset" data-theme-preset="space"><span class="dot" style="background:#64748B;"></span>深空灰</button>
        </div>
    </div>
    <div class="form-group">
        <label>自定义主色</label>
        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <input type="color" id="themeAccentPicker" value="#409EFF" style="width:52px; height:38px; padding:2px; border:1px solid var(--border); border-radius:9px; background:var(--card-2); cursor:pointer;">
            <button type="button" class="btn btn-sm btn-secondary" data-theme-reset><?php echo svg_icon('refresh'); ?>恢复默认</button>
        </div>
    </div>
</div>

<form method="post" action="<?php echo base_url('admin/settings/index.php'); ?>">
    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">

    <div class="card" style="max-width:720px;">
        <div class="card-title">站点设置</div>
        <div class="form-group">
            <label>站点名称</label>
            <input type="text" name="site_name" class="form-control" value="<?php echo e($siteName); ?>">
        </div>
        <div class="form-group">
            <label>站点描述</label>
            <input type="text" name="site_description" class="form-control" value="<?php echo e($siteDesc); ?>">
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="register_enabled" value="1" <?php echo $registerEnabled === '1' ? 'checked' : ''; ?> style="width:auto;"> 开放用户注册</label>
        </div>
        <div class="form-group">
            <label>新用户默认额度（$）</label>
            <input type="number" name="default_quota" step="0.0001" min="0" class="form-control" value="<?php echo e($defaultQuota); ?>">
        </div>
    </div>

    <div class="card" style="max-width:720px;">
        <div class="card-title">安全设置</div>
        <div class="form-group">
            <label>登录失败锁定阈值（次）</label>
            <input type="number" name="login_attempts" min="1" class="form-control" value="<?php echo e($loginAttempts); ?>">
        </div>
        <div class="form-group">
            <label>锁定时间（分钟）</label>
            <input type="number" name="login_lock_time" min="1" class="form-control" value="<?php echo e((int)round($loginLockMinutes)); ?>">
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;">
                <label>API 限流（次数）</label>
                <input type="number" name="api_rate_limit" min="1" class="form-control" value="<?php echo e($rateLimit); ?>">
            </div>
            <div style="flex:1;">
                <label>限流窗口（秒）</label>
                <input type="number" name="api_rate_window" min="1" class="form-control" value="<?php echo e($rateWindow); ?>">
            </div>
        </div>
    </div>

    <div class="card" style="max-width:720px;">
        <div class="card-title">转发设置</div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;">
                <label>上游超时（秒）</label>
                <input type="number" name="relay_timeout" min="10" class="form-control" value="<?php echo e($relayTimeout); ?>">
            </div>
            <div style="flex:1;">
                <label>失败重试次数</label>
                <input type="number" name="retry_count" min="0" class="form-control" value="<?php echo e($retryCount); ?>">
            </div>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="auto_disable" value="1" <?php echo $autoDisable === '1' ? 'checked' : ''; ?> style="width:auto;"> 连续失败自动停用渠道</label>
        </div>
        <div class="form-group">
            <label>自动停用阈值（失败次数）</label>
            <input type="number" name="auto_disable_threshold" min="1" class="form-control" value="<?php echo e($autoDisableThreshold); ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">保存设置</button>
        </div>
    </div>
</form>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>