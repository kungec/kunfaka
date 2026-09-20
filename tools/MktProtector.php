<?php
/**
 * 主控端付费应用加密器(与发卡端 app/lib/Protector.php 算法一致)
 * 付费应用下载时按 请求站点domain+授权码 派生密钥动态加密打包:
 * 文件复制到其他站点/泄露的包均无法解密使用。
 */
class MktProtector
{
    /** 解密密钥派生 */
    public static function key($slug, $domain, $licenseKey)
    {
        return hash('sha256', 'KFENC1|' . $slug . '|' . strtolower(trim((string)$domain)) . '|' . strtoupper(trim((string)$licenseKey)), true);
    }

    /** 加密: KENC1|iv_hex|hmac_hex|base64_cipher */
    public static function encrypt($code, $key)
    {
        $iv = bin2hex(random_bytes(8));
        $cipher = '';
        $len = strlen($code);
        $n = 0;
        $off = 0;
        while ($off < $len) {
            $ks = md5($key . $iv . $n++, true);
            $chunk = substr($code, $off, 16);
            $cipher .= $chunk ^ substr($ks, 0, strlen($chunk));
            $off += strlen($chunk);
        }
        $hmac = hash_hmac('sha256', $iv . $cipher, $key);
        return 'KENC1|' . $iv . '|' . $hmac . '|' . base64_encode($cipher);
    }

    /** 生成加密PHP文件内容: loader stub + __halt_compiler 后密文 */
    public static function encryptPhpContent($code, $relPath, $domain, $licenseKey)
    {
        $slug = preg_replace('#\.(php|phtml)$#', '', str_replace('\\', '/', $relPath));
        $dirPart = dirname('/' . $slug); // '/plugins/usdt_trc20' 等
        $depth = substr_count($dirPart, '/'); // 站点根 -> 文件目录的层级
        $rootExpr = "dirname(__DIR__, {$depth})";
        if ($depth <= 0) $rootExpr = '__DIR__';
        $stub = "<?php /* KUNFAKA-ENC v1 */\n"
            . "if (!function_exists('kfProtectedLoad')) require_once {$rootExpr} . '/app/lib/Protector.php';\n"
            . "return kfProtectedLoad(__FILE__, " . var_export($slug, true) . ");\n"
            . "__HALT_COMPILER(); ?>";
        return $stub . self::encrypt($code, self::key($slug, $domain, $licenseKey));
    }

    /**
     * 将应用zip内的 .php 文件按站点加密后输出新zip(其余文件原样)
     * $srcZip 原始包, $outZip 加密包路径
     */
    public static function encryptZip($srcZip, $outZip, $domain, $licenseKey)
    {
        $src = new ZipArchive();
        if ($src->open($srcZip) !== true) return false;
        $tmpDir = sys_get_temp_dir() . '/kfenc_' . bin2hex(random_bytes(6));
        @mkdir($tmpDir, 0755, true);
        $src->extractTo($tmpDir);
        $src->close();
        // 剥顶层目录(与发卡端解包逻辑一致)
        $entries = scandir($tmpDir);
        $entries = array_diff($entries, ['.', '..']);
        if (count($entries) === 1 && is_dir($tmpDir . '/' . reset($entries))) {
            $inner = $tmpDir . '/' . reset($entries);
            $moved = $tmpDir . '_flat';
            @rename($inner, $moved);
            $tmpDir = $moved;
        }
        $out = new ZipArchive();
        if ($out->open($outZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return false;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $rel = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($tmpDir))), '/');
            if (strtolower($f->getExtension()) === 'php') {
                $code = (string)file_get_contents($f->getPathname());
                $out->addFromString($rel, self::encryptPhpContent($code, $rel, $domain, $licenseKey));
            } else {
                $out->addFile($f->getPathname(), $rel);
            }
        }
        $out->close();
        return true;
    }
}
