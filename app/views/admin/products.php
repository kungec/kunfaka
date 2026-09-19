<?php $activeMenu = 'products'; ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
        <h3 style="margin:0">商品列表</h3>
        <div class="form-inline">
            <a class="store-tab<?= $catId === 0 ? ' on' : '' ?>" href="<?= au('products') ?>">全部</a>
            <?php foreach ($categories as $c): ?>
                <a class="store-tab<?= $catId === (int)$c['id'] ? ' on' : '' ?>" href="<?= au('products', ['cat' => $c['id']]) ?>"><?= e($c['name']) ?></a>
            <?php endforeach; ?>
            <a class="btn" href="<?= au('product_edit') ?>">＋ 新增商品</a>
        </div>
    </div>
</div>

<div class="card">
    <table class="tb">
        <tr><th>ID</th><th>商品</th><th>分类</th><th>价格</th><th>库存</th><th>销量</th><th>状态</th><th>操作</th></tr>
        <?php foreach ($list as $p): ?>
            <tr>
                <td><?= (int)$p['id'] ?></td>
                <td><?= e($p['name']) ?></td>
                <td class="dim"><?= e($p['cat_name'] ?: '未分类') ?></td>
                <td>¥<?= e(nf($p['price'])) ?></td>
                <td><span class="tag <?= $p['stock'] == 0 ? 'bad' : ($p['stock'] < 10 ? 'warn' : 'ok') ?>"><?= (int)$p['stock'] ?></span></td>
                <td><?= (int)$p['sales'] ?></td>
                <td><span class="tag <?= (int)$p['status'] === 1 ? 'ok' : '' ?>"><?= (int)$p['status'] === 1 ? '上架' : '下架' ?></span></td>
                <td class="actions">
                    <a class="btn sm gray" href="<?= au('product_edit', ['id' => $p['id']]) ?>">编辑</a>
                    <a class="btn sm gray" href="<?= au('cards', ['product_id' => $p['id']]) ?>">卡密</a>
                    <button class="btn sm red" data-confirm="确定删除商品? 需先清空未售卡密" data-del="<?= (int)$p['id'] ?>">删除</button>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<script>
document.addEventListener('click', function (ev) {
    var del = ev.target.closest ? ev.target.closest('[data-del]') : null;
    if (!del) return;
    var fd = new FormData();
    fd.append('_csrf', '<?= e(csrf_token()) ?>');
    fd.append('id', del.getAttribute('data-del'));
    fetch('<?= au('product_del') ?>', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { alert(d.msg); if (d.code === 0) location.reload(); });
});
</script>
