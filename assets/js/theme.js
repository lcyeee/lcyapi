/**
 * lcyapi 主题引擎（纯原生 JS，无依赖）
 * 功能：明暗模式（自动/手动）、5 套预设配色、自定义主色、localStorage 持久化
 * 依赖：common.css 中的 CSS 变量体系
 */
(function () {
    'use strict';

    var MODE_KEY = 'lcy_mode';     // auto | light | dark
    var THEME_KEY = 'lcy_theme';   // 预设ID 或 custom
    var COLOR_KEY = 'lcy_colors';  // 自定义色 JSON

    // 5 套预设配色模板（浅蓝 / 极简白 / 薄荷绿 / 淡紫 / 深空灰）
    var PRESETS = {
        ice:   { name: '浅冰蓝',  accent: '#409EFF', accent2: '#66B1FF', accent3: '#94CFFF', bg: '#E8F4FF', bgDark: '#0E141D' },
        white: { name: '极简白',  accent: '#5B8DEF', accent2: '#7FA8F5', accent3: '#B3CBF9', bg: '#F5F7FA', bgDark: '#11151C' },
        mint:  { name: '薄荷绿',  accent: '#34C78B', accent2: '#5BD6A5', accent3: '#9AE7C7', bg: '#EDFBF4', bgDark: '#0C1613' },
        lilac: { name: '淡紫',    accent: '#8B7CF6', accent2: '#A79BF8', accent3: '#CBC4FB', bg: '#F3F1FE', bgDark: '#121019' },
        space: { name: '深空灰',  accent: '#64748B', accent2: '#7D8CA1', accent3: '#A8B4C4', bg: '#EEF1F4', bgDark: '#0B0D10' }
    };
    var DEFAULT_PRESET = 'ice';

    var root = document.documentElement;
    var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function store(key, val) {
        try { localStorage.setItem(key, val); } catch (e) { /* 隐私模式下静默失败 */ }
    }
    function read(key) {
        try { return localStorage.getItem(key); } catch (e) { return null; }
    }

    function getMode() {
        var m = read(MODE_KEY);
        return (m === 'light' || m === 'dark') ? m : 'auto';
    }
    function getThemeId() {
        var t = read(THEME_KEY);
        return (t && (PRESETS[t] || t === 'custom')) ? t : DEFAULT_PRESET;
    }
    function getCustomColors() {
        try { return JSON.parse(read(COLOR_KEY) || '{}'); } catch (e) { return {}; }
    }

    // 根据主色推导辅助色（向亮色偏移）
    function deriveColors(accent) {
        function mix(hex1, hex2, w) {
            var a = parseInt(hex1.slice(1), 16), b = parseInt(hex2.slice(1), 16);
            var r = Math.round(((a >> 16) & 255) * (1 - w) + ((b >> 16) & 255) * w);
            var g = Math.round(((a >> 8) & 255) * (1 - w) + ((b >> 8) & 255) * w);
            var bl = Math.round((a & 255) * (1 - w) + (b & 255) * w);
            return '#' + ((1 << 24) + (r << 16) + (g << 8) + bl).toString(16).slice(1);
        }
        return { accent2: mix(accent, '#FFFFFF', 0.28), accent3: mix(accent, '#FFFFFF', 0.55) };
    }

    function resolvePalette() {
        var id = getThemeId();
        if (id === 'custom') {
            var c = getCustomColors();
            var accent = /^#[0-9a-fA-F]{6}$/.test(c.accent || '') ? c.accent : PRESETS[DEFAULT_PRESET].accent;
            var d = deriveColors(accent);
            return {
                accent: accent, accent2: c.accent2 || d.accent2, accent3: c.accent3 || d.accent3,
                bg: c.bg || PRESETS[DEFAULT_PRESET].bg, bgDark: c.bgDark || PRESETS[DEFAULT_PRESET].bgDark
            };
        }
        var p = PRESETS[id] || PRESETS[DEFAULT_PRESET];
        return { accent: p.accent, accent2: p.accent2, accent3: p.accent3, bg: p.bg, bgDark: p.bgDark };
    }

    function isDark() {
        var mode = getMode();
        if (mode === 'auto') { return media ? media.matches : false; }
        return mode === 'dark';
    }

    /** 应用主题（模式 + 配色），可安全在任意时机调用 */
    function apply() {
        var dark = isDark();
        root.setAttribute('data-mode', dark ? 'dark' : 'light');
        var pal = resolvePalette();
        root.style.setProperty('--accent', pal.accent);
        root.style.setProperty('--accent-2', pal.accent2);
        root.style.setProperty('--accent-3', pal.accent3);
        root.style.setProperty('--bg', dark ? pal.bgDark : pal.bg);
        root.style.setProperty('--bg-grad', dark
            ? 'linear-gradient(160deg, ' + pal.bgDark + ' 0%, ' + shade(pal.bgDark, 1) + ' 55%, ' + pal.bgDark + ' 100%)'
            : 'linear-gradient(160deg, ' + pal.bg + ' 0%, #FFFFFF 55%, ' + pal.bg + ' 100%)');
        syncUI();
    }

    // 深色背景微调（略提亮）
    function shade(hex, level) {
        var n = parseInt(hex.slice(1), 16);
        var r = Math.min(255, ((n >> 16) & 255) + 8 * level);
        var g = Math.min(255, ((n >> 8) & 255) + 10 * level);
        var b = Math.min(255, (n & 255) + 14 * level);
        return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
    }

    /** 同步页面上的主题控件状态（按钮高亮、预设选中） */
    function syncUI() {
        var mode = getMode(), themeId = getThemeId();
        document.querySelectorAll('[data-theme-mode]').forEach(function (el) {
            el.classList.toggle('active', el.getAttribute('data-theme-mode') === mode);
        });
        document.querySelectorAll('[data-theme-preset]').forEach(function (el) {
            el.classList.toggle('active', el.getAttribute('data-theme-preset') === themeId);
        });
        // 模式切换按钮图标：当前为暗色则显示太阳（点击切换到亮）
        document.querySelectorAll('[data-theme-toggle] .i').forEach(function (icon) {
            icon.innerHTML = isDark()
                ? '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>'
                : '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>';
        });
        var picker = document.getElementById('themeAccentPicker');
        if (picker && themeId === 'custom') { picker.value = resolvePalette().accent; }
    }

    /** 绑定一个主题面板容器内的全部控件（可多次调用） */
    function bindPanel(scope) {
        scope = scope || document;
        scope.querySelectorAll('[data-theme-mode]').forEach(function (el) {
            if (el.__themeBound) { return; }
            el.__themeBound = true;
            el.addEventListener('click', function () {
                store(MODE_KEY, el.getAttribute('data-theme-mode'));
                apply();
            });
        });
        scope.querySelectorAll('[data-theme-preset]').forEach(function (el) {
            if (el.__themeBound) { return; }
            el.__themeBound = true;
            el.addEventListener('click', function () {
                store(THEME_KEY, el.getAttribute('data-theme-preset'));
                apply();
            });
        });
        scope.querySelectorAll('[data-theme-toggle]').forEach(function (el) {
            if (el.__themeBound) { return; }
            el.__themeBound = true;
            el.addEventListener('click', function () {
                // 一键在亮/暗之间切换（关闭自动）
                store(MODE_KEY, isDark() ? 'light' : 'dark');
                apply();
            });
        });
        var picker = scope.querySelector('#themeAccentPicker');
        if (picker && !picker.__themeBound) {
            picker.__themeBound = true;
            picker.addEventListener('input', function () {
                var colors = getCustomColors();
                colors.accent = picker.value;
                store(COLOR_KEY, JSON.stringify(colors));
                store(THEME_KEY, 'custom');
                apply();
            });
        }
        var resetBtn = scope.querySelector('[data-theme-reset]');
        if (resetBtn && !resetBtn.__themeBound) {
            resetBtn.__themeBound = true;
            resetBtn.addEventListener('click', function () {
                store(MODE_KEY, 'auto');
                store(THEME_KEY, DEFAULT_PRESET);
                store(COLOR_KEY, '{}');
                apply();
            });
        }
    }

    // 跟随系统深浅模式变化（仅自动模式生效）
    if (media && media.addEventListener) {
        media.addEventListener('change', function () { if (getMode() === 'auto') { apply(); } });
    }

    // 全局暴露 API
    window.LcyTheme = {
        apply: apply,
        bindPanel: bindPanel,
        isDark: isDark,
        presets: PRESETS,
        accent: function () { return resolvePalette().accent; },
        accentRgb: function () {
            var a = resolvePalette().accent, n = parseInt(a.slice(1), 16);
            return ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255);
        }
    };

    apply();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { bindPanel(document); });
    } else {
        bindPanel(document);
    }
})();
