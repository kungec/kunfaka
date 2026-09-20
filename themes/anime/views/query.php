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

    <?php if (($mode === 'contact' || $mode === 'sn') && $orders && !($mode === 'contact' && !$verified)): ?>
        <div class="order-list">
            <?php foreach ($orders as $o): ?>
                <a class="order-item" href="<?= u('order/detail', ['sn' => $o['sn']]) ?>">
                    <div class="oi-line"><b><?= e($o['product_name']) ?> × <?= (int)$o['num'] ?></b>
                        <span class="oi-status <?= (int)$o['status'] === 1 ? 'ok-text' : 'warn-text' ?>"><?= (int)$o['status'] === 1 ? '已完成' : ((int)$o['status'] === 0 ? '待支付' : '已关闭') ?></span></div>
                    <div class="oi-line dim"><?= e($o['sn']) ?> · ¥<?= e(nf($o['total'])) ?> · <?= e(date('m-d H:i', $o['created_at'])) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($mode === 'contact' && $orders && !$verified): ?>
        <?php /* 联系方式查询未验证: 只展示不含订单号的状态概览 */ ?>
        <?php if ($otpErr): ?><p class="tip-line bad-text"><?= e($otpErr) ?></p><?php endif; ?>
        <?php if ($otpOk): ?><p class="tip-line ok-text"><?= e($otpOk) ?></p><?php endif; ?>
        <div class="order-list">
            <?php foreach ($orders as $o): ?>
                <div class="order-item">
                    <div class="oi-line"><b><?= e($o['product_name']) ?> × <?= (int)$o['num'] ?></b>
                        <span class="oi-status <?= (int)$o['status'] === 1 ? 'ok-text' : 'warn-text' ?>"><?= (int)$o['status'] === 1 ? '已完成' : ((int)$o['status'] === 0 ? '待支付' : '已关闭') ?></span></div>
                    <div class="oi-line dim">¥<?= e(nf($o['total'])) ?> · <?= e(date('m-d H:i', $o['created_at'])) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($mailOpen): ?>
            <div class="buy-form" style="margin-top:14px">
                <p class="tip-line">为保护买家隐私, 查看订单详情与卡密需验证邮箱归属。验证通过后 30 分钟内免重复验证。</p>
                <?php if ($otpStep): ?>
                    <form class="buy-form" method="post" action="<?= u('order/query') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="kw" value="<?= e($kw) ?>">
                        <input type="hidden" name="oq_action" value="verify">
                        <div class="form-row">
                            <label>邮件验证码</label>
                            <input type="text" name="code" maxlength="6" inputmode="numeric" placeholder="6位数字验证码" required>
                        </div>
                        <button class="btn-buy" type="submit">验证并查看订单</button>
                    </form>
                    <form method="post" action="<?= u('order/query') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="kw" value="<?= e($kw) ?>">
                        <input type="hidden" name="oq_action" value="send">
                        <button class="btn-buy" type="submit">重新发送验证码</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= u('order/query') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="kw" value="<?= e($kw) ?>">
                        <input type="hidden" name="oq_action" value="send">
                        <button class="btn-buy" type="submit">发送验证码到该邮箱</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="tip-line" style="margin-top:14px">为保护买家隐私, 订单号与卡密不在此展示。请通过支付完成页查看卡密, 或联系站长处理。</p>
        <?php endif; ?>
    <?php elseif ((($mode === 'contact' && !$orders) || ($mode === 'sn' && !$orders)) && $kw !== ''): ?>
        <div class="empty-box"><p>没有找到相关订单 (｡•́︿•̀｡)</p></div>
    <?php endif; ?>
</div>
