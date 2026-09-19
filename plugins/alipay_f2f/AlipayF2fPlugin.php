<?php
/**
 * 支付宝当面付插件(alipay.trade.precreate 扫码支付)
 * 需在支付宝开放平台创建应用并签约「当面付」
 */
class AlipayF2fPlugin extends PaymentBase
{
    public function fields()
    {
        return [
            ['key' => 'app_id', 'label' => '应用APPID', 'type' => 'text', 'default' => '', 'desc' => '支付宝开放平台应用APPID'],
            ['key' => 'private_key', 'label' => '应用私钥', 'type' => 'textarea', 'default' => '', 'desc' => '应用私钥(PKCS8格式, 一行或含头尾行均可)'],
            ['key' => 'alipay_public_key', 'label' => '支付宝公钥', 'type' => 'textarea', 'default' => '', 'desc' => '开放平台「应用详情-支付宝公钥」(不是应用公钥)'],
            ['key' => 'gateway', 'label' => '网关地址', 'type' => 'text', 'default' => 'https://openapi.alipay.com/gateway.php', 'desc' => '正式环境 https://openapi.alipay.com/gateway.php'],
        ];
    }

    protected function client()
    {
        return new AlipayClient($this->cfg('app_id'), $this->cfg('private_key'), $this->cfg('alipay_public_key'), $this->cfg('gateway', 'https://openapi.alipay.com/gateway.php'));
    }

    public function pay(array $order)
    {
        $client = $this->client();
        $qr = $client->precreate($order['sn'], number_format((float)$order['total'], 2, '.', ''), $order['product_name'], $this->notifyUrl());
        return ['type' => 'qrcode', 'qr' => $qr, 'extra' => ['tip' => '请使用支付宝扫一扫付款']];
    }

    public function notify(array $req)
    {
        if (!$this->client()->verify($req)) return null;
        if (!isset($req['trade_status']) || $req['trade_status'] !== 'TRADE_SUCCESS') return null;
        if (!isset($req['out_trade_no'])) return null;
        $money = isset($req['total_amount']) && is_numeric($req['total_amount']) ? (float)$req['total_amount'] : -1;
        return ['sn' => $req['out_trade_no'], 'trade_no' => isset($req['trade_no']) ? $req['trade_no'] : '', 'money' => $money];
    }
}
