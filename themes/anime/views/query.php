<?php /** 二次元主题 订单查询 */ ?>
<div class="panel">
    <h1 class="panel-title">订单查询</h1>
    <form class="buy-form" method="get" action="<?= u('order/query') ?>">
        <div class="form-row">
            <label>订单号 / 联系方式</label>
            <input type="text" name="kw" value="<?= e($kw) ?>" placeholder="输入订单号或下单时填写的邮箱/QQ">
        </div>
        <button class="btn-buy" type="submit">查询</button>
    </form>
    <?php if ($orders): ?>
        <div class="order-list">
            <?php foreach ($orders as $o): ?>
                <a class="order-item" href="<?= u('order/detail', ['sn' => $o['sn']]) ?>">
                    <div class="oi-line"><b><?= e($o['product_name']) ?> × <?= (int)$o['num'] ?></b>
                        <span class="oi-status <?= (int)$o['status'] === 1 ? 'ok-text' : 'warn-text' ?>"><?= (int)$o['status'] === 1 ? '已完成' : ((int)$o['status'] === 0 ? '待支付' : '已关闭') ?></span></div>
                    <div class="oi-line dim"><?= e($o['sn']) ?> · ¥<?= e(nf($o['total'])) ?> · <?= e(date('m-d H:i', $o['created_at'])) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php elseif ($kw !== ''): ?>
        <div class="empty-box"><p>没有找到相关订单 (｡•́︿•̀｡)</p></div>
    <?php endif; ?>
</div>
