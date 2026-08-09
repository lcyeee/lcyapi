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
    $notice = mb_substr(trim($_POST['notice'] ?? ''), 0, 2000);
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
    $affEnabled = empty($_POST['aff_enabled']) ? '0' : '1';
    $affQuota = max(0, (float)($_POST['aff_quota'] ?? 0));
    $checkinEnabled = empty($_POST['checkin_enabled']) ? '0' : '1';
    $checkinReward = max(0, (float)($_POST['checkin_reward'] ?? 0));
    $quotaRemindThreshold = max(0, (float)($_POST['quota_remind_threshold'] ?? 0));
    $emailVerifyRequired = empty($_POST['email_verify_required']) ? '0' : '1';
    $smtpHost = mb_substr(trim($_POST['smtp_host'] ?? ''), 0, 100);
    $smtpPort = max(1, (int)($_POST['smtp_port'] ?? 465));
    $smtpUsername = mb_substr(trim($_POST['smtp_username'] ?? ''), 0, 100);
    $smtpPassword = (string)($_POST['smtp_password'] ?? '');
    $smtpEncryption = in_array($_POST['smtp_encryption'] ?? 'ssl', ['ssl', 'tls', 'none'], true) ? $_POST['smtp_encryption'] : 'ssl';
    $smtpFrom = mb_substr(trim($_POST['smtp_from'] ?? ''), 0, 100);
    $smtpFromName = mb_substr(trim($_POST['smtp_from_name'] ?? ''), 0, 50);
    $sensitiveWords = mb_substr(trim($_POST['sensitive_words'] ?? ''), 0, 20000);
    $maxRequestMb = max(0, (int)($_POST['max_request_mb'] ?? 0));
    $ssrfAllowPrivate = empty($_POST['ssrf_allow_private']) ? '0' : '1';
    $cronSecret = mb_substr(trim($_POST['cron_secret'] ?? ''), 0, 100);
    $turnstileSiteKey = mb_substr(trim($_POST['turnstile_site_key'] ?? ''), 0, 100);
    $turnstileSecretKey = mb_substr(trim($_POST['turnstile_secret_key'] ?? ''), 0, 200);
    $oauthGithubEnabled = empty($_POST['oauth_github_enabled']) ? '0' : '1';
    $oauthGithubId = mb_substr(trim($_POST['oauth_github_client_id'] ?? ''), 0, 100);
    $oauthGithubSecret = mb_substr(trim($_POST['oauth_github_client_secret'] ?? ''), 0, 200);
    $oauthTelegramEnabled = empty($_POST['oauth_telegram_enabled']) ? '0' : '1';
    $oauthTelegramBotToken = mb_substr(trim($_POST['oauth_telegram_bot_token'] ?? ''), 0, 200);
    $oauthTelegramUsername = mb_substr(trim($_POST['oauth_telegram_bot_username'] ?? ''), 0, 100);
    $payRatio = max(0, (float)($_POST['pay_ratio'] ?? 1));
    $epayEnabled = empty($_POST['epay_enabled']) ? '0' : '1';
    $epayApiUrl = rtrim(mb_substr(trim($_POST['epay_api_url'] ?? ''), 0, 200), '/');
    $epayPid = mb_substr(trim($_POST['epay_pid'] ?? ''), 0, 50);
    $epayKey = mb_substr(trim($_POST['epay_key'] ?? ''), 0, 100);
    $stripeEnabled = empty($_POST['stripe_enabled']) ? '0' : '1';
    $stripeSecretKey = mb_substr(trim($_POST['stripe_secret_key'] ?? ''), 0, 200);
    $stripePublishableKey = mb_substr(trim($_POST['stripe_publishable_key'] ?? ''), 0, 200);
    $stripeWebhookSecret = mb_substr(trim($_POST['stripe_webhook_secret'] ?? ''), 0, 200);

    if ($name === '') {
        session_flash('flash_error', '站点名称不能为空');
        redirect(base_url('admin/settings/index.php'));
    }

    setting_set('site_name', $name);
    setting_set('site_description', $desc);
    setting_set('notice', $notice);
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
    setting_set('aff_enabled', $affEnabled);
    setting_set('aff_quota', (string)$affQuota);
    setting_set('checkin_enabled', $checkinEnabled);
    setting_set('checkin_reward', (string)$checkinReward);
    setting_set('quota_remind_threshold', (string)$quotaRemindThreshold);
    setting_set('email_verify_required', $emailVerifyRequired);
    setting_set('smtp_host', $smtpHost);
    setting_set('smtp_port', (string)$smtpPort);
    setting_set('smtp_username', $smtpUsername);
    if ($smtpPassword !== '') {
        setting_set('smtp_password', $smtpPassword);
    }
    setting_set('smtp_encryption', $smtpEncryption);
    setting_set('smtp_from', $smtpFrom);
    setting_set('smtp_from_name', $smtpFromName);
    setting_set('sensitive_words', $sensitiveWords);
    setting_set('max_request_mb', (string)$maxRequestMb);
    setting_set('ssrf_allow_private', $ssrfAllowPrivate);
    if ($cronSecret !== '') {
        setting_set('cron_secret', $cronSecret);
    }
    setting_set('turnstile_site_key', $turnstileSiteKey);
    setting_set('turnstile_secret_key', $turnstileSecretKey);
    setting_set('oauth_github_enabled', $oauthGithubEnabled);
    setting_set('oauth_github_client_id', $oauthGithubId);
    setting_set('oauth_github_client_secret', $oauthGithubSecret);
    setting_set('oauth_telegram_enabled', $oauthTelegramEnabled);
    setting_set('oauth_telegram_bot_token', $oauthTelegramBotToken);
    setting_set('oauth_telegram_bot_username', $oauthTelegramUsername);
    setting_set('pay_ratio', (string)$payRatio);
    setting_set('epay_enabled', $epayEnabled);
    setting_set('epay_api_url', $epayApiUrl);
    setting_set('epay_pid', $epayPid);
    setting_set('epay_key', $epayKey);
    setting_set('stripe_enabled', $stripeEnabled);
    setting_set('stripe_secret_key', $stripeSecretKey);
    setting_set('stripe_publishable_key', $stripePublishableKey);
    if ($stripeWebhookSecret !== '') {
        setting_set('stripe_webhook_secret', $stripeWebhookSecret);
    }

    audit_log('settings_save', null, '系统设置已更新');

    session_flash('flash_success', '设置已保存');
    redirect(base_url('admin/settings/index.php'));
}

