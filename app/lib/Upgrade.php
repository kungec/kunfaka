<?php
/**
 * 坤发卡 数据库结构与默认配置升级(幂等, 可重复执行)
 * MySQL(生产) / SQLite(自动化测试垫片) 双驱动兼容。
 * 由 tools/upgrade.php(CLI) 与 自动更新器(lib/Updater) 共同调用
 */
class Upgrade
{
    public static function run(PDO $pdo)
    {
        $log = function ($m) { echo "[upgrade] $m\n"; };
        $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

        if (!$isSqlite) {
            self::mysqlSchema($pdo, $log);
        }

        // ---- 配置种子(存在即跳过/MySQL用IGNORE, 绝不覆盖已有配置) ----
        $verifyMode = 'captcha';
        foreach ($pdo->query("SELECT k, v FROM settings") as $row) {
            if ($row['k'] === 'turnstile_open' && $row['v'] === '1') $verifyMode = 'turnstile';
        }
        $seeds = [
            'captcha_open' => '1',
            'order_ip_limit' => '30',
            'wx_anti_red' => '0',
            'qq_anti_red' => '0',
            'turnstile_open' => '0',
            'turnstile_site_key' => '',
            'turnstile_secret_key' => '',
            'verify_mode' => $verifyMode,
            'geetest_id' => '',
            'geetest_key' => '',
            'geetest_timeout' => '120',
            'contact_types' => 'email,qq',
            'service_contacts' => '',
            'member_open' => '1',
            'singlepage_open' => '0',
            'singlepage_title' => '关于我们',
            'singlepage_content' => '',
            'cdn_mode' => 'auto',
            'auto_update' => '0',
        ];
        $st = $pdo->prepare($isSqlite
            ? 'INSERT OR IGNORE INTO settings (k, v) VALUES (?, ?)'
            : 'INSERT IGNORE INTO settings (k, v) VALUES (?, ?)');
        foreach ($seeds as $k => $v) $st->execute([$k, $v]);
        $log('OK: 配置种子已就绪(不覆盖已有配置)');

        // 默认主题切换(v1.9): 仅当当前是旧默认 anime 时切换
        $cur = '';
        foreach ($pdo->query("SELECT v FROM settings WHERE k = 'theme'") as $row) $cur = $row['v'];
        if ($cur === '' || $cur === 'anime') {
            if ($isSqlite) {
                $pdo->exec("UPDATE settings SET v = 'store' WHERE k = 'theme'");
                if ((int)$pdo->exec("UPDATE settings SET v = 'store' WHERE k = 'theme' AND 0") === 0 && (int)$pdo->query("SELECT COUNT(*) FROM settings WHERE k = 'theme'")->fetchColumn() === 0) {
                    $pdo->exec("INSERT INTO settings (k, v) VALUES ('theme', 'store')");
                }
            } else {
                $pdo->exec("INSERT INTO settings (k, v) VALUES ('theme', 'store') ON DUPLICATE KEY UPDATE v = 'store'");
            }
            $log('OK: 默认主题已切换为 云商城(store)');
        }

        // 旧版单条公告设置导入为公告记录(仅公告表为空时执行一次)
        if (!$isSqlite) {
            $noticeCount = (int)$pdo->query('SELECT COUNT(*) FROM notices')->fetchColumn();
            if ($noticeCount === 0) {
                foreach ($pdo->query("SELECT v FROM settings WHERE k = 'announcement'") as $row) {
                    $legacy = trim((string)$row['v']);
                    if ($legacy !== '') {
                        $pdo->prepare('INSERT INTO notices (title, content, status, sort, created_at) VALUES (?, ?, 1, 0, ?)')
                            ->execute(['本站公告', $legacy, time()]);
                        $log('OK: 已将旧版公告设置导入公告管理');
                    }
                }
            }
        }
    }

