<?php
/**
 * 渠道类型静态配置表（对齐 lcyapi）：决定认证头、默认上游地址、上游请求格式
 * format: openai = OpenAI 兼容透传；claude = Anthropic Messages；gemini = Google Gemini
 */
class ChannelType
{
    const MAP = [
        'openai'      => ['name' => 'OpenAI',            'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://api.openai.com'],
        'custom'      => ['name' => '自定义（OpenAI 兼容）', 'auth' => 'bearer',      'format' => 'openai', 'base_url' => ''],
        'azure'       => ['name' => 'Azure OpenAI',      'auth' => 'api-key',       'format' => 'openai', 'base_url' => ''],
        'anthropic'   => ['name' => 'Anthropic Claude',  'auth' => 'x-api-key',     'format' => 'claude', 'base_url' => 'https://api.anthropic.com', 'headers' => ['anthropic-version: 2023-06-01']],
        'gemini'      => ['name' => 'Google Gemini',     'auth' => 'x-goog-api-key','format' => 'gemini', 'base_url' => 'https://generativelanguage.googleapis.com'],
        'deepseek'    => ['name' => 'DeepSeek',          'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://api.deepseek.com'],
        'zhipu'       => ['name' => '智谱 AI',            'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://open.bigmodel.cn/api/paas/v4'],
        'ali'         => ['name' => '阿里云百炼',         'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://dashscope.aliyuncs.com/compatible-mode/v1'],
        'baidu'       => ['name' => '百度千帆',           'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://qianfan.baidubce.com/v2'],
        'xunfei'      => ['name' => '讯飞星火',           'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://spark-api-open.xf-yun.com/v1'],
        'tencent'     => ['name' => '腾讯混元',           'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://api.hunyuan.cloud.tencent.com/v1'],
        'volcengine'  => ['name' => '火山方舟（豆包）',    'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://ark.cn-beijing.volces.com/api/v3'],
        'moonshot'    => ['name' => 'Moonshot',          'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://api.moonshot.cn/v1'],
        'ollama'      => ['name' => 'Ollama',            'auth' => 'none',          'format' => 'openai', 'base_url' => 'http://localhost:11434'],
        'openrouter'  => ['name' => 'OpenRouter',        'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://openrouter.ai/api/v1'],
        'siliconflow' => ['name' => '硅基流动',           'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://api.siliconflow.cn/v1'],
        'mistral'     => ['name' => 'Mistral',           'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://api.mistral.ai/v1'],
        'xai'         => ['name' => 'xAI Grok',          'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://api.x.ai/v1'],
        'cohere'      => ['name' => 'Cohere',            'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://api.cohere.ai'],
        'jina'        => ['name' => 'Jina',              'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://api.jina.ai/v1'],
        'sub2api'     => ['name' => 'Sub2API',           'auth' => 'bearer',        'format' => 'openai', 'base_url' => ''],
        'lcyapi'      => ['name' => 'lcyapi',            'auth' => 'bearer',        'format' => 'openai', 'base_url' => ''],
        /* 新增渠道类型 */
        'perplexity'  => ['name' => 'Perplexity',        'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://api.perplexity.ai'],
        'vertex'      => ['name' => 'Vertex AI',         'auth' => 'bearer',        'format' => 'gemini', 'base_url' => ''],
        'aws'         => ['name' => 'AWS Bedrock',       'auth' => 'bearer',        'format' => 'openai', 'base_url' => ''],
        'lingyi'      => ['name' => '零一万物 Yi',        'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://api.lingyiwanwu.com/v1'],
        'minimax'     => ['name' => 'MiniMax',           'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://api.minimax.chat/v1'],
        'cloudflare'  => ['name' => 'Cloudflare AI',     'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://api.cloudflare.com/client/v4'],
        'xinference'  => ['name' => 'Xinference',        'auth' => 'none',          'format' => 'openai', 'base_url' => ''],
        'jimeng'      => ['name' => '即梦（字节跳动）',    'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://visual.volcengineapi.com'],
        '360'         => ['name' => '360 智脑',          'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://api.360.cn/v1'],
        'replicate'   => ['name' => 'Replicate',         'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://api.replicate.com/v1'],
        'coze'        => ['name' => 'Coze（扣子）',       'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://api.coze.cn'],
        'dify'        => ['name' => 'Dify',              'auth' => 'bearer',        'format' => 'openai', 'base_url' => 'https://api.dify.ai/v1'],
        'suno'        => ['name' => 'Suno',              'auth' => 'bearer',        'format' => 'openai', 'base_url' => ''],
    ];

    public static function get($type)
    {
        $type = is_string($type) ? $type : 'openai';
        return isset(self::MAP[$type]) ? self::MAP[$type] : self::MAP['openai'];
    }

    public static function exists($type)
    {
        return is_string($type) && isset(self::MAP[$type]);
    }

    public static function all()
    {
        return self::MAP;
    }

    public static function name($type)
    {
        $cfg = self::get($type);
        return $cfg['name'];
    }

    /** 上游请求格式：openai / claude / gemini */
    public static function format($type)
    {
        return self::get($type)['format'];
    }

    public static function options()
    {
        $out = [];
        foreach (self::MAP as $key => $cfg) {
            $out[$key] = $cfg['name'];
        }
        return $out;
    }
}
