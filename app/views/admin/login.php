<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f1222">
<title>管理后台登录 - 坤发卡</title>
<link rel="stylesheet" href="<?= site_url('assets/css/admin.css') ?>?v=3.0.0">
</head>
<body>
<div class="login-page">
    <form class="login-box" method="post" action="<?= au('login') ?>">
        <div class="login-brand">
            <em>坤</em>
            <div><b>坤发卡</b><small>全自动发卡系统 · 管理后台</small></div>
        </div>
        <?php if ($error): ?><div class="login-err"><?= e($error) ?></div><?php endif; ?>
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div class="form-row">
            <label>管理员账号</label>
            <input type="text" name="username" required autofocus autocomplete="username">
        </div>
        <div class="form-row">
            <label>密码</label>
            <input type="password" name="password" required autocomplete="current-password">
        </div>
        <div class="form-row">
            <label>安全验证</label>
            <?= Captcha::render('admin') ?>
        </div>
        <button class="btn" style="width:100%;margin-top:6px;height:44px;font-size:14.5px" type="submit">登 录</button>
        <p class="login-foot">Powered by 坤发卡</p>
    </form>
</div>
<style>
.login-page {
    min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;
    background: linear-gradient(135deg, #0f1222 0%, #151936 45%, #2b1d5e 100%);
    position: relative; overflow: hidden;
}
.login-page::before {
    content: ''; position: absolute; width: 460px; height: 460px; border-radius: 50%;
    background: radial-gradient(circle, rgba(99, 102, 241, .28), transparent 65%);
    top: -140px; right: -100px;
}
.login-page::after {
    content: ''; position: absolute; width: 380px; height: 380px; border-radius: 50%;
    background: radial-gradient(circle, rgba(139, 92, 246, .22), transparent 65%);
    bottom: -120px; left: -90px;
}
.login-box {
    position: relative; z-index: 1; width: 100%; max-width: 380px;
    background: #fff; border-radius: 18px; padding: 30px 30px 22px;
    box-shadow: 0 30px 70px -20px rgba(0, 0, 0, .5);
}
.login-brand { display: flex; align-items: center; gap: 11px; margin-bottom: 20px; }
.login-brand em {
    width: 42px; height: 42px; border-radius: 12px; font-style: normal; font-size: 19px; font-weight: 800; color: #fff;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #4f46e5, #8b5cf6); box-shadow: 0 8px 18px rgba(99, 102, 241, .45);
}
.login-brand b { font-size: 17px; color: #111827; display: block; }
.login-brand small { color: #6b7280; font-size: 11.5px; }
.login-err { background: #fef2f2; color: #dc2626; font-size: 13px; border-radius: 10px; padding: 9px 13px; margin-bottom: 12px; }
.login-foot { text-align: center; color: #9ca3af; font-size: 11px; margin: 14px 0 0; letter-spacing: 1px; }
.login-box .btn { background: linear-gradient(135deg, #4f46e5, #8b5cf6); }
</style>
</body>
</html>
