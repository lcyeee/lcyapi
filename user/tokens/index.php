<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/templates/header.php';

$newKeyFlash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        session_flash('flash_error', '页面已过期，请重试');
        redirect(base_url('user/tokens/index.php'));
    }
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $quota = (float)($_POST['remain_quota'] ?? -1);
        $expired = trim($_POST['expired_at'] ?? '');
        $allowIps = trim($_POST['allow_ips'] ?? '');
        $group = trim($_POST['group'] ?? '');
        $modelLimits = trim($_POST['model_limits'] ?? '');
        $autoGroups = trim($_POST['auto_groups'] ?? '');
        if ($name === '') {
            session_flash('flash_error', '请输入令牌名称');
            redirect(base_url('user/tokens/index.php'));
        }
        $maxTokens = (int)setting('max_user_tokens', '0');
        if ($maxTokens > 0) {
            $count = (int)DB::value('SELECT COUNT(*) FROM tokens WHERE user_id = ?', [Auth::id()]);
            if ($count >= $maxTokens) {
                session_flash('flash_error', '令牌数量已达上限（最多 ' . $maxTokens . ' 个），请删除不用的令牌');
                redirect(base_url('user/tokens/index.php'));
            }
        }
        if ($modelLimits !== '' && json_decode($modelLimits, true) === null) {
            session_flash('flash_error', '模型限制必须是合法 JSON，例如 {"gpt-4o":8000}');
            redirect(base_url('user/tokens/index.php'));
        }
        if (!Group::isUserSelectableGroup($group)) {
            session_flash('flash_error', '分组「' . $group . '」不存在或不可用');
            redirect(base_url('user/tokens/index.php'));
        }
        $autoList = $autoGroups !== '' ? array_filter(array_map('trim', explode(',', $autoGroups))) : [];
        if ($group === 'auto' && count($autoList) > Group::maxTokenAutoGroups()) {
            session_flash('flash_error', '自动分组数量超过上限（最多 ' . Group::maxTokenAutoGroups() . ' 个）');
            redirect(base_url('user/tokens/index.php'));
        }
        if ($allowIps !== '') {
            foreach (explode(',', $allowIps) as $oneIp) {
                if (filter_var(trim($oneIp), FILTER_VALIDATE_IP) === false) {
                    session_flash('flash_error', 'IP 白名单含非法地址：' . trim($oneIp));
                    redirect(base_url('user/tokens/index.php'));
                }
            }
        }
        $result = Token::create(Auth::id(), $name, $quota, $expired !== '' ? $expired : null, $allowIps !== '' ? $allowIps : null, $group !== '' ? $group : 'default', $modelLimits !== '' ? $modelLimits : null, $autoGroups !== '' ? $autoGroups : null);
        if ($result !== false) {
            $newKeyFlash = $result['key'];
        } else {
            session_flash('flash_error', '创建失败');
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = mb_substr(trim($_POST['name'] ?? ''), 0, 100);
        $quotaInput = trim($_POST['remain_quota'] ?? '');
        $quota = $quotaInput === '' ? -1.0 : (float)$quotaInput;
        $expired = trim($_POST['expired_at'] ?? '');
        $allowIps = trim($_POST['allow_ips'] ?? '');
        $group = trim($_POST['group'] ?? '');
        $modelLimits = trim($_POST['model_limits'] ?? '');
        $autoGroups = trim($_POST['auto_groups'] ?? '');
        if ($name === '') {
            session_flash('flash_error', '令牌名称不能为空');
            redirect(base_url('user/tokens/index.php'));
        }
        if ($modelLimits !== '' && json_decode($modelLimits, true) === null) {
            session_flash('flash_error', '模型限制必须是合法 JSON');
            redirect(base_url('user/tokens/index.php'));
        }
        if ($group !== '' && !Group::isUserSelectableGroup($group)) {
            session_flash('flash_error', '分组「' . $group . '」不存在或不可用');
            redirect(base_url('user/tokens/index.php'));
        }
        $autoList = $autoGroups !== '' ? array_filter(array_map('trim', explode(',', $autoGroups))) : [];
        if ($group === 'auto' && count($autoList) > Group::maxTokenAutoGroups()) {
            session_flash('flash_error', '自动分组数量超过上限（最多 ' . Group::maxTokenAutoGroups() . ' 个）');
            redirect(base_url('user/tokens/index.php'));
        }
        if ($allowIps !== '') {
            foreach (explode(',', $allowIps) as $oneIp) {
                if (filter_var(trim($oneIp), FILTER_VALIDATE_IP) === false) {
                    session_flash('flash_error', 'IP 白名单含非法地址：' . trim($oneIp));
                    redirect(base_url('user/tokens/index.php'));
                }
            }
        }
        $ok = Token::update($id, [
            'name' => $name,
            'remain_quota' => $quota,
            'expired_at' => $expired !== '' ? $expired : null,
            'allow_ips' => $allowIps !== '' ? mb_substr($allowIps, 0, 500) : null,
            'group' => $group !== '' ? mb_substr($group, 0, 32) : null,
            'model_limits' => $modelLimits !== '' ? $modelLimits : null,
            'auto_groups' => $autoGroups !== '' ? mb_substr($autoGroups, 0, 255) : null,
        ], Auth::id());
        session_flash($ok ? 'flash_success' : 'flash_error', $ok ? '令牌已更新' : '更新失败');
        redirect(base_url('user/tokens/index.php'));
    } elseif ($action === 'toggle') {
        Token::update((int)($_POST['id'] ?? 0), ['status' => 0], Auth::id());
        session_flash('flash_success', '令牌已停用');
        redirect(base_url('user/tokens/index.php'));
    } elseif ($action === 'delete') {
        if (Token::delete((int)($_POST['id'] ?? 0), Auth::id())) {
            session_flash('flash_success', '令牌已删除');
        }
        redirect(base_url('user/tokens/index.php'));
    }
}

