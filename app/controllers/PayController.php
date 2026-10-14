<?php
/**
 * 前台: 支付流程(选择支付方式/发起支付/异步通知/同步跳转/到账轮询)
 */
class PayController
{
    /** 选择支付方式页 */
    public function actionChoose()
    {
        $order = $this->findOrder();
        if (!$order) {
            View::theme('error', ['msg' => '订单不存在', 'pageTitle' => '错误']);
            return;
        }
        if ((int)$order['status'] === 1) redirect(u('order/detail', ['sn' => $order['sn']]));
        if ((int)$order['status'] !== 0) redirect(u('order/detail', ['sn' => $order['sn']]));
        $payments = enabled_payments();
        View::theme('choose', [
            'order' => $order,
            'payments' => $payments,
            'pageTitle' => '选择支付方式',
        ]);
    }

    /** 发起支付 */
    public function actionGo()
    {
        // 双入口: 商品页直购(GET, 买家已选支付方式) 与 收银台选择(POST); 发起支付无状态变更危害, GET 免CSRF
        $sn = trim((string)($_REQUEST['sn'] ?? ''));
        $code = trim((string)($_REQUEST['plugin'] ?? ''));
        if ($sn === '' || $code === '') redirect(u('home/index'));
        $order = DB::fetch('SELECT * FROM orders WHERE sn = ?', [$sn]);
        if (!$order) {
            View::theme('error', ['msg' => '订单不存在', 'pageTitle' => '错误']);
            return;
        }
        if ((int)$order['status'] === 1) redirect(u('order/detail', ['sn' => $sn]));
        if ((int)$order['status'] !== 0) {
            View::theme('error', ['msg' => '订单已过期, 请重新下单', 'pageTitle' => '订单过期']);
            return;
        }
        // 双保险: 状态仍是待支付但已过到期时间(cron未及关闭)时同样拒绝发起支付, 防过期订单被继续支付
        if ((int)$order['expired_at'] > 0 && (int)$order['expired_at'] < now()) {
            View::theme('error', ['msg' => '订单已过期, 请重新下单', 'pageTitle' => '订单过期']);
            return;
        }
        $plugin = Plugin::payment($code);
        if (!$plugin || !$plugin->enabled) {
            View::theme('error', ['msg' => '支付方式不可用', 'pageTitle' => '错误']);
            return;
        }
        // 聚合支付渠道(多渠道开放时买家所选的那一个)
        $channel = trim((string)($_REQUEST['channel'] ?? ''));
        $channels = $plugin->channels();
        if ($channels && $channel !== '') {
            if (!in_array($channel, $channels, true)) {
                View::theme('error', ['msg' => '支付渠道不可用', 'pageTitle' => '错误']);
                return;
            }
            $plugin->channel = $channel;
        }
        DB::update('orders', ['pay_plugin' => $code] + ($channel !== '' ? ['channel' => $channel] : []), 'id = ?', [$order['id']]);
        $order['pay_plugin'] = $code;

        // 链上免挂支付: 分配唯一应付金额(插件各自实现)
        $extra = [];
        try {
            $assigned = $plugin->assignAmount($order);
            if ($assigned !== null && (float)$assigned > 0) {
                $extra['expected_amount'] = $assigned;
                $extra['unit'] = $plugin->chainUnit();
                $extra['decimals'] = $plugin->chainDecimals();
                $extra['wallet'] = isset($plugin->config['wallet_address']) ? $plugin->config['wallet_address'] : '';
            }
        } catch (Exception $ex) {
            View::theme('error', ['msg' => $ex->getMessage(), 'pageTitle' => '错误']);
            return;
        }

        try {
            $res = $plugin->pay($order);
        } catch (Exception $ex) {
            View::theme('error', ['msg' => '支付发起失败: ' . $ex->getMessage(), 'pageTitle' => '支付失败']);
            return;
        }
        $res['extra'] = isset($res['extra']) && is_array($res['extra']) ? array_merge($res['extra'], $extra) : $extra;
        $res['plugin_name'] = $plugin->name;
        $res['plugin_code'] = $code;
        $res['icon'] = $channel !== '' ? payment_icon($channel) : payment_icon($code, $plugin->config);

        if (isset($res['type']) && $res['type'] === 'redirect' && !empty($res['url'])) {
            redirect($res['url']);
        }
        View::theme('pay', [
            'order' => $order,
            'pay' => $res,
            'pageTitle' => '订单支付',
        ]);
    }

