<?php $activeMenu = 'license'; $pro = $license['is_pro']; ?>
<style>
/* QQ群条 */
.lic-top{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:13px 18px;border-radius:14px;margin-bottom:16px;background:var(--input-bg);border:1px solid var(--input-border)}
.qq-ico{flex:none;width:30px;height:30px;border-radius:9px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:14px;box-shadow:0 6px 14px -6px rgba(37,99,235,.5)}
.qq-bar-tx{flex:1;min-width:200px;font-size:12.5px;color:var(--text2)}
.qq-bar-tx b{color:var(--text)}
.qq-join{flex:none;font-size:12px;font-weight:700;color:var(--info);text-decoration:none}

/* 授权卡(会员卡式): 左身份右权益, 金色高光 */
.lic-hero{position:relative;overflow:hidden;border-radius:20px;color:#f5f7ff;background:linear-gradient(125deg,#0b1020 0%,#141b33 48%,#2a2352 100%);border:1px solid rgba(255,255,255,.1);box-shadow:0 28px 70px -26px rgba(0,0,0,.65);display:flex;flex-wrap:wrap}
.lic-hero::before{content:"";position:absolute;inset:0;background:radial-gradient(560px 180px at 88% -10%,rgba(250,204,21,.22),transparent 62%),radial-gradient(420px 160px at -6% 110%,rgba(99,102,241,.28),transparent 60%);pointer-events:none}
.lic-hero.is-free{background:linear-gradient(125deg,#101627 0%,#1c2340 55%,#33226b 100%)}
.lic-hero.is-free::before{background:radial-gradient(560px 180px at 88% -10%,rgba(129,140,248,.25),transparent 62%)}
.lh-main{flex:1 1 380px;padding:30px 34px;position:relative;z-index:1}
.lh-side{flex:0 0 300px;padding:30px 30px;border-left:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.04);position:relative;z-index:1;display:flex;flex-direction:column;justify-content:center;gap:13px}
@media (max-width:860px){.lh-side{flex:1 1 100%;border-left:none;border-top:1px solid rgba(255,255,255,.12)}}
.lh-badge{display:inline-flex;align-items:center;gap:7px;align-self:flex-start;font-size:12px;font-weight:800;letter-spacing:1px;padding:6px 15px;border-radius:999px;margin-bottom:16px;background:rgba(250,204,21,.15);color:#fde68a;border:1px solid rgba(250,204,21,.4);box-shadow:0 0 22px -6px rgba(250,204,21,.55)}
.lic-hero.is-free .lh-badge{background:rgba(129,140,248,.15);color:#c7d2fe;border-color:rgba(129,140,248,.4);box-shadow:none}
.lh-title{font-size:25px;font-weight:800;letter-spacing:.4px;line-height:1.3;margin-bottom:8px}
.lh-sub{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:12.5px;color:rgba(245,247,255,.68);margin-bottom:16px}
.lh-sub .dot{width:7px;height:7px;border-radius:50%;background:#4ade80;box-shadow:0 0 8px #4ade80;flex:none}
.lh-keyrow{display:inline-flex;align-items:center;gap:0;max-width:100%}
.lh-key{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;letter-spacing:1px;padding:9px 14px;border-radius:10px 0 0 10px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16);border-right:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lh-copy{border:none;border-radius:0 10px 10px 0;background:rgba(250,204,21,.9);color:#3b2f14;font-size:12px;font-weight:800;padding:9px 14px;cursor:pointer;font-family:inherit}
.lh-copy:hover{filter:brightness(1.07)}
.lh-side-t{font-size:11.5px;font-weight:800;letter-spacing:2px;color:rgba(245,247,255,.55);margin-bottom:2px}
.lh-feat{display:flex;align-items:flex-start;gap:10px;font-size:13px;color:rgba(245,247,255,.88);line-height:1.5}
.lh-feat i{flex:none;width:20px;height:20px;border-radius:50%;background:rgba(250,204,21,.18);color:#fde68a;font-style:normal;font-size:11px;display:inline-flex;align-items:center;justify-content:center;font-weight:800;margin-top:1px}
.lic-hero.is-free .lh-feat i{background:rgba(129,140,248,.18);color:#c7d2fe}

/* 操作区 */
.lic-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:stretch;margin-top:16px}
@media (max-width:1000px){.lic-grid{grid-template-columns:1fr}}
.lic-card h3{margin-bottom:12px}
.lic-card .hint{font-size:12.5px;color:var(--muted);line-height:1.8;margin:0 0 14px}
.lic-card .hint b{color:var(--text)}
.lic-key{display:flex;gap:8px;align-items:center;margin-bottom:12px;flex-wrap:wrap}
.lic-key input{flex:1;min-width:0;height:42px;padding:0 14px;border:1.5px solid var(--input-border);border-radius:10px;background:var(--input-bg);color:var(--text);font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;outline:none;transition:border-color .15s}
.lic-key input:focus{border-color:var(--info)}
.lic-acts{display:flex;gap:9px;flex-wrap:wrap}
.lic-badge-chip{display:inline-flex;align-items:center;gap:8px;background:var(--ok-bg);color:var(--ok);border-radius:10px;padding:10px 14px;font-size:12.5px;font-weight:700;margin-bottom:14px}
</style>

<div class="lic-top">
    <span class="qq-ico">💬</span>
    <span class="qq-bar-tx">官方售后QQ群: <b>307386089</b> —— 授权购买、部署协助、插件咨询请加群。</span>
    <a class="qq-join" href="https://qm.qq.com/cgi-bin/qm/qr?k=307386089" target="_blank" rel="noopener">前往加群 ›</a>
</div>

<div class="lic-hero <?= $pro ? '' : 'is-free' ?>">
    <div class="lh-main">
        <div class="lh-badge"><?= $pro ? '👑 专业版授权 · 已激活' : '免费版 · 未激活专业版' ?></div>
        <div class="lh-title"><?= $pro ? '已解锁全部商店内容' : '解锁应用商店全部插件与主题' ?></div>
        <div class="lh-sub">
            <?php if ($pro): ?>
                <span class="dot"></span>
                <span><?= (int)$license['license_expires'] > 0 ? '有效期至 ' . e(date('Y-m-d', $license['license_expires'])) : '永久授权' ?></span>
                <span>·</span>
                <span>后续新增付费应用同步免费</span>
            <?php else: ?>
                <span>专业版 ¥<?= e($price) ?> · 一次购买 永久授权 · 一码绑定一个站点</span>
            <?php endif; ?>
        </div>
        <?php if ($pro && $license['license_key']): ?>
        <div class="lh-keyrow">
            <span class="lh-key" id="hero-key"><?= e($license['license_key']) ?></span>
            <button class="lh-copy" id="hero-copy" type="button">复制</button>
        </div>
        <?php endif; ?>
    </div>
    <div class="lh-side">
        <div class="lh-side-t">专业版权益</div>
        <div class="lh-feat"><i>✓</i> 全部付费插件免费下载</div>
        <div class="lh-feat"><i>✓</i> 全部付费主题免费使用与切换</div>
        <div class="lh-feat"><i>✓</i> 后续新增付费应用同步免费</div>
    </div>
</div>

<?php if ($buySn !== ''): ?>
<div class="card" id="buy-panel" data-sn="<?= e($buySn) ?>">
    <h3>等待支付结果</h3>
    <p class="dim" style="margin:0 0 10px">订单 <b class="mono"><?= e($buySn) ?></b> 正在确认支付, 确认后激活码会自动显示在下方并发送到您留下的邮箱…</p>
    <p class="dim" id="buy-status" style="margin:0">⟳ 正在查询订单状态…</p>
    <div id="buy-done" style="display:none;margin-top:12px">
        <p style="margin:0 0 8px;color:var(--ok);font-weight:700">✅ 支付成功! 您的永久授权码:</p>
        <div class="lic-key">
            <input type="text" id="buy-key" readonly class="mono" style="font-weight:700">
            <button class="btn gray" id="buy-copy" type="button">复制授权码</button>
            <button class="btn" id="buy-activate" type="button">⚡ 一键激活本站</button>
        </div>
        <p class="dim" style="margin:10px 0 0">授权码已同步发送至您的邮箱; 一码仅绑定一个站点, 如需给其他站点使用请勿点击一键激活。</p>
    </div>
</div>
<?php endif; ?>

<div class="lic-grid">
    <?php if ($pro): ?>
    <div class="card lic-card">
        <h3>🛡 授权管理</h3>
        <div class="lic-badge-chip">✓ 本站专业版已生效<?= (int)$license['license_expires'] > 0 ? '(至 ' . e(date('Y-m-d', $license['license_expires'])) . ')' : '(永久)' ?></div>
        <p class="hint">重验证需联网, 同站重复验证无副作用; 也可粘贴新的授权码为本站续期/换绑。</p>
        <div class="lic-acts"><button class="btn gray" id="verify-btn">✓ 验证授权</button></div>
        <form class="lic-key" id="act-form" style="margin:14px 0 0">
            <input type="text" id="lic-key" placeholder="粘贴新授权码换绑 / 续期" class="mono">
            <button class="btn" type="submit">激活</button>
        </form>
    </div>
    <div class="card lic-card">
        <h3>🛒 为其他站点购买授权码</h3>
        <p class="hint">填写邮箱跳转官方收银台, 支持 <b>支付宝 / 微信 / USDT(TRC20)</b>。支付成功后自动生成<b>永久授权码</b>: 本页展示并同步发送邮箱, 可用于激活其他站点。</p>
        <form class="lic-key" id="buy-form">
            <input type="email" id="buy-email" placeholder="接收授权码的邮箱" required>
            <button class="btn" type="submit">⚡ 购买 ¥<?= e($price) ?></button>
        </form>
    </div>
    <?php else: ?>
    <div class="card lic-card">
        <h3>🔑 激活授权码</h3>
        <form class="lic-key" id="act-form">
            <input type="text" id="lic-key" placeholder="YF99-XXXXX-XXXXX-XXXXX" class="mono">
            <button class="btn" type="submit">激活专业版</button>
        </form>
        <p class="hint" style="margin:0">向官方购买后获得授权码, 粘贴激活即可(支持离线校验, 无需联网)。<br>注意: 一码仅绑定一个站点, 激活后不可更换。</p>
    </div>
    <div class="card lic-card">
        <h3>🛒 购买授权码</h3>
        <p class="hint">填写邮箱后跳转官方收银台, 支持 <b>支付宝 / 微信 / USDT(TRC20)</b>。支付成功后系统自动生成<b>永久授权码</b>: 本页直接展示并同步发送到您的邮箱。</p>
        <form class="lic-key" id="buy-form">
            <input type="email" id="buy-email" placeholder="接收授权码的邮箱" required>
            <button class="btn" type="submit">⚡ 立即购买 ¥<?= e($price) ?></button>
        </form>
        <p class="hint" style="margin:0">已有授权码? 直接在左侧「激活授权码」粘贴激活。</p>
    </div>
    <?php endif; ?>
</div>

<script>
function yfPost(url, data) {
    var fd = new FormData();
    fd.append('_csrf', '<?= e(csrf_token()) ?>');
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); });
}
/* 授权卡上的复制按钮 */
var hc = document.getElementById('hero-copy');
if (hc) hc.addEventListener('click', function () {
    var t = document.getElementById('hero-key').textContent.trim();
    if (navigator.clipboard) navigator.clipboard.writeText(t);
    else { var x = document.createElement('textarea'); x.value = t; document.body.appendChild(x); x.select(); try { document.execCommand('copy'); } catch (e) {} document.body.removeChild(x); }
    hc.textContent = '已复制 ✓';
    setTimeout(function () { hc.textContent = '复制'; }, 1500);
});
document.getElementById('act-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    yfPost('<?= au('license_activate') ?>', { key: document.getElementById('lic-key').value }).then(function (d) { kAlert(d.msg); if (d.code === 0) location.reload(); });
});
var bf = document.getElementById('buy-form');
if (bf) bf.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var btn = bf.querySelector('button[type=submit]');
    btn.disabled = true;
    btn.textContent = '正在创建订单…';
    yfPost('<?= au('license_buy') ?>', { email: document.getElementById('buy-email').value }).then(function (d) {
        if (d.code === 0 && d.redirect) { location.href = d.redirect; return; }
        btn.disabled = false;
        btn.textContent = '⚡ 立即购买 ¥<?= e($price) ?>';
        kAlert(d.msg);
    }).catch(function () { btn.disabled = false; btn.textContent = '⚡ 立即购买 ¥<?= e($price) ?>'; kAlert('网络错误'); });
});
var vb = document.getElementById('verify-btn');
if (vb) vb.addEventListener('click', function () {
    vb.disabled = true;
    yfPost('<?= au('license_verify') ?>', {}).then(function (d) { kAlert(d.msg); vb.disabled = false; });
});
/* 支付返回: 轮询订单, 出码即可复制/一键激活 */
var bp = document.getElementById('buy-panel');
if (bp) {
    var sn = bp.getAttribute('data-sn');
    var timer = null;
    function gotKey(key) {
        if (timer) clearInterval(timer);
        document.getElementById('buy-status').style.display = 'none';
        document.getElementById('buy-done').style.display = 'block';
        document.getElementById('buy-key').value = key;
        document.getElementById('buy-copy').addEventListener('click', function () {
            var t = document.getElementById('buy-key');
            t.select();
            try { document.execCommand('copy'); } catch (e) {}
            if (navigator.clipboard) navigator.clipboard.writeText(t.value);
            this.textContent = '已复制 ✓';
            var b = this;
            setTimeout(function () { b.textContent = '复制授权码'; }, 1500);
        });
        document.getElementById('buy-activate').addEventListener('click', function () {
            yfPost('<?= au('license_activate') ?>', { key: key }).then(function (d) { kAlert(d.msg); if (d.code === 0) location.href = '<?= au('license') ?>'; });
        });
    }
    function poll() {
        yfPost('<?= au('license_buy_check') ?>', { sn: sn }).then(function (d) {
            if (d.code === 0 && d.paid && d.license_key) { gotKey(d.license_key); }
            else if (d.code === 0 && d.paid) { document.getElementById('buy-status').textContent = '支付已确认, 正在生成授权码…'; }
            else { document.getElementById('buy-status').textContent = '⟳ 未检测到支付, 继续等待…'; }
        }).catch(function () {});
    }
    timer = setInterval(poll, 4000);
    poll();
}
</script>
