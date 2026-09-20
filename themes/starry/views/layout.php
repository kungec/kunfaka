<?php /** 坤发卡 星野商城主题 布局(插画背景+毛玻璃导航+沉浸式) */ ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1b2350">
<title><?= e(isset($pageTitle) ? $pageTitle : setting('site_name', '坤发卡')) ?></title>
<link rel="icon" href="<?= e(site_url('favicon.ico')) ?>" sizes="64x64">
<link rel="stylesheet" href="<?= $_theme_url ?>assets/css/style.css?v=1.0.0">
</head>
<body>
<div class="sky" aria-hidden="true"><span class="star s1"></span><span class="star s2"></span><span class="star s3"></span><span class="star s4"></span><span class="star s5"></span><span class="star s6"></span><span class="sun"></span></div>

<header class="glassbar">
    <div class="wrap gb-inner">
        <a class="logo" href="<?= u('home/index') ?>"><?php $logoHtml = site_logo_html(); echo $logoHtml !== null ? $logoHtml : '<span class="logo-mark">✦</span><span>' . e(setting('site_name', '坤发卡')) . '</span>'; ?></a>
        <button class="nav-burger" id="navBurger" aria-label="打开菜单"><span></span><span></span><span></span></button>
        <nav class="gb-nav" id="siteNav">
            <a href="<?= u('home/index') ?>">首页</a>
            <a href="<?= u('home/index') ?>#goods">商品</a>
            <a href="<?= u('order/query') ?>">订单查询</a>
            <a href="<?= u('notice/index') ?>">公告</a>
            <?php if (singlepage_open()): ?><a href="<?= u('notice/page') ?>"><?= e(setting('singlepage_title', '关于我们')) ?></a><?php endif; ?>
            <?php if (current_user_id()): ?>
                <a href="<?= u('user/orders') ?>">我的订单</a>
                <a href="<?= u('user/logout') ?>">退出</a>
            <?php elseif (member_open()): ?>
                <a href="<?= u('user/login') ?>">登录 / 注册</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="wrap main"><?= $content ?></main>

<footer class="site-footer">
    <div class="wrap fb-inner">
        <span>© <?= date('Y') ?> <?= e(setting('site_name', '坤发卡')) ?> · 支付成功立即自动发货</span>
        <span class="powered">Powered by 坤发卡</span>
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
</body>
</html>
