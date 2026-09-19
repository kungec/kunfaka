/* ============ 坤发卡 后台公共JS ============ */
// 移动端抽屉侧栏
(function () {
    var burger = document.getElementById('sideBurger');
    var side = document.getElementById('sideNav');
    var mask = document.getElementById('sideMask');
    if (burger && side) {
        burger.addEventListener('click', function () {
            side.classList.add('open');
            if (mask) mask.classList.add('show');
        });
    }
    if (mask) {
        mask.addEventListener('click', function () {
            side.classList.remove('open');
            mask.classList.remove('show');
        });
    }
    // 抽屉内点击链接后自动收起(移动端)
    if (side) {
        side.addEventListener('click', function (ev) {
            if (ev.target.closest('a') && window.innerWidth <= 900) {
                side.classList.remove('open');
                if (mask) mask.classList.remove('show');
            }
        });
    }
})();

// AJAX POST 辅助: data-ajax 表单
document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-ajax')) return;
    ev.preventDefault();
    var url = form.getAttribute('action');
    var fd = new FormData(form);
    fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            alert(d.msg || (d.code === 0 ? '操作成功' : '操作失败'));
            if (d.code === 0 && form.getAttribute('data-refresh') !== 'no') {
                location.reload();
            }
        })
        .catch(function () { alert('网络错误, 请重试'); });
});

// 确认删除: data-confirm
document.addEventListener('click', function (ev) {
    var el = ev.target.closest ? ev.target.closest('[data-confirm]') : null;
    if (el && !confirm(el.getAttribute('data-confirm'))) {
        ev.preventDefault();
        ev.stopPropagation();
    }
}, true);

// 复制
function yfCopy(text, btn) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function () { flash(btn); });
    } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        flash(btn);
    }
}
function flash(btn) {
    if (!btn) return;
    var old = btn.textContent;
    btn.textContent = '已复制';
    setTimeout(function () { btn.textContent = old; }, 1200);
}
