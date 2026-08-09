<?php
/**
 * 聊天预设（Chat Presets）：配置外部聊天客户端（web iframe / external）
 * 存储于 settings 表 'chat_presets'（JSON）
 */
class ChatPreset
{
    public static function all()
    {
        $raw = setting('chat_presets', '[]');
        $list = json_decode(is_string($raw) ? $raw : '[]', true);
        return is_array($list) ? $list : [];
    }

    public static function get($id)
    {
        foreach (self::all() as $p) {
            if ((string)($p['id'] ?? '') === (string)$id) {
                return $p;
            }
        }
        return null;
    }

    public static function save($list)
    {
        setting_set('chat_presets', json_encode(array_values($list), JSON_UNESCAPED_UNICODE));
    }

    /**
     * 解析聊天 URL（替换占位符）
     */
    public static function resolveUrl($preset, $apiKey, $serverUrl)
    {
        $url = isset($preset['url']) ? $preset['url'] : '';
        $url = str_replace('{api_key}', urlencode($apiKey), $url);
        $url = str_replace('{apiKey}', urlencode($apiKey), $url);
        $url = str_replace('{server_url}', urlencode($serverUrl), $url);
        $url = str_replace('{serverUrl}', urlencode($serverUrl), $url);
        return $url;
    }
}