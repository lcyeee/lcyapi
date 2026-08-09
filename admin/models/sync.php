<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '从渠道同步模型';

$remoteModels = [];
$channel = false;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        session_flash('flash_error', '页面已过期，请重试');
        redirect(base_url('admin/models/sync.php'));
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'save_selected') {
        $names = isset($_POST['models']) && is_array($_POST['models']) ? $_POST['models'] : [];
        $ins = isset($_POST['input_price']) && is_array($_POST['input_price']) ? $_POST['input_price'] : [];
        $outs = isset($_POST['output_price']) && is_array($_POST['output_price']) ? $_POST['output_price'] : [];
        $types = isset($_POST['type']) && is_array($_POST['type']) ? $_POST['type'] : [];
        $created = 0;
        $skipped = 0;
        foreach ($names as $name) {
            $name = trim((string)$name);
            if ($name === '' || preg_match('/^[A-Za-z0-9_.\-\/:]{1,100}$/', $name) !== 1) {
                continue;
            }
            if (Model::find($name) !== false) {
                $skipped++;
                continue;
            }
            if (Model::create([
                'name' => $name,
                'input_price' => max(0, (float)($ins[$name] ?? 0)),
                'output_price' => max(0, (float)($outs[$name] ?? 0)),
                'context_length' => 4096,
                'max_output' => 2048,
                'type' => isset($types[$name]) && in_array($types[$name], ['chat', 'completion', 'embedding', 'image', 'audio'], true) ? $types[$name] : 'chat',
                'enabled' => 1,
            ])) {
                $created++;
            }
        }
        $success = "已同步 {$created} 个新模型" . ($skipped > 0 ? "（{$skipped} 个已存在跳过）" : '');
        audit_log('model_sync', null, 'created=' . $created . ' skipped=' . $skipped);
    }
}

$channelId = (int)($_GET['channel'] ?? ($_POST['channel'] ?? 0));
if ($channelId > 0) {
    $channel = Channel::getById($channelId);
    if ($channel !== false) {
        $fetch = Channel::fetchRemoteModels($channel);
        if (!empty($fetch['ok']) && isset($fetch['models']) && is_array($fetch['models'])) {
            $remoteModels = $fetch['models'];
        } else {
            $error = isset($fetch['message']) ? $fetch['message'] : '获取模型列表失败';
        }
    } else {
        $error = '渠道不存在';
    }
}

$channels = DB::fetchAll('SELECT id, name, base_url, type, models FROM channels ORDER BY priority DESC, id ASC');
$existing = [];
foreach (Model::all() as $model) {
    $existing[strtolower($model['name'])] = $model;
}

/* 按名称规则分组（与渠道编辑页一致） */
function model_group($name)
{
    $name = strtolower($name);
    if (strpos($name, 'embed') !== false || strpos($name, 'ada') !== false || strpos($name, 'rerank') !== false) {
        return 'embedding';
    }
    if (strpos($name, 'image') !== false || strpos($name, 'dall-e') !== false || strpos($name, 'flux') !== false || strpos($name, 'midjourney') !== false) {
        return 'image';
    }
    if (strpos($name, 'whisper') !== false || strpos($name, 'tts') !== false || strpos($name, 'speech') !== false || strpos($name, 'audio') !== false) {
        return 'audio';
    }
    if (strpos($name, 'rerank') !== false) {
        return 'rerank';
    }
    return 'chat';
}
?>
<?php require dirname(__DIR__) . '/templates/header.php'; ?>

<?php if ($error !== '') : ?>
    <div class="alert alert-danger"><?php echo e($error); ?></div>
<?php endif; ?>
<?php if ($success !== '') : ?>
    <div class="alert alert-success"><?php echo e($success); ?></div>
<?php endif; ?>

