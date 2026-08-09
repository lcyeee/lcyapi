<?php
/* 易支付异步通知（无需登录态，纯验签处理） */
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

$result = PayOrder::epayNotify($_POST);
if ($result['ok']) {
    echo 'success';
    exit;
}
write_log('epay notify rejected: ' . $result['msg'], 'pay');
http_response_code(400);
echo 'fail';
