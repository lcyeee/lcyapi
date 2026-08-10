<?php
/**
 * 订阅高级化（对齐 new-api：PreConsumeUserSubscription + 升级/降级分组）
 * - 订阅额度池（quota_left）：结算时优先扣订阅池，不足回退钱包（allow_wallet_overflow）
 * - 购买时应用 upgrade_group、快照 prev_user_group；到期应用 downgrade_group
 * - 幂等：chargeByRequestId 用 request_id 防重复扣减
 */
class Subscription
{
    /**
     * 从订阅额度池扣费，返回实际从订阅扣除的金额（不足部分由调用方走钱包）
     * 按 end_at 升序优先扣即将过期的订阅；FOR UPDATE 防并发超扣
     */
    public static function charge($userId, $cost, $requestId = '')
    {
        $cost = (float)$cost;
        if ($cost <= 0) {
            return 0;
        }
        if ($requestId !== '') {
            $preBilled = DB::value('SELECT subscription_amount FROM subscription_billing WHERE request_id = ? AND user_id = ?', [$requestId, (int)$userId]);
            if ($preBilled !== null) {
                return (float)$preBilled;
            }
        }
        $subs = DB::fetchAll('SELECT * FROM user_subscriptions WHERE user_id = ? AND status = 1 AND quota_left > 0 ORDER BY end_at ASC', [(int)$userId]);
        if (empty($subs)) {
            return 0;
        }
        $remaining = $cost;
        $nested = DB::inTransaction();
        if (!$nested) {
            DB::begin();
        }
        try {
            foreach ($subs as $sub) {
                if ($remaining <= 0) {
                    break;
                }
                $locked = DB::fetch('SELECT quota_left FROM user_subscriptions WHERE id = ? FOR UPDATE', [(int)$sub['id']]);
                if ($locked === false) {
                    continue;
                }
                $available = (float)$locked['quota_left'];
                if ($available <= 0) {
                    continue;
                }
                $take = min($remaining, $available);
                DB::update('user_subscriptions', ['quota_left' => round($available - $take, 6)], 'id = ?', [(int)$sub['id']]);
                $remaining -= $take;
            }
            if (!$nested) {
                DB::commit();
            }
        } catch (Exception $ex) {
            if (!$nested) {
                DB::rollback();
            }
            write_log('subscription charge error: ' . $ex->getMessage(), 'subscription');
            /* 嵌套在外部事务中时，把异常继续抛出由外层决定回滚 */
            if ($nested) {
                throw $ex;
            }
            return 0;
        }
        $charged = $cost - $remaining;
        if ($requestId !== '' && $charged > 0) {
            DB::query('INSERT INTO subscription_billing (request_id, user_id, subscription_amount, created_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), subscription_amount = VALUES(subscription_amount)', [$requestId, (int)$userId, round($charged, 6), date('Y-m-d H:i:s')]);
        }
        return round($charged, 6);
    }

    /**
     * 退款到订阅池（任务失败/差额结算回补）
     */
    public static function refund($userId, $amount, $remark = '订阅退款')
    {
        $amount = (float)$amount;
        if ($amount <= 0) {
            return true;
        }
        $sub = DB::fetch('SELECT * FROM user_subscriptions WHERE user_id = ? AND status = 1 ORDER BY end_at DESC LIMIT 1', [(int)$userId]);
        if ($sub === false) {
            return false;
        }
        return DB::update('user_subscriptions', ['quota_left' => round((float)$sub['quota_left'] + $amount, 6)], 'id = ?', [(int)$sub['id']]) !== false;
    }

