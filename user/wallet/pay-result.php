<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireLogin();

$orderNo = isset($_GET['order_no']) ? trim((string)$_GET['order_no']) : '';
$order = $orderNo !== '' ? PayOrder::findByOrderNo($orderNo) : false;
$canceled = isset($_GET['canceled']);
if ($order !== false && (int)$order['user_id'] !== Auth::id()) {
    $order = false;
}
$paid = $order !== false && $order['status'] === 'paid';
$pageTitle = '充值结果';
require dirname(__DIR__) . '/templates/header.php';
?>
<div class="card" style="max-width:520px;">
    <div class="card-title">充值结果</div>
    <?php if ($canceled) : ?>
        <div class="alert alert-warning"><?php echo svg_icon('info'); ?>支付已取消，订单未入账。</div>
    <?php elseif ($order === false) : ?>
        <div class="alert alert-danger">订单不存在或无权查看。</div>
    <?php elseif ($paid) : ?>
        <div class="alert alert-success">
            <?php echo svg_icon('check'); ?>
            充值成功！支付金额 $<?php echo e(number_format((float)$order['amount'], 2)); ?>，
            入账额度 $<?php echo e(number_format((float)$order['quota'], 4)); ?>。
        </div>
    <?php else : ?>
        <div class="alert alert-info">订单状态：<?php echo e($order['status']); ?>。若您已完成支付但此处仍显示未到账，请稍等片刻后刷新页面。</div>
    <?php endif; ?>
    <div class="form-actions">
        <a href="<?php echo base_url('user/wallet/index.php'); ?>" class="btn">返回钱包</a>
    </div>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
