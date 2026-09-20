<?php /** 蔚蓝商城主题 首页: 主区标题行 + 商品卡网格 */ ?>
<?php $azCatName = $catId > 0 ? (isset($categories[0]) ? '' : '') : ''; ?>
<div class="sec-head">
    <h2 class="sec-title"><?= $catId > 0 ? '分类商品' : '全部商品' ?></h2>
    <span class="sec-count">共 <?= count((array)$products) ?> 个商品</span>
</div>

<?php if (!$products): ?>
    <div class="panel">
        <div class="empty-box">
            <div class="empty-face">📦</div>
            <p>该分类下暂时没有商品, 去看看其他分类吧~</p>
            <a class="btn-buy" href="<?= u('home/index') ?>">返回全部商品</a>
        </div>
    </div>
<?php else: ?>
    <div class="goods-grid">
        <?php foreach ($products as $i => $p): $s = isset($stock[$p['id']]) ? $stock[$p['id']] : 0; ?>
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
                        <span class="goods-sold"><?= $s > 0 ? '有货' : '缺货' ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
