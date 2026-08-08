<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/templates/header.php';

$result = ['ok' => null, 'msg' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $result = ['ok' => false, 'msg' => '页面已过期，请重试'];
    } else {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        if ($code === '') {
            $result = ['ok' => false, 'msg' => '请输入兑换码'];
        } else {
            $redemption = DB::fetch('SELECT * FROM redemptions WHERE code = ?', [$code]);
            if ($redemption === false) {
                $result = ['ok' => false, 'msg' => '兑换码不存在'];
            } elseif ((int)$redemption['status'] !== 1) {
                $result = ['ok' => false, 'msg' => '兑换码已停用'];
            } elseif ($redemption['used_at'] !== null) {
                $result = ['ok' => false, 'msg' => '兑换码已被使用'];
            } else {
                DB::begin();
                try {
                    DB::update('redemptions', [
                        'status' => 0,
                        'used_by' => Auth::id(),
                        'used_at' => date('Y-m-d H:i:s'),
                        'used_ip' => client_ip(),
                    ], 'id = ?', [(int)$redemption['id']]);
                    $ok = User::addQuota(Auth::id(), (float)$redemption['quota'], 'redeem', '兑换码：' . $code, null, (int)$redemption['id']);
                    DB::commit();
                    if ($ok) {
                        $result = ['ok' => true, 'msg' => '兑换成功，已充值 $' . number_format((float)$redemption['quota'], 4)];
                    } else {
                        $result = ['ok' => false, 'msg' => '充值失败，请稍后重试'];
                    }
                } catch (Exception $e) {
                    DB::rollback();
                    $result = ['ok' => false, 'msg' => '兑换失败，请稍后重试'];
                }
            }
        }
    }
}

$recent = DB::fetchAll('SELECT r.code, r.quota, r.used_at, u.username AS by_name FROM redemptions r LEFT JOIN users u ON u.id = r.used_by WHERE r.used_by = ? ORDER BY r.used_at DESC LIMIT 10', [Auth::id()]);
?>
<div class="card" style="max-width:520px;">
    <div class="card-title">兑换码充值</div>
    <?php if ($result['ok'] === true) : ?>
        <div class="alert alert-success"><?php echo e($result['msg']); ?></div>
    <?php elseif ($result['ok'] === false) : ?>
        <div class="alert alert-danger"><?php echo e($result['msg']); ?></div>
    <?php endif; ?>
    <form method="post" action="<?php echo base_url('user/redeem/index.php'); ?>">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <div class="form-group">
            <label>兑换码</label>
            <input type="text" name="code" class="form-control" placeholder="例如：8C813113-1E25-7278" required>
        </div>
        <button type="submit" class="btn">立即兑换</button>
    </form>
</div>

<div class="card">
    <div class="card-title">最近兑换记录</div>
    <table class="table">
        <thead><tr><th>兑换码</th><th>金额</th><th>兑换时间</th></tr></thead>
        <tbody>
        <?php if (empty($recent)) : ?>
            <tr><td colspan="3" class="text-center text-muted">暂无兑换记录</td></tr>
        <?php endif; ?>
        <?php foreach ($recent as $row) : ?>
            <tr>
                <td><code><?php echo e($row['code']); ?></code></td>
                <td>$<?php echo e(number_format((float)$row['quota'], 4)); ?></td>
                <td><?php echo e($row['used_at']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require dirname(__DIR__) . '/templates/footer.php'; ?>