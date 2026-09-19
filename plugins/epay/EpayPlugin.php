<?php
/**
 * 易支付插件(标准易支付协议)
 */
class EpayPlugin extends PaymentBase
{
    public function fields()
    {
        return [
            ['key' => 'gateway', 'label' => '易支付网关', 'type' => 'text', 'default' => '', 'desc' => '易支付平台地址, 例如 https://pay.example.com'],
            ['key' => 'pid', 'label' => '商户ID(pid)', 'type' => 'text', 'default' => '', 'desc' => '易支付平台分配的商户ID'],
            ['key' => 'key', 'label' => '商户密钥(KEY)', 'type' => 'text', 'default' => '', 'desc' => '易支付平台商户通信密钥'],
            ['key' => 'types', 'label' => '开放支付渠道(可多选)', 'type' => 'checkboxes', 'options' => ['alipay' => '支付宝', 'wxpay' => '微信', 'qqpay' => 'QQ钱包'], 'default' => ['alipay', 'wxpay'], 'desc' => '勾选的渠道会分别显示在前台支付页, 买家自行选择'],
        ];
    }

    /** 开放的支付渠道(兼容旧版单渠道type配置) */
    public function channels()
    {
        $t = isset($this->config['types']) ? $this->config['types'] : [];
        if (is_string($t)) $t = array_filter(array_map('trim', explode(',', $t)));
        $t = array_values(array_intersect((array)$t, ['alipay', 'wxpay', 'qqpay']));
        if (!$t) $t = [$this->cfg('type', 'alipay')];
        return $t;
    }

    public function pay(array $order)
    {
        $channels = $this->channels();
        $type = in_array($this->channel, $channels, true) ? $this->channel : $channels[0];
        $url = EpayClient::buildSubmit(
            $this->cfg('gateway'),
            $this->cfg('pid'),
            $this->cfg('key'),
            $type,
            $order,
            $this->notifyUrl(),
            $this->returnUrl($order['sn']),
            setting('site_name', '坤发卡')
        );
        return ['type' => 'redirect', 'url' => $url];
    }

    public function notify(array $req)
    {
        if (!EpayClient::verifyNotify($req, $this->cfg('key'))) return null;
        if (isset($req['trade_status']) && $req['trade_status'] !== 'TRADE_SUCCESS') return null;
        $money = isset($req['money']) && is_numeric($req['money']) ? (float)$req['money'] : -1;
        return ['sn' => $req['out_trade_no'], 'trade_no' => isset($req['trade_no']) ? $req['trade_no'] : '', 'money' => $money];
    }
}
