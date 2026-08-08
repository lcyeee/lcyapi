<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/templates/header.php';
$pageTitle = '外观主题';

/* 与 assets/js/theme.js 中 PRESETS 保持一致 */
$presets = [
    'ice'   => ['name' => '浅冰蓝', 'color' => '#409EFF'],
    'white' => ['name' => '极简白', 'color' => '#5B8DEF'],
    'mint'  => ['name' => '薄荷绿', 'color' => '#34C78B'],
    'lilac' => ['name' => '淡紫',   'color' => '#8B7CF6'],
    'space' => ['name' => '深空灰', 'color' => '#64748B'],
];
?>
<div class="card">
    <div class="card-title"><?php echo svg_icon('eye'); ?>明暗模式</div>
    <div class="theme-mode-group">
        <button type="button" class="theme-mode-btn" data-theme-mode="auto"><?php echo svg_icon('refresh'); ?>跟随系统</button>
        <button type="button" class="theme-mode-btn" data-theme-mode="light"><?php echo svg_icon('sun'); ?>亮色</button>
        <button type="button" class="theme-mode-btn" data-theme-mode="dark"><?php echo svg_icon('moon'); ?>暗色</button>
    </div>
    <div class="form-hint">「跟随系统」会自动感应设备深浅色设置；手动选择后固定不变。</div>
</div>

<div class="card">
    <div class="card-title"><?php echo svg_icon('tag'); ?>预设配色</div>
    <div class="theme-presets">
        <?php foreach ($presets as $id => $p) : ?>
            <button type="button" class="theme-preset" data-theme-preset="<?php echo $id; ?>">
                <span class="dot" style="background:<?php echo $p['color']; ?>;"></span>
                <?php echo $p['name']; ?>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <div class="card-title"><?php echo svg_icon('edit'); ?>自定义主色</div>
    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
        <input type="color" id="themeAccentPicker" value="#409EFF" style="width:52px; height:38px; padding:2px; border:1px solid var(--border); border-radius:9px; background:var(--card-2); cursor:pointer;">
        <span class="text-muted" style="font-size:13px;">用取色器自由选色，实时预览全站变色</span>
    </div>
    <div class="form-actions">
        <button type="button" class="btn btn-secondary" data-theme-reset><?php echo svg_icon('refresh'); ?>恢复默认主题</button>
    </div>
    <div class="form-hint">主题配置保存在本机浏览器（localStorage），不同设备各自独立、永久生效。</div>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
