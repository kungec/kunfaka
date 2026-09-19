<?php $activeMenu = 'license'; $pro = $license['is_pro']; ?>
<div class="lic-hero <?= $pro ? 'is-pro' : '' ?>">
    <div class="lic-hero-main">
        <div class="lic-badge"><?= $pro ? '👑 专业版会员' : '免费版' ?></div>
        <div class="lic-title"><?= $pro ? '已解锁全部商店内容' : '解锁应用商店全部插件与主题' ?></div>
        <div class="lic-sub">
            <?= $pro
                ? ((int)$license['license_expires'] > 0 ? '有效期至 ' . e(date('Y-m-d', $license['license_expires'])) : '永久有效 · 后续新增付费应用同样免费')
                : '开通专业版(¥' . e($sp['price']) . ') · 一次开通 永久有效 · 含USDT免挂支付等付费插件'
            ?>
            <?php if ($license['license_key']): ?><span style="opacity:.65"> · 授权标识 <?= e($license['license_key']) ?></span><?php endif; ?>
        </div>
    </div>
    <div class="lic-hero-side">
        <div class="lic-feat"><i>✓</i> 全部付费插件免费下载</div>
        <div class="lic-feat"><i>✓</i> 全部付费主题免费使用</div>
        <div class="lic-feat"><i>✓</i> 后续新增付费应用同步免费</div>
    </div>
</div>

<div class="lic-grid">
    <div class="card">
        <h3>在线开通(官方主控)</h3>
        <?php if ($license['is_authed']): ?>
            <?php if ($pro): ?>
                <p class="dim" style="margin:0 0 12px">您已是专业版会员。会员状态每小时自动同步, 也可手动立即同步。</p>
                <button class="btn gray" id="sync-btn">⟳ 同步会员状态</button>
            <?php else: ?>
                <p class="dim" style="margin:0 0 12px">通过官方主控收银台在线支付, 支持 <b>码支付(支付宝/微信/QQ)</b> 与 <b>USDT(TRC20)</b>, 支付成功自动开通并同步回本站。当前登录账号: <b><?= e($license['auth_user']) ?></b></p>
                <button class="btn" id="online-buy" style="height:42px;padding:0 26px;font-size:14px">⚡ 立即在线开通 ¥<?= e($sp['price']) ?></button>
                <button class="btn gray" id="sync-btn" style="margin-left:8px">⟳ 同步会员状态</button>
                <p class="dim" style="margin:10px 0 0">支付完成后将自动跳回本页并同步会员状态。</p>
            <?php endif; ?>
        <?php else: ?>
            <p class="dim" style="margin:0 0 12px">请先登录官方账号(免费注册), 登录后即可在线支付开通专业版。</p>
            <a class="btn" href="#offi-account">前往登录官方账号</a>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>激活授权码</h3>
        <form class="form-inline" id="act-form">
            <input type="text" id="lic-key" placeholder="YF99-XXXXX-XXXXX-XXXXX" style="width:260px" class="mono">
            <button class="btn" type="submit">激活专业版</button>
        </form>
        <p class="dim" style="margin:10px 0 0">向官方购买后获得授权码, 粘贴激活即可(支持离线校验, 无需联网)。</p>
    </div>
</div>

<div class="card" id="offi-account">
    <h3>官方账号</h3>
    <?php if ($license['is_authed']): ?>
        <p class="dim" style="margin:0 0 12px">当前登录: <b><?= e($license['auth_user']) ?></b> <span class="tag ok">已登录</span>　登录官方账号后可在应用商店下载所有免费插件与主题。</p>
        <button class="btn red" id="logout-btn">退出官方账号</button>
    <?php else: ?>
        <form class="form-inline" id="login-form">
            <input type="text" id="acc-user" placeholder="账号" style="width:170px">
            <input type="password" id="acc-pass" placeholder="密码" style="width:170px">
            <button class="btn" type="submit">登录</button>
        </form>
        <p class="dim" style="margin:10px 0 0">注册入口(官方主控): <span class="mono"><?= e($marketUrl ?: '(未配置官方市场地址)') ?></span></p>
    <?php endif; ?>
</div>

