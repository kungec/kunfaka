<?php /** 管理后台布局 v5「暗夜」(极简暗色 + 明暗切换) */
/* 侧栏选中态: 视图变量因capture作用域无法传入layout, 改由当前路由推断 */
$__seg = explode('/', strtolower(trim((string)($_GET['s'] ?? 'dashboard'), '/')))[0];
$__menuMap = ['order_detail' => 'orders', 'order_deliver' => 'orders', 'order_del' => 'orders', 'app_config' => 'apps', 'update_run' => 'dashboard', 'update_check' => 'dashboard'];
$activeMenu = isset($__menuMap[$__seg]) ? $__menuMap[$__seg] : $__seg;
?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0e1013">
<title><?= e(isset($pageTitle) ? $pageTitle : '管理后台') ?> - 坤发卡</title>
<link rel="stylesheet" href="<?= site_url('assets/css/admin.css') ?>?v=<?= YF_VERSION ?>">
<script>
(function () {
    var t = null;
    try { t = localStorage.getItem('yf-admin-theme'); } catch (e) {}
    if (t === 'light') document.documentElement.setAttribute('data-theme', 'light');
    else document.documentElement.setAttribute('data-theme', 'dark');
})();
</script>
</head>
<body>
<div class="admin-shell">
    <div class="side-mask" id="sideMask"></div>
    <aside class="side" id="sideNav">
        <div class="side-logo">
            <em class="mark">坤</em>
            <div><b>坤发卡</b><small>ADMIN</small></div>
        </div>
        <nav class="side-nav">
            <a href="<?= au('dashboard') ?>" class="<?= (isset($activeMenu) && $activeMenu === 'dashboard') ? 'on' : '' ?>"><span class="ico">📊</span>控制台</a>
            <div class="side-group">经营</div>
            <a href="<?= au('orders') ?>" class="<?= (isset($activeMenu) && $activeMenu === 'orders') ? 'on' : '' ?>"><span class="ico">🧾</span>订单管理</a>
            <a href="<?= au('users') ?>" class="<?= (isset($activeMenu) && $activeMenu === 'users') ? 'on' : '' ?>"><span class="ico">👥</span>会员管理</a>
            <a href="<?= au('member_levels') ?>" class="<?= (isset($activeMenu) && $activeMenu === 'member_levels') ? 'on' : '' ?>"><span class="ico">🏅</span>会员等级</a>
            <a href="<?= au('products') ?>" class="<?= (isset($activeMenu) && $activeMenu === 'products') ? 'on' : '' ?>"><span class="ico">📦</span>商品管理</a>
            <a href="<?= au('categories') ?>" class="<?= (isset($activeMenu) && $activeMenu === 'categories') ? 'on' : '' ?>"><span class="ico">🗂</span>分类管理</a>
            <a href="<?= au('notices') ?>" class="<?= (isset($activeMenu) && $activeMenu === 'notices') ? 'on' : '' ?>"><span class="ico">📣</span>公告单页</a>
            <a href="<?= au('cards') ?>" class="<?= (isset($activeMenu) && $activeMenu === 'cards') ? 'on' : '' ?>"><span class="ico">🔑</span>卡密管理</a>
            <div class="side-group">应用</div>
            <a href="<?= au('apps', ['type' => 'payment']) ?>" class="<?= (isset($activeMenu) && $activeMenu === 'apps') ? 'on' : '' ?>"><span class="ico">🛒</span>应用商店</a>
            <a href="<?= au('license') ?>" class="<?= (isset($activeMenu) && $activeMenu === 'license') ? 'on' : '' ?>"><span class="ico">👑</span>授权中心</a>
            <div class="side-group">系统</div>
            <a href="<?= au('logs') ?>" class="<?= (isset($activeMenu) && $activeMenu === 'logs') ? 'on' : '' ?>"><span class="ico">📜</span>操作日志</a>
            <?php $curAdm = current_admin(); ?>
            <a href="<?= au('profile') ?>" class="<?= (isset($activeMenu) && $activeMenu === 'profile') ? 'on' : '' ?>"><span class="ico">👤</span>个人设置</a>
            <?php if ($curAdm && $curAdm['role'] === 'super'): ?>
            <a href="<?= au('admins') ?>" class="<?= (isset($activeMenu) && $activeMenu === 'admins') ? 'on' : '' ?>"><span class="ico">🛡</span>管理员</a>
            <?php endif; ?>
            <a href="<?= au('settings') ?>" class="<?= (isset($activeMenu) && $activeMenu === 'settings') ? 'on' : '' ?>"><span class="ico">⚙️</span>系统设置</a>
        </nav>
        <div class="side-foot">
            <a href="<?= site_url('index.php') ?>" target="_blank">🌐 前台</a>
            <a href="<?= au('logout') ?>">退出</a>
        </div>
    </aside>
    <div class="admin-main">
        <header class="admin-topbar">
            <div class="topbar-left">
                <button class="burger" id="sideBurger" aria-label="菜单">☰</button>
                <span class="topbar-title"><?= e(isset($pageTitle) ? $pageTitle : '控制台') ?></span>
            </div>
            <div class="topbar-right">
                <span class="license-tag <?= License::isPro() ? 'pro' : '' ?>"><?= License::isPro() ? '👑 专业版' : '免费版' ?></span>
                <button type="button" class="theme-btn" id="adminThemeBtn" aria-label="切换主题">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M19.1 4.9l-1.4 1.4M6.3 17.7l-1.4 1.4"/></svg>
                </button>
                <a href="<?= au('profile') ?>" style="text-decoration:none"><span class="admin-user"><?= e(isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : '管理员') ?></span></a>
            </div>
        </header>
        <div class="admin-content"><?= $content ?></div>
    </div>
</div>
<script>
/* 弹窗兜底定义: 若 admin.js 未加载成功(缓存/CDN异常), 回退原生弹窗保证操作可用 */
if (typeof kAlert !== 'function') {
    window.kAlert = function (msg, cb) { window.alert(msg || ''); if (cb) cb(); };
}
if (typeof kConfirm !== 'function') {
    window.kConfirm = function (msg, onOk) { if (window.confirm(msg || '确定?')) { if (onOk) onOk(); } };
}
</script>
<script src="<?= site_url('assets/js/admin.js') ?>?v=<?= YF_VERSION ?>"></script>
<script>
(function () {
    var btn = document.getElementById('adminThemeBtn');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var light = document.documentElement.getAttribute('data-theme') === 'light';
        var next = light ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', next);
        try { localStorage.setItem('yf-admin-theme', next); } catch (e) {}
    });
})();
</script>
</body>
</html>
