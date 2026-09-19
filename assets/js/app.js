/**
 * 坤发卡 前台公共JS
 * 二维码渲染 / 支付到账轮询 / 复制卡密
 */
(function () {
    // ---------- 悬浮客服 ----------
    var csBtn = document.getElementById('csBtn');
    var csPanel = document.getElementById('csPanel');
    if (csBtn && csPanel) {
        csBtn.addEventListener('click', function (ev) {
            ev.stopPropagation();
            csPanel.classList.toggle('open');
        });
        var csClose = document.getElementById('csClose');
        if (csClose) csClose.addEventListener('click', function () { csPanel.classList.remove('open'); });
        document.addEventListener('click', function (ev) {
            if (!ev.target.closest('.cs-wrap')) csPanel.classList.remove('open');
        });
    }

    // ---------- 移动端菜单 ----------
    var burger = document.getElementById('navBurger');
    var nav = document.getElementById('siteNav');
    if (burger && nav) {
        burger.addEventListener('click', function () {
            var open = nav.classList.toggle('open');
            burger.classList.toggle('open', open);
        });
        nav.addEventListener('click', function (ev) {
            if (ev.target.closest('a')) {
                nav.classList.remove('open');
                burger.classList.remove('open');
            }
        });
    }

    // ---------- 二维码渲染 ----------
    var qrEl = document.getElementById('qrcode');
    if (qrEl && window.QRCode) {
        var text = qrEl.getAttribute('data-text');
        if (text) {
            new QRCode(qrEl, { text: text, width: 200, height: 200, correctLevel: QRCode.CorrectLevel.M });
        }
    }

    // ---------- 复制按钮 ----------
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest ? ev.target.closest('[data-copy],[data-copy-text-target]') : null;
        if (!btn) return;
        var text = '';
        if (btn.getAttribute('data-copy')) {
            text = btn.getAttribute('data-copy');
        } else {
            var t = document.getElementById(btn.getAttribute('data-copy-text-target'));
            text = t ? t.textContent : '';
        }
        if (!text) return;
        copyText(text).then(function () {
            var old = btn.textContent;
            btn.textContent = '已复制 ✓';
            setTimeout(function () { btn.textContent = old; }, 1500);
        });
    });

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            resolve();
        });
    }

    // ---------- 支付页到账轮询 ----------
    if (typeof window.YF_POLL_SN === 'string' && window.YF_POLL_SN) {
        var sn = window.YF_POLL_SN;
        var statusEl = document.getElementById('poll-status');
        var times = 0;
        var timer = setInterval(function () {
            times++;
            fetch('index.php?s=/pay/check&sn=' + encodeURIComponent(sn), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.paid) {
                        clearInterval(timer);
                        if (statusEl) statusEl.textContent = '支付成功! 正在跳转…';
                        setTimeout(function () { location.href = d.detail_url; }, 800);
                    }
                })
                .catch(function () { });
            if (times > 200) clearInterval(timer); // 约10分钟自动停止
        }, 3000);
    }
})();
