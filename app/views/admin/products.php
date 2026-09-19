<?php $activeMenu = 'products';
$curStatus = isset($_GET['status']) ? (string)$_GET['status'] : '';
$nameKw = isset($_GET['name']) ? (string)$_GET['name'] : '';
$catFilter = $catId > 0 ? (string)$catId : '';
$f = [];
foreach (['cat', 'name', 'status'] as $k) {
    $v = isset($_GET[$k]) ? (string)$_GET[$k] : '';
    if ($v !== '') $f[$k] = $v;
}
$tabLink = function ($status) use ($f) {
    $p = $f;
    if ($status !== '') $p['status'] = $status;
    return au('products', $p);
};
?>
<style>
.p-filters{display:flex;gap:9px;flex-wrap:wrap;align-items:flex-end}
.p-filters .pf label{display:block;font-size:11px;color:var(--muted);margin-bottom:4px;font-weight:600}
.p-filters input,.p-filters select{height:36px;padding:0 10px;font-size:12.5px}
.p-filters input[type=text]{width:220px}
.p-tabs-row{display:flex;gap:7px;margin-top:14px}
.p-tabs-row a{padding:6px 16px;border-radius:8px;border:1.5px solid var(--input-border);font-size:12.5px;font-weight:600;color:var(--text2);text-decoration:none;transition:.12s}
.p-tabs-row a.on{border-color:var(--text);color:var(--text);background:var(--input-bg)}
.p-tabs-row a:hover{border-color:var(--muted)}
th.p-chk,td.p-chk{width:34px;text-align:center}
.p-name b{display:block;font-size:13px;line-height:1.4}
.p-name small{color:var(--muted);font-size:10.5px}
.p-stock{font-size:12.5px}
.p-stock .add{color:var(--ok);font-weight:600;text-decoration:none;margin-left:3px}
.p-sales{font-size:12px;color:var(--text2)}
.p-periods{display:grid;grid-template-columns:repeat(4,minmax(44px,1fr));gap:2px;text-align:center}
.p-periods span{display:block;font-size:11.5px}
/* 开关 */
.sw{position:relative;display:inline-block;width:38px;height:21px;vertical-align:middle}
.sw input{opacity:0;width:0;height:0}
.sw i{position:absolute;cursor:pointer;inset:0;background:var(--input-border);border-radius:999px;transition:.18s}
.sw i::before{content:"";position:absolute;height:15px;width:15px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.18s}
.sw input:checked + i{background:var(--ok)}
.sw input:checked + i::before{transform:translateX(17px)}
/* 右侧抽屉 */
.p-mask{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:120;display:none;justify-content:flex-end}
.p-mask.on{display:flex}
.p-drawer{width:100%;max-width:470px;height:100%;background:var(--card);border-left:1px solid var(--input-border);display:flex;flex-direction:column;box-shadow:-18px 0 50px rgba(0,0,0,.3);animation:pin .18s ease}
@keyframes pin{from{transform:translateX(40px);opacity:.4}to{transform:none;opacity:1}}
.p-dt{display:flex;gap:2px;border-bottom:1px solid var(--input-border);overflow-x:auto;padding:0 8px}
.p-dt button{border:none;background:transparent;color:var(--muted);font-size:12.5px;font-weight:600;padding:13px 12px;cursor:pointer;border-bottom:2px solid transparent;font-family:inherit;white-space:nowrap}
.p-dt button.on{color:var(--text);border-bottom-color:var(--text)}
.p-dt .close{margin-left:auto;font-size:16px}
.p-db{flex:1;overflow-y:auto;padding:18px 20px;display:none}
.p-db.on{display:block}
.p-db label{display:block;font-size:11.5px;color:var(--muted);font-weight:600;margin-bottom:5px}
.p-db input[type=text],.p-db input[type=number],.p-db select,.p-db textarea{width:100%;margin-bottom:13px}
.p-db textarea{min-height:96px}
.p-df{padding:14px 20px;border-top:1px solid var(--input-border);display:flex;gap:9px;justify-content:flex-end}
.p-df .btn{min-width:100px}
</style>

