<?php $activeMenu = 'categories';
$curStatus = isset($_GET['status']) ? (string)$_GET['status'] : '';
$nameKw = isset($_GET['name']) ? (string)$_GET['name'] : '';
$frontBase = site_url('index.php');
?>
<style>
.c-filters{display:flex;gap:9px;flex-wrap:wrap;align-items:flex-end}
.c-filters .cf label{display:block;font-size:11px;color:var(--muted);margin-bottom:4px;font-weight:600}
.c-filters input{height:36px;padding:0 10px;font-size:12.5px;width:200px}
.c-tabs{display:flex;gap:7px;margin-top:14px}
.c-tabs a{padding:6px 16px;border-radius:8px;border:1.5px solid var(--input-border);font-size:12.5px;font-weight:600;color:var(--text2);text-decoration:none;transition:.12s}
.c-tabs a.on{border-color:var(--text);color:var(--text);background:var(--input-bg)}
.c-tabs a:hover{border-color:var(--muted)}
th.c-chk,td.c-chk{width:34px;text-align:center}
.c-name{display:flex;align-items:center;gap:9px}
.c-name .ico{width:32px;height:32px;border-radius:8px;background:var(--input-bg);border:1px solid var(--input-border);display:inline-flex;align-items:center;justify-content:center;font-size:16px;flex:none}
.c-copy{border:1px solid var(--input-border);background:transparent;color:var(--text2);border-radius:7px;padding:4px 10px;font-size:11.5px;cursor:pointer;display:inline-flex;align-items:center;gap:5px;font-family:inherit}
.c-copy:hover{border-color:var(--muted);color:var(--text)}
/* 开关 */
.sw{position:relative;display:inline-block;width:38px;height:21px;vertical-align:middle}
.sw input{opacity:0;width:0;height:0}
.sw i{position:absolute;cursor:pointer;inset:0;background:var(--input-border);border-radius:999px;transition:.18s}
.sw i::before{content:"";position:absolute;height:15px;width:15px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.18s}
.sw input:checked + i{background:var(--ok)}
.sw input:checked + i::before{transform:translateX(17px)}
/* 弹窗 */
.c-mask{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:120;display:none;align-items:flex-start;justify-content:center;padding:60px 16px}
.c-mask.on{display:flex}
.c-modal{background:var(--card);border:1px solid var(--input-border);border-radius:14px;width:100%;max-width:430px;box-shadow:var(--shadow-lg,0 18px 50px rgba(0,0,0,.35));overflow:hidden}
.c-modal .m-head{padding:15px 20px;font-weight:700;font-size:14.5px;border-bottom:1px solid var(--input-border);display:flex;justify-content:space-between;align-items:center}
.c-modal .m-head .x{cursor:pointer;color:var(--muted);font-size:17px;background:none;border:none}
.c-modal .m-body{padding:18px 20px;display:flex;flex-direction:column;gap:13px}
.c-modal .m-body label{display:block;font-size:11.5px;color:var(--muted);font-weight:600;margin-bottom:5px}
.c-modal .m-body input[type=text],.c-modal .m-body input[type=number]{width:100%;height:40px;padding:0 12px;font-size:13px}
.c-modal .m-foot{padding:14px 20px;border-top:1px solid var(--input-border);display:flex;gap:9px;justify-content:center}
.c-modal .m-foot .btn{min-width:96px}
</style>

