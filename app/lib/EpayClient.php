<?php
/**
 * 易支付(epay)协议工具
 * 适用于: 易支付 / 码支付等兼容该协议的聚合平台
 * 提交: gateway/submit.php?pid=..&type=..&out_trade_no=..&notify_url=..&return_url=..&name=..&money=..&sign=md5&sign_type=MD5
 * 签名: 参数按key字典序排序, 拼接 a=b&c=d 后再拼接商户密钥, 取MD5
 */
class EpayClient
{
    /** 生成提交参数(含签名) */
    public static function buildSubmit($gateway, $pid, $key, $type, $order, $notifyUrl, $returnUrl, $sitename)
    {
        $params = [
            'pid' => $pid,
            'type' => $type,
            'out_trade_no' => $order['sn'],
            'notify_url' => $notifyUrl,
            'return_url' => $returnUrl,
            'name' => $order['product_name'],
            'money' => number_format((float)$order['total'], 2, '.', ''),
            'sitename' => $sitename,
        ];
        $params['sign'] = self::sign($params, $key);
        $params['sign_type'] = 'MD5';
        $gateway = rtrim($gateway, '/');
        if (stripos($gateway, 'http') !== 0) $gateway = 'https://' . $gateway;
        return $gateway . '/submit.php?' . http_build_query($params);
    }

    /** 验证异步通知签名(自动剔除系统路由参数, 只校验网关参数) */
    public static function verifyNotify($req, $key)
    {
        if (empty($req['sign']) || empty($req['out_trade_no'])) return false;
        $sign = $req['sign'];
        $params = $req;
        unset($params['sign'], $params['sign_type'], $params['_csrf'], $params['s'], $params['plugin']);
        ksort($params);
        $pairs = [];
        foreach ($params as $k => $v) {
            if ($v === '' || is_array($v)) continue;
            $pairs[] = $k . '=' . $v;
        }
        $expect = md5(implode('&', $pairs) . $key);
        return strtolower($expect) === strtolower($sign);
    }

    /** 计算签名(自动排除sign/sign_type, 对已含签名的数组重算幂等) */
    public static function sign($params, $key)
    {
        unset($params['sign'], $params['sign_type']);
        ksort($params);
        $pairs = [];
        foreach ($params as $k => $v) {
            if ($v === '' || is_array($v)) continue;
            $pairs[] = $k . '=' . $v;
        }
        return md5(implode('&', $pairs) . $key);
    }
}
