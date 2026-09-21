<?php
/**
 * 坤发卡 - 管理后台入口
 * 安装向导会将本文件重命名为随机文件名(如 admin_x7k9q2.php)作为唯一后台入口,
 * 并在 data/config.php 中写入 YF_ADMIN_ENTRY 常量。
 * 直接访问 admin.php 将返回404(安装后本文件已不存在; 手动恢复也只会得到404)。
 */
if (defined('YF_ADMIN_ENTRY') && basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '') !== YF_ADMIN_ENTRY) {
    http_response_code(404);
    exit('404 Not Found');
}
// 后台页面禁止搜索引擎收录(响应头方式, 不暴露后台入口地址)
header('X-Robots-Tag: noindex, nofollow');
require __DIR__ . '/app/bootstrap.php';
require YF_ROOT . '/app/lib/Router.php';
$route = isset($_GET['s']) ? $_GET['s'] : 'dashboard';
Router::dispatch($route, true);
