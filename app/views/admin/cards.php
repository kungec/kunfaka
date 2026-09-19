<?php $activeMenu = 'cards'; $pages = max(1, (int)ceil($total / $per));
$stMap = [0 => '未出售', 1 => '已出售', 2 => '已锁定'];
$curStatus = isset($_GET['status']) ? (string)$_GET['status'] : '';
$f = [];
foreach (['product_id', 'exact', 'fuzzy', 'status', 'date_from', 'date_to'] as $k) {
    $v = isset($_GET[$k]) ? (string)$_GET[$k] : '';
    if ($v !== '') $f[$k] = $v;
}
$pageLink = function ($i) use ($f) {
    $p = $f;
    if ($i > 1) $p['page'] = $i;
    return au('cards', $p);
};
$exportUrl = au('cards_export', $f);
?>
<style>
.k-filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(165px,1fr));gap:9px 10px}
.k-filters .kf label{display:block;font-size:11px;color:var(--muted);margin-bottom:4px;font-weight:600}
.k-filters input,.k-filters select{width:100%;height:36px;padding:0 10px;font-size:12.5px}
.k-tabs{display:flex;gap:7px;margin-top:14px}
.k-tabs a{padding:6px 16px;border-radius:8px;border:1.5px solid var(--input-border);font-size:12.5px;font-weight:600;color:var(--text2);text-decoration:none;transition:.12s}
.k-tabs a.on{border-color:var(--text);color:var(--text);background:var(--input-bg)}
.k-tabs a:hover{border-color:var(--muted)}
th.k-chk,td.k-chk{width:34px;text-align:center}
.k-content{display:flex;align-items:center;gap:6px;max-width:280px}
.k-content .txt{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.k-copy{border:none;background:transparent;color:var(--muted);cursor:pointer;font-size:11px;padding:2px 4px;border-radius:4px;flex:none}
.k-copy:hover{color:var(--text);background:var(--input-border)}
.k-time small{display:block;color:var(--muted);font-size:10.5px;line-height:1.6}
/* 弹窗 */
.k-mask{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:120;display:none;align-items:flex-start;justify-content:center;padding:60px 16px}
.k-mask.on{display:flex}
.k-modal{background:var(--card);border:1px solid var(--input-border);border-radius:14px;width:100%;max-width:560px;box-shadow:var(--shadow-lg,0 18px 50px rgba(0,0,0,.35));overflow:hidden}
.k-modal .m-head{padding:15px 20px;font-weight:700;font-size:14.5px;border-bottom:1px solid var(--input-border);display:flex;justify-content:space-between;align-items:center}
.k-modal .m-head .x{cursor:pointer;color:var(--muted);font-size:17px;background:none;border:none}
.k-modal .m-body{padding:18px 20px}
.k-modal .m-body label{display:block;font-size:11.5px;color:var(--muted);font-weight:600;margin-bottom:5px}
.k-modal .m-body select,.k-modal .m-body input[type=text]{width:100%;height:40px;padding:0 12px;font-size:13px;margin-bottom:13px}
.k-modal .hint{font-size:11.5px;color:var(--muted);margin:8px 0 4px}
.k-modal .example{background:var(--input-bg);border:1px solid var(--input-border);border-radius:8px;padding:9px 12px;font-size:11.5px;color:var(--muted);margin-bottom:10px;line-height:1.7}
.k-modal textarea{width:100%;min-height:150px;font-size:12.5px}
.k-modal .m-foot{padding:14px 20px;border-top:1px solid var(--input-border);display:flex;gap:9px;justify-content:center}
</style>

<div class="page-head">
    <div>
        <h2>🔑 卡密管理</h2>
        <div class="sub">卡密的导入 · 锁定 · 标记出售 · 导出</div>
    </div>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px">
        <div style="display:flex;gap:9px;flex-wrap:wrap">
            <button class="btn sm" id="uploadBtn">☁ 上传卡密</button>
            <button class="btn sm red" id="delBtn" disabled data-op="delete" data-confirm="⚠ 确定移除选中的卡密? 已出售卡密将自动跳过">🗑 移除选中卡密</button>
            <button class="btn sm gray" id="lockBtn" disabled data-op="lock" data-confirm="确定锁定选中的卡密? 锁定后暂不出库">🔒 锁定选中卡密</button>
            <button class="btn sm green" id="unlockBtn" disabled data-op="unlock" data-confirm="确定解锁选中的卡密?">🔓 解锁选中卡密</button>
            <button class="btn sm green" id="soldBtn" disabled data-op="marksold" data-confirm="确定将选中卡密更改为已出售? 常用于线下售出后手动核销">↻ 将卡密更改为已出售</button>
            <a class="btn sm" href="<?= e($exportUrl) ?>">⬇ 导出筛选卡密</a>
        </div>
    </div>

    <form method="get" action="<?= site_url('admin.php') ?>">
        <input type="hidden" name="s" value="/cards">
        <div class="k-filters">
            <div class="kf"><label>卡密信息(精确搜索, 速度快)</label><input type="text" name="exact" value="<?= e(arr_get($_GET, 'exact')) ?>"></div>
            <div class="kf"><label>卡密信息(模糊搜索, 速度慢)</label><input type="text" name="fuzzy" value="<?= e(arr_get($_GET, 'fuzzy')) ?>"></div>
            <div class="kf"><label>查询商品</label>
                <select name="product_id">
                    <option value="">全部</option>
                    <?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $productId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="kf"><label>从入库时间</label><input type="datetime-local" name="date_from" value="<?= e(arr_get($_GET, 'date_from')) ?>"></div>
            <div class="kf"><label>到入库时间</label><input type="datetime-local" name="date_to" value="<?= e(arr_get($_GET, 'date_to')) ?>"></div>
            <div class="kf"><label>&nbsp;</label><button class="btn" type="submit" style="width:100%">🔍 查询</button></div>
        </div>
    </form>

    <div class="k-tabs">
        <a href="<?= e(au('cards', array_filter($f, function ($k) { return $k !== 'status'; }, ARRAY_FILTER_USE_KEY))) ?>" class="<?= $curStatus === '' ? 'on' : '' ?>">全部</a>
        <a href="<?= e(au('cards', array_merge($f, ['status' => '0']))) ?>" class="<?= $curStatus === '0' ? 'on' : '' ?>">未出售</a>
        <a href="<?= e(au('cards', array_merge($f, ['status' => '1']))) ?>" class="<?= $curStatus === '1' ? 'on' : '' ?>">已出售</a>
        <a href="<?= e(au('cards', array_merge($f, ['status' => '2']))) ?>" class="<?= $curStatus === '2' ? 'on' : '' ?>">已锁定</a>
    </div>
</div>

<div class="card">
    <table class="tb" id="cardTable">
        <thead>
        <tr>
            <th class="k-chk"><input type="checkbox" id="chkAll" style="width:auto"></th>
            <th>卡密信息</th>
            <th>商品</th>
            <th>创建 / 出售时间</th>
            <th>备注信息</th>
            <th>状态</th>
            <th>订单号</th>
            <th>操作</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($list as $c): $sid = (int)$c['status']; ?>
            <tr>
                <td class="k-chk"><input type="checkbox" class="row-chk" value="<?= (int)$c['id'] ?>" style="width:auto"></td>
                <td>
                    <div class="k-content">
                        <span class="txt" title="<?= e($c['content']) ?>"><?= e(mb_substr($c['content'], 0, 42)) ?><?= mb_strlen($c['content']) > 42 ? '…' : '' ?></span>
                        <button class="k-copy" data-copy-card="<?= e($c['content']) ?>" title="复制卡密">⧉</button>
                    </div>
                </td>
                <td class="dim"><?= e($c['product_name'] ?: '已删除商品') ?></td>
                <td class="k-time">
                    <small>创建 <?= e(date('Y-m-d H:i', $c['created_at'])) ?></small>
                    <small>出售 <?= $c['sold_at'] > 0 ? e(date('Y-m-d H:i', $c['sold_at'])) : '未出售' ?></small>
                </td>
                <td class="dim"><?= e($c['note'] ?: '—') ?></td>
                <td><span class="tag <?= $sid === 0 ? 'ok' : ($sid === 2 ? 'warn' : '') ?>"><?= $stMap[$sid] ?? '未知' ?></span></td>
                <td class="dim mono"><?= (int)$c['order_id'] > 0 ? '#' . (int)$c['order_id'] : '—' ?></td>
                <td class="actions">
                    <?php if ($sid === 0): ?><button class="btn sm gray" data-lock="lock" data-id="<?= (int)$c['id'] ?>" data-confirm="确定锁定该卡密? 锁定后暂不出库">🔒 锁定</button><?php endif; ?>
                    <?php if ($sid === 2): ?><button class="btn sm green" data-lock="unlock" data-id="<?= (int)$c['id'] ?>">🔓 解锁</button><?php endif; ?>
                    <?php if ($sid !== 1): ?><button class="btn sm red" data-delcard="<?= (int)$c['id'] ?>" data-confirm="确定删除该卡密?">🗑 删除</button><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="8"><div style="text-align:center;padding:34px 0;color:var(--muted)"><div style="font-size:34px;opacity:.55;margin-bottom:6px">🔑</div>暂无卡密, 点击左上角「上传卡密」导入</div></td></tr><?php endif; ?>
        </tbody>
    </table>
    <div style="font-size:12px;color:var(--muted);padding:10px 2px 0">共 <?= (int)$total ?> 条</div>
    <?php if ($pages > 1): ?>
        <div class="pager">
            <?php for ($i = 1; $i <= min($pages, 12); $i++): ?>
                <a class="<?= $i === $page ? 'on' : '' ?>" href="<?= e($pageLink($i)) ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<!-- 上传卡密弹窗 -->
<div class="k-mask" id="kMask">
    <div class="k-modal">
        <div class="m-head"><span>⬆ 上传卡密</span><button class="x" id="kModalClose" type="button">✕</button></div>
        <div class="m-body">
            <label>选择商品 *</label>
            <select id="k-product">
                <option value="0">请选择商品</option>
                <?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
            </select>
            <label>备注信息(该批卡密统一备注)</label>
            <input type="text" id="k-note" maxlength="200" placeholder="如 供应商A 2026-09批次">
            <div class="hint">一行一个库存卡密, 内容随意, 买家购买后直接获得该行内容:</div>
            <div class="example">ABCDEF-GHIJK-LMNOP<br>VIP-2025-0821-XYZ</div>
            <textarea id="k-cards" placeholder="卡密信息, 一行一个"></textarea>
            <div style="margin-top:10px;display:flex;align-items:center;gap:8px;font-size:13px">
                <input type="checkbox" id="k-dedup" style="width:auto"> 去除重复(自动跳过该商品已存在的卡密与本次输入中的重复行)
            </div>
        </div>
        <div class="m-foot">
            <button class="btn" id="kSave" style="min-width:110px">💾 保存</button>
            <button class="btn gray" id="kCancel">✕ 取消</button>
        </div>
    </div>
</div>

<script>
function yfPost(url, fd) {
    fd.append('_csrf', '<?= e(csrf_token()) ?>');
    return fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); });
}
/* 全选 */
var chkAll = document.getElementById('chkAll');
function rowChks() { return Array.prototype.slice.call(document.querySelectorAll('.row-chk')); }
function syncBtns() {
    var n = rowChks().filter(function (c) { return c.checked; }).length;
    ['delBtn', 'lockBtn', 'unlockBtn', 'soldBtn'].forEach(function (id) {
        var b = document.getElementById(id);
        if (b) b.disabled = n === 0;
    });
}
if (chkAll) chkAll.addEventListener('change', function () {
    rowChks().forEach(function (c) { c.checked = chkAll.checked; });
    syncBtns();
});
document.addEventListener('change', function (ev) {
    if (ev.target.classList && ev.target.classList.contains('row-chk')) syncBtns();
});
/* 上传弹窗 */
var kMask = document.getElementById('kMask');
document.getElementById('uploadBtn').addEventListener('click', function () { kMask.classList.add('on'); });
document.getElementById('kModalClose').addEventListener('click', function () { kMask.classList.remove('on'); });
document.getElementById('kCancel').addEventListener('click', function () { kMask.classList.remove('on'); });
kMask.addEventListener('click', function (ev) { if (ev.target === kMask) kMask.classList.remove('on'); });
document.getElementById('kSave').addEventListener('click', function () {
    var fd = new FormData();
    fd.append('product_id', document.getElementById('k-product').value);
    fd.append('note', document.getElementById('k-note').value.trim());
    fd.append('cards', document.getElementById('k-cards').value.trim());
    fd.append('dedup', document.getElementById('k-dedup').checked ? '1' : '0');
    yfPost('<?= au('cards_import') ?>', fd).then(function (d) {
        alert(d.msg);
        if (d.code === 0) location.reload();
    });
});
/* 行内与批量 */
document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-copy-card],[data-delcard],[data-lock],[data-op]') : null;
    if (!t) return;
    if (t.hasAttribute('data-copy-card')) {
        var tmp = document.createElement('textarea');
        tmp.value = t.getAttribute('data-copy-card');
        document.body.appendChild(tmp); tmp.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(tmp);
        t.textContent = '✓';
        var b = t;
        setTimeout(function () { b.textContent = '⧉'; }, 1200);
        return;
    }
    var fd = new FormData();
    if (t.hasAttribute('data-op')) {
        var ids = rowChks().filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
        if (!ids.length) return;
        if (!confirm(t.getAttribute('data-confirm') || '确定执行该操作?')) return;
        ids.forEach(function (v) { fd.append('ids[]', v); });
        fd.append('op', t.getAttribute('data-op'));
        yfPost('<?= au('cards_batch') ?>', fd).then(function (d) { alert(d.msg); if (d.code === 0) location.reload(); });
        return;
    }
    if (t.hasAttribute('data-lock')) {
        if (!confirm(t.getAttribute('data-confirm') || '确定执行该操作?')) return;
        fd.append('id', t.getAttribute('data-id'));
        fd.append('op', t.getAttribute('data-lock'));
        yfPost('<?= au('card_lock') ?>', fd).then(function (d) { alert(d.msg); if (d.code === 0) location.reload(); });
        return;
    }
    if (!confirm(t.getAttribute('data-confirm') || '确定删除该卡密?')) return;
    fd.append('id', t.getAttribute('data-delcard'));
    yfPost('<?= au('card_del') ?>', fd).then(function (d) { alert(d.msg); if (d.code === 0) location.reload(); });
});
</script>
