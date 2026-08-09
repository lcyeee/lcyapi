<?php
class Billing
{
    public static function getPrice($modelName)
    {
        return Model::find($modelName);
    }

    public static function calculate($priceRow, $promptTokens, $completionTokens, $cachedTokens = 0)
    {
        $inputPrice = (float)$priceRow['input_price'];
        $outputPrice = (float)$priceRow['output_price'];
        $cachedTokens = max(0, min((int)$cachedTokens, max(0, (int)$promptTokens)));
        $uncached = max(0, (int)$promptTokens - $cachedTokens);
        $cachePrice = isset($priceRow['cache_input_price']) && (float)$priceRow['cache_input_price'] >= 0
            ? (float)$priceRow['cache_input_price']
            : $inputPrice;
        $cost = (($uncached / 1000) * $inputPrice) + (($cachedTokens / 1000) * $cachePrice) + (($completionTokens / 1000) * $outputPrice);
        return max(0, $cost);
    }

    public static function calculateImage($priceRow, $imageCount)
    {
        $price = (float)$priceRow['input_price'];
        return max(0, $price * max(1, (int)$imageCount));
    }

    public static function calculateAudio($priceRow, $seconds)
    {
        $price = (float)$priceRow['input_price'];
        return max(0, ($seconds / 60) * $price);
    }

    public static function estimateTokens($text)
    {
        $chinese = self::countChinese($text);
        $english = self::countEnglish($text);
        return (int)ceil(($chinese * 1.5) + ($english * 1.3));
    }

    public static function countChinese($text)
    {
        preg_match_all('/[\x{4e00}-\x{9fff}]/u', (string)$text, $m);
        return count($m[0]);
    }

    public static function countEnglish($text)
    {
        $text = preg_replace('/[\x{4e00}-\x{9fff}]/u', ' ', (string)$text);
        $words = preg_split('/\s+/', trim($text));
        $words = array_filter($words, function ($w) {
            return preg_match('/[A-Za-z0-9]/', (string)$w) === 1;
        });
        return count($words);
    }

    public static function estimateChatTokens($payload)
    {
        $tokens = 0;
        $messages = isset($payload['messages']) && is_array($payload['messages']) ? $payload['messages'] : [];
        foreach ($messages as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $content = isset($msg['content']) ? $msg['content'] : '';
            if (is_array($content)) {
                foreach ($content as $part) {
                    if (is_array($part) && isset($part['text'])) {
                        $tokens += self::estimateTokens($part['text']);
                    }
                }
            } else {
                $tokens += self::estimateTokens((string)$content);
            }
        }
        if (isset($payload['prompt'])) {
            $tokens += self::estimateTokens($payload['prompt']);
        }
        if (isset($payload['input'])) {
            $tokens += self::estimateTokens($payload['input']);
        }
        return $tokens;
    }

    public static function formatCost($cost)
    {
        return '$' . number_format((float)$cost, 6, '.', '');
    }

    /* ============ 预估计费（Text Quota）：请求前估算费用 ============ */

    /**
     * 估算一次聊天请求的费用（用于展示给用户 / 预扣费）
     * @return array ['prompt_tokens','completion_tokens','cost','usage']
     */
    public static function estimateChatCost($priceRow, $payload)
    {
        $promptTokens = self::estimateChatTokens($payload);
        $maxTokens = (int)($payload['max_tokens'] ?? ($payload['max_completion_tokens'] ?? 0));
        $completionTokens = $maxTokens > 0 ? min($maxTokens, 4096) : (int)ceil($promptTokens * 0.5);
        $cachedTokens = 0;
        $cost = self::calculate($priceRow, $promptTokens, $completionTokens, $cachedTokens);
        return [
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'cost' => $cost,
            'usage' => ['prompt_tokens' => $promptTokens, 'completion_tokens' => $completionTokens, 'total_tokens' => $promptTokens + $completionTokens],
        ];
    }

    /* ============ 阶梯计费（Tiered Billing）============ */

    /**
     * 按上下文长度分档定价。
     * 数组格式：[[maxInput, price], ...] 或设置 'tiered_billing' 为 JSON：[[上限,单价],...]
     * 命中档位后按该档单价计费（取代基础 input_price output_price）。
     * @return float
     */
    public static function calculateTiered($priceRow, $promptTokens, $completionTokens, $cachedTokens = 0)
    {
        $tiers = setting('tiered_billing', '');
        if ($tiers === '') {
            return self::calculate($priceRow, $promptTokens, $completionTokens, $cachedTokens);
        }
        $tiers = json_decode(is_string($tiers) ? $tiers : json_encode($tiers), true);
        if (!is_array($tiers) || empty($tiers)) {
            return self::calculate($priceRow, $promptTokens, $completionTokens, $cachedTokens);
        }
        $inputPrice = (float)$priceRow['input_price'];
        $outputPrice = (float)$priceRow['output_price'];
        $promptTokens = max(0, (int)$promptTokens);
        $completionTokens = max(0, (int)$completionTokens);
        foreach ($tiers as $tier) {
            if (!is_array($tier) || count($tier) < 2) {
                continue;
            }
            $maxInput = (int)$tier[0];
            $tierPrice = (float)$tier[1];
            if ($promptTokens <= $maxInput) {
                $cost = (($promptTokens / 1000) * $tierPrice) + (($completionTokens / 1000) * $outputPrice);
                return max(0, $cost);
            }
        }
        return self::calculate($priceRow, $promptTokens, $completionTokens, $cachedTokens);
    }

    /* ============ 违规扣费（Violation Fee）============ */

    /**
     * 计算并应用违规扣费（如 CSAM 内容标记）。
     * @return float 扣费金额（0 表示不扣）
     */
    public static function applyViolationFee($userId, $amount, $groupRatio = 1)
    {
        $price = (float)setting('violation_fee', '0');
        if ($price <= 0) {
            return 0;
        }
        $quota = round($price * (float)$groupRatio, 6);
        if ($quota > 0) {
            User::deductQuota((int)$userId, $quota);
            write_log("violation fee applied: user={$userId} amount={$quota}", 'billing');
        }
        return $quota;
    }

    /* ============ 计费路径追踪 ============ */

    /**
     * 记录计费来源路径到日志（用于审计）
     */
    public static function recordBillingPath($requestId, $path)
    {
        write_log("billing_path[{$requestId}]={$path}", 'billing');
    }
}