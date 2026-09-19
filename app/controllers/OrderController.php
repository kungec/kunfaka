<?php
/**
 * 前台: 订单查询
 */
class OrderController
{
    public function actionQuery()
    {
        $kw = trim(arr_get($_GET, 'kw'));
        $orders = [];
        if ($kw !== '') {
            // 支持精确订单号或联系方式匹配
            $orders = DB::fetchAll('SELECT * FROM orders WHERE sn = ? OR contact = ? ORDER BY id DESC LIMIT 20', [$kw, $kw]);
        }
        View::theme('query', [
            'orders' => $orders,
            'kw' => $kw,
            'categories' => DB::fetchAll('SELECT * FROM categories ORDER BY sort ASC, id ASC'),
            'pageTitle' => '订单查询',
        ]);
    }

    public function actionDetail()
    {
        $sn = trim(arr_get($_GET, 'sn'));
        $order = DB::fetch('SELECT * FROM orders WHERE sn = ?', [$sn]);
        if (!$order) {
            View::theme('error', ['msg' => '订单不存在, 请核对订单号', 'pageTitle' => '订单不存在']);
            return;
        }
        $pluginName = '';
        if ($order['pay_plugin']) {
            $meta = Plugin::meta('payment', $order['pay_plugin']);
            $pluginName = $meta ? $meta['title'] : $order['pay_plugin'];
        }
        View::theme('result', [
            'order' => $order,
            'pluginName' => $pluginName,
            'categories' => DB::fetchAll('SELECT * FROM categories ORDER BY sort ASC, id ASC'),
            'pageTitle' => '订单详情',
        ]);
    }
}
