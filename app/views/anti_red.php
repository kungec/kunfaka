<?php
/**
 * 微信/QQ防红引导页(独立完整HTML, 不依赖主题资源)
 * 变量: $hit = 'wx' | 'qq' (由 anti_red_intercept() 传入)
 */
$arUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
$arSite = setting('site_name', '坤发卡');
$arIsWx = $hit === 'wx';
$arBrandName = $arIsWx ? '微信' : 'QQ';
$arC1 = $arIsWx ? '#07c160' : '#12b7f5';
$arC2 = $arIsWx ? '#049143' : '#0a8fd0';
$arStep2 = $arIsWx ? '选择「在浏览器中打开」' : '选择「用浏览器打开」';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>请在浏览器中打开 - <?= e($arSite) ?></title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
html, body { height: 100%; }
body {
    font: 15px/1.6 -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif;
    background: #0b0e1d;
    background-image:
        radial-gradient(60% 50% at 20% 8%, <?= $arC1 ?>22 0%, transparent 62%),
        radial-gradient(55% 45% at 85% 90%, <?= $arC1 ?>1a 0%, transparent 60%),
        radial-gradient(45% 40% at 78% 15%, rgba(99, 102, 241, .16) 0%, transparent 65%);
    color: #eef0ff;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 26px 18px;
    overflow-x: hidden;
}
.ar-wrap { width: min(420px, 100%); animation: arUp .5s cubic-bezier(.2, .7, .3, 1) both; }
@keyframes arUp { from { opacity: 0; transform: translateY(22px); } to { opacity: 1; transform: none; } }
.ar-card {
    background: rgba(255, 255, 255, .055);
    border: 1px solid rgba(255, 255, 255, .12);
    border-radius: 24px;
    padding: 34px 26px 26px;
    text-align: center;
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    box-shadow: 0 34px 90px -30px rgba(0, 0, 0, .8);
}
.ar-ico {
    width: 76px; height: 76px; margin: 0 auto 18px;
    border-radius: 24px;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, <?= $arC1 ?>, <?= $arC2 ?>);
    box-shadow: 0 14px 38px -8px <?= $arC1 ?>80, inset 0 1px 0 rgba(255, 255, 255, .35);
    position: relative;
    animation: arFloat 3.2s ease-in-out infinite;
}
@keyframes arFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-7px); } }
.ar-ico::after {
    content: ''; position: absolute; inset: -9px;
    border-radius: 30px;
    border: 1.5px solid <?= $arC1 ?>55;
    animation: arPulse 2.2s ease-out infinite;
}
@keyframes arPulse { 0% { opacity: .9; transform: scale(.94); } 70% { opacity: 0; transform: scale(1.14); } 100% { opacity: 0; } }
.ar-ico svg { width: 40px; height: 40px; }
.ar-h1 { font-size: 20px; font-weight: 800; letter-spacing: .3px; }
.ar-h1 em { font-style: normal; color: <?= $arC1 ?>; }
.ar-sub { font-size: 13px; color: rgba(238, 240, 255, .62); margin-top: 7px; }
.ar-steps { margin: 22px 0 4px; text-align: left; }
.ar-step {
    display: flex; align-items: center; gap: 12px;
    background: rgba(255, 255, 255, .05);
    border: 1px solid rgba(255, 255, 255, .09);
    border-radius: 14px;
    padding: 12px 14px;
    margin-bottom: 9px;
}
.ar-n {
    flex: none; width: 24px; height: 24px;
    border-radius: 50%;
    background: linear-gradient(135deg, <?= $arC1 ?>, <?= $arC2 ?>);
    color: #fff; font-size: 12.5px; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 12px -2px <?= $arC1 ?>70;
}
.ar-step p { font-size: 13.5px; color: rgba(238, 240, 255, .9); }
.ar-step p b { color: #fff; }
.ar-url {
    display: flex; gap: 8px; margin-top: 16px;
    background: rgba(0, 0, 0, .3);
    border: 1px solid rgba(255, 255, 255, .1);
    border-radius: 13px;
    padding: 9px 9px 9px 14px;
    align-items: center;
}
.ar-url code {
    flex: 1; min-width: 0;
    font: 12px/1.5 ui-monospace, Menlo, Consolas, monospace;
    color: rgba(238, 240, 255, .55);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    direction: rtl; text-align: left;
}
.ar-copy {
    flex: none; border: none; cursor: pointer;
    height: 36px; padding: 0 16px;
    border-radius: 9px;
    background: linear-gradient(135deg, <?= $arC1 ?>, <?= $arC2 ?>);
    color: #fff; font-size: 13px; font-weight: 700; font-family: inherit;
    box-shadow: 0 6px 16px -4px <?= $arC1 ?>90;
    transition: transform .15s;
}
.ar-copy:active { transform: scale(.94); }
.ar-ghost {
    display: block; width: 100%; margin-top: 14px;
    height: 44px;
    border: 1.5px solid rgba(255, 255, 255, .16);
    border-radius: 13px;
    background: transparent;
    color: rgba(238, 240, 255, .68);
    font-size: 13.5px; font-weight: 600; font-family: inherit;
    cursor: pointer;
    transition: .15s;
}
.ar-ghost:hover { border-color: rgba(255, 255, 255, .3); color: #fff; }
.ar-tip { margin-top: 15px; font-size: 11.5px; color: rgba(238, 240, 255, .4); }
.ar-foot { margin-top: 20px; text-align: center; font-size: 12px; color: rgba(238, 240, 255, .34); letter-spacing: .5px; }
</style>
</head>
<body>
<div class="ar-wrap">
    <div class="ar-card">
        <div class="ar-ico">
            <?php if ($arIsWx): ?>
            <svg viewBox="0 0 48 48" fill="none">
                <path d="M18 6C9.7 6 3 11.8 3 19c0 4.2 2.3 8 5.9 10.4L7.5 34l5.3-2.9c1.6.5 3.4.8 5.2.8h.9a12.6 12.6 0 0 1-.3-2.7c0-7 6.6-12.6 14.7-12.6h.8C32.9 10.6 26.1 6 18 6z" fill="#fff" opacity=".96"/>
                <circle cx="12.5" cy="16.5" r="1.9" fill="<?= $arC1 ?>"/>
                <circle cx="23.5" cy="16.5" r="1.9" fill="<?= $arC1 ?>"/>
                <path d="M45 30.6c0-5.9-5.9-10.6-13.1-10.6S18.8 24.7 18.8 30.6 24.7 41.2 31.9 41.2c1.6 0 3.1-.2 4.5-.6l4.6 2.5-1.2-3.9C43 37 45 34 45 30.6z" fill="#fff" opacity=".96"/>
                <circle cx="27.6" cy="29" r="1.6" fill="<?= $arC1 ?>"/>
                <circle cx="36.2" cy="29" r="1.6" fill="<?= $arC1 ?>"/>
            </svg>
            <?php else: ?>
            <svg viewBox="0 0 48 48" fill="none">
                <path d="M24 4c-7 0-12 5.4-12 12.4 0 1.5-.1 2.9-.5 4.3-1 3.4-3.5 6.6-3.5 9.9 0 1.9 1.2 3.4 3 3.4 1 0 2-.5 2.8-1.2 1 2.5 2.9 4.6 5.2 5.8-1.7.8-3 2-3 3.4 0 .8 2.6 2 8 2s8-1.2 8-2c0-1.4-1.3-2.6-3-3.4 2.3-1.2 4.2-3.3 5.2-5.8.8.7 1.8 1.2 2.8 1.2 1.8 0 3-1.5 3-3.4 0-3.3-2.5-6.5-3.5-9.9-.4-1.4-.5-2.8-.5-4.3C36 9.4 31 4 24 4z" fill="#fff" opacity=".96"/>
                <circle cx="19.5" cy="17" r="2" fill="<?= $arC1 ?>"/>
                <circle cx="28.5" cy="17" r="2" fill="<?= $arC1 ?>"/>
            </svg>
            <?php endif; ?>
        </div>
        <div class="ar-h1">检测到您在 <em><?= $arBrandName ?></em> 内打开</div>
        <div class="ar-sub">为保障浏览与支付流程正常, 请使用系统浏览器继续访问</div>
        <div class="ar-steps">
            <div class="ar-step"><span class="ar-n">1</span><p>点击右上角 <b>「···」</b> 按钮</p></div>
            <div class="ar-step"><span class="ar-n">2</span><p><?= $arStep2 ?></p></div>
        </div>
        <div class="ar-url">
            <code id="arUrl"><?= e($arUrl) ?></code>
            <button type="button" class="ar-copy" id="arCopyBtn">复制链接</button>
        </div>
        <button type="button" class="ar-ghost" id="arGoBtn">已了解风险, 仍要继续访问</button>
        <div class="ar-tip">复制链接后可粘贴到系统浏览器打开</div>
    </div>
    <div class="ar-foot"><?= e($arSite) ?></div>
</div>
<script>
(function () {
    var btn = document.getElementById('arCopyBtn');
    var url = document.getElementById('arUrl').textContent;
    btn.addEventListener('click', function () {
        function ok() { btn.textContent = '已复制'; setTimeout(function () { btn.textContent = '复制链接'; }, 1600); }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url).then(ok);
        } else {
            var ta = document.createElement('textarea');
            ta.value = url; ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); ok(); } catch (e) {}
            document.body.removeChild(ta);
        }
    });
    document.getElementById('arGoBtn').addEventListener('click', function () {
        document.cookie = 'yf_ar_ok=1; path=/; max-age=86400';
        location.reload();
    });
})();
</script>
</body>
</html>
