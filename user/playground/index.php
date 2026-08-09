<?php
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
    require ROOT_PATH . '/includes/bootstrap.php';
}
Auth::requireLogin();

if (($_GET['action'] ?? '') === 'chat') {
    $raw = file_get_contents('php://input');
    $req = json_decode($raw, true);
    if (!is_array($req) || empty($req['key']) || empty($req['model']) || empty($req['messages'])) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['error' => ['message' => '参数不完整', 'type' => 'invalid_request_error', 'code' => 'invalid_parameters']], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $endpoint = rtrim((string)config('site.url', 'http://localhost:8000'), '/') . '/v1/chat/completions';
    $body = json_encode([
        'model' => $req['model'],
        'messages' => $req['messages'],
        'stream' => true,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $req['key'],
            'Content-Type: application/json',
            'Accept: text/event-stream',
        ],
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) {
        echo $data;
        @ob_flush();
        flush();
        return strlen($data);
    });
    curl_exec($ch);
    curl_close($ch);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_key') {
    $playKey = trim($_POST['play_key'] ?? '');
    if ($playKey !== '') {
        $_SESSION['playground_key'] = $playKey;
        session_flash('flash_success', '令牌已保存到本次会话');
    } else {
        unset($_SESSION['playground_key']);
        session_flash('flash_success', '已清除令牌');
    }
    redirect(base_url('user/playground/index.php'));
}

$user = Auth::user();
$models = Model::allEnabled();
$playKey = isset($_SESSION['playground_key']) ? (string)$_SESSION['playground_key'] : '';
$pageTitle = 'Playground 测试';
require dirname(__DIR__) . '/templates/header.php';
?>
<div class="card">
    <div class="card-head">
        <h2><?php echo svg_icon('message'); ?>Playground 在线测试</h2>
        <p class="muted">直接走本站 /v1/chat/completions 转发链路，验证渠道与计费</p>
    </div>
    <div class="card-body">
        <form method="post" class="form-inline" id="key-form">
            <input type="hidden" name="action" value="save_key">
            <div class="form-group" style="flex:1;min-width:280px;">
                <label>测试令牌（sk-xxx，仅存本次会话）</label>
                <input type="password" name="play_key" class="form-control" value="<?php echo e($playKey); ?>" placeholder="sk-xxx" autocomplete="off">
            </div>
            <div class="form-group">
                <label>模型</label>
                <select id="play-model" class="form-control">
                    <?php foreach ($models as $m) : ?>
                        <option value="<?php echo e($m['name']); ?>"><?php echo e($m['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-secondary">保存</button>
        </form>

        <div id="chat-box" class="chat-box">
            <div class="chat-empty muted">选择一个模型并发送消息开始测试</div>
        </div>
        <form id="play-form" class="form-inline" style="margin-top:12px;">
            <textarea id="play-input" class="form-control" rows="2" style="flex:1;" placeholder="输入消息，Enter 发送，Shift+Enter 换行"></textarea>
            <button type="submit" class="btn btn-primary" id="play-send">发送</button>
        </form>
    </div>
</div>
<style>
.chat-box { max-height: 460px; overflow-y: auto; background: var(--bg2, rgba(0,0,0,.03)); border-radius: 12px; padding: 14px; }
.chat-empty { text-align: center; padding: 30px 0; }
.chat-msg { margin-bottom: 10px; display: flex; }
.chat-msg.user { justify-content: flex-end; }
.chat-msg .bubble { max-width: 78%; padding: 10px 14px; border-radius: 12px; background: var(--card-bg, #fff); box-shadow: 0 1px 3px rgba(0,0,0,.08); white-space: pre-wrap; word-break: break-word; }
.chat-msg.user .bubble { background: var(--accent, #3478f6); color: #fff; }
.chat-msg .bubble .role { display: block; font-size: 12px; opacity: .65; margin-bottom: 4px; }
.chat-msg.error .bubble { background: var(--danger, #e5484d); color: #fff; }
</style>
<script>
(function () {
    const box = document.getElementById('chat-box');
    const form = document.getElementById('play-form');
    const input = document.getElementById('play-input');
    const sendBtn = document.getElementById('play-send');
    let streaming = false;

    function addMsg(role, text, error) {
        const empty = box.querySelector('.chat-empty');
        if (empty) empty.remove();
        const div = document.createElement('div');
        div.className = 'chat-msg ' + (role === 'user' ? 'user' : '') + (error ? ' error' : '');
        const bubble = document.createElement('div');
        bubble.className = 'bubble';
        const r = document.createElement('span');
        r.className = 'role';
        r.textContent = role === 'user' ? '我' : (error ? '错误' : '模型');
        bubble.appendChild(r);
        const content = document.createElement('div');
        content.className = 'text';
        content.textContent = text;
        bubble.appendChild(content);
        div.appendChild(bubble);
        box.appendChild(div);
        box.scrollTop = box.scrollHeight;
        return content;
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (streaming) return;
        const key = document.querySelector('[name=play_key]').value.trim();
        const model = document.getElementById('play-model').value;
        const text = input.value.trim();
        if (!key) { addMsg('assistant', '请先填写测试令牌并保存', true); return; }
        if (!text) return;
        input.value = '';
        addMsg('user', text);
        const content = addMsg('assistant', '');
        streaming = true;
        sendBtn.disabled = true;

        const messages = [];
        box.querySelectorAll('.chat-msg:not(.error)').forEach(function (m) {
            const isUser = m.classList.contains('user');
            const txtEl = m.querySelector('.text');
            if (!txtEl) return;
            const txt = txtEl.textContent;
            if (isUser && txt.trim()) messages.push({ role: 'user', content: txt });
            if (!isUser && txt.trim() && messages.length) messages.push({ role: 'assistant', content: txt });
        });
        messages.push({ role: 'user', content: text });

        try {
            const resp = await fetch(location.pathname + '?action=chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ key: key, model: model, messages: messages }),
            });
            if (!resp.ok) {
                const err = await resp.json();
                content.textContent = (err.error && err.error.message) || 'HTTP ' + resp.status;
                content.parentElement.parentElement.classList.add('error');
                return;
            }
            const reader = resp.body.getReader();
            const decoder = new TextDecoder();
            let buf = '';
            while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                buf += decoder.decode(value, { stream: true });
                const blocks = buf.split('\n\n');
                buf = blocks.pop();
                for (const block of blocks) {
                    const m = block.match(/^data: (.*)$/m);
                    if (!m) continue;
                    const data = m[1].trim();
                    if (data === '[DONE]') continue;
                    try {
                        const j = JSON.parse(data);
                        const delta = j.choices && j.choices[0] && j.choices[0].delta;
                        if (delta && delta.content) content.textContent += delta.content;
                    } catch (e2) { /* ignore */ }
                }
                box.scrollTop = box.scrollHeight;
            }
        } catch (ex) {
            content.textContent = '请求失败：' + ex.message;
            content.parentElement.parentElement.classList.add('error');
        } finally {
            streaming = false;
            sendBtn.disabled = false;
            box.scrollTop = box.scrollHeight;
        }
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });
})();
</script>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
