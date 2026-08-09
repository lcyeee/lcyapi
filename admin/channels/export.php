<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$format = isset($_GET['format']) && $_GET['format'] === 'json' ? 'json' : 'csv';
$channels = DB::fetchAll('SELECT id, name, type, base_url, models, group, weight, priority, status, tags, remark, balance FROM channels ORDER BY priority DESC, id ASC');

if ($format === 'json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="channels-' . date('YmdHis') . '.json"');
    echo json_encode(['exported_at' => date('c'), 'channels' => $channels], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="channels-' . date('YmdHis') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); /* UTF-8 BOM for Excel */
fputcsv($out, ['ID', '名称', '类型', '地址', '模型', '分组', '权重', '优先级', '状态', '标签', '备注', '余额']);
foreach ($channels as $ch) {
    fputcsv($out, [
        $ch['id'], $ch['name'], $ch['type'], $ch['base_url'], $ch['models'],
        $ch['group'], $ch['weight'], $ch['priority'], $ch['status'] ? '启用' : '停用',
        $ch['tags'], $ch['remark'], $ch['balance'] === null ? '不限' : $ch['balance'],
    ]);
}
fclose($out);
exit;