<div class="card" style="max-width:720px;">
    <div class="card-title">选择渠道</div>
    <form method="get" action="<?php echo base_url('admin/models/sync.php'); ?>">
        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <select name="channel" class="form-control" style="flex:1; min-width:200px;">
                <option value="">-- 选择渠道 --</option>
                <?php foreach ($channels as $ch) : ?>
                    <option value="<?php echo (int)$ch['id']; ?>" <?php echo $channelId === (int)$ch['id'] ? 'selected' : ''; ?>>
                        <?php echo e($ch['name']); ?>（<?php echo e($ch['type']); ?> · <?php echo e($ch['base_url']); ?>）
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn">获取云端模型</button>
        </div>
    </form>
    <div class="form-hint">需要渠道「从云端获取模型」能力（GET 上游 /v1/models）；渠道需保持启用且网络可达。</div>
</div>

<?php if (!empty($remoteModels)) : ?>
<div class="card">
    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <span>云端模型列表（<?php echo count($remoteModels); ?> 个，勾选后录入本地价格表）</span>
        <div style="display:flex; gap:8px;">
            <button type="button" class="btn btn-sm" id="syncCheckAll" onclick="syncCheck(true)">全选新模型</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="syncCheck(false)">全不选</button>
        </div>
    </div>
    <form method="post" action="<?php echo base_url('admin/models/sync.php?channel=' . $channelId); ?>">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="action" value="save_selected">
        <input type="hidden" name="channel" value="<?php echo (int)$channelId; ?>">
        <table class="table">
            <thead><tr><th style="width:36px;"></th><th>模型</th><th>类型</th><th>输入价</th><th>输出价</th><th>状态</th></tr></thead>
            <tbody>
            <?php $grouped = []; foreach ($remoteModels as $rm) { $g = model_group($rm); $grouped[$g][] = $rm; } ?>
            <?php foreach ($grouped as $gname => $models) : ?>
                <tr><td colspan="6" style="background:var(--card-2); font-weight:600;"><?php echo e(['chat' => '对话', 'completion' => '补全', 'embedding' => '嵌入', 'image' => '图像', 'audio' => '音频', 'rerank' => '重排', 'other' => '其他'][$gname] ?? $gname); ?></td></tr>
                <?php foreach ($models as $rm) : ?>
                    <?php $isExisting = isset($existing[strtolower($rm)]); ?>
                    <tr>
                        <td><input type="checkbox" class="ch-sync" name="models[]" value="<?php echo e($rm); ?>" <?php echo $isExisting ? 'disabled' : 'checked'; ?>></td>
                        <td><?php echo e($rm); ?><?php if ($isExisting) : ?><span class="badge badge-gray">已存在</span><?php endif; ?></td>
                        <td>
                            <select name="type[<?php echo e($rm); ?>]" class="form-control" style="width:90px; height:30px;" <?php echo $isExisting ? 'disabled' : ''; ?>>
                                <?php foreach (['chat' => '对话', 'completion' => '补全', 'embedding' => '向量', 'image' => '图像', 'audio' => '音频'] as $tv => $tn) : ?>
                                    <option value="<?php echo $tv; ?>" <?php echo $gname === $tv ? 'selected' : ''; ?>><?php echo $tn; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="number" step="0.000001" min="0" name="input_price[<?php echo e($rm); ?>]" class="form-control" style="width:110px; height:30px;" value="0" <?php echo $isExisting ? 'disabled' : ''; ?>></td>
                        <td><input type="number" step="0.000001" min="0" name="output_price[<?php echo e($rm); ?>]" class="form-control" style="width:110px; height:30px;" value="0" <?php echo $isExisting ? 'disabled' : ''; ?>></td>
                        <td><?php echo $isExisting ? '<span class="badge badge-blue">本地已有</span>' : '<span class="badge badge-green">待录入</span>'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="form-actions">
            <button type="submit" class="btn">同步所选模型到本地</button>
            <a class="btn btn-secondary" href="<?php echo base_url('admin/models/index.php'); ?>">返回模型列表</a>
        </div>
    </form>
</div>
<script>
function syncCheck(checked) {
    document.querySelectorAll('.ch-sync:not(:disabled)').forEach(function (c) { c.checked = checked; });
}
</script>
<?php endif; ?>

<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
