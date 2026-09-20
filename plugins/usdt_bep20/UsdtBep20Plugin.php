<?php
/**
 * USDT免挂支付插件(BEP20)
 * 使用方法: 应用商店安装启用后, 填写该链收款地址与BscScan免费API密钥即可。
 * 原理: 按实时汇率(CNY)将订单金额换算为USDT并附加唯一尾数, 轮询BscScan公开接口
 *       查询该地址的USDT代币转入记录, 金额与订单唯一金额完全一致即自动发货。
 * 建议: 同时在宝塔添加计划任务访问 cron.php (每1分钟), 提升到账检测实时性。
 * 注意: 仅识别BEP20链上向收款地址的直接USDT转账, 请勿转错网络。
 */
class UsdtBep20Plugin extends UsdtEvmBasePlugin
{
    protected $slug = 'usdt_bep20';
    protected $netName = 'BEP20';
    protected $scanApi = 'https://api.bscscan.com';
    protected $contract = '0x55d398326f99059fF775485246999027B3197955';
    protected $tokenDecimals = 18;
}
