<?php
/**
 * 坤发卡 - 引导文件
 */
// 防重复加载: 被多个入口require时(如install.php)直接返回, 避免常量重定义/函数重复声明
if (defined('YF_BOOTSTRAP_LOADED')) return;
define('YF_BOOTSTRAP_LOADED', 1);

if (!defined('YF_ROOT')) define('YF_ROOT', dirname(__DIR__));
if (!defined('YF_DATA')) define('YF_DATA', YF_ROOT . '/data');
if (!defined('YF_VERSION')) define('YF_VERSION', '2.43.8');
if (!defined('YF_START')) define('YF_START', microtime(true));

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Shanghai');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

// 安全响应头
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
    // CSP纵深: 即使未来某处漏了输出转义, 也把脚本来源限制到本站与人机验证域名(inline保留以兼容现有内联脚本)
    // style-src须含geetest(极验皮肤CSS), frame-src须含geetest(极验popup验证弹窗iframe)
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://static.geetest.com https://*.geetest.com https://challenges.cloudflare.com; style-src 'self' 'unsafe-inline' https://static.geetest.com https://*.geetest.com; img-src * data:; connect-src 'self' https://api.geetest.com https://*.geetest.com https://challenges.cloudflare.com; frame-src https://challenges.cloudflare.com https://*.geetest.com; base-uri 'self'");
    // 动态页面禁缓存: 防止套CDN时收银台/二维码/支付结果页被边缘缓存
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('CDN-Cache-Control: no-store');
}

// mbstring缺失时的兜底实现
if (!function_exists('mb_substr')) {
    function mb_substr($s, $start, $len = null) {
        preg_match_all('/./us', (string)$s, $m);
        return implode('', array_slice($m[0], $start, $len));
    }
    function mb_strlen($s) {
        preg_match_all('/./us', (string)$s, $m);
        return count($m[0]);
    }
}

if (!is_file(YF_DATA . '/config.php')) {
    if (defined('YF_INSTALLER')) return; // 安装向导自行处理
    header('Location: install.php');
    exit;
}

require_once YF_DATA . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/View.php';

// 安装向导模式下DB连不上不打断流程(向导自己会用try/catch检测并引导重装)
if (defined('YF_INSTALLER')) {
    try { DB::init(); } catch (Throwable $t) {}
} else {
    DB::init();
}

if (session_status() === PHP_SESSION_NONE) {
    // 会话加固: strict mode拒收未初始化的session id(防会话固定注入); 自定义会话名(不暴露PHP默认指纹)
    @ini_set('session.use_strict_mode', '1');
    session_name('KFKSID');
    // HTTPS环境下(直连或经代理)给会话Cookie加secure, 防降级明文泄露
    $https = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off';
    if (!$https && !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $https = strtolower(trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_PROTO'])[0])) === 'https';
    }
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $https]);
    session_start();
}

// 类自动加载: app/lib 与 app/controllers
spl_autoload_register(function ($class) {
    foreach (['lib', 'controllers'] as $dir) {
        $f = __DIR__ . '/' . $dir . '/' . $class . '.php';
        if (is_file($f)) {
            require $f;
            return;
        }
    }
});

// bcmath 兜底(USDT金额比较)
if (!function_exists('bcadd')) {
    function bcadd($a, $b, $s = 0) { return round((float)$a + (float)$b, $s); }
    function bcsub($a, $b, $s = 0) { return round((float)$a - (float)$b, $s); }
    function bccomp($a, $b, $s = 0) { $a = round((float)$a, $s); $b = round((float)$b, $s); return $a > $b ? 1 : ($a < $b ? -1 : 0); }
    function bcdiv($a, $b, $s = 0) { return round((float)$a / (float)$b, $s); }
}
