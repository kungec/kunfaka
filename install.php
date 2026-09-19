<?php
/**
 * 坤发卡 - 安装向导
 * 宝塔部署: 压缩包解压到站点根目录 → 访问 http://域名/ 自动进入本向导
 * 只需填写数据库账号密码 + 设置管理员账号密码, 其余全自动完成:
 *   1. 建表并写入初始数据
 *   2. 生成 data/config.php
 *   3. 将 admin.php 重命名为随机文件名(后台安全入口), 地址安装完成时展示
 */
define('YF_INSTALLER', true);
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/functions.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$lockFile = YF_DATA . '/install.lock';
$installed = is_file($lockFile);

// 防重装接管: 若配置存在且数据库中已有管理员, 无论lock是否存在都拒绝安装(GET/POST均拦截)
$blocked = false;
if (is_file(YF_DATA . '/config.php')) {
    try {
        require YF_DATA . '/config.php';
        $chk = new PDO('mysql:host=' . YF_DB_HOST . ';port=' . YF_DB_PORT . ';dbname=' . YF_DB_NAME . ';charset=utf8mb4', YF_DB_USER, YF_DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $c = (int)$chk->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        if ($c > 0) $blocked = true;
    } catch (Exception $ex) {
        $blocked = false; // 数据库不可达时按lock文件判断
    }
}
if ($blocked || $installed) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        exit('{"code":1,"msg":"系统已安装, 已拒绝重装"}');
    }
    header('Content-Type: text/html; charset=utf-8');
    exit('<div style="font-family:sans-serif;max-width:520px;margin:80px auto;text-align:center;color:#374151">'
        . '<h2>✦ 坤发卡已安装</h2><p>系统已完成安装。如需重装, 请先删除 data/install.lock 与 data/config.php。</p>'
        . '<p><a href="index.php" style="color:#6366f1">返回首页</a></p></div>');
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';

$envItems = [
    ['PHP版本 >= 7.4', version_compare(PHP_VERSION, '7.4.0', '>='), '当前 ' . PHP_VERSION],
    ['PDO MySQL 扩展', extension_loaded('pdo_mysql'), '连接MySQL必需'],
    ['openssl 扩展', extension_loaded('openssl'), '支付插件签名必需'],
    ['curl 扩展', extension_loaded('curl'), '支付插件与市场必需'],
    ['zip 扩展', extension_loaded('zip'), '应用商店在线安装必需'],
    ['bcmath 扩展', extension_loaded('bcmath'), 'USDT金额计算必需'],
    ['data目录可写', is_writable(YF_DATA), '保存配置文件必需'],
    ['admin.php可重命名', is_writable(__DIR__), '生成随机后台入口必需'],
];
$envOk = true;
foreach ($envItems as $it) if (!$it[1]) $envOk = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim(arr_get($_POST, 'db_host', '127.0.0.1'));
    $port = trim(arr_get($_POST, 'db_port', '3306'));
    if ($host === '') $host = '127.0.0.1';
    if ($port === '') $port = '3306';
    $name = trim(arr_get($_POST, 'db_name'));
    $user = trim(arr_get($_POST, 'db_user'));
    $pass = arr_get($_POST, 'db_pass');
    $adminUser = trim(arr_get($_POST, 'admin_user'));
    $adminPass = arr_get($_POST, 'admin_pass');

    try {
        if ($name === '' || $user === '') throw new Exception('请填写完整数据库信息');
        if (strlen($adminUser) < 3 || strlen($adminPass) < 6) throw new Exception('管理员账号至少3位, 密码至少6位');
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $name)) throw new Exception('数据库名只能包含字母/数字/下划线(最长64位)');
        if (!preg_match('/^[A-Za-z0-9_.\-]{1,120}$/', $host)) throw new Exception('数据库主机地址格式不正确');
        if (!preg_match('/^[0-9]{1,5}$/', $port) || (int)$port < 1 || (int)$port > 65535) throw new Exception('数据库端口格式不正确');
        if (!preg_match('/^[A-Za-z0-9_\x{4e00}-\x{9fa5}]{3,20}$/u', $adminUser)) throw new Exception('管理员账号需为3-20位字母/数字/下划线/中文');
        if ($installed) throw new Exception('系统已安装, 如需重装请先删除 data/install.lock');

        $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        // 数据库不存在则尝试创建(宝塔授权一般允许)
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $pdo->exec("USE `{$name}`");

        // 建表(剥离注释行后按分号执行)
        $sql = file_get_contents(__DIR__ . '/database.sql');
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt === '') continue;
            $pdo->exec($stmt);
        }

        // 管理员账号(仅当无账号时创建; 首个管理员为超级管理员)
        $cnt = $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        if ((int)$cnt === 0) {
            $st = $pdo->prepare('INSERT INTO admin_users (username, password, nickname, role, created_at) VALUES (?, ?, ?, ?, ?)');
            $st->execute([$adminUser, password_hash($adminPass, PASSWORD_DEFAULT), $adminUser, 'super', time()]);
        }

        // 随机后台入口: 复制 admin.php → admin_xxxxxx.php, 并移除原入口
        $adminEntry = 'admin_' . strtolower(bin2hex(random_bytes(3))) . '.php';
        if (!@copy(__DIR__ . '/admin.php', __DIR__ . '/' . $adminEntry)) {
            $adminEntry = 'admin.php'; // 重命名失败时退回默认入口
        } else {
            @unlink(__DIR__ . '/admin.php');
        }

        // 写配置
        $cfg = "<?php\n"
            . "define('YF_DB_HOST', '{$host}');\n"
            . "define('YF_DB_PORT', '{$port}');\n"
            . "define('YF_DB_NAME', '{$name}');\n"
            . "define('YF_DB_USER', '{$user}');\n"
            . "define('YF_DB_PASS', '" . addslashes($pass) . "');\n"
            . "define('YF_ADMIN_ENTRY', '{$adminEntry}');\n"
            . "define('YF_KEY', '" . bin2hex(random_bytes(16)) . "');\n";
        file_put_contents(YF_DATA . '/config.php', $cfg);
        file_put_contents($lockFile, date('Y-m-d H:i:s'));

        header('Location: install.php?step=4&entry=' . urlencode($adminEntry));
        exit;
    } catch (Exception $ex) {
        $error = $ex->getMessage();
        $step = 3;
    }
}

$scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$hostHeader = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$siteBase = $scheme . '://' . $hostHeader;
$doneEntry = isset($_GET['entry']) ? preg_replace('/[^a-z0-9_\-\.]/', '', $_GET['entry']) : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>坤发卡 · 安装向导</title>
<style>
    body { font-family: "PingFang SC","Microsoft YaHei",sans-serif; background: linear-gradient(135deg,#312e5f,#6366f1); min-height:100vh; margin:0; display:flex; align-items:center; justify-content:center; color:#1f2937; }
    .box { background:#fff; border-radius:16px; width:92%; max-width:560px; padding:32px; box-shadow:0 20px 60px rgba(0,0,0,.3); }
    h1 { font-size:22px; margin:0 0 4px; } .sub { color:#6b7280; font-size:13px; margin-bottom:20px; }
    .steps { display:flex; gap:6px; margin-bottom:20px; }
    .steps span { flex:1; text-align:center; font-size:12px; padding:7px 0; border-radius:999px; background:#eef1f6; color:#6b7280; }
    .steps span.on { background:#6366f1; color:#fff; font-weight:600; }
    label { display:block; font-size:13px; color:#6b7280; margin:12px 0 5px; }
    input { width:100%; padding:10px 12px; border:1px solid #dde1ea; border-radius:8px; font-size:14px; box-sizing:border-box; outline:none; }
    input:focus { border-color:#6366f1; }
    button { margin-top:18px; width:100%; padding:11px; border:none; border-radius:8px; background:#6366f1; color:#fff; font-size:15px; cursor:pointer; }
    .err { background:#fde3e3; color:#b91c1c; padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:12px; }
    .env { width:100%; border-collapse:collapse; font-size:13.5px; }
    .env td { padding:8px 4px; border-bottom:1px solid #f0f1f4; }
    .ok { color:#0b7d58; font-weight:600; } .no { color:#b91c1c; font-weight:600; }
    .done { text-align:center; padding:12px 0; }
    .done .big { font-size:46px; }
    .admin-url { background:#eef4ff; border:1px dashed #6366f1; border-radius:10px; padding:12px; margin:14px 0; font-size:14px; word-break:break-all; font-weight:700; color:#4338ca; }
    details { margin-top:10px; font-size:13px; color:#6b7280; }
    details input { margin-top:4px; }
    a.go { display:inline-block; margin-top:14px; padding:10px 26px; background:#6366f1; color:#fff; border-radius:8px; text-decoration:none; }
    a.go.gray { background:#eef1f6; color:#4b5563; }
    p.tip { font-size:12.5px; color:#6b7280; line-height:1.8; }
</style>
</head>
<body>
<div class="box">
    <h1>✦ 坤发卡</h1>
    <div class="sub">全自动发卡系统 · 安装向导</div>
    <div class="steps">
        <span class="<?= $step === 1 ? 'on' : '' ?>">1 环境检测</span>
        <span class="<?= $step === 2 ? 'on' : '' ?>">2 使用协议</span>
        <span class="<?= in_array($step, [3, 4]) ? 'on' : '' ?>">3 数据库配置</span>
    </div>

    <?php if ($step === 1): ?>
        <table class="env">
            <?php foreach ($envItems as $it): ?>
                <tr><td><?= htmlspecialchars($it[0]) ?></td><td style="text-align:right" class="<?= $it[1] ? 'ok' : 'no' ?>"><?= $it[1] ? '通过' : '不通过 · ' . htmlspecialchars($it[2]) ?></td></tr>
            <?php endforeach; ?>
        </table>
        <?php if ($installed): ?><div class="err" style="margin-top:14px">系统已安装。如需重装, 请先删除 data/install.lock 文件。</div><?php endif; ?>
        <?php if (!$envOk): ?>
            <div class="err" style="margin-top:14px">请先解决不通过项: 宝塔 → 软件商店 → PHP → 设置 → 安装对应扩展后重试。</div>
        <?php endif; ?>
        <button onclick="location.href='install.php?step=2'" <?= $envOk ? '' : 'disabled' ?>>下一步</button>

    <?php elseif ($step === 2): ?>
        <div style="font-size:13.5px;line-height:1.9;color:#4b5563">
            <p>1. 坤发卡程序本体<b>完全免费</b>, 全部核心功能无任何限制, 收费内容仅为应用商店内的付费插件与主题;</p>
            <p>2. 本程序仅用于合法合规的商品销售, 请勿用于违反法律法规的用途;</p>
            <p>3. 支付插件需自行申请对应商户资质, 遵守支付平台规则;</p>
            <p>4. 安装完成后后台地址为<b>随机生成的安全入口</b>, 请在完成页面及时收藏;</p>
            <p>5. 商店付费内容(如USDT免挂支付插件)需开通99元专业版会员后免费下载使用。</p>
        </div>
        <button onclick="location.href='install.php?step=3'">同意并继续</button>

    <?php elseif ($step === 3): ?>
        <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <p class="tip">数据库信息可在 宝塔 → 数据库 页面查看, 照抄填写即可。</p>
        <form method="post" action="install.php?step=3" autocomplete="off">
            <label>数据库名(宝塔建站时自动创建的那个)</label><input name="db_name" placeholder="例如 yunfaka" required>
            <label>数据库用户名</label><input name="db_user" required>
            <label>数据库密码</label><input name="db_pass" type="password">
            <label>设置管理员账号(至少3位)</label><input name="admin_user" required>
            <label>设置管理员密码(至少6位)</label><input name="admin_pass" type="password" required>
            <details>
                <summary>高级选项(默认无需修改)</summary>
                <label>数据库地址</label><input name="db_host" value="127.0.0.1">
                <label>端口</label><input name="db_port" value="3306">
            </details>
            <button type="submit">开始安装</button>
        </form>

    <?php else: ?>
        <div class="done">
            <div class="big">🎉</div>
            <h2 style="margin:10px 0">安装完成!</h2>
            <?php if ($doneEntry): ?>
                <p class="tip">你的<b>随机后台入口</b>已生成(请立即收藏, 该地址仅在此时完整展示一次):</p>
                <div class="admin-url"><?= htmlspecialchars($siteBase . '/' . $doneEntry) ?></div>
            <?php endif; ?>
            <p class="tip">
                建议收尾: ① 删除 install.php 文件 ② 宝塔计划任务添加每分钟执行<br>
                <b>php <?= htmlspecialchars(__DIR__) ?>/cron.php</b> (订单过期+USDT到账轮询)<br>
                ③ Nginx用户在站点配置加入: location ^~ /data/ { deny all; }
            </p>
            <?php if ($doneEntry): ?>
                <a class="go" href="<?= htmlspecialchars($doneEntry) ?>">进入管理后台</a>
            <?php endif; ?>
            <a class="go gray" href="index.php">访问前台首页</a>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
