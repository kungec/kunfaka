<?php
/**
 * XMR门罗币免挂支付插件
 * 前置条件: 门罗是隐私币, 无法像BTC那样只凭公开地址查账, 需要站长自行运行官方
 *           monero-wallet-rpc 服务(与发卡站同机或内网), 插件通过其RPC接口识别到账。
 * 启动示例: ./monero-wallet-rpc --daemon-address node.moneroworld.com:18089 \
 *           --rpc-bind-port 18082 --rpc-login user:pass --wallet-file your_wallet \
 *           --password your_wallet_pass --trusted-daemon --restricted-rpc
 * 原理: 按实时汇率(CNY)将订单金额换算为XMR并附加唯一尾数, 通过钱包RPC的
 *       get_transfers(in/pool)按金额匹配到账自动发货。
 */
class XmrPlugin extends PaymentBase
{
    const SLUG = 'xmr';
    const COINGECKO_ID = 'monero';
    const RATE_API = 'https://api.coingecko.com/api/v3';

    public function fields()
    {
        return [
            ['key' => 'rpc_url', 'label' => '钱包RPC地址', 'type' => 'text', 'default' => 'http://127.0.0.1:18082/json_rpc', 'desc' => 'monero-wallet-rpc 的 json_rpc 地址, 例如 http://127.0.0.1:18082/json_rpc'],
            ['key' => 'rpc_user', 'label' => 'RPC用户名', 'type' => 'text', 'default' => '', 'desc' => '与 --rpc-login 保持一致; 未设置认证可留空'],
            ['key' => 'rpc_pass', 'label' => 'RPC密码', 'type' => 'password', 'default' => '', 'desc' => '与 --rpc-login 保持一致'],
            ['key' => 'account_index', 'label' => '账户编号', 'type' => 'text', 'default' => '0', 'desc' => '钱包内用于收款的账户(account)编号, 默认0'],
            ['key' => 'xmr_address', 'label' => 'XMR收款地址(展示用)', 'type' => 'text', 'default' => '', 'desc' => '展示给买家的收款地址(4/8开头), 请使用与RPC钱包一致的账户地址'],
            ['key' => 'premium', 'label' => '汇率溢价(%)', 'type' => 'text', 'default' => '1', 'desc' => '对冲XMR价格波动的加收百分比, 建议不低于1'],
            ['key' => 'rate_api', 'label' => '汇率接口(选填)', 'type' => 'text', 'default' => '', 'desc' => '默认 https://api.coingecko.com/api/v3 (CoinGecko, 按CNY计价)'],
        ];
    }

    public function pay(array $order)
    {
        $addr = $this->cfg('xmr_address');
        if ($addr === '') {
            throw new Exception('管理员尚未配置XMR收款地址');
        }
        return [
            'type' => 'qrcode',
            'qr' => $addr,
            'extra' => [
                'tip' => '请向以下XMR地址转入页面显示的精确金额',
                'wallet' => $addr,
                'chain' => 'Monero',
            ],
        ];
    }

    public function notify(array $req)
    {
        return null;
    }

    public function chainUnit()
    {
        return 'XMR';
    }

    public function chainDecimals()
    {
        return 8;
    }

    public function assignAmount(array $order)
    {
        if ((float)$order['expected_amount'] > 0) return $order['expected_amount'];
        if (trim($this->cfg('rpc_url')) === '') throw new Exception('管理员尚未配置钱包RPC地址');
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
        $rpcUrl = trim($this->cfg('rpc_url'));
        if ($rpcUrl === '') return false;
        $account = (int)$this->cfg('account_index', '0');
        // in=已入账, pool=内存池中待确认
        $resp = $this->rpc('get_transfers', ['in' => true, 'pool' => true]);
        if (!is_array($resp)) return false;
        $wantAtomic = str_replace('.', '', number_format((float)$order['expected_amount'], 8, '.', '')) . str_repeat('0', 4);
        $minTs = (int)$order['created_at'] - 120;
        foreach (['in', 'pool'] as $bucket) {
            if (empty($resp[$bucket]) || !is_array($resp[$bucket])) continue;
            foreach ($resp[$bucket] as $t) {
                if (!isset($t['amount'], $t['txid'])) continue;
                if (isset($t['subaddr_index']['account']) && (int)$t['subaddr_index']['account'] !== $account) continue;
                if (bccomp((string)$t['amount'], $wantAtomic) !== 0) continue;
                $ts = isset($t['timestamp']) ? (int)$t['timestamp'] : 0;
                if ($ts > 0 && $bucket === 'in' && $ts < $minTs) continue;
                $txid = (string)$t['txid'];
                if (DB::value('SELECT id FROM orders WHERE txid = ? AND id != ?', [$txid, $order['id']])) continue;
                OrderService::deliver((int)$order['id'], '', $txid);
                add_log('usdt', 'XMR到账: 订单 ' . $order['sn'] . ' 金额 ' . $order['expected_amount'] . ' XMR, 哈希 ' . substr($txid, 0, 24));
                return true;
            }
        }
        return false;
    }

    public function sweep()
    {
        if ($this->cfg('rpc_url') === '') return 0;
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

    /** monero-wallet-rpc json_rpc 调用 */
    protected function rpc($method, $params)
    {
        $url = trim($this->cfg('rpc_url'));
        $user = $this->cfg('rpc_user');
        $pass = $this->cfg('rpc_pass');
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['jsonrpc' => '2.0', 'id' => '0', 'method' => $method, 'params' => $params]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        curl_tls($ch);
        if ($user !== '' || $pass !== '') curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $pass);
        $res = curl_exec($ch);
        curl_close($ch);
        if ($res === false) throw new Exception('钱包RPC连接失败');
        $json = json_decode($res, true);
        if (!is_array($json)) throw new Exception('钱包RPC响应异常');
        if (isset($json['error'])) throw new Exception('钱包RPC错误: ' . (is_string($json['error']) ? $json['error'] : json_encode($json['error'], JSON_UNESCAPED_UNICODE)));
        return isset($json['result']) && is_array($json['result']) ? $json['result'] : [];
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
        $url = $api . '/simple/price?ids=' . self::COINGECKO_ID . '&vs_currencies=cny';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        curl_tls($ch);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        $res = curl_exec($ch);
        curl_close($ch);
        if ($res === false) throw new Exception('汇率服务请求失败');
        $json = json_decode($res, true);
        $r = is_array($json) && isset($json[self::COINGECKO_ID]['cny']) ? (float)$json[self::COINGECKO_ID]['cny'] : 0;
        if ($r <= 0) throw new Exception('汇率服务暂不可用, 请稍后重试');
        setting_set($cacheKey, json_encode(['t' => now(), 'r' => $r]));
        return $r;
    }
}
