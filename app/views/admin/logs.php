<?php $activeMenu = 'logs';
$types = ['' => '全部', 'admin' => '管理', 'user' => '会员', 'pay' => '支付', 'usdt' => '链上', 'store' => '商店', 'system' => '系统']; ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
        <h3 style="margin:0">系统日志 <span class="tag"><?= (int)$total ?> 条(最多展示最近100条)</span></h3>
        <button class="btn red sm" data-confirm="确定清空全部系统日志? 此操作不可恢复" id="clearLogs">🧹 一键清空日志</button>
    </div>
    <div class="form-inline" style="margin-top:12px">
        <?php foreach ($types as $tk => $tv): ?>
            <a class="store-tab<?= $type === (string)$tk ? ' on' : '' ?>" href="<?= au('logs', $tk !== '' ? ['type' => $tk] : []) ?>"><?= $tv ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <table class="tb">
        <tr><th style="width:150px">时间</th><th style="width:70px">类型</th><th>内容</th><th style="width:130px">IP</th></tr>
        <?php foreach ($list as $log): ?>
            <tr>
                <td class="dim"><?= e(date('Y-m-d H:i:s', $log['created_at'])) ?></td>
                <td><span class="tag <?= $log['type'] === 'pay' || $log['type'] === 'usdt' ? 'ok' : ($log['type'] === 'system' ? 'blue' : '') ?>"><?= e(log_type_label($log['type'])) ?></span></td>
                <td><?= e($log['message']) ?></td>
                <td class="dim mono"><?= e($log['ip']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="4" class="dim" style="text-align:center">暂无日志</td></tr><?php endif; ?>
    </table>
</div>

<script>
document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('#clearLogs') : null;
    if (!t) return;
    var fd = new FormData();
    fd.append('_csrf', '<?= e(csrf_token()) ?>');
    fetch('<?= au('logs_clear') ?>', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { alert(d.msg); if (d.code === 0) location.reload(); });
});
</script>
