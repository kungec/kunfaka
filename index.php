<?php
/**
 * 坤发卡 - 前台入口
 * URL形式: index.php?s=/home/index 或 伪静态 /home/index(需服务器回退支持)
 */
require __DIR__ . '/app/bootstrap.php';
require YF_ROOT . '/app/lib/Router.php';
$route = isset($_GET['s']) ? $_GET['s'] : trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
if ($route === '' || $route === 'index.php') $route = 'home/index';
// 搜索引擎收录控制: /robots.txt 按后台开关生成(默认禁止抓取敏感路径; 不暴露后台随机入口名)
if ($route === 'robots.txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    echo setting('robots_disallow', '1') === '1'
        ? "User-agent: *\nDisallow: /data/\nDisallow: /cron.php\nDisallow: /install.php\nDisallow: /tools/\n"
        : "User-agent: *\nDisallow:\n";
    exit;
}
Router::dispatch($route, false);
