<?php
/**
 * 订单服务: 支付成功后的全自动发卡
 */
class OrderService
{
    /**
     * 标记订单已支付并自动发货
     * @param int    $orderId  订单ID
     * @param string $tradeNo  第三方支付流水号
     * @param string $txid     链上交易哈希(USDT)
     * @return bool 是否本次完成发货
     */
    public static function deliver($orderId, $tradeNo = '', $txid = '')
    {
        $order = DB::fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if (!$order) return false;
        if ((int)$order['status'] !== 0) return false; // 已处理

        $num = (int)$order['num'];
        DB::exec('UPDATE cards SET status = 1, order_id = ?, sold_at = ? WHERE product_id = ? AND status = 0 ORDER BY id ASC LIMIT ' . $num,
            [$orderId, now(), $order['product_id']]);
        $cards = DB::fetchAll('SELECT content FROM cards WHERE order_id = ? AND product_id = ? ORDER BY id ASC', [$orderId, $order['product_id']]);

        if (count($cards) < $num) {
            // 库存不足: 回滚本次占用并保持待支付? 库存不足时订单标记待处理(状态3)
            DB::update('orders', ['status' => 3], 'id = ?', [$orderId]);
            return false;
        }

        $contents = [];
        foreach ($cards as $i => $c) $contents[] = ($num > 1 ? ($i + 1) . '. ' : '') . $c['content'];
        $data = [
            'status' => 1,
            'cards_content' => implode("\n", $contents),
            'paid_at' => now(),
        ];
        if ($tradeNo) $data['trade_no'] = $tradeNo;
        if ($txid) $data['txid'] = $txid;
        DB::update('orders', $data, 'id = ?', [$orderId]);
        DB::exec('UPDATE products SET sales = sales + ? WHERE id = ?', [$num, $order['product_id']]);

        // 邮件通知(联系方式类型为邮箱时; 兼容旧订单按@识别)
        $isEmail = $order['contact_type'] === 'email'
            || ($order['contact_type'] === '' && strpos($order['contact'], '@') !== false);
        if ($isEmail && $order['contact']) {
            send_mail(
                $order['contact'],
                '【' . setting('site_name', '坤发卡') . '】订单发货成功',
                "您的订单 {$order['sn']} 已支付成功, 以下为您的卡密:\n\n" . implode("\n", $contents) . "\n\n订单查询: " . u('order/detail', ['sn' => $order['sn']])
            );
        }
        return true;
    }

    /** 是否需要检查USDT到账(前台轮询时触发) */
    public static function pollCheck($sn)
    {
        $order = DB::fetch('SELECT * FROM orders WHERE sn = ?', [$sn]);
        if (!$order) return null;
        if ((int)$order['status'] === 0 && $order['pay_plugin'] === 'usdt_trc20') {
            $config = app_config('usdt_trc20');
            $wallet = isset($config['wallet_address']) ? trim($config['wallet_address']) : '';
            if ($wallet !== '') {
                try {
                    TronService::checkOrder($order, $wallet);
                } catch (Exception $ex) {
                    if (defined('YF_DEBUG_LOG')) file_put_contents(YF_DEBUG_LOG, date('H:i:s') . ' usdt err: ' . $ex->getMessage() . "\n", FILE_APPEND);
                }
            }
        }
        return DB::fetch('SELECT sn, status, cards_content, expired_at, total, expected_amount, pay_plugin FROM orders WHERE sn = ?', [$sn]);
    }
}