    /** 异步通知(各支付网关回调) */
    public function actionNotify()
    {
        $code = isset($_GET['plugin']) ? $_GET['plugin'] : '';
        $plugin = Plugin::payment($code);
        if (!$plugin || !$plugin->enabled) {
            // 已停用的插件回调入口一并失效, 防止绕过启用状态直打回调
            echo 'fail';
            return;
        }
        // 只取GET/POST参数参与验签(排除COOKIE污染)
        $req = array_merge($_GET, $_POST);
        try {
            $result = $plugin->notify($req);
            if (is_array($result) && !empty($result['sn'])) {
                $order = DB::fetch('SELECT * FROM orders WHERE sn = ?', [$result['sn']]);
                // 回调归属校验: 订单发起支付时记录的插件必须与回调入口插件一致, 防跨渠道伪造/串渠道发货
                if ($order && !empty($order['pay_plugin']) && $order['pay_plugin'] !== $plugin->code) {
                    add_log('pay', '订单 ' . $order['sn'] . ' 回调渠道不符(订单渠道 ' . $order['pay_plugin'] . ', 入口 ' . $plugin->code . '), 已拒绝');
                    echo 'fail';
                    return;
                }
                if ($order && (int)$order['status'] === 0) {
                    // 金额一致性校验: 网关带回有效金额时严格比对; 缺失金额不在此拦截(链上按唯一金额精确对账, 法币可经回跳核实兜底)
                    if (isset($result['money']) && $result['money'] !== null && (float)$result['money'] > 0
                        && abs((float)$result['money'] - (float)$order['total']) > 0.01) {
                        add_log('pay', '订单 ' . $order['sn'] . ' 回调金额不符(收到 ' . $result['money'] . ', 应付 ' . $order['total'] . '), 已拒绝发货');
                        echo 'fail';
                        return;
                    }
                    // 过期处理: 过期在宽限期(30分钟)内照常发货; 超宽限期转人工(状态3), 网关停发重试, 由管理员核对收款后补发或退款
                    $expiredAt = (int)$order['expired_at'];
                    if ($expiredAt > 0 && $expiredAt < now() - 1800) {
                        add_log('pay', '订单 ' . $order['sn'] . ' 已过期超宽限仍收到支付成功回调, 已转人工处理(请核对收款后补发或退款)');
                        DB::update('orders', ['status' => 3], 'id = ? AND status = 0', [$order['id']]);
                        echo $plugin->ack();
                        return;
                    }
                    if (OrderService::deliver($order['id'], isset($result['trade_no']) ? $result['trade_no'] : '')) {
                        add_log('pay', '订单 ' . $order['sn'] . ' 支付成功并自动发货(' . $plugin->name . ')');
                    } else {
                        // 发货未完成(缺货转待处理/状态已变更): 仍应答成功终止重试, 状态3订单在后台补发
                        add_log('pay', '订单 ' . $order['sn'] . ' 支付成功但自动发货未完成(缺货转待处理), 请在后台处理(' . $plugin->name . ')');
                    }
                }
                echo $plugin->ack();
                return;
            }
            echo 'fail';
        } catch (Exception $ex) {
            add_log('pay', '回调处理异常(' . $plugin->code . '): ' . $ex->getMessage());
            echo 'fail';
        } catch (Throwable $ex) {
            add_log('pay', '回调处理异常(' . $plugin->code . '): ' . $ex->getMessage());
            echo 'fail';
        }
    }

    /** 同步跳转回站 */
    /** 支付同步回跳(网关付款后浏览器跳回): 支持插件服务端二次核实发货 */
    public function actionReturn()
    {
        $sn = isset($_GET['sn']) ? trim($_GET['sn']) : (isset($_GET['out_trade_no']) ? trim($_GET['out_trade_no']) : '');
        if ($sn === '') redirect(u('home/index'));
        $order = DB::fetch('SELECT * FROM orders WHERE sn = ?', [$sn]);
        if ($order && (int)$order['status'] === 0 && !empty($order['pay_plugin'])) {
            try {
                $plugin = Plugin::payment($order['pay_plugin']);
                if ($plugin && $plugin->enabled) {
                    $result = $plugin->verifyReturn($order, array_merge($_GET, $_POST));
                    if (is_array($result) && !empty($result['sn'])) {
                        $fresh = DB::fetch('SELECT * FROM orders WHERE sn = ?', [$result['sn']]);
                        if ($fresh && (int)$fresh['status'] === 0
                            && (!isset($result['money']) || abs((float)$result['money'] - (float)$fresh['total']) <= 0.01)) {
                            OrderService::deliver($fresh['id'], isset($result['trade_no']) ? $result['trade_no'] : '');
                            add_log('pay', '订单 ' . $fresh['sn'] . ' 回跳核实支付成功并自动发货(' . $plugin->name . ')');
                        }
                    }
                }
            } catch (Exception $ex) {
                // 核实失败不阻塞跳转, 订单页轮询/Webhook仍可发货
            }
        }
        redirect(u('order/detail', ['sn' => $sn]));
    }

    /** 订单状态轮询(支付页AJAX), USDT订单顺带触发链上到账检测 */
    public function actionCheck()
    {
        $sn = trim(arr_get($_REQUEST, 'sn'));
        $order = OrderService::pollCheck($sn);
        if (!$order) json_out(['code' => 1, 'msg' => '订单不存在']);
        json_out([
            'code' => 0,
            'status' => (int)$order['status'],
            'paid' => (int)$order['status'] === 1,
            'detail_url' => u('order/detail', ['sn' => $order['sn']]),
        ]);
    }

    protected function findOrder()
    {
        $sn = isset($_GET['sn']) ? trim($_GET['sn']) : '';
        if ($sn === '') return null;
        return DB::fetch('SELECT * FROM orders WHERE sn = ?', [$sn]);
    }
}
