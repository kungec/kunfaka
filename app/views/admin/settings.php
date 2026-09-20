<?php $activeMenu = 'settings'; ?>
<div class="page-head">
    <div>
        <h2>⚙️ 系统设置</h2>
        <div class="sub">站点 · 安全 · 邮件</div>
    </div>
</div>

<?php if ((current_admin()['role'] ?? 'normal') !== 'super'): ?>
<div class="card" style="border-left:3px solid var(--warn)">
    <p class="dim" style="font-size:12.5px">⚠ 你当前是普通管理员, 只能查看系统设置; 修改需超级管理员账号。</p>
</div>
<?php endif; ?>

<div class="card" style="padding:0;overflow:visible">
    <div class="settings-tabs">
        <button class="settings-tab on" data-panel="sp-site">站点</button>
        <button class="settings-tab" data-panel="sp-verify">安全验证</button>
        <button class="settings-tab" data-panel="sp-cdn">CDN</button>
        <button class="settings-tab" data-panel="sp-mail">邮件</button>
    </div>

    <!-- ====== 站点 ====== -->
    <div class="settings-panel on" id="sp-site" style="padding:18px 20px 6px">
        <form method="post" action="<?= au('settings_save') ?>" data-ajax>
            <?= csrf_field() ?>
            <div class="form-row">
                <label>网站名称</label>
                <input type="text" name="site_name" value="<?= e(setting('site_name', '坤发卡')) ?>">
            </div>
            <div class="form-row">
                <label>网站 Logo</label>
                <?php $logoType = setting('logo_type', 'default'); ?>
                <select name="logo_type" onchange="document.getElementById('logoTextRow').style.display=this.value==='text'?'block':'none';document.getElementById('logoImageRow').style.display=this.value==='image'?'block':'none'">
                    <option value="default" <?= $logoType === 'default' ? 'selected' : '' ?>>默认样式(图标 + 站点名)</option>
                    <option value="text" <?= $logoType === 'text' ? 'selected' : '' ?>>文字 Logo(纯文字)</option>
                    <option value="image" <?= $logoType === 'image' ? 'selected' : '' ?>>图片 Logo(上传)</option>
                </select>
                <div class="desc">作用于前台所有主题的左上角站点标识。</div>
            </div>
            <div class="form-row" id="logoTextRow" style="display:<?= $logoType === 'text' ? 'block' : 'none' ?>">
                <label>Logo 文字(留空则显示站点名称)</label>
                <input type="text" name="logo_text" value="<?= e(setting('logo_text', '')) ?>" maxlength="20" placeholder="如 KUNFAKA">
            </div>
            <div class="form-row" id="logoImageRow" style="display:<?= $logoType === 'image' ? 'block' : 'none' ?>">
                <label>Logo 图片(png / webp / jpg, ≤2MB, 建议透明底; 换用其他类型会自动停用图片)</label>
                <input type="file" name="logo_image_file" accept=".png,.webp,.jpg,.jpeg">
                <?php if (setting('logo_image')): ?>
                    <div style="margin-top:8px;display:flex;align-items:center;gap:10px">
                        <span style="font-size:11.5px;color:var(--muted)">当前:</span>
                        <img src="<?= e(site_url(setting('logo_image'))) ?>" alt="logo" style="height:34px;max-width:160px;object-fit:contain;background:var(--input-bg);border-radius:6px;padding:3px">
                    </div>
                <?php endif; ?>
            </div>
            <div class="form-row">
                <label>网站地址(支付回调用)</label>
                <input type="text" name="site_url" value="<?= e(setting('site_url')) ?>" placeholder="https://shop.example.com">
            </div>
            <div class="form-row">
                <label>订单未支付超时(分钟)</label>
                <input type="number" name="order_timeout" value="<?= e(setting('order_timeout', '15')) ?>" min="5" max="120" style="max-width:130px">
            </div>
            <div class="form-row">
                <label>当前主题</label>
                <select name="theme">
                    <?php foreach (theme_list() as $dir => $t): ?>
                        <option value="<?= e($dir) ?>" <?= active_theme() === $dir ? 'selected' : '' ?>><?= e($t['title']) ?><?= !empty($t['pro']) ? ' (专业版)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label>前台会员(注册/登录)</label>
                <select name="member_open">
                    <option value="1" <?= setting('member_open', '1') === '1' ? 'selected' : '' ?>>开启(默认)</option>
                    <option value="0" <?= setting('member_open', '1') !== '1' ? 'selected' : '' ?>>关闭(纯游客购买)</option>
                </select>
                <div class="desc">关闭后前台隐藏登录/注册入口, 已注册会员无法登录; 买家无需登录直接购买, 凭联系方式在「订单查询」查单。历史会员数据保留。</div>
            </div>
            <div class="form-row">
                <label>伪静态(URL重写)</label>
                <select name="url_rewrite">
                    <option value="0" <?= setting('url_rewrite', '0') !== '1' ? 'selected' : '' ?>>关闭(默认, index.php?s=形式)</option>
                    <option value="1" <?= setting('url_rewrite', '0') === '1' ? 'selected' : '' ?>>开启(/home/index 形式)</option>
                </select>
                <div class="desc">开启后前台链接变为 /home/index 等路径形式。需服务器支持回退: Nginx 加 <span class="mono">location / { try_files $uri $uri/ /index.php$is_args$args; }</span>; Apache 用根目录 .htaccess 的 RewriteRule 回退 index.php。未配置回退时开启会导致前台打不开, 请先配好服务器再开启。</div>
            </div>
            <div class="form-row">
                <label>下单联系方式</label>
                <input type="hidden" name="contact_types_submitted" value="1">
                <div class="chk-group">
                    <?php $ctEnabled = contact_types_enabled(); ?>
                    <?php foreach (contact_type_all() as $ck => $cv): ?>
                        <label class="chk"><input type="checkbox" name="contact_types[]" value="<?= e($ck) ?>" <?= in_array($ck, $ctEnabled, true) ? 'checked' : '' ?>> <?= e($cv) ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-row">
                <label>前台客服</label>
                <input type="hidden" name="service_contacts" id="serviceContactsInput" value="<?= e(setting('service_contacts', '')) ?>">
                <style>
                    .cs-list{display:flex;flex-direction:column;gap:8px}
                    .cs-row{display:grid;grid-template-columns:148px 1.2fr 1fr 30px;gap:8px;align-items:center;background:var(--card2);border:1px solid var(--border);border-radius:10px;padding:8px 10px;transition:border-color .15s}
                    .cs-row:hover{border-color:var(--input-border)}
                    .cs-row select,.cs-row input{height:34px;padding:0 10px;font-size:12.5px;width:100%;margin:0}
                    .cs-del{width:30px;height:30px;border:none;background:transparent;border-radius:7px;color:var(--muted);cursor:pointer;font-size:18px;line-height:1;display:flex;align-items:center;justify-content:center;transition:.12s;flex:none}
                    .cs-del:hover{color:var(--bad);background:var(--bad-bg)}
                    .cs-empty{border:1.5px dashed var(--input-border);border-radius:10px;padding:18px;text-align:center;font-size:12px;color:var(--muted)}
                    .cs-add{width:100%;height:38px;border:1.5px dashed var(--input-border);background:transparent;border-radius:10px;color:var(--text2);cursor:pointer;font-size:12.5px;font-weight:600;font-family:inherit;transition:.12s;margin-top:8px}
                    .cs-add:hover{border-color:var(--muted);color:var(--text);background:var(--input-bg)}
                </style>
                <div class="cs-list" id="csList"></div>
                <div class="cs-empty" id="csEmpty">暂未添加客服方式，点击下方按钮添加</div>
                <button type="button" class="cs-add" id="csAdd">＋ 添加客服方式</button>
                <div class="desc">支持 Telegram / 邮箱 / QQ / 微信 / 电话，留空则前台不显示客服入口。</div>
            </div>
            <div class="form-row">
                <label>公告与单页</label>
                <div class="desc"><a href="<?= au('notices') ?>" style="color:var(--text)">→ 前往「公告单页」管理</a></div>
            </div>
            <button class="btn" type="submit">保存站点设置</button>
        </form>
    </div>

    <!-- ====== CDN (在站点面板内) ====== -->
    <div class="settings-panel" id="sp-cdn" style="padding:18px 20px 6px">
        <form method="post" action="<?= au('settings_save') ?>" data-ajax>
            <?= csrf_field() ?>
            <div class="form-row">
                <label>CDN 接入模式</label>
                <select name="cdn_mode">
                    <option value="off" <?= setting('cdn_mode', 'off') === 'off' ? 'selected' : '' ?>>未套CDN(直连)</option>
                    <option value="cloudflare" <?= setting('cdn_mode', 'off') === 'cloudflare' ? 'selected' : '' ?>>已套 Cloudflare</option>
                    <option value="cdn" <?= setting('cdn_mode', 'off') === 'cdn' ? 'selected' : '' ?>>其他CDN / 反向代理</option>
                </select>
                <div class="desc">套CDN后选择对应模式，系统自动从CDN头识别买家真实IP。</div>
            </div>
            <div class="form-row">
                <label>套CDN时支付回调注意</label>
                <div class="desc">
                    ① 动态页已自动发送 no-store 禁缓存头；<br>
                    ② CDN侧为 /index.php?s=/pay/* 关闭强制HTTPS跳转、JS挑战、缓存(否则回调失败不发货)；<br>
                    ③ Cloudflare 建议 SSL「完全(严格)」+ 放行回调路径的机器人规则。
                </div>
            </div>
            <button class="btn" type="submit">保存CDN设置</button>
        </form>
    </div>

    <!-- ====== 安全验证 ====== -->
    <div class="settings-panel" id="sp-verify" style="padding:18px 20px 6px">
        <?php $vm = setting('verify_mode'); if ($vm === '') $vm = setting('turnstile_open') === '1' ? 'turnstile' : 'captcha'; ?>
        <form method="post" action="<?= au('settings_save') ?>" data-ajax>
            <?= csrf_field() ?>
            <div class="form-row">
                <label>验证方式(前台注册/登录 + 后台登录)</label>
                <select name="verify_mode" onchange="document.getElementById('ts-fields').style.display=this.value==='turnstile'?'block':'none';document.getElementById('gt-fields').style.display=this.value==='geetest'?'block':'none';document.getElementById('cap-field').style.display=this.value==='captcha'?'block':'none'">
                    <option value="captcha" <?= $vm === 'captcha' ? 'selected' : '' ?>>图形验证码(默认)</option>
                    <option value="turnstile" <?= $vm === 'turnstile' ? 'selected' : '' ?>>Cloudflare Turnstile</option>
                    <option value="geetest" <?= $vm === 'geetest' ? 'selected' : '' ?>>极验 GeeTest v4</option>
                </select>
                <div class="desc">密钥不完整时自动降级为图形验证码。</div>
            </div>
            <div id="cap-field" style="display:<?= $vm === 'captcha' ? 'block' : 'none' ?>">
                <div class="form-row">
                    <label>图形验证码</label>
                    <select name="captcha_open" style="max-width:160px">
                        <option value="1" <?= setting('captcha_open', '1') === '1' ? 'selected' : '' ?>>开启(推荐)</option>
                        <option value="0" <?= setting('captcha_open', '1') !== '1' ? 'selected' : '' ?>>关闭</option>
                    </select>
                </div>
            </div>
            <div id="ts-fields" style="display:<?= $vm === 'turnstile' ? 'block' : 'none' ?>">
                <div class="form-row">
                    <label>Turnstile Site Key</label>
                    <input type="text" name="turnstile_site_key" value="<?= e(setting('turnstile_site_key')) ?>">
                </div>
                <div class="form-row">
                    <label>Turnstile Secret Key</label>
                    <input type="text" name="turnstile_secret_key" value="<?= e(setting('turnstile_secret_key')) ?>">
                    <div class="desc">Cloudflare Dashboard → Turnstile → Add site。</div>
                </div>
            </div>
            <div id="gt-fields" style="display:<?= $vm === 'geetest' ? 'block' : 'none' ?>">
                <div class="form-row">
                    <label>极验 Captcha ID</label>
                    <input type="text" name="geetest_id" value="<?= e(setting('geetest_id')) ?>">
                </div>
                <div class="form-row">
                    <label>极验 Captcha Key</label>
                    <input type="text" name="geetest_key" value="<?= e(setting('geetest_key')) ?>">
                </div>
                <div class="form-row">
                    <label>验证有效期(秒)</label>
                    <input type="number" name="geetest_timeout" value="<?= e(setting('geetest_timeout', '120')) ?>" min="10" max="600" style="max-width:130px">
                </div>
            </div>
            <button class="btn" type="submit">保存验证设置</button>
        </form>
    </div>

    <!-- ====== 邮件 ====== -->
    <div class="settings-panel" id="sp-mail" style="padding:18px 20px 6px">
        <form method="post" action="<?= au('settings_save') ?>" data-ajax>
            <?= csrf_field() ?>
            <div class="form-row">
                <label>发卡邮件通知</label>
                <select name="smtp_open">
                    <option value="0" <?= setting('smtp_open') !== '1' ? 'selected' : '' ?>>关闭</option>
                    <option value="1" <?= setting('smtp_open') === '1' ? 'selected' : '' ?>>开启</option>
                </select>
            </div>
            <div class="form-row">
                <label>SMTP 服务器 / 端口 / 加密</label>
                <div class="form-inline">
                    <input type="text" name="smtp_host" value="<?= e(setting('smtp_host')) ?>" placeholder="smtp.qq.com" style="max-width:200px">
                    <input type="number" name="smtp_port" value="<?= e(setting('smtp_port', '465')) ?>" style="max-width:90px">
                    <select name="smtp_ssl" style="max-width:100px">
                        <option value="1" <?= setting('smtp_ssl') === '1' ? 'selected' : '' ?>>SSL</option>
                        <option value="0" <?= setting('smtp_ssl') !== '1' ? 'selected' : '' ?>>无加密</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <label>邮箱账号 / 授权码</label>
                <div class="form-inline">
                    <input type="text" name="smtp_user" value="<?= e(setting('smtp_user')) ?>" placeholder="xxx@qq.com" style="max-width:200px">
                    <input type="text" name="smtp_pass" value="<?= e(setting('smtp_pass')) ?>" placeholder="SMTP授权码" style="max-width:180px">
                </div>
                <div class="desc">QQ邮箱需开启SMTP服务并填写授权码；卡密自动发送到买家邮箱。</div>
            </div>
            <button class="btn" type="submit">保存邮件设置</button>
        </form>
    </div>
</div>

<!-- ====== 计划任务 ====== -->
<div class="card">
    <h3>计划任务(宝塔)</h3>
    <p class="dim" style="margin-bottom:8px">宝塔面板 → 计划任务 → Shell 脚本，每 1 分钟执行：</p>
    <pre class="mono" style="padding:10px 14px;background:var(--input-bg);border-radius:var(--radius-sm);font-size:12px;overflow-x:auto">php <?= e(YF_ROOT) ?>/cron.php</pre>
    <p class="dim" style="margin-top:6px">用于：订单过期自动关闭、USDT到账轮询(未配置时买家支付页自动触发)。</p>
</div>

<script>
/* 设置页标签切换 */
document.querySelectorAll('.settings-tab').forEach(function (t) {
    t.addEventListener('click', function () {
        document.querySelectorAll('.settings-tab').forEach(function (x) { x.classList.remove('on'); });
        document.querySelectorAll('.settings-panel').forEach(function (x) { x.classList.remove('on'); });
        t.classList.add('on');
        document.getElementById(t.getAttribute('data-panel')).classList.add('on');
    });
});

