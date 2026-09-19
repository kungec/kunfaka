<?php
/**
 * 前台会员: 注册 / 登录 / 我的订单 / 退出
 * 验证: Turnstile(开启时) 或 图形验证码; 注册IP每日3次限制; 登录错误5次锁定60分钟
 */
class UserController
{
    public function actionLogin()
    {
        if (current_user_id()) redirect(u('user/orders'));
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_check();
            $account = trim(arr_get($_POST, 'username'));
            $pass = arr_get($_POST, 'password');
            if (!Captcha::verify('user', $_POST)) {
                $error = '人机验证未通过, 请重试';
            } elseif ($account === '' || $pass === '') {
                $error = '请填写账号和密码';
            } else {
                $lock = Security::loginLocked('user', $account, client_ip());
                if ($lock['locked']) {
                    $error = '密码错误次数过多, 已锁定, 请 ' . max(1, (int)ceil($lock['left'] / 60)) . ' 分钟后再试';
                } else {
                    $user = DB::fetch('SELECT * FROM users WHERE username = ? OR email = ?', [$account, $account]);
                    if (!$user || !password_verify($pass, $user['password'])) {
                        Security::loginFail('user', $account, client_ip());
                        add_log('user', '会员登录失败: ' . $account);
                        $left = Security::LOGIN_MAX_FAILS - $lock['fails'] - 1;
                        $error = '账号或密码错误' . ($left > 0 ? ", 今日还可尝试 {$left} 次" : ', 已触发锁定');
                    } elseif ((int)$user['status'] !== 1) {
                        $error = '账号已被禁用, 请联系管理员';
                    } else {
                        Security::loginClear('user', $account, client_ip());
                        session_regenerate_id(true);
                        $_SESSION['front_user_id'] = (int)$user['id'];
                        $_SESSION['front_user_name'] = $user['username'];
                        add_log('user', '会员登录成功: ' . $user['username']);
                        redirect(u('user/orders'));
                    }
                }
            }
        }
        View::theme('login', ['error' => $error, 'pageTitle' => '登录 / 注册']);
    }

    public function actionRegister()
    {
        if (current_user_id()) redirect(u('user/orders'));
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_check();
            $username = trim(arr_get($_POST, 'username'));
            $email = trim(arr_get($_POST, 'email'));
            $pass = arr_get($_POST, 'password');
            $pass2 = arr_get($_POST, 'password2');
            $ip = client_ip();

            do {
                if (!Captcha::verify('user', $_POST)) { $error = '人机验证未通过, 请重试'; break; }
                $reg = Security::regBlocked($ip);
                if ($reg['blocked']) {
                    $error = '该IP注册过于频繁(每24小时最多' . Security::REG_LIMIT . '次), 请 ' . (int)ceil($reg['left'] / 3600) . ' 小时后再试';
                    break;
                }
                if (!preg_match('/^[\w\x{4e00}-\x{9fa5}]{3,20}$/u', $username)) { $error = '用户名需3-20位(字母/数字/下划线/中文)'; break; }
                if (strlen($pass) < 6) { $error = '密码至少6位'; break; }
                if ($pass !== $pass2) { $error = '两次输入的密码不一致'; break; }
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $error = '邮箱格式不正确'; break; }
                if (DB::value('SELECT id FROM users WHERE username = ?', [$username])) { $error = '用户名已被占用'; break; }
                if ($email !== '' && DB::value('SELECT id FROM users WHERE email = ?', [$email])) { $error = '该邮箱已注册'; break; }

                $uid = (int)DB::insert('users', [
                    'username' => $username,
                    'password' => password_hash($pass, PASSWORD_DEFAULT),
                    'email' => $email,
                    'created_at' => now(),
                    'reg_ip' => $ip,
                ]);
                Security::regRecord($ip);
                session_regenerate_id(true);
                $_SESSION['front_user_id'] = $uid;
                $_SESSION['front_user_name'] = $username;
                add_log('user', '新会员注册: ' . $username . ($email !== '' ? ' <' . $email . '>' : ''));
                redirect(u('user/orders'));
            } while (false);
        }
        View::theme('login', ['error' => $error, 'pageTitle' => '登录 / 注册', 'register' => true]);
    }

    public function actionLogout()
    {
        unset($_SESSION['front_user_id'], $_SESSION['front_user_name']);
        redirect(u('home/index'));
    }

    /** 我的订单 */
    public function actionOrders()
    {
        $uid = current_user_id();
        if (!$uid) redirect(u('user/login'));
        $orders = DB::fetchAll('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 100', [$uid]);
        View::theme('myorders', [
            'orders' => $orders,
            'categories' => DB::fetchAll('SELECT * FROM categories ORDER BY sort ASC, id ASC'),
            'pageTitle' => '我的订单',
        ]);
    }
}
