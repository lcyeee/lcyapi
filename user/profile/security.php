<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

$errors = [];
$info = '';
$setupSecret = isset($_SESSION['2fa_setup_secret']) ? $_SESSION['2fa_setup_secret'] : '';
$setupBackupCodes = isset($_SESSION['2fa_setup_backup']) ? $_SESSION['2fa_setup_backup'] : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = '页面已过期，请重试';
    } else {
        $action = $_POST['action'] ?? '';
        $uid = Auth::id();
        $me = Auth::user();
        if ($action === 'generate_secret') {
            $secret = TOTP::generateSecret();
            $_SESSION['2fa_setup_secret'] = $secret;
            $_SESSION['2fa_setup_backup'] = TOTP::generateBackupCodes($uid);
            redirect(base_url('user/profile/security.php'));
        } elseif ($action === 'enable_2fa') {
            if ($setupSecret === '') {
                $errors[] = '请先点击「生成密钥」';
            } elseif (TOTP::verify($setupSecret, trim($_POST['code'] ?? ''))) {
                if (empty($setupBackupCodes)) {
                    $setupBackupCodes = TOTP::generateBackupCodes($uid);
                }
                User::update($uid, [
                    'totp_secret' => $setupSecret,
                    'totp_enabled' => 1,
                ]);
                unset($_SESSION['2fa_setup_secret'], $_SESSION['2fa_setup_backup']);
                audit_log('user_enable_2fa', 'user#' . $uid);
                session_flash('flash_success', '两步验证已开启，请妥善保存备份码');
                redirect(base_url('user/profile/security.php'));
            } else {
                $errors[] = '验证码错误，无法开启';
            }
        } elseif ($action === 'disable_2fa') {
            $code = trim($_POST['code'] ?? '');
            if (!TOTP::verify((string)$me['totp_secret'], $code) && !TOTP::consumeBackupCode($uid, $code)) {
                $errors[] = '验证码错误，无法关闭两步验证';
            } else {
                User::update($uid, ['totp_secret' => null, 'totp_enabled' => 0, 'backup_codes' => null]);
                DB::delete('backup_codes', 'user_id = ?', [$uid]);
                audit_log('user_disable_2fa', 'user#' . $uid);
                session_flash('flash_success', '两步验证已关闭');
                redirect(base_url('user/profile/security.php'));
            }
        } elseif ($action === 'regenerate_backup') {
            $codes = TOTP::generateBackupCodes($uid);
            $info = '新备份码（只显示一次，请立即保存）：<br><strong style="letter-spacing:1px;">' . e(implode('　', $codes)) . '</strong>';
        } elseif ($action === 'revoke_session') {
            $result = Auth::revokeSession((int)($_POST['session_id'] ?? 0), $uid);
            if ($result['ok']) {
                session_flash('flash_success', '会话已撤销');
            } else {
                $errors[] = $result['msg'];
            }
            redirect(base_url('user/profile/security.php'));
        } elseif ($action === 'revoke_all') {
            Auth::revokeAllSessions($uid);
            session_flash('flash_success', '已撤销其他全部会话');
            redirect(base_url('user/profile/security.php'));
        } elseif ($action === 'unbind_oauth') {
            $provider = trim($_POST['provider'] ?? '');
            if (OAuth::unbind($uid, $provider)) {
                session_flash('flash_success', '已解除 ' . strtoupper($provider) . ' 绑定');
            } else {
                $errors[] = '解绑失败';
            }
            redirect(base_url('user/profile/security.php'));
        }
    }
}
require dirname(__DIR__) . '/templates/header.php';
$user = Auth::user();
$sessions = DB::fetchAll('SELECT * FROM user_sessions WHERE user_id = ? ORDER BY last_active_at DESC', [Auth::id()]);
$bindings = DB::fetchAll('SELECT * FROM oauth_bindings WHERE user_id = ?', [Auth::id()]);
$bound = [];
foreach ($bindings as $b) {
    $bound[$b['provider']] = $b;
}
?>
<div class="card" style="max-width:640px;">
    <div class="card-title">两步验证（2FA）</div>
    <?php if ((int)$user['totp_enabled'] === 1) : ?>
        <div class="alert alert-success"><?php echo svg_icon('shield'); ?>两步验证已开启</div>
        <form method="post" action="<?php echo base_url('user/profile/security.php'); ?>" style="display:inline-block; margin-right:8px;">
            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="regenerate_backup">
            <button type="submit" class="btn btn-secondary">重新生成备份码</button>
        </form>
        <form method="post" action="<?php echo base_url('user/profile/security.php'); ?>"
              data-confirm-title="关闭两步验证" data-confirm-msg="关闭后账号将失去这层保护，确定继续？" data-confirm-ok="确认关闭">
            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="disable_2fa">
            <div style="display:flex; gap:10px; align-items:center; margin-top:12px; flex-wrap:wrap;">
                <input type="text" name="code" class="form-control" style="flex:1; min-width:150px;" placeholder="输入当前验证码" maxlength="10" required>
                <button type="submit" class="btn btn-danger">关闭两步验证</button>
            </div>
        </form>
        <?php if ($info !== '') : ?>
            <div class="alert alert-warning" style="margin-top:12px;"><?php echo $info; ?></div>
        <?php endif; ?>
    <?php else : ?>
        <?php if ($setupSecret !== '') : ?>
            <div class="form-group">
                <label>1. 在身份验证器（Google Authenticator / Authy）中添加以下密钥</label>
                <div style="background:var(--card-2); border-radius:10px; padding:12px; font-family:monospace; font-size:15px; letter-spacing:2px; word-break:break-all;"><?php echo e($setupSecret); ?></div>
                <div class="form-hint" style="word-break:break-all;"><?php echo e(TOTP::otpauthUrl($setupSecret, $user['username'])); ?></div>
            </div>
            <div class="form-group">
                <label>2. 一次性备份码（只显示一次，请立即保存）</label>
                <div style="background:var(--card-2); border-radius:10px; padding:12px; font-family:monospace; font-size:14px; line-height:2;">
                    <?php foreach ($setupBackupCodes as $c) : ?>
                        <span style="display:inline-block; min-width:110px;"><?php echo e($c); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <label>3. 输入验证器生成的 6 位验证码以确认开启</label>
                <div style="display:flex; gap:10px; align-items:center;">
                    <form method="post" action="<?php echo base_url('user/profile/security.php'); ?>" style="display:flex; gap:10px; flex:1; margin:0;">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="action" value="enable_2fa">
                        <input type="text" name="code" class="form-control" style="flex:1;" placeholder="6 位验证码" maxlength="6" inputmode="numeric" required>
                        <button type="submit" class="btn">确认开启</button>
                    </form>
                    <form method="post" action="<?php echo base_url('user/profile/security.php'); ?>" style="margin:0;">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="action" value="generate_secret">
                        <button type="submit" class="btn btn-secondary">重新生成</button>
                    </form>
                </div>
            </div>
        <?php else : ?>
            <p class="form-hint" style="margin-top:0;">开启后登录需输入身份验证器动态验证码，大幅提升账号安全性。</p>
            <form method="post" action="<?php echo base_url('user/profile/security.php'); ?>">
                <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="generate_secret">
                <button type="submit" class="btn">开启两步验证</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="card" style="max-width:640px;">
    <div class="card-title">登录会话</div>
    <div class="form-hint" style="margin-top:0;">管理所有已登录设备，被撤销的设备将立即下线。</div>
    <?php foreach ($sessions as $s) : ?>
        <?php $isCurrent = hash_equals(hash('sha256', session_id()), $s['sid_hash']); ?>
        <div class="detail-list" style="margin-bottom:10px;">
            <div class="item">
                <div class="k"><?php echo e($s['device'] ?: '未知设备'); ?><?php echo $isCurrent ? ' <span class="badge badge-blue">当前</span>' : ''; ?></div>
                <div class="v">
                    <?php echo e($s['ip']); ?> · <?php echo e($s['last_active_at']); ?>
                    <?php if (!$isCurrent) : ?>
                        <form method="post" action="<?php echo base_url('user/profile/security.php'); ?>" style="display:inline; margin:0;" data-confirm-title="撤销会话" data-confirm-msg="该设备将被强制下线。" data-confirm-ok="撤销">
                            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="action" value="revoke_session">
                            <input type="hidden" name="session_id" value="<?php echo (int)$s['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">撤销</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (count($sessions) > 1) : ?>
        <form method="post" action="<?php echo base_url('user/profile/security.php'); ?>"
              data-confirm-title="撤销全部会话" data-confirm-msg="除当前设备外，其他所有设备将被强制下线。" data-confirm-ok="全部撤销">
            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="revoke_all">
            <button type="submit" class="btn btn-secondary">撤销其他全部会话</button>
        </form>
    <?php endif; ?>