$tokens = Token::getByUser(Auth::id());
?>
<?php $usableGroupOptions = Group::usableGroups(); ?>
<div class="card">
    <div class="card-title">创建令牌</div>
    <?php if ($newKeyFlash !== '') : ?>
        <div class="alert alert-info" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <span>新令牌已生成：<code id="newTokenKey"><?php echo e($newKeyFlash); ?></code>（仅显示这一次，请妥善保管）</span>
            <button type="button" class="btn btn-sm btn-secondary" data-copy-target="#newTokenKey"><?php echo svg_icon('copy'); ?>复制</button>
        </div>
    <?php endif; ?>
    <form method="post" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-group" style="margin:0;">
            <label>令牌名称</label>
            <input type="text" name="name" class="form-control" style="width:180px;" placeholder="例如：开发环境">
        </div>
        <div class="form-group" style="margin:0;">
            <label>令牌额度（$，留空不限制）</label>
            <input type="number" name="remain_quota" step="0.0001" class="form-control" style="width:160px;">
        </div>
        <div class="form-group" style="margin:0;">
            <label>过期时间（可留空）</label>
            <input type="datetime-local" name="expired_at" class="form-control" style="width:200px;">
        </div>
        <div class="form-group" style="margin:0;">
            <label>IP 白名单（逗号分隔，留空不限）</label>
            <input type="text" name="allow_ips" class="form-control" style="width:220px;" placeholder="1.2.3.4,5.6.7.8">
        </div>
        <div class="form-group" style="margin:0;">
            <label>分组</label>
            <select name="group" class="form-control" style="width:140px;">
                <?php foreach ($usableGroupOptions as $gname => $gdesc) : ?>
                    <option value="<?php echo e($gname); ?>"><?php echo e($gname . ($gdesc !== $gname ? '（' . $gdesc . '）' : '')); ?></option>
                <?php endforeach; ?>
                <option value="auto">auto（自动分组）</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>自动分组（逗号分隔，auto 时留空=使用全局自动分组）</label>
            <input type="text" name="auto_groups" class="form-control" style="width:180px;" placeholder="vip,internal">
        </div>
        <div class="form-group" style="margin:0;">
            <label>模型限制 JSON（可选，如 {"gpt-4o":8000}）</label>
            <input type="text" name="model_limits" class="form-control" style="width:220px;" placeholder='{"gpt-4o":8000}'>
        </div>
        <button type="submit" class="btn">创建</button>
    </form>
</div>

