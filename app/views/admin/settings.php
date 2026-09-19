<?php $activeMenu = 'settings'; ?>
<div class="card">
    <h3>人机验证(前台注册/登录 + 后台登录)</h3>
    <?php $vm = setting('verify_mode'); if ($vm === '') $vm = setting('turnstile_open') === '1' ? 'turnstile' : 'captcha'; ?>
    <form method="post" action="<?= au('settings_save') ?>" data-ajax>
        <?= csrf_field() ?>
        <div class="form-row">
            <label>验证方式</label>
            <select name="verify_mode" style="max-width:380px" onchange="document.getElementById('ts-fields').style.display=this.value==='turnstile'?'block':'none';document.getElementById('gt-fields').style.display=this.value==='geetest'?'block':'none';document.getElementById('cap-field').style.display=this.value==='captcha'?'block':'none'">
                <option value="captcha" <?= $vm === 'captcha' ? 'selected' : '' ?>>图形验证码(默认, 无需申请)</option>
                <option value="turnstile" <?= $vm === 'turnstile' ? 'selected' : '' ?>>Cloudflare Turnstile(免费无感人机验证)</option>
                <option value="geetest" <?= $vm === 'geetest' ? 'selected' : '' ?>>极验 GeeTest v4(一键通过)</option>
            </select>
        </div>
        <div id="cap-field" style="display:<?= $vm === 'captcha' ? 'block' : 'none' ?>">
            <div class="form-row">
                <label>图形验证码开关</label>
                <select name="captcha_open" style="max-width:200px">
                    <option value="1" <?= setting('captcha_open', '1') === '1' ? 'selected' : '' ?>>开启(推荐)</option>
                    <option value="0" <?= setting('captcha_open', '1') !== '1' ? 'selected' : '' ?>>关闭</option>
                </select>
            </div>
        </div>
        <div id="ts-fields" style="display:<?= $vm === 'turnstile' ? 'block' : 'none' ?>">
            <div class="form-row">
                <label>Turnstile Site Key</label>
                <input type="text" name="turnstile_site_key" value="<?= e(setting('turnstile_site_key')) ?>" style="width:420px">
                <div class="desc">Cloudflare Dashboard → Turnstile → Add site 获取; 密钥不完整时自动降级为图形验证码。</div>
            </div>
            <div class="form-row">
                <label>Turnstile Secret Key</label>
                <input type="text" name="turnstile_secret_key" value="<?= e(setting('turnstile_secret_key')) ?>" style="width:420px">
            </div>
        </div>
        <div id="gt-fields" style="display:<?= $vm === 'geetest' ? 'block' : 'none' ?>">
            <div class="form-row">
                <label>极验 ID(Captcha ID)</label>
                <input type="text" name="geetest_id" value="<?= e(setting('geetest_id')) ?>" style="width:420px">
                <div class="desc">极验后台 → 行为验证4.0 → 验证单元 中的「Captcha ID」; 密钥不完整时自动降级为图形验证码。</div>
            </div>
            <div class="form-row">
                <label>极验 Key(Captcha Key)</label>
                <input type="text" name="geetest_key" value="<?= e(setting('geetest_key')) ?>" style="width:420px">
            </div>
            <div class="form-row">
                <label>验证有效期(秒, 10-600, 默认120)</label>
                <input type="number" name="geetest_timeout" value="<?= e(setting('geetest_timeout', '120')) ?>" min="10" max="600" style="width:120px">
                <div class="desc">弹窗验证通过后的结果在此时限内有效, 服务端二次校验时会检查; 同时作为前端验证请求超时时间。</div>
            </div>
        </div>
        <button class="btn" type="submit">保存验证设置</button>
    </form>
</div>

