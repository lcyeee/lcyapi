/* =========================================================
 * lcyapi iOS 风格磨砂确认弹窗（替代原生 confirm）
 * 用法一：window.LcyModal.open({ title, message, confirmText, danger, onConfirm })
 * 用法二：表单加 data-confirm-msg 属性，提交时自动拦截弹窗确认
 * ========================================================= */
(function () {
    'use strict';

    var mask = null, titleEl, msgEl, cancelBtn, okBtn, onConfirm = null;

    function build() {
        if (mask) { return; }
        mask = document.createElement('div');
        mask.className = 'lcy-modal-mask';
        mask.innerHTML =
            '<div class="lcy-modal" role="dialog" aria-modal="true">' +
            '<div class="lcy-modal-title"></div>' +
            '<div class="lcy-modal-msg"></div>' +
            '<div class="lcy-modal-actions">' +
            '<button type="button" class="lcy-modal-btn cancel"></button>' +
            '<button type="button" class="lcy-modal-btn ok"></button>' +
            '</div></div>';
        document.body.appendChild(mask);
        titleEl = mask.querySelector('.lcy-modal-title');
        msgEl = mask.querySelector('.lcy-modal-msg');
        cancelBtn = mask.querySelector('.lcy-modal-btn.cancel');
        okBtn = mask.querySelector('.lcy-modal-btn.ok');
        mask.addEventListener('click', function (e) {
            if (e.target === mask) { close(); }
        });
        cancelBtn.addEventListener('click', close);
        okBtn.addEventListener('click', function () {
            var fn = onConfirm;
            close();
            if (fn) { fn(); }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen()) { close(); }
        });
    }

    function isOpen() {
        return mask && mask.classList.contains('show');
    }

    function close() {
        if (!mask) { return; }
        mask.classList.remove('show');
        onConfirm = null;
        document.body.style.overflow = '';
    }

    function open(opts) {
        opts = opts || {};
        build();
        titleEl.textContent = opts.title || '';
        titleEl.style.display = opts.title ? '' : 'none';
        if (opts.html) {
            msgEl.innerHTML = opts.message || '';
        } else {
            msgEl.textContent = opts.message || '';
        }
        msgEl.classList.toggle('long', !!opts.long);
        cancelBtn.textContent = opts.cancelText || '取消';
        cancelBtn.style.display = opts.alert ? 'none' : '';
        okBtn.textContent = opts.confirmText || '确定';
        okBtn.classList.toggle('danger', !!opts.danger);
        onConfirm = opts.onConfirm || null;
        document.body.style.overflow = 'hidden';
        void mask.offsetWidth; /* 强制回流，保证每次弹出都播放动画 */
        mask.classList.add('show');
    }

    /* 更新当前已打开弹窗的内容/按钮（如异步加载中的测试结果） */
    function refresh(opts) {
        if (!isOpen()) { return; }
        opts = opts || {};
        if (opts.title !== undefined) {
            titleEl.textContent = opts.title;
            titleEl.style.display = opts.title ? '' : 'none';
        }
        if (opts.message !== undefined) {
            if (opts.html) {
                msgEl.innerHTML = opts.message;
            } else {
                msgEl.textContent = opts.message;
            }
        }
        if (opts.cancelText !== undefined) {
            cancelBtn.textContent = opts.cancelText;
            cancelBtn.style.display = opts.alert ? 'none' : '';
        }
        if (opts.confirmText !== undefined) {
            okBtn.textContent = opts.confirmText;
        }
        if (opts.danger !== undefined) {
            okBtn.classList.toggle('danger', !!opts.danger);
        }
        if (opts.onConfirm !== undefined) {
            onConfirm = opts.onConfirm || null;
        }
    }

    window.LcyModal = {
        open: open,
        close: close,
        refresh: refresh,
        /* 单按钮信息弹窗 */
        alert: function (opts) {
            if (typeof opts === 'string') { opts = { message: opts }; }
            opts.alert = true;
            open(opts);
        }
    };

    /* 错误日志等长文本详情：点击 [data-modal-detail] 弹出磨砂详情弹窗 */
    document.addEventListener('click', function (e) {
        var el = e.target.closest ? e.target.closest('[data-modal-detail]') : null;
        if (!el) { return; }
        e.preventDefault();
        open({
            title: el.getAttribute('data-modal-detail-title') || '详情',
            message: el.getAttribute('data-modal-detail'),
            long: true,
            alert: true,
            confirmText: '关闭'
        });
    });

    /* 一键复制：[data-copy] / [data-copy-target]，成功后按钮短暂显示已复制状态 */
    document.addEventListener('click', function (e) {
        var el = e.target.closest ? e.target.closest('[data-copy],[data-copy-target]') : null;
        if (!el) { return; }
        e.preventDefault();
        var text = el.getAttribute('data-copy');
        if (text === null) {
            var t = document.querySelector(el.getAttribute('data-copy-target'));
            text = t ? (t.value || t.textContent || '') : '';
        }
        var done = function () {
            el.classList.add('copied');
            setTimeout(function () { el.classList.remove('copied'); }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done, function () { fallback(); });
        } else {
            fallback();
        }
        function fallback() {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); done(); } catch (err) { /* 忽略 */ }
            document.body.removeChild(ta);
        }
    });

    /* 拦截带 data-confirm-msg 的表单提交：弹窗确认后再真正提交（form.submit 不再触发 submit 事件，避免死循环） */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.hasAttribute || !form.hasAttribute('data-confirm-msg')) { return; }
        e.preventDefault();
        var submitter = e.submitter || null;
        open({
            title: form.getAttribute('data-confirm-title') || '操作确认',
            message: form.getAttribute('data-confirm-msg'),
            confirmText: form.getAttribute('data-confirm-ok') || '确定',
            danger: form.getAttribute('data-confirm-danger') !== '0',
            onConfirm: function () {
                /* form.submit() 不会携带提交按钮的 name/value，需手动补隐藏字段（如 action=delete） */
                if (submitter && submitter.name) {
                    var h = document.createElement('input');
                    h.type = 'hidden';
                    h.name = submitter.name;
                    h.value = submitter.value;
                    form.appendChild(h);
                }
                form.submit();
            }
        });
    }, true);
})();
