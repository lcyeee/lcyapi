<?php
/**
 * 用户协议
 */
$pageTitle = '用户协议';
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/admin/templates/header.php';
?>
<div class="card">
    <div class="card-title">用户协议</div>
    <div style="line-height:1.8;font-size:13px;color:var(--text-2);">
        <p>欢迎使用 lcyapi 服务。在注册和使用本系统前，请仔细阅读以下条款。</p>
        <h4>1. 服务说明</h4>
        <p>本系统为 AI 模型 API 网关，转发用户请求到第三方大模型服务并按其消耗计费。</p>
        <h4>2. 用户责任</h4>
        <p>用户应合法使用本系统，不得利用本系统从事违法违规活动。用户应自行承担其使用行为的一切法律后果。</p>
        <h4>3. 免责声明</h4>
        <p>本系统按「现状」提供，不提供任何明示或暗示的保证。运营者不对因使用本系统导致的任何损失承担责任。</p>
        <h4>4. 隐私政策</h4>
        <p>我们重视用户隐私。详见<a href="privacy.php">隐私政策</a>。</p>
    </div>
</div>
<?php require dirname(__DIR__) . '/admin/templates/footer.php'; ?>