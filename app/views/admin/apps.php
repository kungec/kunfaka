<?php $activeMenu = 'apps'; $isTheme = $type === 'theme';
$curName = isset($_GET['name']) ? (string)$_GET['name'] : '';
$curAuthor = isset($_GET['author']) ? (string)$_GET['author'] : '';
$curFilter = isset($_GET['filter']) ? (string)$_GET['filter'] : '';
/* 页签链接保留名称/作者筛选 */
$tabLink = function ($filter) use ($curName, $curAuthor, $type) {
    $p = ['type' => $type];
    if ($curName !== '') $p['name'] = $curName;
    if ($curAuthor !== '') $p['author'] = $curAuthor;
    if ($filter !== '') $p['filter'] = $filter;
    return au('apps', $p);
};
?>
<style>
.mkt-tabs{display:flex;gap:4px;border-bottom:1px solid var(--input-border);margin-bottom:14px}
.mkt-tab{padding:11px 18px;font-size:13.5px;font-weight:700;color:var(--muted);text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-1px}
.mkt-tab.on{color:var(--text);border-bottom-color:var(--text)}
.mkt-hero{display:flex;align-items:center;justify-content:space-between;gap:14px;background:var(--info);color:#fff;border-radius:var(--radius);padding:20px 26px;text-decoration:none;margin-bottom:14px;transition:.15s}
.mkt-hero:hover{opacity:.92}
.mkt-hero .h-l{display:flex;align-items:center;gap:14px}
.mkt-hero .h-ico{font-size:22px}
.mkt-hero .h-t{font-size:17px;font-weight:800;letter-spacing:.3px}
.mkt-hero .h-s{font-size:12px;opacity:.85;margin-top:2px}
.mkt-hero .h-btn{flex:none;background:#fff;color:var(--info);font-weight:700;font-size:13px;padding:10px 20px;border-radius:9px;white-space:nowrap}
.mkt-hero.is-pro .h-btn{background:rgba(255,255,255,.18);color:#fff}
.mkt-hero.is-pro{background:var(--ok)}
.mkt-filters{display:flex;gap:9px;flex-wrap:wrap;align-items:flex-end}
.mkt-filters .mf label{display:block;font-size:11px;color:var(--muted);margin-bottom:4px;font-weight:600}
.mkt-filters input,.mkt-filters select{height:36px;padding:0 10px;font-size:12.5px}
.mkt-filters input[type=text]{width:210px}
.mkt-pills{display:flex;gap:7px;flex-wrap:wrap;margin-top:13px}
.mkt-pills a{padding:6px 15px;border-radius:8px;border:1.5px solid var(--input-border);font-size:12.5px;font-weight:600;color:var(--text2);text-decoration:none;transition:.12s}
.mkt-pills a.on{border-color:var(--text);color:var(--text);background:var(--input-bg)}
.mkt-pills a:hover{border-color:var(--muted)}
th.m-chk{width:34px;text-align:center}
td.m-ico{width:62px}
.m-ico img,.m-ico .ph{width:44px;height:44px;border-radius:10px;object-fit:cover;display:inline-flex;align-items:center;justify-content:center;font-size:20px;color:#fff}
.m-title b{display:block;font-size:13px;line-height:1.45}
.m-title small{color:var(--muted);font-size:10.5px}
.m-desc{font-size:12px;color:var(--text2);line-height:1.65;max-width:420px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical}
.m-price .tag{margin-right:4px}
td.m-act{white-space:normal}
td.m-act .btn{margin:2px 2px 2px 0}
</style>

<div class="mkt-tabs">
    <a class="mkt-tab<?= !$isTheme ? ' on' : '' ?>" href="<?= au('apps', ['type' => 'payment']) ?>">🧩 插件市场</a>
    <a class="mkt-tab<?= $isTheme ? ' on' : '' ?>" href="<?= au('apps', ['type' => 'theme']) ?>">🎨 主题市场</a>
</div>

<?php if (!License::isPro()): ?>
    <a class="mkt-hero" href="<?= au('license') ?>">
        <span class="h-l"><span class="h-ico">👑</span>
            <span><span class="h-t">开通专业版</span><br><span class="h-s">全部付费应用免费畅享 · 含USDT免挂支付等付费插件 · 一次开通 永久授权</span></span>
        </span>
        <span class="h-btn">立即开通 →</span>
    </a>
<?php else: ?>
    <a class="mkt-hero is-pro" href="<?= au('license') ?>">
        <span class="h-l"><span class="h-ico">👑</span>
            <span><span class="h-t">专业版授权已激活</span><br><span class="h-s">商店全部付费应用免费下载 · 后续新增应用同步免费</span></span>
        </span>
        <span class="h-btn">管理授权 →</span>
    </a>
<?php endif; ?>

<div class="card">
    <form method="get" action="<?= site_url('admin.php') ?>">
        <input type="hidden" name="s" value="/apps">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <input type="hidden" name="filter" value="<?= e($curFilter) ?>">
        <div class="mkt-filters">
            <div class="mf"><label>搜索应用</label><input type="text" name="name" value="<?= e($curName) ?>" placeholder="名称 / 简介"></div>
            <div class="mf"><label>作者</label>
                <select name="author">
                    <option value="">全部</option>
                    <?php foreach ($authors as $a): ?><option value="<?= e($a) ?>" <?= $curAuthor === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="mf"><button class="btn" type="submit" style="height:36px">🔍 查询</button></div>
        </div>
    </form>
    <div class="mkt-pills">
        <a href="<?= e($tabLink('')) ?>" class="<?= $curFilter === '' ? 'on' : '' ?>">全部</a>
        <a href="<?= e($tabLink('installed')) ?>" class="<?= $curFilter === 'installed' ? 'on' : '' ?>">已安装</a>
        <a href="<?= e($tabLink('pro')) ?>" class="<?= $curFilter === 'pro' ? 'on' : '' ?>">专业版应用</a>
        <a href="<?= e($tabLink('free')) ?>" class="<?= $curFilter === 'free' ? 'on' : '' ?>">免费应用</a>
        <a href="<?= e($tabLink('local')) ?>" class="<?= $curFilter === 'local' ? 'on' : '' ?>">内置应用</a>
        <a href="<?= e($tabLink('remote')) ?>" class="<?= $curFilter === 'remote' ? 'on' : '' ?>">市场应用</a>
    </div>
</div>

<div class="card">
    <table class="tb" id="appTable">
        <thead>
        <tr>
            <th>软件名称</th>
            <th>开发商</th>
            <th>类型</th>
            <th style="width:34%">简介</th>
            <th>版本</th>
            <th>价格</th>
            <th style="width:190px">操作</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($list as $app): $shot = isset($app['shot']) ? (string)$app['shot'] : ''; ?>
            <tr data-app="<?= e($app['name']) ?>" data-type="<?= e($type) ?>">
                <td class="m-ico">
                    <?php if ($shot !== ''): ?>
                        <img src="<?= e($shot) ?>" alt="<?= e($app['title']) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="ph shot-ph" data-seed="<?= e($app['name']) ?>"><?= e(mb_substr($app['title'], 0, 1)) ?></span>
                    <?php endif; ?>
                </td>
                <td class="m-title" style="min-width:150px"><b><?= e($app['title']) ?></b><small><?= e($app['name']) ?></small></td>
                <td>
                    <?php $official = mb_stripos((string)$app['author'], '官方') !== false || mb_stripos((string)$app['author'], '坤发卡') !== false; ?>
                    <span class="tag <?= $official ? 'blue' : '' ?>"><?= e($app['author']) ?></span>
                </td>
                <td>
                    <span class="tag <?= $isTheme ? '' : 'blue' ?>"><?= $isTheme ? '网站模版' : '支付接口' ?></span>
                    <?php if ($app['local']): ?><span class="tag">内置</span><?php else: ?><span class="tag warn">市场</span><?php endif; ?>
                </td>
                <td><div class="m-desc"><?= e($app['desc']) ?></div></td>
                <td class="dim">v<?= e($app['version']) ?><?= isset($app['remote_version']) && $app['remote_version'] !== '' && $app['remote_version'] !== $app['version'] ? '<br><small style="color:var(--info)">可更新 ' . e($app['remote_version']) . '</small>' : '' ?></td>
                <td class="m-price">
                    <?php if ($app['pro']): ?>
                        <?php if ($app['price'] > 0): ?><span class="tag bad">¥<?= e(nf($app['price'])) ?></span><?php endif; ?>
                        <span class="tag ok">专业版免费</span>
                    <?php elseif ($app['price'] > 0): ?>
                        <span class="tag warn">¥<?= e(nf($app['price'])) ?></span>
                    <?php else: ?>
                        <span class="tag ok">免费</span>
                    <?php endif; ?>
                </td>
                <td class="m-act">
                    <?php $proLocked = $app['pro'] && !License::isPro(); ?>
                    <?php if ($app['local']): ?>
                        <?php if (!$isTheme && $app['installed']): ?>
                            <?php if ($app['enabled']): ?>
                                <button class="btn sm gray" data-toggle="<?= e($app['name']) ?>"><?= $proLocked ? '停用(专业版)' : '停用' ?></button>
                                <a class="btn sm" href="<?= au('app_config', ['name' => $app['name'], 'type' => $type]) ?>">配置</a>
                            <?php endif; ?>
                            <?php if ($proLocked): ?>
                                <a class="btn sm green" href="<?= au('license') ?>">👑 开通专业版</a>
                            <?php elseif (!$app['enabled']): ?>
                                <button class="btn sm green" data-toggle="<?= e($app['name']) ?>">启用</button>
                            <?php endif; ?>
                        <?php elseif (!$isTheme): ?>
                            <?php if ($proLocked): ?>
                                <a class="btn sm green" href="<?= au('license') ?>">👑 开通专业版</a>
                            <?php else: ?>
                                <button class="btn sm green" data-install="<?= e($app['name']) ?>">安装</button>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($isTheme): ?>
                            <?php if ($proLocked): ?>
                                <a class="btn sm green" href="<?= au('license') ?>">👑 开通专业版</a>
                            <?php else: ?>
                                <button class="btn sm" data-usetheme="<?= e($app['name']) ?>">使用该主题</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($proLocked): ?>
                            <a class="btn sm green" href="<?= au('license') ?>">👑 开通专业版</a>
                        <?php else: ?>
                            <button class="btn sm green" data-download="<?= e($app['name']) ?>">下载安装</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="7"><div style="text-align:center;padding:34px 0;color:var(--muted)"><div style="font-size:34px;opacity:.55;margin-bottom:6px">🧩</div>没有符合条件的应用</div></td></tr><?php endif; ?>
        </tbody>
    </table>
    <div style="font-size:12px;color:var(--muted);padding:10px 2px 0">共 <?= count($list) ?> 个应用</div>
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
    var row = t.closest('[data-app]');
    var name = row.getAttribute('data-app');
    var type = row.getAttribute('data-type');
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
