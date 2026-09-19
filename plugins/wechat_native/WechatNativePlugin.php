<?php
/**
 * 微信官方支付插件(微信支付V3 API - Native扫码支付)
 * 按微信官方接口开发: /v3/pay/transactions/native
 * 需要配置: 商户号 / 公众号appid / 商户API证书序列号 / 商户API私钥 / APIv3密钥
 */
class WechatNativePlugin extends PaymentBase
{
    public function fields()
    {
        return [
            ['key' => 'mchid', 'label' => '商户号(mchid)', 'type' => 'text', 'default' => '', 'desc' => '微信支付商户平台-商户号'],
            ['key' => 'appid', 'label' => 'APPID', 'type' => 'text', 'default' => '', 'desc' => '公众号/服务号/小程序的APPID(需与商户号绑定)'],
            ['key' => 'serial_no', 'label' => '商户证书序列号', 'type' => 'text', 'default' => '', 'desc' => '商户平台「API安全」中查看证书序列号'],
            ['key' => 'private_key', 'label' => '商户API私钥', 'type' => 'textarea', 'default' => '', 'desc' => 'apiclient_key.pem 文件完整内容'],
            ['key' => 'apiv3_key', 'label' => 'APIv3密钥', 'type' => 'text', 'default' => '', 'desc' => '商户平台「API安全」设置的32位APIv3密钥'],
        ];
    }

    protected function client()
    {
        return new WechatPayV3($this->cfg('mchid'), $this->cfg('appid'), $this->cfg('serial_no'), $this->cfg('private_key'), $this->cfg('apiv3_key'));
    }

    public function pay(array $order)
    {
        $client = $this->client();
        $codeUrl = $client->nativePay(
            $order['sn'],
            (string)round((float)$order['total'] * 100),
            $order['product_name'],
            $this->notifyUrl()
        );
        return ['type' => 'qrcode', 'qr' => $codeUrl, 'extra' => ['tip' => '请使用微信扫一扫付款']];
    }

    public function notify(array $req)
    {
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (strpos($k, 'HTTP_WECHATPAY_') === 0) {
                $name = str_replace('_', '-', strtolower(substr($k, 5)));
                $headers[$name] = $v;
            }
        }
        $body = file_get_contents('php://input');
        try {
            $data = $this->client()->verifyNotify($headers, $body);
        } catch (Exception $ex) {
            return null;
        }
        if (!$data || empty($data['out_trade_no'])) return null;
        if (!isset($data['trade_state']) || $data['trade_state'] !== 'SUCCESS') return null;
        $money = isset($data['amount']['total']) ? round((int)$data['amount']['total'] / 100, 2) : -1;
        return ['sn' => $data['out_trade_no'], 'trade_no' => isset($data['transaction_id']) ? $data['transaction_id'] : '', 'money' => $money];
    }

    public function ack()
    {
        return json_encode(['code' => 'SUCCESS', 'message' => '成功']);
    }
}
