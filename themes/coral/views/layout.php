<?php /** 坤发卡 暖橙商城主题 布局(橙色渐变顶栏+大搜索+圆形快捷图标) */ ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#ff7a45">
<title><?= e(isset($pageTitle) ? $pageTitle : setting('site_name', '坤发卡')) ?></title>
<link rel="icon" href="<?= e(site_url('favicon.ico')) ?>" sizes="64x64">
<link rel="stylesheet" href="<?= $_theme_url ?>assets/css/style.css?v=1.0.0">
</head>
<body>
<header class="topbar">
    <div class="wrap tb-inner">
        <a class="logo" href="<?= u('home/index') ?>"><?php $logoHtml = site_logo_html(); echo $logoHtml !== null ? $logoHtml : '<span class="logo-mark">' . e(setting('site_name', '坤发卡')) . '</span>'; ?></a>
        <form class="tb-search" action="<?= u('home/index') ?>" method="get">
            <input type="text" name="kw" value="<?= e(trim((string)($_GET['kw'] ?? ''))) ?>" placeholder="搜索商品关键词" aria-label="搜索商品">
            <button type="submit">搜 索</button>
        </form>
        <button class="nav-burger" id="navBurger" aria-label="打开菜单"><span></span><span></span><span></span></button>
        <nav class="tb-rounds" id="siteNav">
            <a class="round-act" href="<?= u('order/query') ?>" title="订单查询"><span class="rc">🔍</span><small>订单</small></a>
            <a class="round-act" href="<?= u('notice/index') ?>" title="公告"><span class="rc">📢</span><small>公告</a>
            <?php if (singlepage_open()): ?><a class="round-act" href="<?= u('notice/page') ?>" title="<?= e(setting('singlepage_title', '关于我们')) ?>"><span class="rc">ℹ️</span><small>关于</small></a><?php endif; ?>
            <?php if (current_user_id()): ?>
                <a class="round-act" href="<?= u('user/orders') ?>" title="我的订单"><span class="rc">🧾</span><small>我的</small></a>
                <a class="round-act" href="<?= u('user/logout') ?>" title="退出"><span class="rc">🚪</span><small>退出</small></a>
            <?php elseif (member_open()): ?>
                <a class="round-act" href="<?= u('user/login') ?>" title="登录 / 注册"><span class="rc">👤</span><small>登录</small></a>
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
    <button class="cs-btn" id="csBtn" aria-label="联系客服">客服</button>
</div>
<?php endif; ?>

<script src="<?= site_url('assets/js/qrcode.min.js') ?>"></script>
<script src="<?= site_url('assets/js/app.js') ?>"></script>
</body>
</html>
