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

/** 客户端IP(按接入模式识别真实IP; off=直连不信任任何头) */
function client_ip() {
    $mode = setting('cdn_mode', 'off');
    if ($mode === 'cloudflare') {
        // Cloudflare模式: 信任 CF-Connecting-IP
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = trim((string)$_SERVER['HTTP_CF_CONNECTING_IP']);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    } elseif ($mode === 'cdn') {
        // 通用CDN/反代模式: 取 X-Forwarded-For 最左侧合法IP(最初客户端)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            foreach (explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']) as $ip) {
                $ip = trim($ip);
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        // 部分CDN用 X-Real-IP
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = trim((string)$_SERVER['HTTP_X_REAL_IP']);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
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
            $scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $dir = rtrim(str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/')), '/');
            $base = $scheme . '://' . $host . $dir . '/';
        }
        if (substr($base, -1) !== '/') $base .= '/';
    }
    return $base . ltrim($path, '/');
}

/** 前台URL */
function u($route, $params = []) {
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
    $t = trim((string)setting('theme', 'store'));
    if (!is_dir(YF_ROOT . '/themes/' . $t)) $t = 'store';
    return $t;
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

/** 联系方式类型定义 */
function contact_type_all() {
    return ['telegram' => 'Telegram', 'email' => '邮箱', 'qq' => 'QQ', 'wechat' => '微信', 'phone' => '电话'];
}

/** 后台启用的联系方式类型(数组) */
function contact_types_enabled() {
    $raw = trim((string)setting('contact_types', 'email,qq'));
    if ($raw === '') $raw = 'email,qq';
    $all = contact_type_all();
    $out = [];
    foreach (explode(',', $raw) as $t) {
        $t = trim($t);
        if ($t !== '' && isset($all[$t])) $out[] = $t;
    }
    if (!$out) $out = ['email', 'qq'];
    return $out;
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
    ];
    return isset($map[$type]) ? $map[$type] : '请填写';
}

/** 剩余库存 */
function product_stock($productId) {
    return (int)DB::value('SELECT COUNT(*) FROM cards WHERE product_id = ? AND status = 0', [$productId]);
}

/** 前台支付方式列表(含配置, 用于品牌图标匹配) */
function enabled_payments() {
    $rows = DB::fetchAll("SELECT a.name, a.config FROM apps a WHERE a.type = 'payment' AND a.enabled = 1");
    $labels = ['alipay' => '支付宝', 'wxpay' => '微信', 'qqpay' => 'QQ钱包'];
    $list = [];
    foreach ($rows as $r) {
        $meta = Plugin::meta('payment', $r['name']);
        if (!$meta) continue;
        $cfg = $r['config'] ? (array)json_decode($r['config'], true) : [];
        $plugin = Plugin::payment($r['name']);
        $channels = $plugin ? $plugin->channels() : [];
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
