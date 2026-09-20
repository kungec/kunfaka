<?php
/**
 * 坤发卡 升级工具(仅限命令行执行: php tools/upgrade.php)
 * 幂等设计, 重复执行无副作用。自动更新器(lib/Updater)复用同一升级逻辑。
 */
if (PHP_SAPI !== 'cli') exit("为防止未授权访问, 本脚本仅支持命令行执行: php tools/upgrade.php\n");
if (!is_file(__DIR__ . '/../data/config.php')) exit("请先完成系统安装\n");
define('YF_ROOT', dirname(__DIR__));
require __DIR__ . '/../data/config.php';
require __DIR__ . '/../app/lib/Upgrade.php';

$pdo = new PDO(
    'mysql:host=' . YF_DB_HOST . ';port=' . YF_DB_PORT . ';dbname=' . YF_DB_NAME . ';charset=utf8mb4',
    YF_DB_USER, YF_DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
echo "<h3>坤发卡 升级</h3><pre>\n";
Upgrade::run($pdo);
echo "\n升级完成!\n";
