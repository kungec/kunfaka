<?php $activeMenu = 'orders'; $pages = max(1, (int)ceil($total / $per));
$stMap = [0 => '待支付', 1 => '已完成', 2 => '已过期', 3 => '待处理'];
$curStatus = isset($_GET['status']) ? (string)$_GET['status'] : '';
/* 保留当前筛选条件 */
$f = [];
foreach (['sn', 'product_id', 'card', 'contact', 'status', 'plugin', 'ip', 'user_id', 'date_from', 'date_to'] as $k) {
    $v = isset($_GET[$k]) ? (string)$_GET[$k] : '';
    if ($v !== '') $f[$k] = $v;
}
$tabLink = function ($status) use ($f) {
    $p = $f;
    if ($status !== '') $p['status'] = $status;
    return au('orders', $p);
};
$pageLink = function ($i) use ($f) {
    $p = $f;
    if ($i > 1) $p['page'] = $i;
    return au('orders', $p);
};
$exportUrl = au('orders_export', $f);
?>
<style>
.o-filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:9px 10px}
.o-filters .of label{display:block;font-size:11px;color:var(--muted);margin-bottom:4px;font-weight:600}
.o-filters input,.o-filters select{width:100%;height:36px;padding:0 10px;font-size:12.5px}
.o-tabs{display:flex;gap:7px;margin:14px 0 0}
.o-tabs a{padding:6px 16px;border-radius:8px;border:1.5px solid var(--input-border);font-size:12.5px;font-weight:600;color:var(--text2);text-decoration:none;transition:.12s}
.o-tabs a.on{border-color:var(--text);color:var(--text);background:var(--input-bg)}
.o-tabs a:hover{border-color:var(--muted)}
.o-tip{font-size:11.5px;color:var(--muted);background:var(--input-bg);border:1px solid var(--input-border);border-radius:7px;padding:6px 12px}
.o-sn{display:flex;align-items:center;gap:6px}
.o-copy{border:none;background:transparent;color:var(--muted);cursor:pointer;font-size:11px;padding:2px 4px;border-radius:4px}
.o-copy:hover{color:var(--text);background:var(--input-border)}
.o-prod b{display:block;font-size:12.5px}
.o-prod small{color:var(--muted);font-size:10.5px}
.o-num small{display:block;color:var(--muted);font-size:10.5px}
th.o-chk,td.o-chk{width:34px;text-align:center}
</style>

<div class="page-head">
    <div>
        <h2>🧾 商品订单</h2>
        <div class="sub">全部订单的筛选 · 导出 · 清理 · 销毁</div>
    </div>
</div>

