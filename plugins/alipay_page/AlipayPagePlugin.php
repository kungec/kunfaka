<?php
/**
 * 支付宝官方支付插件(电脑网站支付 alipay.trade.page.pay / 手机网站支付 alipay.trade.wap.pay)
 * 按移动端UA自动选择跳转收银台类型
 */
class AlipayPagePlugin extends PaymentBase
{
    public function fields()
    {
        return [
            ['key' => 'app_id', 'label' => '应用APPID', 'type' => 'text', 'default' => '', 'desc' => '支付宝开放平台应用APPID'],
            ['key' => 'private_key', 'label' => '应用私钥', 'type' => 'textarea', 'default' => '', 'desc' => '应用私钥(PKCS8格式)'],
            ['key' => 'alipay_public_key', 'label' => '支付宝公钥', 'type' => 'textarea', 'default' => '', 'desc' => '开放平台「应用详情-支付宝公钥」'],
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
        $amount = number_format((float)$order['total'], 2, '.', '');
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
        $isMobile = preg_match('/(mobile|android|iphone|ipad|phone)/i', $ua) === 1;
        if ($isMobile) {
            $url = $client->wapPay($order['sn'], $amount, $order['product_name'], $this->notifyUrl(), $this->returnUrl($order['sn']));
        } else {
            $url = $client->pagePay($order['sn'], $amount, $order['product_name'], $this->notifyUrl(), $this->returnUrl($order['sn']));
        }
        return ['type' => 'redirect', 'url' => $url];
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