<div class="page-head">
    <div>
        <h2>🗂 分类管理</h2>
        <div class="sub">商品分类的新增 · 排序 · 启停 · 移除</div>
    </div>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px">
        <div style="display:flex;gap:9px;flex-wrap:wrap">
            <button class="btn sm" id="addBtn">➕ 添加分类</button>
            <button class="btn sm green" id="enableBtn" disabled data-op="enable" data-confirm="确定启用选中的分类?">▶ 启用选中分类</button>
            <button class="btn sm gray" id="disableBtn" disabled data-op="disable" data-confirm="确定停用选中的分类? 停用后前台不再显示">⏸ 停用选中分类</button>
            <button class="btn sm red" id="delBtn" disabled data-op="delete" data-confirm="⚠ 确定移除选中的分类? 分类下有商品时将跳过">🗑 移除选中分类</button>
        </div>
    </div>

    <form method="get" action="<?= site_url('admin.php') ?>">
        <input type="hidden" name="s" value="/categories">
        <div class="c-filters">
            <div class="cf"><label>分类名称</label><input type="text" name="name" value="<?= e($nameKw) ?>" placeholder="名称关键字"></div>
            <div class="cf"><button class="btn" type="submit" style="height:36px">🔍 查询</button></div>
        </div>
    </form>

    <div class="c-tabs">
        <a href="<?= e(au('categories', array_filter(['name' => $nameKw]))) ?>" class="<?= $curStatus === '' ? 'on' : '' ?>">全部</a>
        <a href="<?= e(au('categories', ['name' => $nameKw, 'status' => '1'])) ?>" class="<?= $curStatus === '1' ? 'on' : '' ?>">已启用</a>
        <a href="<?= e(au('categories', ['name' => $nameKw, 'status' => '0'])) ?>" class="<?= $curStatus === '0' ? 'on' : '' ?>">未启用</a>
    </div>
</div>

<div class="card">
    <table class="tb" id="catTable">
        <thead>
        <tr>
            <th class="c-chk"><input type="checkbox" id="chkAll" style="width:auto"></th>
            <th>分类</th>
            <th>排序(越小越前)</th>
            <th>商品数</th>
            <th>前台链接</th>
            <th>状态</th>
            <th>操作</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($list as $c): $sid = (int)$c['status']; ?>
            <tr>
                <td class="c-chk"><input type="checkbox" class="row-chk" value="<?= (int)$c['id'] ?>" style="width:auto"></td>
                <td>
                    <div class="c-name">
                        <span class="ico"><?= e($c['icon'] ?: '🗂') ?></span>
                        <b><?= e($c['name']) ?></b>
                    </div>
                </td>
                <td><?= (int)$c['sort'] ?></td>
                <td><?= (int)$c['products_count'] ?></td>
                <td><button class="c-copy" data-copy-link="<?= (int)$c['id'] ?>" data-link="<?= e($frontBase . '?s=/home/index&cat=' . (int)$c['id']) ?>">⧉ 复制链接</button></td>
                <td>
                    <label class="sw" title="点击切换启用/停用">
                        <input type="checkbox" class="cat-sw" data-id="<?= (int)$c['id'] ?>" <?= $sid === 1 ? 'checked' : '' ?>>
                        <i></i>
                    </label>
                </td>
                <td class="actions">
                    <button class="btn sm gray" data-edit='<?= e(json_encode(['id' => (int)$c['id'], 'name' => $c['name'], 'icon' => $c['icon'], 'sort' => (int)$c['sort'], 'status' => $sid], JSON_UNESCAPED_UNICODE)) ?>'>✏ 编辑</button>
                    <button class="btn sm red" data-del="<?= (int)$c['id'] ?>" data-confirm="确定移除分类 <?= e($c['name']) ?> ?<?= (int)$c['products_count'] > 0 ? ' 该分类下有 ' . (int)$c['products_count'] . ' 个商品, 需先移除商品!' : '' ?>">移除</button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="7"><div style="text-align:center;padding:34px 0;color:var(--muted)"><div style="font-size:34px;opacity:.55;margin-bottom:6px">🗂</div>暂无数据</div></td></tr><?php endif; ?>
        </tbody>
    </table>
    <div style="font-size:12px;color:var(--muted);padding:10px 2px 0">共 <?= count($list) ?> 条</div>
</div>

