<?php
/**
 * 在线支付：易支付（MD5 签名）+ Stripe（PaymentIntent）
 * 支付成功统一走 PayOrder::markPaid → User::addQuota(type='pay')
 */
class PayOrder
{
    public static function create($userId, $amount, $provider)
    {
        $amount = round((float)$amount, 2);
        if ($amount <= 0) {
            return ['ok' => false, 'msg' => '充值金额需大于 0'];
        }
        $ratio = (float)setting('pay_ratio', '1');
        $quota = round($amount * $ratio, 6);
        $orderNo = date('YmdHis') . random_string(8);
        $id = DB::insert('pay_orders', [
            'order_no' => $orderNo,
            'user_id' => (int)$userId,
            'provider' => in_array($provider, ['epay', 'stripe'], true) ? $provider : 'epay',
            'amount' => $amount,
            'quota' => $quota,
            'status' => 'pending',
        ]);
        if ($id === 0) {
            return ['ok' => false, 'msg' => '创建订单失败'];
        }
        return ['ok' => true, 'order_no' => $orderNo, 'amount' => $amount, 'quota' => $quota];
    }

    public static function findByOrderNo($orderNo)
    {
        return DB::fetch('SELECT * FROM pay_orders WHERE order_no = ?', [$orderNo]);
    }

    /**
     * 支付成功入账（幂等：仅 pending 可入账，防止 notify 重复扣账）
     */
    public static function markPaid($orderNo, $transactionId = null)
    {
        $order = self::findByOrderNo($orderNo);
        if ($order === false) {
            return ['ok' => false, 'msg' => '订单不存在'];
        }
        if ($order['status'] === 'paid') {
            return ['ok' => true, 'msg' => '已入账'];
        }
        DB::begin();
        try {
            $locked = DB::fetch('SELECT status FROM pay_orders WHERE order_no = ? FOR UPDATE', [$orderNo]);
            if ($locked === false || $locked['status'] === 'paid') {
                DB::rollback();
                return ['ok' => true, 'msg' => '已入账'];
            }
            DB::update('pay_orders', [
                'status' => 'paid',
                'transaction_id' => $transactionId !== null ? mb_substr($transactionId, 0, 255) : null,
                'paid_at' => date('Y-m-d H:i:s'),
            ], 'order_no = ?', [$orderNo]);
            $ok = User::addQuota((int)$order['user_id'], (float)$order['quota'], 'pay', '在线充值 ' . $order['provider'] . ' 订单 ' . $orderNo, null, null);
            if (!$ok) {
                throw new Exception('入账失败');
            }
            DB::commit();
            write_log('pay success: order=' . $orderNo . ' amount=' . $order['amount'] . ' quota=' . $order['quota'], 'pay');
            return ['ok' => true];
        } catch (Exception $ex) {
            DB::rollback();
            write_log('markPaid error: ' . $ex->getMessage(), 'pay');
            return ['ok' => false, 'msg' => '入账失败'];
        }
    }

    /* ============ 易支付 ============ */

    public static function epaySubmit($orderNo)
    {
        $order = self::findByOrderNo($orderNo);
        if ($order === false) {
            return ['ok' => false, 'msg' => '订单不存在'];
        }
        $apiUrl = setting('epay_api_url', '');
        $pid = setting('epay_pid', '');
        $key = setting('epay_key', '');
        if ($apiUrl === '' || $pid === '' || $key === '') {
            return ['ok' => false, 'msg' => '易支付未配置完整'];
        }
        $siteName = setting('site_name', config('site.name'));
        $params = [
            'pid' => $pid,
            'type' => 'alipay',
            'out_trade_no' => $orderNo,
            'notify_url' => base_url('api/pay/epay_notify.php'),
            'return_url' => base_url('user/wallet/pay-result.php?order_no=' . $orderNo),
            'name' => $siteName . ' 额度充值',
            'money' => sprintf('%.2f', (float)$order['amount']),
            'sign_type' => 'MD5',
        ];
        $params['sign'] = self::epaySign($params, $key);
        return ['ok' => true, 'pay_url' => rtrim($apiUrl, '/') . '/submit.php?' . http_build_query($params)];
    }

    /**
     * 易支付 MD5 签名：所有参数按 key 升序拼 a=b&c=d，追加 &key=商户密钥，md5
     */
    public static function epaySign($params, $key)
    {
        ksort($params);
        $str = '';
        foreach ($params as $k => $v) {
            if ($k === 'sign' || $k === 'sign_type' || $v === '') {
                continue;
            }
            $str .= $k . '=' . $v . '&';
        }
        return md5($str . 'key=' . $key);
    }

