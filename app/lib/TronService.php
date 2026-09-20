<?php
/**
 * TRON 链 USDT(TRC20) 免挂机检测服务
 * 原理: 每笔订单分配唯一金额(尾数区分), 轮询 TronGrid 公开API 查询该地址的
 *       TRC20 转入记录, 金额与订单唯一金额完全一致且在订单有效期内的即判定支付成功。
 *       无需运行节点、无需钱包软件, 只需填写 TRC20 收款地址。
 */
class TronService
{
    public static function apiBase()
    {
        return rtrim(setting('usdt_api', 'https://api.trongrid.io'), '/');
    }

    public static function contract()
    {
        return setting('usdt_contract', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');
    }

    /** 查询地址转入的USDT转账记录(minTimestamp秒), 返回 [['amount'=>'12.340000','txid'=>'..','ts'=>秒],..] */
    public static function getTransfers($address, $minTimestamp = 0)
    {
        $url = self::apiBase() . '/v1/accounts/' . urlencode($address) . '/transactions/trc20'
            . '?only_to=true&limit=50&contract_address=' . urlencode(self::contract())
            . '&min_timestamp=' . ($minTimestamp * 1000);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_tls($ch);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        $res = curl_exec($ch);
        curl_close($ch);
        if ($res === false) throw new Exception('TRON API请求失败');
        $json = json_decode($res, true);
        if (!is_array($json)) throw new Exception('TRON API响应异常');
        $list = [];
        if (!empty($json['data']) && is_array($json['data'])) {
            foreach ($json['data'] as $t) {
                if (!isset($t['value']) || !isset($t['transaction_id'])) continue;
                $amount = number_format((float)$t['value'] / 1000000, 6, '.', ''); // USDT 6位小数
                $list[] = [
                    'amount' => $amount,
                    'txid' => $t['transaction_id'],
                    'ts' => isset($t['block_timestamp']) ? (int)round($t['block_timestamp'] / 1000) : 0,
                ];
            }
        }
        return $list;
    }

    /**
     * USDT对人民币汇率: 插件配置 rate 固定值优先, 否则拉取实时汇率(CoinGecko, 5分钟缓存)
     */
    public static function rateCny($cfg = [])
    {
        $fixed = isset($cfg['rate']) ? trim((string)$cfg['rate']) : '';
        if ($fixed !== '' && (float)$fixed > 0) return (float)$fixed;
        $cached = setting('usdt_rate_cache', '');
        if ($cached !== '') {
            $a = json_decode($cached, true);
            if (is_array($a) && isset($a['t'], $a['r']) && (int)$a['t'] > now() - 300 && (float)$a['r'] > 0) return (float)$a['r'];
        }
        $api = rtrim((isset($cfg['rate_api']) && $cfg['rate_api'] !== '') ? $cfg['rate_api'] : 'https://api.coingecko.com/api/v3', '/');
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $api . '/simple/price?ids=tether&vs_currencies=cny',
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
        $r = is_array($json) && isset($json['tether']['cny']) ? (float)$json['tether']['cny'] : 0;
        if ($r <= 0) throw new Exception('汇率服务暂不可用, 请稍后重试');
        setting_set('usdt_rate_cache', json_encode(['t' => now(), 'r' => $r]));
        return $r;
    }

    /**
     * 为订单分配唯一USDT金额: 按汇率将人民币金额换算为USDT,
     * 并附加极小的唯一尾数(1~9999 sat级, 最多约几分钱)用于区分并发订单
     */
    public static function assignAmount($order, $cfg = [])
    {
        if ((float)$order['expected_amount'] > 0) return $order['expected_amount'];
        $rate = self::rateCny($cfg);
        $base = round((float)$order['total'] / $rate, 2);
        if ($base <= 0) throw new Exception('金额计算异常');
        for ($i = 0; $i < 300; $i++) {
            $tail = rand(1, 9999) / 1000000;
            $amount = number_format($base + $tail, 6, '.', '');
            $exists = DB::value(
                'SELECT id FROM orders WHERE status = 0 AND pay_plugin = ? AND expected_amount = ? AND id != ?',
                ['usdt_trc20', $amount, $order['id']]
            );
            if (!$exists) {
                DB::update('orders', ['expected_amount' => $amount], 'id = ?', [$order['id']]);
                return $amount;
            }
        }
        throw new Exception('唯一金额分配失败, 请稍后重试');
    }

    /**
     * 检测单笔订单是否到账
     */
    public static function checkOrder($order, $wallet)
    {
        if ((float)$order['expected_amount'] <= 0) return false;
        $minTs = (int)$order['created_at'] - 120;
        $transfers = self::getTransfers($wallet, $minTs);
        foreach ($transfers as $t) {
            if (bccomp($t['amount'], $order['expected_amount'], 6) !== 0) continue;
            if ($t['ts'] > 0 && $t['ts'] < $minTs) continue;
            $used = DB::value('SELECT id FROM orders WHERE txid = ? AND id != ?', [$t['txid'], $order['id']]);
            if ($used) continue;
            OrderService::deliver((int)$order['id'], '', $t['txid']);
            add_log('usdt', 'USDT到账: 订单 ' . $order['sn'] . ' 金额 ' . $t['amount'] . ' USDT, 哈希 ' . substr($t['txid'], 0, 24));
            return true;
        }
        return false;
    }

    /** 轮询所有待支付的USDT订单, 返回支付成功笔数 */
    public static function sweep()
    {
        $meta = Plugin::meta('payment', 'usdt_trc20');
        if (!$meta) return 0;
        $row = DB::fetch("SELECT enabled, config FROM apps WHERE name = 'usdt_trc20'");
        if (!$row || (int)$row['enabled'] !== 1) return 0;
        $config = $row['config'] ? (array)json_decode($row['config'], true) : [];
        $wallet = isset($config['wallet_address']) ? trim($config['wallet_address']) : '';
        if ($wallet === '') return 0;

        $orders = DB::fetchAll(
            'SELECT * FROM orders WHERE status = 0 AND pay_plugin = ? AND expected_amount > 0 AND expired_at > ? ORDER BY created_at ASC LIMIT 30',
            ['usdt_trc20', now()]
        );
        $paid = 0;
        foreach ($orders as $order) {
            try {
                if (self::checkOrder($order, $wallet)) $paid++;
            } catch (Exception $ex) {
                // 单笔失败不影响其他
            }
        }
        return $paid;
    }
}