<div class="page-head">
    <div>
        <h2>📦 商品管理</h2>
        <div class="sub">商品的上架 · 定价 · 库存 · 批量操作</div>
    </div>
</div>

<div class="stat-grid">
    <div class="stat"><div class="s-label">总商品</div><div class="s-val"><?= (int)$stats['total'] ?></div><div class="s-sub">全部商品合计</div></div>
    <div class="stat"><div class="s-label">已上架</div><div class="s-val v-ok"><?= (int)$stats['on'] ?></div><div class="s-sub">前台可见在售</div></div>
    <div class="stat"><div class="s-label">未上架</div><div class="s-val v-warn"><?= (int)$stats['off'] ?></div><div class="s-sub">下架/待上架</div></div>
    <div class="stat"><div class="s-label">总库存</div><div class="s-val v-primary"><?= (int)$stats['stock'] ?></div><div class="s-sub">未售卡密合计</div></div>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px">
        <div style="display:flex;gap:9px;flex-wrap:wrap">
            <button class="btn sm" id="addBtn">➕ 添加商品</button>
            <button class="btn sm green" id="onBtn" disabled data-op="on" data-confirm="确定上架选中的商品?">⬆ 上架选中商品</button>
            <button class="btn sm gray" id="offBtn" disabled data-op="off" data-confirm="确定下架选中的商品? 下架后前台不可购买">⬇ 下架选中商品</button>
            <button class="btn sm red" id="delBtn" disabled data-op="delete" data-confirm="⚠ 确定移除选中的商品? 有未售卡密的商品将跳过">🗑 移除选中商品</button>
        </div>
    </div>

    <form method="get" action="<?= site_url('admin.php') ?>">
        <input type="hidden" name="s" value="/products">
        <div class="p-filters">
            <div class="pf"><label>商品分类</label>
                <select name="cat">
                    <option value="">全部</option>
                    <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $catFilter === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="pf"><label>商品名称(模糊搜索)</label><input type="text" name="name" value="<?= e($nameKw) ?>"></div>
            <div class="pf"><button class="btn" type="submit" style="height:36px">🔍 查询</button></div>
        </div>
    </form>

    <div class="p-tabs-row">
        <a href="<?= e($tabLink('')) ?>" class="<?= $curStatus === '' ? 'on' : '' ?>">全部</a>
        <a href="<?= e($tabLink('1')) ?>" class="<?= $curStatus === '1' ? 'on' : '' ?>">已上架</a>
        <a href="<?= e($tabLink('0')) ?>" class="<?= $curStatus === '0' ? 'on' : '' ?>">已下架</a>
    </div>
</div>

