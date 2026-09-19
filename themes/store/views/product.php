<?php /** 云商城主题 商品详情/下单: 左视觉右表单 */ ?>
<div class="product-grid">
    <div class="product-visual">
        <div class="pv-stage gv-0"><span>🎁</span></div>
        <div class="pv-desc"><?= nl2br(e($product['description'])) ?></div>
    </div>
    <div class="buy-panel">
        <div class="p-cat"><?= e($product['cat_name'] ?? '商品') ?></div>
        <h1 class="p-name"><?= e($product['name']) ?></h1>
        <div class="p-meta">
            <span class="tag <?= $stock > 0 ? 'ok' : 'bad' ?>">📦 库存 <?= (int)$stock ?> 件</span>
            <span class="tag muted">⚡ 支付成功立即自动发货</span>
        </div>
        <div class="p-price-row">
            <div class="p-price-label">价格</div>
            <div class="p-price">¥<?= e(nf($product['price'])) ?><small> / 件</small></div>
        </div>
        <form class="buy-form" method="post" action="<?= u('buy/create') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
            <div class="form-row">
                <label>购买数量</label>
                <div class="qty-row">
                    <input type="number" name="num" value="<?= e($product['min_num']) ?>" min="<?= (int)$product['min_num'] ?>"
                           max="<?= max(1, (int)$product['max_num']) ?>" required>
                </div>
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
            <div class="form-row">
                <button class="btn-buy big" type="submit" <?= $stock <= 0 ? 'disabled' : '' ?>>⚡ 立即购买 · 自动秒发</button>
            </div>
            <p class="tip-line">支付成功后卡密立即展示, 并同步发送到您的联系方式</p>
        </form>
    </div>
</div>
