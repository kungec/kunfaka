<?php
/**
 * 坤发卡 - 全局公共函数
 */

/** HTML转义输出 */
function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** 当前时间戳 */
function now() {
    return time();
}

/** 出站HTTPS统一开启证书校验(防中间人); 个别支付网关证书异常时可在 data/config.php 定义 YF_HTTP_INSECURE=true 临时关闭 */
function curl_tls($ch) {
    if (defined('YF_HTTP_INSECURE') && YF_HTTP_INSECURE) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        return;
    }
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
}

/** Cloudflare 官方IP段(用于自动识别请求是否经过CF) */
function cf_ip_ranges() {
    return [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];
}

/** IP是否落在CIDR段内(支持IPv4/IPv6) */
function ip_in_cidr($ip, $cidr) {
    if (strpos($cidr, '/') === false) return strcasecmp($ip, $cidr) === 0;
    [$net, $bits] = explode('/', $cidr, 2);
    $ipBin = @inet_pton($ip);
    $netBin = @inet_pton($net);
    if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) return false;
    $bits = (int)$bits;
    $full = intdiv($bits, 8);
    $rem = $bits % 8;
    if ($full > 0 && substr($ipBin, 0, $full) !== substr($netBin, 0, $full)) return false;
    if ($rem > 0) {
        $mask = (0xFF << (8 - $rem)) & 0xFF;
        if ((ord($ipBin[$full]) & $mask) !== (ord($netBin[$full]) & $mask)) return false;
    }
    return true;
}

function ip_in_cidrs($ip, array $cidrs) {
    foreach ($cidrs as $c) if (ip_in_cidr($ip, $c)) return true;
    return false;
}

function is_public_ip($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

/**
 * 客户端真实IP(全自动判断, 无需手动配置CDN模式):
 * ① REMOTE_ADDR 是回环/内网地址 → 请求来自本机反代, 依次取 CF-Connecting-IP / X-Real-IP / XFF中最右公网IP;
 * ② REMOTE_ADDR 属于 Cloudflare 官方IP段 → 自动识别为经CF, 取 CF-Connecting-IP;
 * ③ 其余情况(直连) → REMOTE_ADDR 即真实IP。
 * cdn_mode 设置仍兼容: cloudflare 模式下优先取 CF 头; cdn 模式下按 XFF 规则解析。
 */
function client_ip() {
    $remote = isset($_SERVER['REMOTE_ADDR']) ? trim((string)$_SERVER['REMOTE_ADDR']) : '';
    if ($remote === '') $remote = '0.0.0.0';
    $cfIp = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? trim((string)$_SERVER['HTTP_CF_CONNECTING_IP']) : '';
    if ($cfIp === '' || !filter_var($cfIp, FILTER_VALIDATE_IP)) $cfIp = '';
    $mode = setting('cdn_mode', 'auto');
    // 严格直连模式(off): 不信任任何代理头, 直接返回TCP对端
    if ($mode === 'off') return $remote;

    // 兼容旧配置: 显式设置的模式优先生效
    if ($mode === 'cloudflare' && $cfIp !== '') return $cfIp;
    if ($mode === 'cdn') {
        $fromXff = '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            foreach (array_reverse(array_map('trim', explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']))) as $ip) {
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) && is_public_ip($ip)) { $fromXff = $ip; break; }
            }
        }
        if ($fromXff !== '') return $fromXff;
        if (!empty($_SERVER['HTTP_X_REAL_IP']) && filter_var(trim((string)$_SERVER['HTTP_X_REAL_IP']), FILTER_VALIDATE_IP)) {
            return trim((string)$_SERVER['HTTP_X_REAL_IP']);
        }
    }

    // ---- 自动判断(不依赖设置) ----
    // ① REMOTE_ADDR 是回环/内网 → 请求经过本机/内网反代, 从代理头还原真实IP
    if (!filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        if ($cfIp !== '') return $cfIp;
        if (!empty($_SERVER['HTTP_X_REAL_IP']) && filter_var(trim((string)$_SERVER['HTTP_X_REAL_IP']), FILTER_VALIDATE_IP)) {
            return trim((string)$_SERVER['HTTP_X_REAL_IP']);
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            foreach (array_reverse(array_map('trim', explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']))) as $ip) {
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) && is_public_ip($ip)) return $ip;
            }
        }
        return $remote; // 本机直连访问, 如实返回
    }
    // ② REMOTE_ADDR 属于 Cloudflare 官方IP段 → 自动识别为经CF
    if ($cfIp !== '' && ip_in_cidrs($remote, cf_ip_ranges())) return $cfIp;
    // ③ 直连
    return $remote;
}

