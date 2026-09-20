<?php
/**
 * 坤发卡 - 引导文件
 */
if (!defined('YF_ROOT')) define('YF_ROOT', dirname(__DIR__));
define('YF_DATA', YF_ROOT . '/data');
define('YF_VERSION', '2.30.4');
define('YF_START', microtime(true));

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

require YF_DATA . '/config.php';
require __DIR__ . '/functions.php';
require __DIR__ . '/DB.php';
require __DIR__ . '/View.php';

DB::init();

if (session_status() === PHP_SESSION_NONE) {
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
