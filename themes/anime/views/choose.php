<?php /** 二次元主题 选择支付方式 */ ?>
<div class="panel">
    <div class="order-summary">
        <div class="os-row"><span>订单编号</span><b><?= e($order['sn']) ?></b></div>
        <div class="os-row"><span>商品</span><b><?= e($order['product_name']) ?> × <?= (int)$order['num'] ?></b></div>
        <div class="os-row"><span>应付金额</span><b class="price-text">¥<?= e(nf($order['total'])) ?></b></div>
    </div>
    <h2 class="panel-subtitle">选择支付方式 ⚡</h2>
    <?php if (!$payments): ?>
        <div class="empty-box"><p>管理员尚未启用任何支付方式</p></div>
    <?php else: ?>
        <div class="pay-list">
            <?php foreach ($payments as $pm): ?>
                <form method="post" action="<?= u('pay/go') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="sn" value="<?= e($order['sn']) ?>">
                    <input type="hidden" name="plugin" value="<?= e($pm['code']) ?>">
                    <?php if (!empty($pm['channel'])): ?><input type="hidden" name="channel" value="<?= e($pm['channel']) ?>"><?php endif; ?>
                    <button class="pay-item" type="submit">
                        <span class="pay-icon"><?= $pm['icon'] ?></span>
                        <span class="pay-name"><?= e($pm['title']) ?></span>
                        <span class="pay-arrow">›</span>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <p class="tip-line">支付成功后系统将立即自动发货, 请勿重复支付。</p>
</div>
