<?php /** 坤发卡 蔚蓝商城主题 布局(经典电商: 顶导航+公告条+左分类侧栏) */ ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#f4f5f9">
<title><?= e(isset($pageTitle) ? $pageTitle : setting('site_name', '坤发卡')) ?></title>
<link rel="icon" href="<?= e(site_url('favicon.ico')) ?>" sizes="64x64">
<link rel="stylesheet" href="<?= $_theme_url ?>assets/css/style.css?v=1.0.0">
</head>
<body>
<header class="topbar">
    <div class="wrap tb-inner">
        <a class="logo" href="<?= u('home/index') ?>"><?php $logoHtml = site_logo_html(); echo $logoHtml !== null ? $logoHtml : '<span class="logo-mark">' . e(mb_substr(setting('site_name', '坤'), 0, 1)) . '</span><span>' . e(setting('site_name', '坤发卡')) . '</span>'; ?></a>
        <button class="nav-burger" id="navBurger" aria-label="打开菜单"><span></span><span></span><span></span></button>
        <nav class="tb-nav" id="siteNav">
            <a href="<?= u('home/index') ?>" class="on">网站首页</a>
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
        <form class="tb-search" action="<?= u('order/query') ?>" method="get">
            <input type="text" name="kw" placeholder="订单号 / 邮箱 查订单..." aria-label="订单查询">
            <button type="submit">查订单</button>
        </form>
    </div>
</header>

<?php $latestNotice = latest_notice(); if ($latestNotice): ?>
<div class="wrap">
    <a class="announce" href="<?= $latestNotice['id'] > 0 ? u('notice/detail', ['id' => $latestNotice['id']]) : u('notice/index') ?>">
        <span class="announce-ico">📢</span>
        <?= e($latestNotice['title']) ?>
        <span class="announce-more">详情 ›</span>
    </a>
</div>
<?php endif; ?>

<div class="wrap layout-cols">
    <aside class="side-cat">
        <div class="sc-head">商品分类</div>
        <?php
        $azCats = cat_list();
        $azCounts = [];
        foreach (DB::fetchAll("SELECT category_id, COUNT(*) AS n FROM products WHERE status = 1 GROUP BY category_id") as $r) {
            $azCounts[(int)$r['category_id']] = (int)$r['n'];
        }
        $azCur = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
        ?>
        <a class="sc-item<?= $azCur === 0 ? ' on' : '' ?>" href="<?= u('home/index') ?>">全部商品</a>
        <?php foreach ($azCats as $c): ?>
            <a class="sc-item<?= $azCur === (int)$c['id'] ? ' on' : '' ?>" href="<?= u('home/index', ['cat' => $c['id']]) ?>"><?= e($c['name']) ?><?= isset($azCounts[(int)$c['id']]) ? ' (' . $azCounts[(int)$c['id']] . ')' : '' ?></a>
        <?php endforeach; ?>
    </aside>
    <main class="main-col"><?= $content ?></main>
</div>

<footer class="site-footer">
    <div class="wrap footer-bottom">
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
