<?php
/**
 * 支付宝 RSA2 客户端(纯PHP实现, 依赖openssl扩展)
 * 支持: 电脑网站支付(page.pay) / 当面付扫码(precreate) / 异步通知验签
 * 密钥格式: PKCS8 私钥(支付宝开放平台应用私钥) + 支付宝公钥
 */
class AlipayClient
{
    public $gateway = 'https://openapi.alipay.com/gateway.php';
    public $appId;
    public $privateKey;      // 应用私钥(含-----BEGIN头或纯文本)
    public $alipayPublicKey; // 支付宝公钥

    public function __construct($appId, $privateKey, $alipayPublicKey, $gateway = '')
    {
        $this->appId = $appId;
        $this->privateKey = self::normalizeKey($privateKey, 'PRIVATE KEY');
        $this->alipayPublicKey = self::normalizeKey($alipayPublicKey, 'PUBLIC KEY');
        if ($gateway) $this->gateway = $gateway;
    }

    /** 密钥文本标准化为PEM */
    public static function normalizeKey($key, $type)
    {
        $key = trim((string)$key);
        if (strpos($key, '-----') !== false) return $key;
        $key = str_replace(["\r", "\n", ' '], ['', '', ''], $key);
        $lines = str_split($key, 64);
        return "-----BEGIN {$type}-----\n" . implode("\n", $lines) . "\n-----END {$type}-----";
    }

    /** RSA2签名(参数按key排序 k=v 拼接后签名) */
    public function sign($params)
    {
        unset($params['sign'], $params['sign_type']);
        ksort($params);
        $pairs = [];
        foreach ($params as $k => $v) {
            if ($v === '' || $v === null) continue;
            $pairs[] = $k . '=' . $v;
        }
        $data = implode('&', $pairs);
        $pkey = openssl_pkey_get_private($this->privateKey);
        if (!$pkey) throw new Exception('应用私钥无效, 请检查格式');
        $sig = '';
        if (!openssl_sign($data, $sig, $pkey, OPENSSL_ALGO_SHA256)) {
            throw new Exception('RSA2签名失败');
        }
        return base64_encode($sig);
    }

    /** 验证支付宝通知/响应签名 */
    public function verify($params)
    {
        if (empty($params['sign'])) return false;
        $sign = $params['sign'];
        unset($params['sign'], $params['sign_type']);
        ksort($params);
        $pairs = [];
        foreach ($params as $k => $v) {
            if ($v === '' || $v === null) continue;
            $pairs[] = $k . '=' . $v;
        }
        $data = implode('&', $pairs);
        $pub = openssl_pkey_get_public($this->alipayPublicKey);
        if (!$pub) return false;
        return (bool)openssl_verify($data, base64_decode($sign), $pub, OPENSSL_ALGO_SHA256);
    }

    /** 公共系统参数 */
    protected function sysParams($method, $bizContent, $notifyUrl = '', $returnUrl = '')
    {
        $params = [
            'app_id' => $this->appId,
            'method' => $method,
            'format' => 'JSON',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'biz_content' => json_encode($bizContent, JSON_UNESCAPED_UNICODE),
        ];
        if ($notifyUrl) $params['notify_url'] = $notifyUrl;
        if ($returnUrl) $params['return_url'] = $returnUrl;
        $params['sign'] = $this->sign($params);
        return $params;
    }

    /** 电脑网站支付: 返回跳转URL */
    public function pagePay($outTradeNo, $totalAmount, $subject, $notifyUrl, $returnUrl)
    {
        $params = $this->sysParams('alipay.trade.page.pay', [
            'out_trade_no' => $outTradeNo,
            'product_code' => 'FAST_PAGE_TRADE',
            'total_amount' => $totalAmount,
            'subject' => $subject,
        ], $notifyUrl, $returnUrl);
        return $this->gateway . '?' . $this->buildQuery($params);
    }

    /** 手机网站支付: 返回跳转URL */
    public function wapPay($outTradeNo, $totalAmount, $subject, $notifyUrl, $returnUrl)
    {
        $params = $this->sysParams('alipay.trade.wap.pay', [
            'out_trade_no' => $outTradeNo,
            'product_code' => 'QUICK_WAP_WAY',
            'total_amount' => $totalAmount,
            'subject' => $subject,
        ], $notifyUrl, $returnUrl);
        return $this->gateway . '?' . $this->buildQuery($params);
    }

    /** 当面付预下单: 返回二维码内容 */
    public function precreate($outTradeNo, $totalAmount, $subject, $notifyUrl)
    {
        $params = $this->sysParams('alipay.trade.precreate', [
            'out_trade_no' => $outTradeNo,
            'total_amount' => $totalAmount,
            'subject' => $subject,
        ], $notifyUrl);
        $res = $this->httpPost($this->gateway, $this->buildQuery($params));
        $json = json_decode($res, true);
        if (!$json) throw new Exception('支付宝接口响应异常: ' . substr($res, 0, 200));
        $resp = isset($json['alipay_trade_precreate_response']) ? $json['alipay_trade_precreate_response'] : null;
        if (!$resp || (isset($resp['code']) && $resp['code'] != '10000')) {
            $msg = isset($resp['sub_msg']) ? $resp['sub_msg'] : (isset($resp['msg']) ? $resp['msg'] : '未知错误');
            throw new Exception('支付宝下单失败: ' . $msg);
        }
        if (empty($resp['qr_code'])) throw new Exception('支付宝未返回二维码');
        return $resp['qr_code'];
    }

    /** 查询交易状态(当面付主动查单) */
    public function queryTrade($outTradeNo)
    {
        $params = $this->sysParams('alipay.trade.query', ['out_trade_no' => $outTradeNo]);
        $res = $this->httpPost($this->gateway, $this->buildQuery($params));
        $json = json_decode($res, true);
        if (!$json || empty($json['alipay_trade_query_response'])) return null;
        $resp = $json['alipay_trade_query_response'];
        if (isset($resp['code']) && $resp['code'] == '10000' && isset($resp['trade_status']) && $resp['trade_status'] === 'TRADE_SUCCESS') {
            return isset($resp['trade_no']) ? $resp['trade_no'] : '';
        }
        return null;
    }

    protected function buildQuery($params)
    {
        $pairs = [];
        foreach ($params as $k => $v) $pairs[] = $k . '=' . urlencode($v);
        return implode('&', $pairs);
    }
}
