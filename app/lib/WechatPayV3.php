<?php
/**
 * 微信支付 V3 API 客户端(纯PHP, 依赖openssl扩展)
 * 支持: Native扫码支付(下单/回调验签/回调报文解密)
 * 配置: 商户号mchid / 公众号或小程序appid / 商户API证书序列号 / 商户APIv3私钥 / APIv3密钥
 */
class WechatPayV3
{
    public $mchId;
    public $appId;
    public $serialNo;     // 商户API证书序列号
    public $privateKey;   // 商户API私钥(apiclient_key.pem内容)
    public $apiV3Key;     // APIv3密钥(32位)
    public $baseUrl = 'https://api.mch.weixin.qq.com';

    public function __construct($mchId, $appId, $serialNo, $privateKey, $apiV3Key)
    {
        $this->mchId = $mchId;
        $this->appId = $appId;
        $this->serialNo = $serialNo;
        $this->privateKey = AlipayClient::normalizeKey($privateKey, 'PRIVATE KEY');
        $this->apiV3Key = $apiV3Key;
    }

    /** 请求签名串: METHOD\nURL\n时间戳\n随机串\n报文\n */
    protected function authHeader($method, $pathWithQuery, $body = '')
    {
        $timestamp = (string)time();
        $nonce = strtoupper(md5(uniqid(mt_rand(), true)));
        $message = strtoupper($method) . "\n" . $pathWithQuery . "\n" . $timestamp . "\n" . $nonce . "\n" . $body . "\n";
        $pkey = openssl_pkey_get_private($this->privateKey);
        if (!$pkey) throw new Exception('商户API私钥无效, 请检查 apiclient_key.pem 内容');
        $sig = '';
        if (!openssl_sign($message, $sig, $pkey, OPENSSL_ALGO_SHA256)) {
            throw new Exception('微信支付签名失败');
        }
        return 'WECHATPAY2-SHA256-RSA2048 mchid="' . $this->mchId . '",nonce_str="' . $nonce
            . '",timestamp="' . $timestamp . '",serial_no="' . $this->serialNo
            . '",signature="' . base64_encode($sig) . '"';
    }

    /** Native下单, 返回 code_url */
    public function nativePay($outTradeNo, $totalFen, $description, $notifyUrl)
    {
        $path = '/v3/pay/transactions/native';
        $body = json_encode([
            'mchid' => $this->mchId,
            'appid' => $this->appId,
            'description' => $description,
            'out_trade_no' => $outTradeNo,
            'notify_url' => $notifyUrl,
            'amount' => ['total' => (int)$totalFen, 'currency' => 'CNY'],
        ], JSON_UNESCAPED_UNICODE);
        $auth = $this->authHeader('POST', $path, $body);
        $res = $this->request($this->baseUrl . $path, $body, [
            'Accept: application/json',
            'Authorization: ' . $auth,
        ]);
        $json = json_decode($res, true);
        if (!$json) throw new Exception('微信支付响应异常: ' . substr($res, 0, 200));
        if (!empty($json['code'])) throw new Exception('微信下单失败[' . $json['code'] . ']: ' . (isset($json['message']) ? $json['message'] : ''));
        if (empty($json['code_url'])) throw new Exception('微信未返回支付二维码');
        return $json['code_url'];
    }

    /** 下载平台证书(解密后), 结果缓存到settings */
    protected function platformCerts()
    {
        $cached = setting('wechat_certs_cache', '');
        if ($cached) {
            $arr = json_decode($cached, true);
            if (is_array($arr) && !empty($arr['expire_at']) && $arr['expire_at'] > now()) {
                return $arr['certs'];
            }
        }
        $path = '/v3/certificates';
        $auth = $this->authHeader('GET', $path);
        $res = $this->request($this->baseUrl . $path, null, [
            'Accept: application/json',
            'Authorization: ' . $auth,
        ]);
        $json = json_decode($res, true);
        if (!$json || empty($json['data'])) throw new Exception('获取微信平台证书失败: ' . substr($res, 0, 200));
        $certs = [];
        $minExpire = PHP_INT_MAX;
        foreach ($json['data'] as $item) {
            $cert = $this->aesGcmDecrypt($item['encrypt_certificate']['ciphertext'], $item['encrypt_certificate']['nonce'], $item['encrypt_certificate']['associated_data']);
            $certs[$item['serial_no']] = "-----BEGIN CERTIFICATE-----\n" . chunk_split($cert, 64, "\n") . "-----END CERTIFICATE-----";
            $info = openssl_x509_parse($cert);
            if ($info && isset($info['validTo_time_t']) && $info['validTo_time_t'] < $minExpire) $minExpire = $info['validTo_time_t'];
        }
        $expire = min($minExpire - 3600, now() + 86400 * 7);
        setting_set('wechat_certs_cache', json_encode(['certs' => $certs, 'expire_at' => $expire]));
        return $certs;
    }

    /**
     * 校验回调签名并解密报文
     * $headers: Wechatpay-Timestamp/Nonce/Signature/Serial
     * 返回解密后的 resource 数组
     */
    public function verifyNotify($headers, $body)
    {
        $timestamp = isset($headers['Wechatpay-Timestamp']) ? $headers['Wechatpay-Timestamp'] : (isset($headers['wechatpay-timestamp']) ? $headers['wechatpay-timestamp'] : '');
        $nonce = isset($headers['Wechatpay-Nonce']) ? $headers['Wechatpay-Nonce'] : (isset($headers['wechatpay-nonce']) ? $headers['wechatpay-nonce'] : '');
        $signature = isset($headers['Wechatpay-Signature']) ? $headers['Wechatpay-Signature'] : (isset($headers['wechatpay-signature']) ? $headers['wechatpay-signature'] : '');
        $serial = isset($headers['Wechatpay-Serial']) ? $headers['Wechatpay-Serial'] : (isset($headers['wechatpay-serial']) ? $headers['wechatpay-serial'] : '');
        if (!$timestamp || !$nonce || !$signature || !$serial) return null;

        $message = $timestamp . "\n" . $nonce . "\n" . $body . "\n";
        $certs = $this->platformCerts();
        if (empty($certs[$serial])) return null;
        $pub = openssl_pkey_get_public($certs[$serial]);
        if (!$pub) return null;
        $ok = openssl_verify($message, base64_decode($signature), $pub, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) return null;

        $json = json_decode($body, true);
        if (!$json || empty($json['resource'])) return null;
        $res = $json['resource'];
        $plain = $this->aesGcmDecrypt($res['ciphertext'], $res['nonce'], isset($res['associated_data']) ? $res['associated_data'] : '');
        return json_decode($plain, true);
    }

    /** AES-256-GCM 解密 */
    protected function aesGcmDecrypt($cipherB64, $nonce, $aad)
    {
        $cipher = base64_decode($cipherB64);
        if ($cipher === false || strlen($cipher) < 16) throw new Exception('解密失败: 密文无效');
        $tag = substr($cipher, -16);
        $text = substr($cipher, 0, -16);
        $plain = openssl_decrypt($text, 'aes-256-gcm', $this->apiV3Key, OPENSSL_RAW_DATA, $nonce, $tag, $aad);
        if ($plain === false) throw new Exception('APIv3密钥解密失败, 请检查APIv3密钥是否正确');
        return $plain;
    }

    protected function request($url, $body, $headers)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_tls($ch);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $res = curl_exec($ch);
        if ($res === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception('微信支付请求失败: ' . $err);
        }
        curl_close($ch);
        return $res;
    }
}