</div>

<div class="card" style="max-width:640px;">
    <div class="card-title">第三方账号绑定</div>
    <div class="detail-list">
        <div class="item">
            <div class="k">GitHub</div>
            <div class="v">
                <?php if (isset($bound['github'])) : ?>
                    <span class="badge badge-green">已绑定</span>
                    <form method="post" action="<?php echo base_url('user/profile/security.php'); ?>" style="display:inline; margin:0;" data-confirm-title="解除绑定" data-confirm-msg="解绑后将无法使用 GitHub 登录。" data-confirm-ok="解绑">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="action" value="unbind_oauth">
                        <input type="hidden" name="provider" value="github">
                        <button type="submit" class="btn btn-sm btn-danger">解绑</button>
                    </form>
                <?php else : ?>
                    <a class="btn btn-sm btn-secondary" href="<?php echo base_url('user/oauth.php?provider=github'); ?>">去绑定</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="item">
            <div class="k">Telegram</div>
            <div class="v">
                <?php if (isset($bound['telegram'])) : ?>
                    <span class="badge badge-green">已绑定</span>
                    <form method="post" action="<?php echo base_url('user/profile/security.php'); ?>" style="display:inline; margin:0;" data-confirm-title="解除绑定" data-confirm-msg="解绑后将无法使用 Telegram 登录。" data-confirm-ok="解绑">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="action" value="unbind_oauth">
                        <input type="hidden" name="provider" value="telegram">
                        <button type="submit" class="btn btn-sm btn-danger">解绑</button>
                    </form>
                <?php else : ?>
                    <a class="btn btn-sm btn-secondary" href="<?php echo base_url('user/oauth.php?provider=telegram'); ?>">去绑定</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="form-hint">绑定后可使用第三方账号一键登录；首次通过第三方登录会自动创建账号。</div>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
