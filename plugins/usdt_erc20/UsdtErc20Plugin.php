<?php
/**
 * USDT免挂支付插件(ERC20)
 * 使用方法: 应用商店安装启用后, 填写该链收款地址与Etherscan免费API密钥即可。
 * 原理: 按实时汇率(CNY)将订单金额换算为USDT并附加唯一尾数, 轮询Etherscan公开接口
 *       查询该地址的USDT代币转入记录, 金额与订单唯一金额完全一致即自动发货。
 * 建议: 同时在宝塔添加计划任务访问 cron.php (每1分钟), 提升到账检测实时性。
 * 注意: 仅识别ERC20链上向收款地址的直接USDT转账, 请勿转错网络。
 */
class UsdtErc20Plugin extends UsdtEvmBasePlugin
{
    protected $slug = 'usdt_erc20';
    protected $netName = 'ERC20';
    protected $scanApi = 'https://api.etherscan.io';
    protected $contract = '0xdAC17F958D2ee523a2206206994597C13D831ec7';
    protected $tokenDecimals = 6;
}
