<?php
/**
 * 订单服务: 支付成功后的全自动发卡
 */
class OrderService
{
    /**
     * 标记订单已支付并自动发货
     * @param int    $orderId   订单ID
     * @param string $tradeNo   第三方支付流水号
     * @param string $txid      链上交易哈希(USDT)
     * @param bool   $redeliver 补发模式: 仅接受缺货待处理(状态3)订单, 补卡后重新发货
     * @return bool 是否本次完成发货
     */
    public static function deliver($orderId, $tradeNo = '', $txid = '', $redeliver = false)
    {
        $order = DB::fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if (!$order) return false;

        // 人工处理型商品: 不占卡密, 付款即转"待处理"(状态3), 由管理员在后台填写内容发货
        $deliveryType = (string)DB::value('SELECT delivery_type FROM products WHERE id = ?', [$order['product_id']]);
        if ($deliveryType === 'manual') {
            if ($redeliver) return false; // 人工商品走后台 actionOrderManualDeliver
            if ((int)$order['status'] !== 0) return false;
            $claimed = DB::exec('UPDATE orders SET status = 3, paid_at = ? WHERE id = ? AND status = 0', [now(), $orderId]);
            if (!$claimed) return false;
            $isEmail = $order['contact_type'] === 'email'
                || ($order['contact_type'] === '' && strpos($order['contact'], '@') !== false);
            if ($isEmail && $order['contact']) {
                send_mail(
                    $order['contact'],
                    '【' . setting('site_name', '坤发卡') . '】订单已支付, 处理中',
                    "您的订单 {$order['sn']} 已支付成功, 我们将尽快人工处理并发送结果到本邮箱。" . self::orderLink($order['sn'])
                );
            }
            self::notifyAdminPaid($order);
            return true;
        }

        $status = (int)$order['status'];
        if ($redeliver) {
            // 补发仅针对缺货待处理(状态3)订单; 释放旧版本可能遗留的部分占用卡(状态3=买家未收到内容, 释放安全)
            if ($status !== 3) return false;
            DB::exec('UPDATE cards SET status = 0, order_id = 0, sold_at = 0 WHERE order_id = ? AND status = 1', [$orderId]);
        } else {
            if ($status !== 0) return false;
        }

        $num = max(1, (int)$order['num']);
        $contents = [];
        $done = false;
        // 事务+行锁保证"选卡→占卡→写单"原子完成: 并发回调不会发出同一张卡;
        // 进程中断时整体回滚, 订单停留原状态, 网关重试/轮询可再次发货
        DB::begin();
        try {
            if (!$redeliver) {
                // 原子认领: 仅当仍为待支付时置为已支付, 防止回调/轮询/cron 并发触发重复发卡
                $claimed = DB::exec('UPDATE orders SET status = 1, paid_at = ? WHERE id = ? AND status = 0', [now(), $orderId]);
                if (!$claimed) { DB::rollBack(); return false; }
            }
            // FOR UPDATE 行锁(MySQL; SQLite为库级写锁)防并发选中同一批卡
            $lock = DB::driver() === 'mysql' ? ' FOR UPDATE' : '';
            $pick = DB::fetchAll('SELECT id FROM cards WHERE product_id = ? AND status = 0 ORDER BY id ASC LIMIT ' . $num . $lock, [$order['product_id']]);
            if (count($pick) >= $num) {
                $ids = array_map('intval', array_column($pick, 'id'));
                $ph = implode(',', array_fill(0, count($ids), '?'));
                DB::exec("UPDATE cards SET status = 1, order_id = ?, sold_at = ? WHERE id IN ($ph) AND status = 0", array_merge([$orderId, now()], $ids));
                $cards = DB::fetchAll("SELECT content FROM cards WHERE id IN ($ph) ORDER BY id ASC", $ids);
                if (count($cards) >= $num) {
                    foreach ($cards as $i => $c) $contents[] = ($num > 1 ? ($i + 1) . '. ' : '') . $c['content'];
                    $data = ['status' => 1, 'cards_content' => implode("\n", $contents)];
                    if (!$redeliver) $data['paid_at'] = now();
                    if ($tradeNo) $data['trade_no'] = $tradeNo;
                    if ($txid) $data['txid'] = $txid;
                    DB::update('orders', $data, 'id = ?', [$orderId]);
                    DB::exec('UPDATE products SET sales = sales + ? WHERE id = ?', [$num, $order['product_id']]);
                    $done = true;
                }
            }
            if ($done) { DB::commit(); } else { DB::rollBack(); }
        } catch (Throwable $t) {
            DB::rollBack();
            throw $t;
        }
        if (!$done) {
            // 库存不足: 订单转"待处理"(状态3)等管理员补卡后补发
            DB::update('orders', ['status' => 3], 'id = ?', [$orderId]);
            self::stockAlertCheck($order['product_id']);
            return false;
        }

        // 以下为事务外的通知类副作用(补发同样邮件通知买家)
        $isEmail = $order['contact_type'] === 'email'
            || ($order['contact_type'] === '' && strpos($order['contact'], '@') !== false);
        if ($isEmail && $order['contact']) {
            $intro = $redeliver ? "您的订单 {$order['sn']} 库存已回补, 以下为您的卡密:" : "您的订单 {$order['sn']} 已支付成功, 以下为您的卡密:";
            send_mail(
                $order['contact'],
                '【' . setting('site_name', '坤发卡') . '】订单发货成功',
                $intro . "\n\n" . implode("\n", $contents) . self::orderLink($order['sn'])
            );
        }
        self::stockAlertCheck($order['product_id']);
        if (!$redeliver) self::notifyOrderPaid($order);
        // 优惠券核销(营销插件): 仅在首次发货与补发成功时各恰好核销一次
        if (!empty($order['coupon_code'])) {
            $mkt = Plugin::marketingActive();
            if ($mkt && method_exists($mkt, 'onPaid')) $mkt->onPaid($order);
        }
        return true;
    }

