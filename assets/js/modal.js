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
        msgEl.textContent = opts.message || '';
        cancelBtn.textContent = opts.cancelText || '取消';
        okBtn.textContent = opts.confirmText || '确定';
        okBtn.classList.toggle('danger', !!opts.danger);
        onConfirm = opts.onConfirm || null;
        document.body.style.overflow = 'hidden';
        void mask.offsetWidth; /* 强制回流，保证每次弹出都播放动画 */
        mask.classList.add('show');
    }

    window.LcyModal = { open: open, close: close };

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
