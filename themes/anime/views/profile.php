<?php /** 前台 个人设置(改邮箱/密码) */ ?>
<div class="panel">
    <h1 class="panel-title">个人设置</h1>
    <?php if (!empty($ok)): ?><div class="ok-panel" style="border:1px solid #b7ebc6;background:#f2fbf5;color:#1c7c3c;border-radius:10px;padding:10px 13px;margin-bottom:13px;font-size:13.5px">✓ <?= e($ok) ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="error-panel">⚠ <?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= u('user/profile') ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <label>用户名(不可修改)</label>
            <input type="text" value="<?= e($user['username']) ?>" disabled>
        </div>
        <div class="form-row">
            <label>邮箱</label>
            <input type="email" name="email" value="<?= e($user['email']) ?>" placeholder="用于接收卡密与找回订单">
        </div>
        <div class="form-row">
            <label>当前密码(保存任何修改都需填写)</label>
            <input type="password" name="password" required autocomplete="current-password">
        </div>
        <div class="form-row">
            <label>新密码(不改请留空)</label>
            <input type="password" name="new_password" autocomplete="new-password">
        </div>
        <div class="form-row">
            <label>确认新密码</label>
            <input type="password" name="new_password2" autocomplete="new-password">
        </div>
        <button type="submit" style="width:100%;height:44px;border:none;border-radius:10px;background:var(--primary,#4f7cff);color:#fff;font-size:15px;font-weight:700;cursor:pointer">💾 保存设置</button>
    </form>
</div>