$s = settings_all();
$siteName = isset($s['site_name']) ? $s['site_name'] : config('site.name');
$siteDesc = isset($s['site_description']) ? $s['site_description'] : config('site.description', '');
$notice = isset($s['notice']) ? $s['notice'] : '';
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
$affEnabled = isset($s['aff_enabled']) ? $s['aff_enabled'] : '0';
$affQuota = isset($s['aff_quota']) ? $s['aff_quota'] : '0';
$checkinEnabled = isset($s['checkin_enabled']) ? $s['checkin_enabled'] : '0';
$checkinReward = isset($s['checkin_reward']) ? $s['checkin_reward'] : '0';
$quotaRemindThreshold = isset($s['quota_remind_threshold']) ? $s['quota_remind_threshold'] : '0';
$emailVerifyRequired = isset($s['email_verify_required']) ? $s['email_verify_required'] : '0';
$smtpHost = isset($s['smtp_host']) ? $s['smtp_host'] : '';
$smtpPort = isset($s['smtp_port']) ? $s['smtp_port'] : '465';
$smtpUsername = isset($s['smtp_username']) ? $s['smtp_username'] : '';
$smtpEncryption = isset($s['smtp_encryption']) ? $s['smtp_encryption'] : 'ssl';
$smtpFrom = isset($s['smtp_from']) ? $s['smtp_from'] : '';
$smtpFromName = isset($s['smtp_from_name']) ? $s['smtp_from_name'] : '';
$smtpConfigured = isset($s['smtp_host']) ? $s['smtp_host'] : '';
$sensitiveWords = isset($s['sensitive_words']) ? $s['sensitive_words'] : '';
$maxRequestMb = isset($s['max_request_mb']) ? $s['max_request_mb'] : '0';
$ssrfAllowPrivate = isset($s['ssrf_allow_private']) ? $s['ssrf_allow_private'] : '0';
$cronSecret = isset($s['cron_secret']) ? $s['cron_secret'] : '';
$turnstileSiteKey = isset($s['turnstile_site_key']) ? $s['turnstile_site_key'] : '';
$turnstileSecretKey = isset($s['turnstile_secret_key']) ? $s['turnstile_secret_key'] : '';
$oauthGithubEnabled = isset($s['oauth_github_enabled']) ? $s['oauth_github_enabled'] : '0';
$oauthGithubId = isset($s['oauth_github_client_id']) ? $s['oauth_github_client_id'] : '';
$oauthGithubSecret = isset($s['oauth_github_client_secret']) ? $s['oauth_github_client_secret'] : '';
$oauthTelegramEnabled = isset($s['oauth_telegram_enabled']) ? $s['oauth_telegram_enabled'] : '0';
$oauthTelegramBotToken = isset($s['oauth_telegram_bot_token']) ? $s['oauth_telegram_bot_token'] : '';
$oauthTelegramUsername = isset($s['oauth_telegram_bot_username']) ? $s['oauth_telegram_bot_username'] : '';
$payRatio = isset($s['pay_ratio']) ? $s['pay_ratio'] : '1';
$epayEnabled = isset($s['epay_enabled']) ? $s['epay_enabled'] : '0';
$epayApiUrl = isset($s['epay_api_url']) ? $s['epay_api_url'] : '';
$epayPid = isset($s['epay_pid']) ? $s['epay_pid'] : '';
$epayKey = isset($s['epay_key']) ? $s['epay_key'] : '';
$stripeEnabled = isset($s['stripe_enabled']) ? $s['stripe_enabled'] : '0';
$stripeSecretKey = isset($s['stripe_secret_key']) ? $s['stripe_secret_key'] : '';
$stripePublishableKey = isset($s['stripe_publishable_key']) ? $s['stripe_publishable_key'] : '';
$stripeWebhookSecret = isset($s['stripe_webhook_secret']) ? $s['stripe_webhook_secret'] : '';
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
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">开放用户注册</label>
            <label class="ios-switch"><input type="checkbox" name="register_enabled" value="1" <?php echo $registerEnabled === '1' ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-group">
            <label>新用户默认额度（$）</label>
            <input type="number" name="default_quota" step="0.0001" min="0" class="form-control" value="<?php echo e($defaultQuota); ?>">
        </div>
        <div class="form-group">
            <label>余额告警阈值（$，用户余额低于此值时前台提示，0=关闭）</label>
            <input type="number" name="quota_remind_threshold" step="0.0001" min="0" class="form-control" value="<?php echo e($quotaRemindThreshold); ?>">
        </div>
        <div class="form-group">
            <label>系统公告（留空不展示，前台顶部显示）</label>
            <textarea name="notice" class="form-control" rows="3" placeholder="例如：本站已升级，新增 XX 模型……"><?php echo e($notice); ?></textarea>
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
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">连续失败自动停用渠道</label>
            <label class="ios-switch"><input type="checkbox" name="auto_disable" value="1" <?php echo $autoDisable === '1' ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-group">
            <label>自动停用阈值（失败次数）</label>
            <input type="number" name="auto_disable_threshold" min="1" class="form-control" value="<?php echo e($autoDisableThreshold); ?>">
        </div>
    </div>

    <div class="card" style="max-width:720px;">
        <div class="card-title">邀请与签到</div>
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">启用邀请奖励</label>
            <label class="ios-switch"><input type="checkbox" name="aff_enabled" value="1" <?php echo $affEnabled === '1' ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-group">
            <label>每成功邀请 1 人注册奖励（$，入邀请人待转余额）</label>
            <input type="number" name="aff_quota" step="0.0001" min="0" class="form-control" value="<?php echo e($affQuota); ?>">
        </div>
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">启用每日签到</label>
            <label class="ios-switch"><input type="checkbox" name="checkin_enabled" value="1" <?php echo $checkinEnabled === '1' ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-group">
            <label>每日签到奖励（$）</label>
            <input type="number" name="checkin_reward" step="0.0001" min="0" class="form-control" value="<?php echo e($checkinReward); ?>">
        </div>
    </div>

    <div class="card" style="max-width:720px;">
        <div class="card-title">邮件与邮箱验证</div>
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">注册要求邮箱验证（注册自动发送验证码）</label>
            <label class="ios-switch"><input type="checkbox" name="email_verify_required" value="1" <?php echo $emailVerifyRequired === '1' ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-hint" style="margin:-4px 0 14px;">未配置 SMTP 时验证码会写入 data/logs/mail.log（仅限开发调试）</div>
        <div class="form-group">
            <label>SMTP 服务器地址</label>
            <input type="text" name="smtp_host" class="form-control" value="<?php echo e($smtpHost); ?>" placeholder="如 smtp.qq.com（留空则使用日志模式）">
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;">
                <label>端口</label>
                <input type="number" name="smtp_port" min="1" class="form-control" value="<?php echo e($smtpPort); ?>">
            </div>
            <div style="flex:1;">
                <label>加密方式</label>
                <select name="smtp_encryption" class="form-control">
                    <option value="ssl" <?php echo $smtpEncryption === 'ssl' ? 'selected' : ''; ?>>SSL（465）</option>
                    <option value="tls" <?php echo $smtpEncryption === 'tls' ? 'selected' : ''; ?>>TLS（587）</option>
                    <option value="none" <?php echo $smtpEncryption === 'none' ? 'selected' : ''; ?>>无</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>SMTP 用户名</label>
            <input type="text" name="smtp_username" class="form-control" value="<?php echo e($smtpUsername); ?>" autocomplete="off">
        </div>
        <div class="form-group">
            <label>SMTP 密码（留空则不修改）</label>
            <input type="password" name="smtp_password" class="form-control" value="" autocomplete="new-password" placeholder="<?php echo $smtpConfigured !== '' ? '已配置，输入新值可覆盖' : '未配置'; ?>">
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;">
                <label>发件人邮箱</label>
                <input type="text" name="smtp_from" class="form-control" value="<?php echo e($smtpFrom); ?>">
            </div>
            <div style="flex:1;">
                <label>发件人名称</label>
                <input type="text" name="smtp_from_name" class="form-control" value="<?php echo e($smtpFromName); ?>">
            </div>
        </div>
        <div class="form-actions">
            <a class="btn btn-secondary" href="<?php echo base_url('admin/settings/mail-test.php'); ?>" target="_blank">发送测试邮件</a>
        </div>
    </div>

    <div class="card" style="max-width:720px;">
        <div class="card-title">内容安全与风控</div>
        <div class="form-group">
            <label>敏感词（每行一个，命中即拦截请求并返回错误；响应命中则打码）</label>
            <textarea name="sensitive_words" class="form-control" rows="5" placeholder="例如：&#10;违法词汇1&#10;违法词汇2"><?php echo e($sensitiveWords); ?></textarea>
        </div>
        <div class="form-group">
            <label>API 请求体大小上限（MB，0=不限制，超过返回 413）</label>
            <input type="number" name="max_request_mb" min="0" class="form-control" value="<?php echo e($maxRequestMb); ?>">
        </div>
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">允许渠道访问内网地址（SSRF 防护开关）</label>
            <label class="ios-switch"><input type="checkbox" name="ssrf_allow_private" value="1" <?php echo $ssrfAllowPrivate === '1' ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-hint" style="margin-top:0;">默认拦截 10.x / 192.168.x / 127.x 等内网地址，防止渠道 base_url 被滥用做 SSRF；本地 mock 调试（127.0.0.1:9001）需开启</div>
        <hr style="border:none; border-top:1px solid var(--border); margin:16px 0;">
        <div class="form-group">
            <label>定时任务访问密钥（cron_secret，留空则 CLI 专用）</label>
            <input type="text" name="cron_secret" class="form-control" value="<?php echo e($cronSecret); ?>" placeholder="配置后 HTTP 访问需带 ?key= 参数">
            <div class="form-hint">HTTP 方式：<?php echo e(base_url('tools/cron.php')); ?>?key=你的密钥；配置前仅可通过服务器命令行执行</div>
        </div>
    </div>

    <div class="card" style="max-width:720px;">
        <div class="card-title">人机验证（Cloudflare Turnstile）</div>
        <div class="form-hint" style="margin-top:0;">登录/注册/找回密码页面接入；密钥留空则跳过验证</div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;">
                <label>站点密钥（Site Key）</label>
                <input type="text" name="turnstile_site_key" class="form-control" value="<?php echo e($turnstileSiteKey); ?>">
            </div>
            <div style="flex:1;">
                <label>密钥（Secret Key）</label>
                <input type="text" name="turnstile_secret_key" class="form-control" value="<?php echo e($turnstileSecretKey); ?>">
            </div>
        </div>
    </div>

    <div class="card" style="max-width:720px;">
        <div class="card-title">第三方登录（OAuth）</div>
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">启用 GitHub 登录</label>
            <label class="ios-switch"><input type="checkbox" name="oauth_github_enabled" value="1" <?php echo $oauthGithubEnabled === '1' ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;">
                <label>GitHub Client ID</label>
                <input type="text" name="oauth_github_client_id" class="form-control" value="<?php echo e($oauthGithubId); ?>">
            </div>
            <div style="flex:1;">
                <label>GitHub Client Secret</label>
                <input type="text" name="oauth_github_client_secret" class="form-control" value="<?php echo e($oauthGithubSecret); ?>">
            </div>
        </div>
        <hr style="border:none; border-top:1px solid var(--border); margin:16px 0;">
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">启用 Telegram 登录</label>
            <label class="ios-switch"><input type="checkbox" name="oauth_telegram_enabled" value="1" <?php echo $oauthTelegramEnabled === '1' ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;">
                <label>Telegram Bot Token</label>
                <input type="text" name="oauth_telegram_bot_token" class="form-control" value="<?php echo e($oauthTelegramBotToken); ?>">
            </div>
            <div style="flex:1;">
                <label>Bot 用户名</label>
                <input type="text" name="oauth_telegram_bot_username" class="form-control" value="<?php echo e($oauthTelegramUsername); ?>" placeholder="如 my_bot">
            </div>
        </div>
    </div>

    <div class="card" style="max-width:720px;">
        <div class="card-title">在线支付</div>
        <div class="form-group">
            <label>充值倍率（到账额度 = 支付金额 × 倍率）</label>
            <input type="number" name="pay_ratio" step="0.01" min="0" class="form-control" value="<?php echo e($payRatio); ?>">
        </div>
        <hr style="border:none; border-top:1px solid var(--border); margin:16px 0;">
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">启用易支付</label>
            <label class="ios-switch"><input type="checkbox" name="epay_enabled" value="1" <?php echo $epayEnabled === '1' ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;">
                <label>易支付网关地址</label>
                <input type="text" name="epay_api_url" class="form-control" value="<?php echo e($epayApiUrl); ?>" placeholder="如 https://pay.example.com">
            </div>
            <div style="flex:1;">
                <label>商户 PID</label>
                <input type="text" name="epay_pid" class="form-control" value="<?php echo e($epayPid); ?>">
            </div>
        </div>
        <div class="form-group">
            <label>商户密钥（Key）</label>
            <input type="text" name="epay_key" class="form-control" value="<?php echo e($epayKey); ?>">
        </div>
        <hr style="border:none; border-top:1px solid var(--border); margin:16px 0;">
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">启用 Stripe</label>
            <label class="ios-switch"><input type="checkbox" name="stripe_enabled" value="1" <?php echo $stripeEnabled === '1' ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;">
                <label>Stripe Secret Key</label>
                <input type="text" name="stripe_secret_key" class="form-control" value="<?php echo e($stripeSecretKey); ?>">
            </div>
            <div style="flex:1;">
                <label>Stripe Publishable Key</label>
                <input type="text" name="stripe_publishable_key" class="form-control" value="<?php echo e($stripePublishableKey); ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Stripe Webhook 密钥（whsec_…，留空则不启用 Webhook）</label>
            <input type="text" name="stripe_webhook_secret" class="form-control" value="<?php echo e($stripeWebhookSecret); ?>">
            <div class="form-hint">Webhook 地址：<?php echo e(base_url('api/pay/stripe_webhook.php')); ?></div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">保存设置</button>
    </div>
</form>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>