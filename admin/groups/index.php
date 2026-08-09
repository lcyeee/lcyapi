<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '分组管理';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        session_flash('flash_error', '页面已过期，请重试');
        redirect(base_url('admin/groups/index.php'));
    }
    if (($_POST['action'] ?? '') === 'delete_group') {
        $name = trim($_POST['group_name'] ?? '');
        $ratio = json_decode((string)setting('group_ratio', ''), true);
        $usable = json_decode((string)setting('user_usable_groups', ''), true);
        if ($name === '' || $name === 'default') {
            session_flash('flash_error', '默认分组不可删除');
            redirect(base_url('admin/groups/index.php'));
        }
        if (is_array($ratio)) {
            unset($ratio[$name]);
            setting_set('group_ratio', json_encode($ratio, JSON_UNESCAPED_UNICODE));
        }
        if (is_array($usable)) {
            unset($usable[$name]);
            setting_set('user_usable_groups', json_encode($usable, JSON_UNESCAPED_UNICODE));
        }
        $resetTokens = empty($_POST['reset_tokens']) ? 0 : 1;
        if ($resetTokens) {
            DB::query('UPDATE tokens SET `group` = ? WHERE `group` = ?', ['default', $name]);
        }
        session_flash('flash_success', '分组「' . $name . '」已删除' . ($resetTokens ? '，相关令牌组已重置为 default' : ''));
        audit_log('group_delete', null, $name);
        redirect(base_url('admin/groups/index.php'));
    }

    $defaults = Group::defaults();
    $input = [
        'group_ratio' => $_POST['group_ratio'] ?? $defaults['group_ratio'],
        'user_usable_groups' => $_POST['user_usable_groups'] ?? $defaults['user_usable_groups'],
        'group_group_ratio' => $_POST['group_group_ratio'] ?? '',
        'topup_group_ratio' => $_POST['topup_group_ratio'] ?? '',
        'auto_groups' => $_POST['auto_groups'] ?? $defaults['auto_groups'],
        'max_token_auto_groups' => $_POST['max_token_auto_groups'] ?? 5,
        'default_use_auto_group' => $_POST['default_use_auto_group'] ?? '',
    ];
    $err = Group::saveAll($input);
    if ($err !== null) {
        session_flash('flash_error', $err);
    } else {
        session_flash('flash_success', '分组设置已保存');
        audit_log('group_save', null, '分组配置已更新');
    }
    redirect(base_url('admin/groups/index.php'));
}

$defaults = Group::defaults();
$get = function ($key, $dk) {
    return (string)setting($key, $dk);
};
$groupRatio = $get('group_ratio', $defaults['group_ratio']);
$usableGroups = $get('user_usable_groups', $defaults['user_usable_groups']);
$groupGroupRatio = $get('group_group_ratio', '');
$topupRatio = $get('topup_group_ratio', '');
$autoGroups = $get('auto_groups', $defaults['auto_groups']);
$maxAuto = (int)setting('max_token_auto_groups', '5');
$defaultUseAuto = setting('default_use_auto_group', '0');
$groups = Group::allGroups();
?>
<?php require dirname(__DIR__) . '/templates/header.php'; ?>

