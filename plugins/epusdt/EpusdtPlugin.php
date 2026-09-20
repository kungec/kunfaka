<?php
/**
 * EPUSDT 自建USDT支付网关插件
 * 对接 epusdt.com GMPay API(v2.0.0+, HMAC-SHA256签名):
 *   下单: POST /payments/gmpay/v1/order/create-transaction (form-urlencoded)
 *   签名: 非空参数按ASCII键名升序拼 key=value& 串(排除signature), 以secret_key为key的HMAC-SHA256, 64位小写hex
 *   回调: POST JSON 到 notify_url, 字段含 status(2=成功)/amount(法币)/block_transaction_id/signature, 验签规则同下单
 *   应答: HTTP 200 + ok/success
 */
class EpusdtPlugin extends PaymentBase
{
    public function fields()
    {
        return [
            ['key' => 'api_base', 'label' => '网关地址', 'type' => 'text', 'default' => '', 'desc' => '你部署的 EPUSDT 网关地址, 例如 https://epusdt.example.com'],
            ['key' => 'pid', 'label' => '商户PID', 'type' => 'text', 'default' => '', 'desc' => 'EPUSDT 管理面板中创建的商户PID'],
            ['key' => 'secret_key', 'label' => '商户密钥(secret_key)', 'type' => 'text', 'default' => '', 'desc' => '与PID匹配且已启用的 api_keys 密钥'],
            ['key' => 'currency', 'label' => '法币币种', 'type' => 'text', 'default' => 'cny', 'desc' => '订单计价法币, 默认 cny, 由网关按汇率自动折算USDT'],
            ['key' => 'token', 'label' => '指定代币(可选)', 'type' => 'text', 'default' => '', 'desc' => '如 usdt / trx / usdc; 与网络须同时填写或同时留空, 留空则买家在收银台自选'],
            ['key' => 'network', 'label' => '指定网络(可选)', 'type' => 'text', 'default' => '', 'desc' => '如 tron / solana / ethereum / bsc / polygon'],
        ];
    }

    public function pay(array $order)
    {
        $base = rtrim($this->cfg('api_base'), '/');
        if ($base === '' || $this->cfg('pid') === '' || $this->cfg('secret_key') === '') {
            throw new Exception('EPUSDT 网关未配置完整(地址/PID/密钥)');
        }
        $params = [
            'pid' => $this->cfg('pid'),
            'order_id' => $order['sn'],
            'currency' => $this->cfg('currency', 'cny'),
            'amount' => $order['total'],
            'notify_url' => $this->notifyUrl(),
            'redirect_url' => $this->returnUrl($order['sn']),
            'name' => mb_substr(setting('site_name', '坤发卡') . '·' . $order['product_name'], 0, 60),
        ];
        $token = strtolower(trim($this->cfg('token')));
        $network = strtolower(trim($this->cfg('network')));
        if ($token !== '' && $network !== '') {
            $params['token'] = $token;
            $params['network'] = $network;
        }
        $params['signature'] = self::sign($params, $this->cfg('secret_key'));

        $res = $this->httpPost($base . '/payments/gmpay/v1/order/create-transaction', $params);
        $json = json_decode((string)$res, true);
        if (!is_array($json) || (int)($json['status_code'] ?? 0) !== 200 || empty($json['data']['payment_url'])) {
            $msg = is_array($json) && isset($json['message']) ? $json['message'] : '网关无响应';
            throw new Exception('EPUSDT 下单失败: ' . $msg);
        }
        return ['type' => 'redirect', 'url' => $json['data']['payment_url']];
    }

    public function notify(array $req)
    {
        // EPUSDT 异步回调为 POST JSON, 从原始请求体解析
        $raw = file_get_contents('php://input');
        $data = json_decode((string)$raw, true);
        if (!is_array($data) || !$data) $data = $req;
        if ((int)($data['status'] ?? 0) !== 2) return null; // 目前仅 status=2(支付成功)才会回调
        if (!self::verify($data, $this->cfg('secret_key'))) return null;
        if (!isset($data['order_id']) || !is_numeric($data['amount'] ?? null)) return null;
        return [
            'sn' => (string)$data['order_id'],
            'trade_no' => (string)($data['block_transaction_id'] ?? $data['trade_id'] ?? ''),
            'money' => (float)$data['amount'],
        ];
    }

    /** GMPay签名: 非空参数按ASCII键名升序拼 k=v& 串(排除signature), HMAC-SHA256(secret_key) 小写hex */
    public static function sign(array $params, $secretKey)
    {
        unset($params['signature']);
        $pairs = [];
        ksort($params, SORT_STRING);
        foreach ($params as $k => $v) {
            if ($v === '' || $v === null) continue;
            $pairs[] = $k . '=' . $v;
        }
        return hash_hmac('sha256', implode('&', $pairs), (string)$secretKey);
    }

    /** 回调验签(排除signature后重算比对) */
    public static function verify(array $data, $secretKey)
    {
        if (!isset($data['signature']) || $data['signature'] === '') return false;
        return hash_equals(self::sign($data, $secretKey), (string)$data['signature']);
    }

    private function httpPost($url, array $params)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        curl_tls($ch);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($res === false) throw new Exception('EPUSDT 网关请求失败: ' . $err);
        return $res;
    }
}