<div class="stat-grid">
    <div class="stat"><div class="s-label">订单数量</div><div class="s-val"><?= (int)$stats['count'] ?></div><div class="s-sub">当前筛选范围内</div></div>
    <div class="stat"><div class="s-label">订单金额(已支付)</div><div class="s-val v-ok">¥<?= e(nf($stats['paid'])) ?></div><div class="s-sub">实时汇总筛选结果</div></div>
    <div class="stat"><div class="s-label">待支付金额</div><div class="s-val v-warn">¥<?= e(nf($stats['pending'])) ?></div><div class="s-sub">未完成订单合计</div></div>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px">
        <div style="display:flex;gap:9px;flex-wrap:wrap">
            <button class="btn sm" data-clean="expired" data-confirm="确定删除全部「已过期」订单? 此操作不可恢复">🧹 一键清理无用订单</button>
            <a class="btn sm green" href="<?= e($exportUrl) ?>">⬇ 导出筛选订单</a>
            <button class="btn sm red" id="destroyBtn" disabled data-confirm="⚠ 将销毁选中的订单(含已完成订单的发货记录)! 此操作不可恢复, 确定继续?">🗑 销毁选中订单</button>
        </div>
        <span class="o-tip">Tips: 上方订单数据会根据下方的查询条件进行筛选显示</span>
    </div>

    <form method="get" action="<?= site_url('admin.php') ?>">
        <input type="hidden" name="s" value="/orders">
        <div class="o-filters">
            <div class="of"><label>订单号</label><input type="text" name="sn" value="<?= e(arr_get($_GET, 'sn')) ?>" placeholder="完整或前缀"></div>
            <div class="of"><label>商品ID</label><input type="number" name="product_id" value="<?= e(arr_get($_GET, 'product_id')) ?>"></div>
            <div class="of"><label>卡密信息(模糊)</label><input type="text" name="card" value="<?= e(arr_get($_GET, 'card')) ?>" placeholder="发货内容关键字"></div>
            <div class="of"><label>联系方式</label><input type="text" name="contact" value="<?= e(arr_get($_GET, 'contact')) ?>"></div>
            <div class="of"><label>支付状态</label>
                <select name="status">
                    <option value="">全部</option>
                    <?php foreach ($stMap as $k => $v): ?><option value="<?= $k ?>" <?= $curStatus !== '' && (int)$curStatus === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="of"><label>支付方式</label>
                <select name="plugin">
                    <option value="">全部</option>
                    <?php foreach ($plugins as $p): ?><option value="<?= e($p) ?>" <?= arr_get($_GET, 'plugin') === $p ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="of"><label>IP地址</label><input type="text" name="ip" value="<?= e(arr_get($_GET, 'ip')) ?>"></div>
            <div class="of"><label>会员ID, 0=游客</label><input type="text" name="user_id" value="<?= e(arr_get($_GET, 'user_id')) ?>"></div>
            <div class="of"><label>从下单时间</label><input type="datetime-local" name="date_from" value="<?= e(arr_get($_GET, 'date_from')) ?>"></div>
            <div class="of"><label>到下单时间</label><input type="datetime-local" name="date_to" value="<?= e(arr_get($_GET, 'date_to')) ?>"></div>
            <div class="of"><label>&nbsp;</label><button class="btn" type="submit" style="width:100%">🔍 查询</button></div>
        </div>
    </form>

    <div class="o-tabs">
        <a href="<?= e($tabLink('')) ?>" class="<?= $curStatus === '' ? 'on' : '' ?>">全部</a>
        <a href="<?= e($tabLink('1')) ?>" class="<?= $curStatus === '1' ? 'on' : '' ?>">已支付</a>
        <a href="<?= e($tabLink('0')) ?>" class="<?= $curStatus === '0' ? 'on' : '' ?>">未支付</a>
    </div>
</div>

<div class="card">
    <table class="tb" id="orderTable">
        <thead>
        <tr>
            <th class="o-chk"><input type="checkbox" id="chkAll" style="width:auto"></th>
            <th>订单号 / 时间</th>
            <th>客户</th>
            <th>商品</th>
            <th>数量 / 金额</th>
            <th>支付方式</th>
            <th>支付状态</th>
            <th>发货状态</th>
            <th>操作</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($list as $o): $sid = (int)$o['status']; $delivered = trim((string)$o['cards_content']) !== ''; ?>
            <tr>
                <td class="o-chk"><input type="checkbox" class="row-chk" value="<?= (int)$o['id'] ?>" style="width:auto"></td>
                <td>
                    <div class="o-sn"><span class="mono"><?= e($o['sn']) ?></span><button class="o-copy" data-copy-sn="<?= e($o['sn']) ?>" title="复制订单号">⧉</button></div>
                    <small class="dim"><?= e(date('Y-m-d H:i', $o['created_at'])) ?></small>
                </td>
                <td>
                    <div><?= e($o['contact'] !== '' ? $o['contact'] : '—') ?></div>
                    <?php if ((int)$o['user_id'] > 0): ?><span class="tag blue">会员<?= $o['member_name'] !== '' && $o['member_name'] !== null ? ' · ' . e($o['member_name']) : ' #' . (int)$o['user_id'] ?></span><?php else: ?><span class="tag">游客</span><?php endif; ?>
                </td>
                <td class="o-prod"><b><?= e($o['product_name']) ?></b><small>ID <?= (int)$o['product_id'] ?></small></td>
                <td class="o-num">× <?= (int)$o['num'] ?><small>¥<?= e(nf($o['total'])) ?></small></td>
                <td class="dim"><?= e($o['pay_plugin'] !== '' ? $o['pay_plugin'] : '—') ?></td>
                <td><span class="tag <?= $sid === 1 ? 'ok' : ($sid === 0 ? 'warn' : 'bad') ?>"><?= $stMap[$sid] ?? '未知' ?></span></td>
                <td><?php if ($delivered): ?><span class="tag ok">已发货</span><?php elseif ($sid === 3): ?><span class="tag warn">待处理</span><?php elseif ($sid === 1): ?><span class="tag warn">未发货</span><?php else: ?><span class="tag">—</span><?php endif; ?></td>
                <td class="actions">
                    <a class="btn sm gray" href="<?= au('order_detail', ['id' => $o['id']]) ?>">详情</a>
                    <?php if ($sid === 3): ?><button class="btn sm green" data-deliver="<?= (int)$o['id'] ?>">补发</button><?php endif; ?>
                    <button class="btn sm red" data-del="<?= (int)$o['id'] ?>" data-confirm="确定删除订单 <?= e($o['sn']) ?> ?<?= $sid === 1 ? '\\n已完成订单的发货记录将一并删除!' : '' ?>">删除</button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="9" class="dim" style="text-align:center;padding:26px">暂无订单</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php if ($pages > 1): ?>
        <div class="pager">
            <?php for ($i = 1; $i <= min($pages, 12); $i++): ?>
                <a class="<?= $i === $page ? 'on' : '' ?>" href="<?= e($pageLink($i)) ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<script>
