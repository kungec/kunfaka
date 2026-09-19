<?php
/**
 * 国际信用卡支付插件(Visa/Mastercard)
 * 按Stripe官方文档对接:
 *   创建会话  POST {api}/v1/checkout/sessions  (Bearer sk_.., 表单参数)
 *   Webhook   Stripe-Signature: t=时间戳,v1=HMAC_SHA256("{t}.{原始body}", whsec_..), 容差300秒
 *   回跳核实  GET  {api}/v1/checkout/sessions/{id}  (payment_status=paid即到账)
 * Visa/万事达卡由Stripe托管收银页自动支持, 无需区分卡种渠道。
 */
class VisaMasterPlugin extends PaymentBase
{
    public function fields()
    {
        return [
            ['key' => 'secret_key', 'label' => 'Stripe密钥(sk_..)', 'type' => 'text', 'default' => '', 'desc' => 'Stripe开发者面板中的Secret Key, 以sk_test_/sk_live_开头'],
            ['key' => 'webhook_secret', 'label' => 'Webhook签名密钥(whsec_..)', 'type' => 'text', 'default' => '', 'desc' => 'Webhook端点签名密钥; 填写后启用异步通知验签发货。Webhook地址: 你的域名/index.php?s=/pay/notify&plugin=visa_master, 订阅事件 checkout.session.completed'],
            ['key' => 'currency', 'label' => '结算币种', 'type' => 'select', 'options' => ['usd' => 'USD 美元', 'eur' => 'EUR 欧元', 'hkd' => 'HKD 港币', 'sgd' => 'SGD 新币', 'jpy' => 'JPY 日元'], 'default' => 'usd', 'desc' => 'Stripe结算币种(ISO小写); 商品价格会按同数值以该币种收取, 请自行按汇率定价'],
            ['key' => 'api_base', 'label' => 'API地址(保持默认)', 'type' => 'text', 'default' => 'https://api.stripe.com', 'desc' => '官方接口地址, 仅测试环境需要修改'],
        ];
    }

    public function pay(array $order)
    {
        $key = trim($this->cfg('secret_key'));
        if ($key === '') throw new Exception('未配置Stripe密钥');
        $api = rtrim($this->cfg('api_base', 'https://api.stripe.com'), '/');
        $amount = (int)round(((float)$order['total']) * 100); // 最小货币单位(分)
        if ($amount <= 0) throw new Exception('订单金额无效');
        $params = [
            'mode' => 'payment',
            'success_url' => site_url('index.php?s=/pay/return&sn=' . $order['sn'] . '&session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => site_url('index.php?s=/pay/choose&sn=' . $order['sn']),
            'client_reference_id' => $order['sn'],
            'metadata[sn]' => $order['sn'],
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower($this->cfg('currency', 'usd')),
            'line_items[0][price_data][unit_amount]' => $amount,
            'line_items[0][price_data][product_data][name]' => mb_substr($order['product_name'], 0, 120),
        ];
        [$code, $body] = $this->httpStripe($api . '/v1/checkout/sessions', http_build_query($params), [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        $json = json_decode((string)$body, true);
        if ($code !== 200 || !is_array($json) || empty($json['url'])) {
            $msg = is_array($json) && isset($json['error']['message']) ? $json['error']['message'] : 'HTTP ' . $code;
            throw new Exception('Stripe会话创建失败: ' . $msg);
        }
        return ['type' => 'redirect', 'url' => $json['url']];
    }

    /**
     * Webhook异步通知(读取原始body做签名校验)
     * 事件: checkout.session.completed
     */
    public function notify(array $req)
    {
        $secret = trim($this->cfg('webhook_secret'));
        if ($secret === '') return null;
        $raw = (string)file_get_contents('php://input');
        $sigHeader = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? (string)$_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
        if (!$this->verifyStripeSignature($raw, $sigHeader, $secret)) return null;
        $event = json_decode($raw, true);
        if (!is_array($event) || ($event['type'] ?? '') !== 'checkout.session.completed') return null;
        $session = $event['data']['object'] ?? [];
        $sn = (string)($session['client_reference_id'] ?? ($session['metadata']['sn'] ?? ''));
        if ($sn === '' || ($session['payment_status'] ?? '') !== 'paid') return null;
        // 金额校验(最小货币单位与下单时一致)
        $expected = (int)round($this->orderTotal($sn) * 100);
        if ((int)($session['amount_total'] ?? -1) !== $expected) return null;
        return [
            'sn' => $sn,
            'trade_no' => (string)($session['payment_intent'] ?? ($session['id'] ?? '')),
            'money' => $expected / 100,
        ];
    }

    /**
     * 支付回跳时服务端二次核实(无Webhook也能发货)
     * success_url 模板中 {CHECKOUT_SESSION_ID} 被Stripe替换为真实会话ID
     */
    public function verifyReturn(array $order, array $req)
    {
        $key = trim($this->cfg('secret_key'));
        $sid = trim((string)($req['session_id'] ?? ''));
        // 会话ID形如 cs_test_xxx / cs_live_xxx, 允许字母数字下划线中划线(禁止路径字符)
        if ($key === '' || $sid === '' || !preg_match('/^cs_[A-Za-z0-9_\-]{5,255}$/', $sid)) return null;
        $api = rtrim($this->cfg('api_base', 'https://api.stripe.com'), '/');
        [$code, $body] = $this->httpStripe($api . '/v1/checkout/sessions/' . urlencode($sid), null, [
            'Authorization: Bearer ' . $key,
        ]);
        $json = json_decode((string)$body, true);
        if ($code !== 200 || !is_array($json)) return null;
        // 会话必须属于本订单
        $sn = (string)($json['client_reference_id'] ?? ($json['metadata']['sn'] ?? ''));
        if ($sn !== $order['sn']) return null;
        if (($json['payment_status'] ?? '') !== 'paid') return null;
        $expected = (int)round(((float)$order['total']) * 100);
        if ((int)($json['amount_total'] ?? -1) !== $expected) return null;
        return [
            'sn' => $order['sn'],
            'trade_no' => (string)($json['payment_intent'] ?? $json['id']),
            'money' => $expected / 100,
        ];
    }

    /** 订单应付金额(用于webhook金额比对; webhoook场景无订单数组, 查库) */
    private function orderTotal($sn)
    {
        $row = DB::fetch('SELECT total FROM orders WHERE sn = ?', [$sn]);
        return $row ? (float)$row['total'] : -1;
    }

    /** Stripe专用HTTP: HTTPS强制证书校验(API密钥走此通道), 返回 [状态码, body] */
    private function httpStripe($url, $body = null, $headers = [])
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'KunFaKa-VisaMaster/1.0',
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        if (stripos($url, 'https://') === 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        }
        $res = curl_exec($ch);
        if ($res === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception('HTTP请求失败: ' . $err);
        }
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, $res];
    }

    /**
     * 按官方文档手工验签:
     * signed_payload = "{t}.{raw_body}", 期望签名 = hash_hmac('sha256', payload, whsec)
     * 常量时间比较 + 300秒时间戳容差(防重放)
     */
    private function verifyStripeSignature($rawBody, $sigHeader, $secret)
    {
        if ($rawBody === '' || $sigHeader === '') return false;
        $timestamp = '';
        $signatures = [];
        foreach (explode(',', $sigHeader) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) continue;
            if ($kv[0] === 't') $timestamp = $kv[1];
            if ($kv[0] === 'v1') $signatures[] = $kv[1];
        }
        if ($timestamp === '' || !$signatures) return false;
        if (abs(time() - (int)$timestamp) > 300) return false; // 防重放
        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) return true;
        }
        return false;
    }
}
