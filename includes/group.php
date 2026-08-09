<?php
/**
 * 分组服务（对照 new-api 的 group_ratio/user_usable_group/auto_group 配置驱动）
 * 所有配置 JSON 存 settings 表：
 *  - group_ratio           {组名:倍率}           计费倍率，default 恒 1
 *  - user_usable_groups    {组名:中文描述}       用户可选分组（令牌组下拉）
 *  - group_group_ratio     {用户组:{目标组:倍率}} 组间倍率，优先于 group_ratio
 *  - topup_group_ratio     {组名:倍率}           充值/兑换码加成倍率
 *  - auto_groups           [组名]                令牌组为 auto 时的候选组
 *  - max_token_auto_groups 每令牌 auto_groups 上限（默认 5）
 *  - default_use_auto_group 新建令牌默认组是否 auto（默认 0）
 */
class Group
{
    const DEFAULT_NAME = 'default';

    /* ---------- 读配置 ---------- */

    public static function ratioMap()
    {
        $raw = setting('group_ratio', '');
        if ($raw === '') {
            return [self::DEFAULT_NAME => 1];
        }
        $map = json_decode($raw, true);
        if (!is_array($map) || empty($map)) {
            return [self::DEFAULT_NAME => 1];
        }
        $out = [];
        foreach ($map as $name => $ratio) {
            $name = trim((string)$name);
            if ($name !== '' && is_numeric($ratio) && (float)$ratio >= 0) {
                $out[$name] = (float)$ratio;
            }
        }
        if (!isset($out[self::DEFAULT_NAME])) {
            $out[self::DEFAULT_NAME] = 1;
        }
        return $out;
    }

    /** 已定义分组列表（来自 group_ratio，保证 default 恒存在） */
    public static function allGroups()
    {
        return array_keys(self::ratioMap());
    }