<?php if (!$pro): ?>
<details class="card" <?= $pending ? 'open' : '' ?>>
    <summary style="cursor:pointer;font-weight:700">本站独立收款(不用官方主控时, 站长自收会员费)</summary>
    <div style="margin-top:14px">
    <?php if ($pending): ?>
        <div class="sp-pending" id="spPanel" data-sn="<?= e($pending['sn']) ?>" data-channel="<?= e($pending['channel']) ?>">
            <div class="sp-pending-head">
                <b>待支付订单</b>
                <span class="mono dim"><?= e($pending['sn']) ?></span>
                <span class="tag warn"><?= $pending['channel'] === 'usdt' ? 'USDT (TRC20)' : '码支付' ?></span>
            </div>
            <?php if ($pending['channel'] === 'usdt'): ?>
                <div class="sp-amount">请精确转入 <b class="mono"><?= e(number_format((float)$pending['amount'], 6, '.', '')) ?></b> USDT</div>
                <div class="form-inline" style="margin:8px 0">
                    <input type="text" class="mono" value="<?= e($sp['usdt']) ?>" readonly style="width:340px" id="spAddr">
                    <button class="btn sm" type="button" id="spCopy">复制地址</button>
                </div>
                <p class="dim" style="margin:0 0 8px">⚠ 金额必须与上方完全一致(含小数), 系统按精确金额自动识别到账并开通。</p>
            <?php else: ?>
                <div class="sp-amount">应付 <b class="mono">¥<?= e(nf($pending['amount'])) ?></b> CNY</div>
                <p class="dim" style="margin:8px 0">若支付页已关闭, 可点击「立即查询」, 已支付将自动开通。</p>
            <?php endif; ?>
            <div class="form-inline">
                <span class="sp-status" id="spStatus">正在检测支付状态…</span>
                <button class="btn sm" type="button" id="spCheck">立即查询</button>
                <button class="btn sm red" type="button" id="spCancel">取消订单</button>
            </div>
        </div>
    <?php else: ?>
        <div class="form-inline" id="spBuy">
            <div class="form-row" style="margin-bottom:0">
                <label>码支付渠道</label>
                <select id="spPayType" style="width:110px">
                    <option value="alipay">支付宝</option>
                    <option value="wxpay">微信</option>
                    <option value="qqpay">QQ钱包</option>
                </select>
            </div>
            <button class="btn" type="button" data-spbuy="codepay" style="margin-top:18px">¥<?= e($sp['price']) ?> · 码支付开通</button>
            <button class="btn green" type="button" data-spbuy="usdt" style="margin-top:18px"><?= e($sp['usdt_amount']) ?> USDT · 链上开通</button>
        </div>
        <?php if ($sp['codepay_api'] === '' && $sp['usdt'] === ''): ?>
            <p class="dim" style="margin-top:8px">提示: 尚未配置本站收款方式, 请在下方「收款配置」中填写。</p>
        <?php endif; ?>
    <?php endif; ?>

    <details style="margin-top:14px">
        <summary style="cursor:pointer;font-weight:600">收款配置(站长填写: 本站会员费的收款渠道)</summary>
        <form id="spCfg" style="margin-top:10px">
            <div class="form-row">
                <label>USDT收款地址(TRC20)</label>
                <input type="text" name="storepay_usdt" value="<?= e($sp['usdt']) ?>" placeholder="TRC20地址" style="max-width:420px" class="mono">
            </div>
            <div class="form-row">
                <label>USDT应收数量</label>
                <input type="number" step="0.01" name="storepay_usdt_amount" value="<?= e($sp['usdt_amount']) ?>" style="width:130px">
            </div>
            <div class="form-row">
                <label>码支付网关</label>
                <input type="text" name="storepay_codepay_api" value="<?= e($sp['codepay_api']) ?>" placeholder="https://xpay.shw1.com/" style="max-width:420px" class="mono">
            </div>
            <div class="form-inline">
                <div class="form-row" style="margin-bottom:0">
                    <label>商户ID(PID)</label>
                    <input type="text" name="storepay_codepay_pid" value="<?= e($sp['codepay_pid']) ?>" style="width:140px" class="mono">
                </div>
                <div class="form-row" style="margin-bottom:0">
                    <label>商户密钥(KEY)</label>
                    <input type="text" name="storepay_codepay_key" value="<?= e($sp['codepay_key']) ?>" style="width:280px" class="mono">
                </div>
                <div class="form-row" style="margin-bottom:0">
                    <label>专业版定价(¥)</label>
                    <input type="number" name="storepay_price" value="<?= e($sp['price']) ?>" style="width:100px">
                </div>
            </div>
            <div class="form-row">
                <button class="btn" type="submit">保存收款配置</button>
            </div>
        </form>
    </details>
    </div>
