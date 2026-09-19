<?php /** 二次元主题 首页: Hero + 分类 + 商品网格 */ ?>
<section class="hero">
    <div class="hero-blob b1"></div>
    <div class="hero-blob b2"></div>
    <h1 class="hero-title">全自动发卡<span>·</span>支付即发货</h1>
    <p class="hero-sub">⚡ 秒级自动发货 &nbsp;·&nbsp; 🔒 官方支付通道 &nbsp;·&nbsp; 🎫 卡密实时到账 &nbsp;·&nbsp; 💌 邮件同步备份</p>
    <span class="hero-chip">全部商品支付成功后立即自动发货, 全天候无人值守 ✨</span>
</section>

<div class="cat-tabs">
    <a class="cat-tab<?= $catId === 0 ? ' active' : '' ?>" href="<?= u('home/index') ?>">全部</a>
    <?php foreach ($categories as $c): ?>
        <a class="cat-tab<?= $catId === (int)$c['id'] ? ' active' : '' ?>" href="<?= u('home/index', ['cat' => $c['id']]) ?>"><?= e($c['name']) ?></a>
    <?php endforeach; ?>
</div>

<?php if (!$products): ?>
    <div class="panel">
        <div class="empty-box">
            <div class="empty-face">´•̥̥̥ω•̥̥̥`</div>
            <p>暂时还没有上架商品, 管理员快去后台添加吧~</p>
        </div>
    </div>
<?php else: ?>
    <div class="goods-grid">
        <?php foreach ($products as $p): $s = isset($stock[$p['id']]) ? $stock[$p['id']] : 0; ?>
            <div class="goods-card">
                <div class="goods-head">
                    <span class="goods-cat"><?php if (!empty($p['icon'])): ?><img src="<?= e(site_url($p['icon'])) ?>" alt="" style="width:16px;height:16px;object-fit:cover;border-radius:4px;vertical-align:-3px;margin-right:5px"><?php endif; ?><?= e($p['name']) ?></span>
                    <span class="goods-badge<?= $s > 0 ? '' : ' soldout' ?>"><?= $s > 0 ? '有货' : '缺货' ?></span>
                </div>
                <div class="goods-desc"><?= nl2br(e(mb_substr(trim(strip_tags($p['description'])), 0, 60))) ?: '快速发货 · 支付即到' ?></div>
                <div class="goods-foot">
                    <span class="goods-price">¥<?= e(nf($p['price'])) ?></span>
                    <a class="btn-buy" href="<?= u('buy/index', ['product_id' => $p['id']]) ?>">立即购买</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
