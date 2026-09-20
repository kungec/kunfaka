<?php $activeMenu = 'users'; $pages = max(1, (int)ceil($total / $per));
$curStatus = isset($_GET['status']) ? (string)$_GET['status'] : '';
$curLevel = isset($_GET['level']) ? (string)$_GET['level'] : '';
/* 保留当前筛选条件 */
$f = [];
foreach (['username', 'uid', 'email', 'reg_ip', 'status', 'level'] as $k) {
    $v = isset($_GET[$k]) ? (string)$_GET[$k] : '';
    if ($v !== '') $f[$k] = $v;
}
$tabLink = function ($status) use ($f) {
    $p = $f;
    if ($status !== '') $p['status'] = $status;
    return au('users', $p);
};
$pageLink = function ($i) use ($f) {
    $p = $f;
    if ($i > 1) $p['page'] = $i;
    return au('users', $p);
};
?>
<style>
.u-filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:9px 10px}
.u-filters .uf label{display:block;font-size:11px;color:var(--muted);margin-bottom:4px;font-weight:600}
.u-filters input{width:100%;height:36px;padding:0 10px;font-size:12.5px}
.u-tabs{display:flex;gap:7px;margin:14px 0 0}
.u-tabs a{padding:6px 16px;border-radius:8px;border:1.5px solid var(--input-border);font-size:12.5px;font-weight:600;color:var(--text2);text-decoration:none;transition:.12s}
.u-tabs a.on{border-color:var(--text);color:var(--text);background:var(--input-bg)}
.u-tabs a:hover{border-color:var(--muted)}
th.u-chk,td.u-chk{width:34px;text-align:center}
.u-name b{display:block;font-size:13px}
.u-name small{color:var(--muted);font-size:10.5px}
.u-empty{text-align:center;padding:34px 0;color:var(--muted)}
.u-empty .ico{font-size:34px;margin-bottom:6px;opacity:.55}
.u-total{font-size:12px;color:var(--muted);padding:10px 2px 0}
/* 等级弹窗 */
.lvl-mask{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:120;display:none;align-items:flex-start;justify-content:center;padding:100px 16px}
.lvl-mask.on{display:flex}
.lvl-modal{background:var(--card);border:1px solid var(--input-border);border-radius:14px;width:100%;max-width:380px;overflow:hidden}
.lvl-modal .m-head{padding:15px 20px;font-weight:700;font-size:14.5px;border-bottom:1px solid var(--input-border);display:flex;justify-content:space-between}
.lvl-modal .m-head .x{cursor:pointer;color:var(--muted);font-size:17px;background:none;border:none}
.lvl-modal .m-body{padding:18px 20px}
.lvl-modal .m-body label{display:block;font-size:11.5px;color:var(--muted);font-weight:600;margin-bottom:5px}
.lvl-modal .m-body select{width:100%;height:40px;padding:0 12px;font-size:13px}
.lvl-modal .m-foot{padding:14px 20px;border-top:1px solid var(--input-border);display:flex;gap:9px;justify-content:center}
</style>

<div class="page-head">
    <div>
        <h2>👥 会员管理</h2>
        <div class="sub">前台注册会员的查询 · 批量启用/禁用 · 移除</div>
    </div>
</div>

