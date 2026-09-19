<?php /** 前台 我的订单 */ ?>
<div class="panel">
    <h1 class="panel-title">我的订单</h1>
    <?php if (!$orders): ?>
        <div class="empty-box">
            <div class="empty-face">(´･ω･`)</div>
            <p>还没有订单, 快去逛逛吧~</p>
            <a class="btn-buy" href="<?= u('home/index') ?>">去购买</a>
        </div>
    <?php else: ?>
        <div class="order-list">
            <?php foreach ($orders as $o): ?>
                <a class="order-item" href="<?= u('order/detail', ['sn' => $o['sn']]) ?>">
                    <div class="oi-line"><b><?= e($o['product_name']) ?> × <?= (int)$o['num'] ?></b>
                        <span class="oi-status <?= (int)$o['status'] === 1 ? 'ok-text' : ((int)$o['status'] === 0 ? 'warn-text' : 'bad-text') ?>"><?= (int)$o['status'] === 1 ? '已完成' : ((int)$o['status'] === 0 ? '待支付' : '已关闭') ?></span></div>
                    <div class="oi-line dim"><?= e($o['sn']) ?> · ¥<?= e(nf($o['total'])) ?> · <?= e(date('m-d H:i', $o['created_at'])) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
