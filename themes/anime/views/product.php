<?php /** 二次元主题 商品详情/下单 */ ?>
<div class="panel product-panel">
    <h1 class="panel-title"><?= e($product['name']) ?></h1>
    <div class="product-meta">
        <span>💰 单价 <b class="price-text">¥<?= e(nf($product['price'])) ?></b></span>
        <span>📦 库存 <b class="<?= $stock > 0 ? 'ok-text' : 'bad-text' ?>"><?= $stock ?></b> 件</span>
        <span>⚡ 支付成功立即自动发货</span>
    </div>
    <?php if (!empty($product['icon'])): ?>
        <img src="<?= e(site_url(product_image_url($product['icon']))) ?>" alt="<?= e($product['name']) ?>" style="width:auto;max-width:100%;height:auto;display:block;border-radius:12px;margin:10px 0">
    <?php endif; ?>
    <div class="product-desc"><?= nl2br(e($product['description'])) ?></div>
    <form class="buy-form" method="post" action="<?= u('buy/create') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
        <div class="form-row">
            <label>购买数量</label>
            <div class="num-step" data-min="<?= (int)$product['min_num'] ?>" data-max="<?= max(1, (int)$product['max_num']) ?>">
                <button type="button" class="ns-btn" data-act="minus" aria-label="减少数量">−</button>
                <input type="number" name="num" value="<?= e($product['min_num']) ?>" min="<?= (int)$product['min_num'] ?>"
                       max="<?= max(1, (int)$product['max_num']) ?>" required>
                <button type="button" class="ns-btn" data-act="plus" aria-label="增加数量">＋</button>
            </div>
        </div>
        <div class="form-row">
            <label>联系方式</label>
            <div class="contact-line">
                <div class="contact-types">
                    <?php $first = true; foreach ($contactTypes as $ct): ?>
                        <label class="ct-item">
                            <input type="radio" name="contact_type" value="<?= e($ct) ?>" data-ph="<?= e(contact_type_placeholder($ct)) ?>" <?= $first ? 'checked' : '' ?>>
                            <?= e(contact_type_label($ct)) ?>
                        </label>
                    <?php $first = false; endforeach; ?>
                </div>
                <input type="text" name="contact" id="contactInput" value="<?= e($contactPrefill) ?>"
                       placeholder="<?= e(contact_type_placeholder($contactTypes[0])) ?>" required>
            </div>
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
        <?php if (in_array('name', $contactExtra ?? [], true)): ?>
        <div class="form-row"><label>收件人姓名</label><input type="text" name="ship_name" maxlength="50" placeholder="收件人姓名"></div>
        <?php endif; ?>
        <?php if (in_array('address', $contactExtra ?? [], true)): ?>
        <div class="form-row"><label>邮寄地址</label><input type="text" name="ship_address" maxlength="200" placeholder="省 / 市 / 区 + 详细地址"></div>
        <?php endif; ?>
        <?php if (Captcha::mode() !== 'off'): ?><div class="form-row buy-captcha"><?= Captcha::render('order') ?></div><?php endif; ?>
        <?php if ($payments): ?>
        <div class="form-row">
            <label>支付方式</label>
            <div class="pay-list pay-compact">
                <?php $pi = 0; foreach ($payments as $pm): ?>
                    <label class="pay-item<?= $pi === 0 ? ' selected' : '' ?>">
                        <input type="radio" name="pay_choice" value="<?= e($pm['code']) ?>" data-channel="<?= e($pm['channel'] ?? '') ?>" <?= $pi === 0 ? 'checked' : '' ?>>
                        <span class="pay-icon"><?= $pm['icon'] ?></span>
                        <span class="pay-name"><?= e($pm['title']) ?></span>
                    </label>
                <?php $pi++; endforeach; ?>
            </div>
            <input type="hidden" name="plugin" id="payPlugin" value="<?= e($payments[0]['code']) ?>">
            <input type="hidden" name="channel" id="payChannel" value="<?= e($payments[0]['channel'] ?? '') ?>">
        </div>
        <?php endif; ?>
        <button class="btn-buy big" type="submit" <?= $stock <= 0 ? 'disabled' : '' ?>>下单购买 · 全自动秒发</button>
    </form>
</div>
<script>
/* 购买数量步进器 */
(function () {
    var step = document.querySelector('.num-step');
    if (!step) return;
    var input = step.querySelector('input');
    var min = parseInt(step.getAttribute('data-min'), 10) || 1;
    var max = parseInt(step.getAttribute('data-max'), 10) || 999999;
    function clamp(v) { v = parseInt(v, 10); if (isNaN(v)) v = min; return Math.max(min, Math.min(max, v)); }
    step.addEventListener('click', function (ev) {
        var btn = ev.target.closest ? ev.target.closest('.ns-btn') : null;
        if (!btn) return;
        var cur = clamp(input.value);
        input.value = btn.getAttribute('data-act') === 'plus' ? clamp(cur + 1) : clamp(cur - 1);
    });
    input.addEventListener('change', function () { input.value = clamp(input.value); });
})();
</script>
<?php if ($payments): ?>
<script>
(function () {
    var radios = document.querySelectorAll('input[name="pay_choice"]');
    var plugin = document.getElementById('payPlugin');
    var channel = document.getElementById('payChannel');
    function sync() {
        radios.forEach(function (r) {
            if (r.checked) { plugin.value = r.value; channel.value = r.getAttribute('data-channel') || ''; }
        });
    }
    radios.forEach(function (r) { r.addEventListener('change', sync); });
    sync();
})();
</script>
<?php endif; ?>
