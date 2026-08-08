<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/templates/header.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = '页面已过期，请重试';
    } else {
        $formType = isset($_POST['form_type']) ? $_POST['form_type'] : 'profile';
        if ($formType === 'delete') {
            /* 删除账号：二次确认文本必须输入当前用户名 */
            $confirmName = trim($_POST['confirm_username'] ?? '');
            $me = Auth::user();
            if ($me['role'] === 'admin') {
                $errors[] = '管理员账号不能自助删除，请先移交管理员权限';
            } elseif ($confirmName !== $me['username']) {
                $errors[] = '请输入正确的用户名以确认删除';
            } else {
                $uid = Auth::id();
                Auth::logout();
                User::delete($uid);
                redirect(base_url('user/login.php'));
            }
        } elseif ($formType === 'password') {
            $result = Auth::changePassword(Auth::id(), $_POST['old_password'] ?? '', $_POST['new_password'] ?? '');
            if (!$result['ok']) {
                $errors[] = $result['msg'];
            } else {
                session_flash('flash_success', '密码已修改');
                redirect(base_url('user/profile/index.php'));
            }
        } else {
            $nickname = mb_substr(trim($_POST['nickname'] ?? ''), 0, 50);
            $email = trim($_POST['email'] ?? '');
            $data = ['nickname' => $nickname !== '' ? $nickname : null];
            if ($email !== '') {
                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $errors[] = '邮箱格式不正确';
                } else {
                    $exists = DB::fetch('SELECT id FROM users WHERE email = ? AND id != ?', [$email, Auth::id()]);
                    if ($exists !== false) {
                        $errors[] = '该邮箱已被使用';
                    } else {
                        $data['email'] = $email;
                    }
                }
            } else {
                $data['email'] = null;
            }
            if (empty($errors)) {
                User::update(Auth::id(), $data);
                session_flash('flash_success', '个人资料已更新');
                redirect(base_url('user/profile/index.php'));
            }
        }
    }
}
$user = Auth::user();
?>
<div class="card" style="max-width:520px;">
    <div class="card-title">个人资料</div>
    <?php foreach ($errors as $err) : ?>
        <div class="alert alert-danger"><?php echo e($err); ?></div>
    <?php endforeach; ?>
    <form method="post" action="<?php echo base_url('user/profile/index.php'); ?>">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="form_type" value="profile">
        <div class="form-group">
            <label>用户名（不可修改）</label>
            <input type="text" class="form-control" value="<?php echo e($user['username']); ?>" disabled>
        </div>
        <div class="form-group">
            <label>昵称</label>
            <input type="text" name="nickname" class="form-control" value="<?php echo e($user['nickname']); ?>">
        </div>
        <div class="form-group">
            <label>邮箱</label>
            <input type="text" name="email" class="form-control" value="<?php echo e($user['email']); ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">保存</button>
            <a href="<?php echo base_url('user/index.php'); ?>" class="btn btn-secondary">返回</a>
        </div>
    </form>
    <hr style="border:none; border-top:1px solid var(--border); margin:20px 0;">

    <div class="card-title">修改密码</div>
    <form method="post" action="<?php echo base_url('user/profile/index.php'); ?>">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="form_type" value="password">
        <div class="form-group">
            <label>原密码</label>
            <input type="password" name="old_password" class="form-control" required>
        </div>
        <div class="form-group">
            <label>新密码（6-64 位）</label>
            <input type="password" name="new_password" class="form-control" minlength="6" maxlength="64" required>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">修改密码</button>
        </div>
    </form>

    <hr style="border:none; border-top:1px solid var(--border); margin:20px 0;">

    <div class="card-title">账号信息</div>
    <div class="detail-list">
        <div class="item"><div class="k">注册时间</div><div class="v"><?php echo e($user['created_at']); ?></div></div>
        <div class="item"><div class="k">最后登录</div><div class="v"><?php echo e($user['last_login_at'] ?: '-'); ?>（<?php echo e($user['last_login_ip'] ?: '-'); ?>）</div></div>
        <div class="item"><div class="k">累计调用</div><div class="v"><?php echo number_format((int)$user['api_count']); ?> 次</div></div>
        <div class="item"><div class="k">角色</div><div class="v"><?php echo $user['role'] === 'admin' ? '管理员' : '普通用户'; ?></div></div>
    </div>

    <?php if ($user['role'] !== 'admin') : ?>
        <hr style="border:none; border-top:1px solid var(--border); margin:20px 0;">
        <div class="card-title" style="color:var(--red-text);">危险区域</div>
        <form method="post" action="<?php echo base_url('user/profile/index.php'); ?>"
              data-confirm-title="删除账号" data-confirm-msg="确定删除账号？所有令牌与数据将被清除，不可恢复。" data-confirm-ok="确认删除">
            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="form_type" value="delete">
            <div class="form-group" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                <div style="flex:1; min-width:180px;">
                    <label>输入用户名「<?php echo e($user['username']); ?>」以确认</label>
                    <input type="text" name="confirm_username" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-danger">删除我的账号</button>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>