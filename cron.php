<?php
/**
 * 坤发卡 - 计划任务入口
 * 宝塔计划任务(Shell脚本):  php /www/wwwroot/你的站点/cron.php
 * 或(访问URL):             curl -s "http://你的站点/cron.php?key=YF_KEY的值"(见 data/config.php)
 * 建议: 每1分钟执行一次, 负责 1)过期订单处理 2)USDT免挂支付轮询到账检测
 */
require __DIR__ . '/app/bootstrap.php';

// Web方式访问需要携带密钥(YF_KEY), 防止外部随意触发; 命令行(宝塔Shell)不受限
if (PHP_SAPI !== 'cli') {
    $key = isset($_GET['key']) ? (string)$_GET['key'] : '';
    if (!defined('YF_KEY') || $key === '' || !hash_equals(YF_KEY, $key)) {
        http_response_code(403);
        exit('forbidden');
    }
}

require YF_ROOT . '/app/lib/Router.php';

// 1. 过期未支付订单
$n = DB::exec('UPDATE orders SET status = 2 WHERE status = 0 AND expired_at > 0 AND expired_at < ?', [now()]);
echo '[expire] ' . $n . " orders expired\n";

// 2. USDT免挂支付轮询
try {
    $c = TronService::sweep();
    echo "[usdt] checked, {$c} paid\n";
} catch (Exception $ex) {
    echo '[usdt] error: ' . $ex->getMessage() . "\n";
}
