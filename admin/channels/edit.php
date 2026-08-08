<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '编辑渠道';

$id = (int)($_GET['id'] ?? 0);
$channel = $id > 0 ? Channel::getById($id) : false;
if ($id > 0 && $channel === false) {
    session_flash('flash_error', '渠道不存在');
    redirect(base_url('admin/channels/index.php'));
}
$isNew = $channel === false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* AJAX：从上游获取模型列表（在保存逻辑之前拦截，避免走到重定向） */
    if (($_POST['action'] ?? '') === 'fetch_models') {
        header('Content-Type: application/json; charset=utf-8');
        $out = function ($ok, $message = '', $models = []) {
            echo json_encode(['ok' => $ok, 'message' => $message, 'models' => $models], JSON_UNESCAPED_UNICODE);
            exit;
        };
        if (!csrf_verify()) {
            $out(false, '表单已过期，请刷新页面后重试');
        }
        $baseUrl = rtrim(trim($_POST['base_url'] ?? ''), '/');
        $apiKey = trim($_POST['api_key'] ?? '');
        /* 编辑时 Key 显示为 ******，用库里已存的 Key 去请求上游 */
        if ($apiKey === '******' || $apiKey === '') {
            $apiKey = $channel !== false ? (string)$channel['api_key'] : '';
        }
        if ($baseUrl === '' || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            $out(false, '请先填写合法的渠道地址');
        }
        if ($apiKey === '') {
            $out(false, '请先填写 API Key');
        }
        $temp = ['type' => $_POST['type'] ?? 'openai', 'base_url' => $baseUrl, 'api_key' => $apiKey];
        $result = Channel::fetchRemoteModels($temp);
        if (!empty($result['ok'])) {
            $out(true, '', $result['models']);
        }
        $detail = isset($result['detail']) && $result['detail'] !== '' ? '；' . $result['detail'] : '';
        $out(false, $result['message'] . $detail);
    }

    if (!csrf_verify()) {
        session_flash('flash_error', '表单已过期，请重试');
        redirect(base_url('admin/channels/edit.php'));
    }
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'type' => in_array(($_POST['type'] ?? 'openai'), ['openai', 'azure', 'custom'], true) ? $_POST['type'] : 'openai',
        'base_url' => rtrim(trim($_POST['base_url'] ?? ''), '/'),
        'api_key' => trim($_POST['api_key'] ?? ''),
        'models' => trim($_POST['models'] ?? ''),
        'weight' => max(1, (int)($_POST['weight'] ?? 1)),
        'priority' => (int)($_POST['priority'] ?? 0),
        'status' => empty($_POST['status']) ? 0 : 1,
        'remark' => mb_substr(trim($_POST['remark'] ?? ''), 0, 255),
    ];
    if ($data['name'] === '') {
        session_flash('flash_error', '渠道名称不能为空');
        redirect(base_url('admin/channels/edit.php' . ($id ? '?id=' . $id : '')));
    }
    if ($data['base_url'] === '' || filter_var($data['base_url'], FILTER_VALIDATE_URL) === false) {
        session_flash('flash_error', '渠道地址不合法（需以 http:// 或 https:// 开头）');
        redirect(base_url('admin/channels/edit.php' . ($id ? '?id=' . $id : '')));
    }
    if ($data['api_key'] === '') {
        session_flash('flash_error', 'API Key 不能为空');
        redirect(base_url('admin/channels/edit.php' . ($id ? '?id=' . $id : '')));
    }
    if ($channel !== false) {
        if ($_POST['api_key'] === '******') {
            unset($data['api_key']);
        }
        Channel::update($id, $data);
        session_flash('flash_success', '渠道已更新');
        audit_log('channel_update', "#{$id}", $data['name']);
    } else {
        $newId = Channel::create($data);
        if ($newId !== false) {
            session_flash('flash_success', '渠道已创建');
            audit_log('channel_create', "#{$newId}", $data['name']);
        } else {
            session_flash('flash_error', '创建失败，请检查数据');
            redirect(base_url('admin/channels/edit.php'));
        }
    }
    redirect(base_url('admin/channels/index.php'));
}

$name = $channel ? $channel['name'] : '';
$type = $channel ? $channel['type'] : 'openai';
$baseUrl = $channel ? $channel['base_url'] : '';
$models = $channel ? $channel['models'] : '';
$weight = $channel ? (int)$channel['weight'] : 1;
$priority = $channel ? (int)$channel['priority'] : 0;
$status = $channel ? (int)$channel['status'] : 1;
$remark = $channel ? $channel['remark'] : '';
?>
<?php require dirname(__DIR__) . '/templates/header.php'; ?>

