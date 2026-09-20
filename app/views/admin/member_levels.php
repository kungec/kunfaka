<?php $activeMenu = 'member_levels';
$lvPalettes = ['#f43f5e', '#f97316', '#eab308', '#22c55e', '#0ea5e9', '#8b5cf6'];
?>
<style>
.ml-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:start}
@media(max-width:1000px){.ml-grid{grid-template-columns:1fr}}
.ml-grid .card{margin-bottom:0}
.ml-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
.ml-head h3{margin:0}
.lv-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 4px;border-bottom:1px dashed var(--input-border)}
.lv-row:last-child{border-bottom:none}
.lv-badge{display:inline-flex;align-items:center;gap:7px}
.lv-badge .lv{font-size:10px;font-weight:800;color:#fff;border-radius:5px;padding:2px 6px;letter-spacing:.5px}
.lv-badge b{font-size:13.5px}
.lv-members{font-size:11px;color:var(--muted)}
table.tb td,table.tb th{border-bottom:1px dashed var(--input-border)}
/* 弹窗 */
.ml-mask{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:120;display:none;align-items:flex-start;justify-content:center;padding:80px 16px}
.ml-mask.on{display:flex}
.ml-modal{background:var(--card);border:1px solid var(--input-border);border-radius:14px;width:100%;max-width:400px;overflow:hidden}
.ml-modal .m-head{padding:15px 20px;font-weight:700;font-size:14.5px;border-bottom:1px solid var(--input-border);display:flex;justify-content:space-between}
.ml-modal .m-head .x{cursor:pointer;color:var(--muted);font-size:17px;background:none;border:none}
.ml-modal .m-body{padding:18px 20px}
.ml-modal .m-body label{display:block;font-size:11.5px;color:var(--muted);font-weight:600;margin-bottom:5px}
.ml-modal .m-body input,.ml-modal .m-body select{width:100%;height:40px;padding:0 12px;font-size:13px;margin-bottom:13px}
.ml-modal .m-foot{padding:14px 20px;border-top:1px solid var(--input-border);display:flex;gap:9px;justify-content:center}
</style>

<div class="page-head">
    <div>
        <h2>🏅 会员等级</h2>
        <div class="sub">会员等级与商品分组: 按等级控制商品的可见与购买</div>
    </div>
</div>

<div class="ml-grid">
    <!-- 会员等级 -->
    <div class="card">
        <div class="ml-head">
            <h3>🏅 会员等级</h3>
            <button class="btn sm" id="addLevelBtn">➕ 新增等级</button>
        </div>
        <table class="tb">
            <thead><tr><th>等级名称</th><th>会员数</th><th style="width:150px">操作</th></tr></thead>
            <tbody>
            <?php foreach ($levels as $lv): $color = $lvPalettes[((int)$lv['level'] - 1) % count($lvPalettes)]; ?>
                <tr>
                    <td>
                        <span class="lv-badge">
                            <span class="lv" style="background:<?= $color ?>">LV<?= (int)$lv['level'] ?></span>
                            <b><?= e($lv['name']) ?></b>
                        </span>
                    </td>
                    <td class="dim"><?= (int)$lv['members'] ?> 人</td>
                    <td class="actions">
                        <button class="btn sm gray" data-edit-level='<?= e(json_encode(['id' => (int)$lv['id'], 'name' => $lv['name'], 'level' => (int)$lv['level']], JSON_UNESCAPED_UNICODE)) ?>'>✏ 修改</button>
                        <button class="btn sm red" data-del-level="<?= (int)$lv['id'] ?>" data-confirm="确定删除等级 LV<?= (int)$lv['level'] ?> <?= e($lv['name']) ?> ? 关联会员将恢复为无等级">🗑 删除</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$levels): ?><tr><td colspan="3"><div style="text-align:center;padding:26px 0;color:var(--muted)">暂无等级, 点击右上角「新增等级」创建</div></td></tr><?php endif; ?>
            </tbody>
        </table>
        <p class="dim" style="font-size:11.5px;margin:10px 0 0">等级数值越大越高。将分组设置最低等级后, 低等级买家看不到该分组下的商品。</p>
    </div>

    <!-- 商品分组 -->
    <div class="card">
        <div class="ml-head">
            <h3>🗂 商品分组</h3>
            <button class="btn sm" id="addGroupBtn">➕ 添加分组</button>
        </div>
        <table class="tb">
            <thead><tr><th>分组名称</th><th>最低等级</th><th>商品</th><th style="width:150px">操作</th></tr></thead>
            <tbody>
            <?php foreach ($groups as $g): ?>
                <tr>
                    <td><b><?= e($g['name']) ?></b></td>
                    <td><?= (int)$g['min_level'] > 0 ? '<span class="tag warn">LV' . (int)$g['min_level'] . '+</span>' : '<span class="tag ok">不限制</span>' ?></td>
                    <td><?= (int)$g['products'] ?></td>
                    <td class="actions">
                        <button class="btn sm gray" data-edit-group='<?= e(json_encode(['id' => (int)$g['id'], 'name' => $g['name'], 'min_level' => (int)$g['min_level']], JSON_UNESCAPED_UNICODE)) ?>'>✏ 修改</button>
                        <button class="btn sm red" data-del-group="<?= (int)$g['id'] ?>" data-confirm="确定删除分组 <?= e($g['name']) ?> ?<?= (int)$g['products'] > 0 ? ' 该分组下有 ' . (int)$g['products'] . ' 个商品, 需先调整!' : '' ?>">🗑 删除</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$groups): ?><tr><td colspan="4"><div style="text-align:center;padding:26px 0;color:var(--muted)">暂无分组</div></td></tr><?php endif; ?>
            </tbody>
        </table>
        <p class="dim" style="font-size:11.5px;margin:10px 0 0">在「商品管理」的添加/编辑抽屉中把商品归入分组; 分组设置了最低等级后, 未达到等级的买家不可见、不可购。</p>
    </div>
