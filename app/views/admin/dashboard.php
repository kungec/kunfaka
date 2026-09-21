<?php $activeMenu = 'dashboard';
/* 同期对比文案 */
$cmp = function ($nowV, $base) {
    $diff = round($nowV - $base, 2);
    if (abs($diff) < 0.005) return '<span class="dim">持平</span>';
    return $diff > 0
        ? '<span style="color:var(--ok)">↑ ¥' . nf($diff) . '</span>'
        : '<span style="color:var(--bad)">↓ ¥' . nf(abs($diff)) . '</span>';
};
?>
<style>
/* 公告条 */
.notice-strip{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:var(--input-bg);border:1px solid var(--input-border);border-radius:12px;padding:10px 16px;margin-bottom:14px;font-size:12.5px;color:var(--text2)}
.notice-strip .ns-badge{flex:none;font-size:10.5px;font-weight:700;padding:2px 8px;border-radius:6px;background:var(--ok-bg);color:var(--ok)}
.notice-strip .ns-t{min-width:0;flex:1;line-height:1.6}
.notice-strip .ns-t b{color:var(--text)}
.notice-strip .ns-d{flex:none;color:var(--muted);font-size:11px}
/* 欢迎条 */
.welcome{display:flex;align-items:center;gap:14px;flex-wrap:wrap;background:var(--input-bg);border:1px solid var(--input-border);border-radius:14px;padding:14px 20px;margin-bottom:14px}
.welcome .w-ava{width:44px;height:44px;border-radius:13px;background:var(--text);color:var(--bg);display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:19px;flex:none}
.welcome .w-id b{display:block;font-size:14.5px;font-weight:800}
.welcome .w-id small{display:block;font-size:11px;color:var(--muted);margin-top:2px}
.welcome .w-login{display:flex;gap:22px;flex-wrap:wrap;margin-left:auto;font-size:11.5px;color:var(--muted)}
.welcome .w-login b{display:block;color:var(--text2);font-size:12px;font-weight:600;margin-top:1px}
.welcome .w-acts{display:flex;gap:8px;flex:none}
/* KPI 卡 */
.kpi-row{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:14px}
@media(max-width:1200px){.kpi-row{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:640px){.kpi-row{grid-template-columns:1fr}}
.kpi{background:var(--card);border:1px solid var(--input-border);border-radius:14px;padding:16px 18px 14px;position:relative;overflow:hidden}
.kpi::after{content:"";position:absolute;left:0;top:0;bottom:0;width:3px;background:linear-gradient(180deg,#6366f1,#a855f7);opacity:.85}
.kpi.k2::after{background:linear-gradient(180deg,#0ea5e9,#22d3ee)}
.kpi.k3::after{background:linear-gradient(180deg,#f59e0b,#f97316)}
.kpi.k4::after{background:linear-gradient(180deg,#10b981,#34d399)}
.kpi .k-label{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px}
.kpi .k-val{font-size:26px;font-weight:800;letter-spacing:-.5px;margin:6px 0 4px}
.kpi .k-sub{font-size:11.5px;color:var(--muted)}
/* 主区双栏 */
.dash-main{display:grid;grid-template-columns:minmax(0,2.1fr) minmax(250px,1fr);gap:14px;align-items:start}
@media(max-width:1100px){.dash-main{grid-template-columns:1fr}}
.dash-col{display:flex;flex-direction:column;gap:14px;min-width:0}
.dash-col .card{margin-bottom:0}
.dash-main .card,.kpi{animation:dashIn .45s cubic-bezier(.2,.7,.3,1) backwards}
@keyframes dashIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
/* 待处理列表 */
.todo-list{display:flex;flex-direction:column}
.todo-list a{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 2px;border-bottom:1px dashed var(--input-border);text-decoration:none;color:var(--text);font-size:12.5px;transition:.12s}
.todo-list a:last-child{border-bottom:none}
.todo-list a:hover{color:var(--text2)}
.todo-list a .n{font-size:16px;font-weight:800}
.todo-list a .arr{color:var(--muted);font-size:12px}
/* 趋势/经营 */
.trend-head,.biz-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px}
.trend-head h3,.biz-head h3{margin:0}
.seg{display:inline-flex;border:1px solid var(--input-border);border-radius:8px;overflow:hidden}
.seg button{border:none;background:transparent;color:var(--text2);font-size:12px;font-weight:600;padding:5px 14px;cursor:pointer;font-family:inherit}
.seg button.on{background:var(--text);color:var(--bg)}
.range-label{font-size:11.5px;color:var(--muted)}
.m-tabs{display:flex;gap:26px;margin:14px 0 4px;border-bottom:1px solid var(--input-border)}
.m-tab{background:none;border:none;padding:0 2px 10px;cursor:pointer;font-family:inherit;text-align:left;border-bottom:2px solid transparent;margin-bottom:-1px;color:var(--muted)}
.m-tab .m-l{display:block;font-size:12px}
.m-tab .m-v{display:block;font-size:19px;font-weight:800;color:var(--text);margin-top:2px}
.m-tab.on{border-bottom-color:var(--primary)}
.m-tab.on .m-l{color:var(--text)}
.t-chart{display:flex;align-items:flex-end;height:190px;gap:4px;padding-top:18px;position:relative}
.t-chart .t-empty{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:12px}
.t-col{flex:1;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:6px;min-width:0}
.t-col .bar{width:min(36px,72%);background:linear-gradient(180deg,#818cf8,#4f46e5);border-radius:5px 5px 0 0;min-height:2px;transition:height .25s}
.t-col .bar:hover{background:linear-gradient(180deg,#a5b4fc,#6366f1)}
.t-col .v{font-size:10px;color:var(--muted)}
.t-x{display:flex;gap:4px;margin-top:8px}
.t-x span{flex:1;text-align:center;font-size:10.5px;color:var(--muted);min-width:0;overflow:hidden;white-space:nowrap}
.biz-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;padding:8px 2px 2px}
.biz-metrics .bm .l{font-size:12px;color:var(--muted)}
.biz-metrics .bm .v{font-size:22px;font-weight:800;margin-top:4px}
/* 更新横幅 */
.update-banner{display:flex;align-items:center;gap:12px;flex-wrap:wrap;background:linear-gradient(90deg,rgba(99,102,241,.18),rgba(139,92,246,.12));border:1px solid rgba(129,140,248,.4);border-radius:12px;padding:12px 18px;margin-bottom:14px;position:relative;z-index:1}
.ub-ico{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:800;flex:none}
.ub-txt{flex:1;min-width:240px;font-size:13px;color:var(--text2)}
.ub-txt b{color:var(--text)}
.ub-btn{height:36px;padding:0 18px;border:none;border-radius:9px;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;font-size:13px;font-weight:700;cursor:pointer}
</style>

<?php if (!empty($updateInfo)): ?>
<div class="update-banner">
    <span class="ub-ico">⬆</span>
    <span class="ub-txt"><b>发现新版本 v<?= e($updateInfo['version']) ?></b><?= setting('auto_update', '0') === '1' ? ' · 自动更新已开启, 系统将自动完成升级' : ' · ' . e(mb_substr($updateInfo['desc'] ?? '', 0, 70)) ?></span>
    <?php if (setting('auto_update', '0') !== '1'): ?><form method="post" action="<?= au('update_run') ?>" data-ajax style="margin-left:auto"><?= csrf_field() ?><button class="ub-btn" type="submit">立即更新</button></form><?php endif; ?>
</div>
<?php endif; ?>

<?php if ($officialNotices): ?>
<div class="notice-strip">
    <span class="ns-badge">官方公告</span>
    <?php foreach ($officialNotices as $i => $n): $isArr = is_array($n); ?>
        <span class="ns-t" <?= $i > 0 ? 'style="display:none"' : '' ?>>
            <?php if ($isArr): ?><b><?= e($n['title']) ?></b><?= trim((string)($n['content'] ?? '')) !== '' ? ' —— ' . e(mb_substr($n['content'], 0, 80)) : '' ?><?php else: ?><?= e($n) ?><?php endif; ?>
        </span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="welcome">
    <span class="w-ava">坤</span>
    <div class="w-id">
        <b><?= e(isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : '管理员') ?><?php if (current_admin() && current_admin()['role'] === 'super'): ?> <span class="tag pro" style="margin-left:4px">👑 超级管理员</span><?php endif; ?></b>
        <small>欢迎回来, 今天也要元气满满地出单哦</small>
    </div>
    <div class="w-login">
        <span>本次登录<b><?= e($curLogin ? date('m-d H:i', $curLogin['created_at']) : '—') ?> · <?= e($curLogin['ip'] ?? client_ip()) ?></b></span>
        <span>上次登录<b><?= e($prevLogin ? date('m-d H:i', $prevLogin['created_at']) : '—') ?> · <?= e($prevLogin['ip'] ?? '—') ?></b></span>
    </div>
    <div class="w-acts">
        <a class="btn sm gray" href="<?= au('profile') ?>">个人设置</a>
        <a class="btn sm gray" href="<?= au('logs') ?>">操作日志</a>
    </div>
</div>

<div class="kpi-row">
    <?php
    $kpis = [
        ['今日销售', '¥' . nf($kpi['today_amount']), '较昨日同时段 ' . $cmp($stats['today']['amount'], $stats['ySame']['amount']), ''],
        ['今日订单', (int)$kpi['today_orders'], '成交 ' . (int)$stats['today']['orders'] . ' 单', 'k2'],
        ['本月销售', '¥' . nf($kpi['month_amount']), '较上月同期 ' . $cmp($stats['month']['amount'], $stats['lMonthSame']['amount']), 'k3'],
        ['剩余库存', (int)$kpi['stock'], $todo['low_stock'] > 0 ? '<span style="color:var(--bad)">⚠ ' . (int)$todo['low_stock'] . ' 个商品库存预警</span>' : '在售商品 ' . (int)$kpi['products'] . ' 件', 'k4'],
    ];
    foreach ($kpis as $i => [$kl, $kv, $ks, $kc]): ?>
    <div class="kpi <?= $kc ?>">
        <div class="k-label"><?= e($kl) ?></div>
        <div class="k-val"><?= e((string)$kv) ?></div>
        <div class="k-sub"><?= $ks ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="dash-main">
    <div class="dash-col">
        <div class="card">
            <div class="trend-head">
                <div style="display:flex;align-items:baseline;gap:10px">
                    <h3>销售趋势</h3>
                    <span class="range-label" id="trendRange"></span>
                </div>
                <div class="seg" id="trendRangeSeg">
                    <button data-range="7" class="on">7天</button>
                    <button data-range="30">30天</button>
                </div>
            </div>
            <div class="m-tabs" id="trendTabs">
                <button class="m-tab on" data-metric="amount"><span class="m-l">销售额</span><span class="m-v" id="tv-amount">¥0.00</span></button>
                <button class="m-tab" data-metric="orders"><span class="m-l">订单数</span><span class="m-v" id="tv-orders">0</span></button>
            </div>
            <div class="t-chart" id="trendChart"></div>
            <div class="t-x" id="trendX"></div>
        </div>

        <div class="card">
            <div class="biz-head">
                <div style="display:flex;align-items:baseline;gap:10px">
                    <h3>经营数据</h3>
                    <span class="range-label"><?= e(date('Y年n月j日')) ?> <?= e(['日', '一', '二', '三', '四', '五', '六'][(int)date('w')]) ?></span>
                </div>
                <div class="seg" id="bizSeg">
                    <?php $first = true; foreach ($bizPeriods as $k => $p): ?>
                        <button data-period="<?= e($k) ?>" class="<?= $first ? 'on' : '' ?>"> <?= e($p['label']) ?></button>
                    <?php $first = false; endforeach; ?>
                </div>
            </div>
            <div class="biz-metrics">
                <div class="bm"><div class="l">成交额</div><div class="v" id="bm-amount">¥0.00</div></div>
                <div class="bm"><div class="l">成交订单</div><div class="v" id="bm-orders">0</div></div>
                <div class="bm"><div class="l">客单价</div><div class="v" id="bm-avg">¥0.00</div></div>
                <div class="bm"><div class="l">新增会员</div><div class="v" id="bm-members">0</div></div>
            </div>
        </div>
    </div>

    <div class="dash-col">
        <div class="card">
            <h3 style="margin:0 0 6px">待处理</h3>
            <div class="todo-list">
                <a href="<?= au('orders', ['status' => 3]) ?>"><span>待处理订单</span><span class="n" <?= $todo['pending_cards'] > 0 ? 'style="color:var(--warn)"' : '' ?>><?= (int)$todo['pending_cards'] ?></span></a>
                <a href="<?= au('orders', ['status' => 0]) ?>"><span>待支付订单</span><span class="n"><?= (int)$todo['pending_pay'] ?></span></a>
                <a href="<?= au('orders', ['status' => 2]) ?>"><span>已过期订单</span><span class="n"><?= (int)$todo['expired'] ?></span></a>
                <a href="<?= au('products') ?>"><span>库存预警商品</span><span class="n" <?= $todo['low_stock'] > 0 ? 'style="color:var(--bad)"' : '' ?>><?= (int)$todo['low_stock'] ?></span></a>
            </div>
        </div>
        <div class="card">
            <h3 style="margin:0 0 6px">站点概览</h3>
            <div class="todo-list">
                <a href="<?= au('users') ?>"><span>会员总数</span><span class="n"><?= (int)$kpi['members'] ?></span></a>
                <a href="<?= au('products') ?>"><span>在售商品</span><span class="n"><?= (int)$kpi['products'] ?></span></a>
                <a href="<?= au('apps', ['type' => 'payment']) ?>"><span>支付方式</span><span class="arr">配置 ›</span></a>
                <a href="<?= au('settings') ?>"><span>系统设置</span><span class="arr">前往 ›</span></a>
            </div>
        </div>
        <?php if ($officialNotices): $n = $officialNotices[0]; ?>
        <div class="card">
            <h3 style="margin:0 0 6px">📣 官方公告</h3>
            <div style="font-size:12.5px;line-height:1.7;color:var(--text2)">
                <?php if (is_array($n)): ?>
                    <b style="color:var(--text)"><?= e($n['title']) ?></b>
                    <?php if (trim((string)($n['content'] ?? '')) !== ''): ?><div style="margin-top:5px;color:var(--muted);font-size:12px"><?= e(mb_substr($n['content'], 0, 90)) ?><?= mb_strlen($n['content']) > 90 ? '…' : '' ?></div><?php endif; ?>
                    <?php if (!empty($n['time'])): ?><div style="margin-top:5px;font-size:11px;color:var(--muted)"><?= e(date('m-d H:i', (int)$n['time'])) ?></div><?php endif; ?>
                <?php else: ?>
                    <?= e($n) ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
var TREND = <?= json_encode($trend) ?>;
var BIZ = <?= json_encode($bizPeriods) ?>;
var trendRange = '7', trendMetric = 'amount';

function nf2(n) { return Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }

function renderTrend() {
    var data = TREND[trendRange], chart = document.getElementById('trendChart'), x = document.getElementById('trendX');
    var max = 0, sum = 0, cnt = 0;
    data.forEach(function (it) { max = Math.max(max, it[trendMetric]); sum += Number(it.amount); cnt += Number(it.orders); });
    chart.innerHTML = '';
    x.innerHTML = '';
    if (max === 0) {
        chart.innerHTML = '<div class="t-empty">暂无成交数据, 出单后这里会显示每日趋势</div>';
    } else {
        data.forEach(function (it, i) {
            var col = document.createElement('div');
            col.className = 't-col';
            var v = it[trendMetric];
            var h = Math.round(v / max * 100);
            var label = trendMetric === 'amount' ? '¥' + nf2(v) : String(v);
            col.innerHTML = '<span class="v">' + (v > 0 ? label : '') + '</span><i class="bar" style="height:' + Math.max(h, 1.5) + '%" title="' + it.d + ' ' + label + '"></i>';
            chart.appendChild(col);
            var xs = document.createElement('span');
            xs.textContent = data.length > 10 && i % 3 !== 0 && i !== data.length - 1 ? '' : it.d;
            x.appendChild(xs);
        });
    }
    document.getElementById('tv-amount').textContent = '¥' + nf2(round2(sum));
    document.getElementById('tv-orders').textContent = String(cnt);
    document.getElementById('trendRange').textContent = data[0].d.replace('/', '月') + '日 ~ ' + data[data.length - 1].d.replace('/', '月') + '日';
}
function round2(n) { return Math.round(Number(n) * 100) / 100; }
document.getElementById('trendRangeSeg').addEventListener('click', function (ev) {
    var b = ev.target.closest('button'); if (!b) return;
    this.querySelectorAll('button').forEach(function (x) { x.classList.remove('on'); });
    b.classList.add('on');
    trendRange = b.getAttribute('data-range');
    renderTrend();
});
document.getElementById('trendTabs').addEventListener('click', function (ev) {
    var b = ev.target.closest('.m-tab'); if (!b) return;
    this.querySelectorAll('.m-tab').forEach(function (x) { x.classList.remove('on'); });
    b.classList.add('on');
    trendMetric = b.getAttribute('data-metric');
    renderTrend();
});

function renderBiz(period) {
    var d = BIZ[period];
    if (!d) return;
    document.getElementById('bm-amount').textContent = '¥' + nf2(d.stat.amount);
    document.getElementById('bm-orders').textContent = String(d.stat.orders);
    document.getElementById('bm-avg').textContent = '¥' + nf2(d.avg);
    document.getElementById('bm-members').textContent = String(d.members);
}
document.getElementById('bizSeg').addEventListener('click', function (ev) {
    var b = ev.target.closest('button'); if (!b) return;
    this.querySelectorAll('button').forEach(function (x) { x.classList.remove('on'); });
    b.classList.add('on');
    renderBiz(b.getAttribute('data-period'));
});

renderTrend();
renderBiz('today');
</script>
