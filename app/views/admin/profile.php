<?php $activeMenu = 'profile'; ?>
<style>
.pf-wrap{max-width:620px}
.pf-info{display:flex;gap:14px;align-items:center;margin-bottom:16px}
.pf-info .avatar{width:52px;height:52px;border-radius:14px;background:var(--text);color:var(--bg);display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:22px}
.pf-info .meta small{display:block;color:var(--muted);font-size:11px;margin-top:2px}
.pf-info .meta .tag{margin-left:6px}
.pf-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:640px){.pf-grid{grid-template-columns:1fr}}
.pf-grid label{display:block;font-size:11.5px;color:var(--muted);font-weight:600;margin-bottom:5px}
.pf-grid input{width:100%;height:40px;padding:0 12px;font-size:13px}
</style>

<div class="page-head">
    <div>
        <h2>👤 个人设置</h2>
        <div class="sub">修改昵称与登录密码</div>
    </div>
</div>

<div class="pf-wrap">
    <div class="card">
        <div class="pf-info">
            <span class="avatar">坤</span>
            <div class="meta">
                <b style="font-size:15px"><?= e($admin['nickname'] ?: $admin['username']) ?><span class="tag <?= $admin['role'] === 'super' ? 'pro' : '' ?>" style="margin-left:6px"><?= $admin['role'] === 'super' ? '超级管理员' : '普通管理员' ?></span></b>
                <small>账号 <?= e($admin['username']) ?> · 创建于 <?= e(date('Y-m-d', $admin['created_at'])) ?></small>
                <?php if ($admin['last_login_at'] > 0): ?><small>本次登录 <?= e(date('Y-m-d H:i:s', $admin['last_login_at'])) ?> · IP <?= e($admin['last_login_ip']) ?></small><?php endif; ?>
                <?php if ($admin['prev_login_at'] > 0): ?><small>上次登录 <?= e(date('Y-m-d H:i:s', $admin['prev_login_at'])) ?> · IP <?= e($admin['prev_login_ip']) ?></small><?php endif; ?>
            </div>
        </div>

        <form id="profileForm">
            <div class="pf-grid">
                <div><label>昵称</label><input type="text" id="p-nickname" maxlength="50" value="<?= e($admin['nickname']) ?>"></div>
                <div><label>旧密码(修改密码时必填)</label><input type="password" id="p-old" autocomplete="current-password"></div>
                <div><label>新密码(至少6位, 留空不改)</label><input type="password" id="p-new" autocomplete="new-password"></div>
                <div><label>确认新密码</label><input type="password" id="p-confirm" autocomplete="new-password"></div>
            </div>
            <div style="display:flex;gap:9px;margin-top:16px">
                <button class="btn" type="submit">💾 确认修改</button>
                <a class="btn gray" href="<?= au('logout') ?>">⏻ 注销登录</a>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>🔐 安全提示</h3>
        <p class="dim" style="font-size:12px;line-height:1.9;margin:0">
            · 密码修改后当前登录状态保留, 建议修改后重新登录一次;<br>
            · 定期修改密码, 不要与其他站点共用同一密码;<br>
            · 超级管理员可在「管理员」页添加普通管理员并随时禁用。
        </p>
    </div>
</div>

<script>
document.getElementById('profileForm').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var fd = new FormData();
    fd.append('_csrf', '<?= e(csrf_token()) ?>');
    fd.append('nickname', document.getElementById('p-nickname').value.trim());
    fd.append('old_password', document.getElementById('p-old').value);
    fd.append('new_password', document.getElementById('p-new').value);
    fd.append('confirm_password', document.getElementById('p-confirm').value);
    fetch('<?= au('profile_save') ?>', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            alert(d.msg);
            if (d.code === 0) location.reload();
        });
});
</script>
