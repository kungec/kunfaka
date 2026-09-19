<?php /** 二次元主题 订单详情/结果页 */ ?>
<div class="panel result-panel">
    <h1 class="panel-title">订单详情</h1>
    <div class="order-summary">
        <div class="os-row"><span>订单编号</span><b><?= e($order['sn']) ?></b></div>
        <div class="os-row"><span>商品</span><b><?= e($order['product_name']) ?> × <?= (int)$order['num'] ?></b></div>
        <div class="os-row"><span>金额</span><b class="price-text">¥<?= e(nf($order['total'])) ?></b></div>
        <div class="os-row"><span>联系方式</span><b><?= e($order['contact']) ?><?= $order['contact_type'] ? ' · ' . e(contact_type_label($order['contact_type'])) : '' ?></b></div>
        <div class="os-row"><span>支付方式</span><b><?= e($pluginName ?: '未选择') ?></b></div>
        <?php if (!empty($order['txid'])): ?>
            <div class="os-row"><span>链上哈希</span><b class="mono"><?= e(mb_substr($order['txid'], 0, 20)) ?>…</b></div>
        <?php endif; ?>
        <div class="os-row"><span>订单状态</span><b class="<?= (int)$order['status'] === 1 ? 'ok-text' : ((int)$order['status'] === 0 ? 'warn-text' : 'bad-text') ?>">
            <?= (int)$order['status'] === 1 ? '✅ 已完成发货' : ((int)$order['status'] === 0 ? '⏳ 待支付' : ((int)$order['status'] === 2 ? '已过期' : '待人工处理')) ?></b></div>
    </div>

    <?php if ((int)$order['status'] === 1): ?>
        <div class="cards-box">
            <div class="cards-head">🎉 您的卡密(已自动发货)</div>
            <pre class="cards-content" id="cards-text"><?= e($order['cards_content']) ?></pre>
            <button type="button" class="btn-buy" data-copy-text-target="cards-text">一键复制</button>
        </div>
    <?php elseif ((int)$order['status'] === 0): ?>
        <div class="pay-actions">
            <?php if ($order['pay_plugin']): ?>
                <a class="btn-buy" href="<?= u('pay/choose', ['sn' => $order['sn']]) ?>">继续支付</a>
            <?php else: ?>
                <a class="btn-buy" href="<?= u('pay/choose', ['sn' => $order['sn']]) ?>">去支付</a>
            <?php endif; ?>
        </div>
        <p class="tip-line">订单将在 <?= e(max(0, (int)ceil(($order['expired_at'] - now()) / 60))) ?> 分钟后自动关闭</p>
    <?php elseif ((int)$order['status'] === 2): ?>
        <p class="tip-line">订单已超时关闭, 请重新下单购买。</p>
    <?php else: ?>
        <p class="tip-line">支付遇到问题? 请联系管理员人工处理。</p>
    <?php endif; ?>
</div>
