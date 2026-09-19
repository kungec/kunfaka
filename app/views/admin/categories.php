<?php $activeMenu = 'categories'; ?>
<div class="card">
    <h3>添加分类</h3>
    <form method="post" action="<?= au('category_save') ?>" data-ajax>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="0">
        <div class="form-inline">
            <div class="form-row" style="margin-bottom:0"><input type="text" name="name" placeholder="分类名称" required></div>
            <div class="form-row" style="margin-bottom:0"><input type="text" name="icon" placeholder="图标(可选, 如 🎮)"></div>
            <div class="form-row" style="margin-bottom:0"><input type="number" name="sort" placeholder="排序" value="0" style="width:90px"></div>
            <button class="btn" type="submit">添加</button>
        </div>
    </form>
</div>

<div class="card">
    <h3>分类列表</h3>
    <table class="tb">
        <tr><th>ID</th><th>名称</th><th>图标</th><th>排序</th><th>商品数</th><th>操作</th></tr>
        <?php foreach ($list as $c): $cnt = (int)DB::value('SELECT COUNT(*) FROM products WHERE category_id = ?', [$c['id']]); ?>
            <tr>
                <td><?= (int)$c['id'] ?></td>
                <td><input type="text" value="<?= e($c['name']) ?>" data-edit="name" data-id="<?= (int)$c['id'] ?>" style="width:200px"></td>
                <td><input type="text" value="<?= e($c['icon']) ?>" data-edit="icon" data-id="<?= (int)$c['id'] ?>" style="width:90px"></td>
                <td><input type="number" value="<?= (int)$c['sort'] ?>" data-edit="sort" data-id="<?= (int)$c['id'] ?>" style="width:70px"></td>
                <td><?= $cnt ?></td>
                <td class="actions">
                    <button class="btn sm" data-save-row="<?= (int)$c['id'] ?>">保存</button>
                    <button class="btn sm red" data-confirm="确定删除该分类?" data-del="<?= (int)$c['id'] ?>">删除</button>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<script>
document.addEventListener('click', function (ev) {
    var save = ev.target.closest ? ev.target.closest('[data-save-row]') : null;
    if (save) {
        var id = save.getAttribute('data-save-row');
        var wrap = {};
        document.querySelectorAll('[data-edit][data-id="' + id + '"]').forEach(function (i) { wrap[i.getAttribute('data-edit')] = i.value; });
        wrap.id = id;
        yfPost('<?= au('category_save') ?>', wrap, function () { location.reload(); });
        return;
    }
    var del = ev.target.closest ? ev.target.closest('[data-del]') : null;
    if (del) {
        yfPost('<?= au('category_del') ?>', { id: del.getAttribute('data-del') }, function () { location.reload(); });
    }
});
function yfPost(url, data, cb) {
    var fd = new FormData();
    fd.append('_csrf', '<?= e(csrf_token()) ?>');
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { alert(d.msg); if (d.code === 0 && cb) cb(); });
}
</script>
