<?php
/**
 * 坤发卡 授权中心
 * - 免费版: 可使用全部免费插件/主题
 * - 专业版: 应用商店所有插件/主题免费下载, 含USDT免挂支付等付费插件
 * 开通方式: 官方主控在线购买(邮箱收码) / 离线授权码激活, 一码仅绑定一个站点
 */
class License
{
    const OFFLINE_SECRET = 'YunFaKa#99pro#2024#secret';

    /** 是否专业版 */
    public static function isPro()
    {
        if (setting('license_type') !== 'pro') return false;
        $expires = (int)setting('license_expires', '0');
        return $expires === 0 || $expires > now();
    }

    /** 授权信息数组 */
    public static function info()
    {
        return [
            'is_pro' => self::isPro(),
            'license_key' => setting('license_key'),
            'license_type' => setting('license_type', 'free'),
            'license_expires' => (int)setting('license_expires', '0'),
        ];
    }

    /**
     * 激活授权码(优先在线校验并绑定本站域名, 网络不通时离线校验)
     * 授权码格式: YF99-XXXXX-XXXXX-XXXXX, 一码仅绑定一个站点
     */
    public static function activate($key)
    {
        $key = strtoupper(trim($key));
        if (!preg_match('/^YF99-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}$/', $key)) {
            throw new Exception('授权码格式不正确');
        }
        $api = setting('official_api');
        if ($api) {
            try {
                $res = self::post($api . '/api/activate', ['key' => $key, 'domain' => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '']);
                if (is_array($res) && isset($res['code']) && $res['code'] === 0) {
                    setting_set('license_key', $key);
                    setting_set('license_type', 'pro');
                    setting_set('license_expires', (string)(int)$res['expires']);
                    return;
                }
            } catch (Exception $ex) {
                // 网络失败转离线校验
            }
        }
        // 离线校验: 末段为前两段的HMAC校验值
        $parts = explode('-', $key);
        $body = $parts[1] . $parts[2];
        $sum = strtoupper(substr(hash_hmac('md5', $body, self::OFFLINE_SECRET), 0, 5));
        if ($sum !== $parts[3]) {
            throw new Exception('授权码无效(校验失败)');
        }
        setting_set('license_key', $key);
        setting_set('license_type', 'pro');
        setting_set('license_expires', '0');
    }

    protected static function post($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $res = curl_exec($ch);
        curl_close($ch);
        if ($res === false) throw new Exception('无法连接官方服务器');
        return json_decode($res, true);
    }
}
