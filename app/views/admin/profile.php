<?php $activeMenu = 'profile'; ?>
<style>
.pf-wrap{max-width:1020px;display:grid;grid-template-columns:300px minmax(0,1fr);gap:16px;align-items:start}
@media (max-width:1000px){.pf-wrap{grid-template-columns:1fr}}
.pf-col{display:flex;flex-direction:column;gap:16px;min-width:0}
/* 身份卡 */
.pf-id{display:flex;flex-direction:column;align-items:center;text-align:center;gap:10px;padding:28px 20px 20px}
.pf-id .avatar{width:84px;height:84px;border-radius:24px;background:linear-gradient(135deg,#a8c7fa,#8ab4f8);color:#0e2242;display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:32px;box-shadow:0 14px 32px -12px rgba(138,180,248,.7);position:relative}
html[data-theme="light"] .pf-id .avatar{background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;box-shadow:0 14px 32px -12px rgba(37,99,235,.6)}
.pf-id .avatar::after{content:"";position:absolute;inset:-6px;border-radius:28px;border:1px solid var(--input-border);opacity:.6}
.pf-id .nm{font-size:17px;font-weight:800}
.pf-id .rl{margin-top:2px}
.pf-rows{width:100%;display:flex;flex-direction:column;margin-top:6px}
.pf-row{display:flex;justify-content:space-between;gap:10px;padding:9px 2px;border-top:1px dashed var(--input-border);font-size:12px}
.pf-row:first-child{border-top:none}
.pf-row .k{color:var(--muted);flex:none}
.pf-row .v{color:var(--text2);text-align:right;word-break:break-all}
/* 安全建议(左列底部) */
.pf-tips{padding:16px 18px 18px}
.pf-tips h3{font-size:13px;margin-bottom:10px}
.pf-tips p{margin:0;font-size:12px;line-height:1.9;color:var(--muted)}
/* 表单卡 */
.pf-form{padding:22px 24px 24px}
.pf-sec{font-size:11px;font-weight:800;letter-spacing:2px;color:var(--muted);text-transform:uppercase;margin:0 0 12px;display:flex;align-items:center;gap:8px}
.pf-sec::before{content:"";width:4px;height:13px;border-radius:4px;background:linear-gradient(180deg,#6366f1,#a855f7)}
.pf-sec + .pf-grid{margin-bottom:6px}
.pf-div{height:1px;background:var(--input-border);margin:18px 0}
.pf-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px 16px}
.pf-grid.c3{grid-template-columns:repeat(3,1fr)}
@media (max-width:860px){.pf-grid,.pf-grid.c3{grid-template-columns:1fr}}
.pf-grid label{display:block;font-size:11.5px;color:var(--muted);font-weight:600;margin-bottom:6px}
.pf-grid input{width:100%;height:40px;padding:0 12px;font-size:13px}
.pf-form .ops{display:flex;gap:9px;margin-top:20px;align-items:center;flex-wrap:wrap}
.pf-note{margin:14px 0 0;font-size:11.5px;color:var(--muted);line-height:1.7}
</style>

<div class="page-head">
    <div>
        <h2>👤 个人设置</h2>
        <div class="sub">账号资料 · 登录密码 · 安全建议</div>
    </div>
</div>

<div class="pf-wrap">
    <div class="pf-col">
        <div class="card pf-id">
            <span class="avatar">坤</span>
            <div>
                <div class="nm"><?= e($admin['nickname'] ?: $admin['username']) ?></div>
                <div class="rl"><span class="tag <?= $admin['role'] === 'super' ? 'pro' : '' ?>"><?= $admin['role'] === 'super' ? '👑 超级管理员' : '普通管理员' ?></span></div>
            </div>
            <div class="pf-rows">
                <div class="pf-row"><span class="k">登录账号</span><span class="v"><?= e($admin['username']) ?></span></div>
                <div class="pf-row"><span class="k">创建时间</span><span class="v"><?= e(date('Y-m-d', $admin['created_at'])) ?></span></div>
                <?php if ($admin['last_login_at'] > 0): ?><div class="pf-row"><span class="k">本次登录</span><span class="v"><?= e(date('m-d H:i', $admin['last_login_at'])) ?><br><?= e($admin['last_login_ip']) ?></span></div><?php endif; ?>
                <?php if ($admin['prev_login_at'] > 0): ?><div class="pf-row"><span class="k">上次登录</span><span class="v"><?= e(date('m-d H:i', $admin['prev_login_at'])) ?><br><?= e($admin['prev_login_ip']) ?></span></div><?php endif; ?>
            </div>
        </div>

        <div class="card pf-tips">
            <h3>🔐 安全建议</h3>
            <p>
                · 定期修改密码, 不要与其他站点共用同一密码;<br>
                · 超级管理员可在「管理员」页添加普通管理员并随时禁用;<br>
                · 发现异常登录记录(IP/时间不符)请立即修改密码并检查「管理员」列表。
            </p>
        </div>
    </div>

    <div class="card pf-form">
        <form id="profileForm">
            <div class="pf-sec">账号资料</div>
            <div class="pf-grid">
                <div><label>昵称(后台显示名)</label><input type="text" id="p-nickname" maxlength="50" value="<?= e($admin['nickname']) ?>"></div>
                <div><label>登录用户名(修改需旧密码验证)</label><input type="text" id="p-username" maxlength="20" value="<?= e($admin['username']) ?>" autocomplete="username"></div>
            </div>

            <div class="pf-div"></div>

            <div class="pf-sec">登录密码</div>
            <div class="pf-grid">
                <div><label>旧密码(改密码/用户名时必填)</label><input type="password" id="p-old" autocomplete="current-password" placeholder="••••••••"></div>
                <div><label>新密码(至少6位, 留空不改)</label><input type="password" id="p-new" autocomplete="new-password" placeholder="至少6位"></div>
                <div><label>确认新密码</label><input type="password" id="p-confirm" autocomplete="new-password" placeholder="再输入一次新密码"></div>
            </div>

            <div class="ops">
                <button class="btn" type="submit">💾 确认修改</button>
                <a class="btn gray" href="<?= au('logout') ?>">⏻ 注销登录</a>
            </div>
            <p class="pf-note">修改后当前登录状态保留; 建议修改完成后退出并重新登录一次确认新密码可用。</p>
        </form>
    </div>
</div>

<script>
document.getElementById('profileForm').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var fd = new FormData();
    fd.append('_csrf', '<?= e(csrf_token()) ?>');
    fd.append('nickname', document.getElementById('p-nickname').value.trim());
    fd.append('username', document.getElementById('p-username').value.trim());
    fd.append('old_password', document.getElementById('p-old').value);
    fd.append('new_password', document.getElementById('p-new').value);
    fd.append('confirm_password', document.getElementById('p-confirm').value);
    fetch('<?= au('profile_save') ?>', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            kAlert(d.msg);
            if (d.code === 0) location.reload();
        });
});
</script>
