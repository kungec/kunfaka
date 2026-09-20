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
.dash-grid{display:grid;grid-template-columns:300px minmax(0,1fr);gap:14px;align-items:start}
@media(max-width:1100px){.dash-grid{grid-template-columns:1fr}}
.dash-side{display:flex;flex-direction:column;gap:14px}
.dash-side .card{margin-bottom:0;min-width:0;overflow:hidden}
.n-list{display:flex;flex-direction:column}
.n-item{display:flex;gap:9px;padding:9px 0;border-bottom:1px dashed var(--input-border);text-decoration:none;color:var(--text)}
.n-item:last-child{border-bottom:none}
.n-item:hover .n-title{color:var(--muted)}
.n-badge{flex:none;font-size:10px;padding:1px 6px;border-radius:4px;height:fit-content;background:var(--ok-bg);color:var(--ok);font-weight:600}
.n-main{min-width:0}
.n-title{font-size:12.5px;line-height:1.55;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.n-date{font-size:10.5px;color:var(--muted);margin-top:3px}
.acct-row{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px dashed var(--input-border);font-size:12px}
.acct-row:last-child{border-bottom:none}
.acct-row .k{color:var(--muted);flex:none}
.acct-row .v{text-align:right;word-break:break-all}
.acct-head{display:flex;align-items:center;gap:11px;margin-bottom:12px}
.acct-head .avatar{width:42px;height:42px;border-radius:12px;background:var(--text);color:var(--bg);display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:18px;flex:none}
.acct-head .nm{font-weight:700;font-size:14px}
.acct-head .rl{font-size:10.5px;color:var(--muted);margin-top:1px}
.d-top{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0}
@media(max-width:900px){.d-top{grid-template-columns:1fr}}
.d-top .cell{padding:2px 18px;border-left:1px solid var(--input-border)}
.d-top .cell:first-child{border-left:none;padding-left:2px}
.d-top .t-label{font-size:12px;color:var(--muted)}
.d-top .t-val{font-size:26px;font-weight:800;letter-spacing:-.5px;margin:5px 0 2px}
.d-top .t-cmp{font-size:11.5px;color:var(--muted)}
.d-top .t-foot{margin-top:12px;padding-top:9px;border-top:1px dashed var(--input-border);font-size:11.5px;color:var(--muted)}
.todo-row{display:flex;align-items:center;gap:4px;flex-wrap:wrap}
.todo-row .t-title{font-weight:700;font-size:14px;padding-right:14px;border-right:1px solid var(--input-border);margin-right:8px}
.todo-item{flex:1;min-width:150px;display:flex;align-items:center;justify-content:space-between;gap:8px;padding:4px 10px;border-radius:8px;text-decoration:none;color:var(--text);font-size:13px;transition:.12s}
.todo-item:hover{background:var(--input-bg)}
.todo-item .n{font-size:19px;font-weight:800}
.todo-item .arr{color:var(--muted);font-size:12px}
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
.m-tab.on{border-bottom-color:var(--text)}
.m-tab.on .m-l{color:var(--text)}
.t-chart{display:flex;align-items:flex-end;height:210px;gap:4px;padding-top:18px;position:relative}
.t-chart .t-empty{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:12px}
.t-col{flex:1;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:6px;min-width:0}
.t-col .bar{width:min(36px,72%);background:var(--text);opacity:.88;border-radius:5px 5px 0 0;min-height:2px;transition:height .25s}
.t-col .bar:hover{opacity:.65}
.t-col .v{font-size:10px;color:var(--muted)}
.t-x{display:flex;gap:4px;margin-top:8px}
.t-x span{flex:1;text-align:center;font-size:10.5px;color:var(--muted);min-width:0;overflow:hidden;white-space:nowrap}
.biz-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;padding:8px 2px 2px}
.biz-metrics .bm .l{font-size:12px;color:var(--muted)}
.biz-metrics .bm .v{font-size:22px;font-weight:800;margin-top:4px}
.update-banner { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; background: linear-gradient(90deg, rgba(99,102,241,.18), rgba(139,92,246,.12)); border: 1px solid rgba(129,140,248,.4); border-radius: 12px; padding: 12px 18px; margin-bottom: 14px; position: relative; z-index: 1; }
.ub-ico { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; flex: none; }
.ub-txt { flex: 1; min-width: 240px; font-size: 13px; color: var(--text2); }
.ub-txt b { color: var(--text); }
.ub-btn { height: 36px; padding: 0 18px; border: none; border-radius: 9px; background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; }