<div class="card">
    <table class="tb" id="productTable">
        <thead>
        <tr>
            <th class="p-chk"><input type="checkbox" id="chkAll" style="width:auto"></th>
            <th>商品</th>
            <th>分类</th>
            <th>库存</th>
            <th>零售价</th>
            <th>今日</th>
            <th>昨日</th>
            <th>本周</th>
            <th>全部</th>
            <th>排序</th>
            <th>状态</th>
            <th>操作</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($list as $p): $sid = (int)$p['status']; $s = isset($salesMap[(int)$p['id']]) ? $salesMap[(int)$p['id']] : ['today' => 0, 'yesterday' => 0, 'week' => 0]; ?>
            <tr>
                <td class="p-chk"><input type="checkbox" class="row-chk" value="<?= (int)$p['id'] ?>" style="width:auto"></td>
                <td class="p-name"><b><?= e($p['name']) ?></b><small>ID <?= (int)$p['id'] ?> · 限购 <?= (int)$p['min_num'] ?>-<?= (int)$p['max_num'] ?><?= !empty($p['group_name']) ? ' · 分组 ' . e($p['group_name']) : '' ?></small></td>
                <td class="dim"><?= e($p['cat_name'] ?: '未分类') ?></td>
                <td class="p-stock"><?= (int)$p['stock'] ?><a class="add" href="<?= au('cards', ['product_id' => $p['id']]) ?>">加卡</a></td>
                <td><b>¥<?= e(nf($p['price'])) ?></b></td>
                <td class="p-sales"><?= (int)$s['today'] ?></td>
                <td class="p-sales"><?= (int)$s['yesterday'] ?></td>
                <td class="p-sales"><?= (int)$s['week'] ?></td>
                <td class="p-sales"><?= (int)$p['sales'] ?></td>
                <td><?= (int)$p['sort'] ?></td>
                <td>
                    <label class="sw" title="点击切换上架/下架">
                        <input type="checkbox" class="prod-sw" data-id="<?= (int)$p['id'] ?>" <?= $sid === 1 ? 'checked' : '' ?>>
                        <i></i>
                    </label>
                </td>
                <td class="actions">
                    <button class="btn sm gray" data-edit='<?= e(json_encode(['id' => (int)$p['id'], 'category_id' => (int)$p['category_id'], 'group_id' => (int)$p['group_id'], 'icon' => (string)$p['icon'], 'name' => $p['name'], 'price' => nf($p['price']), 'min_num' => (int)$p['min_num'], 'max_num' => (int)$p['max_num'], 'description' => (string)$p['description'], 'sort' => (int)$p['sort'], 'status' => $sid], JSON_UNESCAPED_UNICODE)) ?>'>✏ 编辑</button>
                    <a class="btn sm gray" href="<?= au('cards', ['product_id' => $p['id']]) ?>">卡密</a>
                    <button class="btn sm red" data-del="<?= (int)$p['id'] ?>" data-confirm="确定移除商品 <?= e($p['name']) ?> ?<?= (int)$p['stock'] > 0 ? ' 该商品还有 ' . (int)$p['stock'] . ' 张未售卡密, 需先清空库存!' : '' ?>">移除</button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="12"><div style="text-align:center;padding:34px 0;color:var(--muted)"><div style="font-size:34px;opacity:.55;margin-bottom:6px">📦</div>暂无商品, 点击右上角「添加商品」创建</div></td></tr><?php endif; ?>
        </tbody>
    </table>
    <div style="font-size:12px;color:var(--muted);padding:10px 2px 0">共 <?= count($list) ?> 条</div>
</div>

