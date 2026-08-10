<?php
/**
 * 系统定时任务入口：CLI 或 HTTP 均可调用（幂等，按 interval 判定是否到期）
 * CLI:   E:\tools\php82\php.exe tools\cron.php
 * HTTP:  https://domain/tools/cron.php
 */
require dirname(__DIR__) . '/includes/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    /* HTTP 调用需带密钥（cron_secret 配置后生效） */
    $secret = setting('cron_secret', '');
    if ($secret !== '' && !hash_equals($secret, (string)($_GET['key'] ?? ''))) {
        http_response_code(403);
        echo 'forbidden';
        exit;
    }
}

function task_clean_logs(&$result)
{
    $days = max(1, (int)setting('log_retention_days', '30'));
    $since = date('Y-m-d H:i:s', time() - $days * 86400);
    $deleted = DB::query('DELETE FROM logs WHERE created_at < ?', [$since])->rowCount();
    $result .= "清理 logs 早于 {$days} 天前，删除 {$deleted} 行；";
    $deleted = DB::query('DELETE FROM error_logs WHERE created_at < ?', [$since])->rowCount();
    $result .= "清理 error_logs {$deleted} 行；";
}

function task_clean_verifications(&$result)
{
    $since = date('Y-m-d H:i:s', time() - 7 * 86400);
    $deleted = DB::query('DELETE FROM verifications WHERE used = 1 OR expires_at < NOW()', [])->rowCount();
    $result .= "清理已用/过期验证码 {$deleted} 行；";
}

function task_close_expired_orders(&$result)
{
    $since = date('Y-m-d H:i:s', time() - 2 * 86400);
    $deleted = DB::query("UPDATE pay_orders SET status = 'closed' WHERE status = 'pending' AND created_at < ?", [$since])->rowCount();
    $result .= "关闭超时未支付订单 {$deleted} 笔；";
}

function task_expire_subscriptions(&$result)
{
    $deleted = DB::query("UPDATE user_subscriptions SET status = 0 WHERE status = 1 AND end_at < NOW()", [])->rowCount();
    $result .= "过期订阅 {$deleted} 条；";
}

function task_expire_sessions(&$result)
{
    $days = max(7, (int)setting('session_expire_days', '30'));
    $since = date('Y-m-d H:i:s', time() - $days * 86400);
    $deleted = DB::query('DELETE FROM user_sessions WHERE last_active_at < ?', [$since])->rowCount();
    $result .= "清理 {$days} 天前未活动会话 {$deleted} 条；";
}

function task_clean_expired_tokens(&$result)
{
    $deleted = DB::query('DELETE FROM tokens WHERE expired_at IS NOT NULL AND expired_at < NOW()')->rowCount();
    $result .= "清理过期令牌 {$deleted} 个；";
}

function task_midjourney_poll(&$result)
{
    $updated = Midjourney::pollPending();
    $result .= "Midjourney 轮询：更新 {$updated} 个任务；";
}

function task_suno_poll(&$result)
{
    $updated = Suno::pollPending();
    $result .= "Suno 轮询：更新 {$updated} 个任务；";
}

function task_reset_subscriptions(&$result)
{
    /* 重置应重置的订阅额度（按额度重置周期） */
    $resetCount = 0;
    $plans = DB::fetchAll('SELECT sp.id AS plan_id, sp.quota, sp.quota_reset_period, us.id AS sub_id, us.user_id, us.quota_left, us.last_reset_at FROM subscription_plans sp JOIN user_subscriptions us ON us.plan_id = sp.id WHERE us.status = 1 AND sp.quota_reset_period IS NOT NULL AND sp.quota_reset_period != "never"');
    foreach ($plans as $p) {
        $due = false;
        $period = $p['quota_reset_period'];
        if ($period === 'daily') {
            $due = $p['last_reset_at'] === null || strtotime($p['last_reset_at']) < strtotime('today');
        } elseif ($period === 'weekly') {
            $due = $p['last_reset_at'] === null || strtotime($p['last_reset_at']) < strtotime('monday this week');
        } elseif ($period === 'monthly') {
            $due = $p['last_reset_at'] === null || strtotime($p['last_reset_at']) < strtotime('first day of this month');
        }
        if ($due) {
            DB::update('user_subscriptions', ['quota_left' => (float)$p['quota'], 'last_reset_at' => date('Y-m-d H:i:s')], 'id = ?', [(int)$p['sub_id']]);
            $resetCount++;
        }
    }
    /* 订阅到期标记 + 降级分组恢复 */
    $expired = Subscription::expireDue();
    /* 幂等扣费记录保留 7 天后清理 */
    $cleaned = DB::query("DELETE FROM subscription_billing WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")->rowCount();
    $result .= "订阅重置 {$resetCount} 个，到期处理 {$expired} 个，清理幂等记录 {$cleaned} 条；";
}

