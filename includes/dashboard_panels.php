<?php
/**
 * 仪表盘面板：公告/FAQ/API信息/Uptime面板
 * 嵌入到 admin/index.php 中
 */
function dashboard_panels()
{
    $announcements = setting('site_announcements', '');
    $faq = setting('site_faq', '');
    $apiInfo = setting('api_info', '');
    $uptimeUrl = setting('uptime_kuma_url', '');
    ?>
    <div class="stat-grid" style="margin-bottom:16px;">
        <?php if ($announcements !== '') : ?>
        <div class="stat-card" style="grid-column:span 2;">
            <div class="label"><?php echo svg_icon('info'); ?>系统公告</div>
            <div style="font-size:13px;color:var(--text-2);margin-top:6px;line-height:1.6;"><?php echo nl2br(e($announcements)); ?></div>
        </div>
        <?php endif; ?>
        <?php if ($faq !== '') : ?>
        <div class="stat-card">
            <div class="label"><?php echo svg_icon('help'); ?>FAQ</div>
            <div style="font-size:13px;color:var(--text-2);margin-top:6px;line-height:1.6;"><?php echo nl2br(e($faq)); ?></div>
        </div>
        <?php endif; ?>
        <?php if ($apiInfo !== '') : ?>
        <div class="stat-card">
            <div class="label"><?php echo svg_icon('globe'); ?>API 信息</div>
            <div style="font-size:13px;color:var(--text-2);margin-top:6px;line-height:1.6;"><?php echo nl2br(e($apiInfo)); ?></div>
        </div>
        <?php endif; ?>
        <?php if ($uptimeUrl !== '') : ?>
        <div class="stat-card">
            <div class="label"><?php echo svg_icon('server'); ?>Uptime Kuma</div>
            <div style="font-size:13px;color:var(--text-2);margin-top:6px;line-height:1.6;">
                <a href="<?php echo e($uptimeUrl); ?>" target="_blank">查看状态页</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
}