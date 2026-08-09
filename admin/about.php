<?php
/**
 * lcyapi 关于
 * 展示系统信息、版本、开源许可等
 */
$pageTitle = '关于';
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/admin/templates/header.php';
?>
<div class="card">
    <div class="card-title">关于 lcyapi</div>
    <table class="table">
        <tbody>
            <tr><th style="width:180px;">项目名称</th><td>lcyapi — OpenAI 兼容 API 网关</td></tr>
            <tr><th>版本</th><td>1.0.0</td></tr>
            <tr><th>PHP 版本</th><td><?php echo PHP_VERSION; ?></td></tr>
            <tr><th>数据库</th><td>MySQL</td></tr>
            <tr><th>开源许可</th><td>MIT</td></tr>
            <tr><th>项目参考</th><td>界面与功能参考了 <a href="https://github.com/QuantumNous/new-api" target="_blank">new-api</a> 等开源项目</td></tr>
        </tbody>
    </table>
</div>
<div class="card">
    <div class="card-title">免责声明</div>
    <p class="text-muted" style="line-height:1.8;font-size:13px;">
        本系统为个人开发的开源项目，仅供学习与技术交流。使用本系统所产生的一切后果均由使用者（部署者/运营者）自行承担。
        制作者不对因使用、修改、部署本系统导致的任何直接或间接损失负责。
    </p>
</div>
<?php require dirname(__DIR__) . '/admin/templates/footer.php'; ?>