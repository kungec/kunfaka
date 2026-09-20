<?php $activeMenu = 'logs';
$types = ['' => '全部类型', 'admin' => '管理', 'user' => '会员', 'pay' => '支付', 'usdt' => '链上', 'store' => '商店', 'system' => '系统'];
$curRisk = isset($_GET['risk']) ? (string)$_GET['risk'] : '';
$curKw = isset($_GET['kw']) ? (string)$_GET['kw'] : '';
$curIp = isset($_GET['ip']) ? (string)$_GET['ip'] : '';
$curType = isset($_GET['type']) ? (string)$_GET['type'] : '';
$riskOf = function ($msg) {
    return preg_match('/删除|清理|清空|禁用|移除|销毁|吊销|失败|拦截|错误/', (string)$msg) === 1;
};
$pillLink = function ($risk) use ($curKw, $curIp, $curType) {
    $p = [];
    if ($curKw !== '') $p['kw'] = $curKw;
    if ($curIp !== '') $p['ip'] = $curIp;
    if ($curType !== '') $p['type'] = $curType;
    if ($risk !== '') $p['risk'] = $risk;
    return au('logs', $p);
};
?>
<style>
.l-filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:9px 10px}
.l-filters .lf label{display:block;font-size:11px;color:var(--muted);margin-bottom:4px;font-weight:600}
.l-filters input,.l-filters select{width:100%;height:36px;padding:0 10px;font-size:12.5px}
.l-tabs{display:flex;gap:7px;margin-top:14px}
.l-tabs a{padding:6px 16px;border-radius:8px;border:1.5px solid var(--input-border);font-size:12.5px;font-weight:600;color:var(--text2);text-decoration:none;transition:.12s}
.l-tabs a.on{border-color:var(--text);color:var(--text);background:var(--input-bg)}
.l-tabs a:hover{border-color:var(--muted)}
.l-ua{font-size:11px;color:var(--muted);max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.l-msg{font-size:12.5px;line-height:1.6}
</style>

<div class="page-head">
    <div>
        <h2>📜 操作日志</h2>
        <div class="sub">后台与业务事件的检索 · 评估 · 清理</div>
    </div>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px">
        <span class="dim" style="font-size:12px">按内容关键词自动评估风险: 含删除/清理/禁用/失败等字样的日志标记为「风险较高」。</span>
        <div style="display:flex;gap:9px;flex-wrap:wrap">
            <button class="btn sm gray" data-clean="30" data-confirm="确定清理 30 天前的日志? 较新日志不受影响">🧹 清理30天前日志</button>
            <button class="btn sm red" data-clean="all" data-confirm="⚠ 确定清空全部日志? 此操作不可恢复">🗑 清空全部日志</button>
        </div>
    </div>

    <form method="get" action="<?= site_url('admin.php') ?>">
        <input type="hidden" name="s" value="/logs">
        <div class="l-filters">
            <div class="lf"><label>搜索日志</label><input type="text" name="kw" value="<?= e($curKw) ?>" placeholder="内容关键字"></div>
            <div class="lf"><label>IP地址</label><input type="text" name="ip" value="<?= e($curIp) ?>"></div>
            <div class="lf"><label>类型</label>
                <select name="type">
                    <?php foreach ($types as $tk => $tv): ?><option value="<?= e($tk) ?>" <?= $curType === (string)$tk ? 'selected' : '' ?>><?= $tv ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="lf"><label>从操作时间</label><input type="datetime-local" name="date_from" value="<?= e(arr_get($_GET, 'date_from')) ?>"></div>
            <div class="lf"><label>到操作时间</label><input type="datetime-local" name="date_to" value="<?= e(arr_get($_GET, 'date_to')) ?>"></div>
            <div class="lf"><label>&nbsp;</label><button class="btn" type="submit" style="width:100%">🔍 查询</button></div>
        </div>
    </form>

    <div class="l-tabs">
        <a href="<?= e($pillLink('')) ?>" class="<?= $curRisk === '' ? 'on' : '' ?>">全部</a>
        <a href="<?= e($pillLink('low')) ?>" class="<?= $curRisk === 'low' ? 'on' : '' ?>">无风险</a>
        <a href="<?= e($pillLink('high')) ?>" class="<?= $curRisk === 'high' ? 'on' : '' ?>">风险较高</a>
    </div>
</div>

<div class="card">
    <table class="tb">
        <thead>
        <tr>
            <th style="width:92px">操作者</th>
            <th>日志</th>
            <th style="width:150px">时间</th>
            <th style="width:120px">IP</th>
            <th>浏览器</th>
            <th style="width:86px">评估</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($list as $log): $high = $riskOf($log['message']); ?>
            <tr>
                <td><span class="tag <?= $log['type'] === 'pay' || $log['type'] === 'usdt' ? 'ok' : ($log['type'] === 'system' ? 'blue' : '') ?>"><?= e(log_type_label($log['type'])) ?></span></td>
                <td class="l-msg"><?= e($log['message']) ?></td>
                <td class="dim"><?= e(date('Y-m-d H:i:s', $log['created_at'])) ?></td>
                <td class="dim mono"><?= e($log['ip']) ?></td>
                <td><span class="l-ua" title="<?= e($log['ua']) ?>"><?= e($log['ua'] ? mb_substr($log['ua'], 0, 60) : '—') ?></span></td>
                <td><span class="tag <?= $high ? 'bad' : 'ok' ?>"><?= $high ? '风险较高' : '无风险' ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="6"><div style="text-align:center;padding:34px 0;color:var(--muted)"><div style="font-size:34px;opacity:.55;margin-bottom:6px">📜</div>暂无日志</div></td></tr><?php endif; ?>
        </tbody>
    </table>
    <div style="font-size:12px;color:var(--muted);padding:10px 2px 0">共 <?= (int)$total ?> 条<?= $total > 100 ? '(显示最近100条, 可用筛选缩小范围)' : '' ?></div>
</div>

<script>
document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-clean]') : null;
    if (!t) return;
    var fd = new FormData();
    fd.append('_csrf', '<?= e(csrf_token()) ?>');
    fd.append('scope', t.getAttribute('data-clean'));
    fetch('<?= au('logs_clear') ?>', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { kAlert(d.msg); if (d.code === 0) location.reload(); });
});
</script>
