<?php
/**
 * 坤发卡 - 前台入口
 * 宝塔站点运行目录可指向本目录, URL形式: index.php?s=/home/index
 */
require __DIR__ . '/app/bootstrap.php';
require YF_ROOT . '/app/lib/Router.php';
$route = isset($_GET['s']) ? $_GET['s'] : 'home/index';
Router::dispatch($route, false);
