<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    session_flash('flash_error', '页面已过期，请重试');
    redirect(base_url('user/wallet/index.php'));
}
$amount = (float)($_POST['amount'] ?? 0);
$provider = ($_POST['provider'] ?? '') === 'stripe' ? 'stripe' : 'epay';

if ($provider === 'epay' && setting('epay_enabled', '0') !== '1') {
    session_flash('flash_error', '易支付未启用');
    redirect(base_url('user/wallet/index.php'));
}
if ($provider === 'stripe' && setting('stripe_enabled', '0') !== '1') {
    session_flash('flash_error', 'Stripe 未启用');
    redirect(base_url('user/wallet/index.php'));
}

$result = PayOrder::create(Auth::id(), $amount, $provider);
if (!$result['ok']) {
    session_flash('flash_error', $result['msg']);
    redirect(base_url('user/wallet/index.php'));
}
$orderNo = $result['order_no'];

if ($provider === 'epay') {
    $submit = PayOrder::epaySubmit($orderNo);
    if (!$submit['ok']) {
        session_flash('flash_error', $submit['msg']);
        redirect(base_url('user/wallet/index.php'));
    }
    redirect($submit['pay_url']);
}

$checkout = PayOrder::stripeCreateCheckout($orderNo);
if (!$checkout['ok']) {
    session_flash('flash_error', $checkout['msg']);
    redirect(base_url('user/wallet/index.php'));
}
redirect($checkout['checkout_url']);