<div class="card">
    <div class="card-title">我的令牌（<?php echo count($tokens); ?>）</div>
    <table class="table">
        <thead>
            <tr><th>ID</th><th>名称</th><th>分组</th><th>密钥</th><th>剩余额度</th><th>已用</th>
                <th>次数</th><th>过期时间</th><th>状态</th><th>最后使用</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php if (empty($tokens)) : ?>
            <tr><td colspan="11" class="text-center text-muted">暂无令牌，创建第一个吧</td></tr>
        <?php endif; ?>
        <?php foreach ($tokens as $token) : ?>
            <tr>
                <td><?php echo $token['id']; ?></td>
                <td><?php echo e($token['name']); ?></td>
                <td><span class="badge badge-blue"><?php echo e($token['group'] ?? 'default'); ?></span></td>
                <td><code><?php echo e(Token::maskKey($token['key'])); ?></code></td>
                <td><?php echo (float)$token['remain_quota'] < 0 ? '不限' : '$' . e(number_format((float)$token['remain_quota'], 4)); ?></td>
                <td>$<?php echo e(number_format((float)$token['used_quota'], 4)); ?></td>
                <td><?php echo number_format((int)$token['used_count']); ?></td>
                <td><?php echo $token['expired_at'] ? e($token['expired_at']) : '-'; ?></td>
                <td><?php echo $token['status'] ? '<span class="badge badge-green">启用</span>' : '<span class="badge badge-gray">已停用</span>'; ?></td>
                <td><?php echo $token['last_used_at'] ? e($token['last_used_at']) : '-'; ?></td>
                <td style="white-space:nowrap;">
                    <a class="btn btn-sm btn-outline" href="javascript:void(0)" onclick="toggleEdit(<?php echo $token['id']; ?>)">编辑</a>
                    <?php if ($token['status']) : ?>
                        <form method="post" style="display:inline-block; margin-right:4px;">
                            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?php echo $token['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-warning">停用</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" style="display:inline-block;" data-confirm-title="删除令牌" data-confirm-msg="确定删除该令牌？删除后立即失效。" data-confirm-ok="删除">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $token['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                    </form>
                </td>
            </tr>
            <tr id="token-edit-<?php echo $token['id']; ?>" style="display:none;">
                <td colspan="11">
                    <form method="post" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?php echo $token['id']; ?>">
                        <div class="form-group" style="margin:0;">
                            <label>名称</label>
                            <input type="text" name="name" class="form-control" style="width:150px;" value="<?php echo e($token['name']); ?>">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>额度（留空=不限）</label>
                            <input type="number" name="remain_quota" step="0.0001" class="form-control" style="width:130px;" value="<?php echo (float)$token['remain_quota'] < 0 ? '' : e($token['remain_quota']); ?>">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>过期时间（留空=永久）</label>
                            <input type="datetime-local" name="expired_at" class="form-control" style="width:200px;" value="<?php echo $token['expired_at'] ? e(date('Y-m-d\TH:i', strtotime($token['expired_at']))) : ''; ?>">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>IP 白名单（逗号分隔，留空不限）</label>
                            <input type="text" name="allow_ips" class="form-control" style="width:220px;" value="<?php echo e($token['allow_ips'] ?? ''); ?>">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>分组</label>
                            <select name="group" class="form-control" style="width:132px;">
                                <?php $tokGroupEdit = $token['group'] ?? 'default'; ?>
                                <?php foreach ($usableGroupOptions as $gname => $gdesc) : ?>
                                    <option value="<?php echo e($gname); ?>" <?php echo $tokGroupEdit === $gname ? 'selected' : ''; ?>><?php echo e($gname); ?></option>
                                <?php endforeach; ?>
                                <?php if ($tokGroupEdit !== 'auto' && !isset($usableGroupOptions[$tokGroupEdit])) : ?>
                                    <option value="<?php echo e($tokGroupEdit); ?>" selected><?php echo e($tokGroupEdit); ?>（未配置）</option>
                                <?php endif; ?>
                                <option value="auto" <?php echo $tokGroupEdit === 'auto' ? 'selected' : ''; ?>>auto（自动分组）</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>模型限制 JSON</label>
                            <input type="text" name="model_limits" class="form-control" style="width:200px;" value="<?php echo e($token['model_limits'] ?? ''); ?>" placeholder='{"gpt-4o":8000}'>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>自动分组</label>
                            <input type="text" name="auto_groups" class="form-control" style="width:160px;" value="<?php echo e($token['auto_groups'] ?? ''); ?>">
                        </div>
                        <button type="submit" class="btn btn-sm">保存</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
function toggleEdit(id) {
    var el = document.getElementById('token-edit-' + id);
    if (!el) { return; }
    el.style.display = el.style.display === 'none' ? '' : 'none';
}
</script>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>