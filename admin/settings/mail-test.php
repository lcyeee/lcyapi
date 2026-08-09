<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $result = ['ok' => false, 'msg' => '表单已过期，请重试'];
    } else {
        $to = trim($_POST['to'] ?? '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $result = ['ok' => false, 'msg' => '请输入正确的收件邮箱'];
        } else {
            $result = Mailer::send($to, '【测试邮件】来自 ' . setting('site_name', config('site.name')), '<h2>SMTP 配置测试</h2><p>如果您收到此邮件，说明 SMTP 配置正确。</p>');
            if ($result['ok'] && !empty($result['dev'])) {
                $result = ['ok' => true, 'msg' => '当前为日志模式（未配置 SMTP），邮件内容已写入 data/logs/mail.log'];
            }
        }
    }
}
$pageTitle = '发送测试邮件';
?>
<?php require dirname(__DIR__) . '/templates/header.php'; ?>

<div class="card" style="max-width:520px;">
    <div class="card-title">发送测试邮件</div>
    <?php if ($result !== null) : ?>
        <?php if ($result['ok']) : ?>
            <div class="alert alert-success"><?php echo e($result['msg'] ?? '发送成功'); ?></div>
        <?php else : ?>
            <div class="alert alert-danger"><?php echo e($result['msg'] ?? '发送失败'); ?></div>
        <?php endif; ?>
    <?php endif; ?>
    <form method="post" action="<?php echo base_url('admin/settings/mail-test.php'); ?>">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <div class="form-group">
            <label>收件邮箱</label>
            <input type="email" name="to" class="form-control" required placeholder="输入一个可接收的邮箱地址">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">发送测试邮件</button>
            <a href="<?php echo base_url('admin/settings/index.php'); ?>" class="btn btn-secondary">返回设置</a>
        </div>
    </form>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