<div class="card" style="max-width:760px;">
    <div class="card-title">已定义分组</div>
    <p class="form-hint" style="margin:0 0 10px;">分组由「分组倍率」配置定义，未列出的分组视为不存在。渠道按逗号分隔的多组标签匹配，用户/令牌归属于单组。倍率作用于计费：用户实际扣费 = 模型价格 × 组倍率（组间倍率优先）。</p>
    <table class="table">
        <thead><tr><th>分组</th><th>可用描述</th><th>计费倍率</th><th>令牌数</th><th>操作</th></tr></thead>
        <tbody>
        <?php
        $ratioMap = Group::ratioMap();
        $usableMap = Group::usableGroups();
        foreach ($groups as $gname) :
            $tokenCount = (int)DB::value('SELECT COUNT(*) FROM tokens WHERE `group` = ?', [$gname]);
            ?>
            <tr>
                <td><span class="badge badge-blue"><?php echo e($gname); ?></span></td>
                <td><?php echo e(isset($usableMap[$gname]) ? $usableMap[$gname] : '-'); ?></td>
                <td><?php echo (float)$ratioMap[$gname]; ?></td>
                <td><?php echo $tokenCount; ?></td>
                <td>
                    <?php if ($gname !== 'default') : ?>
                        <form method="post" style="display:inline-block;" data-confirm-title="删除分组" data-confirm-msg="确定删除分组「<?php echo e($gname); ?>」？该分组下的令牌将无法匹配渠道。" data-confirm-ok="删除">
                            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="action" value="delete_group">
                            <input type="hidden" name="group_name" value="<?php echo e($gname); ?>">
                            <input type="hidden" name="reset_tokens" value="1">
                            <button type="submit" class="btn btn-sm btn-danger">删除</button>
                        </form>
                    <?php else : ?>
                        <span class="text-muted" style="font-size:12px;">不可删除</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<form method="post" action="<?php echo base_url('admin/groups/index.php'); ?>">
    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
    <div class="card" style="max-width:760px;">
        <div class="card-title">分组配置（JSON，对照 lcyapi 的 GroupRatio/UserUsableGroups）</div>
        <div class="form-group">
            <label>分组倍率 GroupRatio（组名 → 计费倍率）</label>
            <textarea name="group_ratio" class="form-control" rows="4" spellcheck="false"><?php echo e($groupRatio); ?></textarea>
            <div class="form-hint">示例子：{"default":1,"vip":0.8,"svip":0.6}。新增分组在此添加组名与倍率即可，default 恒为 1。</div>
        </div>
        <div class="form-group">
            <label>用户可选分组 UserUsableGroups（组名 → 中文描述，令牌组下拉与「auto」候选）</label>
            <textarea name="user_usable_groups" class="form-control" rows="3" spellcheck="false"><?php echo e($usableGroups); ?></textarea>
            <div class="form-hint">例：{"default":"默认分组","vip":"VIP 分组"}。未在此列出的分组不能作为令牌分组。</div>
        </div>
        <div class="form-group">
            <label>组间倍率 GroupGroupRatio（用户组 → 目标组倍率，优先于分组倍率）</label>
            <textarea name="group_group_ratio" class="form-control" rows="3" spellcheck="false"><?php echo e($groupGroupRatio); ?></textarea>
            <div class="form-hint">例：{"vip":{"edit_this":0.9}}。可留空。用户组 vip 访问目标组 edit_this 的渠道按 0.9 倍计费。</div>
        </div>
        <div class="form-group">
            <label>充值倍率 TopupGroupRatio（兑换码/后台加额按用户组加成）</label>
            <textarea name="topup_group_ratio" class="form-control" rows="2" spellcheck="false"><?php echo e($topupRatio); ?></textarea>
            <div class="form-hint">例：{"vip":1.2} 表示 vip 用户兑换 $1 到账 $1.2。可留空表示全部 1 倍。</div>
        </div>
        <div class="form-group">
            <label>全局自动分组 AutoGroups（令牌分组为 auto 时随机轮询这些组）</label>
            <textarea name="auto_groups" class="form-control" rows="2" spellcheck="false"><?php echo e($autoGroups); ?></textarea>
            <div class="form-hint">JSON 字符串数组，例如 ["default","vip"]。</div>
        </div>
        <div class="form-group" style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end;">
            <div style="flex:1; min-width:180px;">
                <label>令牌 auto_groups 上限（MaxTokenAutoGroups）</label>
                <input type="number" name="max_token_auto_groups" min="1" max="100" class="form-control" value="<?php echo $maxAuto; ?>">
            </div>
            <div style="display:flex; align-items:center; gap:10px; padding-bottom:8px;">
                <label style="margin:0;">新建令牌默认分组为 auto</label>
                <label class="ios-switch"><input type="checkbox" name="default_use_auto_group" value="1" <?php echo $defaultUseAuto === '1' ? 'checked' : ''; ?>><span></span></label>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">保存分组设置</button>
        </div>
    </div>
</form>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>