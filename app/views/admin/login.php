<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b0e1f">
<title>管理后台登录 - 坤发卡</title>
<link rel="stylesheet" href="<?= site_url('assets/css/admin.css') ?>?v=6.2.0">
</head>
<body>
<div class="lg-page">
    <span class="lg-orb o1" aria-hidden="true"></span>
    <span class="lg-orb o2" aria-hidden="true"></span>
    <span class="lg-orb o3" aria-hidden="true"></span>
    <div class="lg-grid" aria-hidden="true"></div>
    <form class="lg-card" method="post" action="<?= au('login') ?>">
        <div class="lg-brand">
            <em>坤</em>
            <div><b>坤发卡</b><small>全自动发卡系统 · 管理后台</small></div>
        </div>
        <?php if ($error): ?><div class="lg-err">⚠ <?= e($error) ?></div><?php endif; ?>
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <label class="lg-field">
            <span class="lg-ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </span>
            <input type="text" name="username" placeholder="管理员账号" required autofocus autocomplete="username">
        </label>
        <label class="lg-field">
            <span class="lg-ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </span>
            <input type="password" name="password" placeholder="密码" required autocomplete="current-password">
        </label>
        <div class="lg-caprow">
            <?= Captcha::render('admin') ?>
        </div>
        <button class="lg-btn" type="submit">登 录
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h13M13 6l6 6-6 6"/></svg>
        </button>
        <p class="lg-foot">Powered by 坤发卡</p>
    </form>
</div>
<style>
* { box-sizing: border-box; }
body { margin: 0; }
.lg-page {
    min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;
    background:
        radial-gradient(1000px 500px at 80% -10%, rgba(99, 102, 241, .30), transparent 60%),
        radial-gradient(800px 460px at -10% 110%, rgba(139, 92, 246, .26), transparent 60%),
        linear-gradient(160deg, #0b0e1f 0%, #10142e 48%, #241d52 100%);
    position: relative; overflow: hidden;
}
.lg-grid {
    position: absolute; inset: 0; pointer-events: none; opacity: .5;
    background-image: linear-gradient(rgba(255,255,255,.045) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px);
    background-size: 44px 44px;
    -webkit-mask-image: radial-gradient(720px 480px at 50% 40%, #000 30%, transparent 100%);
    mask-image: radial-gradient(720px 480px at 50% 40%, #000 30%, transparent 100%);
}
.lg-orb { position: absolute; border-radius: 50%; pointer-events: none; }
.lg-orb.o1 { width: 520px; height: 520px; background: radial-gradient(circle, rgba(99, 102, 241, .40) 0%, rgba(99, 102, 241, .15) 45%, transparent 70%); top: -160px; right: -110px; }
.lg-orb.o2 { width: 440px; height: 440px; background: radial-gradient(circle, rgba(168, 85, 247, .32) 0%, rgba(168, 85, 247, .12) 45%, transparent 70%); bottom: -140px; left: -100px; }
.lg-orb.o3 { width: 320px; height: 320px; background: radial-gradient(circle, rgba(56, 189, 248, .22) 0%, rgba(56, 189, 248, .08) 45%, transparent 70%); top: 46%; left: 55%; }
.lg-card {
    position: relative; z-index: 1; width: 100%; max-width: 396px;
    background: rgba(17, 21, 44, .62);
    border: 1px solid rgba(255, 255, 255, .14);
    border-radius: 20px; padding: 34px 32px 24px;
    backdrop-filter: blur(22px); -webkit-backdrop-filter: blur(22px);
    box-shadow: 0 34px 90px -22px rgba(0, 0, 0, .7), inset 0 1px 0 rgba(255, 255, 255, .08);
    animation: lgUp .5s cubic-bezier(.2, .7, .3, 1);
}
@keyframes lgUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
.lg-brand { display: flex; align-items: center; gap: 12px; margin-bottom: 26px; }
.lg-brand em {
    width: 46px; height: 46px; border-radius: 13px; font-style: normal; font-size: 20px; font-weight: 800; color: #fff;
    display: flex; align-items: center; justify-content: center; flex: none;
    background: linear-gradient(135deg, #6366f1, #a855f7);
    box-shadow: 0 10px 24px rgba(99, 102, 241, .55);
}
.lg-brand b { font-size: 18px; color: #fff; display: block; letter-spacing: .5px; }
.lg-brand small { color: rgba(255, 255, 255, .55); font-size: 11.5px; }
.lg-err { background: rgba(248, 113, 113, .14); border: 1px solid rgba(248, 113, 113, .35); color: #fda4af; font-size: 13px; border-radius: 11px; padding: 10px 13px; margin-bottom: 14px; }
.lg-field { display: flex; align-items: center; gap: 10px; height: 48px; margin-bottom: 14px; padding: 0 13px; border-radius: 12px; background: rgba(255, 255, 255, .06); border: 1px solid rgba(255, 255, 255, .14); transition: .16s; }
.lg-field:focus-within { border-color: rgba(129, 140, 248, .8); background: rgba(255, 255, 255, .09); box-shadow: 0 0 0 4px rgba(99, 102, 241, .18); }
.lg-ico { flex: none; width: 19px; height: 19px; color: rgba(255, 255, 255, .45); display: inline-flex; }
.lg-ico svg { width: 100%; height: 100%; }
.lg-field input { flex: 1; min-width: 0; height: 100%; border: none; outline: none; background: transparent; font-size: 14px; color: #fff; letter-spacing: .3px; }
.lg-field input::placeholder { color: rgba(255, 255, 255, .35); }
.lg-caprow { margin-bottom: 16px; }
.lg-caprow img { border-radius: 10px; cursor: pointer; vertical-align: middle; }
.lg-caprow input { height: 44px; border-radius: 12px; background: rgba(255, 255, 255, .06); border: 1px solid rgba(255, 255, 255, .14); color: #fff; font-size: 14px; letter-spacing: 2px; }
.lg-caprow input:focus { border-color: rgba(129, 140, 248, .8); background: rgba(255, 255, 255, .09); outline: none; box-shadow: 0 0 0 4px rgba(99, 102, 241, .18); }
.lg-btn {
    width: 100%; height: 48px; border: none; border-radius: 13px; cursor: pointer;
    background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff; font-size: 15px; font-weight: 700; letter-spacing: 6px;
    display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-family: inherit;
    transition: .18s; box-shadow: 0 12px 30px -8px rgba(99, 102, 241, .6);
}
.lg-btn svg { width: 17px; height: 17px; letter-spacing: 0; }
.lg-btn:hover { transform: translateY(-1px); box-shadow: 0 16px 36px -8px rgba(99, 102, 241, .7); filter: brightness(1.06); }
.lg-btn:active { transform: none; }
.lg-foot { text-align: center; color: rgba(255, 255, 255, .38); font-size: 11px; margin: 16px 0 0; letter-spacing: 1.5px; }
@media (max-width: 480px) { .lg-card { padding: 28px 22px 20px; } }
</style>
</body>
</html>
