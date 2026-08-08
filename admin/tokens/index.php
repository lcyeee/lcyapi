<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Admin::requireAdmin();
$pageTitle = '令牌管理';

$newKeyFlash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        session_flash('flash_error', '表单已过期，请重试');
        redirect(base_url('admin/tokens/index.php'));
    }
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'create') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '令牌');
        $quota = (float)($_POST['remain_quota'] ?? -1);
        $expired = trim($_POST['expired_at'] ?? '');
        if ($userId <= 0 || User::find($userId) === false) {
            session_flash('flash_error', '用户不存在');
        } elseif ($name === '') {
            session_flash('flash_error', '令牌名称不能为空');
        } else {
            $result = Token::create($userId, $name, $quota, $expired !== '' ? $expired : null);
            if ($result !== false) {
                session_flash('flash_success', '令牌已创建');
                $_SESSION['flash_token_key'] = $result['key'];
                redirect(base_url('admin/tokens/index.php'));
            }
            session_flash('flash_error', '创建失败');
        }
    } elseif ($action === 'toggle') {
        $token = Token::getById($id);
        if ($token !== false) {
            Token::update($id, ['status' => $token['status'] ? 0 : 1]);
            session_flash('flash_success', '令牌状态已更新');
        }
    } elseif ($action === 'delete') {
        if (Token::delete($id)) {
            session_flash('flash_success', '令牌已删除');
        } else {
            session_flash('flash_error', '删除失败');
        }
    } elseif ($action === 'set_quota') {
        $quota = (float)($_POST['remain_quota'] ?? -1);
        if (Token::update($id, ['remain_quota' => $quota])) {
            session_flash('flash_success', '令牌额度已更新');
        }
    }
    redirect(base_url('admin/tokens/index.php'));
}

if (isset($_SESSION['flash_token_key'])) {
    $newKeyFlash = $_SESSION['flash_token_key'];
    unset($_SESSION['flash_token_key']);
}

$keyword = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$where = '';
$params = [];
if ($keyword !== '') {
    $where = ' WHERE t.name LIKE ? OR u.username LIKE ? OR t.`key` LIKE ?';
    $like = '%' . $keyword . '%';
    $params = [$like, $like, $like];
}
$total = (int)DB::value('SELECT COUNT(*) FROM tokens t LEFT JOIN users u ON u.id = t.user_id' . $where, $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$tokens = DB::fetchAll('SELECT t.*, u.username FROM tokens t LEFT JOIN users u ON u.id = t.user_id' . $where . ' ORDER BY t.id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)(($page - 1) * $perPage), $params);
?>
<?php require dirname(__DIR__) . '/templates/header.php'; ?>

<?php if ($newKeyFlash !== '') : ?>
    <div class="alert alert-info">
        新令牌已生成：<code><?php echo e($newKeyFlash); ?></code>（仅显示这一次，请妥善保管）
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-title">新建令牌</div>
    <form method="post" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-group" style="margin:0;">
            <label>所属用户 ID</label>
            <input type="number" name="user_id" class="form-control" style="width:110px;" value="<?php echo e($_POST['user_id'] ?? ''); ?>" required>
        </div>
        <div class="form-group" style="margin:0;">
            <label>令牌名称</label>
            <input type="text" name="name" class="form-control" style="width:160px;" value="<?php echo e($_POST['name'] ?? ''); ?>" placeholder="默认令牌">
        </div>
        <div class="form-group" style="margin:0;">
            <label>令牌额度（$，留空=-1 不限制）</label>
            <input type="number" name="remain_quota" step="0.0001" class="form-control" style="width:160px;" value="<?php echo e($_POST['remain_quota'] ?? ''); ?>">
        </div>
        <div class="form-group" style="margin:0;">
            <label>过期时间（可留空）</label>
            <input type="datetime-local" name="expired_at" class="form-control" style="width:200px;" value="">
        </div>
        <button type="submit" class="btn">创建令牌</button>
    </form>
</div>

<div class="card">
    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center;">
        <span>令牌列表（共 <?php echo $total; ?> 个）</span>
        <form method="get" style="display:flex; gap:8px; align-items:center;">
            <input type="text" name="q" class="form-control" style="width:220px;" value="<?php echo e($keyword); ?>" placeholder="名称 / 用户 / 密钥片段">
            <button type="submit" class="btn btn-sm"><?php echo svg_icon('search'); ?>搜索</button>
        </form>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th><th>名称</th><th>用户</th><th>密钥</th><th>剩余额度</th><th>已用</th>
                <th>次数</th><th>过期时间</th><th>状态</th><th>最后使用</th><th>操作</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($tokens)) : ?>
            <tr><td colspan="11" class="text-center text-muted">暂无令牌</td></tr>
        <?php endif; ?>
        <?php foreach ($tokens as $token) : ?>
            <tr>
                <td><?php echo $token['id']; ?></td>
                <td><?php echo e($token['name']); ?></td>
                <td><?php echo e($token['username'] ?: ('#' . $token['user_id'])); ?></td>
                <td><span class="code-text"><?php echo e(Token::maskKey($token['key'])); ?></span></td>
                <td><?php echo (float)$token['remain_quota'] < 0 ? '不限' : '$' . e(number_format((float)$token['remain_quota'], 4)); ?></td>
                <td>$<?php echo e(number_format((float)$token['used_quota'], 4)); ?></td>
                <td><?php echo number_format((int)$token['used_count']); ?></td>
                <td><?php echo $token['expired_at'] ? e($token['expired_at']) : '-'; ?></td>
                <td><?php echo $token['status'] ? '<span class="badge badge-green">启用</span>' : '<span class="badge badge-gray">停用</span>'; ?></td>
                <td><?php echo $token['last_used_at'] ? e($token['last_used_at']) : '-'; ?></td>
                <td style="white-space:nowrap;">
                    <form method="post" style="display:inline-block; margin-right:4px;">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="id" value="<?php echo $token['id']; ?>">
                        <button type="submit" name="action" value="toggle" class="btn btn-sm btn-warning"><?php echo $token['status'] ? '停用' : '启用'; ?></button>
                    </form>
                    <form method="post" style="display:inline-block; margin-right:4px;" onsubmit="return confirm('确定删除该令牌？')">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="id" value="<?php echo $token['id']; ?>">
                        <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger">删除</button>
                    </form>
                    <a class="btn btn-sm btn-outline" onclick="document.getElementById('quota-<?php echo $token['id']; ?>').style.display='';" href="javascript:void(0)">改额度</a>
                    <form method="post" id="quota-<?php echo $token['id']; ?>" style="display:none; margin-left:6px;">
                        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="id" value="<?php echo $token['id']; ?>">
                        <input type="hidden" name="action" value="set_quota">
                        <input type="number" name="remain_quota" step="0.0001" class="form-control" style="width:120px; display:inline-block;" value="<?php echo e($token['remain_quota']); ?>">
                        <button type="submit" class="btn btn-sm">保存</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($pages > 1) : ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $pages; $i++) : ?>
                <a class="<?php echo $i === $page ? 'current' : ''; ?>" href="?page=<?php echo $i; ?>&q=<?php echo e($keyword); ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>