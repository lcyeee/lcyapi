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
    $deleted = DB::exec('DELETE FROM logs WHERE created_at < ?', [$since]);
    $result .= "清理 logs 早于 {$days} 天前，删除 {$deleted} 行；";
    $deleted = DB::exec('DELETE FROM error_logs WHERE created_at < ?', [$since]);
    $result .= "清理 error_logs {$deleted} 行；";
}

function task_clean_verifications(&$result)
{
    $since = date('Y-m-d H:i:s', time() - 7 * 86400);
    $deleted = DB::exec('DELETE FROM verifications WHERE used = 1 OR expires_at < NOW()', []);
    $result .= "清理已用/过期验证码 {$deleted} 行；";
}

function task_close_expired_orders(&$result)
{
    $since = date('Y-m-d H:i:s', time() - 2 * 86400);
    $deleted = DB::exec("UPDATE pay_orders SET status = 'closed' WHERE status = 'pending' AND created_at < ?", [$since]);
    $result .= "关闭超时未支付订单 {$deleted} 笔；";
}

function task_expire_subscriptions(&$result)
{
    $deleted = DB::exec("UPDATE user_subscriptions SET status = 0 WHERE status = 1 AND end_at < NOW()", []);
    $result .= "过期订阅 {$deleted} 条；";
}

function task_expire_sessions(&$result)
{
    $days = max(7, (int)setting('session_expire_days', '30'));
    $since = date('Y-m-d H:i:s', time() - $days * 86400);
    $deleted = DB::exec('DELETE FROM user_sessions WHERE last_active_at < ?', [$since]);
    $result .= "清理 {$days} 天前未活动会话 {$deleted} 条；";
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