</details>
<?php endif; ?>

<script>
function yfPost(url, data) {
    var fd = new FormData();
    fd.append('_csrf', '<?= e(csrf_token()) ?>');
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); });
}
document.getElementById('act-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    yfPost('<?= au('license_activate') ?>', { key: document.getElementById('lic-key').value }).then(function (d) { alert(d.msg); if (d.code === 0) location.reload(); });
});
var lf = document.getElementById('login-form');
if (lf) lf.addEventListener('submit', function (ev) {
    ev.preventDefault();
    yfPost('<?= au('license_login') ?>', { username: document.getElementById('acc-user').value, password: document.getElementById('acc-pass').value }).then(function (d) { alert(d.msg); if (d.code === 0) location.reload(); });
});
var lb = document.getElementById('logout-btn');
if (lb) lb.addEventListener('click', function () {
    yfPost('<?= au('license_logout') ?>', {}).then(function (d) { alert(d.msg); if (d.code === 0) location.reload(); });
});

/* 在线开通(官方主控) */
var ob = document.getElementById('online-buy');
if (ob) ob.addEventListener('click', function () {
    ob.disabled = true;
    ob.textContent = '正在创建订单…';
    yfPost('<?= au('storepay_online') ?>', {}).then(function (d) {
        if (d.code === 0 && d.redirect) { location.href = d.redirect; return; }
        ob.disabled = false;
        ob.textContent = '⚡ 立即在线开通';
        alert(d.msg);
    }).catch(function () { ob.disabled = false; ob.textContent = '⚡ 立即在线开通'; alert('网络错误'); });
});
var sb = document.getElementById('sync-btn');
if (sb) sb.addEventListener('click', function () {
    sb.disabled = true;
    yfPost('<?= au('license_sync') ?>', {}).then(function (d) { alert(d.msg); if (d.code === 0) location.reload(); });
});

/* ---------- 本站独立收款(备用模式) ---------- */
function spPost(url, data, cb) {
    yfPost(url, data).then(cb);
}
document.querySelectorAll('[data-spbuy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        btn.disabled = true;
        spPost('<?= au('storepay_create') ?>', { channel: btn.getAttribute('data-spbuy'), pay_type: (document.getElementById('spPayType') || {}).value || 'alipay' }, function (d) {
            btn.disabled = false;
            if (d.code !== 0) { alert(d.msg); return; }
            if (d.redirect) { location.href = d.redirect; return; }
            location.reload();
        });
    });
});
var panel = document.getElementById('spPanel');
if (panel) {
    var sn = panel.getAttribute('data-sn');
    var statusEl = document.getElementById('spStatus');
    var timer = null;
    function checkOnce() {
        spPost('<?= au('storepay_check') ?>', { sn: sn }, function (d) {
            if (d.paid) {
                statusEl.textContent = '支付成功! 专业版已开通 ✓';
                statusEl.style.color = '#16a34a';
                clearInterval(timer);
                setTimeout(function () { location.reload(); }, 1200);
            } else {
                statusEl.textContent = d.msg ? d.msg : '未检测到支付, 继续等待…';
            }
        });
    }
    timer = setInterval(checkOnce, 4000);
    setTimeout(checkOnce, 400);
    var chk = document.getElementById('spCheck');
    if (chk) chk.addEventListener('click', checkOnce);
    var cancel = document.getElementById('spCancel');
    if (cancel) cancel.addEventListener('click', function () {
        if (!confirm('确定取消该订单?')) return;
        clearInterval(timer);
        spPost('<?= au('storepay_cancel') ?>', { sn: sn }, function (d) { alert(d.msg); if (d.code === 0) location.reload(); });
    });
    var cp = document.getElementById('spCopy');
    if (cp) cp.addEventListener('click', function () {
        var t = document.getElementById('spAddr');
        t.select();
        try { document.execCommand('copy'); } catch (e) {}
        if (navigator.clipboard) navigator.clipboard.writeText(t.value);
        cp.textContent = '已复制 ✓';
        setTimeout(function () { cp.textContent = '复制地址'; }, 1500);
    });
}
var cfg = document.getElementById('spCfg');
if (cfg) cfg.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var data = {};
    cfg.querySelectorAll('[name]').forEach(function (i) { data[i.name] = i.value; });
    yfPost('<?= au('storepay_save') ?>', data).then(function (d) { alert(d.msg); if (d.code === 0) location.reload(); });
});
</script>
