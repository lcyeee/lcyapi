<?php
/* Stripe Webhook（验签处理 payment_intent.succeeded） */
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

$payload = file_get_contents('php://input');
$sigHeader = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? (string)$_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
$result = PayOrder::stripeWebhook((string)$payload, $sigHeader);
if (!$result['ok']) {
    http_response_code(isset($result['code']) ? $result['code'] : 400);
    write_log('stripe webhook rejected: ' . $result['msg'], 'pay');
    echo json_encode(['error' => $result['msg']]);
    exit;
}
echo json_encode(['received' => true]);