<!-- 添加/编辑抽屉 -->
<div class="p-mask" id="pMask">
    <aside class="p-drawer">
        <input type="hidden" id="saveId" value="0">
        <div class="p-dt" id="drawerTabs">
            <button data-pane="base" class="on">➕ 基本信息</button>
            <button data-pane="intro">📄 商品介绍</button>
            <button data-pane="limit">🛒 购买限制</button>
            <button data-pane="cards">🔑 卡密导入</button>
            <button class="close" id="drawerClose" title="关闭">✕</button>
        </div>
        <div class="p-db on" data-pane="base">
            <label>商品图标(可选, 上传后前台商品卡与详情页展示; 不传则用默认样式)</label>
            <input type="hidden" id="f-icon_current" value="">
            <input type="hidden" id="f-icon_reset" value="0">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:13px">
                <span id="iconPrev" style="width:56px;height:56px;border-radius:10px;border:1.5px dashed var(--input-border);display:inline-flex;align-items:center;justify-content:center;font-size:24px;overflow:hidden;flex:none;background:var(--input-bg)">🎁</span>
                <div style="display:flex;flex-direction:column;gap:6px">
                    <input type="file" id="f-icon_file" accept=".jpg,.jpeg,.png,.webp,.gif" style="font-size:12px;max-width:230px">
                    <button type="button" class="btn sm gray" id="iconResetBtn" style="align-self:flex-start;display:none">↺ 恢复默认图标</button>
                </div>
            </div>
            <label>商品分类</label>
            <select id="f-category_id">
                <option value="0">未分类</option>
                <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
            <label>商品分组(可在「会员等级」页设置分组可见的最低等级)</label>
            <select id="f-group_id">
                <option value="0">不分组(所有人可见)</option>
                <?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>"><?= e($g['name']) ?><?= (int)$g['min_level'] > 0 ? '(需LV' . (int)$g['min_level'] . '+' : '' ?><?= (int)$g['min_level'] > 0 ? ')' : '' ?></option><?php endforeach; ?>
            </select>
            <label>商品名称 *</label>
            <input type="text" id="f-name" maxlength="100" placeholder="如 游戏充值月卡">
            <label>零售价(元) *</label>
            <input type="number" id="f-price" step="0.01" min="0" value="0.00">
            <label>排序(越小越靠前)</label>
            <input type="number" id="f-sort" value="0">
            <label style="display:flex;align-items:center;gap:8px;color:var(--text);font-size:13px;font-weight:600">
                <span class="sw" style="display:inline-block"><input type="checkbox" id="f-status" checked><i></i></span> 立即上架(前台可见)
            </label>
        </div>
        <div class="p-db" data-pane="intro">
            <label>商品介绍(购买页展示, 支持换行)</label>
            <textarea id="f-description" rows="8" placeholder="商品说明、使用方式、有效期等"></textarea>
        </div>
        <div class="p-db" data-pane="limit">
            <label>最少购买数量</label>
            <input type="number" id="f-min_num" value="1" min="1">
            <label>最多购买数量</label>
            <input type="number" id="f-max_num" value="5" min="1">
        </div>
        <div class="p-db" data-pane="cards">
            <label>卡密导入(每行一张; 编辑已有商品时填入可追加库存)</label>
            <textarea id="f-cards_import" rows="10" placeholder="卡密1&#10;卡密2&#10;..."></textarea>
        </div>
        <div class="p-df">
            <button class="btn" id="drawerSave" style="min-width:120px">💾 保存</button>
            <button class="btn gray" id="drawerCancel">✕ 取消</button>
        </div>
    </aside>
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
    ['onBtn', 'offBtn', 'delBtn'].forEach(function (id) {
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
    if (ev.target.classList && ev.target.classList.contains('prod-sw')) {
        var fd = new FormData();
        fd.append('id', ev.target.getAttribute('data-id'));
        yfPost('<?= au('product_toggle') ?>', fd).then(function (d) {
            if (d.code !== 0) alert(d.msg);
            location.reload();
        });
    }
});
/* 抽屉 */
var mask = document.getElementById('pMask');
function openDrawer(data) {
    document.getElementById('f-category_id').value = data ? data.category_id : 0;
    document.getElementById('f-group_id').value = data ? (data.group_id || 0) : 0;
    // 图标预览与状态
    var icon = data ? (data.icon || '') : '';
    document.getElementById('f-icon_current').value = icon;
    document.getElementById('f-icon_reset').value = '0';
    document.getElementById('f-icon_file').value = '';
    var prev = document.getElementById('iconPrev');
    var rst = document.getElementById('iconResetBtn');
    if (icon !== '') {
        prev.innerHTML = '<img src="<?= e(site_url("")) ?>' + icon + '" style="width:100%;height:100%;object-fit:cover" alt="">';
        if (rst) rst.style.display = 'inline-flex';
    } else {
        prev.innerHTML = '🎁';
        if (rst) rst.style.display = 'none';
    }
    document.getElementById('f-name').value = data ? data.name : '';
    document.getElementById('f-price').value = data ? data.price : '0.00';
    document.getElementById('f-sort').value = data ? data.sort : 0;
    document.getElementById('f-status').checked = data ? data.status === 1 : true;
    document.getElementById('f-description').value = data ? data.description : '';
    document.getElementById('f-min_num').value = data ? data.min_num : 1;
    document.getElementById('f-max_num').value = data ? data.max_num : 5;
    document.getElementById('f-cards_import').value = '';
    mask.classList.add('on');
    switchPane('base');
    document.getElementById('f-name').focus();
}
function switchPane(name) {
    document.querySelectorAll('#drawerTabs button[data-pane]').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-pane') === name); });
    document.querySelectorAll('.p-db').forEach(function (p) { p.classList.toggle('on', p.getAttribute('data-pane') === name); });
}
document.getElementById('drawerTabs').addEventListener('click', function (ev) {
    var b = ev.target.closest('button[data-pane]');
    if (b) switchPane(b.getAttribute('data-pane'));
});
document.getElementById('drawerClose').addEventListener('click', function () { mask.classList.remove('on'); });
document.getElementById('drawerCancel').addEventListener('click', function () { mask.classList.remove('on'); });
document.getElementById('addBtn').addEventListener('click', function () {
    openDrawer(null);
    document.getElementById('saveId').value = 0;
});
/* 图标选择预览 + 恢复默认 */
document.getElementById('f-icon_file').addEventListener('change', function () {
    var f = this.files && this.files[0];
    if (!f) return;
    document.getElementById('f-icon_reset').value = '0';
    document.getElementById('iconPrev').innerHTML = '<img src="' + URL.createObjectURL(f) + '" style="width:100%;height:100%;object-fit:cover" alt="">';
});
document.getElementById('iconResetBtn').addEventListener('click', function () {
    document.getElementById('f-icon_file').value = '';
    document.getElementById('f-icon_reset').value = '1';
    document.getElementById('iconPrev').innerHTML = '🎁';
    this.style.display = 'none';
});
mask.addEventListener('click', function (ev) { if (ev.target === mask) mask.classList.remove('on'); });
document.getElementById('drawerSave').addEventListener('click', function () {
    var fd = new FormData();
    fd.append('id', document.getElementById('saveId').value);
    fd.append('category_id', document.getElementById('f-category_id').value);
    fd.append('group_id', document.getElementById('f-group_id').value);
    fd.append('name', document.getElementById('f-name').value.trim());
    fd.append('price', document.getElementById('f-price').value);
    fd.append('sort', document.getElementById('f-sort').value || '0');
    fd.append('status', document.getElementById('f-status').checked ? '1' : '0');
    fd.append('description', document.getElementById('f-description').value);
    fd.append('min_num', document.getElementById('f-min_num').value || '1');
    fd.append('max_num', document.getElementById('f-max_num').value || '1');
    var iconFile = document.getElementById('f-icon_file').files[0];
    if (iconFile) fd.append('icon_file', iconFile);
    fd.append('icon_reset', document.getElementById('f-icon_reset').value);
    var cards = document.getElementById('f-cards_import').value.trim();
    if (cards !== '') fd.append('cards_import', cards);
    yfPost('<?= au('product_save') ?>', fd).then(function (d) {
        alert(d.msg);
        if (d.code === 0) location.reload();
    });
});
/* 行内动作 */
document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-edit],[data-copy],[data-del],[data-op]') : null;
    if (!t) return;
    if (t.hasAttribute('data-edit')) {
        var d = JSON.parse(t.getAttribute('data-edit'));
        openDrawer(d);
        document.getElementById('saveId').value = d.id;
        return;
    }
    if (t.hasAttribute('data-copy')) {
        var d2 = JSON.parse(t.getAttribute('data-copy'));
        openDrawer(d2);
        document.getElementById('saveId').value = 0;
        document.getElementById('f-name').value = d2.name + ' (副本)';
        return;
    }
    var fd = new FormData();
    if (t.hasAttribute('data-op')) {
        var ids = rowChks().filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
        if (!ids.length) return;
        if (!confirm(t.getAttribute('data-confirm') || '确定执行该操作?')) return;
        ids.forEach(function (v) { fd.append('ids[]', v); });
        fd.append('op', t.getAttribute('data-op'));
        yfPost('<?= au('products_batch') ?>', fd).then(function (d) { alert(d.msg); if (d.code === 0) location.reload(); });
        return;
    }
    if (!confirm(t.getAttribute('data-confirm') || '确定执行该操作?')) return;
    fd.append('id', t.getAttribute('data-del'));
    yfPost('<?= au('product_del') ?>', fd).then(function (d) { alert(d.msg); if (d.code === 0) location.reload(); });
});
</script>
