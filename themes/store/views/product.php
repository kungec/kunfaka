<?php /** 云商城主题 商品详情/下单: 左视觉右表单 */ ?>
<div class="product-grid">
    <div class="product-visual">
        <div class="pv-stage gv-0">
            <?php if (!empty($product['icon'])): ?>
                <img src="<?= e(site_url(product_image_url($product['icon']))) ?>" alt="<?= e($product['name']) ?>" style="width:auto;max-width:100%;height:auto;display:block;margin:0 auto;border-radius:inherit">
            <?php else: ?>
                <span>🎁</span>
            <?php endif; ?>
        </div>
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
            <div class="form-row">
                <label>支付方式</label>
                <?php if ($payments): ?>
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
                <?php else: ?>
                    <div class="desc">管理员尚未启用支付方式, 暂不可购买。</div>
                <?php endif; ?>
            </div>
            <div class="form-row">
                <button class="btn-buy big" type="submit" <?= ($stock <= 0 || !$payments) ? 'disabled' : '' ?>>⚡ 立即购买 · 自动秒发</button>
            </div>
            <p class="tip-line">支付成功后卡密立即展示, 并同步发送到您的联系方式</p>
        </form>
    </div>
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
            var label = r.closest('.pay-item');
            if (label) label.style.borderColor = r.checked ? 'var(--accent)' : '';
            if (r.checked) {
                plugin.value = r.value;
                channel.value = r.getAttribute('data-channel') || '';
            }
        });
    }
    radios.forEach(function (r) { r.addEventListener('change', sync); });
    sync();
})();
</script>
<?php endif; ?>
<?php if ($payments): ?>
<script>
(function () {
    var rows = document.querySelectorAll('.pay-opt input');
    var plugin = document.getElementById('payPlugin');
    var channel = document.getElementById('payChannel');
    function sync() {
        rows.forEach(function (r) {
            r.closest('.pay-opt').classList.toggle('on', r.checked);
            if (r.checked) {
                plugin.value = r.value;
                channel.value = r.getAttribute('data-channel') || '';
            }
        });
    }
    rows.forEach(function (r) { r.addEventListener('change', sync); });
    sync();
})();
</script>
<?php endif; ?>