    /** 订单查询链接(cron/CLI下未配置site_url时Host为localhost, 链接无效则省略) */
    private static function orderLink($sn)
    {
        $link = u('order/detail', ['sn' => $sn]);
        return (strpos($link, '//localhost/') !== false) ? '' : "\n\n订单查询: " . $link;
    }

    /** 库存断货预警: 剩余≤阈值时通知管理员(每商品每次跌破只发一次, 回补后自动重置) */
    public static function stockAlertCheck($productId)
    {
        $threshold = (int)setting('stock_alert_num', '5');
        if ($threshold <= 0) return;
        $flagKey = 'stock_alert_' . (int)$productId;
        $stock = (int)DB::value('SELECT COUNT(*) FROM cards WHERE product_id = ? AND status = 0', [$productId]);
        if ($stock > 0 && $stock <= $threshold) {
            if (setting($flagKey) === '1') return; // 已通知过, 避免每单重复轰炸
            $name = (string)DB::value('SELECT name FROM products WHERE id = ?', [$productId]);
            notify_admin(
                '库存预警: ' . $name,
                "商品「{$name}」可用库存仅剩 {$stock} 件(预警阈值 {$threshold} 件), 请及时到后台补充卡密。\n\n后台入口: " . site_url(defined('YF_ADMIN_ENTRY') ? YF_ADMIN_ENTRY : 'admin.php')
            );
            setting_set($flagKey, '1');
        } elseif ($stock > $threshold) {
            if (setting($flagKey) !== '') setting_set($flagKey, '');
        }
    }

    /** 人工处理商品有新付款时通知管理员(复用统一通知通道) */
    protected static function notifyAdminPaid($order)
    {
        notify_admin(
            '人工发货提醒: ' . $order['product_name'],
            "商品「{$order['product_name']}」有新订单待人工处理:\n\n订单号: {$order['sn']}\n数量: {$order['num']}\n金额: ¥" . nf($order['total']) . "\n联系方式: {$order['contact']}\n\n请到后台「订单管理」中筛选待处理订单进行发货。"
        );
    }

    /** 支付成功成交推送(开关默认关, 供TG等即时通道) */
    protected static function notifyOrderPaid($order)
    {
        if (setting('notify_order_paid', '0') !== '1') return;
        $payName = payment_display_name($order['pay_plugin'], $order['channel'] ?? '');
        notify_admin(
            '新订单成交: ' . $order['product_name'],
            "商品「{$order['product_name']}」已支付成交:\n\n订单号: {$order['sn']}\n数量: {$order['num']}\n金额: ¥" . nf($order['total']) . "\n支付方式: {$payName}\n联系方式: {$order['contact']}"
        );
    }

    /** 是否需要检查链上免挂支付到账(前台轮询时触发) */
    public static function pollCheck($sn)
    {
        $order = DB::fetch('SELECT * FROM orders WHERE sn = ?', [$sn]);
        if (!$order) return null;
        // 懒过期: 待支付但已到到期时间(cron未及关闭)时就地关闭, 口径与cron/发起支付入口一致, 且不再触发链上轮询
        if ((int)$order['status'] === 0 && (int)$order['expired_at'] > 0 && (int)$order['expired_at'] < now()) {
            DB::exec('UPDATE orders SET status = 2 WHERE id = ? AND status = 0', [$order['id']]);
            $order['status'] = 2;
            return DB::fetch('SELECT sn, status, cards_content, expired_at, total, expected_amount, pay_plugin FROM orders WHERE sn = ?', [$sn]);
        }
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