    /**
     * 校验易支付异步通知，返回 ['ok'=>true,'order_no'] 或 ['ok'=>false,'msg']
     */
    public static function epayNotify($data)
    {
        $key = setting('epay_key', '');
        if ($key === '') {
            return ['ok' => false, 'msg' => '商户密钥未配置'];
        }
        $orderNo = isset($data['out_trade_no']) ? trim((string)$data['out_trade_no']) : '';
        $status = isset($data['trade_status']) ? (string)$data['trade_status'] : '';
        $sign = isset($data['sign']) ? (string)$data['sign'] : '';
        if ($orderNo === '' || $status === '' || $sign === '') {
            return ['ok' => false, 'msg' => '参数缺失'];
        }
        if (!hash_equals(self::epaySign($data, $key), $sign)) {
            return ['ok' => false, 'msg' => '签名校验失败'];
        }
        if ($status !== 'TRADE_SUCCESS' && $status !== 'TRADE_FINISHED') {
            return ['ok' => false, 'msg' => '订单未支付'];
        }
        $order = self::findByOrderNo($orderNo);
        if ($order === false) {
            return ['ok' => false, 'msg' => '订单不存在'];
        }
        /* 金额必须与订单一致 */
        if (abs((float)$data['money'] - (float)$order['amount']) > 0.01) {
            return ['ok' => false, 'msg' => '金额不一致'];
        }
        self::markPaid($orderNo, isset($data['trade_no']) ? (string)$data['trade_no'] : null);
        return ['ok' => true, 'order_no' => $orderNo];
    }

    /* ============ Stripe ============ */

    /**
     * Stripe Checkout Sessions 托管支付页：创建后返回跳转 URL（无需前端 JS 库）
     */
    public static function stripeCreateCheckout($orderNo)
    {
        $order = self::findByOrderNo($orderNo);
        if ($order === false) {
            return ['ok' => false, 'msg' => '订单不存在'];
        }
        $secretKey = setting('stripe_secret_key', '');
        if ($secretKey === '') {
            return ['ok' => false, 'msg' => 'Stripe 未配置'];
        }
        $siteName = setting('site_name', config('site.name'));
        $body = http_build_query([
            'mode' => 'payment',
            'success_url' => base_url('user/wallet/pay-result.php?order_no=' . $orderNo . '&provider=stripe'),
            'cancel_url' => base_url('user/wallet/pay-result.php?order_no=' . $orderNo . '&provider=stripe&canceled=1'),
            'line_items[0][quantity]' => '1',
            'line_items[0][price_data][currency]' => 'usd',
            'line_items[0][price_data][unit_amount]' => (string)(int)round((float)$order['amount'] * 100),
            'line_items[0][price_data][product_data][name]' => $siteName . ' 额度充值',
            'metadata[order_no]' => $orderNo,
        ]);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.stripe.com/v1/checkout/sessions',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERPWD => $secretKey . ':',
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string)$resp, true);
        if ($code < 200 || $code >= 300 || empty($json['id']) || empty($json['url'])) {
            return ['ok' => false, 'msg' => 'Stripe 创建支付单失败：' . (isset($json['error']['message']) ? $json['error']['message'] : 'HTTP ' . $code)];
        }
        DB::update('pay_orders', ['prepay_id' => $json['id'], 'pay_url' => $json['url']], 'order_no = ?', [$orderNo]);
        return ['ok' => true, 'checkout_url' => $json['url']];
    }

    /**
     * Stripe Webhook 验签（whsec）并处理 payment_intent.succeeded
     */
    public static function stripeWebhook($payload, $sigHeader)
    {
        $webhookSecret = setting('stripe_webhook_secret', '');
        if ($webhookSecret === '') {
            return ['ok' => false, 'msg' => 'Stripe Webhook 密钥未配置', 'code' => 400];
        }
        if ($sigHeader === '') {
            return ['ok' => false, 'msg' => '缺少签名头', 'code' => 400];
        }
        $decoded = self::stripeVerifySignature($payload, $sigHeader, $webhookSecret);
        if ($decoded === null) {
            return ['ok' => false, 'msg' => '签名校验失败', 'code' => 400];
        }
        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['type']) || empty($event['data']['object']['id'])) {
            return ['ok' => false, 'msg' => '事件格式错误', 'code' => 400];
        }
        if ($event['type'] === 'payment_intent.succeeded' || $event['type'] === 'checkout.session.completed') {
            $obj = $event['data']['object'];
            $orderNo = isset($obj['metadata']['order_no']) ? $obj['metadata']['order_no'] : '';
            if ($orderNo === '') {
                return ['ok' => false, 'msg' => '缺少订单号', 'code' => 400];
            }
            $txn = isset($obj['payment_intent']) ? $obj['payment_intent'] : (isset($obj['id']) ? $obj['id'] : null);
            self::markPaid($orderNo, $txn !== null ? (string)$txn : null);
        }
        return ['ok' => true];
    }

    /**
     * Stripe HMAC-SHA256 时间戳签名校验，返回事件 JSON 或 null
     */
    public static function stripeVerifySignature($payload, $sigHeader, $secret)
    {
        if (!preg_match('/t=(\d+),v1=([^,]+)/', $sigHeader, $m)) {
            return null;
        }
        $timestamp = (int)$m[1];
        $signature = $m[2];
        if (abs(time() - $timestamp) > 300) {
            return null;
        }
        $signed = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signed, $secret);
        if (!hash_equals($expected, $signature)) {
            return null;
        }
        return json_decode($payload, true);
    }
}
