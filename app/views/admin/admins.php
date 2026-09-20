<?php $activeMenu = 'admins';
$curStatus = isset($_GET['status']) ? (string)$_GET['status'] : '';
$kw = isset($_GET['kw']) ? (string)$_GET['kw'] : '';
$me = current_admin();
?>
<style>
.adm-filters{display:flex;gap:9px;flex-wrap:wrap;align-items:flex-end}
.adm-filters .af label{display:block;font-size:11px;color:var(--muted);margin-bottom:4px;font-weight:600}
.adm-filters input{height:36px;padding:0 10px;font-size:12.5px;width:200px}
.adm-tabs{display:flex;gap:7px;margin-top:14px}
.adm-tabs a{padding:6px 16px;border-radius:8px;border:1.5px solid var(--input-border);font-size:12.5px;font-weight:600;color:var(--text2);text-decoration:none;transition:.12s}
.adm-tabs a.on{border-color:var(--text);color:var(--text);background:var(--input-bg)}
.adm-user b{display:block;font-size:13px}
.adm-user small{color:var(--muted);font-size:10.5px}
.adm-mask{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:120;display:none;align-items:flex-start;justify-content:center;padding:80px 16px}
.adm-mask.on{display:flex}
.adm-modal{background:var(--card);border:1px solid var(--input-border);border-radius:14px;width:100%;max-width:420px;overflow:hidden}
.adm-modal .m-head{padding:15px 20px;font-weight:700;font-size:14.5px;border-bottom:1px solid var(--input-border);display:flex;justify-content:space-between}
.adm-modal .m-head .x{cursor:pointer;color:var(--muted);font-size:17px;background:none;border:none}
.adm-modal .m-body{padding:18px 20px}
.adm-modal .m-body label{display:block;font-size:11.5px;color:var(--muted);font-weight:600;margin-bottom:5px}
.adm-modal .m-body input,.adm-modal .m-body select{width:100%;height:40px;padding:0 12px;font-size:13px;margin-bottom:13px}
.adm-modal .m-foot{padding:14px 20px;border-top:1px solid var(--input-border);display:flex;gap:9px;justify-content:center}
.adm-modal .m-foot .btn{min-width:100px}
</style>

<div class="page-head">
    <div>
        <h2>🛡 管理员</h2>
        <div class="sub">后台账号的新增 · 启停 · 移除(仅超级管理员)</div>
    </div>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px">
        <button class="btn sm" id="addBtn">➕ 添加管理员</button>
    </div>

    <form method="get" action="<?= site_url('admin.php') ?>">
        <input type="hidden" name="s" value="/admins">
        <div class="adm-filters">
            <div class="af"><label>用户名 / 昵称</label><input type="text" name="kw" value="<?= e($kw) ?>"></div>
            <div class="af"><button class="btn" type="submit" style="height:36px">🔍 查询</button></div>
        </div>
    </form>

    <div class="adm-tabs">
        <a href="<?= e(au('admins', array_filter(['kw' => $kw]))) ?>" class="<?= $curStatus === '' ? 'on' : '' ?>">全部</a>
        <a href="<?= e(au('admins', ['kw' => $kw, 'status' => '1'])) ?>" class="<?= $curStatus === '1' ? 'on' : '' ?>">已启用</a>
        <a href="<?= e(au('admins', ['kw' => $kw, 'status' => '0'])) ?>" class="<?= $curStatus === '0' ? 'on' : '' ?>">未启用</a>
    </div>
</div>

<div class="card">
    <table class="tb">
        <thead>
        <tr>
            <th>管理员</th>
            <th>状态</th>
            <th>角色</th>
            <th>创建时间</th>
            <th>登录时间 / IP</th>
            <th>上次登录时间 / IP</th>
            <th>操作</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($list as $a): $isMe = (int)$a['id'] === (int)$me['id']; $isSuperTarget = $a['role'] === 'super'; ?>
            <tr>
                <td class="adm-user"><b><?= e($a['username']) ?><?= $isMe ? ' (我)' : '' ?></b><small><?= e($a['nickname'] ?: '—') ?></small></td>
                <td><span class="tag <?= (int)$a['status'] === 1 ? 'ok' : 'bad' ?>"><?= (int)$a['status'] === 1 ? '已启用' : '已禁用' ?></span></td>
                <td><?= $isSuperTarget ? '<span class="tag pro">超级管理员</span>' : '<span class="tag">普通管理员</span>' ?></td>
                <td class="dim"><?= e(date('Y-m-d', $a['created_at'])) ?></td>
                <td class="dim"><?= $a['last_login_at'] > 0 ? e(date('m-d H:i', $a['last_login_at'])) . '<br><small class="mono">' . e($a['last_login_ip']) . '</small>' : '—' ?></td>
                <td class="dim"><?= $a['prev_login_at'] > 0 ? e(date('m-d H:i', $a['prev_login_at'])) . '<br><small class="mono">' . e($a['prev_login_ip']) . '</small>' : '—' ?></td>
                <td class="actions">
                    <button class="btn sm gray" data-edit='<?= e(json_encode(['id' => (int)$a['id'], 'username' => $a['username'], 'nickname' => $a['nickname'], 'role' => $a['role']], JSON_UNESCAPED_UNICODE)) ?>'>✏ 编辑</button>
                    <?php if (!$isMe): ?>
                        <button class="btn sm <?= (int)$a['status'] === 1 ? 'gray' : 'green' ?>" data-toggle="<?= (int)$a['id'] ?>" data-confirm="确定<?= (int)$a['status'] === 1 ? '禁用' : '启用' ?> <?= e($a['username']) ?> ?"><?= (int)$a['status'] === 1 ? '禁用' : '启用' ?></button>
                        <button class="btn sm red" data-del="<?= (int)$a['id'] ?>" data-confirm="确定删除管理员 <?= e($a['username']) ?> ? 此操作不可恢复">🗑 删除</button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="7"><div style="text-align:center;padding:34px 0;color:var(--muted)">暂无数据</div></td></tr><?php endif; ?>
        </tbody>
    </table>
    <div style="font-size:12px;color:var(--muted);padding:10px 2px 0">共 <?= count($list) ?> 条</div>
