<?php
/**
 * lcyapi 安装向导
 * 首次打开时：配置数据库连接、站点信息并创建管理员账号
 * 场景1：全新安装（无 config.php）→ 填写数据库 + 站点 + 管理员
 * 场景2：已有 config.php 未初始化 → 只需确认管理员账号
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Shanghai');

$ROOT = __DIR__;
$CONFIG_FILE = $ROOT . '/config.php';
$SQL_FILE = $ROOT . '/sql/install.sql';

if (session_status() === PHP_SESSION_NONE) {
    session_name('lcyapi_sid');
    session_start();
}
if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(16));
}

$configExists = is_file($CONFIG_FILE);
$oldConfig = $configExists ? require $CONFIG_FILE : null;
$dbCfg = $configExists ? $oldConfig['db'] : ['host' => '127.0.0.1', 'port' => 3306, 'name' => 'lcyapi', 'user' => 'root', 'pass' => '', 'charset' => 'utf8mb4'];
$siteCfg = $configExists ? $oldConfig['site'] : ['name' => 'lcyapi', 'url' => 'http://localhost:8000'];

function inst_pdo($cfg)
{
    $dsn = 'mysql:host=' . $cfg['host'] . ';port=' . $cfg['port'] . ';dbname=' . $cfg['name'] . ';charset=utf8mb4';
    return new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
}

function inst_test($cfg)
{
    try {
        return inst_pdo($cfg);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Unknown database') !== false) {
            return null;
        }
        throw $e;
    }
}

function inst_create_db($cfg)
{
    $dsn = 'mysql:host=' . $cfg['host'] . ';port=' . $cfg['port'] . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $cfg['name']) . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    return inst_pdo($cfg);
}

function inst_execute_sql(PDO $pdo, $sqlFile)
{
    $sql = file_get_contents($sqlFile);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('无法读取安装脚本: ' . $sqlFile);
    }
    $pdo->exec('SET NAMES utf8mb4');
    $pdo->exec($sql);
}

function inst_write_config($cfg, $site)
{
    global $CONFIG_FILE;
    $arr = function ($v) {
        return var_export($v, true);
    };
    $content = <<<PHP
<?php
return [
    'db' => [
        'host' => {$arr($cfg['host'])},
        'port' => {$arr((int)$cfg['port'])},
        'name' => {$arr($cfg['name'])},
        'user' => {$arr($cfg['user'])},
        'pass' => {$arr($cfg['pass'])},
        'charset' => 'utf8mb4',
    ],
    'site' => [
        'name' => {$arr($site['name'])},
        'url' => {$arr($site['url'])},
        'description' => 'AI 模型网关',
        'register_enabled' => true,
        'default_quota' => 0.0000,
    ],
    'security' => [
        'session_lifetime' => 86400,
        'login_attempts' => 5,
        'login_lock_time' => 900,
        'api_rate_limit' => 60,
        'api_rate_window' => 60,
    ],
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'path' => '',
    ],
    'log' => [
        'level' => 'error',
        'path' => '',
        'save_request_body' => false,
    ],
    'relay' => [
        'timeout' => 120,
        'retry_count' => 0,
        'stream_enabled' => true,
        'auto_disable' => false,
        'auto_disable_threshold' => 100,
    ],
    'app' => [
        'debug' => false,
    ],
    'timezone' => 'Asia/Shanghai',
];
PHP;
    return @file_put_contents($CONFIG_FILE, $content) !== false;
}

$errors = [];
$doneMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_csrf']) || !hash_equals($_SESSION['install_csrf'], (string)$_POST['_csrf'])) {
        $errors[] = '表单已过期，请刷新页面后重试';
    } else {
        $siteName = isset($_POST['site_name']) ? trim($_POST['site_name']) : ($siteCfg['name'] ?? 'lcyapi');
        $adminUser = isset($_POST['admin_username']) ? trim($_POST['admin_username']) : '';
        $adminEmail = isset($_POST['admin_email']) ? trim($_POST['admin_email']) : '';
        $adminPass = isset($_POST['admin_password']) ? $_POST['admin_password'] : '';
        $adminPass2 = isset($_POST['admin_password2']) ? $_POST['admin_password2'] : '';

        if ($siteName === '' || mb_strlen($siteName) > 50) {
            $errors[] = '站点名称不能为空且不超过 50 字';
        }

        if (!$configExists) {
            $dbCfg = [
                'host' => isset($_POST['db_host']) ? trim($_POST['db_host']) : '',
                'port' => (int)($_POST['db_port'] ?? 3306),
                'name' => isset($_POST['db_name']) ? trim($_POST['db_name']) : '',
                'user' => isset($_POST['db_user']) ? trim($_POST['db_user']) : '',
                'pass' => isset($_POST['db_pass']) ? $_POST['db_pass'] : '',
                'charset' => 'utf8mb4',
            ];
            if ($dbCfg['host'] === '' || $dbCfg['name'] === '' || $dbCfg['user'] === '') {
                $errors[] = '数据库主机、库名、用户不能为空';
            } elseif ($dbCfg['port'] < 1 || $dbCfg['port'] > 65535) {
                $errors[] = '数据库端口无效';
            }
        }

        if (preg_match('/^[A-Za-z0-9_]{3,32}$/', $adminUser) !== 1) {
            $errors[] = '管理员用户名需为 3-32 位字母、数字或下划线';
        }
        if ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = '邮箱格式不正确';
        }
        if (strlen($adminPass) < 6 || strlen($adminPass) > 64) {
            $errors[] = '管理员密码长度需在 6-64 位之间';
        } elseif ($adminPass !== $adminPass2) {
            $errors[] = '两次输入的密码不一致';
        }

        if (empty($errors)) {
            try {
                $pdo = inst_test($dbCfg);
                if ($pdo === null && !$configExists) {
                    $pdo = inst_create_db($dbCfg);
                }
                if ($pdo === null) {
                    throw new RuntimeException('无法连接数据库：' . $dbCfg['name']);
                }

                $hasUsers = false;
                try {
                    $hasUsers = $pdo->query("SHOW TABLES LIKE 'users'")->fetch() !== false;
                } catch (Throwable $e) {
                    $hasUsers = false;
                }
                if (!$hasUsers) {
                    inst_execute_sql($pdo, $SQL_FILE);
                }

                if (!$configExists) {
                    $siteUrl = isset($_POST['site_url']) ? rtrim(trim($_POST['site_url']), '/') : '';
                    if ($siteUrl === '' || filter_var($siteUrl, FILTER_VALIDATE_URL) === false) {
                        throw new RuntimeException('站点地址格式不正确');
                    }
                    if (!inst_write_config($dbCfg, ['name' => $siteName, 'url' => $siteUrl])) {
                        throw new RuntimeException('无法写入 config.php，请检查目录写权限');
                    }
                }

                $hasAdmin = (int)$pdo->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'")->fetch(PDO::FETCH_ASSOC)['c'] > 0;
                $msg = '数据库初始化完成';
                if (!$hasAdmin) {
                    $stmt = $pdo->prepare('INSERT INTO users (username, email, password, nickname, role, quota, status) VALUES (?, ?, ?, ?, ?, 10.0000, 1)');
                    $stmt->execute([$adminUser, $adminEmail !== '' ? $adminEmail : null, password_hash($adminPass, PASSWORD_DEFAULT), $adminUser, 'admin']);
                    $msg .= '，管理员账号已创建';
                } else {
                    $msg .= '，管理员账号检测已存在';
                }

                $pdo->prepare("INSERT INTO settings (`key`, value, updated_at) VALUES ('installed', '1', NOW()) ON DUPLICATE KEY UPDATE value = '1', updated_at = NOW()")->execute();
                $pdo->prepare("INSERT INTO settings (`key`, value, updated_at) VALUES ('site_name', ?, NOW()) ON DUPLICATE KEY UPDATE value = ?, updated_at = NOW()")->execute([$siteName, $siteName]);

                $_SESSION['install_done'] = $msg . '，请使用管理员账号登录。';
                header('Location: install.php?done=1');
                exit;
            } catch (Throwable $ex) {
                /* 附带出错位置，便于排查（如「Path cannot be empty」这类无上下文消息） */
                $errors[] = '安装失败：' . $ex->getMessage() . '（' . basename($ex->getFile()) . ':' . $ex->getLine() . '）';
            }
        }
    }
}

