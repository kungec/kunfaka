<?php
/**
 * 坤发卡 一键打包脚本(命令行)
 * 用法: php tools/make_package.php [版本号]
 * 自动完成:
 *   1. 清理测试产物(data/config.php, data/install.lock)
 *   2. 校验交付文件完整性
 *   3. 全量PHP语法检查(可选, 找到php可用时)
 *   4. 生成根级结构的zip压缩包(解压到站点根目录即用)
 *   5. 产物自检: zip内不得包含测试配置, data目录只允许.htaccess/readme.txt
 */
if (PHP_SAPI !== 'cli') exit('仅限命令行运行');

$root = dirname(__DIR__);
$version = isset($argv[1]) ? preg_replace('/[^0-9A-Za-z\.\-]/', '', $argv[1]) : date('Ymd');
$outZip = dirname($root) . '/坤发卡-v' . $version . '.zip';

echo "==== 坤发卡打包 v{$version} ====\n";

// 1. 清理测试产物
$mustRemove = [$root . '/data/config.php', $root . '/data/install.lock'];
foreach ($mustRemove as $f) {
    if (is_file($f)) {
        unlink($f);
        echo "[清理] 已删除测试产物: " . basename(dirname($f)) . '/' . basename($f) . "\n";
    }
}

// 2. 交付文件完整性
$required = [
    'index.php', 'admin.php', 'cron.php', 'install.php', 'database.sql', 'README.md', '.htaccess',
    'app/bootstrap.php', 'app/functions.php', 'app/DB.php', 'app/View.php',
    'assets/css/admin.css', 'assets/js/app.js', 'assets/js/admin.js', 'assets/js/qrcode.min.js',
    'data/.htaccess', 'data/readme.txt', 'tools/upgrade.php', 'tools/make_license.php',
    'license-server/index.php', 'license-server/admin.php', 'license-server/common.php',
    'license-server/.htaccess', 'license-server/packages/.htaccess',
    'plugins/codepay/codepay.json', 'plugins/epay/epay.json', 'plugins/alipay_f2f/alipay_f2f.json',
    'plugins/alipay_page/alipay_page.json', 'plugins/wechat_native/wechat_native.json',
    'plugins/usdt_trc20/usdt_trc20.json',
    'plugins/codepay/shot.svg', 'plugins/epay/shot.svg', 'plugins/alipay_f2f/shot.svg',
    'plugins/alipay_page/shot.svg', 'plugins/wechat_native/shot.svg', 'plugins/usdt_trc20/shot.svg',
    'plugins/visa_master/visa_master.json', 'plugins/visa_master/VisaMasterPlugin.php', 'plugins/visa_master/shot.svg',
    'themes/anime/anime.json',
    'themes/anime/shot.svg',
    'themes/store/store.json', 'themes/store/assets/css/style.css',
    'themes/store/shot.svg',
    'themes/store/views/layout.php', 'themes/store/views/home.php', 'themes/store/views/product.php',
    'themes/store/views/choose.php', 'themes/store/views/pay.php', 'themes/store/views/result.php',
    'themes/store/views/query.php', 'themes/store/views/login.php', 'themes/store/views/myorders.php',
    'themes/store/views/error.php',
];
$missing = [];
foreach ($required as $rel) {
    if (!is_file($root . '/' . $rel)) $missing[] = $rel;
}
if ($missing) {
    echo "[失败] 缺少交付文件:\n" . implode("\n", array_map(function ($m) { return '  - ' . $m; }, $missing)) . "\n";
    exit(1);
}
echo "[OK] 交付文件完整性 (" . count($required) . "项关键文件)\n";

// 3. 全量语法检查(若可用)
$php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
$fail = 0;
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$allPhp = [];
foreach ($files as $f) {
    if ($f->isFile() && substr($f->getFilename(), -4) === '.php') $allPhp[] = $f->getPathname();
}
foreach ($allPhp as $f) {
    exec(escapeshellarg($php) . ' -l ' . escapeshellarg($f) . ' 2>&1', $out, $code);
    if ($code !== 0) { $fail++; echo "[语法错误] $f\n" . implode("\n", $out) . "\n"; }
    $out = [];
}
if ($fail > 0) {
    echo "[失败] $fail 个文件存在语法错误, 已中止打包\n";
    exit(1);
}
echo "[OK] PHP语法检查 " . count($allPhp) . " 个文件\n";

// 4. 生成zip(根级结构)
if (is_file($outZip)) unlink($outZip);
if (!class_exists('ZipArchive')) {
    echo "[失败] 缺少zip扩展\n";
    exit(1);
}
$zip = new ZipArchive();
$zip->open($outZip, ZipArchive::CREATE);
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$count = 0;
foreach ($iter as $f) {
    if (!$f->isFile()) continue;
    $rel = substr($f->getPathname(), strlen($root) + 1);
    $rel = str_replace('\\', '/', $rel);
    $zip->addFile($f->getPathname(), $rel);
    $count++;
}
$zip->close();
echo "[OK] 已生成: $outZip ($count 个文件, " . round(filesize($outZip) / 1024) . " KB)\n";

// 5. 产物自检
$check = new ZipArchive();
$check->open($outZip);
$dirty = [];
$allowedInData = ['data/.htaccess', 'data/readme.txt'];
for ($i = 0; $i < $check->numFiles; $i++) {
    $n = $check->getNameIndex($i);
    $std = str_replace('\\', '/', $n);
    if (strpos($std, 'data/') === 0 && !in_array($std, $allowedInData, true)) $dirty[] = $n;
    if ($std === 'config.php' || $std === 'install.lock') $dirty[] = $n;
}
$check->close();
if ($dirty) {
    echo "[失败] 产物包含非法文件:\n" . implode("\n", $dirty) . "\n";
    exit(1);
}
echo "[OK] 产物自检: data目录干净, 无测试配置混入\n";
echo "==== 打包完成 ====\n";