function task_auto_health(&$result)
{
    $channels = DB::fetchAll('SELECT * FROM channels WHERE status = 1');
    $failed = 0;
    $autoDisable = setting('auto_disable', '0') === '1';
    $threshold = max(1, (int)setting('auto_disable_threshold', '100'));
    foreach ($channels as $channel) {
        $test = Channel::test((int)$channel['id']);
        if (!$test['ok']) {
            $failed++;
            $newFailCount = (int)$channel['fail_count'] + 1;
            DB::query('UPDATE channels SET fail_count = ? WHERE id = ?', [$newFailCount, (int)$channel['id']]);
            if ($autoDisable && $newFailCount >= $threshold) {
                Channel::update((int)$channel['id'], ['status' => 0]);
                write_log("channel #{$channel['id']} disabled by auto_health (fail_count={$newFailCount})", 'task');
            } else {
                write_log("channel health check failed: #{$channel['id']} {$channel['name']} - {$test['message']}", 'task');
            }
        } else {
            DB::query('UPDATE channels SET fail_count = 0 WHERE id = ?', [(int)$channel['id']]);
        }
    }
    $result .= "渠道健康检查：共 " . count($channels) . " 个启用渠道，失败 {$failed} 个；";
}

function task_sync_ratios(&$result)
{
    /* 模型倍率同步：从配置的 JSON 地址拉取 {模型名: {input: 每1M输入价, output: 每1M输出价}}，更新 models 表 */
    $url = trim((string)setting('ratio_sync_url', ''));
    if ($url === '') {
        $result .= '倍率同步：未配置 ratio_sync_url，跳过；';
        return;
    }
    if (!preg_match('#^https?://#i', $url)) {
        $result .= '倍率同步：URL 必须以 http/https 开头；';
        return;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) {
        $result .= "倍率同步：拉取失败（HTTP $code）；";
        return;
    }
    $data = json_decode((string)$body, true);
    if (!is_array($data)) {
        $result .= '倍率同步：返回数据不是 JSON；';
        return;
    }
    $updated = 0;
    foreach ($data as $modelName => $price) {
        if (!is_string($modelName) || $modelName === '' || !is_array($price)) {
            continue;
        }
        $input = isset($price['input']) ? (float)$price['input'] : null;
        $output = isset($price['output']) ? (float)$price['output'] : null;
        if ($input === null && $output === null) {
            continue;
        }
        $set = [];
        if ($input !== null && $input >= 0) { $set['input_price'] = $input; }
        if ($output !== null && $output >= 0) { $set['output_price'] = $output; }
        if ($set === []) {
            continue;
        }
        $affected = DB::update('models', $set, 'name = ?', [$modelName]);
        if ($affected > 0) {
            $updated++;
        }
    }
    Cache::deletePrefix('models');
    $result .= "倍率同步：更新 {$updated} 个模型；";
}

function task_sync_upstream_models(&$result)
{
    /* 对比启用渠道上游模型与 models 表，缺失的自动补录（auto_sync_models=1 时写入，否则仅报告） */
    $channels = DB::fetchAll('SELECT * FROM channels WHERE status = 1');
    $added = 0;
    $totalRemote = 0;
    $autoSync = setting('auto_sync_models', '1') === '1';
    foreach ($channels as $channel) {
        $remote = Channel::fetchRemoteModels($channel);
        if (empty($remote['ok']) || !isset($remote['models']) || !is_array($remote['models'])) {
            write_log('upstream sync failed: #' . $channel['id'] . ' ' . ($remote['message'] ?? '未知错误'), 'task');
            continue;
        }
        $totalRemote += count($remote['models']);
        foreach ($remote['models'] as $m) {
            $name = trim((string)$m);
            if ($name === '') {
                continue;
            }
            if (Model::find($name) === false) {
                if ($autoSync) {
                    DB::insert('models', [
                        'name' => $name,
                        'input_price' => 0.0001,
                        'output_price' => 0.0002,
                        'context_length' => 8192,
                        'max_output' => 2048,
                        'type' => 'chat',
                        'enabled' => 1,
                    ]);
                }
                $added++;
            }
        }
    }
    $result .= "上游模型同步：检查 " . count($channels) . " 个渠道、" . $totalRemote . " 个模型，" . ($autoSync ? '新增' : '待补录') . " {$added} 个；";
}

$tasks = DB::fetchAll('SELECT * FROM system_tasks WHERE status = 1 ORDER BY id ASC');
foreach ($tasks as $task) {
    $due = false;
    if ($task['last_run_at'] === null) {
        $due = true;
    } else {
        $due = strtotime($task['last_run_at']) + (int)$task['interval'] <= time();
    }
    if (!$due) {
        continue;
    }
    $result = '';
    try {
        $fn = 'task_' . $task['type'];
        if (!function_exists($fn)) {
            continue;
        }
        $fn($result);
        DB::update('system_tasks', [
            'last_run_at' => date('Y-m-d H:i:s'),
            'last_result' => mb_substr(trim($result), 0, 500),
        ], 'id = ?', [(int)$task['id']]);
        write_log('task run: ' . $task['type'] . ' ' . $result, 'task');
    } catch (Throwable $ex) {
        DB::update('system_tasks', [
            'last_run_at' => date('Y-m-d H:i:s'),
            'last_result' => '错误：' . mb_substr($ex->getMessage(), 0, 400),
        ], 'id = ?', [(int)$task['id']]);
        write_log('task error: ' . $task['type'] . ' ' . $ex->getMessage(), 'task');
    }
}
echo "cron done\n";
