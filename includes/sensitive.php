<?php
/**
 * 敏感词过滤：设置页 sensitive_words 每行一个
 * 请求内容命中 → relay 返回 400 拦截；响应文本命中 → 打码
 */
class Sensitive
{
    private static $words = null;

    public static function words()
    {
        if (self::$words === null) {
            $raw = (string)setting('sensitive_words', '');
            self::$words = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw))));
        }
        return self::$words;
    }

    public static function enabled()
    {
        return !empty(self::words());
    }

    /**
     * 检查文本是否命中敏感词，返回命中词列表（空数组=未命中）
     */
    public static function check($text)
    {
        if (!is_string($text) || $text === '' || !self::enabled()) {
            return [];
        }
        $hit = [];
        foreach (self::words() as $word) {
            if ($word !== '' && mb_strpos($text, $word) !== false) {
                $hit[] = $word;
            }
        }
        return $hit;
    }

    /**
     * 把文本中的敏感词替换为星号
     */
    public static function mask($text)
    {
        if (!is_string($text) || $text === '' || !self::enabled()) {
            return $text;
        }
        foreach (self::words() as $word) {
            if ($word !== '') {
                $text = str_replace($word, str_repeat('*', mb_strlen($word)), $text);
            }
        }
        return $text;
    }
}