/* ===== 视觉特效层 ===== */
.dash-grid{position:relative}
.dg-glow{position:fixed;border-radius:50%;pointer-events:none;z-index:0}
.dg-glow.g1{width:640px;height:640px;background:radial-gradient(circle,rgba(99,102,241,.20) 0%,rgba(99,102,241,.08) 40%,transparent 68%);top:-220px;right:2%}
.dg-glow.g2{width:560px;height:560px;background:radial-gradient(circle,rgba(34,211,238,.13) 0%,rgba(34,211,238,.05) 45%,transparent 70%);bottom:-240px;left:18%}
.dash-grid>*{position:relative;z-index:1}
.dash-grid .card{animation:dashIn .5s cubic-bezier(.2,.7,.3,1) backwards;transition:transform .18s ease,box-shadow .18s ease;background-image:linear-gradient(180deg,rgba(255,255,255,.045),rgba(255,255,255,0) 42%);border:1px solid rgba(255,255,255,.07);box-shadow:inset 0 1px 0 rgba(255,255,255,.05),0 12px 32px -20px rgba(0,0,0,.5)}
[data-theme="light"] .dash-grid .card{background-image:linear-gradient(180deg,#ffffff,#fbfbfd);border-color:var(--line);box-shadow:var(--shadow)}
.dash-grid .card:hover{transform:translateY(-2px);box-shadow:inset 0 1px 0 rgba(255,255,255,.08),0 18px 40px -16px rgba(0,0,0,.6);border-color:rgba(255,255,255,.14)}
[data-theme="light"] .dash-grid .card:hover{box-shadow:0 14px 30px -14px rgba(30,40,90,.25);border-color:#d5d9e6}
.dash-grid .card:hover{transform:translateY(-2px);box-shadow:0 16px 38px -16px rgba(0,0,0,.55)}
[data-theme="light"] .dash-grid .card:hover{box-shadow:0 14px 30px -14px rgba(30,40,90,.25)}
.dash-side .card:nth-child(1){animation-delay:.04s}
.dash-side .card:nth-child(2){animation-delay:.11s}
.dash-grid>div:last-child .card:nth-child(1){animation-delay:.08s}
.dash-grid>div:last-child .card:nth-child(2){animation-delay:.15s}
.dash-grid>div:last-child .card:nth-child(3){animation-delay:.22s}
.dash-grid>div:last-child .card:nth-child(4){animation-delay:.29s}
@keyframes dashIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
.d-top .t-val,.biz-metrics .bm .v,.m-tab.on .m-v{background:linear-gradient(120deg,#f5f6ff 10%,#a5b4fc 55%,#67e8f9 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;filter:drop-shadow(0 0 14px rgba(129,140,248,.35))}
[data-theme="light"] .d-top .t-val,[data-theme="light"] .biz-metrics .bm .v,[data-theme="light"] .m-tab.on .m-v{background:linear-gradient(120deg,#111827 10%,#4f46e5 70%);-webkit-background-clip:text;background-clip:text;filter:none}
.todo-item .n{animation:nPulse 2.6s ease-in-out infinite}
@keyframes nPulse{0%,100%{opacity:1}50%{opacity:.55}}
.t-chart .bar{background:linear-gradient(180deg,#818cf8,#4f46e5);opacity:1;transform-origin:bottom;animation:barGrow .6s cubic-bezier(.2,.7,.3,1) backwards}
.t-chart .bar:hover{background:linear-gradient(180deg,#a5b4fc,#6366f1);opacity:1}
@keyframes barGrow{from{transform:scaleY(0)}to{transform:scaleY(1)}}
/* ---- 更新提示横幅 ---- */
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

<span class="dg-glow g1" aria-hidden="true"></span>
<span class="dg-glow g2" aria-hidden="true"></span>
<div class="dash-grid">
    <!-- ====== 左栏: 公告 + 账号 ====== -->
    <div class="dash-side">
        <div class="card">
            <h3>📣 官方公告</h3>
            <?php if (!$officialNotices): ?>
                <p class="dim" style="margin:0">暂无官方公告。</p>
            <?php else: ?>
            <div class="n-list">
                <?php foreach ($officialNotices as $n): ?>
                    <div class="n-item" style="cursor:default">
                        <span class="n-badge">官方</span>
                        <span class="n-main">
                            <span class="n-title"><?= e(is_array($n) ? $n['title'] : $n) ?></span>
                            <?php if (is_array($n) && trim((string)($n['content'] ?? '')) !== ''): ?>
                                <span style="display:block;font-size:12px;color:var(--muted);white-space:pre-wrap;word-break:break-all"><?= e($n['content']) ?></span>
                            <?php endif; ?>
                        </span>
                        <?php if (is_array($n) && !empty($n['time'])): ?><span style="font-size:11px;color:var(--muted);flex:none"><?= e(date('m-d', (int)$n['time'])) ?></span><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="acct-head">
                <span class="avatar">坤</span>
                <div>
                    <div class="nm"><?= e(isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : '管理员') ?></div>
                    <div class="rl">站点管理员<?php if (current_admin() && current_admin()['role'] === 'super'): ?> · 超级管理员<?php endif; ?></div>
                </div>
            </div>
            <div class="acct-row"><span class="k">本次登录IP</span><span class="v mono"><?= e($curLogin['ip'] ?? client_ip()) ?></span></div>
            <div class="acct-row"><span class="k">本次登录时间</span><span class="v"><?= e($curLogin ? date('Y-m-d H:i', $curLogin['created_at']) : date('Y-m-d H:i')) ?></span></div>
            <div class="acct-row"><span class="k">上次登录</span><span class="v"><?= e($prevLogin ? date('m-d H:i', $prevLogin['created_at']) : '—') ?></span></div>
            <div class="acct-row"><span class="k">上次登录IP</span><span class="v mono"><?= e($prevLogin['ip'] ?? '—') ?></span></div>
            <div style="margin-top:12px;display:flex;gap:8px">
                <a class="btn sm gray" href="<?= au('profile') ?>">个人设置</a>
                <a class="btn sm gray" href="<?= au('logs') ?>">操作日志</a>
            </div>
        </div>
    </div>

    <!-- ====== 右栏: 统计 / 待处理 / 趋势 / 经营 ====== -->
    <div style="display:flex;flex-direction:column;gap:14px;min-width:0">
        <?php $kIcons = [
            ['今日销售', '¥' . nf($kpi['today_amount']), '💰', 'kg'],
            ['今日订单', (int)$kpi['today_orders'], '🧾', 'kb'],
            ['本月销售', '¥' . nf($kpi['month_amount']), '📈', 'kc'],
            ['会员总数', (int)$kpi['members'], '👥', 'kv'],
            ['在售商品', (int)$kpi['products'], '📦', 'kp'],
            ['剩余库存', (int)$kpi['stock'], '🔑', 'ky'],
        ]; ?>
        <div class="kgrid">
            <?php foreach ($kIcons as [$kl, $kv, $ki, $kc]): ?>
            <div class="card kstat">
                <div class="ks-main"><div class="ks-label"><?= e($kl) ?></div><div class="ks-val"><?= e((string)$kv) ?></div></div>
                <div class="ks-ico <?= $kc ?>"><?= $ki ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="card">
            <div class="d-top">
                <div class="cell">
                    <div class="t-label">今日销售额</div>
                    <div class="t-val">¥<?= e(nf($stats['today']['amount'])) ?></div>
                    <div class="t-cmp">较昨日同时段 <?= $cmp($stats['today']['amount'], $stats['ySame']['amount']) ?></div>
                    <div class="t-foot">成交 <?= (int)$stats['today']['orders'] ?> 单</div>
                </div>
                <div class="cell">
                    <div class="t-label">昨日销售额</div>
                    <div class="t-val">¥<?= e(nf($stats['yesterday']['amount'])) ?></div>
                    <div class="t-cmp">较前日 <?= $cmp($stats['yesterday']['amount'], $stats['dayBefore']['amount']) ?></div>
                    <div class="t-foot">成交 <?= (int)$stats['yesterday']['orders'] ?> 单</div>
                </div>
                <div class="cell">
                    <div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px">
                        <div class="t-label">本月销售额</div>
                        <span class="range-label">上月 ¥<?= e(nf($stats['lMonthFull']['amount'])) ?></span>
                    </div>
                    <div class="t-val">¥<?= e(nf($stats['month']['amount'])) ?></div>
                    <div class="t-cmp">较上月同期 <?= $cmp($stats['month']['amount'], $stats['lMonthSame']['amount']) ?></div>
                    <div class="t-foot">成交 <?= (int)$stats['month']['orders'] ?> 单</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="todo-row">
                <span class="t-title">待处理</span>
                <a class="todo-item" href="<?= au('orders', ['status' => 3]) ?>"><span>待处理订单</span><span class="n <?= $todo['pending_cards'] > 0 ? 'v-warn' : '' ?>" <?= $todo['pending_cards'] > 0 ? 'style="color:var(--warn)"' : '' ?>><?= (int)$todo['pending_cards'] ?></span><span class="arr">›</span></a>
                <a class="todo-item" href="<?= au('orders', ['status' => 0]) ?>"><span>待支付订单</span><span class="n"><?= (int)$todo['pending_pay'] ?></span><span class="arr">›</span></a>
                <a class="todo-item" href="<?= au('orders', ['status' => 2]) ?>"><span>已过期订单</span><span class="n"><?= (int)$todo['expired'] ?></span><span class="arr">›</span></a>
                <a class="todo-item" href="<?= au('products') ?>"><span>库存预警商品</span><span class="n" <?= $todo['low_stock'] > 0 ? 'style="color:var(--bad)"' : '' ?>><?= (int)$todo['low_stock'] ?></span><span class="arr">›</span></a>
            </div>
        </div>

        <div class="card">
            <div class="trend-head">
                <div style="display:flex;align-items:baseline;gap:10px">
                    <h3>趋势</h3>
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