<div class="stat-grid">
    <div class="stat"><div class="s-label">总用户</div><div class="s-val"><?= (int)$stats['total'] ?></div><div class="s-sub">全部注册会员</div></div>
    <div class="stat"><div class="s-label">今日新增</div><div class="s-val v-ok"><?= (int)$stats['today'] ?></div><div class="s-sub">今日注册</div></div>
    <div class="stat"><div class="s-label">已封禁</div><div class="s-val v-warn"><?= (int)$stats['banned'] ?></div><div class="s-sub">禁用状态会员</div></div>
    <div class="stat"><div class="s-label">会员成交订单</div><div class="s-val v-primary"><?= (int)$stats['paid_orders'] ?></div><div class="s-sub">会员身份完成的订单</div></div>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px">
        <div style="display:flex;gap:9px;flex-wrap:wrap">
            <button class="btn sm" id="lvlBtn" disabled>🏅 修改会员等级</button>
            <button class="btn sm" id="enableBtn" disabled data-op="enable" data-confirm="确定启用选中的会员?">✓ 启用选中</button>
            <button class="btn sm gray" id="disableBtn" disabled data-op="disable" data-confirm="确定禁用选中的会员? 禁用后其账号将无法登录">🚫 禁用选中</button>
            <button class="btn sm red" id="delBtn" disabled data-op="delete" data-confirm="⚠ 确定移除选中的会员? 账号不可恢复(历史订单保留)">🗑 移除选中用户</button>
        </div>
        <span class="dim" style="font-size:11.5px">Tips: 勾选会员后可批量启用 / 禁用 / 移除</span>
    </div>

    <form method="get" action="<?= site_url('admin.php') ?>">
        <input type="hidden" name="s" value="/users">
        <div class="u-filters">
            <div class="uf"><label>用户名</label><input type="text" name="username" value="<?= e(arr_get($_GET, 'username')) ?>"></div>
            <div class="uf"><label>UID</label><input type="number" name="uid" value="<?= e(arr_get($_GET, 'uid')) ?>"></div>
            <div class="uf"><label>邮箱</label><input type="text" name="email" value="<?= e(arr_get($_GET, 'email')) ?>"></div>
            <div class="uf"><label>注册IP</label><input type="text" name="reg_ip" value="<?= e(arr_get($_GET, 'reg_ip')) ?>"></div>
            <div class="uf"><label>会员等级</label>
                <select name="level">
                    <option value="">全部</option>
                    <option value="0" <?= $curLevel === '0' ? 'selected' : '' ?>>无等级</option>
                    <?php foreach ($levels as $lv): ?><option value="<?= (int)$lv['id'] ?>" <?= $curLevel === (string)$lv['id'] ? 'selected' : '' ?>>LV<?= (int)$lv['level'] ?> <?= e($lv['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="uf"><label>&nbsp;</label><button class="btn" type="submit" style="width:100%">🔍 查询</button></div>
        </div>
    </form>

    <div class="u-tabs">
        <a href="<?= e($tabLink('')) ?>" class="<?= $curStatus === '' ? 'on' : '' ?>">全部</a>
        <a href="<?= e($tabLink('1')) ?>" class="<?= $curStatus === '1' ? 'on' : '' ?>">正常</a>
        <a href="<?= e($tabLink('0')) ?>" class="<?= $curStatus === '0' ? 'on' : '' ?>">封禁</a>
    </div>
</div>

<div class="card">
    <table class="tb" id="userTable">
        <thead>
        <tr>
            <th class="u-chk"><input type="checkbox" id="chkAll" style="width:auto"></th>
            <th>用户名</th>
            <th>等级</th>
            <th>邮箱</th>
            <th>成功订单</th>
            <th>注册IP</th>
            <th>注册时间</th>
            <th>状态</th>
            <th>操作</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($list as $u): $sid = (int)$u['status']; ?>
            <tr>
                <td class="u-chk"><input type="checkbox" class="row-chk" value="<?= (int)$u['id'] ?>" style="width:auto"></td>
                <td class="u-name"><b><?= e($u['username']) ?></b><small>UID <?= (int)$u['id'] ?></small></td>
                <td><?php if (!empty($u['level_num'])): ?><span class="tag blue">LV<?= (int)$u['level_num'] ?> <?= e($u['level_name']) ?></span><?php else: ?><span class="tag">—</span><?php endif; ?></td>
                <td class="dim"><?= e($u['email'] ?: '—') ?></td>
                <td><?= (int)$u['orders_count'] ?></td>
                <td class="dim mono"><?= e($u['reg_ip'] ?: '—') ?></td>
                <td class="dim"><?= e(date('Y-m-d H:i', $u['created_at'])) ?></td>
                <td><span class="tag <?= $sid === 1 ? 'ok' : 'bad' ?>"><?= $sid === 1 ? '正常' : '已封禁' ?></span></td>
                <td class="actions">
                    <button class="btn sm gray" data-toggle="<?= (int)$u['id'] ?>" data-confirm="<?= $sid === 1 ? '确定禁用该会员? 其账号将无法登录' : '确定启用该会员?' ?>"><?= $sid === 1 ? '禁用' : '启用' ?></button>
                    <button class="btn sm red" data-del="<?= (int)$u['id'] ?>" data-confirm="确定移除会员 <?= e($u['username']) ?> ? 账号不可恢复(历史订单保留)">删除</button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="9"><div class="u-empty"><div class="ico">🗂</div>暂无数据</div></td></tr><?php endif; ?>
        </tbody>
    </table>
    <div class="u-total">共 <?= (int)$total ?> 条</div>
    <?php if ($pages > 1): ?>
        <div class="pager">
            <?php for ($i = 1; $i <= min($pages, 12); $i++): ?>
                <a class="<?= $i === $page ? 'on' : '' ?>" href="<?= e($pageLink($i)) ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<!-- 修改会员等级弹窗 -->
<div class="lvl-mask" id="lvlMask">
    <div class="lvl-modal">
        <div class="m-head"><span>🏅 修改会员等级</span><button class="x" id="lvlClose" type="button">✕</button></div>
        <div class="m-body">
            <label>将选中的 <b id="lvlCount">0</b> 位会员设为:</label>
            <select id="lvl-select">
                <option value="0">无等级</option>
                <?php foreach ($levels as $lv): ?><option value="<?= (int)$lv['id'] ?>">LV<?= (int)$lv['level'] ?> <?= e($lv['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="m-foot">
            <button class="btn" id="lvlSave" style="min-width:110px">💾 确定</button>
            <button class="btn gray" id="lvlCancel">✕ 取消</button>
        </div>
    </div>
</div>

<script>
var chkAll = document.getElementById('chkAll');
function rowChks() { return Array.prototype.slice.call(document.querySelectorAll('.row-chk')); }
function syncBtns() {
    var n = rowChks().filter(function (c) { return c.checked; }).length;
    ['lvlBtn', 'enableBtn', 'disableBtn', 'delBtn'].forEach(function (id) {
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
function yfPost(url, fd) {
    fd.append('_csrf', '<?= e(csrf_token()) ?>');
    return fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); });
}
/* 修改会员等级弹窗 */
var lvlMask = document.getElementById('lvlMask');
function checkedIds() { return rowChks().filter(function (c) { return c.checked; }).map(function (c) { return c.value; }); }
document.getElementById('lvlBtn').addEventListener('click', function () {
    var ids = checkedIds();
    if (!ids.length) return;
    document.getElementById('lvlCount').textContent = String(ids.length);
    lvlMask.classList.add('on');
});
document.getElementById('lvlClose').addEventListener('click', function () { lvlMask.classList.remove('on'); });
document.getElementById('lvlCancel').addEventListener('click', function () { lvlMask.classList.remove('on'); });
lvlMask.addEventListener('click', function (ev) { if (ev.target === lvlMask) lvlMask.classList.remove('on'); });
document.getElementById('lvlSave').addEventListener('click', function () {
    var fd = new FormData();
    checkedIds().forEach(function (v) { fd.append('ids[]', v); });
    fd.append('op', 'level');
    fd.append('level_id', document.getElementById('lvl-select').value);
    yfPost('<?= au('users_batch') ?>', fd).then(function (d) { kAlert(d.msg); if (d.code === 0) location.reload(); });
});
document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-toggle],[data-del],[data-op]') : null;
    if (!t) return;
    var fd = new FormData();
    if (t.hasAttribute('data-op')) {
        var ids = rowChks().filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
        if (!ids.length) return;
        ids.forEach(function (v) { fd.append('ids[]', v); });
        fd.append('op', t.getAttribute('data-op'));
        yfPost('<?= au('users_batch') ?>', fd).then(function (d) { kAlert(d.msg); if (d.code === 0) location.reload(); });
        return;
    }
    fd.append('id', t.getAttribute('data-toggle') || t.getAttribute('data-del'));
    var url = t.hasAttribute('data-toggle') ? '<?= au('user_toggle') ?>' : '<?= au('user_del') ?>';
    yfPost(url, fd).then(function (d) { kAlert(d.msg); if (d.code === 0) location.reload(); });
});
</script>
