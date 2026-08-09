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
        'type' => ChannelType::exists(($_POST['type'] ?? 'openai')) ? $_POST['type'] : 'openai',
        'base_url' => rtrim(trim($_POST['base_url'] ?? ''), '/'),
        'api_key' => trim($_POST['api_key'] ?? ''),
        'api_keys' => trim($_POST['api_keys'] ?? ''),
        'tags' => mb_substr(trim($_POST['tags'] ?? ''), 0, 255),
        'models' => trim($_POST['models'] ?? ''),
        'group' => mb_substr(trim($_POST['group'] ?? ''), 0, 500),
        'model_mapping' => trim($_POST['model_mapping'] ?? ''),
        'extra_headers' => trim($_POST['extra_headers'] ?? ''),
        'weight' => max(1, (int)($_POST['weight'] ?? 1)),
        'priority' => (int)($_POST['priority'] ?? 0),
        'status' => empty($_POST['status']) ? 0 : 1,
        'remark' => mb_substr(trim($_POST['remark'] ?? ''), 0, 255),
    ];
    if ($data['api_keys'] !== '') {
        $keysList = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $data['api_keys']) ?: []), function ($k) {
            return $k !== '';
        }));
        $data['api_keys'] = json_encode($keysList, JSON_UNESCAPED_UNICODE);
    }
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
$apiKeysText = '';
if ($channel && !empty($channel['api_keys'])) {
    $keysArr = json_decode((string)$channel['api_keys'], true);
    if (is_array($keysArr)) {
        $apiKeysText = implode("\n", $keysArr);
    }
}
$tags = $channel ? $channel['tags'] : '';
$models = $channel ? $channel['models'] : '';
$group = $channel ? $channel['group'] : '';
$modelMapping = $channel ? $channel['model_mapping'] : '';
$extraHeaders = $channel ? $channel['extra_headers'] : '';
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
                <?php foreach (ChannelType::options() as $tKey => $tName): ?>
                    <option value="<?php echo $tKey; ?>" <?php echo $type === $tKey ? 'selected' : ''; ?>><?php echo $tName; ?></option>
                <?php endforeach; ?>
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
            <label>多 Key（每行一个，转发时随机选取，可选）</label>
            <textarea name="api_keys" class="form-control" rows="3" placeholder="sk-key1&#10;sk-key2"><?php echo e($apiKeysText); ?></textarea>
            <div class="form-hint">填入后按多 Key 随机轮换使用；留空则使用上方单个 API Key</div>
        </div>
        <div class="form-group">
            <label>标签（逗号分隔，可选）</label>
            <input type="text" name="tags" class="form-control" value="<?php echo e($tags); ?>" placeholder="主力,备用">
            <div class="form-hint">用于渠道列表筛选与管理</div>
        </div>
        <div class="form-group">
            <label>支持的模型（点选下方模型或手动输入，支持粘贴逗号分隔列表与通配符，留空=全部）</label>
            <textarea id="modelsInput" name="models" class="form-control" rows="2" placeholder="gpt-4o,gpt-4o-mini,text-embedding-3-small,image-*"></textarea>
            <div id="selectedChips" class="selected-chips" style="display:none;"></div>
            <div style="display:flex; align-items:center; gap:8px; margin-top:8px; flex-wrap:wrap;">
                <button type="button" id="fetchModelsBtn" class="btn btn-sm">从云端获取模型</button>
                <input type="text" id="modelSearch" class="form-control" placeholder="搜索模型…" style="display:none; width:160px; padding:5px 10px; font-size:12px;">
                <span id="fetchModelsStatus" style="font-size:12px; color:var(--text-2);"></span>
            </div>
            <div id="modelChips" class="model-chips" style="display:none; margin-top:10px;">
                <div style="margin-bottom:8px; font-size:12px; color:var(--text-2); display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                    共 <span id="modelCount">0</span> 个模型，已选 <span id="selectedCount">0</span> 个；
                    <a href="javascript:void(0)" id="selectAllModels">全选</a> ·
                    <a href="javascript:void(0)" id="invertModels">反选</a> ·
                    <a href="javascript:void(0)" id="clearModels">清空</a>
                </div>
                <div id="modelGroups"></div>
            </div>
        </div>
        <div class="form-group">
            <label>服务分组</label>
            <input type="text" name="group" class="form-control" value="<?php echo e($group); ?>" placeholder="default,vip（逗号分隔，留空=服务所有分组）">
            <div class="form-hint">令牌/用户按分组匹配渠道；留空表示该渠道服务所有分组</div>
        </div>
        <div class="form-group">
            <label>模型映射（JSON，可选）</label>
            <textarea name="model_mapping" class="form-control" rows="2" placeholder='{"gpt-4o":"gpt-4o-0613","*":"gpt-3.5-turbo"}'><?php echo e($modelMapping); ?></textarea>
            <div class="form-hint">客户端模型 → 上游模型；支持精确匹配、* 前缀通配与 "*" 兜底，转发时自动替换</div>
        </div>
        <div class="form-group">
            <label>附加请求头（JSON，可选）</label>
            <textarea name="extra_headers" class="form-control" rows="2" placeholder='{"X-Custom":"value"}'><?php echo e($extraHeaders); ?></textarea>
            <div class="form-hint">转发到上游时附加的 HTTP 请求头</div>
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
.model-group { margin-bottom:10px; }
.model-group-head { display:flex; align-items:center; gap:8px; font-size:12px; color:var(--text-2); margin-bottom:6px; }
.model-group-name { font-weight:600; color:var(--text); }
.model-group-link { cursor:pointer; color:var(--accent); }
.sel-chip { display:inline-flex; align-items:center; gap:5px; padding:3px 9px; font-size:12px; border-radius:999px; background:var(--accent-soft); color:var(--accent); margin:6px 6px 0 0; }
.sel-chip-x { cursor:pointer; font-weight:700; opacity:.65; }
.sel-chip-x:hover { opacity:1; }
</style>
<script>
(function () {
    var btn = document.getElementById('fetchModelsBtn');
    var status = document.getElementById('fetchModelsStatus');
    var chipsBox = document.getElementById('modelChips');
    var groupsBox = document.getElementById('modelGroups');
    var countEl = document.getElementById('modelCount');
    var selCountEl = document.getElementById('selectedCount');
    var searchEl = document.getElementById('modelSearch');
    var input = document.getElementById('modelsInput');
    var selBox = document.getElementById('selectedChips');
    var allModels = [];
    var initialModels = <?php echo json_encode(array_values(array_filter(array_map('trim', explode(',', $models)))) ?: [], JSON_UNESCAPED_UNICODE); ?>;
    input.value = initialModels.join(',');

    /* 按模型名规则分组（参照 lcyapi 的模型类型划分） */
    var GROUPS = [
        { name: '对话', test: function (m) { return /^(gpt-|o\d|chatgpt|claude|gemini|deepseek|qwen|llama|glm-|moonshot|mistral|phi-|yi-|command|jina|grok|doubao|ep-|kimi|minimax)/i.test(m); } },
        { name: '嵌入', test: function (m) { return /embed/i.test(m); } },
        { name: '重排', test: function (m) { return /rerank/i.test(m); } },
        { name: '图像', test: function (m) { return /^(dall-|image-|sd|stable|flux|mj|midjourney|cogview|wanx|kolors)/i.test(m); } },
        { name: '语音', test: function (m) { return /whisper|tts|speech|audio|suno|voice|asr/i.test(m); } }
    ];

    function groupOf(m) {
        for (var i = 0; i < GROUPS.length; i++) {
            if (GROUPS[i].test(m)) { return GROUPS[i].name; }
        }
        return '其他';
    }

    function currentSelected() {
        return input.value.split(',').map(function (s) { return s.trim(); }).filter(function (s) { return s !== ''; });
    }

    function syncTextarea(list) {
        input.value = list.join(',');
    }

    function renderSelectedChips() {
        var sel = currentSelected();
        selCountEl.textContent = sel.length;
        selBox.innerHTML = '';
        selBox.style.display = sel.length ? 'block' : 'none';
        sel.forEach(function (m) {
            var c = document.createElement('span');
            c.className = 'sel-chip';
            var t = document.createElement('span');
            t.textContent = m;
            c.appendChild(t);
            var x = document.createElement('span');
            x.className = 'sel-chip-x';
            x.textContent = '×';
            x.title = '移除';
            x.addEventListener('click', function () {
                var cur = currentSelected();
                var idx = cur.indexOf(m);
                if (idx !== -1) { cur.splice(idx, 1); }
                syncTextarea(cur);
                renderAll();
            });
            c.appendChild(x);
            selBox.appendChild(c);
        });
    }

    function toggleModel(m) {
        var cur = currentSelected();
        var idx = cur.indexOf(m);
        if (idx !== -1) { cur.splice(idx, 1); } else { cur.push(m); }
        syncTextarea(cur);
        renderAll();
    }

    function renderGroups() {
        var kw = searchEl.value.trim().toLowerCase();
        var sel = currentSelected();
        groupsBox.innerHTML = '';
        var byGroup = {};
        allModels.forEach(function (m) {
            if (kw && m.toLowerCase().indexOf(kw) === -1) { return; }
            var g = groupOf(m);
            (byGroup[g] = byGroup[g] || []).push(m);
        });
        var order = GROUPS.map(function (g) { return g.name; }).concat(['其他']);
        var shown = 0;
        order.forEach(function (gname) {
            var list = byGroup[gname];
            if (!list || !list.length) { return; }
            shown += list.length;
            var gdiv = document.createElement('div');
            gdiv.className = 'model-group';
            var head = document.createElement('div');
            head.className = 'model-group-head';
            var allOn = list.every(function (m) { return sel.indexOf(m) !== -1; });
            head.innerHTML = '<span class="model-group-name">' + gname + '</span><span>(' + list.length + ')</span>' +
                '<span class="model-group-link" data-g="' + gname + '">' + (allOn ? '取消本组' : '全选本组') + '</span>';
            head.querySelector('.model-group-link').addEventListener('click', function () {
                var cur = currentSelected();
                var every = list.every(function (m) { return cur.indexOf(m) !== -1; });
                list.forEach(function (m) {
                    var i = cur.indexOf(m);
                    if (every) { if (i !== -1) { cur.splice(i, 1); } } else { if (i === -1) { cur.push(m); } }
                });
                syncTextarea(cur);
                renderAll();
            });
            gdiv.appendChild(head);
            var wrap = document.createElement('div');
            wrap.style.cssText = 'display:flex; flex-wrap:wrap; gap:6px;';
            list.forEach(function (m) {
                var span = document.createElement('span');
                span.className = 'model-chip' + (sel.indexOf(m) !== -1 ? ' on' : '');
                span.textContent = m;
                span.addEventListener('click', function () { toggleModel(m); });
                wrap.appendChild(span);
            });
            gdiv.appendChild(wrap);
            groupsBox.appendChild(gdiv);
        });
        if (!shown) {
            groupsBox.innerHTML = '<div style="font-size:12px; color:var(--text-3); padding:6px 0;">无匹配模型</div>';
        }
    }

    function renderAll() {
        renderGroups();
        renderSelectedChips();
    }

    /* 粘贴时自动拆分逗号/中文逗号/换行/分号/空格（参照 lcyapi 的批量添加） */
    input.addEventListener('paste', function (e) {
        var text = (e.clipboardData || window.clipboardData).getData('text');
        if (!text || !/[,，;；\n\r\s]/.test(text)) { return; }
        e.preventDefault();
        var parts = text.split(/[,，;；\n\r\s]+/).map(function (s) { return s.trim(); }).filter(Boolean);
        var cur = currentSelected();
        parts.forEach(function (p) { if (cur.indexOf(p) === -1) { cur.push(p); } });
        syncTextarea(cur);
        renderAll();
    });
    input.addEventListener('input', renderSelectedChips);
    searchEl.addEventListener('input', renderGroups);

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
                    var has = allModels.length > 0;
                    chipsBox.style.display = has ? 'block' : 'none';
                    searchEl.style.display = has ? 'inline-block' : 'none';
                    status.textContent = has ? '已获取 ' + allModels.length + ' 个模型，点击选择' : '上游未返回任何模型';
                    status.style.color = '';
                    renderAll();
                } else {
                    chipsBox.style.display = 'none';
                    searchEl.style.display = 'none';
                    status.textContent = '获取失败：' + data.message;
                    status.style.color = 'var(--red-text)';
                }
            })
            .catch(function () {
                status.textContent = '请求异常，请稍后重试';
                status.style.color = 'var(--red-text)';
            })
            .then(function () { btn.disabled = false; });
    });

    document.getElementById('selectAllModels').addEventListener('click', function () {
        var cur = currentSelected();
        allModels.forEach(function (m) { if (cur.indexOf(m) === -1) { cur.push(m); } });
        syncTextarea(cur);
        renderAll();
    });
    document.getElementById('invertModels').addEventListener('click', function () {
        var cur = currentSelected();
        allModels.forEach(function (m) {
            var i = cur.indexOf(m);
            if (i !== -1) { cur.splice(i, 1); } else { cur.push(m); }
        });
        syncTextarea(cur);
        renderAll();
    });
    document.getElementById('clearModels').addEventListener('click', function () {
        input.value = '';
        renderAll();
    });

    renderSelectedChips();
})();
</script>

<?php require dirname(__DIR__) . '/templates/footer.php'; ?>