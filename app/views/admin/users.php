<?php $activeMenu = 'users'; $pages = max(1, (int)ceil($total / $per)); ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
        <h3 style="margin:0">会员列表 <span class="tag"><?= (int)$total ?> 人</span></h3>
        <form class="form-inline" method="get" action="<?= site_url('admin.php') ?>">
            <input type="hidden" name="s" value="/users">
            <input type="text" name="kw" value="<?= e($kw) ?>" placeholder="用户名/邮箱">
            <button class="btn" type="submit">搜索</button>
        </form>
    </div>
</div>

<div class="card">
    <table class="tb">
        <tr><th>ID</th><th>用户名</th><th>邮箱</th><th>成功订单</th><th>注册IP</th><th>注册时间</th><th>状态</th><th>操作</th></tr>
        <?php foreach ($list as $u): ?>
            <tr>
                <td><?= (int)$u['id'] ?></td>
                <td><?= e($u['username']) ?></td>
                <td class="dim"><?= e($u['email'] ?: '-') ?></td>
                <td><?= (int)$u['orders_count'] ?></td>
                <td class="dim mono"><?= e($u['reg_ip']) ?></td>
                <td class="dim"><?= e(date('Y-m-d H:i', $u['created_at'])) ?></td>
                <td><span class="tag <?= (int)$u['status'] === 1 ? 'ok' : 'bad' ?>"><?= (int)$u['status'] === 1 ? '正常' : '已禁用' ?></span></td>
                <td class="actions">
                    <button class="btn sm gray" data-toggle="<?= (int)$u['id'] ?>"><?= (int)$u['status'] === 1 ? '禁用' : '启用' ?></button>
                    <button class="btn sm red" data-confirm="确定删除该会员? 其历史订单将保留" data-del="<?= (int)$u['id'] ?>">删除</button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="8" class="dim" style="text-align:center">暂无会员</td></tr><?php endif; ?>
    </table>
    <?php if ($pages > 1): ?>
        <div class="pager">
            <?php for ($i = 1; $i <= min($pages, 12); $i++): ?>
                <a class="<?= $i === $page ? 'on' : '' ?>" href="<?= au('users', array_filter(['kw' => $kw, 'page' => $i > 1 ? $i : ''], function ($v) { return $v !== '' && $v !== null; })) ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-toggle],[data-del]') : null;
    if (!t) return;
    var fd = new FormData();
    fd.append('_csrf', '<?= e(csrf_token()) ?>');
    fd.append('id', t.getAttribute('data-toggle') || t.getAttribute('data-del'));
    var url = t.hasAttribute('data-toggle') ? '<?= au('user_toggle') ?>' : '<?= au('user_del') ?>';
    fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { alert(d.msg); if (d.code === 0) location.reload(); });
});
</script>
