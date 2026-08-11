<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/templates/header.php';

$typeFilter = trim($_GET['type'] ?? '');
$kw = trim($_GET['q'] ?? '');

$where = ['enabled = 1'];
$params = [];
if (in_array($typeFilter, ['chat', 'completion', 'embedding', 'image', 'audio'], true)) {
    $where[] = 'type = ?';
    $params[] = $typeFilter;
}
if ($kw !== '') {
    $where[] = '(name LIKE ? OR display_name LIKE ?)';
    $like = '%' . $kw . '%';
    $params[] = $like;
    $params[] = $like;
}
$models = DB::fetchAll('SELECT * FROM models WHERE ' . implode(' AND ', $where) . ' ORDER BY sort DESC, id ASC', $params);

$typeLabels = ['chat' => '对话', 'completion' => '补全', 'embedding' => '向量', 'image' => '绘图', 'audio' => '音频'];
?>
<div class="card">
    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <span>模型价格（共 <?php echo count($models); ?> 个）</span>
        <form method="get" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <select name="type" class="form-control" style="width:110px;">
                <option value="">全部类型</option>
                <?php foreach ($typeLabels as $tk => $tl) : ?>
                    <option value="<?php echo $tk; ?>" <?php echo $typeFilter === $tk ? 'selected' : ''; ?>><?php echo $tl; ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="q" class="form-control" style="width:170px;" value="<?php echo e($kw); ?>" placeholder="模型名称">
            <button type="submit" class="btn btn-sm"><?php echo svg_icon('search'); ?>筛选</button>
        </form>
    </div>
    <table class="table table-collapsible">
        <thead>
            <tr><th>模型</th><th>类型</th><th>输入价</th><th>输出价</th><th>上下文</th><th>最大输出</th></tr>
        </thead>
        <tbody>
        <?php if (empty($models)) : ?>
            <tr class="row-empty"><td colspan="6" class="text-center text-muted">暂无可用模型</td></tr>
        <?php endif; ?>
        <?php foreach ($models as $m) : ?>
            <tr>
                <td data-label="模型">
                    <code><?php echo e($m['name']); ?></code>
                    <?php if ($m['display_name']) : ?><div class="form-hint"><?php echo e($m['display_name']); ?></div><?php endif; ?>
                </td>
                <td data-label="类型"><span class="badge badge-blue"><?php echo isset($typeLabels[$m['type']]) ? $typeLabels[$m['type']] : e($m['type']); ?></span></td>
                <td data-label="输入价">$<?php echo e(number_format((float)$m['input_price'], 6)); ?> / 1K</td>
                <td data-label="输出价">$<?php echo e(number_format((float)$m['output_price'], 6)); ?> / 1K</td>
                <td data-label="上下文"><?php echo number_format((int)$m['context_length']); ?></td>
                <td data-label="最大输出"><?php echo number_format((int)$m['max_output']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="form-hint" style="margin-top:10px;">价格单位为美元（$）每 1K tokens；绘图/音频类模型按次计费时以实际扣费为准。</div>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