</div>

<!-- 等级弹窗 -->
<div class="ml-mask" id="lvMask">
    <div class="ml-modal">
        <div class="m-head"><span id="lvTitle">新增等级</span><button class="x" id="lvClose" type="button">✕</button></div>
        <div class="m-body">
            <input type="hidden" id="lv-id" value="0">
            <div><label>等级名称 *</label><input type="text" id="lv-name" maxlength="20" placeholder="如 小康之家"></div>
            <div><label>等级数值(LV显示, 越大越高) *</label><input type="number" id="lv-level" min="1" value="1"></div>
        </div>
        <div class="m-foot">
            <button class="btn" id="lvSave" style="min-width:110px">💾 保存</button>
            <button class="btn gray" id="lvCancel">✕ 取消</button>
        </div>
    </div>
</div>

<!-- 分组弹窗 -->
<div class="ml-mask" id="gpMask">
    <div class="ml-modal">
        <div class="m-head"><span id="gpTitle">添加分组</span><button class="x" id="gpClose" type="button">✕</button></div>
        <div class="m-body">
            <input type="hidden" id="gp-id" value="0">
            <div><label>分组名称 *</label><input type="text" id="gp-name" maxlength="50" placeholder="如 VIP专区"></div>
            <div><label>可见所需最低等级(0=不限制)</label>
                <select id="gp-min">
                    <option value="0">不限制(所有人可见)</option>
                    <?php foreach ($levels as $lv): ?><option value="<?= (int)$lv['level'] ?>">LV<?= (int)$lv['level'] ?> <?= e($lv['name']) ?> 及以上</option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="m-foot">
            <button class="btn" id="gpSave" style="min-width:110px">💾 保存</button>
            <button class="btn gray" id="gpCancel">✕ 取消</button>
        </div>
    </div>
