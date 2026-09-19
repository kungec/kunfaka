<?php
/**
 * 商店自助购买 - 异步通知(公开路由, 仅验签名)
 * 码支付(epay协议)服务器回调: ?s=/storepay/notify
 * 校验: MD5签名 + 商户订单号 + 金额一致 → 激活专业版
 */
class StorepayController
{
    public function actionNotify()
    {
        $req = array_merge($_GET, $_POST);
        $key = setting('storepay_codepay_key');
        if ($key === '') exit('fail');
        if (!EpayClient::verifyNotify($req, $key)) {
            http_response_code(400);
            exit('sign error');
        }
        // 仅受理交易成功通知
        if (isset($req['trade_status']) && $req['trade_status'] !== 'TRADE_SUCCESS') {
            exit('success');
        }
        $sn = trim((string)$req['out_trade_no']);
        $money = (float)$req['money'];
        $order = DB::fetch('SELECT * FROM store_orders WHERE sn = ?', [$sn]);
        if (!$order) {
            http_response_code(400);
            exit('order not found');
        }
        if ((int)$order['status'] === 1) {
            exit('success'); // 幂等
        }
        if (abs($money - (float)$order['amount']) >= 0.01) {
            http_response_code(400);
            exit('money error');
        }
        DB::update('store_orders', [
            'status' => 1,
            'txid' => trim((string)($req['trade_no'] ?? '')),
            'paid_at' => now(),
        ], 'id = ?', [(int)$order['id']]);
        $this->activatePro($sn);
        echo 'success';
    }

    /** 激活专业版(永久) */
    public static function activatePro($sn)
    {
        setting_set('license_key', 'STORE-' . $sn);
        setting_set('license_type', 'pro');
        setting_set('license_expires', '0');
        add_log('store', '商店购买订单 ' . $sn . ' 支付成功, 已自动开通专业版会员');
    }
}
