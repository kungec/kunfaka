<?php
/**
 * 坤发卡 99专业版离线授权码生成工具(命令行)
 * 用法: YF_OFFLINE_SECRET='你的密钥' php make_license.php [生成数量]
 * 密钥须与发卡端 data/config.php 中定义的 YF_OFFLINE_SECRET 一致; 不设置则无法生成。
 * 生成的授权码支持离线激活, 交付给购买了99元专业版的用户。
 */
if (PHP_SAPI !== 'cli') exit('仅限命令行运行');

require_once __DIR__ . '/../app/lib/License.php'; // 仅用离线密钥读取器, 不依赖数据库

$secret = License::offlineSecret();
if ($secret === '') exit("请先设置环境变量 YF_OFFLINE_SECRET (与发卡端 data/config.php 中定义一致)\n");

$n = isset($argv[1]) ? max(1, min(500, (int)$argv[1])) : 10;
$charset = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
echo "生成 {$n} 个专业版离线授权码:\n";
for ($i = 0; $i < $n; $i++) {
    $body = '';
    for ($j = 0; $j < 10; $j++) $body .= $charset[random_int(0, strlen($charset) - 1)];
    $sum = strtoupper(substr(hash_hmac('md5', $body, $secret), 0, 5));
    echo 'YF99-' . substr($body, 0, 5) . '-' . substr($body, 5, 5) . '-' . $sum . "\n";
}