</div>

<script>
function yfPost(url, fd) {
    fd.append('_csrf', '<?= e(csrf_token()) ?>');
    return fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); });
}
function bindModal(maskId, openId, closeIds, fill) {
    var mask = document.getElementById(maskId);
    document.getElementById(openId).addEventListener('click', function () {
        fill(null);
        mask.classList.add('on');
    });
    closeIds.forEach(function (id) {
        document.getElementById(id).addEventListener('click', function () { mask.classList.remove('on'); });
    });
    mask.addEventListener('click', function (ev) { if (ev.target === mask) mask.classList.remove('on'); });
    return mask;
}
/* 等级 */
var lvMask = bindModal('lvMask', 'addLevelBtn', ['lvClose', 'lvCancel'], function (data) {
    document.getElementById('lvTitle').textContent = data ? '修改等级' : '新增等级';
    document.getElementById('lv-id').value = data ? data.id : 0;
    document.getElementById('lv-name').value = data ? data.name : '';
    document.getElementById('lv-level').value = data ? data.level : (<?= count($levels) ?> + 1);
});
document.getElementById('lvSave').addEventListener('click', function () {
    var fd = new FormData();
    fd.append('id', document.getElementById('lv-id').value);
    fd.append('name', document.getElementById('lv-name').value.trim());
    fd.append('level', document.getElementById('lv-level').value || '1');
    yfPost('<?= au('level_save') ?>', fd).then(function (d) { kAlert(d.msg); if (d.code === 0) location.reload(); });
});
/* 分组 */
var gpMask = bindModal('gpMask', 'addGroupBtn', ['gpClose', 'gpCancel'], function (data) {
    document.getElementById('gpTitle').textContent = data ? '修改分组' : '添加分组';
    document.getElementById('gp-id').value = data ? data.id : 0;
    document.getElementById('gp-name').value = data ? data.name : '';
    document.getElementById('gp-min').value = data ? data.min_level : 0;
});
document.getElementById('gpSave').addEventListener('click', function () {
    var fd = new FormData();
    fd.append('id', document.getElementById('gp-id').value);
    fd.append('name', document.getElementById('gp-name').value.trim());
    fd.append('min_level', document.getElementById('gp-min').value || '0');
    yfPost('<?= au('group_save') ?>', fd).then(function (d) { kAlert(d.msg); if (d.code === 0) location.reload(); });
});
/* 行内动作 */
document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-edit-level],[data-del-level],[data-edit-group],[data-del-group]') : null;
    if (!t) return;
    if (t.hasAttribute('data-edit-level')) {
        var d = JSON.parse(t.getAttribute('data-edit-level'));
        lvMask.classList.add('on');
        document.getElementById('lvTitle').textContent = '修改等级';
        document.getElementById('lv-id').value = d.id;
        document.getElementById('lv-name').value = d.name;
        document.getElementById('lv-level').value = d.level;
        return;
    }
    if (t.hasAttribute('data-del-level')) {
        var fd = new FormData(); fd.append('id', t.getAttribute('data-del-level'));
        yfPost('<?= au('level_del') ?>', fd).then(function (d) { kAlert(d.msg); if (d.code === 0) location.reload(); });
        return;
    }
    if (t.hasAttribute('data-edit-group')) {
        var g = JSON.parse(t.getAttribute('data-edit-group'));
        gpMask.classList.add('on');
        document.getElementById('gpTitle').textContent = '修改分组';
        document.getElementById('gp-id').value = g.id;
        document.getElementById('gp-name').value = g.name;
        document.getElementById('gp-min').value = g.min_level;
        return;
    }
    var fd2 = new FormData(); fd2.append('id', t.getAttribute('data-del-group'));
    yfPost('<?= au('group_del') ?>', fd2).then(function (d) { kAlert(d.msg); if (d.code === 0) location.reload(); });
});
</script>
