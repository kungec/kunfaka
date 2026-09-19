<?php
/**
 * 坤发卡 - 前台入口
 * URL形式: index.php?s=/home/index 或 伪静态 /home/index(需服务器回退支持)
 */
require __DIR__ . '/app/bootstrap.php';
require YF_ROOT . '/app/lib/Router.php';
$route = isset($_GET['s']) ? $_GET['s'] : trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
if ($route === '') $route = 'home/index';
Router::dispatch($route, false);
