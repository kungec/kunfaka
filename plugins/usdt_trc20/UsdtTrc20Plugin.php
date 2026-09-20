<?php
/**
 * USDT免挂支付插件(TRC20)
 * 使用方法: 应用商店安装启用后, 在插件配置中填写自己的TRC20钱包地址即可。
 * 原理: 订单金额附加唯一尾数, 轮询TRON公链公开API(TronGrid)查询该地址的
 *       TRC20转入记录, 金额与订单唯一金额一致即自动发货。
 * 建议: 同时在宝塔添加计划任务访问 cron.php (每1分钟), 提升到账检测实时性。
 */
class UsdtTrc20Plugin extends PaymentBase
{
    public function fields()
    {
        return [
            ['key' => 'wallet_address', 'label' => 'TRC20钱包地址', 'type' => 'text', 'default' => '', 'desc' => '您的USDT(TRC20)收款地址, 以T开头, 例如 TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'],
        ];
    }

    /* ---- 链上免挂钩子实现(委托 TronService) ---- */

    public function chainUnit()
    {
        return 'USDT';
    }

    public function assignAmount(array $order)
    {
        return TronService::assignAmount($order);
    }

    public function pollOrder(array $order)
    {
        $wallet = $this->cfg('wallet_address');
        if ($wallet === '') return false;
        return TronService::checkOrder($order, $wallet);
    }

    public function sweep()
    {
        return TronService::sweep();
    }

    public function pay(array $order)
    {
        $wallet = $this->cfg('wallet_address');
        if ($wallet === '') {
            throw new Exception('管理员尚未配置TRC20收款地址');
        }
        return [
            'type' => 'qrcode',
            'qr' => $wallet,
            'extra' => [
                'tip' => '请向以下TRC20地址转入精确金额的USDT',
                'wallet' => $wallet,
                'chain' => 'TRC20',
            ],
        ];
    }

    public function notify(array $req)
    {
        // USDT为链上主动轮询到账, 无第三方异步通知
        return null;
    }
}
