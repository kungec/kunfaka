-- ============================================================
-- 坤发卡 全自动发卡系统 数据库结构
-- 适用于 MySQL 5.6+ / MariaDB
-- ============================================================

SET NAMES utf8mb4;

-- 系统配置表
CREATE TABLE IF NOT EXISTS `settings` (
  `k` varchar(64) NOT NULL PRIMARY KEY,
  `v` text NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 商品分类
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT '',
  `sort` int NOT NULL DEFAULT 0,
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1启用 0停用(前台隐藏)',
  `created_at` int unsigned NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 商品
CREATE TABLE IF NOT EXISTS `products` (
  `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `category_id` int unsigned NOT NULL DEFAULT 0,
  `name` varchar(200) NOT NULL,
  `description` text NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_num` int unsigned NOT NULL DEFAULT 1,
  `max_num` int unsigned NOT NULL DEFAULT 1,
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1上架 0下架',
  `sort` int NOT NULL DEFAULT 0,
  `sales` int unsigned NOT NULL DEFAULT 0,
  `group_id` int unsigned NOT NULL DEFAULT 0 COMMENT '商品分组(0=不分组)',
  `created_at` int unsigned NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 会员等级(LV数值用于商品分组可见性校验)
CREATE TABLE IF NOT EXISTS `member_levels` (
  `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(50) NOT NULL,
  `level` int NOT NULL DEFAULT 1 COMMENT '等级数值(越大越高)',
  `created_at` int unsigned NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 商品分组(可设最低会员等级, 低于该等级的买家不可见/不可购)
CREATE TABLE IF NOT EXISTS `product_groups` (
  `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(100) NOT NULL,
  `min_level` int NOT NULL DEFAULT 0 COMMENT '可见所需最低等级数值(0=不限制)',
  `created_at` int unsigned NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 卡密库存
CREATE TABLE IF NOT EXISTS `cards` (
  `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `product_id` int unsigned NOT NULL,
  `content` text NOT NULL,
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '0未售 1已售 2锁定(暂不出库)',
  `note` varchar(200) NOT NULL DEFAULT '' COMMENT '备注信息',
  `order_id` int unsigned NOT NULL DEFAULT 0,
  `created_at` int unsigned NOT NULL DEFAULT 0,
  `sold_at` int unsigned NOT NULL DEFAULT 0,
  KEY `idx_product_status` (`product_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 订单
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `sn` varchar(32) NOT NULL,
  `user_id` int unsigned NOT NULL DEFAULT 0 COMMENT '下单会员ID(游客为0)',
  `product_id` int unsigned NOT NULL DEFAULT 0,
  `product_name` varchar(200) NOT NULL DEFAULT '',
  `num` int unsigned NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `expected_amount` decimal(14,6) NOT NULL DEFAULT 0.000000 COMMENT 'USDT应收唯一金额',
  `contact` varchar(200) NOT NULL DEFAULT '' COMMENT '联系邮箱/QQ',
  `contact_type` varchar(20) NOT NULL DEFAULT '' COMMENT '联系方式类型: telegram/email/qq/wechat/phone',
  `pay_plugin` varchar(40) NOT NULL DEFAULT '',
  `trade_no` varchar(64) NOT NULL DEFAULT '' COMMENT '第三方流水号',
  `txid` varchar(100) NOT NULL DEFAULT '' COMMENT '链上交易哈希',
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '0待支付 1已完成 2已过期 3待处理',
  `cards_content` text NULL COMMENT '发货内容',
  `ip` varchar(45) NOT NULL DEFAULT '',
  `created_at` int unsigned NOT NULL DEFAULT 0,
  `paid_at` int unsigned NOT NULL DEFAULT 0,
  `expired_at` int unsigned NOT NULL DEFAULT 0,
  UNIQUE KEY `uk_sn` (`sn`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status_amount` (`status`,`pay_plugin`,`expected_amount`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 前台会员
CREATE TABLE IF NOT EXISTS `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `username` varchar(32) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL DEFAULT '',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1正常 0禁用',
  `created_at` int unsigned NOT NULL DEFAULT 0,
  `reg_ip` varchar(45) NOT NULL DEFAULT '',
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 注册记录(每IP每日注册次数限制)
CREATE TABLE IF NOT EXISTS `registers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `ip` varchar(45) NOT NULL,
  `created_at` int unsigned NOT NULL DEFAULT 0,
  KEY `idx_ip` (`ip`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 登录失败记录(爆破锁定)
CREATE TABLE IF NOT EXISTS `login_fails` (
  `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `scene` varchar(10) NOT NULL COMMENT 'admin后台 / user前台',
  `account` varchar(50) NOT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `fail_at` int unsigned NOT NULL DEFAULT 0,
  KEY `idx_key` (`scene`,`account`,`ip`,`fail_at`),
  KEY `idx_scene_ip` (`scene`,`ip`,`fail_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 系统日志(登录/支付/发货/商店/清理等关键事件)
CREATE TABLE IF NOT EXISTS `logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `type` varchar(20) NOT NULL DEFAULT 'system' COMMENT 'admin/user/pay/usdt/store/system',
  `message` text NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `created_at` int unsigned NOT NULL DEFAULT 0,
  KEY `idx_type` (`type`,`created_at`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 公告(后台可发布多条, 前台条栏+公告页展示)
CREATE TABLE IF NOT EXISTS `notices` (
  `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` varchar(200) NOT NULL,
  `content` text NULL COMMENT '公告正文(纯文本, 自动转义)',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1显示 0隐藏',
  `sort` int NOT NULL DEFAULT 0 COMMENT '越大越靠前',
  `created_at` int unsigned NOT NULL DEFAULT 0,
  KEY `idx_status` (`status`,`sort`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 应用(插件/主题)安装与启用状态
CREATE TABLE IF NOT EXISTS `apps` (
  `name` varchar(40) NOT NULL PRIMARY KEY,
  `type` varchar(20) NOT NULL DEFAULT 'payment',
  `enabled` tinyint NOT NULL DEFAULT 0,
  `config` text NULL,
  `installed_at` int unsigned NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 管理员
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nickname` varchar(50) NOT NULL DEFAULT '' COMMENT '昵称',
  `role` varchar(10) NOT NULL DEFAULT 'normal' COMMENT 'super超级管理员 normal普通管理员',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
  `last_login_at` int unsigned NOT NULL DEFAULT 0,
  `last_login_ip` varchar(45) NOT NULL DEFAULT '',
  `prev_login_at` int unsigned NOT NULL DEFAULT 0,
  `prev_login_ip` varchar(45) NOT NULL DEFAULT '',
  `created_at` int unsigned NOT NULL DEFAULT 0,
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 初始配置
INSERT IGNORE INTO `settings` (`k`,`v`) VALUES
('site_name','坤发卡'),
('site_url',''),
('theme','store'),
('announcement','本站已接入全自动发卡系统，支付成功后立即发货！'),
('order_timeout','15'),
('contact_qq',''),
('smtp_open','0'),
('smtp_host',''),
('smtp_port','465'),
('smtp_user',''),
('smtp_pass',''),
('smtp_ssl','1'),
('official_api','https://market.kunfaka.com'),
('license_key',''),
('license_type','free'),
('license_expires','0'),
('usdt_api','https://api.trongrid.io'),
('usdt_contract','TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'),
('captcha_open','1'),
('member_open','1'),
('turnstile_open','0'),
('turnstile_site_key',''),
('turnstile_secret_key',''),
('verify_mode','captcha'),
('geetest_id',''),
('geetest_key',''),
('geetest_timeout','120'),
('contact_types','email,qq'),
('service_contacts',''),
('singlepage_open','0'),
('singlepage_title','关于我们'),
('singlepage_content',''),
('cdn_mode','off');
