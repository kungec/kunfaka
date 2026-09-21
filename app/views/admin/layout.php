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
<style>
/* 顶栏用户菜单(样式内联: 与HTML同步加载, 不受外部CSS缓存影响) */
.user-menu { position: relative; }
.admin-user {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 5px 12px 5px 6px; border-radius: 999px;
    border: 1px solid var(--input-border); background: var(--input-bg);
    color: var(--text); font-size: 12.5px; font-weight: 600; cursor: pointer;
    transition: border-color .15s, background .15s;
}
.admin-user:hover { border-color: var(--muted); }
.uu-ava {
    width: 22px; height: 22px; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 800; flex: none;
    background: linear-gradient(135deg, #a8c7fa, #8ab4f8); color: #0e2242;
}
html[data-theme="light"] .uu-ava { background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; }
.uu-caret { font-size: 9px; color: var(--muted); transition: transform .18s; }
.user-menu.open .uu-caret { transform: rotate(180deg); }
.um-panel {
    position: absolute; right: 0; top: calc(100% + 8px); z-index: 90;
    min-width: 210px; padding: 8px;
    background: var(--card); border: 1px solid var(--border); border-radius: 13px;
    box-shadow: 0 22px 50px -18px rgba(0,0,0,.55);
    display: none;
}
.user-menu.open .um-panel { display: block; }
.um-head {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    padding: 8px 10px 10px; border-bottom: 1px solid var(--input-border); margin-bottom: 6px;
}
.um-head b { font-size: 13px; }
.um-head small { color: var(--muted); font-size: 11px; }
.um-panel a {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 10px; border-radius: 8px;
    color: var(--text2); font-size: 12.5px; text-decoration: none;
    transition: background .12s, color .12s;
}
.um-panel a:hover { background: rgba(140,155,190,.1); color: var(--text); }
html[data-theme="light"] .um-panel a:hover { background: rgba(59,130,246,.07); }
.um-panel a.um-out { color: var(--bad); margin-top: 2px; }
.um-panel a.um-out:hover { background: var(--bad-bg); color: var(--bad); }
@media (max-width: 760px) {
    .admin-user { padding: 5px; border-radius: 50%; font-size: 0; }
    .admin-user .uu-caret { display: none; }
    .um-panel { min-width: 190px; }
}
</style>
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
            <svg class="lg-mark" viewBox="0 0 64 64" aria-hidden="true">
                <defs>
                    <linearGradient id="kfGold" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" stop-color="#f0d67a"/>
                        <stop offset=".45" stop-color="#d4af37"/>
                        <stop offset="1" stop-color="#a8842c"/>
                    </linearGradient>
                </defs>
                <circle cx="32" cy="32" r="29.5" fill="none" stroke="url(#kfGold)" stroke-width="2.2"/>
                <circle cx="32" cy="32" r="24" fill="none" stroke="url(#kfGold)" stroke-width=".9" opacity=".6" stroke-dasharray="1.5 3.2"/>
                <text x="32" y="34.5" text-anchor="middle" dominant-baseline="central" font-family="'Songti SC','STSong','SimSun','Noto Serif SC','Source Han Serif SC',serif" font-size="27" font-weight="900" fill="url(#kfGold)">坤</text>
                <g fill="url(#kfGold)">
                    <path d="M32 0l2.8 2.8L32 5.6 29.2 2.8z"/>
                    <path d="M32 58.4l2.8 2.8L32 64l-2.8-2.8z" opacity=".85"/>
                </g>
            </svg>
            <div><b>坤发卡</b><small>KUNFAKA · ADMIN</small></div>
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
                <div class="user-menu" id="userMenu">
                    <button type="button" class="admin-user" id="userMenuBtn" aria-haspopup="true"><span class="uu-ava"><?= e(mb_substr(($_SESSION['admin_name'] ?? '管'), 0, 1)) ?></span><?= e(isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : '管理员') ?><span class="uu-caret">▾</span></button>
                    <div class="um-panel" role="menu">
                        <div class="um-head">
                            <b><?= e(isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : '管理员') ?></b>
                            <small><?= License::isPro() ? '👑 专业版' : '免费版' ?></small>
                        </div>
                        <a href="<?= au('profile') ?>">👤 个人设置 / 修改密码</a>
                        <?php if ($curAdm && $curAdm['role'] === 'super'): ?><a href="<?= au('admins') ?>">🛡 管理员账号</a><?php endif; ?>
                        <a href="<?= au('logs') ?>">📜 操作日志</a>
                        <a href="<?= au('logout') ?>" class="um-out">🚪 退出登录</a>
                    </div>
                </div>
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
/* 顶栏用户菜单: 点击展开/收起, 点击面板外部自动收起 */
(function () {
    var wrap = document.getElementById('userMenu');
    var btn = document.getElementById('userMenuBtn');
    if (!wrap || !btn) return;
    btn.addEventListener('click', function (ev) {
        ev.stopPropagation();
        wrap.classList.toggle('open');
    });
    document.addEventListener('click', function (ev) {
        if (!wrap.contains(ev.target)) wrap.classList.remove('open');
    });
})();
</script>
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
