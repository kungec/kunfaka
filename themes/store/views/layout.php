<?php /** 坤发卡 云商城主题 布局 */ ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#f5f5f7" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0a0a0b" media="(prefers-color-scheme: dark)">
<title><?= e(isset($pageTitle) ? $pageTitle : setting('site_name', '坤发卡')) ?></title>
<link rel="stylesheet" href="<?= $_theme_url ?>assets/css/style.css?v=1.0.0">
<script>
(function () {
    var t = null;
    try { t = localStorage.getItem('yf-theme'); } catch (e) {}
    if (t === 'dark' || t === 'light') document.documentElement.setAttribute('data-theme', t);
    else if (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
})();
</script>
</head>
<body>
<header class="site-header">
    <div class="wrap header-inner">
        <a class="logo" href="<?= u('home/index') ?>">
            <span class="logo-mark"><?= e(mb_substr(setting('site_name', '云'), 0, 1)) ?></span>
            <span><?= e(setting('site_name', '坤发卡')) ?></span>
        </a>
        <button class="nav-burger" id="navBurger" aria-label="打开菜单"><span></span><span></span><span></span></button>
        <nav class="nav" id="siteNav">
            <a href="<?= u('home/index') ?>">全部商品</a>
            <a href="<?= u('order/query') ?>">订单查询</a>
            <a href="<?= u('notice/index') ?>">公告</a>
            <?php if (singlepage_open()): ?><a href="<?= u('notice/page') ?>"><?= e(setting('singlepage_title', '关于我们')) ?></a><?php endif; ?>
            <?php if (current_user_id()): ?>
                <a href="<?= u('user/orders') ?>">我的订单</a>
                <span class="nav-user">👋 <?= e(isset($_SESSION['front_user_name']) ? $_SESSION['front_user_name'] : '') ?></span>
                <a href="<?= u('user/logout') ?>">退出</a>
            <?php elseif (member_open()): ?>
                <a href="<?= u('user/login') ?>">登录 / 注册</a>
            <?php endif; ?>
            <a href="<?= au('login') ?>">管理后台</a>
        </nav>
        <div class="header-acts">
            <button type="button" class="icon-btn" id="themeToggle" aria-label="切换主题" title="切换亮/暗模式">
                <svg class="ico-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2.4M12 19.1v2.4M2.5 12h2.4M19.1 12h2.4M4.9 4.9l1.7 1.7M17.4 17.4l1.7 1.7M19.1 4.9l-1.7 1.7M6.6 17.4l-1.7 1.7"/></svg>
            </button>
        </div>
    </div>
</header>

<?php $latestNotice = latest_notice(); if ($latestNotice): ?>
<div class="wrap"><a class="announce" href="<?= $latestNotice['id'] > 0 ? u('notice/detail', ['id' => $latestNotice['id']]) : u('notice/index') ?>">📢 <?= e($latestNotice['title']) ?><span class="announce-more">详情 ›</span></a></div>
<?php endif; ?>

<main class="wrap main"><?= $content ?></main>

<footer class="site-footer">
    <div class="wrap">
        <div class="footer-grid">
            <div class="f-brand">
                <div class="f-name"><?= e(setting('site_name', '坤发卡')) ?></div>
                <div class="f-sub">全自动发卡 · 支付成功立即自动发货</div>
            </div>
            <div class="f-col">
                <div class="f-title">商城</div>
                <a href="<?= u('home/index') ?>">全部商品</a>
                <a href="<?= u('order/query') ?>">订单查询</a>
                <a href="<?= u('notice/index') ?>">站点公告</a>
                <?php if (member_open()): ?><a href="<?= u('user/orders') ?>">个人中心</a><?php endif; ?>
            </div>
            <div class="f-col">
                <div class="f-title">账户</div>
                <?php if (member_open()): ?><a href="<?= u('user/login') ?>">登录 / 注册</a><?php endif; ?>
                <a href="<?= au('login') ?>">管理后台</a>
            </div>
            <div class="f-col">
                <div class="f-title">关于</div>
                <a href="<?= u('home/index') ?>">自助购买</a>
                <a href="<?= u('order/query') ?>">游客查单</a>
                <?php if (singlepage_open()): ?><a href="<?= u('notice/page') ?>"><?= e(setting('singlepage_title', '关于我们')) ?></a><?php endif; ?>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© <?= date('Y') ?> <?= e(setting('site_name', '坤发卡')) ?></span>
            <span class="powered">Powered by 坤发卡</span>
        </div>
    </div>
</footer>

<?php $csList = service_contacts(); if ($csList): ?>
<div class="cs-wrap">
    <div class="cs-panel" id="csPanel">
        <div class="cs-head">联系客服 <span class="cs-close" id="csClose">×</span></div>
        <?php foreach ($csList as $c): $csLink = service_contact_link($c['type'], $c['value']); ?>
            <div class="cs-item">
                <span class="cs-tag"><?= e(contact_type_label($c['type'])) ?></span>
                <span class="cs-val mono"><?= e($c['value']) ?></span>
                <?php if ($csLink !== ''): ?>
                    <a class="cs-act" href="<?= e($csLink) ?>" <?= $c['type'] === 'telegram' ? 'target="_blank" rel="noopener"' : '' ?>><?= $c['type'] === 'telegram' ? '前往' : '拨打' ?></a>
                <?php else: ?>
                    <button type="button" class="cs-act" data-copy="<?= e($c['value']) ?>">复制</button>
                <?php endif; ?>
                <?php if ($c['note']): ?><span class="cs-note"><?= e($c['note']) ?></span><?php endif; ?>
            </div>
        <?php endforeach; ?>
        <div class="cs-tip">添加后请备注订单号, 方便快速处理</div>
    </div>
    <button class="cs-btn" id="csBtn" aria-label="联系客服">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 13a8 8 0 1 1 16 0"/><path d="M20 16.5a2.5 2.5 0 0 1-2.5 2.5H15v-6h2.5a2.5 2.5 0 0 1 2.5 2.5v1zM4 16.5A2.5 2.5 0 0 0 6.5 19H9v-6H6.5A2.5 2.5 0 0 0 4 15.5v1z"/><path d="M4 13v3.5"/></svg>
    </button>
</div>
<?php endif; ?>

<script src="<?= site_url('assets/js/qrcode.min.js') ?>"></script>
<script src="<?= site_url('assets/js/app.js') ?>"></script>
<script>
(function () {
    var btn = document.getElementById('themeToggle');
    if (!btn) return;
    function apply() {
        var dark = document.documentElement.getAttribute('data-theme') === 'dark';
        btn.title = dark ? '切换到浅色模式' : '切换到深色模式';
    }
    apply();
    btn.addEventListener('click', function () {
        var dark = document.documentElement.getAttribute('data-theme') === 'dark';
        var next = dark ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        try { localStorage.setItem('yf-theme', next); } catch (e) {}
        apply();
    });
})();
</script>
</body>
</html>
