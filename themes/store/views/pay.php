<?php /** 云商城主题 支付页(QR码/跳转提示 + 到账轮询) */ ?>
<div class="panel">
    <h1 class="panel-title">订单支付</h1>
    <div class="order-summary">
        <div class="os-row"><span>支付方式</span><b class="pay-brand-row"><?= isset($pay['icon']) ? $pay['icon'] : '' ?> <?= e($pay['plugin_name']) ?></b></div>
        <div class="os-row"><span>订单编号</span><b class="mono"><?= e($order['sn']) ?></b></div>
        <div class="os-row"><span>商品</span><b><?= e($order['product_name']) ?> × <?= (int)$order['num'] ?></b></div>
        <?php if ($pay['plugin_code'] === 'usdt_trc20' && !empty($pay['extra']['expected_amount'])): ?>
            <div class="os-row"><span>需支付USDT</span><b class="usdt-amount"><?= e(number_format((float)$pay['extra']['expected_amount'], 6, '.', '')) ?></b></div>
            <div class="os-row warn-line">⚠ 必须转入与上方完全一致的金额, 系统按金额自动识别到账</div>
        <?php else: ?>
            <div class="os-row"><span>应付金额</span><b class="price-text">¥<?= e(nf($order['total'])) ?></b></div>
        <?php endif; ?>
    </div>

    <?php if (isset($pay['type']) && $pay['type'] === 'qrcode'): ?>
        <div class="qr-wrap">
            <div id="qrcode" class="qr-box" data-text="<?= e($pay['qr']) ?>"></div>
            <div class="qr-tip"><?= e(isset($pay['extra']['tip']) ? $pay['extra']['tip'] : '请扫码支付') ?></div>
            <?php if (!empty($pay['extra']['wallet'])): ?>
                <div class="wallet-line">
                    <code id="wallet-text"><?= e($pay['extra']['wallet']) ?></code>
                    <button type="button" class="btn-copy" data-copy="<?= e($pay['extra']['wallet']) ?>">复制地址</button>
                </div>
                <div class="qr-tip small">支付通道: <?= e($pay['extra']['chain']) ?> · 系统检测到账后立即自动发货</div>
            <?php endif; ?>
        </div>
    <?php elseif (isset($pay['type']) && $pay['type'] === 'html'): ?>
        <?= isset($pay['html']) ? $pay['html'] : '' ?>
    <?php else: ?>
        <div class="empty-box"><p>正在跳转支付…</p></div>
    <?php endif; ?>

    <div class="poll-status" id="poll-status">正在等待支付结果<span class="dotting">…</span></div>
    <div class="pay-actions">
        <a class="btn-ghost" href="<?= u('order/detail', ['sn' => $order['sn']]) ?>">查看订单</a>
        <a class="btn-ghost" href="<?= u('home/index') ?>">返回首页</a>
    </div>
    <script>YF_POLL_SN = <?= json_encode($order['sn']) ?>;</script>
</div>