    /**
     * 开通订阅：扣款（价格>0 从钱包扣）→ 建订阅 → 应用升级分组
     * 返回 ['ok'=>true,'sub_id'=>..] 或 ['ok'=>false,'msg'=>..]
     */
    public static function activate($userId, $planId, $paymentRef = 'wallet')
    {
        $plan = DB::fetch('SELECT * FROM subscription_plans WHERE id = ? AND status = 1', [(int)$planId]);
        if ($plan === false) {
            return ['ok' => false, 'msg' => '套餐不存在或已下架'];
        }
        $userId = (int)$userId;
        $max = (int)$plan['max_purchase_per_user'];
        if ($max > 0) {
            $purchases = (int)DB::value('SELECT purchase_count FROM user_subscriptions WHERE user_id = ? AND plan_id = ? ORDER BY id DESC LIMIT 1', [$userId, (int)$planId]);
            if ($purchases > 0 && $purchases >= $max) {
                return ['ok' => false, 'msg' => '已达该套餐限购次数（' . $max . ' 次）'];
            }
        }
        $price = (float)$plan['price'];
        $existing = DB::fetch('SELECT * FROM user_subscriptions WHERE user_id = ? AND status = 1 AND plan_id = ? ORDER BY end_at DESC LIMIT 1', [$userId, (int)$planId]);
        $now = time();
        $endAt = $existing !== false ? strtotime($existing['end_at']) : $now;
        if ($endAt < $now) {
            $endAt = $now;
        }
        $endAt += (int)$plan['days'] * 86400;
        $newQuota = (float)$plan['quota'];
        $prevQuota = $existing !== false ? (float)$existing['quota_left'] : 0;
        $upgradeGroup = $plan['upgrade_group'] !== '' ? $plan['upgrade_group'] : null;
        $downgradeGroup = $plan['downgrade_group'] !== '' ? $plan['downgrade_group'] : null;
        $prevGroup = null;
        $purchaseCount = (int)DB::value('SELECT COALESCE(MAX(purchase_count), 0) FROM user_subscriptions WHERE user_id = ? AND plan_id = ?', [$userId, (int)$planId]);
        $nextPurchaseCount = max(1, $purchaseCount + 1);

        DB::begin();
        try {
            if ($price > 0) {
                $ok = User::deductQuota($userId, $price);
                if (!$ok) {
                    throw new Exception('钱包余额不足');
                }
                DB::insert('recharge_logs', [
                    'user_id' => $userId,
                    'amount' => -$price,
                    'type' => 'subscribe',
                    'remark' => '订阅套餐：' . $plan['name'] . '（' . $paymentRef . '）',
                ]);
            }
            $user = User::find($userId);
            if ($user === false) {
                throw new Exception('用户不存在');
            }
            if ($upgradeGroup !== null) {
                $prevGroup = $user['group'] !== 'default' ? $user['group'] : null;
                DB::update('users', ['group' => $upgradeGroup], 'id = ?', [$userId]);
            }
            if ($existing !== false) {
                DB::update('user_subscriptions', [
                    'quota_left' => round($prevQuota + $newQuota, 6),
                    'end_at' => date('Y-m-d H:i:s', $endAt),
                    'upgrade_group' => $upgradeGroup,
                    'prev_user_group' => $prevGroup,
                    'downgrade_group' => $downgradeGroup,
                    'purchase_count' => $nextPurchaseCount,
                ], 'id = ?', [(int)$existing['id']]);
                $subId = (int)$existing['id'];
            } else {
                $subId = (int)DB::insert('user_subscriptions', [
                    'user_id' => $userId,
                    'plan_id' => (int)$planId,
                    'start_at' => date('Y-m-d H:i:s', $now),
                    'end_at' => date('Y-m-d H:i:s', $endAt),
                    'status' => 1,
                    'quota_left' => $newQuota,
                    'upgrade_group' => $upgradeGroup,
                    'prev_user_group' => $prevGroup,
                    'downgrade_group' => $downgradeGroup,
                    'purchase_count' => $nextPurchaseCount,
                ]);
            }
            DB::commit();
            return ['ok' => true, 'sub_id' => $subId];
        } catch (Exception $ex) {
            DB::rollback();
            return ['ok' => false, 'msg' => $ex->getMessage()];
        }
    }

    /**
     * 订阅到期处理：标记失效 + 应用降级分组/恢复原分组（cron 调用）
     * 返回处理条数
     */
    public static function expireDue()
    {
        $rows = DB::fetchAll('SELECT * FROM user_subscriptions WHERE status = 1 AND end_at < NOW() ORDER BY id ASC');
        $handled = 0;
        foreach ($rows as $sub) {
            DB::begin();
            try {
                DB::update('user_subscriptions', ['status' => 0], 'id = ?', [(int)$sub['id']]);
                if ($sub['downgrade_group'] !== null && $sub['downgrade_group'] !== '') {
                    DB::update('users', ['group' => $sub['downgrade_group']], 'id = ? AND `group` = COALESCE(NULLIF(?, ""), `group`)', [(int)$sub['user_id'], $sub['upgrade_group']]);
                } elseif ($sub['prev_user_group'] !== null && $sub['prev_user_group'] !== '') {
                    DB::update('users', ['group' => $sub['prev_user_group']], 'id = ? AND `group` = COALESCE(NULLIF(?, ""), `group`)', [(int)$sub['user_id'], $sub['upgrade_group']]);
                }
                DB::commit();
                $handled++;
            } catch (Exception $ex) {
                DB::rollback();
                write_log('subscription expire error: ' . $ex->getMessage(), 'subscription');
            }
        }
        return $handled;
    }

    /**
     * 用户当前有效订阅（含代扣中已用额度）
     */
    public static function activeFor($userId)
    {
        return DB::fetch('SELECT us.*, p.name AS plan_name FROM user_subscriptions us LEFT JOIN subscription_plans p ON p.id = us.plan_id WHERE us.user_id = ? AND us.status = 1 ORDER BY us.id DESC LIMIT 1', [(int)$userId]);
    }

    /**
     * 订阅池可用余额（所有有效订阅 quota_left 之和）
     */
    public static function poolBalance($userId)
    {
        $v = DB::value('SELECT SUM(quota_left) FROM user_subscriptions WHERE user_id = ? AND status = 1 AND quota_left > 0', [(int)$userId]);
        return $v !== null ? (float)$v : 0.0;
    }

    /**
     * 更新钱包余额记录（recharge_logs 语义复用：订阅扣款记负数金额）
     */
    public static function logPurchase($userId, $amount, $planName)
    {
        return true;
    }
}