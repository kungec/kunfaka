<?php $activeMenu = 'license'; $pro = $license['is_pro']; ?>
<style>
.qq-bar{display:flex;align-items:center;gap:10px;padding:11px 16px;border:1px solid var(--input-border);border-radius:12px;background:var(--input-bg);margin:0 0 16px;font-size:12.5px;color:var(--text2)}
.qq-bar b{color:var(--text)}
.qq-ico{flex:none;width:26px;height:26px;border-radius:8px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:13px}
.lic-hero{display:grid;grid-template-columns:minmax(0,1fr) 280px;gap:18px;align-items:center;border-radius:16px;padding:26px 28px;margin-bottom:16px;position:relative;overflow:hidden}
.lic-hero::after{content:'';position:absolute;inset:0;background:radial-gradient(420px 200px at 92% 0%,rgba(255,255,255,.14),transparent 60%);pointer-events:none}
.lic-hero.is-pro{background:linear-gradient(120deg,#b45309,#d97706 45%,#f59e0b);color:#fff;box-shadow:0 18px 44px -18px rgba(217,119,6,.55)}
.lic-hero:not(.is-pro){background:linear-gradient(120deg,#4f46e5,#7c3aed 55%,#a855f7);color:#fff;box-shadow:0 18px 44px -18px rgba(124,58,237,.5)}
.lic-badge{display:inline-block;font-size:11.5px;font-weight:800;letter-spacing:.5px;padding:5px 13px;border-radius:999px;background:rgba(255,255,255,.2);backdrop-filter:blur(4px);margin-bottom:12px}
.lic-title{font-size:21px;font-weight:800;letter-spacing:.3px}
.lic-sub{margin-top:8px;font-size:13px;opacity:.92}
.lic-sub .mono{opacity:.8}
.lic-feats{display:flex;flex-direction:column;gap:9px;position:relative;z-index:1}
.lic-feat{display:flex;align-items:center;gap:8px;font-size:12.5px;font-weight:600}
.lic-feat i{flex:none;width:20px;height:20px;border-radius:50%;background:rgba(255,255,255,.25);color:#fff;font-style:normal;font-size:11px;display:inline-flex;align-items:center;justify-content:center;font-weight:800}
@media (max-width:1000px){.lic-hero{grid-template-columns:1fr}.lic-feats{flex-direction:row;flex-wrap:wrap;gap:8px}}
.lic-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;margin-bottom:16px}
@media (max-width:1000px){.lic-grid{grid-template-columns:1fr}}
.lic-card h3{margin-bottom:12px}
.lic-card .hint{font-size:12.5px;color:var(--muted);line-height:1.8;margin:0 0 14px}
.lic-card .hint b{color:var(--text)}
.lic-key{display:flex;gap:8px;align-items:center;margin-bottom:12px}
.lic-key input{flex:1;min-width:0;height:40px;padding:0 14px;border:1.5px solid var(--input-border);border-radius:10px;background:var(--input-bg);color:var(--text);font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;outline:none}
.lic-key input:focus{border-color:var(--info)}
.lic-acts{display:flex;gap:9px;flex-wrap:wrap;align-items:center}
.perx{font-size:22px;font-weight:800}
</style>

<div class="qq-bar">
    <span class="qq-ico">💬</span>
    <span>官方售后QQ群: <b>307386089</b> —— 授权购买、部署协助、插件咨询请加群。</span>
</div>

<div class="lic-hero <?= $pro ? 'is-pro' : '' ?>">
    <div class="lic-hero-main">
        <div class="lic-badge"><?= $pro ? '👑 专业版授权' : '免费版' ?></div>
        <div class="lic-title"><?= $pro ? '已解锁全部商店内容' : '解锁应用商店全部插件与主题' ?></div>
        <div class="lic-sub">
            <?= $pro
                ? ((int)$license['license_expires'] > 0 ? '有效期至 ' . e(date('Y-m-d', $license['license_expires'])) : '永久授权 · 后续新增付费应用同样免费')
                : '专业版(¥' . e($price) . ') · 一次购买 永久授权 · 一码绑定一个站点'
            ?>
            <?php if ($license['license_key']): ?><span class="mono"> · 授权码 <?= e($license['license_key']) ?></span><?php endif; ?>
        </div>
    </div>
    <div class="lic-feats">
        <div class="lic-feat"><i>✓</i> 全部付费插件免费下载</div>
        <div class="lic-feat"><i>✓</i> 全部付费主题免费使用</div>
        <div class="lic-feat"><i>✓</i> 后续新增付费应用同步免费</div>
    </div>
</div>

<?php if ($buySn !== ''): ?>
<div class="card" id="buy-panel" data-sn="<?= e($buySn) ?>">
    <h3>等待支付结果</h3>
    <p class="dim" style="margin:0 0 10px">订单 <b class="mono"><?= e($buySn) ?></b> 正在确认支付, 确认后激活码会自动显示在下方并发送到您留下的邮箱…</p>
    <p class="dim" id="buy-status" style="margin:0">⟳ 正在查询订单状态…</p>
    <div id="buy-done" style="display:none;margin-top:12px">
        <p style="margin:0 0 8px;color:var(--ok);font-weight:700">✅ 支付成功! 您的永久授权码:</p>
        <div class="form-inline">
            <input type="text" id="buy-key" readonly class="mono" style="width:280px;font-weight:700">
            <button class="btn gray" id="buy-copy" type="button">复制授权码</button>
            <button class="btn" id="buy-activate" type="button">⚡ 一键激活本站</button>
        </div>
        <p class="dim" style="margin:10px 0 0">授权码已同步发送至您的邮箱; 一码仅绑定一个站点, 如需给其他站点使用请勿点击一键激活。</p>
    </div>
</div>
<?php endif; ?>

<div class="lic-grid">
    <div class="card lic-card">
        <h3>🛒 购买授权码</h3>
        <?php if ($pro): ?>
            <p class="hint">本站已激活专业版。可随时重新验证授权(需联网, 同站重复验证无副作用)。</p>
            <div class="lic-acts"><button class="btn gray" id="verify-btn">✓ 验证授权</button></div>
        <?php else: ?>
            <p class="hint">填写邮箱后跳转官方收银台, 支持 <b>支付宝 / 微信 / USDT(TRC20)</b>。支付成功后系统自动生成<b>永久授权码</b>: 本页直接展示并同步发送到您的邮箱。</p>
            <form class="form-inline" id="buy-form">
                <input type="email" id="buy-email" placeholder="接收授权码的邮箱" style="width:220px" required>
                <button class="btn" type="submit" style="height:42px;padding:0 26px;font-size:14px">⚡ 立即购买 <span class="perx">¥<?= e($price) ?></span></button>
            </form>
            <p class="hint" style="margin:10px 0 0">一个授权码可激活一个站点; 已有授权码可直接在右侧粘贴激活。</p>
        <?php endif; ?>
    </div>

    <div class="card lic-card">
        <h3>🔑 激活授权码</h3>
        <form class="lic-key" id="act-form">
            <input type="text" id="lic-key" placeholder="YF99-XXXXX-XXXXX-XXXXX" class="mono">
            <button class="btn" type="submit">激活专业版</button>
        </form>
        <p class="hint" style="margin:0">向官方购买后获得授权码, 粘贴激活即可(支持离线校验, 无需联网)。<br>注意: 一码仅绑定一个站点, 激活后不可更换。</p>
    </div>
</div>

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