/* 客服行编辑器 */
(function () {
    var types = <?= json_encode(contact_type_all(), JSON_UNESCAPED_UNICODE) ?>;
    var data = <?= json_encode(service_contacts(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
    var icons = { telegram: '💬', email: '📧', qq: '🐧', wechat: '💚', phone: '📞' };
    var phs = { telegram: '@用户名 或 t.me 链接', email: 'name@example.com', qq: 'QQ 号码', wechat: '微信号', phone: '+86 手机号' };
    var list = document.getElementById('csList');
    var hidden = document.getElementById('serviceContactsInput');
    var empty = document.getElementById('csEmpty');
    if (!list || !hidden) return;
    function serialize() {
        var rows = [];
        list.querySelectorAll('.cs-row').forEach(function (row) {
            var value = row.querySelector('.cs-value').value.trim();
            if (!value) return;
            rows.push({ type: row.querySelector('.cs-type').value, value: value, note: row.querySelector('.cs-note').value.trim() });
        });
        hidden.value = JSON.stringify(rows);
    }
    function syncPh(row) {
        var t = row.querySelector('.cs-type').value;
        row.querySelector('.cs-value').placeholder = phs[t] || '客服账号';
    }
    function syncEmpty() {
        empty.style.display = list.querySelector('.cs-row') ? 'none' : 'block';
    }
    function addRow(item) {
        var row = document.createElement('div');
        row.className = 'cs-row';
        var opts = '';
        for (var k in types) opts += '<option value="' + k + '">' + (icons[k] ? icons[k] + ' ' : '') + types[k] + '</option>';
        row.innerHTML = '<select class="cs-type">' + opts + '</select>' +
            '<input class="cs-value" placeholder="客服账号">' +
            '<input class="cs-note" placeholder="备注(选填)">' +
            '<button type="button" class="cs-del" title="删除该行">×</button>';
        if (item) {
            row.querySelector('.cs-type').value = item.type || 'qq';
            row.querySelector('.cs-value').value = item.value || '';
            row.querySelector('.cs-note').value = item.note || '';
        }
        syncPh(row);
        list.appendChild(row);
        syncEmpty();
    }
    list.addEventListener('click', function (ev) {
        var del = ev.target.closest ? ev.target.closest('.cs-del') : null;
        if (del) { del.closest('.cs-row').remove(); serialize(); syncEmpty(); }
    });
    list.addEventListener('input', serialize);
    list.addEventListener('change', function (ev) {
        var row = ev.target.closest ? ev.target.closest('.cs-row') : null;
        if (row && ev.target.classList.contains('cs-type')) syncPh(row);
        serialize();
    });
    document.getElementById('csAdd').addEventListener('click', function () { addRow(null); });
    data.forEach(function (item) { addRow(item); });
    syncEmpty();
})();
</script>
