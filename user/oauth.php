<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$provider = isset($_GET['provider']) ? strtolower(trim($_GET['provider'])) : '';
if (!in_array($provider, ['github', 'telegram'], true)) {
    redirect(base_url('user/login.php'));
}

$isCallback = isset($_GET['code']) || isset($_GET['user']);
if (!$isCallback) {
    /* 发起授权：已登录则用于绑定，未登录则用于登录 */
    if (!OAuth::enabled($provider)) {
        session_flash('flash_error', '该登录方式未启用');
        redirect(base_url('user/login.php'));
    }
    $url = OAuth::authorizeUrl($provider);
    if ($url === '') {
        session_flash('flash_error', 'OAuth 配置不完整');
        redirect(Auth::check() ? base_url('user/profile/security.php') : base_url('user/login.php'));
    }
    redirect($url);
}

/* 回调处理 */
if (!OAuth::verifyState()) {
    session_flash('flash_error', '授权状态校验失败，请重试');
    redirect(base_url('user/login.php'));
}

$info = OAuth::handleCallback($provider);
if (!$info['ok']) {
    session_flash('flash_error', $info['msg']);
    redirect(base_url('user/login.php'));
}

$bindUserId = Auth::check() ? Auth::id() : null;
$result = OAuth::loginWithIdentity($info, $bindUserId);
if (!$result['ok']) {
    session_flash('flash_error', $result['msg']);
    redirect($bindUserId !== null ? base_url('user/profile/security.php') : base_url('user/login.php'));
}
session_flash('flash_success', $bindUserId !== null ? '第三方账号绑定成功' : ($result['new'] ? '欢迎新用户，账号已创建' : '登录成功'));
redirect($bindUserId !== null ? base_url('user/profile/security.php') : base_url('user/index.php'));
