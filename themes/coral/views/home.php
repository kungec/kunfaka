<?php /** 坤发卡 暖橙商城主题 首页: banner活动卡+公告卡+分类胶囊+商品网格 */ ?>
<div class="home-top">
    <div class="promo-card">
        <div class="promo-txt">
            <b>⚡ 全自动发卡 · 秒级到账</b>
            <span>在线支付成功后卡密立即展示并发送到您的联系方式, 全天候无人值守</span>
        </div>
        <a class="promo-btn" href="#goods">去购买 ›</a>
    </div>
    <div class="notice-card">
        <div class="nc-head">📢 站点公告</div>
        <?php $latestNotice = latest_notice(); if ($latestNotice): ?>
            <a class="nc-item" href="<?= $latestNotice['id'] > 0 ? u('notice/detail', ['id' => $latestNotice['id']]) : u('notice/index') ?>"><?= e($latestNotice['title']) ?></a>
        <?php else: ?>
            <p class="nc-empty">暂无公告</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($categories): ?>
<div class="panel cat-panel">
    <h3 class="panel-title">商品分类</h3>
    <div class="cat-chips">
        <a class="cat-chip<?= $catId === 0 && trim((string)($_GET['kw'] ?? '')) === '' ? ' on' : '' ?>" href="<?= u('home/index') ?>">全部商品</a>
        <?php foreach ($categories as $c): ?>
            <a class="cat-chip<?= $catId === (int)$c['id'] ? ' on' : '' ?>" href="<?= u('home/index', ['cat' => $c['id']]) ?>"><?= e($c['name']) ?>（<?= e(isset($catCounts[$c['id']]) ? $catCounts[$c['id']] : 0) ?>）</a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php $searchKw = trim((string)($_GET['kw'] ?? '')); ?>
<div class="sec-head">
    <h2 class="sec-title"><?= $searchKw !== '' ? '「' . e($searchKw) . '」的搜索结果' : ($catId > 0 ? '分类商品' : '全部商品') ?></h2>
    <span class="sec-count">共 <?= count((array)$products) ?> 个商品</span>
</div>

<?php if (!$products): ?>
    <div class="panel">
        <div class="empty-box">
            <div class="empty-face">📦</div>
            <p>没有找到相关商品, 换个关键词或分类看看吧~</p>
            <a class="btn-buy" href="<?= u('home/index') ?>">返回全部商品</a>
        </div>
    </div>
<?php else: ?>
    <div class="goods-grid">
        <?php foreach ($products as $p): $s = isset($stock[$p['id']]) ? $stock[$p['id']] : 0; ?>
            <div class="goods-card">
                <a class="goods-visual" href="<?= u('buy/index', ['product_id' => $p['id']]) ?>">
                    <?php if (!empty($p['icon'])): ?>
                        <img src="<?= e(site_url(product_image_url($p['icon'], 'thumb'))) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="goods-ph">🎁</span>
                    <?php endif; ?>
                </a>
                <div class="goods-body">
                    <div class="goods-name"><a href="<?= u('buy/index', ['product_id' => $p['id']]) ?>"><?= e($p['name']) ?></a></div>
                    <div class="goods-foot">
                        <span class="goods-price"><small>¥</small><?= e(nf($p['price'])) ?></span>
                        <span class="goods-sold">已售 <?= e((string)($p['sales'] ?? 0)) ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
