<?php
/**
 * USDT(EVM系)免挂支付基类
 * ERC20 / BEP20 / POLYGON 三条链的 USDT 转账检测逻辑完全同构
 * (Etherscan系浏览器API: module=account&action=tokentx), 差异仅在:
 * 扫链API地址 / USDT合约地址 / 代币小数位。
 * 子类需定义: $slug/$netName/$scanApi/$contract/$tokenDecimals
 * 依赖: TronService::rateCny 提供USDT对人民币汇率(带5分钟缓存)
 */
abstract class UsdtEvmBasePlugin extends PaymentBase
{
    protected $slug = '';           // 插件标识
    protected $netName = '';        // 链名(展示): ERC20 / BEP20 / POLYGON
    protected $scanApi = '';        // 浏览器API根地址(可被配置 api_base 覆盖)
    protected $contract = '';       // 该链USDT合约地址
    protected $tokenDecimals = 6;   // 链上USDT小数位

    /** 插件配置字段(公共部分; 子类如需差异文案可覆盖) */
    public function fields()
    {
        $scanName = $this->scanName();
        return [
            ['key' => 'wallet_address', 'label' => '收款地址(' . $this->netName . ')', 'type' => 'text', 'default' => '', 'desc' => '该链上用于收取USDT的钱包地址, 仅识别向此地址的直接转账'],
            ['key' => 'scan_key', 'label' => $scanName . ' API密钥', 'type' => 'text', 'default' => '', 'desc' => '在 ' . $scanName . ' 免费注册创建(免费版额度完全够用)'],
            ['key' => 'premium', 'label' => '汇率溢价(%)', 'type' => 'text', 'default' => '1', 'desc' => '对冲USDT场外价格波动的加收百分比, 建议不低于1'],
            ['key' => 'rate', 'label' => '固定汇率(选填)', 'type' => 'text', 'default' => '', 'desc' => '1 USDT = 多少元人民币, 如 7.20; 留空则自动使用实时汇率(每5分钟更新)'],
            ['key' => 'rate_api', 'label' => '汇率接口(选填)', 'type' => 'text', 'default' => '', 'desc' => '默认 https://api.coingecko.com/api/v3; 被墙或需代理时可替换为兼容接口'],
        ];
    }

    protected function scanName()
    {
        $map = ['https://api.etherscan.io' => 'Etherscan', 'https://api.bscscan.com' => 'BscScan', 'https://api.polygonscan.com' => 'Polygonscan'];
        return isset($map[$this->scanApi]) ? $map[$this->scanApi] : '区块浏览器';
    }

    public function chainUnit()
    {
        return 'USDT-' . $this->netName;
    }

    public function chainDecimals()
    {
        return 6;
    }

    public function pay(array $order)
    {
        $wallet = trim($this->cfg('wallet_address'));
        if ($wallet === '') {
            throw new Exception('管理员尚未配置' . $this->netName . '收款地址');
        }
        return [
            'type' => 'qrcode',
            'qr' => $wallet,
            'extra' => [
                'tip' => '请向以下' . $this->netName . '地址转入页面显示的精确金额的 USDT',
                'wallet' => $wallet,
                'chain' => $this->netName,
            ],
        ];
    }

    public function notify(array $req)
    {
        return null;
    }

    public function assignAmount(array $order)
    {
        if ((float)$order['expected_amount'] > 0) return $order['expected_amount'];
        $rate = TronService::rateCny($this->config);
        $premium = (float)$this->cfg('premium', '1');
        $base = round((float)$order['total'] / $rate * (1 + $premium / 100), 2);
        if ($base <= 0) throw new Exception('金额计算异常');
        // 尾数1~9999 * 0.000001 USDT(约合几厘钱), 用于区分并发订单
        for ($i = 0; $i < 300; $i++) {
            $tail = rand(1, 9999) / 1000000;
            $amount = number_format($base + $tail, 6, '.', '');
            $exists = DB::value(
                'SELECT id FROM orders WHERE status = 0 AND pay_plugin = ? AND expected_amount = ? AND id != ?',
                [$this->slug, $amount, $order['id']]
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
        $txs = $this->transfers($addr);
        if (!$txs) return false;
        // 期望金额换算为链上最小单位(USDT展示6位小数, 按代币实际小数位补零)
        $wantRaw = str_replace('.', '', number_format((float)$order['expected_amount'], 6, '.', ''));
        $pad = $this->tokenDecimals - 6;
        if ($pad > 0) $wantRaw .= str_repeat('0', $pad);
        $minTs = (int)$order['created_at'] - 120;
        foreach ($txs as $tx) {
            if (!isset($tx['hash'], $tx['value'], $tx['to'])) continue;
            if (strcasecmp((string)$tx['to'], $addr) !== 0) continue;
            if (bccomp((string)$tx['value'], $wantRaw) !== 0) continue;
            $ts = isset($tx['timeStamp']) ? (int)$tx['timeStamp'] : 0;
            if ($ts > 0 && $ts < $minTs) continue;
            $txid = (string)$tx['hash'];
            if (DB::value('SELECT id FROM orders WHERE txid = ? AND id != ?', [$txid, $order['id']])) continue;
            OrderService::deliver((int)$order['id'], '', $txid);
            add_log('usdt', 'USDT(' . $this->netName . ')到账: 订单 ' . $order['sn'] . ' 金额 ' . $order['expected_amount'] . ' USDT, 哈希 ' . substr($txid, 0, 24));
            return true;
        }
        return false;
    }

    public function sweep()
    {
        if (trim($this->cfg('wallet_address')) === '') return 0;
        $orders = DB::fetchAll(
            'SELECT * FROM orders WHERE status = 0 AND pay_plugin = ? AND expected_amount > 0 AND expired_at > ? ORDER BY created_at ASC LIMIT 30',
            [$this->slug, now()]
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

    /** 拉取地址的USDT代币转账记录(倒序50条) */
    protected function transfers($addr)
    {
        $api = rtrim($this->cfg('api_base', $this->scanApi), '/');
        $url = $api . '/api?module=account&action=tokentx&contractaddress=' . urlencode($this->contract)
            . '&address=' . urlencode($addr) . '&page=1&offset=50&sort=desc&apikey=' . urlencode(trim($this->cfg('scan_key')));
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
        $json = json_decode($res, true);
        return (is_array($json) && isset($json['result']) && is_array($json['result'])) ? $json['result'] : [];
    }
}
