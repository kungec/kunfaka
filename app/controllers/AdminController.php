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
            if (empty($_SESSION['admin_id']) || !current_admin()) {
                unset($_SESSION['admin_id'], $_SESSION['admin_name']);
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                    json_out(['code' => 401, 'msg' => '登录已失效']);
                }
                redirect(au('login'));
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_check();
        }
    }

    /** 当前管理员是否超级管理员 */
    protected function isSuper()
    {
        $a = current_admin();
        return $a && $a['role'] === 'super';
    }

    // ---------- 登录 ----------

    public function actionLogin()
    {
        if (!empty($_SESSION['admin_id']) && current_admin()) redirect(au('dashboard'));
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
                        if ((int)$row['status'] !== 1) {
                            $error = '账号已被禁用, 请联系超级管理员';
                        } else {
                            Security::loginClear('admin', $user, client_ip());
                            session_regenerate_id(true);
                            $_SESSION['admin_id'] = $row['id'];
                            $_SESSION['admin_name'] = $row['nickname'] !== '' ? $row['nickname'] : $row['username'];
                            DB::exec('UPDATE admin_users SET prev_login_at = last_login_at, prev_login_ip = last_login_ip, last_login_at = ?, last_login_ip = ? WHERE id = ?', [now(), client_ip(), $row['id']]);
                            add_log('admin', '管理员「' . $user . '」登录成功');
                            redirect(au('dashboard'));
                        }
                    } else {
                        Security::loginFail('admin', $user, client_ip());
                        add_log('admin', '管理员登录失败: ' . $user);
                        $left = Security::LOGIN_MAX_FAILS - $lock['fails'] - 1;
                        $error = '用户名或密码错误' . ($left > 0 ? ", 今日还可尝试 {$left} 次" : ', 已触发锁定(60分钟)');
                    }
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
        $now = now();
        $todayStart = strtotime(date('Y-m-d'));
        $yStart = $todayStart - 86400;
        $monthStart = strtotime(date('Y-m-01'));
        $lastMonthStart = strtotime('-1 month', $monthStart);
        $paidStat = function ($from, $to) {
            return [
                'amount' => (float)DB::value('SELECT IFNULL(SUM(total),0) FROM orders WHERE status = 1 AND paid_at >= ? AND paid_at < ?', [$from, $to]),
                'orders' => (int)DB::value('SELECT COUNT(*) FROM orders WHERE status = 1 AND paid_at >= ? AND paid_at < ?', [$from, $to]),
            ];
        };
        $stats = [
            'today' => $paidStat($todayStart, PHP_INT_MAX),
            'ySame' => $paidStat($yStart, $yStart + ($now - $todayStart)),
            'yesterday' => $paidStat($yStart, $todayStart),
            'dayBefore' => $paidStat($yStart - 86400, $yStart),
            'month' => $paidStat($monthStart, PHP_INT_MAX),
            'lMonthSame' => $paidStat($lastMonthStart, $lastMonthStart + ($now - $monthStart)),
            'lMonthFull' => $paidStat($lastMonthStart, $monthStart),
        ];
        // 待处理事项
        $todo = [
            'pending_cards' => (int)DB::value('SELECT COUNT(*) FROM orders WHERE status = 3'),
            'pending_pay' => (int)DB::value('SELECT COUNT(*) FROM orders WHERE status = 0'),
            'expired' => (int)DB::value('SELECT COUNT(*) FROM orders WHERE status = 2'),
            'low_stock' => (int)DB::value('SELECT COUNT(*) FROM products p WHERE p.status = 1 AND (SELECT COUNT(*) FROM cards c WHERE c.product_id = p.id AND c.status = 0) < 10'),
        ];
        // 近30天成交趋势(按支付日聚合)
        $aggA = [];
        $aggO = [];
        foreach (DB::fetchAll('SELECT paid_at, total FROM orders WHERE status = 1 AND paid_at >= ?', [$todayStart - 29 * 86400]) as $r) {
            $k = date('Y-m-d', (int)$r['paid_at']);
            $aggA[$k] = ($aggA[$k] ?? 0) + (float)$r['total'];
            $aggO[$k] = ($aggO[$k] ?? 0) + 1;
        }
        $buildTrend = function ($days) use ($todayStart, $aggA, $aggO) {
            $out = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $k = date('Y-m-d', $todayStart - $i * 86400);
                $out[] = ['d' => date('n/j', $todayStart - $i * 86400), 'amount' => round($aggA[$k] ?? 0, 2), 'orders' => $aggO[$k] ?? 0];
            }
            return $out;
        };
        $trend = ['7' => $buildTrend(7), '30' => $buildTrend(30)];
        // 经营数据(多时段)
        $weekStart = $todayStart - ((int)date('N') - 1) * 86400;
        $memberCount = function ($from, $to = null) {
            if ($to === null) return (int)DB::value('SELECT COUNT(*) FROM users');
            return (int)DB::value('SELECT COUNT(*) FROM users WHERE created_at >= ? AND created_at < ?', [$from, $to]);
        };
        $bizPeriods = [
            'today' => ['label' => '今日', 'stat' => $paidStat($todayStart, PHP_INT_MAX), 'members' => $memberCount($todayStart, PHP_INT_MAX)],
            'yesterday' => ['label' => '昨日', 'stat' => $paidStat($yStart, $todayStart), 'members' => $memberCount($yStart, $todayStart)],
            'week' => ['label' => '本周', 'stat' => $paidStat($weekStart, PHP_INT_MAX), 'members' => $memberCount($weekStart, PHP_INT_MAX)],
            'month' => ['label' => '本月', 'stat' => $paidStat($monthStart, PHP_INT_MAX), 'members' => $memberCount($monthStart, PHP_INT_MAX)],
            'all' => ['label' => '全部', 'stat' => $paidStat(0, PHP_INT_MAX), 'members' => $memberCount(0)],
        ];
        array_walk($bizPeriods, function (&$p) {
            $p['avg'] = $p['stat']['orders'] > 0 ? round($p['stat']['amount'] / $p['stat']['orders'], 2) : 0;
        });
        // 站内公告 + 登录信息
        $notices = DB::fetchAll('SELECT id, title, created_at FROM notices WHERE status = 1 ORDER BY sort DESC, id DESC LIMIT 6');
        $logins = DB::fetchAll("SELECT ip, created_at FROM logs WHERE type = 'admin' AND message LIKE '%登录成功%' ORDER BY id DESC LIMIT 2");
        View::admin('dashboard', [
            'stats' => $stats, 'todo' => $todo, 'trend' => $trend, 'bizPeriods' => $bizPeriods,
            'notices' => $notices, 'curLogin' => $logins[0] ?? null, 'prevLogin' => $logins[1] ?? null,
        ]);
    }

    // ---------- 分类管理 ----------

    public function actionCategories()
    {
        $where = '1';
        $params = [];
        $name = trim(arr_get($_GET, 'name'));
        if ($name !== '') { $where .= ' AND name LIKE ?'; $params[] = '%' . $name . '%'; }
        $status = arr_get($_GET, 'status', '');
        if ($status !== '') { $where .= ' AND status = ?'; $params[] = (int)$status; }
        $list = DB::fetchAll("SELECT *, (SELECT COUNT(*) FROM products p WHERE p.category_id = categories.id) AS products_count FROM categories WHERE {$where} ORDER BY sort ASC, id ASC", $params);
        $stats = [
            'total' => (int)DB::value('SELECT COUNT(*) FROM categories'),
            'enabled' => (int)DB::value('SELECT COUNT(*) FROM categories WHERE status = 1'),
        ];
        View::admin('categories', ['list' => $list, 'stats' => $stats]);
    }

    public function actionCategorySave()
    {
        $id = (int)arr_get($_POST, 'id');
        $data = [
            'name' => trim(arr_get($_POST, 'name')),
            'icon' => mb_substr(trim(arr_get($_POST, 'icon')), 0, 50),
            'sort' => (int)arr_get($_POST, 'sort'),
            'status' => arr_get($_POST, 'status') === '0' ? 0 : 1,
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

    /** 单个分类启用/停用开关 */
    public function actionCategoryToggle()
    {
        $id = (int)arr_get($_POST, 'id');
        $cat = DB::fetch('SELECT * FROM categories WHERE id = ?', [$id]);
        if (!$cat) json_out(['code' => 1, 'msg' => '分类不存在']);
        $status = (int)$cat['status'] === 1 ? 0 : 1;
        DB::update('categories', ['status' => $status], 'id = ?', [$id]);
        json_out(['code' => 0, 'msg' => $status ? '已启用' : '已停用(前台隐藏)']);
    }

    /** 批量操作选中分类(启用/停用/移除) */
    public function actionCategoriesBatch()
    {
        $op = trim(arr_get($_POST, 'op'));
        $ids = array_values(array_filter(array_map('intval', is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [])));
        if (!$ids) json_out(['code' => 1, 'msg' => '未选择分类']);
        $in = implode(',', $ids);
        if ($op === 'enable') {
            $n = DB::exec("UPDATE categories SET status = 1 WHERE id IN ({$in})");
            json_out(['code' => 0, 'msg' => '已启用 ' . $n . ' 个分类']);
        }
        if ($op === 'disable') {
            $n = DB::exec("UPDATE categories SET status = 0 WHERE id IN ({$in})");
            json_out(['code' => 0, 'msg' => '已停用 ' . $n . ' 个分类(前台隐藏)']);
        }
        if ($op === 'delete') {
            $done = 0;
            $skip = 0;
            foreach ($ids as $id) {
                $cnt = (int)DB::value('SELECT COUNT(*) FROM products WHERE category_id = ?', [$id]);
                if ($cnt > 0) { $skip++; continue; }
                DB::exec('DELETE FROM categories WHERE id = ?', [$id]);
                $done++;
            }
            $msg = '已移除 ' . $done . ' 个分类' . ($skip > 0 ? ', ' . $skip . ' 个分类下有商品已跳过' : '');
            add_log('system', '管理员批量移除分类: ' . $msg);
            json_out(['code' => $done > 0 ? 0 : 1, 'msg' => $msg]);
        }
        json_out(['code' => 1, 'msg' => '无效操作']);
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
        $where = '1';
        $params = [];
        $catId = (int)arr_get($_GET, 'cat');
        if ($catId > 0) {
            $where .= ' AND p.category_id = ?';
            $params[] = $catId;
        }
        $name = trim(arr_get($_GET, 'name'));
        if ($name !== '') {
            $where .= ' AND p.name LIKE ?';
            $params[] = '%' . $name . '%';
        }
        $status = arr_get($_GET, 'status', '');
        if ($status !== '') {
            $where .= ' AND p.status = ?';
            $params[] = (int)$status;
        }
        $list = DB::fetchAll(
            "SELECT p.*, c.name AS cat_name, g.name AS group_name,
             (SELECT COUNT(*) FROM cards cc WHERE cc.product_id = p.id AND cc.status = 0) AS stock
             FROM products p LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN product_groups g ON g.id = p.group_id
             WHERE {$where} ORDER BY p.sort ASC, p.id DESC LIMIT 200", $params);
        $dayStart = strtotime(date('Y-m-d'));
        $yStart = $dayStart - 86400;
        $weekStart = $dayStart - ((int)date('N') - 1) * 86400;
        $salesMap = [];
        foreach (DB::fetchAll(
            'SELECT product_id,
                SUM(CASE WHEN paid_at >= ? THEN 1 ELSE 0 END) AS today_cnt,
                SUM(CASE WHEN paid_at >= ? AND paid_at < ? THEN 1 ELSE 0 END) AS yesterday_cnt,
                SUM(CASE WHEN paid_at >= ? THEN 1 ELSE 0 END) AS week_cnt
             FROM orders WHERE status = 1 AND paid_at >= ? GROUP BY product_id',
            [$dayStart, $yStart, $dayStart, $weekStart, $weekStart]) as $r) {
            $salesMap[(int)$r['product_id']] = [
                'today' => (int)$r['today_cnt'],
                'yesterday' => (int)$r['yesterday_cnt'],
                'week' => (int)$r['week_cnt'],
            ];
        }
        $stats = [
            'total' => (int)DB::value('SELECT COUNT(*) FROM products'),
            'on' => (int)DB::value('SELECT COUNT(*) FROM products WHERE status = 1'),
            'off' => (int)DB::value('SELECT COUNT(*) FROM products WHERE status = 0'),
            'stock' => (int)DB::value('SELECT COUNT(*) FROM cards WHERE status = 0'),
        ];
        View::admin('products', [
            'list' => $list,
            'categories' => DB::fetchAll('SELECT * FROM categories ORDER BY sort ASC, id ASC'),
            'groups' => DB::fetchAll('SELECT * FROM product_groups ORDER BY id ASC'),
            'catId' => $catId,
            'stats' => $stats,
            'salesMap' => $salesMap,
        ]);
    }

    /** 单个商品上架/下架开关 */
    public function actionProductToggle()
    {
        $id = (int)arr_get($_POST, 'id');
        $product = DB::fetch('SELECT * FROM products WHERE id = ?', [$id]);
        if (!$product) json_out(['code' => 1, 'msg' => '商品不存在']);
        $status = (int)$product['status'] === 1 ? 0 : 1;
        DB::update('products', ['status' => $status], 'id = ?', [$id]);
        json_out(['code' => 0, 'msg' => $status ? '已上架' : '已下架']);
    }

    /** 批量操作选中商品(上架/下架/移除) */
    public function actionProductsBatch()
    {
        $op = trim(arr_get($_POST, 'op'));
        $ids = array_values(array_filter(array_map('intval', is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [])));
        if (!$ids) json_out(['code' => 1, 'msg' => '未选择商品']);
        $in = implode(',', $ids);
        if ($op === 'on') {
            $n = DB::exec("UPDATE products SET status = 1 WHERE id IN ({$in})");
            json_out(['code' => 0, 'msg' => '已上架 ' . $n . ' 个商品']);
        }
        if ($op === 'off') {
            $n = DB::exec("UPDATE products SET status = 0 WHERE id IN ({$in})");
            json_out(['code' => 0, 'msg' => '已下架 ' . $n . ' 个商品']);
        }
        if ($op === 'delete') {
            $done = 0;
            $skip = 0;
            foreach ($ids as $id) {
                $cnt = (int)DB::value('SELECT COUNT(*) FROM cards WHERE product_id = ? AND status = 0', [$id]);
                if ($cnt > 0) { $skip++; continue; }
                DB::exec('DELETE FROM products WHERE id = ?', [$id]);
                $done++;
            }
            $msg = '已移除 ' . $done . ' 个商品' . ($skip > 0 ? ', ' . $skip . ' 个商品有未售卡密已跳过' : '');
            add_log('system', '管理员批量移除商品: ' . $msg);
            json_out(['code' => $done > 0 ? 0 : 1, 'msg' => $msg]);
        }
        json_out(['code' => 1, 'msg' => '无效操作']);
    }

    public function actionProductSave()
    {
        $id = (int)arr_get($_POST, 'id');
        $old = $id > 0 ? DB::fetch('SELECT icon FROM products WHERE id = ?', [$id]) : null;
        $data = [
            'category_id' => (int)arr_get($_POST, 'category_id'),
            'group_id' => (int)arr_get($_POST, 'group_id'),
            'name' => trim(arr_get($_POST, 'name')),
            'description' => arr_get($_POST, 'description'),
            'price' => round((float)arr_get($_POST, 'price'), 2),
            'min_num' => max(1, (int)arr_get($_POST, 'min_num', 1)),
            'max_num' => max(1, (int)arr_get($_POST, 'max_num', 1)),
            'status' => (int)arr_get($_POST, 'status', 1),
            'sort' => (int)arr_get($_POST, 'sort'),
        ];
        if ($data['name'] === '') json_out(['code' => 1, 'msg' => '商品名称不能为空']);
        // 商品图标上传(jpg/png/webp/gif, ≤5MB)
        $iconReset = arr_get($_POST, 'icon_reset') === '1';
        if (!empty($_FILES['icon_file']['tmp_name']) && $_FILES['icon_file']['error'] === UPLOAD_ERR_OK) {
            $f = $_FILES['icon_file'];
            if ($f['size'] > 5 * 1024 * 1024) json_out(['code' => 1, 'msg' => '图标图片不能超过5MB']);
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) json_out(['code' => 1, 'msg' => '仅支持 jpg/png/webp/gif 图片']);
            $info = @getimagesize($f['tmp_name']);
            if ($info === false) json_out(['code' => 1, 'msg' => '图标文件不是有效图片']);
            $dir = YF_ROOT . '/uploads';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $fname = 'p_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $fname)) json_out(['code' => 1, 'msg' => '图标保存失败, 请检查目录权限']);
            if ($old && (string)$old['icon'] !== '' && strpos($old['icon'], 'uploads/') === 0) @unlink(YF_ROOT . '/' . $old['icon']);
            $data['icon'] = 'uploads/' . $fname;
        } elseif ($iconReset) {
            if ($old && (string)$old['icon'] !== '' && strpos($old['icon'], 'uploads/') === 0) @unlink(YF_ROOT . '/' . $old['icon']);
            $data['icon'] = '';
        }
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

    // ---------- 管理员系统 ----------

    public function actionAdmins()
    {
        if (!$this->isSuper()) redirect(au('dashboard'));
        $where = '1';
        $params = [];
        $kw = trim(arr_get($_GET, 'kw'));
        if ($kw !== '') {
            $where .= ' AND (username LIKE ? OR nickname LIKE ?)';
            $params = array_merge($params, ['%' . $kw . '%', '%' . $kw . '%']);
        }
        $status = arr_get($_GET, 'status', '');
        if ($status !== '') {
            $where .= ' AND status = ?';
            $params[] = (int)$status;
        }
        $list = DB::fetchAll("SELECT * FROM admin_users WHERE {$where} ORDER BY id ASC LIMIT 200", $params);
        View::admin('admins', ['list' => $list]);
    }

    /** 新增/修改管理员(仅超级管理员) */
    public function actionAdminSave()
    {
        if (!$this->isSuper()) json_out(['code' => 1, 'msg' => '仅超级管理员可管理管理员']);
        $id = (int)arr_get($_POST, 'id');
        $me = current_admin();
        if ($id > 0) {
            $target = DB::fetch('SELECT * FROM admin_users WHERE id = ?', [$id]);
            if (!$target) json_out(['code' => 1, 'msg' => '管理员不存在']);
            $data = ['nickname' => mb_substr(trim(arr_get($_POST, 'nickname')), 0, 50)];
            $role = arr_get($_POST, 'role') === 'super' ? 'super' : 'normal';
            // 不能修改自己的角色; 不能降级最后一个超级管理员
            if ($id === (int)$me['id'] && $role !== 'super') json_out(['code' => 1, 'msg' => '不能修改自己的角色']);
            if ($target['role'] === 'super' && $role === 'normal') {
                $left = (int)DB::value("SELECT COUNT(*) FROM admin_users WHERE role = 'super' AND status = 1 AND id <> ?", [$id]);
                if ($left === 0) json_out(['code' => 1, 'msg' => '至少保留一个启用的超级管理员']);
            }
            $data['role'] = $role;
            $pass = (string)arr_get($_POST, 'password');
            if ($pass !== '') {
                if (strlen($pass) < 6) json_out(['code' => 1, 'msg' => '密码至少6位']);
                $data['password'] = password_hash($pass, PASSWORD_DEFAULT);
            }
            DB::update('admin_users', $data, 'id = ?', [$id]);
            add_log('admin', '超级管理员修改管理员: ' . $target['username']);
            json_out(['code' => 0, 'msg' => '保存成功']);
        }
        $username = trim(arr_get($_POST, 'username'));
        $password = (string)arr_get($_POST, 'password');
        $role = arr_get($_POST, 'role') === 'super' ? 'super' : 'normal';
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) json_out(['code' => 1, 'msg' => '用户名需3-20位字母/数字/下划线']);
        if (strlen($password) < 6) json_out(['code' => 1, 'msg' => '密码至少6位']);
        if (DB::value('SELECT id FROM admin_users WHERE username = ?', [$username])) json_out(['code' => 1, 'msg' => '用户名已存在']);
        DB::insert('admin_users', [
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'nickname' => mb_substr(trim(arr_get($_POST, 'nickname')), 0, 50),
            'role' => $role,
            'created_at' => now(),
        ]);
        add_log('admin', '超级管理员新增管理员: ' . $username);
        json_out(['code' => 0, 'msg' => '管理员已创建']);
    }

    /** 启用/禁用管理员(仅超级管理员) */
    public function actionAdminToggle()
    {
        if (!$this->isSuper()) json_out(['code' => 1, 'msg' => '仅超级管理员可执行此操作']);
        $id = (int)arr_get($_POST, 'id');
        $me = current_admin();
        $target = DB::fetch('SELECT * FROM admin_users WHERE id = ?', [$id]);
        if (!$target) json_out(['code' => 1, 'msg' => '管理员不存在']);
        if ($id === (int)$me['id']) json_out(['code' => 1, 'msg' => '不能禁用自己的账号']);
        if ($target['role'] === 'super' && (int)$target['status'] === 1) {
            $left = (int)DB::value("SELECT COUNT(*) FROM admin_users WHERE role = 'super' AND status = 1 AND id <> ?", [$id]);
            if ($left === 0) json_out(['code' => 1, 'msg' => '至少保留一个启用的超级管理员']);
        }
        $status = (int)$target['status'] === 1 ? 0 : 1;
        DB::update('admin_users', ['status' => $status], 'id = ?', [$id]);
        json_out(['code' => 0, 'msg' => $status ? '已启用' : '已禁用']);
    }

    /** 删除管理员(仅超级管理员) */
    public function actionAdminDel()
    {
        if (!$this->isSuper()) json_out(['code' => 1, 'msg' => '仅超级管理员可执行此操作']);
        $id = (int)arr_get($_POST, 'id');
        $me = current_admin();
        $target = DB::fetch('SELECT * FROM admin_users WHERE id = ?', [$id]);
        if (!$target) json_out(['code' => 1, 'msg' => '管理员不存在']);
        if ($id === (int)$me['id']) json_out(['code' => 1, 'msg' => '不能删除自己的账号']);
        if ($target['role'] === 'super') {
            $left = (int)DB::value("SELECT COUNT(*) FROM admin_users WHERE role = 'super' AND status = 1 AND id <> ?", [$id]);
            if ($left === 0) json_out(['code' => 1, 'msg' => '至少保留一个启用的超级管理员']);
        }
        DB::exec('DELETE FROM admin_users WHERE id = ?', [$id]);
        add_log('admin', '超级管理员删除管理员: ' . $target['username']);
        json_out(['code' => 0, 'msg' => '已删除']);
    }

    // ---------- 个人设置 ----------

    public function actionProfile()
    {
        View::admin('profile', ['admin' => current_admin()]);
    }

    public function actionProfileSave()
    {
        $me = current_admin();
        $data = [];
        if (isset($_POST['nickname'])) {
            $data['nickname'] = mb_substr(trim(arr_get($_POST, 'nickname')), 0, 50);
        }
        $oldPass = (string)arr_get($_POST, 'old_password');
        $newPass = (string)arr_get($_POST, 'new_password');
        $confirmPass = (string)arr_get($_POST, 'confirm_password');
        $changingPass = $oldPass !== '' || $newPass !== '' || $confirmPass !== '';
        if ($changingPass) {
            if (!password_verify($oldPass, $me['password'])) json_out(['code' => 1, 'msg' => '旧密码不正确']);
            if (strlen($newPass) < 6) json_out(['code' => 1, 'msg' => '新密码至少6位']);
            if ($newPass !== $confirmPass) json_out(['code' => 1, 'msg' => '两次输入的新密码不一致']);
            $data['password'] = password_hash($newPass, PASSWORD_DEFAULT);
        }
        if (!$data) json_out(['code' => 1, 'msg' => '没有需要保存的修改']);
        DB::update('admin_users', $data, 'id = ?', [$me['id']]);
        if (isset($data['password'])) {
            // 改密后更换会话ID(防会话固定), 登录态保留
            session_regenerate_id(true);
        }
        if (isset($data['nickname']) && $data['nickname'] !== '') {
            $_SESSION['admin_name'] = $data['nickname'];
        }
        add_log('admin', '管理员修改个人设置' . (isset($data['password']) ? '(含密码)' : ''));
        json_out(['code' => 0, 'msg' => '保存成功']);
    }

    // ---------- 卡密管理 ----------

    public function actionCards()
    {
        $where = '1';
        $params = [];
        $productId = (int)arr_get($_GET, 'product_id');
        if ($productId > 0) {
            $where .= ' AND c.product_id = ?';
            $params[] = $productId;
        }
        $exact = trim(arr_get($_GET, 'exact'));
        if ($exact !== '') {
            $where .= ' AND c.content = ?';
            $params[] = $exact;
        }
        $fuzzy = trim(arr_get($_GET, 'fuzzy'));
        if ($fuzzy !== '') {
            $where .= ' AND c.content LIKE ?';
            $params[] = '%' . $fuzzy . '%';
        }
        $status = arr_get($_GET, 'status', '');
        if ($status !== '') {
            $where .= ' AND c.status = ?';
            $params[] = (int)$status;
        }
        $from = trim(arr_get($_GET, 'date_from'));
        if ($from !== '' && ($ts = strtotime($from)) > 0) {
            $where .= ' AND c.created_at >= ?';
            $params[] = $ts;
        }
        $to = trim(arr_get($_GET, 'date_to'));
        if ($to !== '' && ($ts = strtotime($to)) > 0) {
            $where .= ' AND c.created_at <= ?';
            $params[] = $ts + 86399;
        }
        $page = max(1, (int)arr_get($_GET, 'page', 1));
        $per = 50;
        $total = (int)DB::value("SELECT COUNT(*) FROM cards c WHERE {$where}", $params);
        $list = DB::fetchAll(
            "SELECT c.*, p.name AS product_name FROM cards c LEFT JOIN products p ON p.id = c.product_id
             WHERE {$where} ORDER BY c.id DESC LIMIT {$per} OFFSET " . (($page - 1) * $per), $params);
        $stats = [
            'total' => (int)DB::value('SELECT COUNT(*) FROM cards'),
            'unsold' => (int)DB::value('SELECT COUNT(*) FROM cards WHERE status = 0'),
            'sold' => (int)DB::value('SELECT COUNT(*) FROM cards WHERE status = 1'),
            'locked' => (int)DB::value('SELECT COUNT(*) FROM cards WHERE status = 2'),
        ];
        View::admin('cards', [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'per' => $per,
            'productId' => $productId,
            'stats' => $stats,
            'products' => DB::fetchAll('SELECT id, name FROM products ORDER BY id DESC LIMIT 200'),
        ]);
    }

    /** 导出筛选卡密(CSV, 最多5000条) */
    public function actionCardsExport()
    {
        $where = '1';
        $params = [];
        $productId = (int)arr_get($_GET, 'product_id');
        if ($productId > 0) { $where .= ' AND c.product_id = ?'; $params[] = $productId; }
        $exact = trim(arr_get($_GET, 'exact'));
        if ($exact !== '') { $where .= ' AND c.content = ?'; $params[] = $exact; }
        $fuzzy = trim(arr_get($_GET, 'fuzzy'));
        if ($fuzzy !== '') { $where .= ' AND c.content LIKE ?'; $params[] = '%' . $fuzzy . '%'; }
        $status = arr_get($_GET, 'status', '');
        if ($status !== '') { $where .= ' AND c.status = ?'; $params[] = (int)$status; }
        $rows = DB::fetchAll(
            "SELECT c.*, p.name AS product_name FROM cards c LEFT JOIN products p ON p.id = c.product_id
             WHERE {$where} ORDER BY c.id DESC LIMIT 5000", $params);
        $stMap = [0 => '未出售', 1 => '已出售', 2 => '已锁定'];
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="cards-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['ID', '卡密内容', '商品', '状态', '备注', '订单号', '入库时间', '出售时间']);
        foreach ($rows as $c) {
            fputcsv($out, [
                $c['id'], $c['content'], $c['product_name'], $stMap[(int)$c['status']] ?? (string)$c['status'],
                $c['note'], $c['order_id'] > 0 ? $c['order_id'] : '',
                date('Y-m-d H:i:s', $c['created_at']),
                $c['sold_at'] > 0 ? date('Y-m-d H:i:s', $c['sold_at']) : '',
            ]);
        }
        exit;
    }

    public function actionCardsImport()
    {
        $productId = (int)arr_get($_POST, 'product_id');
        $cards = trim(arr_get($_POST, 'cards'));
        $note = mb_substr(trim(arr_get($_POST, 'note')), 0, 200);
        $dedup = arr_get($_POST, 'dedup') === '1';
        if ($productId <= 0) json_out(['code' => 1, 'msg' => '请选择商品']);
        if ($cards === '') json_out(['code' => 1, 'msg' => '请输入卡密内容']);
        $n = $this->importCards($productId, $cards, $note, $dedup);
        json_out(['code' => 0, 'msg' => '成功导入 ' . $n . ' 张卡密']);
    }

    protected function importCards($productId, $text, $note = '', $dedup = false)
    {
        $n = 0;
        $now = now();
        $exists = [];
        if ($dedup) {
            foreach (DB::fetchAll('SELECT content FROM cards WHERE product_id = ?', [$productId]) as $r) {
                $exists[$r['content']] = true;
            }
        }
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if ($dedup) {
                if (isset($exists[$line])) continue;
                $exists[$line] = true;
            }
            DB::insert('cards', ['product_id' => $productId, 'content' => $line, 'note' => $note, 'created_at' => $now]);
            $n++;
        }
        return $n;
    }

    /** 卡密锁定/解锁/标记已售 */
    public function actionCardLock()
    {
        $id = (int)arr_get($_POST, 'id');
        $op = trim(arr_get($_POST, 'op'));
        $card = DB::fetch('SELECT * FROM cards WHERE id = ?', [$id]);
        if (!$card) json_out(['code' => 1, 'msg' => '卡密不存在']);
        if ($op === 'lock') {
            if ((int)$card['status'] !== 0) json_out(['code' => 1, 'msg' => '仅未出售的卡密可锁定']);
            DB::update('cards', ['status' => 2], 'id = ?', [$id]);
            json_out(['code' => 0, 'msg' => '已锁定(暂不出库)']);
        }
        if ($op === 'unlock') {
            if ((int)$card['status'] !== 2) json_out(['code' => 1, 'msg' => '该卡密未锁定']);
            DB::update('cards', ['status' => 0], 'id = ?', [$id]);
            json_out(['code' => 0, 'msg' => '已解锁']);
        }
        json_out(['code' => 1, 'msg' => '无效操作']);
    }

    public function actionCardsBatch()
    {
        $op = trim(arr_get($_POST, 'op'));
        $ids = array_values(array_filter(array_map('intval', is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [])));
        if (!$ids) json_out(['code' => 1, 'msg' => '未选择卡密']);
        $in = implode(',', $ids);
        if ($op === 'lock') {
            $n = DB::exec("UPDATE cards SET status = 2 WHERE id IN ({$in}) AND status = 0");
            json_out(['code' => 0, 'msg' => '已锁定 ' . $n . ' 张卡密']);
        }
        if ($op === 'unlock') {
            $n = DB::exec("UPDATE cards SET status = 0 WHERE id IN ({$in}) AND status = 2");
            json_out(['code' => 0, 'msg' => '已解锁 ' . $n . ' 张卡密']);
        }
        if ($op === 'marksold') {
            $n = DB::exec("UPDATE cards SET status = 1, sold_at = " . now() . " WHERE id IN ({$in}) AND status IN (0, 2)");
            json_out(['code' => 0, 'msg' => '已将 ' . $n . ' 张卡密标记为已出售']);
        }
        if ($op === 'delete') {
            $done = 0;
            $skip = 0;
            foreach ($ids as $id) {
                $cnt = (int)DB::value('SELECT COUNT(*) FROM cards WHERE id = ? AND status = 1', [$id]);
                if ($cnt > 0) { $skip++; continue; }
                DB::exec('DELETE FROM cards WHERE id = ?', [$id]);
                $done++;
            }
            $msg = '已移除 ' . $done . ' 张卡密' . ($skip > 0 ? ', ' . $skip . ' 张已出售卡密已跳过' : '');
            json_out(['code' => $done > 0 ? 0 : 1, 'msg' => $msg]);
        }
        json_out(['code' => 1, 'msg' => '无效操作']);
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
        [$where, $params] = $this->orderFilter();
        $page = max(1, (int)arr_get($_GET, 'page', 1));
        $per = 20;
        $total = (int)DB::value("SELECT COUNT(*) FROM orders o WHERE {$where}", $params);
        $list = DB::fetchAll("SELECT o.*, u.username AS member_name FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE {$where} ORDER BY o.id DESC LIMIT {$per} OFFSET " . (($page - 1) * $per), $params);
        $stats = [
            'count' => $total,
            'paid' => (float)DB::value("SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE {$where} AND o.status = 1", $params),
            'pending' => (float)DB::value("SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE {$where} AND o.status = 0", $params),
        ];
        $plugins = [];
        foreach (DB::fetchAll("SELECT DISTINCT pay_plugin FROM orders WHERE pay_plugin <> ''") as $r) {
            $plugins[] = $r['pay_plugin'];
        }
        View::admin('orders', [
            'list' => $list, 'total' => $total, 'page' => $page, 'per' => $per, 'stats' => $stats,
            'plugins' => $plugins,
        ]);
    }

    /** 订单筛选条件(列表/统计/导出共用) */
    protected function orderFilter()
    {
        $where = '1';
        $params = [];
        $sn = trim(arr_get($_GET, 'sn'));
        if ($sn !== '') { $where .= ' AND o.sn LIKE ?'; $params[] = $sn . '%'; }
        $pid = (int)arr_get($_GET, 'product_id');
        if ($pid > 0) { $where .= ' AND o.product_id = ?'; $params[] = $pid; }
        $card = trim(arr_get($_GET, 'card'));
        if ($card !== '') { $where .= ' AND o.cards_content LIKE ?'; $params[] = '%' . $card . '%'; }
        $contact = trim(arr_get($_GET, 'contact'));
        if ($contact !== '') { $where .= ' AND o.contact LIKE ?'; $params[] = '%' . $contact . '%'; }
        $status = arr_get($_GET, 'status', '');
        if ($status !== '') { $where .= ' AND o.status = ?'; $params[] = (int)$status; }
        $plugin = trim(arr_get($_GET, 'plugin'));
        if ($plugin !== '') { $where .= ' AND o.pay_plugin = ?'; $params[] = $plugin; }
        $ip = trim(arr_get($_GET, 'ip'));
        if ($ip !== '') { $where .= ' AND o.ip = ?'; $params[] = $ip; }
        $uid = arr_get($_GET, 'user_id', '');
        if ($uid !== '') { $where .= ' AND o.user_id = ?'; $params[] = (int)$uid; }
        $from = trim(arr_get($_GET, 'date_from'));
        if ($from !== '' && ($ts = strtotime($from)) > 0) { $where .= ' AND o.created_at >= ?'; $params[] = $ts; }
        $to = trim(arr_get($_GET, 'date_to'));
        if ($to !== '' && ($ts = strtotime($to)) > 0) { $where .= ' AND o.created_at <= ?'; $params[] = $ts + 86399; }
        return [$where, $params];
    }

    /** 导出筛选订单(CSV, 最多5000条) */
    public function actionOrdersExport()
    {
        [$where, $params] = $this->orderFilter();
        $rows = DB::fetchAll("SELECT o.*, u.username AS member_name FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE {$where} ORDER BY o.id DESC LIMIT 5000", $params);
        $stMap = [0 => '待支付', 1 => '已完成', 2 => '已过期', 3 => '待处理'];
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="orders-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['订单号', '商品ID', '商品', '数量', '单价', '金额', '联系方式', '联系类型', '支付方式', '支付状态', '发货内容', '会员', 'IP', '下单时间', '支付时间']);
        foreach ($rows as $o) {
            fputcsv($out, [
                $o['sn'], $o['product_id'], $o['product_name'], $o['num'], $o['unit_price'], $o['total'],
                $o['contact'], $o['contact_type'], $o['pay_plugin'],
                $stMap[(int)$o['status']] ?? (string)$o['status'],
                (string)$o['cards_content'],
                $o['member_name'] !== null && $o['member_name'] !== '' ? $o['member_name'] : ($o['user_id'] > 0 ? 'UID' . $o['user_id'] : '游客'),
                $o['ip'],
                date('Y-m-d H:i:s', $o['created_at']),
                $o['paid_at'] > 0 ? date('Y-m-d H:i:s', $o['paid_at']) : '',
            ]);
        }
        exit;
    }

    /** 批量销毁选中订单 */
    public function actionOrdersDestroy()
    {
        $ids = array_values(array_filter(array_map('intval', is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [])));
        if (!$ids) json_out(['code' => 1, 'msg' => '未选择订单']);
        $in = implode(',', $ids);
        $n = DB::exec("DELETE FROM orders WHERE id IN ({$in})");
        add_log('system', '管理员批量销毁订单, 共 ' . $n . ' 条');
        json_out(['code' => 0, 'msg' => '已销毁 ' . $n . ' 条订单']);
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
        DB::exec('DELETE FROM orders WHERE id = ?', [$id]);
        add_log('system', '管理员删除订单: ' . $order['sn']);
        json_out(['code' => 0, 'msg' => '删除成功']);
    }

    // ---------- 应用商店 ----------

    public function actionApps()
    {
        $type = arr_get($_GET, 'type', 'payment') === 'theme' ? 'theme' : 'payment';
        $all = Market::all($type);
        // 作者下拉
        $authors = [];
        foreach ($all as $a) {
            if (!in_array((string)$a['author'], $authors, true)) $authors[] = (string)$a['author'];
        }
        sort($authors);
        // 筛选: 名称/作者/分类页签
        $name = trim(arr_get($_GET, 'name'));
        $author = trim(arr_get($_GET, 'author'));
        $filter = (string)arr_get($_GET, 'filter', '');
        $list = array_values(array_filter($all, function ($a) use ($name, $author, $filter) {
            if ($name !== '' && mb_stripos((string)$a['title'] . ' ' . (string)$a['name'] . ' ' . (string)$a['desc'], $name) === false) return false;
            if ($author !== '' && (string)$a['author'] !== $author) return false;
            switch ($filter) {
                case 'installed': return !empty($a['installed']);
                case 'pro': return !empty($a['pro']);
                case 'free': return empty($a['pro']);
                case 'local': return !empty($a['local']);
                case 'remote': return empty($a['local']);
            }
            return true;
        }));
        View::admin('apps', [
            'type' => $type,
            'list' => $list,
            'license' => License::info(),
            'price' => $this->masterPrice(),
            'authors' => $authors,
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
            json_out(['code' => 2, 'msg' => '该应用为专业版专享, 请先在授权中心购买并激活授权码']);
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
        // 启用专业版应用需已激活专业版(停用不受限)
        $meta = Plugin::meta($row['type'] ?? 'payment', $name);
        if ($enabled === 1 && !empty($meta['pro']) && !License::isPro()) {
            json_out(['code' => 2, 'msg' => '该应用为专业版专享, 请先在授权中心激活专业版']);
        }
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

    // ---------- 授权中心(激活码授权) ----------

    public function actionLicense()
    {
        $sn = trim(arr_get($_GET, 'sn'));
        View::admin('license', [
            'license' => License::info(),
            'price' => $this->masterPrice(),
            'buySn' => preg_match('/^SP[0-9A-Z]{4,40}$/', $sn) ? $sn : '',
        ]);
    }

    /** 主控专业版定价(10分钟缓存, 失败回退99) */
    protected function masterPrice()
    {
        $cache = json_decode((string)setting('license_price_cache', ''), true);
        if (is_array($cache) && isset($cache['at'], $cache['price']) && (int)$cache['at'] > now() - 600) {
            return (string)$cache['price'];
        }
        $price = '99';
        $api = rtrim(Market::apiUrl(), '/');
        if ($api !== '') {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api . '/api/price');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $res = curl_exec($ch);
            curl_close($ch);
            $json = json_decode((string)$res, true);
            if (is_array($json) && ($json['code'] ?? 1) === 0 && isset($json['data']['price'])) {
                $price = (string)max(1, (float)$json['data']['price']);
            }
        }
        setting_set('license_price_cache', json_encode(['price' => $price, 'at' => now()]));
        return $price;
    }

    /** 购买授权码: 填邮箱 → 跳转官方主控收银台(码支付/USDT) */
    public function actionLicenseBuy()
    {
        if (License::isPro()) json_out(['code' => 1, 'msg' => '本站已激活专业版']);
        $email = trim(arr_get($_POST, 'email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            json_out(['code' => 1, 'msg' => '请填写正确的邮箱地址, 授权码将发送到该邮箱']);
        }
        $api = rtrim(Market::apiUrl(), '/');
        if ($api === '') json_out(['code' => 1, 'msg' => '未配置官方市场地址(主控域名)']);
        $returnUrl = site_url('admin.php') . '?s=/license';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api . '/api/buy');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => $email, 'return_url' => $returnUrl]));
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
        add_log('store', '跳转官方主控收银台购买授权码: ' . $json['data']['sn'] . ' (邮箱 ' . $email . ')');
        json_out(['code' => 0, 'msg' => '正在跳转主控收银台', 'redirect' => $json['data']['pay_url'], 'sn' => $json['data']['sn']]);
    }

    /** 查询购买订单: 支付完成后返回主控生成的授权码 */
    public function actionLicenseBuyCheck()
    {
        $sn = trim(arr_get($_POST, 'sn'));
        if (!preg_match('/^SP[0-9A-Z]{4,40}$/', $sn)) json_out(['code' => 1, 'msg' => '订单号无效']);
        $api = rtrim(Market::apiUrl(), '/');
        if ($api === '') json_out(['code' => 1, 'msg' => '未配置官方市场地址']);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api . '/api/order');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['sn' => $sn]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $res = curl_exec($ch);
        curl_close($ch);
        $json = json_decode((string)$res, true);
        if (!is_array($json) || ($json['code'] ?? 1) !== 0) {
            json_out(['code' => 1, 'msg' => '主控连接失败, 请稍后再试']);
        }
        json_out(['code' => 0,
            'paid' => !empty($json['data']['paid']),
            'license_key' => isset($json['data']['license_key']) ? (string)$json['data']['license_key'] : '',
        ]);
    }

    /** 验证已激活的授权码(同域名幂等, 顺带同步有效期) */
    public function actionLicenseVerify()
    {
        $key = setting('license_key');
        if ($key === '') json_out(['code' => 1, 'msg' => '本站尚未激活授权码']);
        try {
            License::activate($key);
            json_out(['code' => 0, 'msg' => License::isPro() ? '授权有效 ✓ 专业版' : '授权有效 ✓']);
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

    // ---------- 会员等级与商品分组 ----------

    public function actionMemberLevels()
    {
        $levels = DB::fetchAll('SELECT l.*, (SELECT COUNT(*) FROM users u WHERE u.level_id = l.id) AS members FROM member_levels l ORDER BY l.level ASC, l.id ASC');
        $groups = DB::fetchAll('SELECT g.*, (SELECT COUNT(*) FROM products p WHERE p.group_id = g.id) AS products FROM product_groups g ORDER BY g.id ASC');
        View::admin('member_levels', ['levels' => $levels, 'groups' => $groups]);
    }

    public function actionLevelSave()
    {
        $id = (int)arr_get($_POST, 'id');
        $name = trim(arr_get($_POST, 'name'));
        $level = max(1, (int)arr_get($_POST, 'level', 1));
        if ($name === '') json_out(['code' => 1, 'msg' => '等级名称不能为空']);
        if ($id > 0) {
            DB::update('member_levels', ['name' => $name, 'level' => $level], 'id = ?', [$id]);
        } else {
            DB::insert('member_levels', ['name' => $name, 'level' => $level, 'created_at' => now()]);
        }
        json_out(['code' => 0, 'msg' => '保存成功']);
    }

    public function actionLevelDel()
    {
        $id = (int)arr_get($_POST, 'id');
        DB::update('users', ['level_id' => 0], 'level_id = ?', [$id]);
        DB::exec('DELETE FROM member_levels WHERE id = ?', [$id]);
        json_out(['code' => 0, 'msg' => '已删除, 关联会员恢复为无等级']);
    }

    public function actionGroupSave()
    {
        $id = (int)arr_get($_POST, 'id');
        $name = trim(arr_get($_POST, 'name'));
        $minLevel = max(0, (int)arr_get($_POST, 'min_level'));
        if ($name === '') json_out(['code' => 1, 'msg' => '分组名称不能为空']);
        if ($id > 0) {
            DB::update('product_groups', ['name' => $name, 'min_level' => $minLevel], 'id = ?', [$id]);
        } else {
            DB::insert('product_groups', ['name' => $name, 'min_level' => $minLevel, 'created_at' => now()]);
        }
        json_out(['code' => 0, 'msg' => '保存成功']);
    }

    public function actionGroupDel()
    {
        $id = (int)arr_get($_POST, 'id');
        $count = (int)DB::value('SELECT COUNT(*) FROM products WHERE group_id = ?', [$id]);
        if ($count > 0) json_out(['code' => 1, 'msg' => '该分组下有 ' . $count . ' 个商品, 请先在商品管理中调整']);
        DB::exec('DELETE FROM product_groups WHERE id = ?', [$id]);
        json_out(['code' => 0, 'msg' => '删除成功']);
    }

    // ---------- 会员管理 ----------

    public function actionUsers()
    {
        [$where, $params] = $this->userFilter();
        $page = max(1, (int)arr_get($_GET, 'page', 1));
        $per = 20;
        $total = (int)DB::value("SELECT COUNT(*) FROM users u WHERE {$where}", $params);
        $list = DB::fetchAll(
            "SELECT u.*, l.name AS level_name, l.level AS level_num,
             (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.status = 1) AS orders_count
             FROM users u LEFT JOIN member_levels l ON l.id = u.level_id
             WHERE {$where} ORDER BY u.id DESC LIMIT {$per} OFFSET " . (($page - 1) * $per), $params);
        $todayStart = strtotime(date('Y-m-d'));
        $stats = [
            'total' => (int)DB::value('SELECT COUNT(*) FROM users'),
            'today' => (int)DB::value('SELECT COUNT(*) FROM users WHERE created_at >= ?', [$todayStart]),
            'banned' => (int)DB::value('SELECT COUNT(*) FROM users WHERE status = 0'),
            'paid_orders' => (int)DB::value('SELECT COUNT(*) FROM orders WHERE status = 1 AND user_id > 0'),
        ];
        View::admin('users', [
            'list' => $list, 'total' => $total, 'page' => $page, 'per' => $per, 'stats' => $stats,
            'levels' => DB::fetchAll('SELECT * FROM member_levels ORDER BY level ASC, id ASC'),
        ]);
    }

    /** 会员筛选条件(列表/统计共用) */
    protected function userFilter()
    {
        $where = '1';
        $params = [];
        $name = trim(arr_get($_GET, 'username'));
        if ($name !== '') { $where .= ' AND u.username LIKE ?'; $params[] = '%' . $name . '%'; }
        $uid = arr_get($_GET, 'uid', '');
        if ($uid !== '') { $where .= ' AND u.id = ?'; $params[] = (int)$uid; }
        $email = trim(arr_get($_GET, 'email'));
        if ($email !== '') { $where .= ' AND u.email LIKE ?'; $params[] = '%' . $email . '%'; }
        $ip = trim(arr_get($_GET, 'reg_ip'));
        if ($ip !== '') { $where .= ' AND u.reg_ip = ?'; $params[] = $ip; }
        $status = arr_get($_GET, 'status', '');
        if ($status !== '') { $where .= ' AND u.status = ?'; $params[] = (int)$status; }
        $level = arr_get($_GET, 'level', '');
        if ($level !== '') { $where .= ' AND u.level_id = ?'; $params[] = (int)$level; }
        return [$where, $params];
    }

    /** 批量操作选中会员(启用/禁用/删除) */
    public function actionUsersBatch()
    {
        $op = trim(arr_get($_POST, 'op'));
        $ids = array_values(array_filter(array_map('intval', is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [])));
        if (!$ids) json_out(['code' => 1, 'msg' => '未选择会员']);
        $in = implode(',', $ids);
        if ($op === 'enable') {
            $n = DB::exec("UPDATE users SET status = 1 WHERE id IN ({$in})");
            json_out(['code' => 0, 'msg' => '已启用 ' . $n . ' 位会员']);
        }
        if ($op === 'disable') {
            $n = DB::exec("UPDATE users SET status = 0 WHERE id IN ({$in})");
            add_log('admin', '管理员批量禁用会员 ' . $n . ' 名');
            json_out(['code' => 0, 'msg' => '已禁用 ' . $n . ' 位会员']);
        }
        if ($op === 'delete') {
            $n = DB::exec("DELETE FROM users WHERE id IN ({$in})");
            add_log('admin', '管理员批量删除会员 ' . $n . ' 名(历史订单保留)');
            json_out(['code' => 0, 'msg' => '已移除 ' . $n . ' 位会员(历史订单保留)']);
        }
        if ($op === 'level') {
            $levelId = (int)arr_get($_POST, 'level_id');
            $lvl = DB::fetch('SELECT * FROM member_levels WHERE id = ?', [$levelId]);
            if (!$lvl) json_out(['code' => 1, 'msg' => '等级不存在']);
            $n = DB::exec("UPDATE users SET level_id = {$levelId} WHERE id IN ({$in})");
            add_log('admin', '管理员批量调整会员等级: ' . $n . ' 名 → LV' . $lvl['level'] . ' ' . $lvl['name']);
            json_out(['code' => 0, 'msg' => '已将 ' . $n . ' 位会员设为 LV' . $lvl['level'] . ' ' . $lvl['name']]);
        }
        json_out(['code' => 1, 'msg' => '无效操作']);
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
        $where = '1';
        $params = [];
        $type = trim(arr_get($_GET, 'type'));
        if ($type !== '') {
            $where .= ' AND type = ?';
            $params[] = $type;
        }
        $kw = trim(arr_get($_GET, 'kw'));
        if ($kw !== '') {
            $where .= ' AND message LIKE ?';
            $params[] = '%' . $kw . '%';
        }
        $ip = trim(arr_get($_GET, 'ip'));
        if ($ip !== '') {
            $where .= ' AND ip = ?';
            $params[] = $ip;
        }
        $from = trim(arr_get($_GET, 'date_from'));
        if ($from !== '' && ($ts = strtotime($from)) > 0) {
            $where .= ' AND created_at >= ?';
            $params[] = $ts;
        }
        $to = trim(arr_get($_GET, 'date_to'));
        if ($to !== '' && ($ts = strtotime($to)) > 0) {
            $where .= ' AND created_at <= ?';
            $params[] = $ts + 86399;
        }
        // 风险评估: 按日志内容关键词分级(最多取500条后内存过滤)
        $risk = (string)arr_get($_GET, 'risk', '');
        $rows = DB::fetchAll("SELECT * FROM logs WHERE {$where} ORDER BY id DESC LIMIT 500", $params);
        $isHigh = function ($m) {
            return preg_match('/删除|清理|清空|禁用|移除|销毁|吊销|失败|拦截|错误/', (string)$m) === 1;
        };
        if ($risk === 'high' || $risk === 'low') {
            $rows = array_values(array_filter($rows, function ($r) use ($isHigh, $risk) {
                return $risk === 'high' ? $isHigh($r['message']) : !$isHigh($r['message']);
            }));
        }
        $total = count($rows);
        $list = array_slice($rows, 0, 100);
        View::admin('logs', ['list' => $list, 'total' => $total, 'type' => $type, 'risk' => $risk, 'kw' => $kw, 'ip' => $ip]);
    }

    public function actionLogsClear()
    {
        $scope = arr_get($_POST, 'scope', 'all');
        if ($scope === '30') {
            $n = DB::exec('DELETE FROM logs WHERE created_at < ?', [now() - 30 * 86400]);
            add_log('system', '管理员清理30天前日志, 共 ' . $n . ' 条');
            json_out(['code' => 0, 'msg' => '已清理 ' . $n . ' 条30天前日志']);
        }
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
        $keys = ['site_name', 'site_url', 'theme', 'announcement', 'order_timeout',
            'smtp_open', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_ssl', 'official_api',
            'verify_mode', 'captcha_open', 'turnstile_open', 'turnstile_site_key', 'turnstile_secret_key',
            'geetest_id', 'geetest_key', 'geetest_timeout', 'cdn_mode', 'member_open'];
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