/* 全选/反选 + 销毁按钮状态 */
var chkAll = document.getElementById('chkAll');
function rowChks() { return Array.prototype.slice.call(document.querySelectorAll('.row-chk')); }
function syncDestroy() {
    var n = rowChks().filter(function (c) { return c.checked; }).length;
    var b = document.getElementById('destroyBtn');
    if (b) { b.disabled = n === 0; b.textContent = n > 0 ? '🗑 销毁选中订单(' + n + ')' : '🗑 销毁选中订单'; }
}
if (chkAll) chkAll.addEventListener('change', function () {
    rowChks().forEach(function (c) { c.checked = chkAll.checked; });
    syncDestroy();
});
document.addEventListener('change', function (ev) {
    if (ev.target.classList && ev.target.classList.contains('row-chk')) syncDestroy();
});
function yfPost(url, fd) {
    fd.append('_csrf', '<?= e(csrf_token()) ?>');
    return fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); });
}
document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-deliver],[data-clean],[data-del],[data-copy-sn],#destroyBtn') : null;
    if (!t) return;
    var fd = new FormData();
    if (t.hasAttribute('data-copy-sn')) {
        var tmp = document.createElement('textarea');
        tmp.value = t.getAttribute('data-copy-sn');
        document.body.appendChild(tmp); tmp.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(tmp);
        t.textContent = '✓'; setTimeout(function () { t.textContent = '⧉'; }, 1200);
        return;
    }
    if (t.id === 'destroyBtn') {
        var ids = rowChks().filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
        if (!ids.length) return;
        ids.forEach(function (v) { fd.append('ids[]', v); });
        yfPost('<?= au('orders_destroy') ?>', fd).then(function (d) { kAlert(d.msg); if (d.code === 0) location.reload(); });
        return;
    }
    if (t.hasAttribute('data-del')) {
        fd.append('id', t.getAttribute('data-del'));
        yfPost('<?= au('order_del') ?>', fd).then(function (d) { kAlert(d.msg); if (d.code === 0) location.reload(); });
        return;
    }
    if (t.hasAttribute('data-clean')) {
        fd.append('scope', t.getAttribute('data-clean'));
        yfPost('<?= au('order_clean') ?>', fd).then(function (d) { kAlert(d.msg); if (d.code === 0) location.reload(); });
        return;
    }
    fd.append('id', t.getAttribute('data-deliver'));
    yfPost('<?= au('order_deliver') ?>', fd).then(function (d) { kAlert(d.msg); if (d.code === 0) location.reload(); });
});
</script>
