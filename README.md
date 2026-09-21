<div align="center">

# 坤发卡 · 全自动发卡系统

**买家付款成功的那一刻, 卡密已经发出去了。**

![PHP](https://img.shields.io/badge/PHP-7.4%20~%208.3-777bb4)
![MySQL](https://img.shields.io/badge/MySQL-5.6%2B-4479a1)
![Docker](https://img.shields.io/badge/Docker-✓-2496ED)
![License](https://img.shields.io/badge/License-MIT-green)

**程序完全免费开源** · 无 Composer 依赖 · 宝塔 3 分钟部署 · 手机电脑全适配

[功能总览](#-功能总览) · [快速开始](#-快速开始) · [支付插件](#-支付插件) · [常见问题](#-常见问题) · [交流群](#-交流支持)

</div>

---

## 一图看懂

| | 坤发卡能做到 |
|---|---|
| 🤖 **全自动发货** | 支付回调秒级发卡, 卡密即时展示 + 邮件送达, 7×24 无人值守 |
| 💳 **收款全兼容** | 个人码聚合(码支付/易支付) · 支付宝/微信官方直连 · Stripe 信用卡 |
| ⛓️ **加密币免挂** | USDT(TRC20/BEP20/ERC20/Polygon) · BTC · ETH · XMR, 唯一金额对账, 到账即发 |
| 🛍️ **商品全类型** | 虚拟卡密 · 账号租售 · 兑换码 · 实物邮寄(收件人/地址字段) |
| 👥 **会员体系** | 前台注册登录 · 会员等级与分组可见性 · 联系方式预填 |
| 🎨 **5 套主题** | 云商城/初音物语免费, 3 套专业版主题商店分发, 亮暗双模式 |
| 🧩 **应用商店** | 插件/主题在线安装升级, 付费应用按域名加密分发 |
| 🔄 **自动更新** | 后台检测新版本, 可开启全自动升级, 配置与附件升级不丢失 |

## 🚀 快速开始(宝塔面板)

1. **建站**: 宝塔 → 添加站点(PHP 7.4+) → 创建 MySQL 数据库
2. **上传**: 发行包解压到站点根目录, 访问域名进入安装向导
3. **收尾**: 收藏随机后台入口(形如 `admin_x7k9q2.php`, 只显示一次) → 删除 `install.php` → Nginx 加 `location ^~ /data/ { deny all; }` → 配置每分钟 cron:

```bash
php /www/wwwroot/你的站点目录/cron.php
```

> cron 负责: 未支付订单超时关闭 + 链上到账轮询(买家支付页也会触发, cron 实时性更佳)

<details>
<summary><b>🐳 Docker 部署</b></summary>

```bash
docker compose up -d --build
```

访问 `http://IP:8080/install.php`, 数据库填: 地址 `db` / 库名 `kunfaka` / 用户 `kunfaka` / 密码 `kunfaka123`。自带 cron 服务与数据卷, 升级镜像不丢配置。

</details>

## 💳 支付插件(后台商店一键安装)

| 免费 | ⭐ 专业版(99 元会员畅享) |
|---|---|
| 码支付 · 易支付 | USDT 免挂(TRC20) |
| 支付宝当面付 · 支付宝官方 | USDT 多链(BEP20 / ERC20 / Polygon) |
| 微信官方 V3 Native | BTC / ETH / XMR |
| Stripe 信用卡 | EPUSDT 自建网关 |

> 链上免挂原理: 每笔订单生成唯一金额, 公链接口到账精准对账后自动发货 —— 不挂机、不上私钥。

## 🛡️ 安全内建

- **人机验证三选一**: 极验 v4(页面内联按钮) / Cloudflare Turnstile / 图形码, 覆盖注册、登录、后台、下单四道关口
- **频控防刷**: 注册 IP 限制、下单频次限制、登录爆破锁定
- **数据安全**: CSRF 全站令牌、支付回调双验签 + 金额校验、64 位高熵订单号
- **分发安全**: 付费应用按「域名 + 授权码」动态加密, 泄露包无法使用
- **质量保障**: 渗透测试 32 项断言 + e2e 自动化 380 项断言

## 🎨 主题

| 主题 | 风格 | 权限 |
|---|---|---|
| 云商城(默认) | 现代商城 · 白卡渐变, 亮/暗切换 | 免费 |
| 初音物语 | 二次元 · 粉蓝渐变樱花动效 | 免费 |
| 蔚蓝 / 暖橙 / 星野 | 经典电商 / 活力橙 / 玻璃拟态 | ⭐ 专业版 |

自定义主题: 复制 `themes/store` 改 CSS 即可, 视图自动继承。

## ❓ 常见问题

<details>
<summary><b>忘了后台入口</b></summary>
查看 <code>data/config.php</code> 的 <code>YF_ADMIN_ENTRY</code>, 即入口文件名。
</details>

<details>
<summary><b>USDT 不到账</b></summary>
地址须 TRC20(T 开头)、金额与页面完全一致、每分钟 cron 已配置。
</details>

<details>
<summary><b>支付回调失败</b></summary>
系统设置「网站地址」填完整 https 域名, 确保公网可达回调地址。
</details>

<details>
<summary><b>验证码组件不显示</b></summary>
极验 ID/Key 勿填反, 域名白名单含本站; 密钥不全自动降级图形码。
</details>

<details>
<summary><b>能自己开发插件/主题吗</b></summary>
能。插件放 <code>plugins/</code>, 主题复制 <code>themes/store</code> 改样式, 商店自动识别。
</details>

## 💬 交流支持

官方 QQ 群: **[307386089](https://qm.qq.com/cgi-bin/qm/qr?k=307386089)** —— 部署协助、使用答疑、插件主题咨询。

## 📄 开源协议

[MIT](LICENSE) —— 程序本体完全免费, 核心功能永不加付费墙; 收费仅限应用商店内的付费插件与主题。