    /** MySQL 结构与列迁移(幂等) */
    private static function mysqlSchema(PDO $pdo, $log)
    {
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
            "CREATE TABLE IF NOT EXISTS `logs` (
                `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `type` varchar(20) NOT NULL DEFAULT 'system',
                `message` text NULL,
                `ip` varchar(45) NOT NULL DEFAULT '',
                `ua` varchar(255) NOT NULL DEFAULT '' COMMENT '浏览器UA',
                `created_at` int unsigned NOT NULL DEFAULT 0,
                KEY `idx_type` (`type`,`created_at`),
                KEY `idx_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `notices` (
                `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `title` varchar(200) NOT NULL,
                `content` text NULL COMMENT '公告正文(纯文本, 自动转义)',
                `status` tinyint NOT NULL DEFAULT 1 COMMENT '1显示 0隐藏',
                `sort` int NOT NULL DEFAULT 0 COMMENT '越大越靠前',
                `created_at` int unsigned NOT NULL DEFAULT 0,
                KEY `idx_status` (`status`,`sort`,`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `member_levels` (
                `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` varchar(50) NOT NULL,
                `level` int NOT NULL DEFAULT 1 COMMENT '等级数值(越大越高)',
                `created_at` int unsigned NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `product_groups` (
                `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` varchar(100) NOT NULL,
                `min_level` int NOT NULL DEFAULT 0 COMMENT '可见所需最低等级数值(0=不限制)',
                `created_at` int unsigned NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `contact_otps` (
                `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `contact` varchar(100) NOT NULL DEFAULT '',
                `code` varchar(6) NOT NULL DEFAULT '',
                `ip` varchar(45) NOT NULL DEFAULT '',
                `tries` tinyint NOT NULL DEFAULT 0,
                `created_at` int unsigned NOT NULL DEFAULT 0,
                `expires_at` int unsigned NOT NULL DEFAULT 0,
                KEY `idx_contact` (`contact`,`expires_at`),
                KEY `idx_ip` (`ip`,`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
        foreach ($ddl as $sql) {
            $pdo->exec($sql);
            $log('OK: ' . substr($sql, 0, 46) . '...');
        }

        // ---- 列补齐(幂等) ----
        $col = function ($table, $column, $def, $after = null) use ($pdo, $log) {
            $st = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = '{$column}'");
            if ((int)$st->fetchColumn() === 0) {
                $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$def}" . ($after ? " AFTER `{$after}`" : '');
                $pdo->exec($sql);
                $log("OK: {$table}.{$column} 已添加");
            }
        };
        $col('orders', 'user_id', "int unsigned NOT NULL DEFAULT 0 COMMENT '下单会员ID'", 'sn');
        try { $pdo->exec("ALTER TABLE `orders` ADD INDEX `idx_user` (`user_id`)"); } catch (Exception $ex) {}
        $col('orders', 'contact_type', "varchar(20) NOT NULL DEFAULT '' COMMENT '联系方式类型'", 'contact');
        $col('orders', 'channel', "varchar(20) NOT NULL DEFAULT '' COMMENT '聚合支付渠道: alipay/wxpay/qqpay'", 'pay_plugin');
        $col('orders', 'ship_name', "varchar(100) NOT NULL DEFAULT '' COMMENT '收件人姓名'", 'channel');
        $col('orders', 'ship_address', "varchar(300) NOT NULL DEFAULT '' COMMENT '邮寄地址'", 'ship_name');
        $col('products', 'contact_types', "varchar(200) NOT NULL DEFAULT '' COMMENT '商品级下单联系方式: 逗号分隔(空=默认email)'");
        $col('orders', 'channel', "varchar(20) NOT NULL DEFAULT '' COMMENT '聚合支付渠道: alipay/wxpay/qqpay'", 'pay_plugin');
        $col('categories', 'status', "tinyint NOT NULL DEFAULT 1 COMMENT '1启用 0停用(前台隐藏)'");
        $col('categories', 'image', "varchar(200) NOT NULL DEFAULT '' COMMENT '分类图片(上传路径)'");
        // 授权域名主域名归一: 历史数据里带www前缀的license_domain去掉www(与主控归一规则一致)
        try {
            $bad = DB::value("SELECT k FROM settings WHERE k = 'license_domain' AND v LIKE 'www.%'");
            if ($bad) DB::exec("UPDATE settings SET v = SUBSTR(v, 5) WHERE k = 'license_domain' AND v LIKE 'www.%' AND length(v) > 5");
        } catch (Exception $ex) {}
        $col('cards', 'note', "varchar(200) NOT NULL DEFAULT '' COMMENT '备注信息'");
        $col('users', 'level_id', "int unsigned NOT NULL DEFAULT 0 COMMENT '会员等级(0=无等级)'");
        $col('products', 'group_id', "int unsigned NOT NULL DEFAULT 0 COMMENT '商品分组(0=不分组)'");
        $col('products', 'icon', "varchar(255) NOT NULL DEFAULT '' COMMENT '商品图标(上传路径)'");

        // admin_users 扩展列
        foreach ([
            ['nickname', "varchar(50) NOT NULL DEFAULT '' COMMENT '昵称'", 'username'],
            ['role', "varchar(10) NOT NULL DEFAULT 'normal' COMMENT 'super超级管理员 normal普通管理员'", 'nickname'],
            ['status', "tinyint NOT NULL DEFAULT 1 COMMENT '1启用 0禁用'", 'role'],
            ['last_login_at', 'int unsigned NOT NULL DEFAULT 0', 'status'],
            ['last_login_ip', "varchar(45) NOT NULL DEFAULT ''", 'last_login_at'],
            ['prev_login_at', 'int unsigned NOT NULL DEFAULT 0', 'last_login_ip'],
            ['prev_login_ip', "varchar(45) NOT NULL DEFAULT ''", 'prev_login_at'],
        ] as [$c, $def, $after]) {
            $col('admin_users', $c, $def, $after);
        }
        // 最早创建的管理员设为超级管理员(仅一次)
        $pdo->exec("UPDATE admin_users SET role = 'super' WHERE id = (SELECT id FROM (SELECT id FROM admin_users ORDER BY id ASC LIMIT 1) t) AND role <> 'super'");

        // logs.ua
        $st = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'logs' AND COLUMN_NAME = 'ua'");
        if ((int)$st->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `logs` ADD COLUMN `ua` varchar(255) NOT NULL DEFAULT '' COMMENT '浏览器UA'");
            $log('OK: logs.ua 已添加');
        }

        // orders.expected_amount 精度扩容(6→10位小数, 适配BTC等高精度链上金额)
        $st = $pdo->query("SELECT NUMERIC_SCALE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'expected_amount'");
        $sc = $st->fetch(PDO::FETCH_ASSOC);
        if ($sc && (int)$sc['NUMERIC_SCALE'] < 10) {
            $pdo->exec("ALTER TABLE `orders` MODIFY `expected_amount` decimal(20,10) NOT NULL DEFAULT 0.0000000000");
            $log('OK: orders.expected_amount 精度已扩容至 decimal(20,10)');
        }
    }
}