<!-- 添加/编辑弹窗 -->
<div class="c-mask" id="catMask">
    <div class="c-modal">
        <div class="m-head"><span id="modalTitle">添加分类</span><button class="x" id="modalClose" type="button">✕</button></div>
        <div class="m-body">
            <input type="hidden" id="f-id" value="0">
            <div><label>分类名称 *</label><input type="text" id="f-name" maxlength="50" placeholder="如 游戏充值 / 软件激活码"></div>
            <div><label>图标(可选, 输入一个表情或字符)</label><input type="text" id="f-icon" maxlength="4" placeholder="如 🎮"></div>
            <div><label>排序(越小越靠前)</label><input type="number" id="f-sort" value="0"></div>
            <div><label style="display:flex;align-items:center;gap:8px;margin:0;color:var(--text);font-size:13px;font-weight:600">
                <span class="sw" style="margin:0;display:inline-block"><input type="checkbox" id="f-status" checked><i></i></span> 启用(前台显示)
            </label></div>
        </div>
        <div class="m-foot">
            <button class="btn" id="modalSave" style="min-width:110px">💾 保存</button>
            <button class="btn gray" id="modalCancel">✕ 取消</button>
        </div>
    </div>
</div>

<script>
var catFrontBase = '<?= e($frontBase) ?>';
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
    ['enableBtn', 'disableBtn', 'delBtn'].forEach(function (id) {
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
    if (ev.target.classList && ev.target.classList.contains('cat-sw')) {
        var fd = new FormData();
        fd.append('id', ev.target.getAttribute('data-id'));
        yfPost('<?= au('category_toggle') ?>', fd).then(function (d) {
            if (d.code !== 0) { kAlert(d.msg); }
            location.reload();
        });
    }
});
/* 弹窗 */
var mask = document.getElementById('catMask');
function openModal(data) {
    document.getElementById('modalTitle').textContent = data ? '编辑分类' : '添加分类';
    document.getElementById('f-id').value = data ? data.id : 0;
    document.getElementById('f-name').value = data ? data.name : '';
    document.getElementById('f-icon').value = data ? data.icon : '';
    document.getElementById('f-sort').value = data ? data.sort : 0;
    document.getElementById('f-status').checked = data ? data.status === 1 : true;
    mask.classList.add('on');
    document.getElementById('f-name').focus();
}
document.getElementById('addBtn').addEventListener('click', function () { openModal(null); });
document.getElementById('modalClose').addEventListener('click', function () { mask.classList.remove('on'); });
document.getElementById('modalCancel').addEventListener('click', function () { mask.classList.remove('on'); });
mask.addEventListener('click', function (ev) { if (ev.target === mask) mask.classList.remove('on'); });
document.getElementById('modalSave').addEventListener('click', function () {
    var fd = new FormData();
    fd.append('id', document.getElementById('f-id').value);
    fd.append('name', document.getElementById('f-name').value.trim());
    fd.append('icon', document.getElementById('f-icon').value.trim());
    fd.append('sort', document.getElementById('f-sort').value || '0');
    fd.append('status', document.getElementById('f-status').checked ? '1' : '0');
    yfPost('<?= au('category_save') ?>', fd).then(function (d) {
        kAlert(d.msg);
        if (d.code === 0) location.reload();
    });
});
/* 行内动作 */
document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-edit],[data-del],[data-copy-link],[data-op]') : null;
    if (!t) return;
    if (t.hasAttribute('data-edit')) {
        openModal(JSON.parse(t.getAttribute('data-edit')));
        return;
    }
    if (t.hasAttribute('data-copy-link')) {
        var tmp = document.createElement('textarea');
        tmp.value = t.getAttribute('data-link');
        document.body.appendChild(tmp); tmp.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(tmp);
        t.textContent = '✓ 已复制';
        var b = t;
        setTimeout(function () { b.innerHTML = '⧉ 复制链接'; }, 1300);
        return;
    }
    var fd = new FormData();
    if (t.hasAttribute('data-op')) {
        var ids = rowChks().filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
        if (!ids.length) return;
        ids.forEach(function (v) { fd.append('ids[]', v); });
        fd.append('op', t.getAttribute('data-op'));
        yfPost('<?= au('categories_batch') ?>', fd).then(function (d) { kAlert(d.msg); if (d.code === 0) location.reload(); });
        return;
    }
    fd.append('id', t.getAttribute('data-del'));
    yfPost('<?= au('category_del') ?>', fd).then(function (d) { kAlert(d.msg); if (d.code === 0) location.reload(); });
});
</script>
