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
}