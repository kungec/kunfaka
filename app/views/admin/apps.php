<?php $activeMenu = 'apps'; $isTheme = $type === 'theme'; ?>
<div class="store-tabs">
    <a class="store-tab<?= !$isTheme ? ' on' : '' ?>" href="<?= au('apps', ['type' => 'payment']) ?>">插件</a>
    <a class="store-tab<?= $isTheme ? ' on' : '' ?>" href="<?= au('apps', ['type' => 'theme']) ?>">主题</a>
</div>

<?php if (!License::isPro()): ?>
    <div class="store-banner">
        <span>💡 坤发卡<b>程序本体完全免费</b>, 收费内容仅限应用商店。开通 <b>99元专业版会员</b>, 商店全部付费插件与主题(含USDT免挂支付)免费畅享。</span>
        <a href="<?= au('license') ?>">前往授权中心开通 →</a>
    </div>
<?php else: ?>
    <div class="store-banner"><span>👑 专业版会员已激活, 程序完全免费 + 商店全部应用免费畅享。</span></div>
<?php endif; ?>
<?php if (!License::isAuthed() && !$isTheme): ?>
    <div class="alert info">登录官方会员账号(免费注册)后, 可下载官方市场持续上架的免费应用。当前内置应用无需登录即可使用。</div>
<?php endif; ?>

<div class="store-grid">
    <?php foreach ($list as $app): $shot = isset($app['shot']) ? (string)$app['shot'] : ''; ?>
        <div class="app-card">
            <div class="app-shot">
                <?php if ($shot !== ''): ?>
                    <img src="<?= e($shot) ?>" alt="<?= e($app['title']) ?> 演示截图" loading="lazy">
                <?php else: ?>
                    <div class="shot-ph" data-seed="<?= e($app['name']) ?>"><span><?= e(mb_substr($app['title'], 0, 1)) ?></span></div>
                <?php endif; ?>
            </div>
            <div class="app-head">
                <span class="app-title"><?= e($app['title']) ?></span>
                <?php if ($app['pro']): ?><span class="tag pro">专业版专享</span>
                <?php elseif ($app['price'] > 0): ?><span class="tag warn">¥<?= e(nf($app['price'])) ?></span>
                <?php else: ?><span class="tag ok">免费</span><?php endif; ?>
            </div>
            <div class="app-desc"><?= e($app['desc']) ?></div>
            <div class="app-meta">
                <span>v<?= e($app['version']) ?></span><span><?= e($app['author']) ?></span>
                <?php if ($app['local']): ?><span class="tag blue">内置</span><?php endif; ?>
                <?php if (!$isTheme && $app['installed']): ?>
                    <span class="tag <?= $app['enabled'] ? 'ok' : '' ?>"><?= $app['enabled'] ? '已启用' : '已停用' ?></span>
                <?php endif; ?>
                <?php if ($isTheme && setting('theme') === $app['name']): ?><span class="tag ok">当前使用</span><?php endif; ?>
            </div>
            <div class="app-foot" data-app="<?= e($app['name']) ?>" data-type="<?= e($type) ?>" data-pro="<?= $app['pro'] ? 1 : 0 ?>">
                <span class="app-price"><?= $app['pro'] ? '99会员免费' : ($app['price'] > 0 ? '¥' . nf($app['price']) : '免费') ?></span>
                <span>
                    <?php if ($app['local']): ?>
                        <?php if (!$isTheme && $app['installed']): ?>
                            <button class="btn sm gray" data-toggle="<?= e($app['name']) ?>"><?= $app['enabled'] ? '停用' : '启用' ?></button>
                            <?php if ($app['enabled']): ?><a class="btn sm" href="<?= au('app_config', ['name' => $app['name'], 'type' => $type]) ?>">配置</a><?php endif; ?>
                        <?php elseif (!$isTheme && !$app['installed']): ?>
                            <?php if ($app['pro'] && !License::isPro()): ?>
                                <button class="btn sm gray" disabled title="需开通99元专业版">安装(需专业版)</button>
                            <?php else: ?>
                                <button class="btn sm green" data-install="<?= e($app['name']) ?>">安装</button>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($isTheme): ?>
                            <?php if ($app['pro'] && !License::isPro()): ?>
                                <button class="btn sm gray" disabled title="需开通99元专业版">使用(需专业版)</button>
                            <?php else: ?>
                                <button class="btn sm" data-usetheme="<?= e($app['name']) ?>">使用该主题</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($app['pro'] && !License::isPro()): ?>
                            <button class="btn sm gray" disabled>下载(需专业版)</button>
                        <?php elseif (!License::isAuthed()): ?>
                            <button class="btn sm gray" disabled title="请先在授权中心登录官方账号">下载(需登录)</button>
                        <?php else: ?>
                            <button class="btn sm green" data-download="<?= e($app['name']) ?>">下载安装</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
/* 无截图条目的占位渐变(按应用名散列选色) */
(function () {
    var palettes = [
        ['#6366f1', '#8b5cf6'], ['#0ea5e9', '#22d3ee'], ['#f59e0b', '#f97316'],
        ['#10b981', '#34d399'], ['#f43f5e', '#fb7185'], ['#8b5cf6', '#d946ef'],
    ];
    document.querySelectorAll('.shot-ph').forEach(function (el) {
        var s = el.getAttribute('data-seed') || '', h = 0;
        for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0;
        var p = palettes[h % palettes.length];
        el.style.background = 'linear-gradient(135deg,' + p[0] + ',' + p[1] + ')';
    });
})();
function appPost(url, name, type) {
    var fd = new FormData();
    fd.append('_csrf', '<?= e(csrf_token()) ?>');
    fd.append('name', name);
    fd.append('type', type);
    return fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); });
}
document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-install],[data-toggle],[data-download],[data-usetheme]') : null;
    if (!t) return;
    var foot = t.closest('[data-app]');
    var name = foot.getAttribute('data-app');
    var type = foot.getAttribute('data-type');
    var url = null;
    if (t.hasAttribute('data-install')) url = '<?= au('app_install') ?>';
    else if (t.hasAttribute('data-toggle')) url = '<?= au('app_toggle') ?>';
    else if (t.hasAttribute('data-download')) url = '<?= au('app_download') ?>';
    else if (t.hasAttribute('data-usetheme')) url = '<?= au('theme_set') ?>';
    t.disabled = true;
    appPost(url, name, type).then(function (d) {
        alert(d.msg);
        t.disabled = false;
        if (d.code === 0) location.reload();
    }).catch(function () { t.disabled = false; alert('网络错误'); });
});
</script>
