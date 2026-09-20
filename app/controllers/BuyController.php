<?php
/**
 * 前台: 商品购买下单
 */
class BuyController
{
    public function actionIndex()
    {
        $id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
        $product = DB::fetch('SELECT * FROM products WHERE id = ? AND status = 1', [$id]);
        if (!$product) {
            View::theme('error', ['msg' => '商品不存在或已下架', 'pageTitle' => '商品不存在']);
            return;
        }
        if (!product_visible($product)) {
            View::theme('error', ['msg' => '该商品未对您当前的会员等级开放', 'pageTitle' => '无法购买']);
            return;
        }
        $categories = cat_list();
        $contactTypes = contact_types_enabled();
        $contactPrefill = '';
        $uid = current_user_id();
        if ($uid) {
            $u = DB::fetch('SELECT email FROM users WHERE id = ?', [$uid]);
            if ($u && $u['email']) {
                // 会员邮箱在启用邮箱类型时预填并默认选中
                if (in_array('email', $contactTypes, true)) {
                    $contactPrefill = $u['email'];
                }
            }
        }
        View::theme('product', [
            'product' => $product,
            'stock' => product_stock($product['id']),
            'categories' => $categories,
            'contactTypes' => $contactTypes,
            'contactPrefill' => $contactPrefill,
            'payments' => enabled_payments(),
            'pageTitle' => $product['name'] . ' - ' . setting('site_name', '坤发卡'),
        ]);
    }

    public function actionCreate()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(u('home/index'));
        csrf_check();
        // 防恶意刷单: 人机验证(极验/Turnstile/图形验证码按配置自动匹配) + 同IP下单频控兜底
        if (!Captcha::verify('order', $_POST)) {
            View::theme('error', ['msg' => '人机验证未通过, 请返回上一步刷新后重试', 'pageTitle' => '验证失败']);
            return;
        }
        $ipLimit = (int)setting('order_ip_limit', '30');
        if ($ipLimit > 0 && (int)DB::value('SELECT COUNT(*) FROM orders WHERE ip = ? AND created_at > ?', [client_ip(), now() - 3600]) >= $ipLimit) {
            View::theme('error', ['msg' => '下单过于频繁, 请一小时后再试', 'pageTitle' => '请稍后再试']);
            return;
        }
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $num = isset($_POST['num']) ? (int)$_POST['num'] : 1;
        $contact = trim(arr_get($_POST, 'contact'));
        $contactType = trim(arr_get($_POST, 'contact_type'));
        // 买家在商品页选择的支付方式(直购模式: 创建后直接跳转支付)
        $pluginCode = trim(arr_get($_POST, 'plugin'));
        $product = DB::fetch('SELECT * FROM products WHERE id = ? AND status = 1', [$productId]);
        if (!$product) {
            View::theme('error', ['msg' => '商品不存在或已下架', 'pageTitle' => '错误']);
            return;
        }
        if (!product_visible($product)) {
            View::theme('error', ['msg' => '该商品未对您当前的会员等级开放', 'pageTitle' => '无法购买']);
            return;
        }
        if ($num < (int)$product['min_num']) $num = (int)$product['min_num'];
        if ((int)$product['max_num'] > 0 && $num > (int)$product['max_num']) $num = (int)$product['max_num'];
        if ($num < 1) $num = 1;
        $stock = product_stock($product['id']);
        if ($stock < $num) {
            View::theme('error', ['msg' => '库存不足, 剩余 ' . $stock . ' 件', 'pageTitle' => '库存不足']);
            return;
        }

        // 联系方式类型与格式校验(未传类型时兼容旧表单: 含@识别为邮箱, 否则用第一个启用类型)
        $enabled = contact_types_enabled();
        if ($contactType === '') {
            $contactType = strpos($contact, '@') !== false ? 'email' : $enabled[0];
        }
        if (!in_array($contactType, $enabled, true)) {
            View::theme('error', ['msg' => '该联系方式类型未开放, 请重新选择', 'pageTitle' => '错误']);
            return;
        }
        $err = contact_validate($contactType, $contact);
        if ($err !== '') {
            View::theme('error', ['msg' => $err, 'pageTitle' => '错误']);
            return;
        }

        if ($contact === '') {
            View::theme('error', ['msg' => '请填写联系方式, 方便接收卡密与查询订单', 'pageTitle' => '错误']);
            return;
        }
        $total = round((float)$product['price'] * $num, 2);
        $sn = order_sn();
        DB::insert('orders', [
            'sn' => $sn,
            'user_id' => current_user_id(),
            'product_id' => $product['id'],
            'product_name' => $product['name'],
            'num' => $num,
            'unit_price' => $product['price'],
            'total' => $total,
            'contact' => $contact,
            'contact_type' => $contactType,
            'ip' => client_ip(),
            'created_at' => now(),
            'expired_at' => now() + order_timeout_minutes() * 60,
        ]);
        // 直购模式: 买家已在商品页选择支付方式, 直接跳转支付发起(免选择页)
        if ($pluginCode !== '') {
            redirect(u('pay/go', array_filter(['sn' => $sn, 'plugin' => $pluginCode])));
        }
        redirect(u('pay/choose', ['sn' => $sn]));
    }
}