/** 金额显示 */
function nf($v) {
    return number_format((float)$v, 2, '.', '');
}

/** 站点根URL(末尾带/) */
function site_url($path = '') {
    static $base = null;
    if ($base === null) {
        $base = trim((string)setting('site_url'));
        if ($base === '') {
            // 协议探测与bootstrap会话Cookie一致: 支持反代终结TLS的场景(X-Forwarded-Proto)
            $scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
            if ($scheme === 'http' && !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
                if (strtolower(trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_PROTO'])[0])) === 'https') $scheme = 'https';
            }
            // Host头校验: 非法格式(注入字符/缺失)一律回退localhost, 防Host注入进入生成的链接与支付回调地址
            $rawHost = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
            $hostPart = preg_replace('/:\d{1,5}$/', '', $rawHost);
            $okHost = $hostPart !== '' && (filter_var($hostPart, FILTER_VALIDATE_IP)
                || filter_var($hostPart, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME));
            $host = $okHost ? $rawHost : 'localhost';
            $dir = rtrim(str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/')), '/');
            $base = $scheme . '://' . $host . $dir . '/';
        }
        if (substr($base, -1) !== '/') $base .= '/';
    }
    return $base . ltrim($path, '/');
}

/** 前台URL */
function u($route, $params = []) {
    $path = '/' . ltrim($route, '/');
    // 伪静态开启: 前台输出路径形式URL(需服务器将不存在路径回退到 index.php)
    try {
        if (setting('url_rewrite', '0') === '1') {
            return site_url($path) . ($params ? '?' . http_build_query($params) : '');
        }
    } catch (Exception $ex) {
    }
    $q = 'index.php?s=/' . ltrim($route, '/');
    if ($params) $q .= '&' . http_build_query($params);
    return site_url($q);
}

/** 后台URL(入口文件名安装时随机生成, 由YF_ADMIN_ENTRY常量指定) */
function au($route, $params = []) {
    $entry = defined('YF_ADMIN_ENTRY') ? YF_ADMIN_ENTRY : 'admin.php';
    $q = $entry . '?s=/' . ltrim($route, '/');
    if ($params) $q .= '&' . http_build_query($params);
    return site_url($q);
}

/** 当前激活主题 */
function active_theme() {
    static $cached = null;
    if ($cached !== null) return $cached;
    $t = trim((string)setting('theme', 'store'));
    // 目录名严格白名单, 防止settings被写入 ../ 等路径形式
    if (!preg_match('/^[a-z0-9_\-]{1,40}$/', $t) || !is_dir(YF_ROOT . '/themes/' . $t)) $t = 'store';
    // 专业版主题兜底: 未激活专业版时回退默认主题
    if ($t !== 'store' && ($tmeta = Plugin::meta('theme', $t)) && !empty($tmeta['pro']) && !License::isPro()) {
        $t = 'store';
    }
    // 付费主题加密版运行时门控: 授权/域名绑定不一致时回退默认主题(明文源码为开发模式豁免)
    if ($t !== 'store' && Protector::isProApp('theme', $t) && !Protector::gate('theme', $t)) {
        $t = 'store';
    }
    return $cached = $t;
}

/** 读取系统配置(带缓存; setting_set 写入会同步穿透缓存) */
function setting($k, $default = '') {
    if (!isset($GLOBALS['YF_SET_CACHE'])) {
        $GLOBALS['YF_SET_CACHE'] = [];
        try {
            $rows = DB::fetchAll('SELECT k,v FROM settings');
            foreach ($rows as $r) $GLOBALS['YF_SET_CACHE'][$r['k']] = $r['v'];
        } catch (Exception $ex) {
        }
    }
    return isset($GLOBALS['YF_SET_CACHE'][$k]) && $GLOBALS['YF_SET_CACHE'][$k] !== '' ? $GLOBALS['YF_SET_CACHE'][$k] : $default;
}

/** 写系统配置(同步更新本次请求的配置缓存) */
function setting_set($k, $v) {
    DB::exec('REPLACE INTO settings (k, v) VALUES (?, ?)', [$k, $v]);
    if (isset($GLOBALS['YF_SET_CACHE'])) $GLOBALS['YF_SET_CACHE'][$k] = $v;
}

/** 读取应用(插件/主题)配置 */
function app_config($name) {
    $row = DB::fetch('SELECT config FROM apps WHERE name = ?', [$name]);
    if (!$row || $row['config'] === null || $row['config'] === '') return [];
    $arr = json_decode($row['config'], true);
    return is_array($arr) ? $arr : [];
}

/** 保存应用配置 */
function app_config_save($name, $config) {
    DB::exec('UPDATE apps SET config = ? WHERE name = ?', [json_encode($config, JSON_UNESCAPED_UNICODE), $name]);
}

/** 生成订单号(高熵随机, 防止订单/卡密被遍历枚举; 64位随机空间) */
function order_sn() {
    return date('Ymd') . strtoupper(bin2hex(random_bytes(8)));
}

/** CSRF Token */
function csrf_token() {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['_csrf'];
}

function csrf_field() {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check() {
    $t = isset($_REQUEST['_csrf']) ? $_REQUEST['_csrf'] : '';
    if (empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $t)) {
        http_response_code(403);
        exit('非法请求(CSRF校验失败)');
    }
}

/** 订单超时时间(分钟) */
function order_timeout_minutes() {
    return max(5, (int)setting('order_timeout', '15'));
}

/** JSON输出并退出 */
function json_out($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** HTTP 302跳转(清洗换行, 防Location头注入) */
function redirect($url) {
    header('Location: ' . str_replace(["\r", "\n", "\0"], '', (string)$url));
    exit;
}

/** 取代get_magic_quotes等: 安全取值 */
function arr_get($arr, $key, $default = '') {
    return isset($arr[$key]) ? $arr[$key] : $default;
}

/** 发送邮件(可选SMTP) */
function send_mail($to, $subject, $body) {
    if (setting('smtp_open') !== '1') return false;
    try {
        require_once YF_ROOT . '/app/lib/Smtp.php';
        $smtp = new Smtp(setting('smtp_host'), (int)setting('smtp_port'), (bool)setting('smtp_ssl'), setting('smtp_user'), setting('smtp_pass'));
        return $smtp->send($to, $subject, $body, setting('site_name', '坤发卡'));
    } catch (Exception $ex) {
        return false;
    }
}

/** 前台注册/登录功能是否开启(后台系统设置可关) */
function member_open() {
    return setting('member_open', '1') === '1';
}

/** 前台可用分类(已启用; 后台管理用 actionCategories 全量查询) */
function cat_list() {
    return DB::fetchAll('SELECT * FROM categories WHERE status = 1 ORDER BY sort ASC, id ASC');
}

/** 商品缩略图路径(原图路径推导: p_x.ext → p_x_thumb.ext); 非上传图返回 null */
function product_thumb_path($icon) {
    if (!is_string($icon) || $icon === '' || strpos($icon, 'uploads/') !== 0) return null;
    $info = pathinfo($icon);
    return $info['dirname'] . '/' . $info['filename'] . '_thumb.' . strtolower(isset($info['extension']) ? $info['extension'] : 'jpg');
}

/** 商品图片URL: variant=thumb 优先返回缩略图(不存在回退原图); 无图返回 '' */
function product_image_url($icon, $variant = 'full') {
    if (!is_string($icon) || $icon === '' || strpos($icon, 'uploads/') !== 0) return '';
    if ($variant === 'thumb') {
        $t = product_thumb_path($icon);
        if ($t !== null && is_file(YF_ROOT . '/' . $t)) return $t;
    }
    return $icon;
}

/** 前台站点Logo(按系统设置: 图片/文字; 返回null表示使用主题默认样式) */
function site_logo_html($fallbackMark = '') {
    $type = setting('logo_type', 'default');
    $name = setting('site_name', '坤发卡');
    if ($type === 'image' && setting('logo_image')) {
        return '<img src="' . e(site_url(setting('logo_image'))) . '" alt="' . e($name) . '" style="height:38px;max-width:200px;object-fit:contain">';
    }
    if ($type === 'text') {
        return '<span style="font-weight:800;font-size:17px;letter-spacing:.5px">' . e(setting('logo_text') ?: $name) . '</span>';
    }
    return null;
}

/** 当前访客的会员等级数值(游客/无等级为0) */
function viewer_level() {
    $uid = current_user_id();
    if ($uid <= 0) return 0;
    $v = DB::value('SELECT l.level FROM users u LEFT JOIN member_levels l ON l.id = u.level_id WHERE u.id = ?', [$uid]);
    return $v ? (int)$v : 0;
}

/** 商品对指定等级是否可见(所属分组有最低等级要求时校验) */
function product_visible(array $product, $level = null) {
    $gid = (int)($product['group_id'] ?? 0);
    if ($gid <= 0) return true;
    static $cache = [];
    if (!isset($cache[$gid])) {
        $cache[$gid] = (int)DB::value('SELECT min_level FROM product_groups WHERE id = ?', [$gid]);
    }
    if ($level === null) $level = viewer_level();
    return $level >= $cache[$gid];
}

/** 当前登录的管理员行(未登录/已禁用返回null; 每请求缓存) */
function current_admin() {
    static $row = null, $loaded = false;
    if (!$loaded) {
        $loaded = true;
        $id = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
        $row = $id > 0 ? DB::fetch('SELECT * FROM admin_users WHERE id = ? AND status = 1', [$id]) : null;
    }
    return $row;
}

/** 当前登录前台会员ID(未登录0; 会员功能关闭时视为游客) */
function current_user_id() {
    if (!member_open()) return 0;
    return isset($_SESSION['front_user_id']) ? (int)$_SESSION['front_user_id'] : 0;
}

/** 写系统日志(失败静默, 不影响业务) */
function add_log($type, $message) {
    try {
        DB::insert('logs', [
            'type' => substr(trim($type), 0, 20),
            'message' => mb_substr((string)$message, 0, 500),
            'ip' => client_ip(),
            'ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'created_at' => now(),
        ]);
    } catch (Exception $ex) {
    }
}

/** 日志类型中文名 */
function log_type_label($type) {
    $map = ['admin' => '管理', 'user' => '会员', 'pay' => '支付', 'usdt' => '链上', 'store' => '商店', 'system' => '系统'];
    return isset($map[$type]) ? $map[$type] : '其他';
}

/** 客服联系方式列表(后台配置, 前台展示) */
function service_contacts() {
    $raw = trim((string)setting('service_contacts', ''));
    if ($raw === '') return [];
    $arr = json_decode($raw, true);
    if (!is_array($arr)) return [];
    $allowed = array_keys(contact_type_all());
    $out = [];
    foreach ($arr as $row) {
        $t = isset($row['type']) ? $row['type'] : '';
        $v = isset($row['value']) ? trim($row['value']) : '';
        if ($t && $v !== '' && in_array($t, $allowed, true)) {
            $out[] = ['type' => $t, 'value' => $v, 'note' => isset($row['note']) ? trim($row['note']) : ''];
        }
    }
    return $out;
}

/** 客服联系方式直达链接(空串=复制型) */
function service_contact_link($type, $value) {
    switch ($type) {
        case 'telegram': return 'https://t.me/' . ltrim($value, '@');
        case 'email': return 'mailto:' . $value;
        case 'phone': return 'tel:' . preg_replace('/[^0-9+]/', '', $value);
        default: return '';
    }
}

/** 联系方式类型定义(互斥单选类型 + 附加信息类型) */
function contact_type_all() {
    return ['telegram' => 'Telegram', 'email' => '邮箱', 'qq' => 'QQ', 'wechat' => '微信', 'phone' => '电话', 'name' => '收件人姓名', 'address' => '邮寄地址'];
}

/** 互斥单选的联系方式类型(买家五选一) */
function contact_type_single_keys() {
    return ['telegram', 'email', 'qq', 'wechat', 'phone'];
}

/** 附加信息类型(下单时额外必填字段, 如收件人姓名/邮寄地址) */
function contact_type_extra_keys() {
    return ['name', 'address'];
}

/**
 * 商品启用的下单联系方式类型(商品级配置, 不再读全局设置)
 * 返回互斥单选数组; $withExtra=true 时额外返回 ['extra' => 附加类型数组, 'all' => 全部启用类型]
 */
function product_contact_types($product, $withExtra = false)
{
    $all = contact_type_all();
    $raw = trim((string)($product['contact_types'] ?? ''));
    $types = [];
    foreach (explode(',', $raw) as $t) {
        $t = trim($t);
        if ($t !== '' && isset($all[$t])) $types[] = $t;
    }
    // 商品未选择任何联系方式: 默认邮箱
    if (!$types) $types = ['email'];
    $single = array_values(array_intersect(contact_type_single_keys(), $types));
    if (!$single) $single = ['email'];
    if (!$withExtra) return $single;
    return [$single, ['extra' => array_values(array_intersect(contact_type_extra_keys(), $types)), 'all' => $types]];
}

/** 联系方式类型中文名 */
function contact_type_label($type) {
    $all = contact_type_all();
    return isset($all[$type]) ? $all[$type] : '联系方式';
}

/** 联系方式格式校验, 返回错误信息(空串=通过) */
function contact_validate($type, $value) {
    $value = trim($value);
    if ($value === '') return '请填写联系方式, 方便接收卡密与查询订单';
    switch ($type) {
        case 'email':
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) return '邮箱格式不正确';
            break;
        case 'phone':
            if (!preg_match('/^[0-9+\-\s]{5,20}$/', $value)) return '电话号码格式不正确';
            break;
        case 'qq':
            if (!preg_match('/^[1-9][0-9]{4,10}$/', $value)) return 'QQ号格式不正确(5-11位数字)';
            break;
        case 'telegram':
            if (!preg_match('/^@?[A-Za-z0-9_]{4,32}$/', $value)) return 'Telegram用户名格式不正确(@+4-32位字母数字下划线)';
            break;
        case 'wechat':
            if (!preg_match('/^[A-Za-z0-9_-]{4,20}$/', $value)) return '微信号格式不正确(4-20位字母数字下划线)';
            break;
        case 'name':
            $l = mb_strlen($value);
            if ($l < 2 || $l > 50) return '收件人姓名长度需为2-50字';
            break;
        case 'address':
            $l = mb_strlen($value);
            if ($l < 5 || $l > 200) return '邮寄地址长度需为5-200字';
            break;
    }
    if (mb_strlen($value) > 100) return '联系方式过长';
    return '';
}

/** 联系方式输入框占位提示 */
function contact_type_placeholder($type) {
    $map = [
        'telegram' => '@username',
        'email' => 'you@example.com',
        'qq' => 'QQ号',
        'wechat' => '微信号',
        'phone' => '手机号',
        'name' => '收件人姓名',
        'address' => '收货地址(省市区+详细地址)',
    ];
    return isset($map[$type]) ? $map[$type] : '请填写';
}

/** 剩余库存 */
function product_stock($productId) {
    return (int)DB::value('SELECT COUNT(*) FROM cards WHERE product_id = ? AND status = 0', [$productId]);
}

/** 前台支付方式列表(含配置, 用于品牌图标匹配) */
function enabled_payments() {
    $rows = DB::fetchAll("SELECT a.name, a.enabled, a.config FROM apps a WHERE a.type = 'payment' AND a.enabled = 1");
    $labels = ['alipay' => '支付宝', 'wxpay' => '微信', 'qqpay' => 'QQ钱包'];
    $list = [];
    foreach ($rows as $r) {
        $meta = Plugin::meta('payment', $r['name']);
        if (!$meta) continue;
        $cfg = $r['config'] ? (array)json_decode($r['config'], true) : [];
        // 传入已查询的行, 免去 Plugin::payment 内逐插件再查一次
        $plugin = Plugin::payment($r['name'], $r);
        // 付费应用未通过授权门控(或加载失败): 前台完全不展示
        if (!$plugin) continue;
        $channels = $plugin->channels();
        // 聚合插件勾选了多个渠道: 按渠道展开成多个带品牌图标的支付方式
        if (count($channels) > 1) {
            foreach ($channels as $ch) {
                $list[] = ['code' => $r['name'], 'channel' => $ch, 'title' => $meta['title'] . ' · ' . (isset($labels[$ch]) ? $labels[$ch] : $ch), 'config' => $cfg, 'icon' => payment_icon($ch)];
            }
            continue;
        }
        $list[] = ['code' => $r['name'], 'channel' => $channels ? $channels[0] : '', 'title' => $meta['title'], 'config' => $cfg, 'icon' => payment_icon($r['name'], $cfg)];
    }
    return $list;
}

/** 支付品牌图标(内联SVG, 按插件代码/聚合渠道自动匹配) */
function payment_icon($code, $cfg = []) {
    $code = strtolower((string)$code);
    if (strpos($code, 'usdt') !== false || strpos($code, 'trc20') !== false) return payment_icon_svg('usdt');
    if (strpos($code, 'visa') !== false || strpos($code, 'master') !== false || strpos($code, 'card') !== false) return payment_icon_svg('card');
    if (strpos($code, 'wx') !== false || strpos($code, 'wechat') !== false || strpos($code, 'weixin') !== false) return payment_icon_svg('wechat');
    if (strpos($code, 'ali') !== false) return payment_icon_svg('alipay');
    $type = isset($cfg['type']) ? strtolower((string)$cfg['type']) : '';
    if ($type === 'alipay') return payment_icon_svg('alipay');
    if ($type === 'wxpay' || $type === 'wechat') return payment_icon_svg('wechat');
    if ($type === 'qqpay' || $type === 'qq') return payment_icon_svg('qq');
    return payment_icon_svg('generic');
}

/** 品牌SVG(内联, 无外部依赖) */
function payment_icon_svg($key) {
    $map = [
        'card' => '<svg class="pay-brand" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><rect width="24" height="24" rx="5.5" fill="#1a1f36"/><rect x="3.5" y="7" width="17" height="11" rx="2" fill="none" stroke="#fff" stroke-width="1.4"/><rect x="3.5" y="9.6" width="17" height="2.2" fill="#fff"/><circle cx="16.2" cy="14.6" r="2" fill="#EB001B"/><circle cx="18.2" cy="14.6" r="2" fill="#F79E1B" fill-opacity=".9"/></svg>',
        'alipay' => '<svg class="pay-brand" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><rect width="24" height="24" rx="5.5" fill="#1677FF"/><text x="12" y="17" font-size="13" font-weight="700" fill="#fff" text-anchor="middle" font-family="PingFang SC, Microsoft YaHei, sans-serif">支</text></svg>',
        'wechat' => '<svg class="pay-brand" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><rect width="24" height="24" rx="5.5" fill="#07C160"/><path d="M9.7 5.2C6 5.2 3 7.6 3 10.6c0 1.7 1 3.2 2.5 4.2l-.6 2.1 2.4-1.2c.7.2 1.5.4 2.4.4h.3a5.5 5.5 0 0 1-.2-1.5c0-2.9 2.8-5.2 6.2-5.2h.4c-.6-2.4-3.3-4.2-6.7-4.2z" fill="#fff"/><circle cx="7.8" cy="9.5" r=".9" fill="#07C160"/><circle cx="11.6" cy="9.5" r=".9" fill="#07C160"/><path d="M21 14.6c0-2.4-2.4-4.3-5.3-4.3s-5.3 1.9-5.3 4.3 2.4 4.3 5.3 4.3c.6 0 1.2-.1 1.8-.3l2 1-.5-1.8c1.2-.8 2-1.9 2-3.2z" fill="#fff" opacity=".95"/><circle cx="14" cy="14.2" r=".7" fill="#07C160"/><circle cx="17.4" cy="14.2" r=".7" fill="#07C160"/></svg>',
        'usdt' => '<svg class="pay-brand" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#26A17B"/><rect x="7" y="6.6" width="10" height="2.1" rx=".4" fill="#fff"/><path d="M6.9 8.7h10.2v1.7c0 1-2.3 1.8-5.1 1.8s-5.1-.8-5.1-1.8z" fill="#fff" opacity=".96"/><rect x="10.65" y="8.7" width="2.7" height="8.7" fill="#fff"/></svg>',
        'qq' => '<svg class="pay-brand" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><rect width="24" height="24" rx="5.5" fill="#12B7F5"/><text x="12" y="16.5" font-size="10" font-weight="800" fill="#fff" text-anchor="middle" font-family="Arial, sans-serif">QQ</text></svg>',
        'generic' => '<svg class="pay-brand" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><rect width="24" height="24" rx="5.5" fill="#6366F1"/><path d="M5.5 9.2h13v7.3a1.8 1.8 0 0 1-1.8 1.8H7.3a1.8 1.8 0 0 1-1.8-1.8z" fill="#fff"/><path d="M7 9.2V8a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v1.2" fill="none" stroke="#fff" stroke-width="1.6"/><circle cx="14.5" cy="13.6" r="1.5" fill="#6366F1"/></svg>',
    ];
    return isset($map[$key]) ? $map[$key] : $map['generic'];
}

/** 主题展示信息 */
function theme_list() {
    $list = [];
    foreach (glob(YF_ROOT . '/themes/*/*.json') as $file) {
        $json = json_decode((string)file_get_contents($file), true);
        if (is_array($json) && (empty($json['type']) || $json['type'] === 'theme')) {
            $list[basename(dirname($file))] = $json;
        }
    }
    return $list;
}

/** 前台公告条数据: 最新一条上架公告; 公告表为空时回退旧版单条公告设置 */
function latest_notice()
{
    try {
        $n = DB::fetch('SELECT id, title FROM notices WHERE status = 1 ORDER BY sort DESC, id DESC LIMIT 1');
    } catch (Exception $ex) {
        $n = null;
    }
    if ($n) return ['id' => (int)$n['id'], 'title' => (string)$n['title']];
    $legacy = trim((string)setting('announcement'));
    return $legacy !== '' ? ['id' => 0, 'title' => $legacy] : null;
}

/** 自定义单页是否开启 */
function singlepage_open()
{
    return setting('singlepage_open') === '1';
}

/** 应用商店条目演示截图地址: json的shot字段 > 本地 shot.svg/png/jpg/webp > 无 */
function store_shot_url($type, $name, $meta = [])
{
    $shot = isset($meta['shot']) ? trim((string)$meta['shot']) : '';
    if ($shot !== '') {
        if (preg_match('#^(https?:)?//#i', $shot)) return $shot;
        return site_url(ltrim($shot, '/'));
    }
    $baseDir = ($type === 'theme' ? 'themes/' : 'plugins/') . $name . '/';
    foreach (['shot.svg', 'shot.png', 'shot.jpg', 'shot.webp'] as $f) {
        if (is_file(YF_ROOT . '/' . $baseDir . $f)) return site_url($baseDir . $f);
    }
    return '';
}

/** 微信/QQ防红: 按UA与开关判断命中, 返回 'wx' | 'qq' | '' */
function anti_red_hit()
{
    $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '') return '';
    if (setting('wx_anti_red') === '1' && strpos($ua, 'micromessenger') !== false) return 'wx';
    if (setting('qq_anti_red') === '1' && preg_match('/qq\/[\d.]+/', $ua)) return 'qq';
    return '';
}

/** 微信/QQ防红拦截: 命中且未放行时渲染引导页并终止(仅前台GET, 回调/轮询不拦); 在 Router 前台分支调用 */
function anti_red_intercept($route = null)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') return;
    $hit = anti_red_hit();
    if ($hit === '' || isset($_COOKIE['yf_ar_ok'])) return;
    $route = $route !== null ? strtolower(trim((string)$route, '/')) : strtolower(trim((string)($_GET['s'] ?? '')));
    if (in_array($route, ['pay/notify', 'pay/check', 'captcha/image'], true)) return;
    require YF_ROOT . '/app/views/anti_red.php';
    exit;
}

/** 聚合支付渠道中文名 */
function payment_channel_label($channel)
{
    $map = ['alipay' => '支付宝', 'wxpay' => '微信', 'qqpay' => 'QQ钱包'];
    return $map[(string)$channel] ?? ucfirst((string)$channel);
}

/** 订单支付方式可读名称: 插件标题(+ · 渠道名); 插件已卸载时回退原代码 */
function payment_display_name($payPlugin, $channel = '')
{
    $payPlugin = trim((string)$payPlugin);
    if ($payPlugin === '') return '';
    $meta = Plugin::meta('payment', $payPlugin);
    $title = is_array($meta) && isset($meta['title']) && $meta['title'] !== '' ? $meta['title'] : $payPlugin;
    $channel = trim((string)$channel);
    if ($channel !== '') $title .= ' · ' . payment_channel_label($channel);
    return $title;
}

/** 后台内联SVG图标(stroke风格, currentColor随文字色) */
function ico($name, $size = 15) {
    static $p = [
        'chart' => '<path d="M3 3v18h18"/><path d="M7 15l4-6 4 3 5-8"/>',
        'orders' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h6"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'award' => '<circle cx="12" cy="8" r="6"/><path d="M15.5 13l1.5 8-5-3-5 3 1.5-8"/>',
        'package' => '<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v9"/>',
        'folder' => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
        'megaphone' => '<path d="M3 11l18-7v18l-9-4H3z"/><path d="M7 11v6a2 2 0 0 0 4 0v-4"/>',
        'key' => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="M11 12l9-9M17 6l3 3M14 9l2 2"/>',
        'store' => '<path d="M6 2L3 7v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7l-3-5z"/><path d="M3 7h18M16 11a4 4 0 0 1-8 0"/>',
        'crown' => '<path d="M2 18h20M3 18l-1-9 5.5 4L12 5l4.5 8L23 9l-1 9z"/>',
        'logs' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h0a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h0a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'globe' => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
        'trash' => '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>',
        'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/>',
        'edit' => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'shield2' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>',
        'zap' => '<path d="M13 2L3 14h9l-1 8 10-12h-9z"/>',
        'gift' => '<path d="M20 12v10H4V12M2 7h20v5H2zM12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>',
        'cart' => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
        'ban' => '<circle cx="12" cy="12" r="10"/><path d="M4.9 4.9l14.2 14.2"/>',
        'mail' => '<path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><path d="M22 6l-10 7L2 6"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
        'refresh' => '<path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'unlock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>',
        'message' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'file' => '<path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M13 2v7h7"/>',
        'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.81.3 1.6.54 2.36a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.72-1.11a2 2 0 0 1 2.11-.45c.76.24 1.55.42 2.36.54A2 2 0 0 1 22 16.92z"/>',
        'inbox' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'send' => '<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/>',
        'alert' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5M12 3v12"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    ];
    if (!isset($p[$name])) return '';
    return '<svg class="ico-svg" width="' . (int)$size . '" height="' . (int)$size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2.5px;flex:none">' . $p[$name] . '</svg>';
}

/** Telegram机器人消息推送(管理员通知通道; 未配置或网络不可达时静默跳过) */
function send_telegram($text)
{
    $token = trim((string)setting('tg_bot_token', ''));
    $chat = trim((string)setting('tg_chat_id', ''));
    if ($token === '' || $chat === '') return false;
    try {
        $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['chat_id' => $chat, 'text' => $text]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        curl_exec($ch);
        curl_close($ch);
        return true;
    } catch (Exception $ex) {
        return false;
    }
}

/** 管理员统一通知: 邮箱(已配置时) + Telegram(已配置时) */
function notify_admin($title, $body)
{
    $to = trim((string)setting('notify_email', ''));
    if ($to !== '') send_mail($to, '【' . setting('site_name', '坤发卡') . '】' . $title, $body);
    send_telegram($title . "\n\n" . $body);
}

/** 下单黑名单命中检查(每行一条: 邮箱或IP, 精确匹配) */
function order_blacklisted($contact, $ip)
{
    $list = preg_split('/\r\n|\r|\n/', (string)setting('order_blacklist', ''));
    $set = [];
    foreach ($list as $line) {
        $v = strtolower(trim($line));
        if ($v !== '') $set[$v] = true;
    }
    if (!$set) return false;
    return isset($set[strtolower(trim((string)$contact))]) || isset($set[strtolower(trim((string)$ip))]);
}