<form method="post" action="<?php echo base_url('admin/channels/edit.php' . ($id ? '?id=' . $id : '')); ?>">
    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
    <div class="card" style="max-width:720px;">
        <div class="card-title"><?php echo $isNew ? '新建渠道' : '编辑渠道 #' . $id; ?></div>

        <div class="form-group">
            <label>渠道名称</label>
            <input type="text" name="name" class="form-control" value="<?php echo e($name); ?>" placeholder="例如：OpenAI 官方">
        </div>
        <div class="form-group">
            <label>渠道类型</label>
            <select name="type" class="form-control">
                <option value="openai" <?php echo $type === 'openai' ? 'selected' : ''; ?>>OpenAI</option>
                <option value="azure" <?php echo $type === 'azure' ? 'selected' : ''; ?>>Azure</option>
                <option value="custom" <?php echo $type === 'custom' ? 'selected' : ''; ?>>自定义（OpenAI 兼容）</option>
            </select>
        </div>
        <div class="form-group">
            <label>渠道地址</label>
            <input type="text" name="base_url" class="form-control" value="<?php echo e($baseUrl); ?>" placeholder="https://api.openai.com">
            <div class="form-hint">支持带 /v1 结尾，例如 https://api.openai.com/v1</div>
        </div>
        <div class="form-group">
            <label>API Key</label>
            <input type="text" name="api_key" class="form-control" value="<?php echo $channel ? '******' : ''; ?>" placeholder="sk-xxx">
            <div class="form-hint"><?php echo $channel ? '留空或保持 ****** 表示不修改' : '渠道的上游密钥'; ?></div>
        </div>
        <div class="form-group">
            <label>支持的模型（点击下方模型即可选中/取消，也可手动编辑，支持通配符，留空=全部）</label>
            <textarea id="modelsInput" name="models" class="form-control" rows="3" placeholder="gpt-4o,gpt-4o-mini,text-embedding-3-small,image-*"><?php echo e($models); ?></textarea>
            <div style="display:flex; align-items:center; gap:8px; margin-top:8px;">
                <button type="button" id="fetchModelsBtn" class="btn btn-sm">从云端获取模型</button>
                <span id="fetchModelsStatus" style="font-size:12px; color:var(--text-2);"></span>
            </div>
            <div id="modelChips" class="model-chips" style="display:none; margin-top:10px;">
                <div style="margin-bottom:8px; font-size:12px; color:var(--text-2);">共 <span id="modelCount">0</span> 个模型，点击选择；
                    <a href="javascript:void(0)" id="selectAllModels">全选</a> · <a href="javascript:void(0)" id="clearModels">清空</a>
                </div>
                <div id="modelChipList" style="display:flex; flex-wrap:wrap; gap:6px;"></div>
            </div>
        </div>
        <div class="form-group" style="display:flex; gap:16px;">
            <div style="flex:1;">
                <label>权重</label>
                <input type="number" name="weight" class="form-control" value="<?php echo $weight; ?>" min="1">
            </div>
            <div style="flex:1;">
                <label>优先级（越大越优先）</label>
                <input type="number" name="priority" class="form-control" value="<?php echo $priority; ?>">
            </div>
        </div>
        <div class="form-group">
            <label>备注</label>
            <input type="text" name="remark" class="form-control" value="<?php echo e($remark); ?>">
        </div>
        <div class="form-group" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <label style="margin:0;">启用渠道</label>
            <label class="ios-switch"><input type="checkbox" name="status" value="1" <?php echo $status ? 'checked' : ''; ?>><span></span></label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn"><?php echo $channel ? '保存' : '创建'; ?></button>
            <a href="<?php echo base_url('admin/channels/index.php'); ?>" class="btn btn-secondary">返回</a>
        </div>
    </div>
</form>

<style>
.model-chip { display:inline-block; padding:3px 10px; font-size:12px; border-radius:999px; border:1px solid var(--border); background:var(--card-2); color:var(--text-2); cursor:pointer; user-select:none; transition:.15s; }
.model-chip:hover { border-color:var(--accent); color:var(--accent); }
.model-chip.on { background:linear-gradient(135deg, var(--accent), var(--accent-2)); border-color:transparent; color:#fff; }
</style>
<script>
(function () {
    var btn = document.getElementById('fetchModelsBtn');
    var status = document.getElementById('fetchModelsStatus');
    var chipsBox = document.getElementById('modelChips');
    var chipList = document.getElementById('modelChipList');
    var countEl = document.getElementById('modelCount');
    var input = document.getElementById('modelsInput');
    var allModels = [];

    function currentSelected() {
        return input.value.split(',').map(function (s) { return s.trim(); }).filter(function (s) { return s !== ''; });
    }

    function syncTextarea(list) {
        input.value = list.join(',');
    }

    function renderChips() {
        var selected = currentSelected();
        chipList.innerHTML = '';
        allModels.forEach(function (m) {
            var span = document.createElement('span');
            span.className = 'model-chip' + (selected.indexOf(m) !== -1 ? ' on' : '');
            span.textContent = m;
            span.addEventListener('click', function () {
                var cur = currentSelected();
                var idx = cur.indexOf(m);
                if (idx !== -1) { cur.splice(idx, 1); } else { cur.push(m); }
                syncTextarea(cur);
                span.className = 'model-chip' + (idx === -1 ? ' on' : '');
            });
            chipList.appendChild(span);
        });
    }

    btn.addEventListener('click', function () {
        btn.disabled = true;
        status.textContent = '正在从上游获取模型列表…';
        var form = document.querySelector('form');
        var body = new URLSearchParams(new FormData(form));
        body.append('action', 'fetch_models');
        fetch(form.action, { method: 'POST', body: body, headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    allModels = data.models || [];
                    countEl.textContent = allModels.length;
                    chipsBox.style.display = allModels.length ? 'block' : 'none';
                    status.textContent = data.models.length ? '已获取 ' + data.models.length + ' 个模型，点击选择' : '上游未返回任何模型';
                    status.style.color = '';
                    renderChips();
                } else {
                    chipsBox.style.display = 'none';
                    status.textContent = '获取失败：' + data.message;
                    status.style.color = 'var(--red-text, #e5484d)';
                }
            })
            .catch(function () {
                status.textContent = '请求异常，请稍后重试';
                status.style.color = 'var(--red-text, #e5484d)';
            })
            .then(function () { btn.disabled = false; });
    });

    document.getElementById('selectAllModels').addEventListener('click', function () {
        var cur = currentSelected();
        allModels.forEach(function (m) { if (cur.indexOf(m) === -1) { cur.push(m); } });
        syncTextarea(cur);
        renderChips();
    });
    document.getElementById('clearModels').addEventListener('click', function () {
        input.value = '';
        renderChips();
    });
})();
</script>

<?php require dirname(__DIR__) . '/templates/footer.php'; ?>