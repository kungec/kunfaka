<?php $activeMenu = 'cards'; $pages = max(1, (int)ceil($total / $per)); ?>
<div class="card">
    <h3>导入卡密</h3>
    <form method="post" action="<?= au('cards_import') ?>" data-ajax>
        <?= csrf_field() ?>
        <div class="form-row">
            <label>选择商品</label>
            <select name="product_id" style="max-width:300px">
                <option value="0">请选择商品</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $productId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label>卡密内容(每行一张, 支持任意格式: 卡号----密码 / 单条卡密)</label>
            <textarea name="cards" placeholder="XXXX-XXXX-XXXX&#10;YYYY-YYYY-YYYY" style="min-height:120px"></textarea>
        </div>
        <button class="btn green" type="submit">批量导入</button>
    </form>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px">
        <h3 style="margin:0">卡密库存 <span class="tag"><?= (int)$total ?> 张</span></h3>
        <?php if ($productId > 0): ?>
            <button class="btn sm red" data-confirm="确定清空该商品全部未售卡密?" data-clear="<?= $productId ?>">清空未售</button>
        <?php endif; ?>
    </div>
    <table class="tb">
        <tr><th>ID</th><th>商品</th><th>内容</th><th>状态</th><th>添加时间</th><th>操作</th></tr>
        <?php foreach ($list as $c): ?>
            <tr>
                <td><?= (int)$c['id'] ?></td>
                <td class="dim"><?= e($c['product_name']) ?></td>
                <td class="mono"><?= e(mb_substr($c['content'], 0, 40)) ?><?= mb_strlen($c['content']) > 40 ? '…' : '' ?></td>
                <td><span class="tag <?= (int)$c['status'] === 0 ? 'ok' : '' ?>"><?= (int)$c['status'] === 0 ? '未售' : '已售' ?></span></td>
                <td class="dim"><?= e(date('Y-m-d H:i', $c['created_at'])) ?></td>
                <td class="actions">
                    <?php if ((int)$c['status'] === 0): ?><button class="btn sm red" data-confirm="删除该卡密?" data-delcard="<?= (int)$c['id'] ?>">删除</button><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php if ($pages > 1): ?>
        <div class="pager">
            <?php for ($i = 1; $i <= min($pages, 12); $i++): ?>
                <a class="<?= $i === $page ? 'on' : '' ?>" href="<?= au('cards', array_filter(['product_id' => $productId > 0 ? $productId : '', 'page' => $i])) ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-delcard],[data-clear]') : null;
    if (!t) return;
    var fd = new FormData();
    fd.append('_csrf', '<?= e(csrf_token()) ?>');
    fd.append(t.hasAttribute('data-delcard') ? 'id' : 'product_id', t.hasAttribute('data-delcard') ? t.getAttribute('data-delcard') : t.getAttribute('data-clear'));
    fetch(t.hasAttribute('data-delcard') ? '<?= au('card_del') ?>' : '<?= au('cards_clear') ?>', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { alert(d.msg); if (d.code === 0) location.reload(); });
});
</script>
