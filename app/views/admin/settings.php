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
                <label>Logo 图片</label>
                <div class="up-zone" id="logoZone">
                    <span class="up-ico" id="logoPrev"><?php if (setting('logo_image')): ?><img src="<?= e(site_url(setting('logo_image'))) ?>" alt="logo"><?php else: ?>🖼<?php endif; ?></span>
                    <div class="up-txt">
                        <b>点击上传 Logo 图片</b>
                        png / webp / jpg, ≤2MB, 建议透明底; 换用其他类型会自动停用图片<span class="up-file" id="logoFileName"></span>
                    </div>
                    <input type="file" class="up-input" name="logo_image_file" id="logoFile" accept=".png,.webp,.jpg,.jpeg">
                </div>
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
                    <?php foreach (theme_list() as $dir => $t): $locked = !empty($t['pro']) && !License::isPro(); ?>
                        <option value="<?= e($dir) ?>" <?= active_theme() === $dir ? 'selected' : '' ?> <?= $locked ? 'disabled' : '' ?>><?= e($t['title']) ?><?= !empty($t['pro']) ? ($locked ? ' (专业版·未开通)' : ' (专业版)') : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="desc">专业版主题需在「授权中心」激活专业版后启用; 主题文件也可在「应用商店」获取。</div>
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
                <label>搜索引擎收录限制(robots.txt)</label>
                <select name="robots_disallow">
                    <option value="1" <?= setting('robots_disallow', '1') === '1' ? 'selected' : '' ?>>开启(默认, 禁止收录敏感路径)</option>
                    <option value="0" <?= setting('robots_disallow', '1') !== '1' ? 'selected' : '' ?>>关闭(允许收录全站)</option>
                </select>
                <div class="desc">开启后 /robots.txt 告知搜索引擎不抓取 /data/、/cron.php、/install.php、/tools/ 等敏感路径; 后台登录页同时响应 noindex 头(不会暴露后台随机入口地址)。仅对遵守 robots 协议的搜索引擎生效。</div>
            </div>
            <div class="form-row">
                <label>自动更新</label>
                <select name="auto_update">
                    <option value="0" <?= setting('auto_update', '0') !== '1' ? 'selected' : '' ?>>关闭(默认, 发现新版本时手动确认升级)</option>
                    <option value="1" <?= setting('auto_update', '0') === '1' ? 'selected' : '' ?>>开启(发现新版本自动完成升级, 无需人工确认)</option>
                </select>
                <div class="desc">开启后系统检测到官方新版本将自动下载更新包并完成升级(自动保留您的配置与上传文件)。建议计划任务(cron)保持开启以加速更新包预下载。</div>
            </div>
            <div class="form-row">
                <label>下单联系方式</label>
                <div class="desc">已改为<b>每个商品独立设置</b>: 编辑商品时在「下单联系方式」中勾选该商品支持的联系方式(收件人姓名/邮寄地址为附加信息字段)。商品未配置时默认使用邮箱。</div>
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
            <div class="form-row">
                <label>微信 / QQ 防红</label>
                <input type="hidden" name="anti_red_submitted" value="1">
                <style>
                    .ar-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
                    @media (max-width:760px){.ar-grid{grid-template-columns:1fr}}
                    .ar-card{position:relative;display:flex;align-items:center;gap:13px;padding:15px 16px;border:1.5px solid var(--input-border);border-radius:15px;cursor:pointer;transition:border-color .18s,box-shadow .18s,transform .12s;background:var(--input-bg);user-select:none;overflow:hidden}
                    .ar-card:hover{border-color:var(--muted);transform:translateY(-1px)}
                    .ar-card:active{transform:translateY(0) scale(.99)}
                    .ar-card input{position:absolute;opacity:0;pointer-events:none}
                    .ar-ico{flex:none;width:44px;height:44px;border-radius:13px;display:flex;align-items:center;justify-content:center;transition:.18s}
                    .ar-ico svg{width:24px;height:24px}
                    .ar-wx .ar-ico{background:linear-gradient(135deg,#07c160,#049143);box-shadow:0 6px 16px -6px rgba(7,193,96,.55)}
                    .ar-qq .ar-ico{background:linear-gradient(135deg,#12b7f5,#0a8fd0);box-shadow:0 6px 16px -6px rgba(18,183,245,.55)}
                    .ar-tx{flex:1;min-width:0}
                    .ar-tx b{display:block;font-size:13px;color:var(--text)}
                    .ar-tx small{display:block;font-size:11px;color:var(--muted);margin-top:3px;line-height:1.55}
                    .ar-sw{flex:none;width:42px;height:24px;border-radius:99px;background:var(--input-border);position:relative;transition:background .2s}
                    .ar-sw::after{content:'';position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.35);transition:left .2s cubic-bezier(.2,.7,.3,1.4)}
                    .ar-card:has(input:checked) .ar-sw::after{left:21px}
                    .ar-wx:has(input:checked) .ar-sw{background:#07c160}
                    .ar-qq:has(input:checked) .ar-sw{background:#12b7f5}
                    .ar-wx:has(input:checked){border-color:rgba(7,193,96,.55);box-shadow:0 0 0 3px rgba(7,193,96,.12)}
                    .ar-qq:has(input:checked){border-color:rgba(18,183,245,.55);box-shadow:0 0 0 3px rgba(18,183,245,.12)}
                    .ar-card:has(input:checked) .ar-tx small{color:var(--text2)}
                    .ar-on{display:none;font-size:10.5px;font-weight:700;padding:1px 7px;border-radius:99px;vertical-align:1px;margin-left:6px}
                    .ar-wx:has(input:checked) .ar-on.wx{display:inline-block;background:rgba(7,193,96,.16);color:#2ecc71}
                    .ar-qq:has(input:checked) .ar-on.qq{display:inline-block;background:rgba(18,183,245,.16);color:#3ec6ff}
                </style>
                <div class="ar-grid">
                    <label class="ar-card ar-wx">
                        <input type="checkbox" name="wx_anti_red" value="1" <?= setting('wx_anti_red', '0') === '1' ? 'checked' : '' ?>>
                        <span class="ar-ico">
                            <svg viewBox="0 0 48 48" fill="none">
                                <path d="M18 6C9.7 6 3 11.8 3 19c0 4.2 2.3 8 5.9 10.4L7.5 34l5.3-2.9c1.6.5 3.4.8 5.2.8h.9a12.6 12.6 0 0 1-.3-2.7c0-7 6.6-12.6 14.7-12.6h.8C32.9 10.6 26.1 6 18 6z" fill="#fff" opacity=".96"/>
                                <circle cx="12.5" cy="16.5" r="1.9" fill="#07c160"/>
                                <circle cx="23.5" cy="16.5" r="1.9" fill="#07c160"/>
                                <path d="M45 30.6c0-5.9-5.9-10.6-13.1-10.6S18.8 24.7 18.8 30.6 24.7 41.2 31.9 41.2c1.6 0 3.1-.2 4.5-.6l4.6 2.5-1.2-3.9C43 37 45 34 45 30.6z" fill="#fff" opacity=".96"/>
                                <circle cx="27.6" cy="29" r="1.6" fill="#07c160"/>
                                <circle cx="36.2" cy="29" r="1.6" fill="#07c160"/>
                            </svg>
                        </span>
                        <span class="ar-tx">
                            <b>微信防红<span class="ar-on wx">已开启</span></b>
                            <small>买家从微信内打开站点时, 先展示引导页提示「在浏览器中打开」, 避免域名被拦截与支付异常</small>
                        </span>
                        <span class="ar-sw"></span>
                    </label>
                    <label class="ar-card ar-qq">
                        <input type="checkbox" name="qq_anti_red" value="1" <?= setting('qq_anti_red', '0') === '1' ? 'checked' : '' ?>>
                        <span class="ar-ico">
                            <svg viewBox="0 0 48 48" fill="none">
                                <path d="M24 4c-7 0-12 5.4-12 12.4 0 1.5-.1 2.9-.5 4.3-1 3.4-3.5 6.6-3.5 9.9 0 1.9 1.2 3.4 3 3.4 1 0 2-.5 2.8-1.2 1 2.5 2.9 4.6 5.2 5.8-1.7.8-3 2-3 3.4 0 .8 2.6 2 8 2s8-1.2 8-2c0-1.4-1.3-2.6-3-3.4 2.3-1.2 4.2-3.3 5.2-5.8.8.7 1.8 1.2 2.8 1.2 1.8 0 3-1.5 3-3.4 0-3.3-2.5-6.5-3.5-9.9-.4-1.4-.5-2.8-.5-4.3C36 9.4 31 4 24 4z" fill="#fff" opacity=".96"/>
                                <circle cx="19.5" cy="17" r="2" fill="#12b7f5"/>
                                <circle cx="28.5" cy="17" r="2" fill="#12b7f5"/>
                            </svg>
                        </span>
                        <span class="ar-tx">
                            <b>QQ防红<span class="ar-on qq">已开启</span></b>
                            <small>买家从 QQ 内打开站点时, 先展示引导页提示「用浏览器打开」, 防止域名被冻结与支付中断</small>
                        </span>
                        <span class="ar-sw"></span>
                    </label>
                </div>
                <div class="desc">引导页含「复制链接」与「仍要继续访问」(24小时内不再拦截); 支付回调与后台登录不受影响。两开关独立生效。</div>
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
                    <option value="auto" <?= setting('cdn_mode', 'auto') === 'auto' ? 'selected' : '' ?>>自动判断(推荐)</option>
                    <option value="off" <?= setting('cdn_mode', 'off') === 'off' ? 'selected' : '' ?>>未套CDN(直连)</option>
                    <option value="cloudflare" <?= setting('cdn_mode', 'off') === 'cloudflare' ? 'selected' : '' ?>>已套 Cloudflare</option>
                    <option value="cdn" <?= setting('cdn_mode', 'off') === 'cdn' ? 'selected' : '' ?>>其他CDN / 反向代理</option>
                </select>
                <div class="desc">推荐保持「自动判断」: 系统自动识别请求是否经过 Cloudflare/反向代理并还原买家真实IP, 无需手动配置。</div>
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
                <label>验证方式(注册/登录/后台登录/下单购买)</label>
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
            <div class="form-row">
                <label>下单频控(同IP每小时最多下单数)</label>
                <input type="number" name="order_ip_limit" value="<?= e(setting('order_ip_limit', '30')) ?>" min="0" max="1000" style="max-width:130px">
                <div class="desc">防恶意刷单兜底: 超出后该IP本小时内无法再下单, 填 0 关闭频控(人机验证仍生效)。</div>
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

/* Logo 上传组件交互 */
(function () {
    var zone = document.getElementById('logoZone');
    var input = document.getElementById('logoFile');
    if (!zone || !input) return;
    zone.addEventListener('click', function () { input.click(); });
    input.addEventListener('change', function () {
        var f = input.files && input.files[0];
        if (!f) return;
        document.getElementById('logoPrev').innerHTML = '<img src="' + URL.createObjectURL(f) + '" alt="logo">';
        document.getElementById('logoFileName').textContent = '已选择: ' + f.name + ' (' + Math.max(1, Math.round(f.size / 1024)) + 'KB)';
        zone.classList.add('has');
    });
})();

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
