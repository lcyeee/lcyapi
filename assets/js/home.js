/**
 * lcyapi 公开落地页交互（纯原生 JS，无依赖）
 * 功能：顶栏滚动胶囊、移动端全屏菜单、滚动入场动画、数字滚动计数、终端演示轮播
 * 依赖：assets/css/home.css、assets/js/theme.js（可选，用于主题色取色）
 */
(function () {
    'use strict';

    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------- 顶栏滚动收缩 ---------- */
    var nav = document.querySelector('.pub-nav');
    function onScroll() {
        if (!nav) { return; }
        nav.classList.toggle('scrolled', window.scrollY > 20);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    /* ---------- 移动端全屏菜单 ---------- */
    var burger = document.querySelector('.pub-burger');
    var mobile = document.querySelector('.pub-mobile');
    if (burger && mobile) {
        burger.addEventListener('click', function () {
            var open = mobile.classList.toggle('open');
            burger.classList.toggle('open', open);
            document.body.style.overflow = open ? 'hidden' : '';
        });
        mobile.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
                mobile.classList.remove('open');
                burger.classList.remove('open');
                document.body.style.overflow = '';
            });
        });
    }

    /* ---------- 滚动入场动画 ---------- */
    var animEls = document.querySelectorAll('.landing-fade-up, .landing-scale-in');
    if (reduced || !('IntersectionObserver' in window)) {
        animEls.forEach(function (el) { el.classList.add('landing-in'); });
    } else {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('landing-in');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });
        animEls.forEach(function (el) { io.observe(el); });
    }

    /* ---------- 数字滚动计数 ---------- */
    function countUp(el) {
        var end = parseFloat(el.getAttribute('data-count')) || 0;
        var prefix = el.getAttribute('data-prefix') || '';
        var suffix = el.getAttribute('data-suffix') || '';
        var decimals = parseInt(el.getAttribute('data-decimals') || '0', 10);
        var duration = parseInt(el.getAttribute('data-duration') || '1600', 10);
        var reducedMotion = reduced;
        var format = function (v) {
            return decimals > 0 ? v.toFixed(decimals) : Math.round(v).toLocaleString('zh-CN');
        };
        if (reducedMotion) {
            el.textContent = prefix + format(end) + suffix;
            return;
        }
        var start = null;
        function step(now) {
            if (start === null) { start = now; }
            var progress = Math.min((now - start) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = prefix + format(eased * end) + suffix;
            if (progress < 1) { requestAnimationFrame(step); }
        }
        requestAnimationFrame(step);
    }
    var counters = document.querySelectorAll('[data-count]');
    if (reduced || !('IntersectionObserver' in window)) {
        counters.forEach(countUp);
    } else {
        var cio = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    countUp(entry.target);
                    cio.unobserve(entry.target);
                }
            });
        }, { threshold: 0.4 });
        counters.forEach(function (el) { cio.observe(el); });
    }

    /* ---------- 终端演示（Chat / Responses / Claude / Gemini 轮播） ---------- */
    var DEMOS = (function () {
        function req(lines) { return lines.join('\n'); }
        function res(lines) { return lines.join('\n'); }
        return [
            {
                label: 'Chat',
                accent: '#34D399',
                method: 'POST',
                url: '/v1/chat/completions',
                request: req([
                    '<span class="tok-cmd">curl</span> <span class="tok-flag">-X</span> <span class="tok-flag">POST</span> <span class="tok-str">&quot;/v1/chat/completions&quot;</span> <span class="tok-mut">\\</span>',
                    '  <span class="tok-flag">-H</span> <span class="tok-str">&quot;Authorization: Bearer sk-••••&quot;</span> <span class="tok-mut">\\</span>',
                    '  <span class="tok-flag">-d</span> <span class="tok-str">&quot;{</span>',
                    '    <span class="tok-key">&quot;model&quot;</span><span class="tok-mut">:</span> <span class="tok-str">&quot;your-model&quot;</span><span class="tok-mut">,</span>',
                    '    <span class="tok-key">&quot;messages&quot;</span><span class="tok-mut">:</span> <span class="tok-str">[</span>',
                    '      <span class="tok-str">{</span> <span class="tok-key">&quot;role&quot;</span><span class="tok-mut">:</span> <span class="tok-str">&quot;user&quot;</span><span class="tok-mut">,</span> <span class="tok-key">&quot;content&quot;</span><span class="tok-mut">:</span> <span class="tok-str">&quot;...&quot;</span> <span class="tok-str">}</span>',
                    '    <span class="tok-str">]</span>',
                    '  <span class="tok-str">&quot;}&quot;</span>',
                ]),
                response: req([
                    '<span class="tok-mut">{</span>',
                    '  <span class="tok-key">&quot;choices&quot;</span><span class="tok-mut">:</span> <span class="tok-str">[{</span> <span class="tok-key">&quot;message&quot;</span><span class="tok-mut">:</span> <span class="tok-str">{</span> <span class="tok-key">&quot;content&quot;</span><span class="tok-mut">:</span> <span class="tok-hi">&lt;text&gt;</span> <span class="tok-str">}</span> <span class="tok-str">}],</span>',
                    '  <span class="tok-key">&quot;usage&quot;</span><span class="tok-mut">:</span> <span class="tok-str">{</span> <span class="tok-key">&quot;total_tokens&quot;</span><span class="tok-mut">:</span> <span class="tok-hi">&lt;tokens&gt;</span> <span class="tok-str">}</span>',
                    '<span class="tok-mut">}</span>',
                ]),
                tokens: 27, latency: 142,
            },
            {
                label: 'Responses',
                accent: '#F59E0B',
                method: 'POST',
                url: '/v1/responses',
                request: req([
                    '<span class="tok-cmd">curl</span> <span class="tok-flag">-X</span> <span class="tok-flag">POST</span> <span class="tok-str">&quot;/v1/responses&quot;</span> <span class="tok-mut">\\</span>',
                    '  <span class="tok-flag">-H</span> <span class="tok-str">&quot;Authorization: Bearer sk-••••&quot;</span> <span class="tok-mut">\\</span>',
                    '  <span class="tok-flag">-d</span> <span class="tok-str">&quot;{</span>',
                    '    <span class="tok-key">&quot;model&quot;</span><span class="tok-mut">:</span> <span class="tok-str">&quot;your-model&quot;</span><span class="tok-mut">,</span>',
                    '    <span class="tok-key">&quot;input&quot;</span><span class="tok-mut">:</span> <span class="tok-str">&quot;...&quot;</span>',
                    '  <span class="tok-str">&quot;}&quot;</span>',
                ]),
                response: req([
                    '<span class="tok-mut">{</span>',
                    '  <span class="tok-key">&quot;output&quot;</span><span class="tok-mut">:</span> <span class="tok-str">[{</span> <span class="tok-key">&quot;type&quot;</span><span class="tok-mut">:</span> <span class="tok-str">&quot;output_text&quot;</span><span class="tok-mut">,</span> <span class="tok-key">&quot;text&quot;</span><span class="tok-mut">:</span> <span class="tok-hi">&lt;text&gt;</span> <span class="tok-str">}],</span>',
                    '  <span class="tok-key">&quot;usage&quot;</span><span class="tok-mut">:</span> <span class="tok-str">{</span> <span class="tok-key">&quot;total_tokens&quot;</span><span class="tok-mut">:</span> <span class="tok-hi">&lt;tokens&gt;</span> <span class="tok-str">}</span>',
                    '<span class="tok-mut">}</span>',
                ]),
                tokens: 31, latency: 168,
            },
            {
                label: 'Claude',
                accent: '#3B82F6',
                method: 'POST',
                url: '/v1/messages',
                request: req([
                    '<span class="tok-cmd">curl</span> <span class="tok-flag">-X</span> <span class="tok-flag">POST</span> <span class="tok-str">&quot;/v1/messages&quot;</span> <span class="tok-mut">\\</span>',
                    '  <span class="tok-flag">-H</span> <span class="tok-str">&quot;x-api-key: sk-••••&quot;</span> <span class="tok-mut">\\</span>',
                    '  <span class="tok-flag">-H</span> <span class="tok-str">&quot;anthropic-version: 2023-06-01&quot;</span> <span class="tok-mut">\\</span>',
                    '  <span class="tok-flag">-d</span> <span class="tok-str">&quot;{</span>',
                    '    <span class="tok-key">&quot;model&quot;</span><span class="tok-mut">:</span> <span class="tok-str">&quot;your-model&quot;</span><span class="tok-mut">,</span>',
                    '    <span class="tok-key">&quot;max_tokens&quot;</span><span class="tok-mut">:</span> <span class="tok-str">1024,</span>',
                    '  <span class="tok-str">&quot;}&quot;</span>',
                ]),
                response: req([
                    '<span class="tok-mut">{</span>',
                    '  <span class="tok-key">&quot;content&quot;</span><span class="tok-mut">:</span> <span class="tok-str">[{</span> <span class="tok-key">&quot;type&quot;</span><span class="tok-mut">:</span> <span class="tok-str">&quot;text&quot;</span><span class="tok-mut">,</span> <span class="tok-key">&quot;text&quot;</span><span class="tok-mut">:</span> <span class="tok-hi">&lt;text&gt;</span> <span class="tok-str">}],</span>',
                    '  <span class="tok-key">&quot;usage&quot;</span><span class="tok-mut">:</span> <span class="tok-str">{</span> <span class="tok-key">&quot;input_tokens&quot;</span><span class="tok-mut">:</span> <span class="tok-hi">&lt;in&gt;</span><span class="tok-mut">,</span> <span class="tok-key">&quot;output_tokens&quot;</span><span class="tok-mut">:</span> <span class="tok-hi">&lt;out&gt;</span> <span class="tok-str">}</span>',
                    '<span class="tok-mut">}</span>',
                ]),
                tokens: 29, latency: 156,
            },
            {
                label: 'Gemini',
                accent: '#8B5CF6',
                method: 'POST',
                url: '/v1beta/models/{model}:generateContent',
                request: req([
                    '<span class="tok-cmd">curl</span> <span class="tok-flag">-X</span> <span class="tok-flag">POST</span> <span class="tok-str">&quot;/v1beta/models/{model}:generateContent&quot;</span> <span class="tok-mut">\\</span>',
                    '  <span class="tok-flag">-H</span> <span class="tok-str">&quot;x-goog-api-key: sk-••••&quot;</span> <span class="tok-mut">\\</span>',
                    '  <span class="tok-flag">-d</span> <span class="tok-str">&quot;{</span>',
                    '    <span class="tok-key">&quot;contents&quot;</span><span class="tok-mut">:</span> <span class="tok-str">[</span>',
                    '      <span class="tok-str">{</span> <span class="tok-key">&quot;role&quot;</span><span class="tok-mut">:</span> <span class="tok-str">&quot;user&quot;</span><span class="tok-mut">,</span>',
                    '        <span class="tok-key">&quot;parts&quot;</span><span class="tok-mut">:</span> <span class="tok-str">[{</span> <span class="tok-key">&quot;text&quot;</span><span class="tok-mut">:</span> <span class="tok-str">&quot;...&quot;</span> <span class="tok-str">}]</span> <span class="tok-str">}</span>',
                    '    <span class="tok-str">]</span>',
                    '  <span class="tok-str">&quot;}&quot;</span>',
                ]),
                response: req([
                    '<span class="tok-mut">{</span>',
                    '  <span class="tok-key">&quot;candidates&quot;</span><span class="tok-mut">:</span> <span class="tok-str">[{</span> <span class="tok-key">&quot;content&quot;</span><span class="tok-mut">:</span> <span class="tok-str">{</span> <span class="tok-key">&quot;parts&quot;</span><span class="tok-mut">:</span> <span class="tok-str">[{</span> <span class="tok-key">&quot;text&quot;</span><span class="tok-mut">:</span> <span class="tok-hi">&lt;text&gt;</span> <span class="tok-str">}]</span> <span class="tok-str">}</span> <span class="tok-str">}],</span>',
                    '  <span class="tok-key">&quot;usageMetadata&quot;</span><span class="tok-mut">:</span> <span class="tok-str">{</span> <span class="tok-key">&quot;totalTokenCount&quot;</span><span class="tok-mut">:</span> <span class="tok-hi">&lt;tokens&gt;</span> <span class="tok-str">}</span>',
                    '<span class="tok-mut">}</span>',
                ]),
                tokens: 25, latency: 93,
            },
        ];
    })();

    var termRoot = document.getElementById('termDemo');
    var METRIC_MAP = { '<text>': 'text', '<tokens>': 'tokens', '<in>': 'in', '<out>': 'out' };
    function cleanForMetrics(html) {
        return html.replace(/<[^>]+>/g, '').replace(/&quot;/g, '"');
    }
    function renderTerm(idx) {
        if (!termRoot) { return; }
        var d = DEMOS[idx];
        var tabs = termRoot.querySelector('.term-tabs');
        var endpoint = termRoot.querySelector('.term-url');
        var method = termRoot.querySelector('.term-method');
        var reqBox = termRoot.querySelector('.term-request pre');
        var resBox = termRoot.querySelector('.term-response pre');
        var metricLat = termRoot.querySelector('[data-metric="latency"]');
        var metricTok = termRoot.querySelector('[data-metric="tokens"]');
        var metricCost = termRoot.querySelector('[data-metric="cost"]');

        var rootVar = termRoot.style;
        rootVar.setProperty('--term-accent', d.accent);

        Array.prototype.forEach.call(tabs.children, function (tab, i) {
            tab.classList.toggle('active', i === idx);
        });
        endpoint.textContent = d.url;
        method.textContent = d.method;
        reqBox.innerHTML = d.request;
        resBox.innerHTML = d.response;
        metricLat.textContent = d.latency;
        metricTok.textContent = d.tokens;
        metricCost.textContent = '$' + (d.tokens * 0.00003).toFixed(5);
    }

    if (termRoot) {
        var activeIdx = 0;
        var interval = null;
        var timeout = null;

        function startCycle() {
            if (reduced) { return; }
            stopCycle();
            interval = setInterval(function () {
                timeout = setTimeout(function () {
                    activeIdx = (activeIdx + 1) % DEMOS.length;
                    renderTerm(activeIdx);
                }, 160);
            }, 4500);
        }
        function stopCycle() {
            if (interval) { clearInterval(interval); interval = null; }
            if (timeout) { clearTimeout(timeout); timeout = null; }
        }

        termRoot.querySelector('.term-tabs').addEventListener('click', function (e) {
            var tab = e.target.closest('.term-tab');
            if (!tab) { return; }
            var idx = Array.prototype.indexOf.call(tab.parentNode.children, tab);
            if (idx !== activeIdx) {
                activeIdx = idx;
                renderTerm(idx);
                startCycle();
            }
        });

        renderTerm(0);
        startCycle();
    }

    /* ---------- Hero 光斑跟随主题色（theme.js 可用时） ---------- */
    if (window.LcyTheme && window.LcyTheme.accentRgb) {
        var heroBg = document.querySelector('.hero-bg');
        if (heroBg) {
            try {
                var rgb = window.LcyTheme.accentRgb().split(',').map(function (n) { return n.trim(); });
                heroBg.style.background = [
                    'radial-gradient(ellipse 60% 50% at 20% 20%, rgba(' + rgb + ',0.22) 0%, transparent 70%)',
                    'radial-gradient(ellipse 50% 40% at 80% 15%, rgba(' + rgb + ',0.16) 0%, transparent 70%)',
                    'radial-gradient(ellipse 40% 35% at 40% 80%, rgba(' + rgb + ',0.12) 0%, transparent 70%)',
                ].join(', ');
            } catch (e) { /* 保持 CSS 兜底 */ }
        }
    }
})();