</div>

<!-- 添加/编辑弹窗 -->
<div class="adm-mask" id="admMask">
    <div class="adm-modal">
        <div class="m-head"><span id="modalTitle">添加管理员</span><button class="x" id="admClose" type="button">✕</button></div>
        <div class="m-body">
            <input type="hidden" id="f-id" value="0">
            <div id="f-username-wrap"><label>用户名 *(3-20位字母/数字/下划线, 创建后不可改)</label><input type="text" id="f-username" maxlength="20"></div>
            <div><label>昵称</label><input type="text" id="f-nickname" maxlength="50"></div>
            <div><label>角色</label>
                <select id="f-role">
                    <option value="normal">普通管理员</option>
                    <option value="super">超级管理员(可管理管理员)</option>
                </select>
            </div>
            <div><label id="f-pass-label">初始密码 *(至少6位)</label><input type="password" id="f-password" maxlength="64"></div>
        </div>
        <div class="m-foot">
            <button class="btn" id="admSave" style="min-width:110px">💾 保存</button>
            <button class="btn gray" id="admCancel">✕ 取消</button>
        </div>
    </div>
</div>

<script>
function yfPost(url, fd) {
    fd.append('_csrf', '<?= e(csrf_token()) ?>');
    return fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); });
}
var mask = document.getElementById('admMask');
function openModal(data) {
    var isEdit = !!data;
    document.getElementById('modalTitle').textContent = isEdit ? '编辑管理员' : '添加管理员';
    document.getElementById('f-id').value = isEdit ? data.id : 0;
    document.getElementById('f-nickname').value = isEdit ? data.nickname : '';
    document.getElementById('f-role').value = isEdit ? data.role : 'normal';
    document.getElementById('f-password').value = '';
    document.getElementById('f-username-wrap').style.display = isEdit ? 'none' : 'block';
    document.getElementById('f-pass-label').textContent = isEdit ? '重置密码(留空则不修改)' : '初始密码 *(至少6位)';
    mask.classList.add('on');
}
document.getElementById('addBtn').addEventListener('click', function () { openModal(null); });
document.getElementById('admClose').addEventListener('click', function () { mask.classList.remove('on'); });
document.getElementById('admCancel').addEventListener('click', function () { mask.classList.remove('on'); });
mask.addEventListener('click', function (ev) { if (ev.target === mask) mask.classList.remove('on'); });
document.getElementById('admSave').addEventListener('click', function () {
    var fd = new FormData();
    fd.append('id', document.getElementById('f-id').value);
    fd.append('username', document.getElementById('f-username').value.trim());
    fd.append('nickname', document.getElementById('f-nickname').value.trim());
    fd.append('role', document.getElementById('f-role').value);
    fd.append('password', document.getElementById('f-password').value);
    yfPost('<?= au('admin_save') ?>', fd).then(function (d) { kAlert(d.msg); if (d.code === 0) location.reload(); });
});
document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-edit],[data-toggle],[data-del]') : null;
    if (!t) return;
    var fd = new FormData();
    if (t.hasAttribute('data-edit')) {
        openModal(JSON.parse(t.getAttribute('data-edit')));
        return;
    }
    if (!confirm(t.getAttribute('data-confirm') || '确定执行该操作?')) return;
    if (t.hasAttribute('data-toggle')) {
        fd.append('id', t.getAttribute('data-toggle'));
        yfPost('<?= au('admin_toggle') ?>', fd).then(function (d) { kAlert(d.msg); if (d.code === 0) location.reload(); });
        return;
    }
    fd.append('id', t.getAttribute('data-del'));
    yfPost('<?= au('admin_del') ?>', fd).then(function (d) { kAlert(d.msg); if (d.code === 0) location.reload(); });
});
</script>