<div class="card">
    <h3>站点设置</h3>
    <form method="post" action="<?= au('settings_save') ?>" data-ajax>
        <?= csrf_field() ?>
        <div class="form-row">
            <label>网站名称</label>
            <input type="text" name="site_name" value="<?= e(setting('site_name', '坤发卡')) ?>">
        </div>
        <div class="form-row">
            <label>网站地址(用于支付回调, 建议填写, 如 https://shop.example.com)</label>
            <input type="text" name="site_url" value="<?= e(setting('site_url')) ?>" placeholder="留空则自动识别">
        </div>
        <div class="form-row">
            <label>CDN接入模式</label>
            <select name="cdn_mode" style="max-width:300px">
                <option value="off" <?= setting('cdn_mode', 'off') === 'off' ? 'selected' : '' ?>>未套CDN(直连, 默认)</option>
                <option value="cloudflare" <?= setting('cdn_mode', 'off') === 'cloudflare' ? 'selected' : '' ?>>已套 Cloudflare</option>
                <option value="cdn" <?= setting('cdn_mode', 'off') === 'cdn' ? 'selected' : '' ?>>其他CDN / 反向代理</option>
            </select>
            <div class="desc">套CDN后开启对应模式, 系统会从CDN头识别<b>买家真实IP</b>(注册限制/登录锁定/日志/订单不再误记CDN节点IP)。</div>
        </div>
        <div class="form-row">
            <label>套CDN时支付回调注意事项</label>
            <div class="desc">
                ① 本系统动态页已自动发送 <b>no-store 禁缓存头</b>, 收银台/二维码/支付结果不会被CDN缓存;<br>
                ② 请在CDN侧为 <b>*index.php* 与 /index.php?s=/pay/*</b> 关闭「强制HTTPS跳转」「浏览器完整性检查/JS挑战」「缓存」, 否则支付网关的 http 回调可能被跳转或拦截导致<b>回调失败不发货</b>;<br>
                ③ Cloudflare 建议SSL模式用「完全(严格)」并放行 /index.php?s=/pay/* 的机器人规则。
            </div>
        </div>
        <div class="form-row">
            <label>公告与自定义单页 <a href="<?= au('notices') ?>" style="color:inherit">→ 前往「公告单页」管理</a>(支持发布多条公告 + 自由编辑联系客服/购买须知等单页内容)</label>
        </div>
        <div class="form-row">
            <label>订单未支付超时(分钟, 5-120)</label>
            <input type="number" name="order_timeout" value="<?= e(setting('order_timeout', '15')) ?>" min="5" max="120" style="width:120px">
        </div>
        <div class="form-row">
            <label>下单联系方式(可多选, 至少一项; 买家下单时选择类型并填写)</label>
            <input type="hidden" name="contact_types_submitted" value="1">
            <div class="form-inline chk-group">
                <?php $ctEnabled = contact_types_enabled(); ?>
                <?php foreach (contact_type_all() as $ck => $cv): ?>
                    <label class="chk"><input type="checkbox" name="contact_types[]" value="<?= e($ck) ?>" <?= in_array($ck, $ctEnabled, true) ? 'checked' : '' ?>> <?= e($cv) ?></label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-row">
            <label>当前主题</label>
            <select name="theme" style="max-width:300px">
                <?php foreach (theme_list() as $dir => $t): ?>
                    <option value="<?= e($dir) ?>" <?= active_theme() === $dir ? 'selected' : '' ?>><?= e($t['title']) ?><?= !empty($t['pro']) ? '(专业版专享)' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label>客服联系方式(前台右下角悬浮按钮展示, 可添加多条; 留空则不显示)</label>
            <input type="hidden" name="service_contacts" id="serviceContactsInput" value="<?= e(setting('service_contacts', '')) ?>">
            <div id="csList"></div>
            <button type="button" class="btn gray sm" id="csAdd" style="margin-top:8px">＋ 添加客服方式</button>
            <div class="desc">支持 Telegram / 邮箱 / QQ / 微信 / 电话; 备注选填(如「售后」「工作时间」)。Telegram填用户名(自动生成t.me链接), 微信/QQ买家点击复制。</div>
        </div>
        <div class="form-row">
            <label>官方市场地址(应用商店/会员授权API, 保持默认即可)</label>
            <input type="text" name="official_api" value="<?= e(setting('official_api', 'https://market.kunfaka.com')) ?>">
        </div>
        <button class="btn" type="submit">保存设置</button>
    </form>
</div>

<div class="card">
    <h3>发货邮件通知(SMTP)</h3>
    <form method="post" action="<?= au('settings_save') ?>" data-ajax>
        <?= csrf_field() ?>
        <div class="form-row">
            <label>开启邮件发卡</label>
            <select name="smtp_open">
                <option value="0" <?= setting('smtp_open') !== '1' ? 'selected' : '' ?>>关闭</option>
                <option value="1" <?= setting('smtp_open') === '1' ? 'selected' : '' ?>>开启</option>
            </select>
        </div>
        <div class="form-row">
            <label>SMTP服务器 / 端口 / 加密</label>
            <div class="form-inline">
                <input type="text" name="smtp_host" value="<?= e(setting('smtp_host')) ?>" placeholder="smtp.qq.com" style="width:220px">
                <input type="number" name="smtp_port" value="<?= e(setting('smtp_port', '465')) ?>" style="width:100px">
                <select name="smtp_ssl" style="width:110px">
                    <option value="1" <?= setting('smtp_ssl') === '1' ? 'selected' : '' ?>>SSL</option>
                    <option value="0" <?= setting('smtp_ssl') !== '1' ? 'selected' : '' ?>>无加密</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <label>邮箱账号 / 授权码</label>
            <div class="form-inline">
                <input type="text" name="smtp_user" value="<?= e(setting('smtp_user')) ?>" placeholder="xxx@qq.com" style="width:220px">
                <input type="text" name="smtp_pass" value="<?= e(setting('smtp_pass')) ?>" placeholder="SMTP授权码" style="width:200px">
            </div>
            <div class="desc">使用QQ邮箱请开启SMTP服务并填写授权码; 卡密将自动发送到买家邮箱。</div>
        </div>
        <button class="btn" type="submit">保存邮件设置</button>
    </form>
</div>

<div class="card">
    <h3>计划任务建议(宝塔)</h3>
    <p class="dim">在宝塔面板「计划任务」中添加任务类型 <b>Shell脚本</b>, 每 1 分钟执行:</p>
    <pre class="cards">php <?= e(YF_ROOT) ?>/cron.php</pre>
    <p class="dim" style="margin-top:6px">用于: 订单过期自动关闭、USDT免挂支付到账轮询(未配置计划任务时, 买家支付页会自动触发检测)。</p>
</div>

<script>
(function () {
    var types = <?= json_encode(contact_type_all(), JSON_UNESCAPED_UNICODE) ?>;
    var data = <?= json_encode(service_contacts(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
    var list = document.getElementById('csList');
    var hidden = document.getElementById('serviceContactsInput');
    if (!list || !hidden) return;

    function serialize() {
        var rows = [];
        list.querySelectorAll('.cs-row').forEach(function (row) {
            var value = row.querySelector('.cs-value').value.trim();
            if (!value) return;
            rows.push({
                type: row.querySelector('.cs-type').value,
                value: value,
                note: row.querySelector('.cs-note').value.trim()
            });
        });
        hidden.value = JSON.stringify(rows);
    }
    function addRow(item) {
        var row = document.createElement('div');
        row.className = 'cs-row';
        var opts = '';
        for (var k in types) opts += '<option value="' + k + '">' + types[k] + '</option>';
        row.innerHTML = '<select class="cs-type">' + opts + '</select>' +
            '<input class="cs-value" placeholder="客服账号">' +
            '<input class="cs-note" placeholder="备注(选填)">' +
            '<button type="button" class="btn sm red cs-del">删除</button>';
        if (item) {
            row.querySelector('.cs-type').value = item.type || 'qq';
            row.querySelector('.cs-value').value = item.value || '';
            row.querySelector('.cs-note').value = item.note || '';
        }
        list.appendChild(row);
    }
    list.addEventListener('click', function (ev) {
        var del = ev.target.closest ? ev.target.closest('.cs-del') : null;
        if (del) { del.closest('.cs-row').remove(); serialize(); }
    });
    list.addEventListener('input', serialize);
    list.addEventListener('change', serialize);
    document.getElementById('csAdd').addEventListener('click', function () { addRow(null); });
    data.forEach(function (item) { addRow(item); });
})();
</script>
