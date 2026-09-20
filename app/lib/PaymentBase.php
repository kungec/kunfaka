<?php
/**
 * 支付插件基类 - 所有支付插件继承此类
 * pay():    发起支付, 返回 ['type'=>'redirect','url'=>..] 或 ['type'=>'qrcode','qr'=>文本,'extra'=>[]] 或 ['type'=>'html','html'=>..]
 * notify(): 处理异步通知, 验签成功返回 ['sn'=>订单号,'trade_no'=>流水号], 失败返回 null
 * fields(): 返回插件配置表单定义
 */
abstract class PaymentBase
{
    public $code = '';
    public $name = '';
    public $enabled = false;
    public $config = [];
    /** 聚合支付当前选中的渠道(alipay/wxpay/qqpay), 单渠道插件为空 */
    public $channel = '';

    /** 发起支付 */
    abstract public function pay(array $order);

    /** 异步通知验签与解析 */
    abstract public function notify(array $req);

    /** 配置表单字段定义 */
    public function fields()
    {
        return [];
    }

    /** 聚合支付开放渠道列表(多渠道插件覆盖此方法; 单渠道插件返回空数组) */
    public function channels()
    {
        return [];
    }

    /**
     * 支付同步回跳时的服务端二次核实(可选实现)
     * 返回 ['sn'=>..,'trade_no'=>..,'money'=>..] 表示已核实到账, 由控制器发货; null=不处理
     */
    public function verifyReturn(array $order, array $req)
    {
        return null;
    }

    /** 通知应答内容 */
    public function ack()
    {
        return 'success';
    }

    protected function notifyUrl()
    {
        return site_url('index.php?s=/pay/notify&plugin=' . $this->code);
    }

    protected function returnUrl($sn)
    {
        return site_url('index.php?s=/pay/return&sn=' . $sn);
    }

    protected function cfg($key, $default = '')
    {
        return isset($this->config[$key]) && $this->config[$key] !== '' ? $this->config[$key] : $default;
    }
}
