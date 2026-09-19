<?php
/**
 * 坤发卡 会员授权中心
 * - 免费版: 可使用全部免费插件/主题
 * - 专业版(99元): 应用商店所有插件/主题免费下载, 含USDT免挂支付等付费插件
 * 支持两种开通方式: 官方会员中心在线开通 / 离线授权码激活
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

    /** 是否已登录官方账号(免费注册会员) */
    public static function isAuthed()
    {
        return setting('auth_token') !== '' && (int)setting('auth_expires', '0') > now();
    }

    /** 会员信息数组 */
    public static function info()
    {
        return [
            'is_pro' => self::isPro(),
            'is_authed' => self::isAuthed(),
            'license_key' => setting('license_key'),
            'license_type' => setting('license_type', 'free'),
            'license_expires' => (int)setting('license_expires', '0'),
            'auth_user' => setting('auth_user'),
        ];
    }

    /**
     * 激活授权码(优先在线校验, 网络不通时离线校验)
     * 授权码格式: YF99-XXXXX-XXXXX-XXXXX
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

    /** 登录官方会员账号(免费注册即会员, 可下商店免费应用; 专业版可下全部) */
    public static function login($user, $pass)
    {
        $api = setting('official_api');
        if (!$api) throw new Exception('未配置官方市场地址');
        $res = self::post($api . '/api/login', ['username' => $user, 'password' => $pass]);
        if (!is_array($res) || !isset($res['code']) || $res['code'] !== 0) {
            throw new Exception(isset($res['msg']) ? $res['msg'] : '登录失败, 请检查官方市场地址或稍后再试');
        }
        $d = $res['data'];
        setting_set('auth_token', $d['token']);
        setting_set('auth_user', $user);
        setting_set('auth_expires', (string)(now() + 86400 * 7));
        if (isset($d['membership'])) {
            setting_set('license_type', $d['membership'] === 'pro' ? 'pro' : 'free');
            setting_set('license_expires', (string)(int)(isset($d['expires']) ? $d['expires'] : 0));
        }
    }

    /** 退出官方账号登录 */
    public static function logout()
    {
        setting_set('auth_token', '');
        setting_set('auth_user', '');
        setting_set('auth_expires', '0');
    }

    protected static function post($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $res = curl_exec($ch);
        curl_close($ch);
        if ($res === false) throw new Exception('无法连接官方服务器');
        return json_decode($res, true);
    }
}