    /** 用户可选分组：{组名:描述} */
    public static function usableGroups()
    {
        $raw = setting('user_usable_groups', '');
        $map = $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($map) || empty($map)) {
            return [self::DEFAULT_NAME => '默认分组'];
        }
        $out = [];
        foreach ($map as $name => $desc) {
            $name = trim((string)$name);
            if ($name !== '') {
                $out[$name] = $desc !== '' ? (string)$desc : $name;
            }
        }
        if (!isset($out[self::DEFAULT_NAME])) {
            $out = [self::DEFAULT_NAME => '默认分组'] + $out;
        }
        return $out;
    }

    /** 用户组可切换到的全部组（含其本身） */
    public static function selectableGroups($userGroup = '')
    {
        $userGroup = $userGroup !== '' ? $userGroup : self::DEFAULT_NAME;
        $groups = [self::DEFAULT_NAME, $userGroup];
        foreach (self::usableGroups() as $name => $desc) {
            $groups[] = $name;
        }
        $groups = array_values(array_unique($groups));
        $resolved = [];
        foreach ($groups as $g) {
            if (self::isUserSelectableGroup($g)) {
                $resolved[] = $g;
            }
        }
        return $resolved;
    }

    /** 是否存在于倍率配置（即"存在"的分组） */
    public static function isGroupDefined($name)
    {
        $name = trim((string)$name);
        return $name === '' || isset(self::ratioMap()[$name]);
    }

    /** 用户令牌是否可选用该分组（可用分组∩已定义分组） */
    public static function isUserSelectableGroup($name)
    {
        $name = trim((string)$name);
        if ($name === '' || $name === 'auto') {
            return true;
        }
        return isset(self::usableGroups()[$name]) && isset(self::ratioMap()[$name]);
    }

    /**
     * 用户组计费倍率：先查组间倍率 group_group_ratio[userGroup][targetGroup]，
     * 否则 group_ratio[userGroup]，默认 1
     */
    public static function getUserGroupRatio($userGroup = '', $targetGroup = '')
    {
        $userGroup = $userGroup !== '' ? $userGroup : self::DEFAULT_NAME;
        if ($targetGroup !== '') {
            $map = self::groupGroupRatio();
            if (isset($map[$userGroup][$targetGroup])) {
                return max(0, (float)$map[$userGroup][$targetGroup]);
            }
        }
        $ratios = self::ratioMap();
        return isset($ratios[$userGroup]) ? max(0, (float)$ratios[$userGroup]) : 1.0;
    }

    public static function groupGroupRatio()
    {
        $raw = setting('group_group_ratio', '');
        if ($raw === '') return [];
        $map = json_decode($raw, true);
        if (!is_array($map) || empty($map)) return [];
        $out = [];
        foreach ($map as $userGroup => $list) {
            if (!is_array($list)) continue;
            foreach ($list as $targetGroup => $ratio) {
                if (is_numeric($ratio) && (float)$ratio >= 0) {
                    $out[trim((string)$userGroup)][trim((string)$targetGroup)] = (float)$ratio;
                }
            }
        }
        return $out;
    }

    /** 充值倍率（兑换码/后台加额按组加成），默认 1 */
    public static function topupRatio($userGroup = '')
    {
        $userGroup = $userGroup !== '' ? $userGroup : self::DEFAULT_NAME;
        $raw = setting('topup_group_ratio', '');
        $map = $raw !== '' ? json_decode($raw, true) : [];
        if (is_array($map) && isset($map[$userGroup]) && is_numeric($map[$userGroup])) {
            return max(0, (float)$map[$userGroup]);
        }
        return 1.0;
    }

    /** 全局自动分组候选列表 */
    public static function autoGroups()
    {
        $raw = setting('auto_groups', '');
        if ($raw === '') return [self::DEFAULT_NAME];
        $list = json_decode($raw, true);
        if (!is_array($list) || empty($list)) return [self::DEFAULT_NAME];
        $out = [];
        foreach ($list as $g) {
            $g = trim((string)$g);
            if ($g !== '' && !in_array($g, $out, true)) $out[] = $g;
        }
        if (empty($out)) $out[] = self::DEFAULT_NAME;
        return $out;
    }

    public static function maxTokenAutoGroups()
    {
        $n = (int)setting('max_token_auto_groups', '5');
        return $n > 0 ? $n : 5;
    }

    /** 新建令牌默认组是否为 auto */
    public static function defaultUseAutoGroup()
    {
        return setting('default_use_auto_group', '0') === '1';
    }

    /**
     * 令牌组解析：group=auto 时取令牌 auto_groups（快照）；为空时取全局自动分组。
     * 返回最终分组列表（排除不存在分组的配置组）
     */
    public static function resolveTokenGroups($token, $userGroup = '')
    {
        $group = isset($token['group']) ? trim((string)$token['group']) : '';
        $autoGroups = [];
        if (isset($token['auto_groups']) && trim((string)$token['auto_groups']) !== '') {
            foreach (array_filter(array_map('trim', explode(',', $token['auto_groups']))) as $g) {
                $autoGroups[] = $g;
            }
        }
        if ($group === 'auto') {
            $candidates = $autoGroups;
            if (empty($candidates)) {
                $candidates = self::autoGroups();
            }
            $out = [];
            foreach ($candidates as $g) {
                if ($g !== 'auto' && !in_array($g, $out, true)) {
                    $out[] = $g;
                }
            }
            return empty($out) ? [self::DEFAULT_NAME] : $out;
        }
        if ($group === '') {
            $group = $userGroup !== '' ? $userGroup : self::DEFAULT_NAME;
        }
        return [$group];
    }

    /** 校验并存储各 JSON 配置，非法返回错误信息（null=成功） */
    public static function saveAll($input)
    {
        $check = function ($label, $raw, $itemCheck) {
            $raw = trim((string)$raw);
            if ($raw === '') return null;
            $arr = json_decode($raw, true);
            if (!is_array($arr) || empty($arr)) {
                return $label . ' 必须是合法的 JSON 对象';
            }
            foreach ($arr as $k => $v) {
                $err = $itemCheck($k, $v);
                if ($err !== null) return $err;
            }
            return null;
        };

        $err = $check('分组倍率', $input['group_ratio'], function ($k, $v) {
            if (trim((string)$k) === '' || !is_numeric($v) || (float)$v < 0) return '分组倍率包含非法项：「' . $k . '」倍率必须是非负数字';
            return null;
        });
        if ($err !== null) return $err;

        $err = $check('可用分组', $input['user_usable_groups'], function ($k, $v) {
            if (trim((string)$k) === '') return '可用分组包含空组名';
            return null;
        });
        if ($err !== null) return $err;

        $err = $check('组间倍率', $input['group_group_ratio'], function ($k, $v) {
            if (trim((string)$k) === '') return '组间倍率包含空用户组名';
            if (!is_array($v)) return '组间倍率「' . $k . '」必须是对象';
            foreach ($v as $k2 => $v2) {
                if (trim((string)$k2) === '' || !is_numeric($v2) || (float)$v2 < 0) return '组间倍率「' . $k . '→' . $k2 . '」必须是非负数字';
            }
            return null;
        });
        if ($err !== null) return $err;

        $err = $check('充值倍率', $input['topup_group_ratio'], function ($k, $v) {
            if (trim((string)$k) === '' || !is_numeric($v) || (float)$v < 0) return '充值倍率包含非法项：「' . $k . '」倍率必须是非负数字';
            return null;
        });
        if ($err !== null) return $err;

        $err = $check('自动分组', $input['auto_groups'], function ($k, $v) {
            if (trim((string)$v) === '' ) return null;
            if (!is_string($v)) return '自动分组必须是字符串数组';
            return null;
        });
        if ($err !== null) return $err;

        $max = (int)($input['max_token_auto_groups'] ?? 5);
        if ($max < 1 || $max > 100) return '令牌自动分组上限需在 1-100 之间';

        setting_set('group_ratio', trim((string)$input['group_ratio']));
        setting_set('user_usable_groups', trim((string)$input['user_usable_groups']));
        setting_set('group_group_ratio', trim((string)$input['group_group_ratio']));
        setting_set('topup_group_ratio', trim((string)$input['topup_group_ratio']));
        setting_set('auto_groups', trim((string)$input['auto_groups']));
        setting_set('max_token_auto_groups', (string)$max);
        setting_set('default_use_auto_group', empty($input['default_use_auto_group']) ? '0' : '1');
        return null;
    }

    /** 默认配置 JSON（用于首次保存/展示预填） */
    public static function defaults()
    {
        return [
            'group_ratio' => '{"default":1}',
            'user_usable_groups' => '{"default":"默认分组"}',
            'group_group_ratio' => '',
            'topup_group_ratio' => '',
            'auto_groups' => '["default"]',
            'max_token_auto_groups' => '5',
            'default_use_auto_group' => '0',
        ];
    }
}