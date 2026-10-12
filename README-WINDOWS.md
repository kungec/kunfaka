# 坤发卡 · Windows Server 2016 + IIS + PHP 7.4 + MySQL 5.7 部署指南

本仓库代码已内置 **Windows/IIS 兼容修复**（见文末改动清单），Linux 宝塔环境同样适用。

---

## 一、环境清单

| 组件 | 要求 | 说明 |
|---|---|---|
| 操作系统 | Windows Server 2016 | 2019/2022 同样适用 |
| Web服务器 | IIS 10 | 需勾选 **CGI** 功能 |
| IIS模块 | URL Rewrite 2.0 | 伪静态必需，[官方下载](https://www.iis.net/downloads/microsoft/url-rewrite) |
| PHP | 7.4.x **VC15 x64 Non Thread Safe** | FastCGI 必须用 NTS 版 |
| MySQL | 5.7.x | 5.6 / 8.0 / MariaDB 亦可 |

## 二、安装 IIS 与 PHP

### 1. 安装 IIS（含 CGI）
服务器管理器 → 添加角色和功能 → Web 服务器(IIS) → 角色服务里勾选：
**应用程序开发** → **CGI**（其余保持默认）→ 安装。

### 2. 安装 PHP 7.4（NTS x64）
1. 下载 `php-7.4.x-nts-Win32-VC15-x64.zip`（windows.php.net 下载存档），解压到 `C:\php74`。
2. 复制 `php.ini-production` 为 `php.ini`，修改：
   ```ini
   extension_dir = "C:\php74\ext"
   cgi.fix_pathinfo = 1
   date.timezone = Asia/Shanghai

   extension=php_pdo_mysql.dll
   extension=php_curl.dll
   extension=php_openssl.dll
   extension=php_mbstring.dll
   extension=php_gd2.dll
   extension=php_fileinfo.dll
   ```
   > **不要写** `extension=php_zip.dll` 与 `extension=php_bcmath.dll`——PHP 7.4 的 zip 与 bcmath
   > 已**内置**（`php -m` 直接可见），额外放第三方 DLL 反而启动报错。
   > GD 在 7.4 的文件名是 **php_gd2.dll**（8.x 才叫 php_gd.dll），勿混用 PHP 8 的扩展文件。
3. 把 `C:\php74` 加入系统 PATH（可选，方便命令行执行 php）。
4. 验证：`C:\php74\php.exe -v`、`C:\php74\php.exe -m`（确认上面扩展都在）。

### 3. 把 PHP 注册进 IIS
推荐用 **PHP Manager for IIS**（IIS 官方扩展）：
IIS 管理器 → 服务器节点 → PHP Manager → Register new PHP version → 选择 `C:\php74\php-cgi.exe`。

> 若不装 PHP Manager，也可手动：站点 → 处理程序映射 → 添加模块映射 →
> 请求路径 `*.php` → 模块 `FastCgiModule` → 可执行文件 `C:\php74\php-cgi.exe`。

### 4. 安装 URL Rewrite 模块
下载安装 URL Rewrite 2.0（安装完 IIS 管理器里出现 "URL 重写" 图标即成功）。

## 三、安装 MySQL 5.7

1. MySQL Installer 安装 MySQL Server 5.7（Server only 即可），记住 root 密码。
2. 建库（字符集务必 utf8mb4）：
   ```sql
   CREATE DATABASE kunfaka DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   ```
   也可在安装向导里直接填已存在的库名，向导会自动建表。

## 四、部署站点

1. 解压本包到 `C:\inetpub\wwwroot\kunfaka`（站点根下直接是 index.php、web.config 等）。
2. IIS 管理器 → 网站 → 添加网站：
   - 物理路径：`C:\inetpub\wwwroot\kunfaka`
   - 端口：如 `8080`（或绑定你的域名）
   - 应用程序池：默认即可（.NET CLR 版本选"无托管代码"更佳）
3. **目录权限（关键）**：给应用池身份对站点目录的"修改"权限：
   ```bat
   icacls C:\inetpub\wwwroot\kunfaka /grant "IIS_IUSRS:(OI)(CI)M" /T
   ```
   （若自定义了应用池名称，改授 `IIS AppPool\你的应用池名`）
   安装向导需要写 `data/`、根目录（重命名 admin.php）；运行期需要写 `data/`、`uploads/`。
4. 浏览器访问 `http://localhost:8080/install.php`：
   - **之前在这台机器装失败过的**：先删除 `data\config.php` 与 `data\install.lock`，再进向导；
   - 第1步环境检测 → 第2步协议 → 第3步填数据库信息（地址 `127.0.0.1`、端口 `3306`、库名/账号/密码）+ 管理员账号密码 → 完成。
5. 收藏完成页展示的**随机后台入口**（形如 `admin_xxxxxx.php`），此后 `admin.php` 不再存在。

## 五、配置计划任务（重要）

管理员命令行执行（路径按实际调整）：
```bat
schtasks /create /tn "kunfaka_cron" /tr "\"C:\php74\php.exe\" \"C:\inetpub\wwwroot\kunfaka\cron.php\"" /sc minute /mo 1 /ru SYSTEM /f
```
作用：未支付订单超时自动关闭、链上支付（USDT/BTC/ETH/XMR）到账轮询。
也可用访问URL方式：`http://你的域名/cron.php?key=YF_KEY的值`（YF_KEY 见 `data\config.php`）。

## 六、安全收尾

1. 安装完成后**删除 `install.php`**；
2. 确认 `data\web.config`（整目录拒绝访问）与 `uploads\web.config`（禁脚本执行）在位 —— 仓库已自带；
3. 不要把 `data\config.php` 提交到任何公开仓库。

## 七、常见问题

| 现象 | 处理 |
|---|---|
| 访问报 500 / 404.3 | PHP 未注册进 IIS 或未装 CGI 功能，见第二步 |
| **HTTP 500.19 提示"重复集合项"(0x800700b7)** | 子级 web.config 与服务器级默认文档重复所致——新版包已移除该块；旧包手动删除 web.config 中整个 `<defaultDocument>...</defaultDocument>` 块即可 |
| 伪静态不生效(内页404) | 未安装 URL Rewrite 模块 |
| 安装向导提示 data 不可写 | 未授予目录"修改"权限，见第四步第3点 |
| `Cannot redeclare e()` 致命错误 | 本修复已解决；若用旧版代码，删除 `data\config.php` 与 `data\install.lock` 后重新安装 |
| 启动报 PHP Startup 扩展加载错误 | ini 里写了与 PHP 版本不匹配的扩展 DLL——7.4 删掉 php_zip/php_bcmath 行（内置），GD 用 php_gd2.dll |
| 应用商店在线安装失败 | 确认 `php -m` 列表中含 zip（7.4 内置；缺失多为 PHP 版本/线程安全不匹配） |
| 验证码图片不显示 | 确认 `php -m` 列表中含 gd（7.4 启用 php_gd2.dll） |
| 发信失败 | 确认 `php_openssl.dll` 已启用（SMTP SSL/TLS） |

---

## 八、Windows/IIS 兼容修复清单

1. **修复 install.php 致命错误 `Cannot redeclare e()`**（本次 Windows 部署报错的根因）：
   `data/config.php` 已存在时，`bootstrap.php` 不再提前返回而是继续加载 `functions.php`，
   随后 `install.php` 又用普通 `require` 加载一次 → `e()` 重复声明。
   - `app/bootstrap.php`：头部增加 `YF_BOOTSTRAP_LOADED` 防重复加载守护；config/functions/DB/View 全部改为 `require_once`；安装模式下 `DB::init()` 失败不再中断向导。
   - `app/functions.php`：头部增加 `YF_FUNCTIONS_LOADED` 防重复加载守护（双保险）。
   - `install.php`：`functions.php`、`config.php` 改为 `require_once`。
2. **IIS 支持**：新增根目录 `web.config`（伪静态，等价 .htaccess）、`data/web.config`（等价 data/.htaccess 拒绝访问）、`uploads/web.config`（禁脚本执行），并补齐 `uploads/.htaccess`。
3. 原有 Linux/Apache/Nginx 环境行为不受任何影响（守护代码零开销）。

适用范围：PHP 7.4 ~ 8.3（官方支持区间），MySQL 5.6 / 5.7 / 8.0 / MariaDB。
