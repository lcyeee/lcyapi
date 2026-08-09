<?php
/**
 * 隐私政策
 */
$pageTitle = '隐私政策';
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/admin/templates/header.php';
?>
<div class="card">
    <div class="card-title">隐私政策</div>
    <div style="line-height:1.8;font-size:13px;color:var(--text-2);">
        <p>我们重视您的隐私。本隐私政策说明我们如何收集、使用和保护您的个人信息。</p>
        <h4>1. 收集的信息</h4>
        <p>我们收集您的用户名、邮箱地址（可选）、API 请求日志等必要信息以提供服务。</p>
        <h4>2. 信息使用</h4>
        <p>收集的信息仅用于系统运行、计费结算和故障排查。我们不会向第三方出售您的个人信息。</p>
        <h4>3. 数据安全</h4>
        <p>我们采取合理的安全措施保护您的数据，包括加密传输、访问控制等。</p>
        <h4>4. 数据保留</h4>
        <p>请求日志保留 30 天，过期自动清理。您可随时申请删除账号及相关数据。</p>
    </div>
</div>
<?php require dirname(__DIR__) . '/admin/templates/footer.php'; ?>