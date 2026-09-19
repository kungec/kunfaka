<?php $activeMenu = 'orders'; $pages = max(1, (int)ceil($total / $per));
$stMap = [0 => '待支付', 1 => '已完成', 2 => '已过期', 3 => '待处理']; ?>
<div class="card">
    <form class="form-inline" method="get" action="<?= site_url('admin.php') ?>">
        <input type="hidden" name="s" value="/orders">
        <select name="status">
            <option value="">全部状态</option>
            <?php foreach ($stMap as $k => $v): ?>
                <option value="<?= $k ?>" <?= $status !== '' && (int)$status === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="kw" value="<?= e($kw) ?>" placeholder="订单号/联系方式/商品">
        <button class="btn" type="submit">筛选</button>
    </form>
    <div class="form-inline" style="margin-top:12px;padding-top:12px;border-top:1px dashed var(--line)">
        <span class="dim">🧹 一键清理:</span>
        <button class="btn sm red" data-confirm="确定删除全部「已过期」订单? 此操作不可恢复" data-clean="expired">已过期订单</button>
        <button class="btn sm red" data-confirm="确定删除全部「待支付」订单? 未完成的订单将被移除" data-clean="pending">待支付订单</button>
        <button class="btn sm red" data-confirm="⚠ 将删除全部订单(含已完成订单的卡密发货记录)! 此操作不可恢复, 确定继续?" data-clean="all">清空全部订单</button>
        <a class="btn sm gray" href="<?= au('logs') ?>">📜 系统日志</a>
    </div>
</div>

<div class="card">
    <table class="tb">
        <tr><th>订单号</th><th>商品</th><th>金额</th><th>联系方式</th><th>支付方式</th><th>状态</th><th>下单时间</th><th>操作</th></tr>
        <?php foreach ($list as $o): ?>
            <tr>
                <td class="mono"><?= e($o['sn']) ?></td>
                <td><?= e($o['product_name']) ?> × <?= (int)$o['num'] ?></td>
                <td>¥<?= e(nf($o['total'])) ?></td>
                <td class="dim"><?= e($o['contact']) ?><?= $o['contact_type'] ? ' <span class="tag">' . e(contact_type_label($o['contact_type'])) . '</span>' : '' ?></td>
                <td class="dim"><?= e($o['pay_plugin'] ?: '-') ?></td>
                <td><span class="tag <?= (int)$o['status'] === 1 ? 'ok' : ((int)$o['status'] === 0 ? 'warn' : 'bad') ?>"><?= isset($stMap[(int)$o['status']]) ? $stMap[(int)$o['status']] : '未知' ?></span></td>
                <td class="dim"><?= e(date('m-d H:i', $o['created_at'])) ?></td>
                <td class="actions">
                    <a class="btn sm gray" href="<?= au('order_detail', ['id' => $o['id']]) ?>">详情</a>
                    <?php if ((int)$o['status'] === 3): ?><button class="btn sm green" data-deliver="<?= (int)$o['id'] ?>">补发</button><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="8" class="dim" style="text-align:center">暂无订单</td></tr><?php endif; ?>
    </table>
    <?php if ($pages > 1): ?>
        <div class="pager">
            <?php for ($i = 1; $i <= min($pages, 12); $i++): ?>
                <a class="<?= $i === $page ? 'on' : '' ?>" href="<?= au('orders', array_filter(['status' => $status, 'kw' => $kw, 'page' => $i > 1 ? $i : ''], function ($v) { return $v !== '' && $v !== null; })) ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-deliver],[data-clean]') : null;
    if (!t) return;
    var fd = new FormData();
    fd.append('_csrf', '<?= e(csrf_token()) ?>');
    if (t.hasAttribute('data-clean')) {
        fd.append('scope', t.getAttribute('data-clean'));
        fetch('<?= au('order_clean') ?>', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (d) { alert(d.msg); if (d.code === 0) location.reload(); });
        return;
    }
    fd.append('id', t.getAttribute('data-deliver'));
    fetch('<?= au('order_deliver') ?>', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { alert(d.msg); if (d.code === 0) location.reload(); });
});
</script>
