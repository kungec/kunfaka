<?php /** 前台 登录/注册(双Tab) */ $isReg = !empty($register); ?>
<div class="panel auth-panel">
    <div class="auth-tabs">
        <a class="auth-tab<?= !$isReg ? ' active' : '' ?>" href="<?= u('user/login') ?>">登录</a>
        <a class="auth-tab<?= $isReg ? ' active' : '' ?>" href="<?= u('user/register') ?>">注册</a>
    </div>
    <?php if ($error): ?><div class="auth-err"><?= e($error) ?></div><?php endif; ?>

    <?php if (!$isReg): ?>
        <form class="buy-form" method="post" action="<?= u('user/login') ?>">
            <?= csrf_field() ?>
            <div class="form-row">
                <label>用户名 / 邮箱</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-row">
                <label>密码</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-row">
                <label>安全验证</label>
                <?= Captcha::render('user') ?>
            </div>
            <div class="form-row">
                <button class="btn-buy big" type="submit">登 录</button>
            </div>
            <p class="tip-line">密码一天内错误5次将锁定60分钟。</p>
        </form>
    <?php else: ?>
        <form class="buy-form" method="post" action="<?= u('user/register') ?>">
            <?= csrf_field() ?>
            <div class="form-row">
                <label>用户名(3-20位, 字母/数字/下划线/中文)</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-row">
                <label>邮箱(选填, 用于自动接收卡密)</label>
                <input type="email" name="email">
            </div>
            <div class="form-row">
                <label>密码(至少6位)</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-row">
                <label>确认密码</label>
                <input type="password" name="password2" required>
            </div>
            <div class="form-row">
                <label>安全验证</label>
                <?= Captcha::render('user') ?>
            </div>
            <div class="form-row">
                <button class="btn-buy big" type="submit">注 册</button>
            </div>
            <p class="tip-line">同一IP每24小时最多注册<?= (int)\Security::REG_LIMIT ?>个账号。</p>
        </form>
    <?php endif; ?>
</div>
