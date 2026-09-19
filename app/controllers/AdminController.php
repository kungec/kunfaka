<?php
/**
 * 管理后台控制器
 */
class AdminController
{
    public function before($method)
    {
        $public = ['actionLogin', 'actionCaptchaImage'];
        if (!in_array($method, $public)) {
            if (empty($_SESSION['admin_id'])) {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                    json_out(['code' => 401, 'msg' => '登录已失效']);
                }
                redirect(au('login'));
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_check();
        }
    }

    // ---------- 登录 ----------

    public function actionLogin()
    {
        if (!empty($_SESSION['admin_id'])) redirect(au('dashboard'));
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user = trim(arr_get($_POST, 'username'));
            $pass = arr_get($_POST, 'password');
            if (empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], arr_get($_POST, '_csrf'))) {
                $error = '页面已过期, 请重试';
            } elseif (!Captcha::verify('admin', $_POST)) {
                $error = '人机验证未通过, 请重试';
            } else {
                $lock = Security::loginLocked('admin', $user, client_ip());
                if ($lock['locked']) {
                    $error = '密码错误次数过多, 已锁定, 请 ' . max(1, (int)ceil($lock['left'] / 60)) . ' 分钟后再试';
                } else {
                    $row = DB::fetch('SELECT * FROM admin_users WHERE username = ?', [$user]);
                    if ($row && password_verify($pass, $row['password'])) {
                        Security::loginClear('admin', $user, client_ip());
                        session_regenerate_id(true);
                        $_SESSION['admin_id'] = $row['id'];
                        $_SESSION['admin_name'] = $row['username'];
                        add_log('admin', '管理员「' . $user . '」登录成功');
                        redirect(au('dashboard'));
                    }
                    Security::loginFail('admin', $user, client_ip());
                    add_log('admin', '管理员登录失败: ' . $user);
                    $left = Security::LOGIN_MAX_FAILS - $lock['fails'] - 1;
                    $error = '用户名或密码错误' . ($left > 0 ? ", 今日还可尝试 {$left} 次" : ', 已触发锁定(60分钟)');
                }
            }
        }
        View::admin('login', ['error' => $error]);
    }

    /** 后台登录页验证码图片 */
    public function actionCaptchaImage()
    {
        Captcha::image('admin');
    }

    public function actionLogout()
    {
        $_SESSION = [];
        session_destroy();
        redirect(au('login'));
    }

    // ---------- 仪表盘 ----------

    public function actionDashboard()
    {
        $todayStart = strtotime(date('Y-m-d'));
        $yStart = $todayStart - 86400;
        $stats = [
            'today_orders' => (int)DB::value('SELECT COUNT(*) FROM orders WHERE created_at >= ? AND status IN (1)', [$todayStart]),
            'today_amount' => (float)DB::value('SELECT IFNULL(SUM(total),0) FROM orders WHERE created_at >= ? AND status = 1', [$todayStart]),
            'yesterday_orders' => (int)DB::value('SELECT COUNT(*) FROM orders WHERE created_at >= ? AND created_at < ? AND status = 1', [$yStart, $todayStart]),
            'yesterday_amount' => (float)DB::value('SELECT IFNULL(SUM(total),0) FROM orders WHERE created_at >= ? AND created_at < ? AND status = 1', [$yStart, $todayStart]),
            'total_orders' => (int)DB::value('SELECT COUNT(*) FROM orders WHERE status = 1'),
            'total_amount' => (float)DB::value('SELECT IFNULL(SUM(total),0) FROM orders WHERE status = 1'),
            'pending_orders' => (int)DB::value('SELECT COUNT(*) FROM orders WHERE status = 0 AND expired_at > ?', [now()]),
            'pending_cards' => (int)DB::value('SELECT COUNT(*) FROM orders WHERE status = 3'),
            'cards_left' => (int)DB::value('SELECT COUNT(*) FROM cards WHERE status = 0'),
        ];
        $lowStock = DB::fetchAll(
            'SELECT p.id, p.name, (SELECT COUNT(*) FROM cards c WHERE c.product_id = p.id AND c.status = 0) AS stock FROM products p WHERE p.status = 1 AND (SELECT COUNT(*) FROM cards c2 WHERE c2.product_id = p.id AND c2.status = 0) < 10 ORDER BY stock ASC LIMIT 8'
        );
        $recent = DB::fetchAll('SELECT * FROM orders ORDER BY id DESC LIMIT 10');
        $week = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = strtotime(date('Y-m-d')) - $i * 86400;
            $cnt = (int)DB::value('SELECT COUNT(*) FROM orders WHERE status = 1 AND paid_at >= ? AND paid_at < ?', [$day, $day + 86400]);
            $week[] = ['day' => date('m-d', $day), 'count' => $cnt];
        }
        View::admin('dashboard', ['stats' => $stats, 'lowStock' => $lowStock, 'recent' => $recent, 'week' => $week]);
    }

    // ---------- 分类管理 ----------

    public function actionCategories()
    {
        View::admin('categories', [
            'list' => DB::fetchAll('SELECT * FROM categories ORDER BY sort ASC, id ASC'),
        ]);
    }

    public function actionCategorySave()
    {
        $id = (int)arr_get($_POST, 'id');
        $data = [
            'name' => trim(arr_get($_POST, 'name')),
            'icon' => trim(arr_get($_POST, 'icon')),
            'sort' => (int)arr_get($_POST, 'sort'),
        ];
        if ($data['name'] === '') json_out(['code' => 1, 'msg' => '分类名称不能为空']);
        if ($id > 0) {
            DB::update('categories', $data, 'id = ?', [$id]);
        } else {
            $data['created_at'] = now();
            DB::insert('categories', $data);
        }
        json_out(['code' => 0, 'msg' => '保存成功']);
    }

    public function actionCategoryDel()
    {
        $id = (int)arr_get($_POST, 'id');
        $count = (int)DB::value('SELECT COUNT(*) FROM products WHERE category_id = ?', [$id]);
        if ($count > 0) json_out(['code' => 1, 'msg' => '该分类下有 ' . $count . ' 个商品, 请先移除']);
        DB::exec('DELETE FROM categories WHERE id = ?', [$id]);
        json_out(['code' => 0, 'msg' => '删除成功']);
    }

    // ---------- 商品管理 ----------

    public function actionProducts()
    {
        $catId = (int)arr_get($_GET, 'cat');
        $where = '1';
        $params = [];
        if ($catId > 0) {
            $where .= ' AND p.category_id = ?';
            $params[] = $catId;
        }
        $list = DB::fetchAll(
            "SELECT p.*, c.name AS cat_name,
             (SELECT COUNT(*) FROM cards cc WHERE cc.product_id = p.id AND cc.status = 0) AS stock
             FROM products p LEFT JOIN categories c ON c.id = p.category_id
             WHERE {$where} ORDER BY p.sort ASC, p.id DESC LIMIT 200", $params);
        View::admin('products', [
            'list' => $list,
            'categories' => DB::fetchAll('SELECT * FROM categories ORDER BY sort ASC, id ASC'),
            'catId' => $catId,
        ]);
    }

    public function actionProductEdit()
    {
        $id = (int)arr_get($_GET, 'id');
        $product = $id > 0 ? DB::fetch('SELECT * FROM products WHERE id = ?', [$id]) : null;
        View::admin('product_edit', [
            'product' => $product,
            'categories' => DB::fetchAll('SELECT * FROM categories ORDER BY sort ASC, id ASC'),
        ]);
    }

    public function actionProductSave()
    {
        $id = (int)arr_get($_POST, 'id');
        $data = [
            'category_id' => (int)arr_get($_POST, 'category_id'),
            'name' => trim(arr_get($_POST, 'name')),
            'description' => arr_get($_POST, 'description'),
            'price' => round((float)arr_get($_POST, 'price'), 2),
            'min_num' => max(1, (int)arr_get($_POST, 'min_num', 1)),
            'max_num' => max(1, (int)arr_get($_POST, 'max_num', 1)),
            'status' => (int)arr_get($_POST, 'status', 1),
            'sort' => (int)arr_get($_POST, 'sort'),
        ];
        if ($data['name'] === '') json_out(['code' => 1, 'msg' => '商品名称不能为空']);
        if ($id > 0) {
            DB::update('products', $data, 'id = ?', [$id]);
        } else {
            $data['created_at'] = now();
            $id = (int)DB::insert('products', $data);
        }
        // 支持创建商品时同时导入卡密
        $cards = trim(arr_get($_POST, 'cards_import'));
        if ($cards !== '') $this->importCards($id, $cards);
        json_out(['code' => 0, 'msg' => '保存成功']);
    }

    public function actionProductDel()
    {
        $id = (int)arr_get($_POST, 'id');
        $count = (int)DB::value('SELECT COUNT(*) FROM cards WHERE product_id = ? AND status = 0', [$id]);
        if ($count > 0) json_out(['code' => 1, 'msg' => '该商品还有 ' . $count . ' 张未售卡密, 请先清空库存']);
        DB::exec('DELETE FROM products WHERE id = ?', [$id]);
        json_out(['code' => 0, 'msg' => '删除成功']);
    }

    // ---------- 卡密管理 ----------

    public function actionCards()
    {
        $productId = (int)arr_get($_GET, 'product_id');
        $page = max(1, (int)arr_get($_GET, 'page', 1));
        $per = 50;
        $where = '1';
        $params = [];
        if ($productId > 0) {
            $where .= ' AND c.product_id = ?';
            $params[] = $productId;
        }
        $total = (int)DB::value("SELECT COUNT(*) FROM cards c WHERE {$where}", $params);
        $list = DB::fetchAll(
            "SELECT c.*, p.name AS product_name FROM cards c LEFT JOIN products p ON p.id = c.product_id
             WHERE {$where} ORDER BY c.id DESC LIMIT {$per} OFFSET " . (($page - 1) * $per), $params);
        View::admin('cards', [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'per' => $per,
            'productId' => $productId,
            'products' => DB::fetchAll('SELECT id, name FROM products ORDER BY id DESC LIMIT 200'),
        ]);
    }

    public function actionCardsImport()
    {
        $productId = (int)arr_get($_POST, 'product_id');
        $cards = trim(arr_get($_POST, 'cards'));
        if ($productId <= 0) json_out(['code' => 1, 'msg' => '请选择商品']);
        if ($cards === '') json_out(['code' => 1, 'msg' => '请输入卡密内容']);
        $n = $this->importCards($productId, $cards);
        json_out(['code' => 0, 'msg' => '成功导入 ' . $n . ' 张卡密']);
    }

    protected function importCards($productId, $text)
    {
        $n = 0;
        $now = now();
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            DB::insert('cards', ['product_id' => $productId, 'content' => $line, 'created_at' => $now]);
            $n++;
        }
        return $n;
    }

    public function actionCardDel()
    {
        $id = (int)arr_get($_POST, 'id');
        $card = DB::fetch('SELECT * FROM cards WHERE id = ?', [$id]);
        if (!$card) json_out(['code' => 1, 'msg' => '卡密不存在']);
        if ((int)$card['status'] === 1) json_out(['code' => 1, 'msg' => '已售出的卡密不能删除(影响订单)']);
        DB::exec('DELETE FROM cards WHERE id = ?', [$id]);
        json_out(['code' => 0, 'msg' => '删除成功']);
    }

    public function actionCardsClear()
    {
        $productId = (int)arr_get($_POST, 'product_id');
        DB::exec('DELETE FROM cards WHERE product_id = ? AND status = 0', [$productId]);
        json_out(['code' => 0, 'msg' => '未售卡密已清空']);
    }

    // ---------- 订单管理 ----------

    public function actionOrders()
    {
        $status = arr_get($_GET, 'status', '');
        $kw = trim(arr_get($_GET, 'kw'));
        $page = max(1, (int)arr_get($_GET, 'page', 1));
        $per = 20;
        $where = '1';
        $params = [];
        if ($status !== '') {
            $where .= ' AND status = ?';
            $params[] = (int)$status;
        }
        if ($kw !== '') {
            $where .= ' AND (sn LIKE ? OR contact LIKE ? OR product_name LIKE ?)';
            $params = array_merge($params, ['%' . $kw . '%', '%' . $kw . '%', '%' . $kw . '%']);
        }
        $total = (int)DB::value("SELECT COUNT(*) FROM orders WHERE {$where}", $params);
        $list = DB::fetchAll("SELECT * FROM orders WHERE {$where} ORDER BY id DESC LIMIT {$per} OFFSET " . (($page - 1) * $per), $params);
        View::admin('orders', [
            'list' => $list, 'total' => $total, 'page' => $page, 'per' => $per,
            'status' => $status, 'kw' => $kw,
        ]);
    }

    public function actionOrderDetail()
    {
        $id = (int)arr_get($_GET, 'id');
        $order = DB::fetch('SELECT * FROM orders WHERE id = ?', [$id]);
        if (!$order) {
            echo '订单不存在';
            return;
        }
        View::admin('order_detail', ['order' => $order]);
    }

    /** 手动补发(库存不足待处理订单 / 已支付未发货) */
    public function actionOrderDeliver()
    {
        $id = (int)arr_get($_POST, 'id');
        $order = DB::fetch('SELECT * FROM orders WHERE id = ?', [$id]);
        if (!$order) json_out(['code' => 1, 'msg' => '订单不存在']);
        if ((int)$order['status'] === 0) json_out(['code' => 1, 'msg' => '订单未支付, 不能发货']);
        if ((int)$order['status'] === 1) json_out(['code' => 1, 'msg' => '订单已完成']);
        if ((int)$order['status'] === 2) json_out(['code' => 1, 'msg' => '订单已过期']);
        if (OrderService::deliver($id)) {
            json_out(['code' => 0, 'msg' => '补发成功']);
        }
        json_out(['code' => 1, 'msg' => '库存不足, 请先为该商品导入卡密']);
    }

    public function actionOrderDel()
    {
        $id = (int)arr_get($_POST, 'id');
        $order = DB::fetch('SELECT * FROM orders WHERE id = ?', [$id]);
        if (!$order) json_out(['code' => 1, 'msg' => '订单不存在']);
        if ((int)$order['status'] === 1) json_out(['code' => 1, 'msg' => '已完成订单不允许删除']);
        DB::exec('DELETE FROM orders WHERE id = ?', [$id]);
        json_out(['code' => 0, 'msg' => '删除成功']);
    }

    // ---------- 应用商店 ----------

    public function actionApps()
    {
        $type = arr_get($_GET, 'type', 'payment') === 'theme' ? 'theme' : 'payment';
        $list = Market::all($type);
        View::admin('apps', [
            'type' => $type,
            'list' => $list,
            'license' => License::info(),
            'marketUrl' => Market::apiUrl(),
        ]);
    }

    /** 安装本地内置应用 */
    public function actionAppInstall()
    {
        $type = arr_get($_POST, 'type') === 'theme' ? 'theme' : 'payment';
        $name = trim(arr_get($_POST, 'name'));
        $meta = Plugin::meta($type, $name);
        if (!$meta) json_out(['code' => 1, 'msg' => '应用不存在']);
        if (!empty($meta['pro']) && !License::isPro()) {
            json_out(['code' => 2, 'msg' => '该应用为专业版专享, 请先开通99元专业版会员']);
        }
        Plugin::install($type, $name);
        add_log('store', '安装应用: ' . $meta['title'] . '(' . $name . ')');
        json_out(['code' => 0, 'msg' => '安装成功']);
    }

    /** 从官方市场下载远程应用(需登录会员账号; 付费应用需专业版) */
    public function actionAppDownload()
    {
        $type = arr_get($_POST, 'type') === 'theme' ? 'theme' : 'payment';
        $name = trim(arr_get($_POST, 'name'));
        // 远程列表里查找该应用, 判断是否付费
        $found = null;
        foreach (Market::all($type) as $item) {
            if ($item['name'] === $name && !$item['local']) {
                $found = $item;
                break;
            }
        }
        if (!$found) json_out(['code' => 1, 'msg' => '市场不存在该应用']);
        if ($found['pro'] && !License::isPro()) {
            json_out(['code' => 2, 'msg' => '该应用为付费应用, 需开通99元专业版会员后免费下载']);
        }
        try {
            Market::download($type, $name);
        } catch (Exception $ex) {
            json_out(['code' => 1, 'msg' => $ex->getMessage()]);
        }
        Plugin::install($type, $name);
        add_log('store', '从官方市场下载安装应用: ' . $name);
        json_out(['code' => 0, 'msg' => '下载安装成功']);
    }

    public function actionAppToggle()
    {
        $name = trim(arr_get($_POST, 'name'));
        $row = DB::fetch('SELECT * FROM apps WHERE name = ?', [$name]);
        if (!$row) json_out(['code' => 1, 'msg' => '请先安装该应用']);
        $enabled = (int)$row['enabled'] === 1 ? 0 : 1;
        DB::update('apps', ['enabled' => $enabled], 'name = ?', [$name]);
        json_out(['code' => 0, 'msg' => $enabled ? '已启用' : '已停用', 'enabled' => $enabled]);
    }

    public function actionAppUninstall()
    {
        $name = trim(arr_get($_POST, 'name'));
        DB::exec('DELETE FROM apps WHERE name = ? AND enabled = 0', [$name]);
        json_out(['code' => 0, 'msg' => '已卸载']);
    }

    public function actionAppConfig()
    {
        $name = trim(arr_get($_GET, 'name'));
        $type = arr_get($_GET, 'type', 'payment') === 'theme' ? 'theme' : 'payment';
        $meta = Plugin::meta($type, $name);
        if (!$meta) {
            echo '应用不存在';
            return;
        }
        $fields = [];
        if ($type === 'payment') {
            $plugin = Plugin::payment($name);
            $fields = $plugin ? $plugin->fields() : [];
        }
        View::admin('app_config', [
            'meta' => $meta,
            'type' => $type,
            'fields' => $fields,
            'config' => app_config($name),
            'row' => DB::fetch('SELECT * FROM apps WHERE name = ?', [$name]),
        ]);
    }

    public function actionAppConfigSave()
    {
        $name = trim(arr_get($_POST, 'name'));
        $meta = Plugin::meta('payment', $name) ?: Plugin::meta('theme', $name);
        if (!$meta) json_out(['code' => 1, 'msg' => '应用不存在']);
        $config = [];
        foreach ($_POST as $k => $v) {
            if ($k === 'name' || $k === '_csrf') continue;
            $config[$k] = is_string($v) ? trim($v) : $v;
        }
        app_config_save($name, $config);
        json_out(['code' => 0, 'msg' => '配置已保存']);
    }

    /** 切换主题 */
    public function actionThemeSet()
    {
        $name = trim(arr_get($_POST, 'name'));
        $meta = Plugin::meta('theme', $name);
        if (!$meta) json_out(['code' => 1, 'msg' => '主题不存在']);
        if (!empty($meta['pro']) && !License::isPro()) {
            json_out(['code' => 2, 'msg' => '该主题为专业版专享, 请先开通99元专业版会员']);
        }
        Plugin::install('theme', $name);
        setting_set('theme', $name);
        add_log('store', '切换主题: ' . $meta['title'] . '(' . $name . ')');
        json_out(['code' => 0, 'msg' => '主题已切换为「' . $meta['title'] . '」']);
    }

    // ---------- 授权中心(会员) ----------

    public function actionLicense()
    {
        // ?sync=1: 从主控返回后强制同步会员状态
        $this->trySync(isset($_GET['sync']));
        View::admin('license', [
            'license' => License::info(),
            'marketUrl' => Market::apiUrl(),
            'pending' => self::pendingStoreOrder(),
            'sp' => [
                'usdt' => setting('storepay_usdt'),
                'codepay_api' => setting('storepay_codepay_api'),
                'codepay_pid' => setting('storepay_codepay_pid'),
                'codepay_key' => setting('storepay_codepay_key'),
                'price' => setting('storepay_price', '99'),
                'usdt_amount' => setting('storepay_usdt_amount', '15'),
            ],
        ]);
    }

    /** 在线开通专业版(通过官方主控收银台支付) */
    public function actionStorepayOnline()
    {
        if (!License::isAuthed()) json_out(['code' => 1, 'msg' => '请先登录官方账号']);
        if (License::isPro()) json_out(['code' => 1, 'msg' => '您已是专业版会员']);
        $api = rtrim(Market::apiUrl(), '/');
        if ($api === '') json_out(['code' => 1, 'msg' => '未配置官方市场地址(主控域名)']);
        $returnUrl = site_url('admin.php') . '?s=/license&sync=1';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api . '/api/purchase');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['token' => setting('auth_token'), 'return_url' => $returnUrl]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $res = curl_exec($ch);
        curl_close($ch);
        $json = json_decode((string)$res, true);
        if (!is_array($json) || ($json['code'] ?? 1) !== 0 || empty($json['data']['pay_url'])) {
            json_out(['code' => 1, 'msg' => isset($json['msg']) ? $json['msg'] : '主控连接失败, 请稍后再试']);
        }
        add_log('store', '跳转官方主控收银台购买专业版: ' . $json['data']['sn']);
        json_out(['code' => 0, 'msg' => '正在跳转主控收银台', 'redirect' => $json['data']['pay_url']]);
    }

    /** 手动同步主控会员状态 */
    public function actionLicenseSync()
    {
        if (!License::isAuthed()) json_out(['code' => 1, 'msg' => '请先登录官方账号']);
        $this->trySync(true);
        json_out(['code' => 0, 'msg' => License::isPro() ? '已同步: 专业版会员 ✓' : '已同步: 当前为免费版']);
    }

    public function actionLicenseLogin()
    {
        try {
            License::login(trim(arr_get($_POST, 'username')), arr_get($_POST, 'password'));
            json_out(['code' => 0, 'msg' => '登录成功']);
        } catch (Exception $ex) {
            json_out(['code' => 1, 'msg' => $ex->getMessage()]);
        }
    }

    public function actionLicenseActivate()
    {
        try {
            License::activate(arr_get($_POST, 'key'));
            json_out(['code' => 0, 'msg' => '专业版激活成功, 已解锁全部付费应用']);
        } catch (Exception $ex) {
            json_out(['code' => 1, 'msg' => $ex->getMessage()]);
        }
    }

    public function actionLicenseLogout()
    {
        License::logout();
        json_out(['code' => 0, 'msg' => '已退出登录']);
    }

    protected function trySync($force = false)
    {
        if (!License::isAuthed()) return;
        $last = (int)setting('license_sync_at', '0');
        if (!$force && now() - $last < 3600) return;
        try {
            $api = Market::apiUrl();
            $token = setting('auth_token');
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api . '/api/me');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['token' => $token, 'domain' => isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '']));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $res = curl_exec($ch);
            curl_close($ch);
            $json = json_decode((string)$res, true);
            if (is_array($json) && isset($json['code']) && $json['code'] === 0 && isset($json['data']['membership'])) {
                setting_set('license_type', $json['data']['membership'] === 'pro' ? 'pro' : 'free');
                setting_set('license_expires', (string)(int)(isset($json['data']['expires']) ? $json['data']['expires'] : 0));
                // 主控为授权唯一事实来源: 同步授权标识
                if (isset($json['data']['license_key'])) {
                    setting_set('license_key', (string)$json['data']['license_key']);
                }
            }
            setting_set('license_sync_at', (string)now());
        } catch (Exception $ex) {
        }
    }

    // ---------- 会员管理 ----------

    public function actionUsers()
    {
        $kw = trim(arr_get($_GET, 'kw'));
        $page = max(1, (int)arr_get($_GET, 'page', 1));
        $per = 20;
        $where = '1';
        $params = [];
        if ($kw !== '') {
            $where .= ' AND (u.username LIKE ? OR u.email LIKE ?)';
            $params = array_merge($params, ['%' . $kw . '%', '%' . $kw . '%']);
        }
        $total = (int)DB::value("SELECT COUNT(*) FROM users u WHERE {$where}", $params);
        $list = DB::fetchAll(
            "SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.status = 1) AS orders_count
             FROM users u WHERE {$where} ORDER BY u.id DESC LIMIT {$per} OFFSET " . (($page - 1) * $per), $params);
        View::admin('users', ['list' => $list, 'total' => $total, 'page' => $page, 'per' => $per, 'kw' => $kw]);
    }

    public function actionUserToggle()
    {
        $id = (int)arr_get($_POST, 'id');
        $user = DB::fetch('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$user) json_out(['code' => 1, 'msg' => '会员不存在']);
        $status = (int)$user['status'] === 1 ? 0 : 1;
        DB::update('users', ['status' => $status], 'id = ?', [$id]);
        json_out(['code' => 0, 'msg' => $status ? '已启用' : '已禁用']);
    }

    public function actionUserDel()
    {
        $id = (int)arr_get($_POST, 'id');
        DB::exec('DELETE FROM users WHERE id = ?', [$id]);
        json_out(['code' => 0, 'msg' => '已删除(历史订单保留)']);
    }

    // ---------- 系统日志与数据清理 ----------

    public function actionLogs()
    {
        $type = trim(arr_get($_GET, 'type'));
        $where = '1';
        $params = [];
        if ($type !== '') {
            $where .= ' AND type = ?';
            $params[] = $type;
        }
        $total = (int)DB::value("SELECT COUNT(*) FROM logs WHERE {$where}", $params);
        $list = DB::fetchAll("SELECT * FROM logs WHERE {$where} ORDER BY id DESC LIMIT 100", $params);
        View::admin('logs', ['list' => $list, 'total' => $total, 'type' => $type]);
    }

    public function actionLogsClear()
    {
        $n = (int)DB::value('SELECT COUNT(*) FROM logs');
        DB::exec('DELETE FROM logs');
        add_log('system', '管理员清空系统日志(原 ' . $n . ' 条)');
        json_out(['code' => 0, 'msg' => '已清空 ' . $n . ' 条日志']);
    }

    public function actionOrderClean()
    {
        $scope = arr_get($_POST, 'scope');
        $map = [
            'expired' => ['已过期', 'status = 2'],
            'pending' => ['待支付', 'status = 0'],
            'all' => ['全部', '1'],
        ];
        if (!isset($map[$scope])) json_out(['code' => 1, 'msg' => '无效的清理范围']);
        $n = DB::exec('DELETE FROM orders WHERE ' . $map[$scope][1]);
        add_log('system', '管理员清理' . $map[$scope][0] . '订单, 共 ' . $n . ' 条');
        json_out(['code' => 0, 'msg' => '已清理 ' . $n . ' 条' . $map[$scope][0] . '订单']);
    }

    // ---------- 公告与单页 ----------

    public function actionNotices()
    {
        $editId = (int)arr_get($_GET, 'edit');
        View::admin('notices', [
            'list' => DB::fetchAll('SELECT * FROM notices ORDER BY sort DESC, id DESC'),
            'edit' => $editId > 0 ? DB::fetch('SELECT * FROM notices WHERE id = ?', [$editId]) : null,
            'spOpen' => setting('singlepage_open') === '1',
            'spTitle' => setting('singlepage_title', '关于我们'),
            'spContent' => (string)setting('singlepage_content'),
        ]);
    }

    public function actionNoticeSave()
    {
        $id = (int)arr_get($_POST, 'id');
        $title = trim(arr_get($_POST, 'title'));
        if ($title === '') json_out(['code' => 1, 'msg' => '公告标题不能为空']);
        if (mb_strlen($title) > 100) json_out(['code' => 1, 'msg' => '公告标题过长(最多100字)']);
        $data = [
            'title' => $title,
            'content' => trim(arr_get($_POST, 'content')),
            'status' => (int)arr_get($_POST, 'status') === 1 ? 1 : 0,
            'sort' => (int)arr_get($_POST, 'sort'),
        ];
        if ($id > 0) {
            DB::update('notices', $data, 'id = ?', [$id]);
            add_log('system', '管理员编辑公告#' . $id);
        } else {
            $data['created_at'] = now();
            DB::insert('notices', $data);
            add_log('system', '管理员发布公告「' . mb_substr($title, 0, 30) . '」');
        }
        json_out(['code' => 0, 'msg' => '已保存']);
    }

    public function actionNoticeDel()
    {
        $id = (int)arr_get($_POST, 'id');
        if ($id <= 0) json_out(['code' => 1, 'msg' => '参数错误']);
        DB::exec('DELETE FROM notices WHERE id = ?', [$id]);
        add_log('system', '管理员删除公告#' . $id);
        json_out(['code' => 0, 'msg' => '已删除']);
    }

    public function actionPageSave()
    {
        $open = (int)arr_get($_POST, 'open') === 1 ? '1' : '0';
        $title = trim(arr_get($_POST, 'title'));
        setting_set('singlepage_open', $open);
        setting_set('singlepage_title', $title !== '' ? mb_substr($title, 0, 50) : '关于我们');
        setting_set('singlepage_content', trim(arr_get($_POST, 'content')));
        add_log('system', '管理员更新单页设置(状态: ' . ($open === '1' ? '开启' : '关闭') . ')');
        json_out(['code' => 0, 'msg' => '单页设置已保存']);
    }

    // ---------- 商店自助购买(码支付/USDT → 专业版) ----------

    public function actionStorepaySave()
    {
        $keys = ['storepay_usdt', 'storepay_codepay_api', 'storepay_codepay_pid', 'storepay_codepay_key'];
        foreach ($keys as $k) {
            if (isset($_POST[$k])) setting_set($k, trim((string)$_POST[$k]));
        }
        if (isset($_POST['storepay_price'])) setting_set('storepay_price', (string)max(1, (int)$_POST['storepay_price']));
        if (isset($_POST['storepay_usdt_amount'])) setting_set('storepay_usdt_amount', (string)max(0.01, (float)$_POST['storepay_usdt_amount']));
        add_log('store', '管理员更新商店收款配置');
        json_out(['code' => 0, 'msg' => '收款配置已保存']);
    }

    public function actionStorepayCreate()
    {
        $channel = arr_get($_POST, 'channel') === 'codepay' ? 'codepay' : 'usdt';
        if (self::pendingStoreOrder()) json_out(['code' => 1, 'msg' => '已有待支付订单, 请先完成或取消']);
        $sn = 'SP' . date('Ymd') . strtoupper(bin2hex(random_bytes(6)));
        if ($channel === 'codepay') {
            $api = trim(setting('storepay_codepay_api'));
            $pid = trim(setting('storepay_codepay_pid'));
            $key = trim(setting('storepay_codepay_key'));
            if ($api === '' || $pid === '' || $key === '') json_out(['code' => 1, 'msg' => '请先在下方配置码支付网关/PID/密钥']);
            $price = (float)setting('storepay_price', '99');
            DB::insert('store_orders', [
                'sn' => $sn, 'channel' => 'codepay',
                'pay_type' => in_array(arr_get($_POST, 'pay_type'), ['alipay', 'wxpay', 'qqpay'], true) ? arr_get($_POST, 'pay_type') : 'alipay',
                'amount' => $price, 'status' => 0, 'created_at' => now(),
            ]);
            add_log('store', '发起商店购买(码支付): ' . $sn);
            $url = EpayClient::buildSubmit(
                $api, $pid, $key,
                in_array(arr_get($_POST, 'pay_type'), ['alipay', 'wxpay', 'qqpay'], true) ? arr_get($_POST, 'pay_type') : 'alipay',
                ['sn' => $sn, 'product_name' => '坤发卡专业版会员', 'total' => $price],
                site_url('index.php?s=/storepay/notify'),
                au('license'),
                setting('site_name', '坤发卡')
            );
            json_out(['code' => 0, 'msg' => '正在跳转支付', 'redirect' => $url, 'sn' => $sn]);
        }
        // USDT: 基础数量 + 唯一尾数(防撞单)
        $addr = trim(setting('storepay_usdt'));
        if ($addr === '') json_out(['code' => 1, 'msg' => '请先在下方配置USDT(TRC20)收款地址']);
        $base = (float)setting('storepay_usdt_amount', '15');
        $amount = 0.0;
        for ($i = 0; $i < 30; $i++) {
            $try = (float)number_format($base + mt_rand(1, 999999) / 1000000, 6, '.', '');
            $dup = DB::fetch('SELECT id FROM store_orders WHERE status = 0 AND channel = \'usdt\' AND amount = ?', [$try]);
            if (!$dup) { $amount = $try; break; }
        }
        if ($amount <= 0) json_out(['code' => 1, 'msg' => '金额分配失败, 请重试']);
        DB::insert('store_orders', ['sn' => $sn, 'channel' => 'usdt', 'amount' => $amount, 'status' => 0, 'created_at' => now()]);
        add_log('store', '发起商店购买(USDT): ' . $sn . ' 金额 ' . $amount);
        json_out(['code' => 0, 'msg' => '订单已创建', 'sn' => $sn]);
    }

    public function actionStorepayCheck()
    {
        $sn = trim(arr_get($_POST, 'sn'));
        $order = DB::fetch('SELECT * FROM store_orders WHERE sn = ?', [$sn]);
        if (!$order) json_out(['code' => 1, 'msg' => '订单不存在']);
        if ((int)$order['status'] === 1) json_out(['code' => 0, 'paid' => true]);
        if ((int)$order['status'] !== 0) json_out(['code' => 0, 'paid' => false, 'msg' => '订单已取消']);
        if ($order['channel'] === 'codepay') {
            // 易支付协议订单查询接口
            $api = rtrim(trim(setting('storepay_codepay_api')), '/');
            $pid = trim(setting('storepay_codepay_pid'));
            $key = trim(setting('storepay_codepay_key'));
            if ($api === '' || $key === '') json_out(['code' => 1, 'msg' => '码支付未配置']);
            $qs = http_build_query(['act' => 'order', 'pid' => $pid, 'key' => $key, 'out_trade_no' => $sn]);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, (stripos($api, 'http') === 0 ? $api : 'https://' . $api) . '/api.php?' . $qs);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $res = curl_exec($ch);
            curl_close($ch);
            $j = json_decode((string)$res, true);
            if (is_array($j) && isset($j['status']) && (int)$j['status'] === 1) {
                DB::update('store_orders', ['status' => 1, 'txid' => trim((string)($j['trade_no'] ?? '')), 'paid_at' => now()], 'id = ?', [(int)$order['id']]);
                StorepayController::activatePro($sn);
                json_out(['code' => 0, 'paid' => true]);
            }
            json_out(['code' => 0, 'paid' => false]);
        }
        // USDT: 链上精确金额匹配
        $addr = trim(setting('storepay_usdt'));
        try {
            $list = TronService::getTransfers($addr, (int)$order['created_at'] - 120);
        } catch (Exception $ex) {
            json_out(['code' => 0, 'paid' => false, 'msg' => '链上查询失败, 稍后重试']);
        }
        foreach ($list as $t) {
            if ((float)$t['amount'] === (float)$order['amount']) {
                DB::update('store_orders', ['status' => 1, 'txid' => $t['txid'], 'paid_at' => now()], 'id = ?', [(int)$order['id']]);
                StorepayController::activatePro($sn);
                json_out(['code' => 0, 'paid' => true]);
            }
        }
        json_out(['code' => 0, 'paid' => false]);
    }

    public function actionStorepayCancel()
    {
        $sn = trim(arr_get($_POST, 'sn'));
        $n = DB::update('store_orders', ['status' => 2], 'sn = ? AND status = 0', [$sn]);
        if ($n <= 0) json_out(['code' => 1, 'msg' => '订单不存在或状态不可取消']);
        add_log('store', '取消商店购买订单: ' . $sn);
        json_out(['code' => 0, 'msg' => '订单已取消']);
    }

    /** 当前待支付的商店订单 */
    protected static function pendingStoreOrder()
    {
        return DB::fetch('SELECT * FROM store_orders WHERE status = 0 ORDER BY id DESC LIMIT 1');
    }

    // ---------- 系统设置 ----------

    public function actionSettings()
    {
        View::admin('settings');
    }

    public function actionSettingsSave()
    {
        // 客服联系方式(JSON行编辑器)
        if (isset($_POST['service_contacts'])) {
            $arr = json_decode((string)$_POST['service_contacts'], true);
            $allowed = array_keys(contact_type_all());
            $clean = [];
            foreach ((array)$arr as $row) {
                $t = isset($row['type']) ? trim($row['type']) : '';
                $v = isset($row['value']) ? trim($row['value']) : '';
                $n = isset($row['note']) ? trim($row['note']) : '';
                if ($t !== '' && $v !== '' && in_array($t, $allowed, true) && mb_strlen($v) <= 100) {
                    $clean[] = ['type' => $t, 'value' => $v, 'note' => mb_substr($n, 0, 30)];
                }
            }
            setting_set('service_contacts', json_encode($clean, JSON_UNESCAPED_UNICODE));
        }
        // 联系方式多选(仅站点设置表单携带)
        if (isset($_POST['contact_types_submitted'])) {
            $allowed = array_keys(contact_type_all());
            $picked = isset($_POST['contact_types']) && is_array($_POST['contact_types']) ? $_POST['contact_types'] : [];
            $picked = array_values(array_intersect($allowed, $picked));
            if (!$picked) json_out(['code' => 1, 'msg' => '请至少选择一种下单联系方式']);
            setting_set('contact_types', implode(',', $picked));
        }
        $keys = ['site_name', 'site_url', 'theme', 'announcement', 'order_timeout', 'contact_qq',
            'smtp_open', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_ssl', 'official_api',
            'verify_mode', 'captcha_open', 'turnstile_open', 'turnstile_site_key', 'turnstile_secret_key',
            'geetest_id', 'geetest_key', 'geetest_timeout', 'cdn_mode'];
        foreach ($keys as $k) {
            if (isset($_POST[$k])) setting_set($k, is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k]);
        }
        // CDN模式白名单兜底(仅允许三个合法值)
        if (isset($_POST['cdn_mode']) && !in_array($_POST['cdn_mode'], ['off', 'cloudflare', 'cdn'], true)) {
            setting_set('cdn_mode', 'off');
        }
        json_out(['code' => 0, 'msg' => '设置已保存']);
    }
}
