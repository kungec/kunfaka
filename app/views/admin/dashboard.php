<?php $activeMenu = 'dashboard'; ?>
<div class="page-head">
    <div>
        <h2>📊 仪表盘</h2>
        <div class="sub">经营概览 · <?= e(date('Y年m月d日')) ?></div>
    </div>
    <div class="page-actions">
        <a class="btn sm gray" href="<?= au('orders') ?>">全部订单</a>
        <a class="btn sm" href="<?= au('products') ?>">商品管理</a>
    </div>
</div>

<div class="stat-grid">
    <div class="stat"><div class="s-label">今日成功订单</div><div class="s-val v-primary"><?= (int)$stats['today_orders'] ?></div><div class="s-sub">昨日 <?= (int)$stats['yesterday_orders'] ?> 单</div></div>
    <div class="stat"><div class="s-label">今日销售额</div><div class="s-val v-ok">¥<?= e(nf($stats['today_amount'])) ?></div><div class="s-sub">昨日 ¥<?= e(nf($stats['yesterday_amount'])) ?></div></div>
    <div class="stat"><div class="s-label">累计销售额</div><div class="s-val">¥<?= e(nf($stats['total_amount'])) ?></div><div class="s-sub">累计 <?= (int)$stats['total_orders'] ?> 单</div></div>
    <div class="stat"><div class="s-label">进行中订单</div><div class="s-val v-warn"><?= (int)$stats['pending_orders'] ?></div><div class="s-sub">待人工处理 <?= (int)$stats['pending_cards'] ?> 单</div></div>
    <div class="stat"><div class="s-label">剩余卡密库存</div><div class="s-val"><?= (int)$stats['cards_left'] ?></div><div class="s-sub">全部商品合计</div></div>
</div>

<div class="card">
    <h3>近7日成交趋势</h3>
    <div class="week-chart">
        <?php $max = 1; foreach ($week as $w) $max = max($max, $w['count']); ?>
        <?php foreach ($week as $w): ?>
            <div class="w-bar">
                <div title="<?= $w['count'] ?> 单" style="height:100%;display:flex;align-items:flex-end;justify-content:center;width:100%">
                    <i style="height:<?= max(4, (int)($w['count'] / $max * 105)) ?>px"></i>
                </div>
                <span><?= (int)$w['count'] ?></span>
                <span><?= e($w['day']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <h3>库存预警(少于10张)</h3>
    <?php if (!$lowStock): ?><p class="dim">库存充足, 无预警商品 ✓</p>
    <?php else: ?>
        <table class="tb">
            <tr><th>商品</th><th>剩余库存</th><th></th></tr>
            <?php foreach ($lowStock as $p): ?>
                <tr><td><?= e($p['name']) ?></td>
                    <td><span class="tag <?= $p['stock'] == 0 ? 'bad' : 'warn' ?>"><?= (int)$p['stock'] ?></span></td>
                    <td class="actions"><a class="btn sm gray" href="<?= au('cards', ['product_id' => $p['id']]) ?>">去补货</a></td></tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h3>最新订单</h3>
    <table class="tb">
        <tr><th>订单号</th><th>商品</th><th>金额</th><th>状态</th><th>时间</th><th></th></tr>
        <?php foreach ($recent as $o): ?>
            <tr>
                <td class="mono"><?= e($o['sn']) ?></td>
                <td><?= e($o['product_name']) ?> × <?= (int)$o['num'] ?></td>
                <td>¥<?= e(nf($o['total'])) ?></td>
                <td><span class="tag <?= (int)$o['status'] === 1 ? 'ok' : ((int)$o['status'] === 0 ? 'warn' : 'bad') ?>"><?= (int)$o['status'] === 1 ? '已完成' : ((int)$o['status'] === 0 ? '待支付' : ((int)$o['status'] === 2 ? '已过期' : '待处理')) ?></span></td>
                <td class="dim"><?= e(date('m-d H:i:s', $o['created_at'])) ?></td>
                <td class="actions"><a class="btn sm gray" href="<?= au('order_detail', ['id' => $o['id']]) ?>">详情</a></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
