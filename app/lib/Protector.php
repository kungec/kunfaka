<?php
/**
 * 坤发卡 付费应用保护器
 * - 付费插件/主题核心文件经"站点授权指纹"加密分发(主控下载时按 domain+授权码派生密钥加密)
 * - 密钥 = sha256('KFENC1|' + slug + '|' + 绑定域名 + '|' + 授权码), 文件复制到其他站点无法解密
 * - 解密结果缓存到 data/cache_protected/(带执行守卫), 命中OPcache后运行时零开销
 * - 运行时门控: 付费应用加载前校验 专业版授权 + 域名绑定, 失败回退/拒绝
 * 主控端加密函数见 license-server/Protector.php(算法一致)
 */
if (!defined('YF_ROOT')) define('YF_ROOT', dirname(__DIR__, 2)); // 独立require(如e2e进程)自持
class Protector
{
    /** 官方付费应用清单(运行时信任锚之一, json的pro字段被篡改不影响本清单判定) */
    private static $proManifest = [
        'payment' => ['usdt_trc20', 'usdt_bep20', 'usdt_polygon', 'usdt_erc20', 'btc', 'eth', 'xmr', 'visa_master', 'epusdt'],
        'theme' => ['azure', 'coral', 'starry'],
    ];

    /** 是否官方付费应用(不信任json的pro字段) */
    public static function isProApp($type, $name)
    {
        $type = $type === 'theme' ? 'theme' : 'payment';
        return isset(self::$proManifest[$type]) && in_array($name, self::$proManifest[$type], true);
    }

    /** 授权绑定域名(激活时写入的归一化域名, 再去www一次兜底→根域名) */
    public static function bindDomain()
    {
        return preg_replace('/^www\./i', '', strtolower(trim((string)setting('license_domain', ''))));
    }

    /** 当前访问域名归一化(去端口; www与非www视为等价) */
    public static function currentDomain()
    {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host);
        return preg_replace('/^www\./', '', $host);
    }

    /** 应用主文件是否为官方加密分发态(密文强制校验; 明文=作者/开发模式豁免) */
    public static function isEncrypted($type, $name)
    {
        $type = $type === 'theme' ? 'theme' : 'payment';
        $base = YF_ROOT . '/' . ($type === 'theme' ? 'themes' : 'plugins') . '/' . $name;
        if (!is_dir($base)) return false;
        $candidates = $type === 'theme'
            ? [$base . '/views/layout.php', $base . '/layout.php']
            : [];
        if ($type === 'theme' && is_file($candidates[0])) {
            $head = (string)file_get_contents($candidates[0], false, null, 0, 64);
            return strpos($head, 'KUNFAKA-ENC v1') !== false;
        }
        // 扫描目录内首个php文件判断(自持, 不依赖Plugin类)
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (strtolower($f->getExtension()) !== 'php') continue;
            $head = (string)file_get_contents($f->getPathname(), false, null, 0, 64);
            return strpos($head, 'KUNFAKA-ENC v1') !== false;
        }
        return false;
    }

    /**
     * 付费应用运行时门控: 加密分发态必须 专业版有效 + 域名绑定一致
     * 缓存用$GLOBALS(请求级): fpm worker常驻, static会把部署/授权变更期的旧结果跨请求长期持有
     */
    public static function gate($type, $name)
    {
        $key = $type . ':' . $name;
        $cache = isset($GLOBALS['YF_GATE_CACHE']) && is_array($GLOBALS['YF_GATE_CACHE']) ? $GLOBALS['YF_GATE_CACHE'] : [];
        if (array_key_exists($key, $cache)) return $cache[$key];
        if (!self::isProApp($type, $name)) return $GLOBALS['YF_GATE_CACHE'][$key] = true; // 非付费应用放行
        if (!self::isEncrypted($type, $name)) return $GLOBALS['YF_GATE_CACHE'][$key] = true; // 明文源码=开发模式豁免
        if (!License::isPro()) return $GLOBALS['YF_GATE_CACHE'][$key] = false;
        // 两侧统一去www后比较(www与非www视为同一站点)
        $bind = preg_replace('/^www\./i', '', self::bindDomain());
        $cur = self::currentDomain();
        // 未记录绑定域名(老授权)时放行; 已记录则必须与当前域名一致
        return $GLOBALS['YF_GATE_CACHE'][$key] = ($bind === '' || $bind === $cur);
    }

    /** 解密密钥派生(与主控端Protector一致) */
    public static function key($slug, $domain, $licenseKey)
    {
        return hash('sha256', 'KFENC1|' . $slug . '|' . strtolower(trim((string)$domain)) . '|' . strtoupper(trim((string)$licenseKey)), true);
    }

    /** 解密密文段(主控端加密格式: KENC1|iv_hex|hmac_hex|base64_cipher) */
    public static function decrypt($payload, $key)
    {
        $parts = explode('|', $payload);
        if (count($parts) !== 4 || $parts[0] !== 'KENC1') return null;
        $iv = $parts[1];
        $hmac = $parts[2];
        $cipher = base64_decode($parts[3], true);
        if ($cipher === false || !hash_equals(hash_hmac('sha256', $iv . $cipher, $key), $hmac)) return null;
        // md5 keystream 流解密
        $out = '';
        $len = strlen($cipher);
        $n = 0;
        while (strlen($out) < $len) {
            $ks = md5($key . $iv . $n++, true);
            $out .= substr($cipher, strlen($out), 16) ^ substr($ks, 0, min(16, $len - strlen($out)));
        }
        return $out;
    }

    /**
     * 加密文件解密并准备缓存(供stub在自身作用域include缓存文件——
     * 若在loader函数内include, 视图/类的控制器变量作用域会丢失)
     * 返回缓存文件路径; 解密失败返回 false
     */
    public static function cacheFile($file, $slug)
    {
        if (!defined('KF_RUNTIME')) define('KF_RUNTIME', 1); // 守卫常量: 本请求已获准执行受保护缓存
        $domain = self::bindDomain();
        $lk = strtoupper(trim((string)setting('license_key', '')));
        $key = self::key($slug, $domain, $lk);
        $cacheDir = YF_ROOT . '/data/cache_protected';
        $cacheFile = $cacheDir . '/' . md5($slug . '|' . $domain . '|' . $lk) . '.php';
        if (is_file($cacheFile)) return $cacheFile;
        $raw = (string)file_get_contents($file);
        $marker = '__HALT_COMPILER(); ?>';
        $pos = strpos($raw, $marker);
        if ($pos === false) return false;
        $code = self::decrypt(substr($raw, $pos + strlen($marker)), $key);
        if ($code === null) return false;
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
        @file_put_contents($cacheFile, "<?php if (!defined('KF_RUNTIME')) exit('denied'); ?>" . $code);
        return $cacheFile;
    }

    /**
     * 加密文件loader: 解密并include(类定义型文件适用)
     * 视图模板请用 kfProtectedCacheFile + stub内include 以保留变量作用域
     */
    public static function load($file, $slug)
    {
        if (!defined('KF_RUNTIME')) define('KF_RUNTIME', 1);
        $cacheFile = self::cacheFile($file, $slug);
        if ($cacheFile === false) return false;
        include $cacheFile;
        return true;
    }
}

/** 加密文件loader入口(stub调用; 加密loader与解密器解耦) */
if (!function_exists('kfProtectedLoad')) {
    function kfProtectedLoad($file, $slug)
    {
        return Protector::load($file, $slug);
    }
}
if (!function_exists('kfProtectedCacheFile')) {
    function kfProtectedCacheFile($file, $slug)
    {
        return Protector::cacheFile($file, $slug);
    }
}
