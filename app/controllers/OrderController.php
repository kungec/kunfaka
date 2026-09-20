<?php
/**
 * 前台: 订单查询
 * 订单号(高熵随机)本身即访问凭证; 凭联系方式查询必须先通过邮箱验证码验证,
 * 防止"知道他人邮箱即可拉取其已购卡密"。邮件功能未开启或非邮箱联系方式时,
 * 仅展示不含订单号/卡密的脱敏状态概览。
 */
class OrderController
{
    const OQ_TTL = 1800;   // 验证通过后会话放行时长(30分钟)

    public function actionQuery()
    {
        $kw = trim(arr_get($_REQUEST, 'kw'));
        $otpErr = '';
        $otpOk = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_check();
            $kw = trim(arr_get($_POST, 'kw'));
            $act = arr_get($_POST, 'oq_action');
            if ($act === 'send') {
                $otpErr = self::oqSend($kw);
                if ($otpErr === '') $otpOk = '验证码已发送至该邮箱, 请查收(10分钟内有效)';
            } elseif ($act === 'verify') {
                if (self::oqVerify($kw, trim(arr_get($_POST, 'code')))) {
                    redirect(u('order/query', ['kw' => $kw]));
                }
                $otpErr = '验证码错误或已过期, 请重试';
            }
        }

        $orders = [];
        $mode = 'none';        // none=未查询 / sn=订单号直查 / contact=联系方式查询
        $verified = false;     // 联系方式已通过验证码验证(可见订单号与卡密)
        $otpStep = false;      // 已发送验证码, 等待输入
        if ($kw !== '') {
            $bySn = DB::value('SELECT COUNT(*) FROM orders WHERE sn = ?', [$kw]);
            if ($bySn > 0) {
                $mode = 'sn';
                $orders = DB::fetchAll('SELECT * FROM orders WHERE sn = ? ORDER BY id DESC LIMIT 20', [$kw]);
            } else {
                $mode = 'contact';
                $orders = DB::fetchAll('SELECT * FROM orders WHERE contact = ? ORDER BY id DESC LIMIT 20', [$kw]);
                $verified = self::oqVerified($kw);
                $otpStep = (bool)DB::value('SELECT COUNT(*) FROM contact_otps WHERE contact = ? AND expires_at > ? AND tries < 5', [$kw, now()]);
            }
        }
        View::theme('query', [
            'orders' => $orders,
            'kw' => $kw,
            'mode' => $mode,
            'verified' => $verified,
            'otpStep' => $otpStep,
            'mailOpen' => setting('smtp_open') === '1',
            'otpErr' => $otpErr,
            'otpOk' => $otpOk,
            'categories' => cat_list(),
            'pageTitle' => '订单查询',
        ]);
    }

    public function actionDetail()
    {
        $sn = trim(arr_get($_GET, 'sn'));
        $order = DB::fetch('SELECT * FROM orders WHERE sn = ?', [$sn]);
        if (!$order) {
            View::theme('error', ['msg' => '订单不存在, 请核对订单号', 'pageTitle' => '订单不存在']);
            return;
        }
        $pluginName = '';
        if ($order['pay_plugin']) {
            $meta = Plugin::meta('payment', $order['pay_plugin']);
            $pluginName = $meta ? $meta['title'] : $order['pay_plugin'];
        }
        View::theme('result', [
            'order' => $order,
            'pluginName' => $pluginName,
            'categories' => cat_list(),
            'pageTitle' => '订单详情',
        ]);
    }

    /** 发送查单验证码(带频控), 返回错误消息, 空串=成功 */
    protected static function oqSend($contact)
    {
        $contact = trim((string)$contact);
        if ($contact === '' || !filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            return '邮箱验证仅支持下单时填写的邮箱联系方式';
        }
        $recentContact = (int)DB::value('SELECT COUNT(*) FROM contact_otps WHERE contact = ? AND created_at > ?', [$contact, now() - 600]);
        if ($recentContact >= 3) return '该邮箱请求过于频繁, 请10分钟后再试';
        $ip = client_ip();
        $recentIp = (int)DB::value('SELECT COUNT(*) FROM contact_otps WHERE ip = ? AND created_at > ?', [$ip, now() - 1800]);
        if ($recentIp >= 10) return '请求过于频繁, 请稍后再试';
        $code = (string)random_int(100000, 999999);
        DB::insert('contact_otps', [
            'contact' => mb_substr($contact, 0, 100),
            'code' => $code,
            'ip' => $ip,
            'tries' => 0,
            'created_at' => now(),
            'expires_at' => now() + 600,
        ]);
        $site = setting('site_name', '坤发卡');
        $ok = send_mail($contact, $site . ' 订单查询验证码',
            "您正在查询在 {$site} 购买的订单。\n\n验证码: {$code}\n\n10分钟内有效, 请勿泄露给他人。如非本人操作请忽略本邮件。");
        return $ok ? '' : '验证码邮件发送失败, 请稍后再试';
    }

    /** 校验验证码并通过后放行会话 */
    protected static function oqVerify($contact, $code)
    {
        $contact = trim((string)$contact);
        if ($contact === '' || !preg_match('/^\d{6}$/', (string)$code)) return false;
        $row = DB::fetch('SELECT * FROM contact_otps WHERE contact = ? AND expires_at > ? AND tries < 5 ORDER BY id DESC LIMIT 1', [$contact, now()]);
        if (!$row) return false;
        DB::update('contact_otps', ['tries' => (int)$row['tries'] + 1], 'id = ?', [$row['id']]);
        if (!hash_equals((string)$row['code'], (string)$code)) return false;
        DB::exec('DELETE FROM contact_otps WHERE contact = ?', [$contact]);
        $_SESSION['oq'] = ['c' => hash('sha256', $contact), 't' => now() + self::OQ_TTL];
        return true;
    }

    /** 当前会话对该联系方式是否已通过验证 */
    protected static function oqVerified($contact)
    {
        if (empty($_SESSION['oq']['c']) || empty($_SESSION['oq']['t'])) return false;
        if ((int)$_SESSION['oq']['t'] < now()) return false;
        return hash_equals((string)$_SESSION['oq']['c'], hash('sha256', trim((string)$contact)));
    }
}
