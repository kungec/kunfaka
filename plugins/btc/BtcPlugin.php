<?php
/**
 * BTC比特币免挂支付插件
 * 使用方法: 应用商店安装启用后, 在插件配置中填写自己的BTC收款地址即可。
 * 原理: 按实时汇率(CNY)将订单金额换算为BTC并附加唯一尾数, 轮询比特币公链公开接口
 *       (mempool.space)查询该地址的转入输出, 金额与订单唯一金额完全一致即自动发货。
 * 建议: 同时在宝塔添加计划任务访问 cron.php (每1分钟), 提升到账检测实时性。
 * 注意: BTC价格波动较大, 建议配置溢价(默认1%)对冲波动; 支付页金额以下单时刻汇率为准。
 */
class BtcPlugin extends PaymentBase
{
    const SLUG = 'btc';
    const COINGECKO_ID = 'bitcoin';
    const RATE_API = 'https://api.coingecko.com/api/v3';

    public function fields()
    {
        return [
            ['key' => 'wallet_address', 'label' => 'BTC收款地址', 'type' => 'text', 'default' => '', 'desc' => '您的比特币收款地址(原生SegWit以bc1开头, 或1/3开头)'],
            ['key' => 'premium', 'label' => '汇率溢价(%)', 'type' => 'text', 'default' => '1', 'desc' => '在上浮后的价格基础上加收的百分比, 对冲BTC价格波动, 建议不低于1'],
            ['key' => 'rate_api', 'label' => '汇率接口(选填)', 'type' => 'text', 'default' => '', 'desc' => '默认 https://api.coingecko.com/api/v3 (CoinGecko, 按CNY计价); 被墙或需代理时可替换为兼容接口'],
        ];
    }

    public function pay(array $order)
    {
        $wallet = $this->cfg('wallet_address');
        if ($wallet === '') {
            throw new Exception('管理员尚未配置BTC收款地址');
        }
        return [
            'type' => 'qrcode',
            'qr' => $wallet,
            'extra' => [
                'tip' => '请向以下BTC地址转入页面显示的精确金额',
                'wallet' => $wallet,
                'chain' => 'Bitcoin',
            ],
        ];
    }

    public function notify(array $req)
    {
        return null;
    }

    public function chainUnit()
    {
        return 'BTC';
    }

    public function assignAmount(array $order)
    {
        if ((float)$order['expected_amount'] > 0) return $order['expected_amount'];
        $rate = $this->rateCny();
        $premium = (float)$this->cfg('premium', '1');
        $base = (float)$order['total'] / $rate * (1 + $premium / 100);
        if ($base <= 0) throw new Exception('金额计算异常');
        for ($i = 0; $i < 300; $i++) {
            $tail = rand(1, 999) / 1000000;
            $amount = number_format(round($base, 4) + $tail, 6, '.', '');
            $exists = DB::value(
                'SELECT id FROM orders WHERE status = 0 AND pay_plugin = ? AND expected_amount = ? AND id != ?',
                [self::SLUG, $amount, $order['id']]
            );
            if (!$exists) {
                DB::update('orders', ['expected_amount' => $amount], 'id = ?', [$order['id']]);
                return $amount;
            }
        }
        throw new Exception('唯一金额分配失败, 请稍后重试');
    }

    public function pollOrder(array $order)
    {
        if ((float)$order['expected_amount'] <= 0) return false;
        $addr = trim($this->cfg('wallet_address'));
        if ($addr === '') return false;
        $api = rtrim($this->cfg('api_base', 'https://mempool.space'), '/');
        $txs = $this->httpGetJson($api . '/api/address/' . urlencode($addr) . '/txs');
        if (!is_array($txs)) return false;
        $wantSats = (string)round((float)$order['expected_amount'] * 100000000);
        $minTs = (int)$order['created_at'] - 120;
        foreach ($txs as $tx) {
            if (empty($tx['txid']) || empty($tx['vout']) || !is_array($tx['vout'])) continue;
            foreach ($tx['vout'] as $vout) {
                if (!isset($vout['scriptpubkey_address'], $vout['value'])) continue;
                if (strcasecmp((string)$vout['scriptpubkey_address'], $addr) !== 0) continue;
                if ((string)$vout['value'] !== $wantSats) continue;
                $txid = (string)$tx['txid'];
                if (DB::value('SELECT id FROM orders WHERE txid = ? AND id != ?', [$txid, $order['id']])) continue 2;
                OrderService::deliver((int)$order['id'], '', $txid);
                add_log('usdt', 'BTC到账: 订单 ' . $order['sn'] . ' 金额 ' . $order['expected_amount'] . ' BTC, 哈希 ' . substr($txid, 0, 24));
                return true;
            }
        }
        return false;
    }

    public function sweep()
    {
        if ($this->cfg('wallet_address') === '') return 0;
        $orders = DB::fetchAll(
            'SELECT * FROM orders WHERE status = 0 AND pay_plugin = ? AND expected_amount > 0 AND expired_at > ? ORDER BY created_at ASC LIMIT 30',
            [self::SLUG, now()]
        );
        $paid = 0;
        foreach ($orders as $order) {
            try {
                if ($this->pollOrder($order)) $paid++;
            } catch (Exception $ex) {
                // 单笔失败不影响其他
            }
        }
        return $paid;
    }

    /** 实时CNY汇率(5分钟缓存) */
    protected function rateCny()
    {
        $cacheKey = self::SLUG . '_rate_cache';
        $cached = setting($cacheKey, '');
        if ($cached !== '') {
            $a = json_decode($cached, true);
            if (is_array($a) && isset($a['t'], $a['r']) && (int)$a['t'] > now() - 300 && (float)$a['r'] > 0) return (float)$a['r'];
        }
        $api = rtrim($this->cfg('rate_api', self::RATE_API), '/');
        $json = $this->httpGetJson($api . '/simple/price?ids=' . self::COINGECKO_ID . '&vs_currencies=cny');
        $r = is_array($json) && isset($json[self::COINGECKO_ID]['cny']) ? (float)$json[self::COINGECKO_ID]['cny'] : 0;
        if ($r <= 0) throw new Exception('汇率服务暂不可用, 请稍后重试');
        setting_set($cacheKey, json_encode(['t' => now(), 'r' => $r]));
        return $r;
    }

    protected function httpGetJson($url)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        curl_tls($ch);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        $res = curl_exec($ch);
        curl_close($ch);
        if ($res === false) throw new Exception('链上接口请求失败');
        return json_decode($res, true);
    }
}
