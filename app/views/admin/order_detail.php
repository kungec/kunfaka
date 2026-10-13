<?php $activeMenu = 'orders';
$stMap = [0 => '待支付', 1 => '已完成', 2 => '已过期', 3 => '待处理'];
$isManual = ($order['delivery_type'] ?? DB::value('SELECT delivery_type FROM products WHERE id = ?', [$order['product_id']])) === 'manual'; ?>
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
        <?php if ((int)$order['status'] === 3 && !$isManual): ?><button class="btn green" data-deliver="<?= (int)$order['id'] ?>">补发卡密</button><?php endif; ?>
        <?php if ((int)$order['status'] === 3 && $isManual): ?>
        <div class="card" style="margin:14px 0;padding:14px">
            <div style="font-weight:700;margin-bottom:8px">人工发货(填写内容后发给买家, 邮箱联系方式将同步邮件通知)</div>
            <textarea id="manualContent" rows="4" placeholder="填写发货内容: 如充值进度 / 账号信息 / 兑换说明等" style="width:100%;max-width:640px"></textarea>
            <div style="margin-top:8px"><button class="btn green" id="manualDeliver" data-id="<?= (int)$order['id'] ?>"><?= ico('send') ?> 完成发货并通知买家</button></div>
        </div>
        <?php endif; ?>
        <?php if ((int)$order['status'] !== 1): ?><button class="btn red" data-confirm="确定删除该订单?" data-delorder="<?= (int)$order['id'] ?>">删除订单</button><?php endif; ?>
        <a class="btn gray" href="<?= au('orders') ?>">返回列表</a>
    </div>
</div>

<script>
/* 人工发货提交(人工处理型商品) */
var manualBtn = document.getElementById('manualDeliver');
if (manualBtn) manualBtn.addEventListener('click', function () {
    var content = document.getElementById('manualContent').value.trim();
    if (!content) { kAlert('请填写发货内容'); return; }
    var fd = new FormData();
    fd.append('_csrf', '<?= e(csrf_token()) ?>');
    fd.append('id', manualBtn.getAttribute('data-id'));
    fd.append('content', content);
    fetch('<?= au('order_manual_deliver') ?>', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { kAlert(d.msg); if (d.code === 0) location.reload(); });
});
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
