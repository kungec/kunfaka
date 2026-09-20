<?php $activeMenu = 'orders';
$stMap = [0 => '待支付', 1 => '已完成', 2 => '已过期', 3 => '库存不足待处理']; ?>
<div class="card">
    <h3>订单详情</h3>
    <table class="tb" style="max-width:640px">
        <tr><th style="width:110px">订单号</th><td class="mono"><?= e($order['sn']) ?></td></tr>
        <tr><th>商品</th><td><?= e($order['product_name']) ?> × <?= (int)$order['num'] ?></td></tr>
        <tr><th>单价/总价</th><td>¥<?= e(nf($order['unit_price'])) ?> / <b>¥<?= e(nf($order['total'])) ?></b></td></tr>
        <?php if ((float)$order['expected_amount'] > 0): ?>
            <tr><th>USDT应收</th><td class="mono"><?= e(number_format((float)$order['expected_amount'], 6, '.', '')) ?> USDT</td></tr>
        <?php endif; ?>
        <tr><th>联系方式</th><td><?= e($order['contact']) ?><?= $order['contact_type'] ? ' <span class="tag blue">' . e(contact_type_label($order['contact_type'])) . '</span>' : '' ?></td></tr>
        <tr><th>支付方式</th><td><?= e(payment_display_name($order["pay_plugin"], $order["channel"] ?? "")) ?: "-" ?></td></tr>
        <tr><th>支付流水</th><td class="mono"><?= e($order['trade_no'] ?: $order['txid'] ?: '-') ?></td></tr>
        <tr><th>状态</th><td><span class="tag <?= (int)$order['status'] === 1 ? 'ok' : ((int)$order['status'] === 0 ? 'warn' : 'bad') ?>"><?= isset($stMap[(int)$order['status']]) ? $stMap[(int)$order['status']] : '未知' ?></span></td></tr>
        <tr><th>下单时间</th><td class="dim"><?= e(date('Y-m-d H:i:s', $order['created_at'])) ?></td></tr>
        <tr><th>支付时间</th><td class="dim"><?= $order['paid_at'] ? e(date('Y-m-d H:i:s', $order['paid_at'])) : '-' ?></td></tr>
        <tr><th>客户IP</th><td class="dim"><?= e($order['ip']) ?></td></tr>
        <?php if ($order['cards_content']): ?>
            <tr><th>发货内容</th><td><pre class="cards"><?= e($order['cards_content']) ?></pre>
                <button class="btn sm gray" onclick="yfCopy(this.previousElementSibling.textContent, this)">复制卡密</button></td></tr>
        <?php endif; ?>
    </table>
    <div style="margin-top:14px">
        <?php if ((int)$order['status'] === 3): ?><button class="btn green" data-deliver="<?= (int)$order['id'] ?>">补发卡密</button><?php endif; ?>
        <?php if ((int)$order['status'] !== 1): ?><button class="btn red" data-confirm="确定删除该订单?" data-delorder="<?= (int)$order['id'] ?>">删除订单</button><?php endif; ?>
        <a class="btn gray" href="<?= au('orders') ?>">返回列表</a>
    </div>
</div>

<script>
document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-deliver],[data-delorder]') : null;
    if (!t) return;
    var fd = new FormData();
    fd.append('_csrf', '<?= e(csrf_token()) ?>');
    fd.append('id', t.getAttribute('data-deliver') || t.getAttribute('data-delorder'));
    var url = t.hasAttribute('data-deliver') ? '<?= au('order_deliver') ?>' : '<?= au('order_del') ?>';
    fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { kAlert(d.msg); if (d.code === 0) location.href = '<?= au('orders') ?>'; });
});
</script>
