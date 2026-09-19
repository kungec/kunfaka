<?php /** 坤发卡 二次元主题 布局 v2 */ ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#ff7eb3">
<title><?= e(isset($pageTitle) ? $pageTitle : setting('site_name', '坤发卡')) ?></title>
<link rel="stylesheet" href="<?= $_theme_url ?>assets/css/style.css?v=2.0.0">
</head>
<body>
<div class="sakura-bg" aria-hidden="true">
    <span></span><span></span><span></span><span></span><span></span><span></span>
    <span></span><span></span><span></span><span></span><span></span><span></span>
</div>
<header class="site-header">
    <div class="wrap header-inner">
        <a class="logo" href="<?= u('home/index') ?>"><span class="logo-icon">✦</span><?= e(setting('site_name', '坤发卡')) ?></a>
        <button class="nav-burger" id="navBurger" aria-label="打开菜单"><span></span><span></span><span></span></button>
        <nav class="nav" id="siteNav">
            <a href="<?= u('home/index') ?>">商品首页</a>
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
    </div>
</header>
<?php $latestNotice = latest_notice(); if ($latestNotice): ?>
<div class="wrap"><?php if ($latestNotice['id'] > 0): ?><a class="announce" href="<?= u('notice/detail', ['id' => $latestNotice['id']]) ?>">📢 <?= e($latestNotice['title']) ?> <span class="announce-more">详情 ›</span></a><?php else: ?><div class="announce">📢 <?= e($latestNotice['title']) ?></div><?php endif; ?></div>
<?php endif; ?>
<main class="wrap main"><?= $content ?></main>
<footer class="site-footer">
    <div class="wrap">
        <p><?= e(setting('site_name', '坤发卡')) ?> · 全自动发卡系统 <span class="powered">Powered by 坤发卡</span></p>
    </div>
</footer>
<?php $csList = service_contacts(); if ($csList): ?>
<div class="cs-wrap">
    <div class="cs-panel" id="csPanel">
        <div class="cs-head">联系客服 <span class="cs-close" id="csClose">×</span></div>
        <?php foreach ($csList as $c): $csLink = service_contact_link($c['type'], $c['value']); ?>
            <div class="cs-item">
                <span class="cs-tag"><?= e(contact_type_label($c['type'])) ?></span>
                <?php if ($c['note']): ?><span class="cs-note"><?= e($c['note']) ?></span><?php endif; ?>
                <span class="cs-val mono"><?= e($c['value']) ?></span>
                <?php if ($csLink !== ''): ?>
                    <a class="cs-act" href="<?= e($csLink) ?>" <?= $c['type'] === 'telegram' ? 'target="_blank" rel="noopener"' : '' ?>><?= $c['type'] === 'telegram' ? '前往' : '拨打' ?></a>
                <?php else: ?>
                    <button type="button" class="cs-act" data-copy="<?= e($c['value']) ?>">复制</button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <div class="cs-tip">添加后请备注订单号, 方便快速处理 ✨</div>
    </div>
    <button class="cs-btn" id="csBtn" aria-label="联系客服">💬</button>
</div>
<?php endif; ?>
<script src="<?= site_url('assets/js/qrcode.min.js') ?>"></script>
<script src="<?= site_url('assets/js/app.js') ?>"></script>
</body>
</html>
