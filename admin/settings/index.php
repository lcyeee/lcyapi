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
    $oauthDiscordEnabled = empty($_POST['oauth_discord_enabled']) ? '0' : '1';
    $oauthDiscordId = mb_substr(trim($_POST['oauth_discord_client_id'] ?? ''), 0, 100);
    $oauthDiscordSecret = mb_substr(trim($_POST['oauth_discord_client_secret'] ?? ''), 0, 200);
    $oauthLinuxdoEnabled = empty($_POST['oauth_linuxdo_enabled']) ? '0' : '1';
    $oauthLinuxdoId = mb_substr(trim($_POST['oauth_linuxdo_client_id'] ?? ''), 0, 100);
    $oauthLinuxdoSecret = mb_substr(trim($_POST['oauth_linuxdo_client_secret'] ?? ''), 0, 200);
    $oauthLinuxdoBaseUrl = mb_substr(trim($_POST['oauth_linuxdo_base_url'] ?? ''), 0, 200);
    $oauthOidcEnabled = empty($_POST['oauth_oidc_enabled']) ? '0' : '1';
    $oauthOidcIssuer = mb_substr(trim($_POST['oauth_oidc_issuer'] ?? ''), 0, 200);
    $oauthOidcId = mb_substr(trim($_POST['oauth_oidc_client_id'] ?? ''), 0, 100);
    $oauthOidcSecret = mb_substr(trim($_POST['oauth_oidc_client_secret'] ?? ''), 0, 200);
    $oauthWechatEnabled = empty($_POST['oauth_wechat_enabled']) ? '0' : '1';
    $oauthWechatAppId = mb_substr(trim($_POST['oauth_wechat_app_id'] ?? ''), 0, 100);
    $oauthWechatSecret = mb_substr(trim($_POST['oauth_wechat_app_secret'] ?? ''), 0, 200);
    if (isset($_POST['confirm_payment_compliance']) && $_POST['confirm_payment_compliance'] === '1') {
        PayOrder::confirmCompliance(Auth::id());
    }
    $payRatio = max(0, (float)($_POST['pay_ratio'] ?? 1));
    $epayEnabled = empty($_POST['epay_enabled']) ? '0' : '1';
    $epayApiUrl = rtrim(mb_substr(trim($_POST['epay_api_url'] ?? ''), 0, 200), '/');
    $epayPid = mb_substr(trim($_POST['epay_pid'] ?? ''), 0, 50);
    $epayKey = mb_substr(trim($_POST['epay_key'] ?? ''), 0, 100);
    $stripeEnabled = empty($_POST['stripe_enabled']) ? '0' : '1';
    $stripeSecretKey = mb_substr(trim($_POST['stripe_secret_key'] ?? ''), 0, 200);
    $stripePublishableKey = mb_substr(trim($_POST['stripe_publishable_key'] ?? ''), 0, 200);
    $stripeWebhookSecret = mb_substr(trim($_POST['stripe_webhook_secret'] ?? ''), 0, 200);
    $quotaDisplayType = in_array($_POST['quota_display_type'] ?? 'USD', ['USD', 'CNY', 'TOKENS', 'CUSTOM'], true) ? $_POST['quota_display_type'] : 'USD';
    $customSymbol = mb_substr(trim($_POST['custom_currency_symbol'] ?? ''), 0, 20);
    $customRate = max(0.0001, (float)($_POST['custom_currency_rate'] ?? 1));
    $maxUserTokens = max(0, (int)($_POST['max_user_tokens'] ?? 0));
    $topupAmounts = implode(',', array_filter(array_map(function ($v) {
        $n = (float)$v;
        return $n > 0 ? rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') : '';
    }, explode(',', str_replace(array('，', ' ', "\n"), ',', trim($_POST['topup_amounts'] ?? '')))), function ($v) { return $v !== ''; }));
    $topupDiscount = max(0.1, min(1, (float)($_POST['topup_discount'] ?? 1)));
    $selfUseMode = empty($_POST['self_use_mode']) ? '0' : '1';
    $faqEnabled = empty($_POST['faq_enabled']) ? '0' : '1';
    $faqContent = mb_substr(trim($_POST['faq_content'] ?? ''), 0, 20000);
    $checkinBonusStep = max(0, (float)($_POST['checkin_bonus_step'] ?? 0));
    $autoDisableStatusCodes = mb_substr(trim($_POST['auto_disable_status_codes'] ?? ''), 0, 200);
    $autoDisableKeywords = mb_substr(trim($_POST['auto_disable_keywords'] ?? ''), 0, 500);
    $retryStatusCodes = mb_substr(trim($_POST['retry_status_codes'] ?? ''), 0, 200);
    $modelRateLimit = max(0, (int)($_POST['model_rate_limit'] ?? 0));
    $modelRateWindow = max(1, (int)($_POST['model_rate_limit_window'] ?? 60));
    $modelSuccessRateLimit = max(0, (int)($_POST['model_success_rate_limit'] ?? 0));
    $freeModelSkipDeduct = empty($_POST['free_model_skip_deduct']) ? '0' : '1';
    $toolPrices = mb_substr(trim($_POST['tool_prices'] ?? ''), 0, 2000);
    $trustedRedirectDomains = mb_substr(trim($_POST['trusted_redirect_domains'] ?? ''), 0, 500);
    $notifyRateLimit = max(1, (int)($_POST['notify_rate_limit'] ?? 60));
    $totpMaxFails = max(1, (int)($_POST['totp_max_fails'] ?? 5));
    $totpLockMinutes = max(1, (int)($_POST['totp_lock_minutes'] ?? 5));
    $maxActiveSessions = max(0, (int)($_POST['max_active_sessions'] ?? 0));
    $emailDomainRestriction = empty($_POST['email_domain_restriction']) ? '0' : '1';
    $emailDomainWhitelist = mb_substr(trim($_POST['email_domain_whitelist'] ?? ''), 0, 500);
    $emailAliasRestriction = empty($_POST['email_alias_restriction']) ? '0' : '1';
    $trustQuota = max(0, (float)($_POST['trust_quota'] ?? 0));
    $checkinBonusMaxDays = max(1, (int)($_POST['checkin_bonus_max_days'] ?? 7));
    $checkinRandomReward = empty($_POST['checkin_random_reward']) ? '0' : '1';
    $checkinRewardMin = max(0, (float)($_POST['checkin_reward_min'] ?? 0));
    $checkinRewardMax = max(0, (float)($_POST['checkin_reward_max'] ?? 0));
    $smtpAuth = in_array($_POST['smtp_auth'] ?? 'auto', ['auto', 'plain', 'login'], true) ? $_POST['smtp_auth'] : 'auto';
    $oauthAllowRegister = empty($_POST['oauth_allow_register']) ? '0' : '1';
    $oauthAllowedDomains = mb_substr(trim($_POST['oauth_allowed_domains'] ?? ''), 0, 500);
    $ratioSyncUrl = mb_substr(trim($_POST['ratio_sync_url'] ?? ''), 0, 500);

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
    setting_set('oauth_discord_enabled', $oauthDiscordEnabled);
    setting_set('oauth_discord_client_id', $oauthDiscordId);
    setting_set('oauth_discord_client_secret', $oauthDiscordSecret);
    setting_set('oauth_linuxdo_enabled', $oauthLinuxdoEnabled);
    setting_set('oauth_linuxdo_client_id', $oauthLinuxdoId);
    setting_set('oauth_linuxdo_client_secret', $oauthLinuxdoSecret);
    setting_set('oauth_linuxdo_base_url', $oauthLinuxdoBaseUrl);
    setting_set('oauth_oidc_enabled', $oauthOidcEnabled);
    setting_set('oauth_oidc_issuer', $oauthOidcIssuer);
    setting_set('oauth_oidc_client_id', $oauthOidcId);
    setting_set('oauth_oidc_client_secret', $oauthOidcSecret);
    setting_set('oauth_wechat_enabled', $oauthWechatEnabled);
    setting_set('oauth_wechat_app_id', $oauthWechatAppId);
    setting_set('oauth_wechat_app_secret', $oauthWechatSecret);
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
    setting_set('quota_display_type', $quotaDisplayType);
    setting_set('custom_currency_symbol', $customSymbol);
    setting_set('custom_currency_rate', (string)$customRate);
    setting_set('max_user_tokens', (string)$maxUserTokens);
    setting_set('topup_amounts', $topupAmounts);
    setting_set('topup_discount', (string)$topupDiscount);
    setting_set('self_use_mode', $selfUseMode);
    setting_set('faq_enabled', $faqEnabled);
    setting_set('faq_content', $faqContent);
    setting_set('checkin_bonus_step', (string)$checkinBonusStep);
    setting_set('auto_disable_status_codes', $autoDisableStatusCodes);
    setting_set('auto_disable_keywords', $autoDisableKeywords);
    setting_set('retry_status_codes', $retryStatusCodes);
    setting_set('model_rate_limit', (string)$modelRateLimit);
    setting_set('model_rate_limit_window', (string)$modelRateWindow);
    setting_set('model_success_rate_limit', (string)$modelSuccessRateLimit);
    setting_set('free_model_skip_deduct', $freeModelSkipDeduct);
    setting_set('tool_prices', $toolPrices);
    setting_set('trusted_redirect_domains', $trustedRedirectDomains);
    setting_set('notify_rate_limit', (string)$notifyRateLimit);
    setting_set('totp_max_fails', (string)$totpMaxFails);
    setting_set('totp_lock_minutes', (string)$totpLockMinutes);
    setting_set('max_active_sessions', (string)$maxActiveSessions);
    setting_set('email_domain_restriction', $emailDomainRestriction);
    setting_set('email_domain_whitelist', $emailDomainWhitelist);
    setting_set('email_alias_restriction', $emailAliasRestriction);
    setting_set('trust_quota', (string)$trustQuota);
    setting_set('checkin_bonus_max_days', (string)$checkinBonusMaxDays);
    setting_set('checkin_random_reward', $checkinRandomReward);
    setting_set('checkin_reward_min', (string)$checkinRewardMin);
    setting_set('checkin_reward_max', (string)$checkinRewardMax);
    setting_set('smtp_auth', $smtpAuth);
    setting_set('oauth_allow_register', $oauthAllowRegister);
    setting_set('oauth_allowed_domains', $oauthAllowedDomains);
    setting_set('ratio_sync_url', $ratioSyncUrl);
    $mjEnabled = empty($_POST['midjourney_enabled']) ? '0' : '1';
    $mjProxyUrl = rtrim(mb_substr(trim($_POST['midjourney_proxy_url'] ?? ''), 0, 200), '/');
    $mjApiKey = mb_substr(trim($_POST['midjourney_api_key'] ?? ''), 0, 200);
    $mjNotifyHook = rtrim(mb_substr(trim($_POST['midjourney_notify_hook'] ?? ''), 0, 200), '/');
    $mjCharge = max(0, (float)($_POST['midjourney_charge'] ?? 0));
    $mjModeClear = empty($_POST['midjourney_mode_clear']) ? '0' : '1';

    setting_set('midjourney_enabled', $mjEnabled);
    setting_set('midjourney_proxy_url', $mjProxyUrl);
    if ($mjApiKey !== '') {
        setting_set('midjourney_api_key', $mjApiKey);
    }
    setting_set('midjourney_notify_hook', $mjNotifyHook);
    setting_set('midjourney_charge', (string)$mjCharge);
    setting_set('midjourney_mode_clear', $mjModeClear);
    $sunoEnabled = empty($_POST['suno_enabled']) ? '0' : '1';
    $sunoProxyUrl = rtrim(mb_substr(trim($_POST['suno_proxy_url'] ?? ''), 0, 200), '/');
    $sunoApiKey = mb_substr(trim($_POST['suno_api_key'] ?? ''), 0, 200);
    $sunoCharge = max(0, (float)($_POST['suno_charge'] ?? 0));

    setting_set('suno_enabled', $sunoEnabled);
    setting_set('suno_proxy_url', $sunoProxyUrl);
    if ($sunoApiKey !== '') {
        setting_set('suno_api_key', $sunoApiKey);
    }
    setting_set('suno_charge', (string)$sunoCharge);

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
$smtpAuth = isset($s['smtp_auth']) ? $s['smtp_auth'] : 'auto';
$checkinRandomReward = isset($s['checkin_random_reward']) ? $s['checkin_random_reward'] : '0';
$checkinRewardMin = isset($s['checkin_reward_min']) ? $s['checkin_reward_min'] : '0';
$checkinRewardMax = isset($s['checkin_reward_max']) ? $s['checkin_reward_max'] : '0';
$oauthAllowRegister = isset($s['oauth_allow_register']) ? $s['oauth_allow_register'] : '1';
$oauthAllowedDomains = isset($s['oauth_allowed_domains']) ? $s['oauth_allowed_domains'] : '';
$ratioSyncUrl = isset($s['ratio_sync_url']) ? $s['ratio_sync_url'] : '';
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
$quotaDisplayType = isset($s['quota_display_type']) ? $s['quota_display_type'] : 'USD';
$customSymbol = isset($s['custom_currency_symbol']) ? $s['custom_currency_symbol'] : '';
$customRate = isset($s['custom_currency_rate']) ? $s['custom_currency_rate'] : '1';
$maxUserTokens = isset($s['max_user_tokens']) ? $s['max_user_tokens'] : '0';
$topupAmounts = isset($s['topup_amounts']) ? $s['topup_amounts'] : '5,10,20,50,100';
$topupDiscount = isset($s['topup_discount']) ? $s['topup_discount'] : '1';
$selfUseMode = isset($s['self_use_mode']) ? $s['self_use_mode'] : '0';
$faqEnabled = isset($s['faq_enabled']) ? $s['faq_enabled'] : '0';
$faqContent = isset($s['faq_content']) ? $s['faq_content'] : '';
$checkinBonusStep = isset($s['checkin_bonus_step']) ? $s['checkin_bonus_step'] : '0';
$autoDisableStatusCodes = isset($s['auto_disable_status_codes']) ? $s['auto_disable_status_codes'] : '';
$autoDisableKeywords = isset($s['auto_disable_keywords']) ? $s['auto_disable_keywords'] : '';
$retryStatusCodes = isset($s['retry_status_codes']) ? $s['retry_status_codes'] : '';
$mjEnabled = isset($s['midjourney_enabled']) ? $s['midjourney_enabled'] : '0';
$mjProxyUrl = isset($s['midjourney_proxy_url']) ? $s['midjourney_proxy_url'] : '';
$mjApiKey = isset($s['midjourney_api_key']) ? $s['midjourney_api_key'] : '';
$mjNotifyHook = isset($s['midjourney_notify_hook']) ? $s['midjourney_notify_hook'] : '';
$mjCharge = isset($s['midjourney_charge']) ? $s['midjourney_charge'] : '0';
$mjModeClear = isset($s['midjourney_mode_clear']) ? $s['midjourney_mode_clear'] : '0';
$sunoEnabled = isset($s['suno_enabled']) ? $s['suno_enabled'] : '0';
$sunoProxyUrl = isset($s['suno_proxy_url']) ? $s['suno_proxy_url'] : '';
$sunoApiKey = isset($s['suno_api_key']) ? $s['suno_api_key'] : '';
$sunoCharge = isset($s['suno_charge']) ? $s['suno_charge'] : '0';
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
        <div class="card-title">额度显示</div>
        <div class="form-group">
            <label>额度展示类型</label>
            <select name="quota_display_type" class="form-control">
                <option value="USD" <?php echo $quotaDisplayType === 'USD' ? 'selected' : ''; ?>>美元 $</option>
                <option value="CNY" <?php echo $quotaDisplayType === 'CNY' ? 'selected' : ''; ?>>人民币 ¥（按汇率换算显示）</option>
                <option value="TOKENS" <?php echo $quotaDisplayType === 'TOKENS' ? 'selected' : ''; ?>>积分（按汇率换算显示）</option>
                <option value="CUSTOM" <?php echo $quotaDisplayType === 'CUSTOM' ? 'selected' : ''; ?>>自定义符号</option>
            </select>
            <div class="form-hint">库内仍以美元结算，仅影响界面显示；余额展示 = 美元值 × 汇率</div>
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;">
                <label>自定义符号（CUSTOM 时使用，如 ¥、积分）</label>
                <input type="text" name="custom_currency_symbol" class="form-control" value="<?php echo e($customSymbol); ?>">
            </div>
            <div style="flex:1;">
                <label>汇率（1 USD = ? 单位）</label>
                <input type="number" name="custom_currency_rate" step="0.0001" min="0.0001" class="form-control" value="<?php echo e($customRate); ?>">
            </div>
        </div>
        <div class="form-group">
            <label>单用户令牌数上限（0 = 不限制）</label>
            <input type="number" name="max_user_tokens" min="0" class="form-control" value="<?php echo e($maxUserTokens); ?>">
            <div class="form-hint">用户个人中心创建令牌时的数量上限，防止滥用。</div>
        </div>
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
        <div class="form-group">
            <label>可重试状态码（留空默认 500-599 可重试，支持区间如 500,502-504,529）</label>
            <input type="text" name="retry_status_codes" class="form-control" value="<?php echo e($retryStatusCodes); ?>" placeholder="502,503,504,529">
            <div class="form-hint">命中列表内的上游状态码才触发重试与下一个渠道，业务 4xx 一律不重试。</div>
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;">
                <label>模型级限流（次数/窗口）</label>
                <input type="number" name="model_rate_limit" min="0" class="form-control" value="<?php echo e(isset($s['model_rate_limit']) ? $s['model_rate_limit'] : '0'); ?>" placeholder="0=关闭">
            </div>
            <div style="flex:1;">
                <label>限流窗口（秒）</label>
                <input type="number" name="model_rate_limit_window" min="1" class="form-control" value="<?php echo e(isset($s['model_rate_limit_window']) ? $s['model_rate_limit_window'] : '60'); ?>">
            </div>
        </div>
        <div class="form-group">
            <label>成功请求限流（次数/窗口，0=关闭）</label>
            <input type="number" name="model_success_rate_limit" min="0" class="form-control" value="<?php echo e(isset($s['model_success_rate_limit']) ? $s['model_success_rate_limit'] : '0'); ?>">
        </div>
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">免费模型跳过预扣费</label>
            <label class="ios-switch"><input type="checkbox" name="free_model_skip_deduct" value="1" <?php echo (isset($s['free_model_skip_deduct']) && $s['free_model_skip_deduct'] === '1') ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-group">
            <label>工具调用价格（JSON：{工具名:价格}，支持前缀如 web_search:gpt-4o*）</label>
            <textarea name="tool_prices" class="form-control" rows="2" placeholder='{"web_search":0.005,"web_search_preview":0.005,"file_search":0.002}'><?php echo e(isset($s['tool_prices']) ? $s['tool_prices'] : ''); ?></textarea>
        </div>
        <div class="form-group">
            <label>立即停用状态码（命中即停用该渠道，支持区间，留空仅按失败阈值）</label>
            <input type="text" name="auto_disable_status_codes" class="form-control" value="<?php echo e($autoDisableStatusCodes); ?>" placeholder="429,401">
            <div class="form-hint">如上游返回 401 密钥失效，渠道立即停用等人工处理。</div>
        </div>
        <div class="form-group">
            <label>立即停用错误关键词（逗号分隔，命中上游错误文本即停用）</label>
            <input type="text" name="auto_disable_keywords" class="form-control" value="<?php echo e($autoDisableKeywords); ?>" placeholder="invalid api key,insufficient quota">
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
        <div class="form-group">
            <label>连续签到每日加成（$，封顶 7 天）</label>
            <input type="number" name="checkin_bonus_step" step="0.0001" min="0" class="form-control" value="<?php echo e($checkinBonusStep); ?>" placeholder="如 0.01：第 2 天 +0.01、第 3 天 +0.02……">
            <div class="form-hint">连续签到第 N 天奖励 = 基础奖励 + (N-1)×加成；中断后重新从第 1 天计算。</div>
        </div>
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">随机签到奖励（每天在下方区间内随机）</label>
            <label class="ios-switch"><input type="checkbox" name="checkin_random_reward" value="1" <?php echo $checkinRandomReward === '1' ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;">
                <label>奖励下限（$）</label>
                <input type="number" name="checkin_reward_min" step="0.0001" min="0" class="form-control" value="<?php echo e($checkinRewardMin); ?>">
            </div>
            <div style="flex:1;">
                <label>奖励上限（$）</label>
                <input type="number" name="checkin_reward_max" step="0.0001" min="0" class="form-control" value="<?php echo e($checkinRewardMax); ?>">
            </div>
        </div>
        <div class="form-group">
            <label>连续签到加成封顶天数</label>
            <input type="number" name="checkin_bonus_max_days" min="1" class="form-control" value="<?php echo e(isset($s['checkin_bonus_max_days']) ? $s['checkin_bonus_max_days'] : '7'); ?>">
            <div class="form-hint">连续签到第 N 天的递增加成最多计算到该天数，之后不再增长。</div>
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
            <div style="flex:1;">
                <label>认证方式</label>
                <select name="smtp_auth" class="form-control">
                    <option value="auto" <?php echo $smtpAuth === 'auto' ? 'selected' : ''; ?>>自动（推荐）</option>
                    <option value="plain" <?php echo $smtpAuth === 'plain' ? 'selected' : ''; ?>>PLAIN（Outlook/Office365）</option>
                    <option value="login" <?php echo $smtpAuth === 'login' ? 'selected' : ''; ?>>LOGIN</option>
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
        <div class="form-group">
            <label>信任重定向域名（逗号分隔，OAuth 回调验证，留空=不限制）</label>
            <input type="text" name="trusted_redirect_domains" class="form-control" value="<?php echo e(isset($s['trusted_redirect_domains']) ? $s['trusted_redirect_domains'] : ''); ?>" placeholder="example.com,sub.example.com">
        </div>
        <div class="form-group">
            <label>通知频率限制（次数/小时/用户/类型）</label>
            <input type="number" name="notify_rate_limit" min="1" class="form-control" value="<?php echo e(isset($s['notify_rate_limit']) ? $s['notify_rate_limit'] : '60'); ?>">
            <div class="form-hint">按「用户+通知类型」独立统计，超过后该类型通知暂停发送。</div>
        </div>
        <div class="form-group">
            <label>模型倍率同步地址（ratio_sync_url，JSON：{"模型名":{"input":每1M输入价,"output":每1M输出价}}）</label>
            <input type="text" name="ratio_sync_url" class="form-control" value="<?php echo e($ratioSyncUrl); ?>" placeholder="https://example.com/model-prices.json">
            <div class="form-hint">由定时任务 sync_ratios 拉取并批量更新 models 表价格，留空则跳过。</div>
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;">
                <label>2FA 最大失败次数</label>
                <input type="number" name="totp_max_fails" min="1" class="form-control" value="<?php echo e(isset($s['totp_max_fails']) ? $s['totp_max_fails'] : '5'); ?>">
            </div>
            <div style="flex:1;">
                <label>2FA 锁定时间（分钟）</label>
                <input type="number" name="totp_lock_minutes" min="1" class="form-control" value="<?php echo e(isset($s['totp_lock_minutes']) ? $s['totp_lock_minutes'] : '5'); ?>">
            </div>
        </div>
        <div class="form-group">
            <label>每用户最大活跃会话数（0=不限制）</label>
            <input type="number" name="max_active_sessions" min="0" class="form-control" value="<?php echo e(isset($s['max_active_sessions']) ? $s['max_active_sessions'] : '0'); ?>">
        </div>
        <div class="form-group">
            <label>信任额度旁路（用户余额≥此值跳过预扣费，0=关闭）</label>
            <input type="number" name="trust_quota" step="0.01" min="0" class="form-control" value="<?php echo e(isset($s['trust_quota']) ? $s['trust_quota'] : '0'); ?>">
        </div>
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">邮箱域名白名单限制</label>
            <label class="ios-switch"><input type="checkbox" name="email_domain_restriction" value="1" <?php echo (isset($s['email_domain_restriction']) && $s['email_domain_restriction'] === '1') ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-group">
            <label>允许注册的邮箱域名（逗号分隔，需开启上方开关）</label>
            <input type="text" name="email_domain_whitelist" class="form-control" value="<?php echo e(isset($s['email_domain_whitelist']) ? $s['email_domain_whitelist'] : ''); ?>" placeholder="example.com,company.com">
        </div>
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">邮箱别名限制（禁止 + 和 . 别名）</label>
            <label class="ios-switch"><input type="checkbox" name="email_alias_restriction" value="1" <?php echo (isset($s['email_alias_restriction']) && $s['email_alias_restriction'] === '1') ? 'checked' : ''; ?>><span></span></label>
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
            <label style="margin:0;">允许通过第三方登录自动注册新账号</label>
            <label class="ios-switch"><input type="checkbox" name="oauth_allow_register" value="1" <?php echo $oauthAllowRegister === '1' ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-group">
            <label>允许注册的邮箱域名（逗号分隔，仅限有邮箱的提供商，空=不限制）</label>
            <input type="text" name="oauth_allowed_domains" class="form-control" value="<?php echo e($oauthAllowedDomains); ?>" placeholder="github.com,example.com">
            <div class="form-hint">Telegram 等无邮箱提供商不受此限制；未绑定过的第三方账号只能登录已有账号。</div>
        </div>
        <hr style="border:none; border-top:1px solid var(--border); margin:16px 0;">
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
        <hr style="border:none; border-top:1px solid var(--border); margin:16px 0;">
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">启用 Discord 登录</label>
            <label class="ios-switch"><input type="checkbox" name="oauth_discord_enabled" value="1" <?php echo (isset($s['oauth_discord_enabled']) && $s['oauth_discord_enabled'] === '1') ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;"><label>Discord Client ID</label><input type="text" name="oauth_discord_client_id" class="form-control" value="<?php echo e(isset($s['oauth_discord_client_id']) ? $s['oauth_discord_client_id'] : ''); ?>"></div>
            <div style="flex:1;"><label>Discord Client Secret</label><input type="text" name="oauth_discord_client_secret" class="form-control" value="<?php echo e(isset($s['oauth_discord_client_secret']) ? $s['oauth_discord_client_secret'] : ''); ?>"></div>
        </div>
        <hr style="border:none; border-top:1px solid var(--border); margin:16px 0;">
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">启用 LinuxDO 登录</label>
            <label class="ios-switch"><input type="checkbox" name="oauth_linuxdo_enabled" value="1" <?php echo (isset($s['oauth_linuxdo_enabled']) && $s['oauth_linuxdo_enabled'] === '1') ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;"><label>LinuxDO Client ID</label><input type="text" name="oauth_linuxdo_client_id" class="form-control" value="<?php echo e(isset($s['oauth_linuxdo_client_id']) ? $s['oauth_linuxdo_client_id'] : ''); ?>"></div>
            <div style="flex:1;"><label>LinuxDO Client Secret</label><input type="text" name="oauth_linuxdo_client_secret" class="form-control" value="<?php echo e(isset($s['oauth_linuxdo_client_secret']) ? $s['oauth_linuxdo_client_secret'] : ''); ?>"></div>
        </div>
        <div class="form-group"><label>LinuxDO Base URL</label><input type="text" name="oauth_linuxdo_base_url" class="form-control" value="<?php echo e(isset($s['oauth_linuxdo_base_url']) ? $s['oauth_linuxdo_base_url'] : 'https://connect.linux.do'); ?>"></div>
        <hr style="border:none; border-top:1px solid var(--border); margin:16px 0;">
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">启用 OIDC 登录</label>
            <label class="ios-switch"><input type="checkbox" name="oauth_oidc_enabled" value="1" <?php echo (isset($s['oauth_oidc_enabled']) && $s['oauth_oidc_enabled'] === '1') ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;"><label>OIDC Issuer URL</label><input type="text" name="oauth_oidc_issuer" class="form-control" value="<?php echo e(isset($s['oauth_oidc_issuer']) ? $s['oauth_oidc_issuer'] : ''); ?>" placeholder="https://auth.example.com"></div>
            <div style="flex:1;"><label>OIDC Client ID</label><input type="text" name="oauth_oidc_client_id" class="form-control" value="<?php echo e(isset($s['oauth_oidc_client_id']) ? $s['oauth_oidc_client_id'] : ''); ?>"></div>
        </div>
        <div class="form-group"><label>OIDC Client Secret</label><input type="text" name="oauth_oidc_client_secret" class="form-control" value="<?php echo e(isset($s['oauth_oidc_client_secret']) ? $s['oauth_oidc_client_secret'] : ''); ?>"></div>
        <hr style="border:none; border-top:1px solid var(--border); margin:16px 0;">
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">启用微信扫码登录</label>
            <label class="ios-switch"><input type="checkbox" name="oauth_wechat_enabled" value="1" <?php echo (isset($s['oauth_wechat_enabled']) && $s['oauth_wechat_enabled'] === '1') ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;"><label>微信 App ID</label><input type="text" name="oauth_wechat_app_id" class="form-control" value="<?php echo e(isset($s['oauth_wechat_app_id']) ? $s['oauth_wechat_app_id'] : ''); ?>"></div>
            <div style="flex:1;"><label>微信 App Secret</label><input type="text" name="oauth_wechat_app_secret" class="form-control" value="<?php echo e(isset($s['oauth_wechat_app_secret']) ? $s['oauth_wechat_app_secret'] : ''); ?>"></div>
        </div>
        <hr style="border:none; border-top:1px solid var(--border); margin:16px 0;">
        <div class="form-group"><label>支付合规确认</label>
            <?php if (PayOrder::paymentComplianceConfirmed()) : ?>
                <div class="alert alert-success">已确认（<?php echo e(isset($s['payment_compliance_at']) ? $s['payment_compliance_at'] : '-'); ?>）</div>
            <?php else : ?>
                <div class="alert alert-warning">尚未确认支付合规条款。启用支付前请确认。</div>
                <button type="button" class="btn btn-sm" onclick="confirmCompliance()">确认合规</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="max-width:720px;">
        <div class="card-title">在线支付</div>
        <div class="form-group">
            <label>充值倍率（到账额度 = 支付金额 × 倍率）</label>
            <input type="number" name="pay_ratio" step="0.01" min="0" class="form-control" value="<?php echo e($payRatio); ?>">
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;">
                <label>快速充值档位（逗号分隔，留空则不显示档位）</label>
                <input type="text" name="topup_amounts" class="form-control" value="<?php echo e($topupAmounts); ?>" placeholder="5,10,20,50,100">
            </div>
            <div style="flex:1;">
                <label>首充/活动折扣（0-1，1 无折扣，0.9 即 9 折）</label>
                <input type="number" name="topup_discount" step="0.01" min="0.1" max="1" class="form-control" value="<?php echo e($topupDiscount); ?>">
                <div class="form-hint">到账额度 = 支付金额 × 倍率 × 折扣</div>
            </div>
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

    <div class="card" style="max-width:720px;">
        <div class="card-title">Midjourney 绘图</div>
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">启用 Midjourney（外部 MJ-Proxy 服务）</label>
            <label class="ios-switch"><input type="checkbox" name="midjourney_enabled" value="1" <?php echo $mjEnabled === '1' ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;">
                <label>MJ-Proxy 服务地址</label>
                <input type="text" name="midjourney_proxy_url" class="form-control" value="<?php echo e($mjProxyUrl); ?>" placeholder="如 https://mj.example.com">
                <div class="form-hint">需支持 /mj/submit/{action} 与 /mj/task/{id}/fetch 接口</div>
            </div>
            <div style="flex:1;">
                <label>mj-api-secret 密钥</label>
                <input type="text" name="midjourney_api_key" class="form-control" value="" placeholder="留空保持不变">
                <div class="form-hint">当前已配置：<?php echo $mjApiKey !== '' ? '是（' . strlen($mjApiKey) . ' 位）' : '否'; ?></div>
            </div>
        </div>
        <div class="form-group">
            <label>任务回调地址（notifyHook，可选）</label>
            <input type="text" name="midjourney_notify_hook" class="form-control" value="<?php echo e($mjNotifyHook); ?>" placeholder="如 <?php echo e(base_url('mj/notify')); ?>">
            <div class="form-hint">MJ-Proxy 任务完成时会 POST 到此地址；不配置则依赖 cron 轮询（系统任务「Midjourney 轮询」）</div>
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;">
                <label>单次绘图费用（额度）</label>
                <input type="number" name="midjourney_charge" step="0.000001" min="0" class="form-control" value="<?php echo e($mjCharge); ?>">
                <div class="form-hint">提交时预扣，失败自动退款；0 为免费</div>
            </div>
            <div style="flex:1; display:flex; align-items:flex-end; padding-bottom:4px;">
                <label class="ios-switch" style="margin:0;">
                    <input type="checkbox" name="midjourney_mode_clear" value="1" <?php echo $mjModeClear === '1' ? 'checked' : ''; ?>>
                    <span></span>
                </label>
                <span style="margin-left:8px;">清除 --fast/--relax/--turbo 模式参数</span>
            </div>
        </div>
    </div>

    <div class="card" style="max-width:720px;">
        <div class="card-title">Suno 音乐</div>
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">启用 Suno（外部 Suno-GoAPI 服务）</label>
            <label class="ios-switch"><input type="checkbox" name="suno_enabled" value="1" <?php echo $sunoEnabled === '1' ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;">
                <label>Suno-GoAPI 服务地址</label>
                <input type="text" name="suno_proxy_url" class="form-control" value="<?php echo e($sunoProxyUrl); ?>" placeholder="如 https://suno.example.com">
                <div class="form-hint">需支持 /suno/submit/{action} 与 /suno/fetch/{id} 接口</div>
            </div>
            <div style="flex:1;">
                <label>API 密钥（Bearer）</label>
                <input type="text" name="suno_api_key" class="form-control" value="" placeholder="留空保持不变">
                <div class="form-hint">当前已配置：<?php echo $sunoApiKey !== '' ? '是（' . strlen($sunoApiKey) . ' 位）' : '否'; ?></div>
            </div>
        </div>
        <div class="form-group">
            <label>单次生成费用（额度）</label>
            <input type="number" name="suno_charge" step="0.000001" min="0" class="form-control" value="<?php echo e($sunoCharge); ?>">
            <div class="form-hint">提交时预扣，失败自动退款；0 为免费</div>
        </div>
    </div>

    <div class="card" style="max-width:720px;">
        <div class="card-title">站点模式与 FAQ</div>
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">自用模式（隐藏「邀请」「兑换」「价格页」入口，仅本机/好友使用）</label>
            <label class="ios-switch"><input type="checkbox" name="self_use_mode" value="1" <?php echo $selfUseMode === '1' ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">前台展示常见问题（FAQ）</label>
            <label class="ios-switch"><input type="checkbox" name="faq_enabled" value="1" <?php echo $faqEnabled === '1' ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-group">
            <label>FAQ 内容（每行一条：问题|答案）</label>
            <textarea name="faq_content" class="form-control" rows="8" placeholder="如何充值？|个人中心-钱包-选择档位或输入金额，选择支付方式完成支付。&#10;API Key 在哪里获取？|个人中心-令牌-新建令牌，复制 sk- 开头的密钥。"><?php echo e($faqContent); ?></textarea>
            <div class="form-hint">显示在用户中心首页底部，用 | 分隔问题和答案，每行一条。</div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">保存设置</button>
    </div>
</form>
<script>
function confirmCompliance() {
    LcyModal.open({
        title: '确认支付合规',
        message: '确认已阅读并同意支付相关合规条款？',
        confirmText: '确认',
        onConfirm: function () {
            var form = document.getElementById('settingsForm');
            if (!form) { form = document.querySelector('form[method="post"]'); }
            var h = document.createElement('input');
            h.type = 'hidden'; h.name = 'confirm_payment_compliance'; h.value = '1';
            form.appendChild(h);
            form.submit();
        }
    });
}
</script>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>