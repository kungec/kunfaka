<?php /** 二次元主题 商品详情/下单 */ ?>
<div class="panel product-panel">
    <h1 class="panel-title"><?= e($product['name']) ?></h1>
    <div class="product-meta">
        <span>💰 单价 <b class="price-text">¥<?= e(nf($product['price'])) ?></b></span>
        <span>📦 库存 <b class="<?= $stock > 0 ? 'ok-text' : 'bad-text' ?>"><?= $stock ?></b> 件</span>
        <span>⚡ 支付成功立即自动发货</span>
    </div>
    <div class="product-desc"><?= nl2br(e($product['description'])) ?></div>
    <form class="buy-form" method="post" action="<?= u('buy/create') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
        <div class="form-row">
            <label>购买数量</label>
            <input type="number" name="num" value="<?= e($product['min_num']) ?>" min="<?= (int)$product['min_num'] ?>"
                   max="<?= max(1, (int)$product['max_num']) ?>" required>
        </div>
        <div class="form-row">
            <label>联系方式类型</label>
            <div class="contact-types">
                <?php $first = true; foreach ($contactTypes as $ct): ?>
                    <label class="ct-item">
                        <input type="radio" name="contact_type" value="<?= e($ct) ?>" data-ph="<?= e(contact_type_placeholder($ct)) ?>" <?= $first ? 'checked' : '' ?>>
                        <?= e(contact_type_label($ct)) ?>
                    </label>
                <?php $first = false; endforeach; ?>
            </div>
        </div>
        <div class="form-row">
            <label>联系方式账号(用于接收卡密与查询订单)</label>
            <input type="text" name="contact" id="contactInput" value="<?= e($contactPrefill) ?>"
                   placeholder="<?= e(contact_type_placeholder($contactTypes[0])) ?>" required>
        </div>
        <script>
        (function () {
            var input = document.getElementById('contactInput');
            if (!input) return;
            var presets = {};
            document.querySelectorAll('input[name="contact_type"]').forEach(function (r) {
                presets[r.value] = r.getAttribute('data-ph');
                r.addEventListener('change', function () {
                    input.placeholder = presets[r.value] || '';
                });
            });
        })();
        </script>
        <button class="btn-buy big" type="submit" <?= $stock <= 0 ? 'disabled' : '' ?>>下单购买 · 全自动秒发</button>
    </form>
</div>