if (isset($_GET['done']) && isset($_SESSION['install_done'])) {
    $doneMsg = $_SESSION['install_done'];
    unset($_SESSION['install_done']);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>安装向导 - lcyapi</title>
<script>(function(){try{var m=localStorage.getItem('lcy_mode');var d=m==='dark'||((!m||m==='auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.setAttribute('data-mode',d?'dark':'light');}catch(e){}})();</script>
<script src="assets/js/theme.js"></script>
<link rel="stylesheet" href="assets/css/common.css">
<style>
body { display: flex; align-items: flex-start; justify-content: center; min-height: 100vh; padding: 32px 16px; }
.box { width: 100%; max-width: 620px; position: relative; }
.install-theme-toggle { position: absolute; top: -4px; right: -4px; z-index: 2; width: 38px; height: 38px; background: var(--glass); border: 1px solid var(--glass-border); backdrop-filter: blur(18px); }
.head { background: linear-gradient(135deg, var(--accent), var(--accent-2)); margin: -16px -16px 18px; padding: 22px 26px; border-radius: var(--radius) var(--radius) 0 0; position: relative; overflow: hidden; }
.head::after { content: ""; position: absolute; width: 180px; height: 180px; border-radius: 50%; background: rgba(255,255,255,.14); top: -100px; right: -50px; }
.head h1 { color: #fff; font-size: 20px; margin: 0 0 4px; letter-spacing: -.3px; position: relative; }
.head p { color: rgba(255,255,255,.85); font-size: 13px; margin: 0; position: relative; }
.step-title { font-size: 15px; font-weight: 700; margin: 22px 0 12px; color: var(--text); display: flex; align-items: center; gap: 8px; }
.step-title .no { width: 22px; height: 22px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), var(--accent-2)); color: #fff; font-size: 12px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 2px 8px var(--accent-border); }
.note { background: var(--card-2); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 12px 14px; font-size: 13px; color: var(--text-2); margin-bottom: 16px; line-height: 1.8; }
.install-submit { width: 100%; padding: 12px; font-size: 15px; margin-top: 8px; }
</style>
</head>
<body>
<div class="box">
    <button type="button" class="icon-btn install-theme-toggle" data-theme-toggle title="切换明暗模式"><svg class="i" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg></button>
    <div class="card">
        <div class="head">
            <h1>lcyapi 安装向导</h1>
            <p>首次打开请配置数据库连接并创建管理员账号</p>
        </div>

        <?php if ($doneMsg !== '') : ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($doneMsg); ?></div>
            <p style="margin-bottom:0;">系统安装完成，请 <a href="user/login.php">前往登录</a> 或 <a href="admin/login.php">进入管理后台</a>。</p>
        <?php else : ?>
            <?php foreach ($errors as $err) : ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
            <?php endforeach; ?>

            <form method="post" action="install.php">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_SESSION['install_csrf']); ?>">

                <div class="step-title"><span class="no">1</span> 数据库配置<?php echo $configExists ? '<span style="font-size:12px;color:var(--green-text);font-weight:400;">（使用现有 config.php）</span>' : ''; ?></div>
                <?php if ($configExists) : ?>
                    <div class="note">
                        配置文件 config.php 已存在，将使用其中数据库设置：<br>
                        host: <code><?php echo htmlspecialchars($dbCfg['host'] . ':' . $dbCfg['port']); ?></code>
                        db: <code><?php echo htmlspecialchars($dbCfg['name']); ?></code>
                        user: <code><?php echo htmlspecialchars($dbCfg['user']); ?></code>
                    </div>
                <?php else : ?>
                    <div class="form-group">
                        <label>数据库主机</label>
                        <input type="text" name="db_host" class="form-control" value="<?php echo htmlspecialchars($_POST['db_host'] ?? '127.0.0.1'); ?>" placeholder="127.0.0.1">
                    </div>
                    <div class="form-group">
                        <label>数据库端口</label>
                        <input type="number" name="db_port" class="form-control" value="<?php echo htmlspecialchars($_POST['db_port'] ?? '3306'); ?>">
                    </div>
                    <div class="form-group">
                        <label>数据库名</label>
                        <input type="text" name="db_name" class="form-control" value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'lcyapi'); ?>" placeholder="lcyapi（不存在将自动创建）">
                    </div>
                    <div class="form-group">
                        <label>数据库用户</label>
                        <input type="text" name="db_user" class="form-control" value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'root'); ?>">
                    </div>
                    <div class="form-group">
                        <label>数据库密码</label>
                        <input type="password" name="db_pass" class="form-control" value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>">
                    </div>
                <?php endif; ?>

                <div class="step-title"><span class="no">2</span> 站点信息</div>
                <div class="form-group">
                    <label>站点名称</label>
                    <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($_POST['site_name'] ?? $siteCfg['name']); ?>">
                </div>
                <?php if (!$configExists) : ?>
                    <div class="form-group">
                        <label>站点地址</label>
                        <input type="text" name="site_url" class="form-control" value="<?php echo htmlspecialchars($_POST['site_url'] ?? $siteCfg['url']); ?>" placeholder="http://127.0.0.1:8000">
                        <div class="form-hint">用于生成页面链接与 API 地址</div>
                    </div>
                <?php endif; ?>

                <div class="step-title"><span class="no">3</span> 管理员账号</div>
                <div class="form-group">
                    <label>用户名</label>
                    <input type="text" name="admin_username" class="form-control" value="<?php echo htmlspecialchars($_POST['admin_username'] ?? ''); ?>" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>邮箱（可选）</label>
                    <input type="text" name="admin_email" class="form-control" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>密码</label>
                    <input type="password" name="admin_password" class="form-control" value="" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label>确认密码</label>
                    <input type="password" name="admin_password2" class="form-control" value="" autocomplete="new-password">
                </div>

                <button type="submit" class="btn install-submit">开始安装</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>