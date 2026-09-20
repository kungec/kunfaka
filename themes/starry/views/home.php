<?php /** 坤发卡 星野商城主题 首页: Hero大标题区 + 商品卡网格 */ ?>
<section class="hero">
    <h1 class="hero-title">自动发卡 · 即买即用</h1>
    <p class="hero-sub"><?= e(setting('site_name', '坤发卡')) ?> 提供全自动发卡服务 —— 在线支付成功后卡密立即展示并发送至您的联系方式, 全天候无人值守, 无需等待人工发货。</p>
    <div class="hero-acts">
        <a class="hero-btn primary" href="#goods">立即购买
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h13M13 6l6 6-6 6"/></svg>
        </a>
        <a class="hero-btn ghost" href="<?= u('order/query') ?>">订单查询</a>
    </div>
    <div class="hero-chips">
        <span class="hero-chip">🔒 支付安全</span>
        <span class="hero-chip">⚡ 自动秒发</span>
        <span class="hero-chip">💬 在线客服</span>
    </div>
    <div class="hero-glass">✨ 全部商品由系统自动发货, 支付成功后即可在订单页查看卡密</div>
</section>

<div class="sec-head" id="goods">
    <h2 class="sec-title">热门商品</h2>
    <span class="sec-count">共 <?= count((array)$products) ?> 个商品</span>
</div>

<?php if (!$products): ?>
    <div class="panel">
        <div class="empty-box">
            <div class="empty-face">📦</div>
            <p>暂时还没有上架商品, 管理员快去后台添加吧~</p>
            <a class="btn-buy" href="<?= u('home/index') ?>">刷新看看</a>
        </div>
    </div>
<?php else: ?>
    <div class="goods-grid">
        <?php foreach ($products as $i => $p): $s = isset($stock[$p['id']]) ? $stock[$p['id']] : 0; ?>
            <div class="goods-card">
                <div class="goods-visual">
                    <?php if (!empty($p['icon'])): ?>
                        <img src="<?= e(site_url(product_image_url($p['icon'], 'thumb'))) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="goods-ph">🎁</span>
                    <?php endif; ?>
                </div>
                <div class="goods-body">
                    <div class="goods-cat"><?= e($p['cat_name'] ?? '商品') ?><?= $s > 0 ? ' · 有货' : ' · 缺货' ?></div>
                    <div class="goods-name"><a href="<?= u('buy/index', ['product_id' => $p['id']]) ?>"><?= e($p['name']) ?></a></div>
                    <div class="goods-desc"><?= e(mb_substr(trim(strip_tags($p['description'])), 0, 50)) ?: '快速发货 · 支付即到' ?></div>
                    <div class="goods-foot">
                        <span class="goods-price"><small>¥</small><?= e(nf($p['price'])) ?><small class="from">起</small></span>
                        <a class="btn-buy" href="<?= u('buy/index', ['product_id' => $p['id']]) ?>"><?= $s > 0 ? '立即购买' : '已缺货' ?></a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
