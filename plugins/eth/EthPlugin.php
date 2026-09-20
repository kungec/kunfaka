<?php
/**
 * ETH以太坊免挂支付插件(ETH主链)
 * 使用方法: 应用商店安装启用后, 填写自己的ETH收款地址与Etherscan API密钥(免费注册)。
 * 原理: 按实时汇率(CNY)将订单金额换算为ETH并附加唯一尾数, 轮询Etherscan公开接口
 *       查询该地址的转入交易, 金额与订单唯一金额完全一致即自动发货。
 * 建议: 同时在宝塔添加计划任务访问 cron.php (每1分钟), 提升到账检测实时性。
 * 注意: ETH价格波动较大, 建议配置溢价(默认1%); 仅支持ETH主链转账(合约代币不识别)。
 */
class EthPlugin extends PaymentBase
{
    const SLUG = 'eth';
    const COINGECKO_ID = 'ethereum';
    const RATE_API = 'https://api.coingecko.com/api/v3';

    public function fields()
    {
        return [
            ['key' => 'wallet_address', 'label' => 'ETH收款地址', 'type' => 'text', 'default' => '', 'desc' => '您的以太坊收款地址, 以0x开头(仅识别ETH主链直接转账, 合约代币转账不发货)'],
            ['key' => 'etherscan_key', 'label' => 'Etherscan API密钥', 'type' => 'text', 'default' => '', 'desc' => '在 etherscan.io 免费注册创建(免费版每秒5次, 完全够用)'],
            ['key' => 'premium', 'label' => '汇率溢价(%)', 'type' => 'text', 'default' => '1', 'desc' => '对冲ETH价格波动的加收百分比, 建议不低于1'],
            ['key' => 'rate_api', 'label' => '汇率接口(选填)', 'type' => 'text', 'default' => '', 'desc' => '默认 https://api.coingecko.com/api/v3 (CoinGecko, 按CNY计价); 被墙或需代理时可替换为兼容接口'],
        ];
    }

    public function pay(array $order)
    {
        $wallet = $this->cfg('wallet_address');
        if ($wallet === '') {
            throw new Exception('管理员尚未配置ETH收款地址');
        }
        return [
            'type' => 'qrcode',
            'qr' => $wallet,
            'extra' => [
                'tip' => '请向以下ETH地址转入页面显示的精确金额',
                'wallet' => $wallet,
                'chain' => 'Ethereum',
            ],
        ];
    }

    public function notify(array $req)
    {
        return null;
    }

    public function chainUnit()
    {
        return 'ETH';
    }

    public function chainDecimals()
    {
        return 8;
    }

    public function assignAmount(array $order)
    {
        if ((float)$order['expected_amount'] > 0) return $order['expected_amount'];
        if (trim($this->cfg('etherscan_key')) === '') throw new Exception('管理员尚未配置Etherscan API密钥');
        $rate = $this->rateCny();
        $premium = (float)$this->cfg('premium', '1');
        $base = (float)$order['total'] / $rate * (1 + $premium / 100);
        if ($base <= 0) throw new Exception('金额计算异常');
        for ($i = 0; $i < 300; $i++) {
            $tail = rand(1, 9999) / 100000000;
            $amount = number_format(round($base, 6) + $tail, 8, '.', '');
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
        $key = trim($this->cfg('etherscan_key'));
        if ($addr === '' || $key === '') return false;
        $api = rtrim($this->cfg('api_base', 'https://api.etherscan.io'), '/');
        $url = $api . '/api?module=account&action=txlist&address=' . urlencode($addr)
            . '&startblock=0&endblock=99999999&page=1&offset=50&sort=desc&apikey=' . urlencode($key);
        $json = $this->httpGetJson($url);
        if (!is_array($json) || empty($json['result']) || !is_array($json['result'])) return false;
        $wantWei = str_replace('.', '', number_format((float)$order['expected_amount'], 8, '.', '')) . str_repeat('0', 10);
        $minTs = (int)$order['created_at'] - 120;
        foreach ($json['result'] as $tx) {
            if (!isset($tx['hash'], $tx['value'], $tx['to'])) continue;
            if (strcasecmp((string)$tx['to'], $addr) !== 0) continue;
            if (bccomp((string)$tx['value'], $wantWei) !== 0) continue;
            $ts = isset($tx['timeStamp']) ? (int)$tx['timeStamp'] : 0;
            if ($ts > 0 && $ts < $minTs) continue;
            $txid = (string)$tx['hash'];
            if (DB::value('SELECT id FROM orders WHERE txid = ? AND id != ?', [$txid, $order['id']])) continue;
            OrderService::deliver((int)$order['id'], '', $txid);
            add_log('usdt', 'ETH到账: 订单 ' . $order['sn'] . ' 金额 ' . $order['expected_amount'] . ' ETH, 哈希 ' . substr($txid, 0, 24));
            return true;
        }
        return false;
    }

    public function sweep()
    {
        if ($this->cfg('wallet_address') === '' || $this->cfg('etherscan_key') === '') return 0;
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
