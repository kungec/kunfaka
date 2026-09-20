<?php /** 云商城主题 首页: Hero横幅 + 分类彩卡 + 商品网格 */ ?>
<section class="hero">
    <span class="hero-ring" aria-hidden="true"></span>
    <span class="hero-orb o1" aria-hidden="true"></span>
    <span class="hero-orb o2" aria-hidden="true"></span>
    <span class="hero-orb o3" aria-hidden="true"></span>
    <span class="hero-badge">⚡ 全自动发卡系统</span>
    <h1 class="hero-title"><?= e(setting('site_name', '坤发卡')) ?></h1>
    <p class="hero-sub">支付成功立即自动发货 · 官方支付通道 · 卡密秒级到账 · 全天候无人值守</p>
    <a class="hero-cta" href="#goods">浏览商品
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h13M13 6l6 6-6 6"/></svg>
    </a>
    <span class="hero-chip">购买后卡密自动展示并发送至联系方式</span>
</section>

<?php if ($categories): ?>
<div class="sec-head">
    <h2 class="sec-title">按分类逛</h2>
    <a class="sec-link" href="<?= u('home/index') ?>">全部分类
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
    </a>
</div>
<div class="cat-grid">
    <?php foreach ($categories as $i => $c): $ci = $i % 8; ?>
        <a class="cat-card cc-<?= $ci ?><?= $catId === (int)$c['id'] ? ' active' : '' ?>" href="<?= u('home/index', ['cat' => $c['id']]) ?>">
            <span class="cat-ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 13.4L12.6 21.4a2 2 0 0 1-2.8 0l-6.2-6.2A2 2 0 0 1 3 13.8V5a2 2 0 0 1 2-2h8.8a2 2 0 0 1 1.4.6l6.2 6.2a2 2 0 0 1 0 2.8z" transform="scale(0.92) translate(1,1)"/><circle cx="7.8" cy="7.8" r="1.4" fill="currentColor" stroke="none"/></svg>
            </span>
            <span>
                <span class="cat-name"><?= e($c['name']) ?></span><br>
                <span class="cat-count"><?= e(isset($catCounts[$c['id']]) ? $catCounts[$c['id']] : '') ?></span>
            </span>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="sec-head" id="goods">
    <h2 class="sec-title"><?= $catId > 0 ? '分类商品' : '精选商品' ?></h2>
    <a class="sec-link" href="<?= u('order/query') ?>">订单查询
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
    </a>
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
        <?php foreach ($products as $i => $p): $s = isset($stock[$p['id']]) ? $stock[$p['id']] : 0; $gi = $i % 8; ?>
            <div class="goods-card">
                <div class="goods-visual gv-<?= $gi ?>">
                    <?php if (!empty($p['icon'])): ?>
                        <img src="<?= e(site_url(product_image_url($p['icon'], 'thumb'))) ?>" alt="<?= e($p['name']) ?>" style="width:100%;height:100%;object-fit:cover" loading="lazy">
                    <?php else: ?>
                        <span><?= ['🎁','💎','🎮','📱','🎧','⭐','🔥','🛒'][$gi] ?></span>
                    <?php endif; ?>
                    <span class="gv-stock<?= $s > 0 ? '' : ' soldout' ?>"><?= $s > 0 ? '有货' : '缺货' ?></span>
                </div>
                <div class="goods-body">
                    <span class="goods-cat"><?= e($p['cat_name'] ?? '商品') ?></span>
                    <div class="goods-name"><a href="<?= u('buy/index', ['product_id' => $p['id']]) ?>"><?= e($p['name']) ?></a></div>
                    <div class="goods-desc"><?= e(mb_substr(trim(strip_tags($p['description'])), 0, 42)) ?: '快速发货 · 支付即到' ?></div>
                    <div class="goods-foot">
                        <div style="display:flex;flex-direction:column;gap:2px">
                            <span class="goods-price"><small>¥</small><?= e(nf($p['price'])) ?></span>
                            <span class="g-sales">已售 <b><?= (int)($p['_sales'] ?? 0) ?></b></span>
                        </div>
                        <a class="btn-buy" href="<?= u('buy/index', ['product_id' => $p['id']]) ?>"><?= $s > 0 ? '立即购买' : '已缺货' ?></a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
