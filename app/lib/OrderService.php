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

        // 人工处理型商品: 不占卡密, 付款即转"待处理"(状态3), 由管理员在后台填写内容发货
        $deliveryType = (string)DB::value('SELECT delivery_type FROM products WHERE id = ?', [$order['product_id']]);
        if ($deliveryType === 'manual') {
            $claimed = DB::exec('UPDATE orders SET status = 3, paid_at = ? WHERE id = ? AND status = 0', [now(), $orderId]);
            if (!$claimed) return false;
            $isEmail = $order['contact_type'] === 'email'
                || ($order['contact_type'] === '' && strpos($order['contact'], '@') !== false);
            if ($isEmail && $order['contact']) {
                send_mail(
                    $order['contact'],
                    '【' . setting('site_name', '坤发卡') . '】订单已支付, 处理中',
                    "您的订单 {$order['sn']} 已支付成功, 我们将尽快人工处理并发送结果到本邮箱。\n\n订单查询: " . u('order/detail', ['sn' => $order['sn']])
                );
            }
            self::notifyAdminPaid($order);
            return true;
        }

        // 原子认领: 仅当仍为待支付时置为已支付, 防止回调/轮询/cron 并发触发重复发卡
        $claimed = DB::exec('UPDATE orders SET status = 1, paid_at = ? WHERE id = ? AND status = 0', [now(), $orderId]);
        if (!$claimed) return false;

        $num = max(1, (int)$order['num']);
        // 先查后占: SQLite的UPDATE不支持LIMIT, 用SELECT筛选+IN绑定保持双数据库兼容
        $pick = DB::fetchAll('SELECT id FROM cards WHERE product_id = ? AND status = 0 ORDER BY id ASC LIMIT ?', [$order['product_id'], $num]);
        if (count($pick) < $num) {
            // 库存不足: 订单转"待处理"(状态3)等管理员补卡后补发
            DB::update('orders', ['status' => 3], 'id = ?', [$orderId]);
            self::stockAlertCheck($order['product_id']);
            return false;
        }
        $ids = array_map('intval', array_column($pick, 'id'));
        $ph = implode(',', array_fill(0, count($ids), '?'));
        DB::exec("UPDATE cards SET status = 1, order_id = ?, sold_at = ? WHERE id IN ($ph)", array_merge([$orderId, now()], $ids));
        $cards = DB::fetchAll("SELECT content FROM cards WHERE id IN ($ph) ORDER BY id ASC", $ids);

        if (count($cards) < $num) {
            // 占位校验失败(并发极端情况): 订单转"待处理"
            DB::update('orders', ['status' => 3], 'id = ?', [$orderId]);
            self::stockAlertCheck($order['product_id']);
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
        self::stockAlertCheck($order['product_id']);
        // 优惠券核销(营销插件)
        if (!empty($order['coupon_code'])) {
            $mkt = Plugin::marketingActive();
            if ($mkt && method_exists($mkt, 'onPaid')) $mkt->onPaid($order);
        }
        return true;
    }

    /** 库存断货预警: 剩余≤阈值时通知管理员(每商品每次跌破只发一次, 回补后自动重置) */
    public static function stockAlertCheck($productId)
    {
        $threshold = (int)setting('stock_alert_num', '5');
        $to = trim((string)setting('notify_email', ''));
        if ($threshold <= 0 || $to === '') return;
        $flagKey = 'stock_alert_' . (int)$productId;
        $stock = (int)DB::value('SELECT COUNT(*) FROM cards WHERE product_id = ? AND status = 0', [$productId]);
        if ($stock > 0 && $stock <= $threshold) {
            if (setting($flagKey) === '1') return; // 已通知过, 避免每单重复轰炸
            $name = (string)DB::value('SELECT name FROM products WHERE id = ?', [$productId]);
            send_mail(
                $to,
                '【' . setting('site_name', '坤发卡') . '】库存预警: ' . $name,
                "商品「{$name}」可用库存仅剩 {$stock} 件(预警阈值 {$threshold} 件), 请及时到后台补充卡密。\n\n后台入口: " . site_url(defined('YF_ADMIN_ENTRY') ? YF_ADMIN_ENTRY : 'admin.php')
            );
            setting_set($flagKey, '1');
        } elseif ($stock > $threshold) {
            if (setting($flagKey) !== '') setting_set($flagKey, '');
        }
    }

    /** 人工处理商品有新付款时通知管理员(复用断货预警的通知邮箱) */
    protected static function notifyAdminPaid($order)
    {
        $to = trim((string)setting('notify_email', ''));
        if ($to === '') return;
        send_mail(
            $to,
            '【' . setting('site_name', '坤发卡') . '】人工发货提醒: ' . $order['product_name'],
            "商品「{$order['product_name']}」有新订单待人工处理:\n\n订单号: {$order['sn']}\n数量: {$order['num']}\n金额: ¥" . nf($order['total']) . "\n联系方式: {$order['contact']}\n\n请到后台「订单管理」中筛选待处理订单进行发货。"
        );
    }

    /** 是否需要检查链上免挂支付到账(前台轮询时触发) */
    public static function pollCheck($sn)
    {
        $order = DB::fetch('SELECT * FROM orders WHERE sn = ?', [$sn]);
        if (!$order) return null;
        if ((int)$order['status'] === 0 && $order['pay_plugin'] !== '') {
            $plugin = Plugin::payment($order['pay_plugin']);
            if ($plugin && $plugin->enabled) {
                try {
                    $plugin->pollOrder($order);
                } catch (Exception $ex) {
                    if (defined('YF_DEBUG_LOG')) file_put_contents(YF_DEBUG_LOG, date('H:i:s') . ' ' . $order['pay_plugin'] . ' err: ' . $ex->getMessage() . "\n", FILE_APPEND);
                }
            }
        }
        return DB::fetch('SELECT sn, status, cards_content, expired_at, total, expected_amount, pay_plugin FROM orders WHERE sn = ?', [$sn]);
    }
}
