<?php
/**
 * 坤发卡 v1.0.0 → v1.1.0 升级脚本(浏览器访问一次即可)
 * 新增: 前台会员体系 / 注册IP限制 / 登录爆破锁定 / Cloudflare Turnstile支持
 * 幂等设计, 重复执行无副作用。执行后建议删除本文件。
 */
if (!is_file(__DIR__ . '/../data/config.php')) exit('请先完成系统安装');
define('YF_ROOT', dirname(__DIR__));
require __DIR__ . '/../data/config.php';

$pdo = new PDO(
    'mysql:host=' . YF_DB_HOST . ';port=' . YF_DB_PORT . ';dbname=' . YF_DB_NAME . ';charset=utf8mb4',
    YF_DB_USER, YF_DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "<h3>坤发卡 v1.1.0 升级</h3><pre>\n";

$ddl = [
    "CREATE TABLE IF NOT EXISTS `users` (
        `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `username` varchar(32) NOT NULL,
        `password` varchar(255) NOT NULL,
        `email` varchar(100) NOT NULL DEFAULT '',
        `status` tinyint NOT NULL DEFAULT 1,
        `created_at` int unsigned NOT NULL DEFAULT 0,
        `reg_ip` varchar(45) NOT NULL DEFAULT '',
        UNIQUE KEY `uk_username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `registers` (
        `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `ip` varchar(45) NOT NULL,
        `created_at` int unsigned NOT NULL DEFAULT 0,
        KEY `idx_ip` (`ip`,`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `login_fails` (
        `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `scene` varchar(10) NOT NULL,
        `account` varchar(50) NOT NULL,
        `ip` varchar(45) NOT NULL DEFAULT '',
        `fail_at` int unsigned NOT NULL DEFAULT 0,
        KEY `idx_key` (`scene`,`account`,`ip`,`fail_at`),
        KEY `idx_scene_ip` (`scene`,`ip`,`fail_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
foreach ($ddl as $sql) {
    $pdo->exec($sql);
    echo "OK: " . substr($sql, 0, 60) . "...\n";
}

// orders 表补 user_id 列与索引(仅v1.0安装需要)
$stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'user_id'");
if ((int)$stmt->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE `orders` ADD COLUMN `user_id` int unsigned NOT NULL DEFAULT 0 COMMENT '下单会员ID' AFTER `sn`");
    $pdo->exec("ALTER TABLE `orders` ADD INDEX `idx_user` (`user_id`)");
    echo "OK: orders 表已添加 user_id 列\n";
} else {
    echo "SKIP: orders.user_id 已存在\n";
}

// orders 表补 contact_type 列(v1.3→v1.4)
$stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'contact_type'");
if ((int)$stmt->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE `orders` ADD COLUMN `contact_type` varchar(20) NOT NULL DEFAULT '' COMMENT '联系方式类型' AFTER `contact`");
    echo "OK: orders 表已添加 contact_type 列\n";
} else {
    echo "SKIP: orders.contact_type 已存在\n";
}

// categories 表补 status 列(v2.9.0 分类启用/停用)
$stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'status'");
if ((int)$stmt->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE `categories` ADD COLUMN `status` tinyint NOT NULL DEFAULT 1 COMMENT '1启用 0停用(前台隐藏)'");
    echo "OK: categories 表已添加 status 列\n";
} else {
    echo "SKIP: categories.status 已存在\n";
}

// cards 表补 note 列(v2.11.0 卡密备注)
$stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cards' AND COLUMN_NAME = 'note'");
if ((int)$stmt->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE `cards` ADD COLUMN `note` varchar(200) NOT NULL DEFAULT '' COMMENT '备注信息'");
    echo "OK: cards 表已添加 note 列\n";
} else {
    echo "SKIP: cards.note 已存在\n";
}

// 会员等级与商品分组(v2.13.0)
$pdo->exec("CREATE TABLE IF NOT EXISTS `member_levels` (
    `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` varchar(50) NOT NULL,
    `level` int NOT NULL DEFAULT 1 COMMENT '等级数值(越大越高)',
    `created_at` int unsigned NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS `product_groups` (
    `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` varchar(100) NOT NULL,
    `min_level` int NOT NULL DEFAULT 0 COMMENT '可见所需最低等级数值(0=不限制)',
    `created_at` int unsigned NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "OK: member_levels / product_groups 表就绪\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'level_id'");
if ((int)$stmt->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `level_id` int unsigned NOT NULL DEFAULT 0 COMMENT '会员等级(0=无等级)'");
    echo "OK: users 表已添加 level_id 列\n";
} else {
    echo "SKIP: users.level_id 已存在\n";
}
$stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'group_id'");
if ((int)$stmt->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE `products` ADD COLUMN `group_id` int unsigned NOT NULL DEFAULT 0 COMMENT '商品分组(0=不分组)'");
    echo "OK: products 表已添加 group_id 列\n";
} else {
    echo "SKIP: products.group_id 已存在\n";
}

// 系统日志表(v1.4→v1.5)
$pdo->exec("CREATE TABLE IF NOT EXISTS `logs` (
    `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `type` varchar(20) NOT NULL DEFAULT 'system',
    `message` text NULL,
    `ip` varchar(45) NOT NULL DEFAULT '',
    `created_at` int unsigned NOT NULL DEFAULT 0,
    KEY `idx_type` (`type`,`created_at`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "OK: logs 表就绪\n";

// 新增系统配置项
$verifyMode = 'captcha';
foreach ($pdo->query("SELECT k, v FROM settings") as $row) {
    if ($row['k'] === 'turnstile_open' && $row['v'] === '1') $verifyMode = 'turnstile';
}
$settings = [
    'captcha_open' => '1',
    'turnstile_open' => '0',
    'turnstile_site_key' => '',
    'turnstile_secret_key' => '',
    'verify_mode' => $verifyMode,
    'geetest_id' => '',
    'geetest_key' => '',
    'geetest_timeout' => '120',
    'contact_types' => 'email,qq',
    'service_contacts' => '',
];
$st = $pdo->prepare('REPLACE INTO settings (k, v) VALUES (?, ?)');
foreach ($settings as $k => $v) {
    $st->execute([$k, $v]);
    echo "OK: settings[{$k}]\n";
}
// member_open 仅首次插入(不覆盖站长已关闭的状态)
$pdo->exec("INSERT IGNORE INTO settings (k, v) VALUES ('member_open', '1')");
echo "OK: settings[member_open]\n";

// 默认主题切换到云商城(v1.9): 仅当当前是旧默认 anime 时切换, 自选主题不动
$cur = '';
foreach ($pdo->query("SELECT v FROM settings WHERE k = 'theme'") as $row) $cur = $row['v'];
if ($cur === '' || $cur === 'anime') {
    $st->execute(['theme', 'store']);
    echo "OK: 默认主题已切换为 云商城(store)\n";
} else {
    echo "SKIP: 主题保持为 {$cur}\n";
}

// 公告表 + 单页设置(v1.10)
$pdo->exec("CREATE TABLE IF NOT EXISTS `notices` (
    `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `title` varchar(200) NOT NULL,
    `content` text NULL COMMENT '公告正文(纯文本, 自动转义)',
    `status` tinyint NOT NULL DEFAULT 1 COMMENT '1显示 0隐藏',
    `sort` int NOT NULL DEFAULT 0 COMMENT '越大越靠前',
    `created_at` int unsigned NOT NULL DEFAULT 0,
    KEY `idx_status` (`status`,`sort`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "OK: notices 表就绪\n";
foreach (['singlepage_open' => '0', 'singlepage_title' => '关于我们', 'singlepage_content' => ''] as $k => $v) {
    $st->execute([$k, $v]);
    echo "OK: settings[{$k}]\n";
}
// 旧版单条公告设置导入为公告记录(仅公告表为空时执行一次)
$noticeCount = (int)$pdo->query('SELECT COUNT(*) FROM notices')->fetchColumn();
if ($noticeCount === 0) {
    foreach ($pdo->query("SELECT v FROM settings WHERE k = 'announcement'") as $row) {
        $legacy = trim((string)$row['v']);
        if ($legacy !== '') {
            $pdo->prepare('INSERT INTO notices (title, content, status, sort, created_at) VALUES (?, ?, 1, 0, ?)')
                ->execute(['本站公告', $legacy, time()]);
            echo "OK: 已将旧版公告设置导入公告管理\n";
        }
    }
}

// 商店自助购买(v1.12): store_orders 表 + 收款配置(INSERT IGNORE, 不覆盖已配置密钥)
$pdo->exec("CREATE TABLE IF NOT EXISTS `store_orders` (
    `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `sn` varchar(32) NOT NULL,
    `channel` varchar(10) NOT NULL DEFAULT 'usdt' COMMENT 'usdt / codepay',
    `pay_type` varchar(10) NOT NULL DEFAULT '' COMMENT 'codepay子渠道 alipay/wxpay/qqpay',
    `amount` decimal(14,6) NOT NULL DEFAULT 0.000000 COMMENT '应收金额(CNY或USDT)',
    `status` tinyint NOT NULL DEFAULT 0 COMMENT '0待支付 1已支付 2已取消',
    `txid` varchar(100) NOT NULL DEFAULT '' COMMENT '链上哈希或三方流水',
    `created_at` int unsigned NOT NULL DEFAULT 0,
    `paid_at` int unsigned NOT NULL DEFAULT 0,
    UNIQUE KEY `uk_sn` (`sn`),
    KEY `idx_status` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "OK: store_orders 表就绪\n";
foreach (['storepay_usdt' => '', 'storepay_codepay_api' => '', 'storepay_codepay_pid' => '',
          'storepay_codepay_key' => '', 'storepay_price' => '99', 'storepay_usdt_amount' => '15',
          'cdn_mode' => 'off'] as $k => $v) {
    $chk = $pdo->prepare('SELECT COUNT(*) FROM settings WHERE k = ?');
    $chk->execute([$k]);
    if ((int)$chk->fetchColumn() === 0) {
        $st->execute([$k, $v]);
        echo "OK: settings[{$k}]\n";
    } else {
        echo "SKIP: settings[{$k}] 已存在\n";
    }
}

echo "\n升级完成! 建议: 后台-系统设置 中配置人机验证方式; 执行完成后删除本文件。\n";
