<?php $activeMenu = 'notices'; ?>
<div class="card">
    <h3><?= $edit ? '编辑公告 #' . (int)$edit['id'] : '发布公告' ?></h3>
    <form method="post" action="<?= au('notice_save') ?>" data-ajax>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <div class="form-row">
            <label>公告标题(显示在前台公告条与列表)</label>
            <input type="text" name="title" maxlength="100" required value="<?= e($edit['title'] ?? '') ?>" style="max-width:520px" placeholder="如: 新用户首单立减5元">
        </div>
        <div class="form-row">
            <label>公告正文(详情页展示, 支持换行; 可写活动说明/联系方式等内容)</label>
            <textarea name="content" style="min-height:130px" placeholder="公告详细内容..."><?= e($edit['content'] ?? '') ?></textarea>
        </div>
        <div class="form-inline">
            <div class="form-row" style="margin-bottom:0">
                <label>状态</label>
                <select name="status" style="width:110px">
                    <option value="1" <?= (int)($edit['status'] ?? 1) === 1 ? 'selected' : '' ?>>显示</option>
                    <option value="0" <?= (int)($edit['status'] ?? 1) === 0 ? 'selected' : '' ?>>隐藏</option>
                </select>
            </div>
            <div class="form-row" style="margin-bottom:0">
                <label>排序(越大越靠前)</label>
                <input type="number" name="sort" value="<?= (int)($edit['sort'] ?? 0) ?>" style="width:100px">
            </div>
            <button class="btn" type="submit" style="margin-top:18px"><?= $edit ? '保存修改' : '发布公告' ?></button>
            <?php if ($edit): ?><a class="btn sm" href="<?= au('notices') ?>" style="margin-top:18px">取消编辑</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <h3>公告列表 <span class="tag"><?= count($list) ?> 条</span></h3>
    <table class="tb">
        <tr><th>ID</th><th style="min-width:180px">标题</th><th style="min-width:220px">正文摘要</th><th>状态</th><th>排序</th><th>发布时间</th><th style="width:170px">操作</th></tr>
        <?php foreach ($list as $n): ?>
            <tr>
                <td><?= (int)$n['id'] ?></td>
                <td><?= e(mb_substr($n['title'], 0, 40)) ?></td>
                <td class="dim"><?= e(mb_substr(preg_replace('/\s+/u', ' ', (string)$n['content']), 0, 30)) ?: '—' ?></td>
                <td><span class="tag <?= (int)$n['status'] === 1 ? 'ok' : '' ?>"><?= (int)$n['status'] === 1 ? '显示中' : '已隐藏' ?></span></td>
                <td><?= (int)$n['sort'] ?></td>
                <td class="dim"><?= e(date('Y-m-d H:i', $n['created_at'])) ?></td>
                <td class="actions">
                    <a class="btn sm" href="<?= au('notices', ['edit' => (int)$n['id']]) ?>">编辑</a>
                    <a class="btn sm" href="<?= site_url('index.php') ?>?s=/notice/detail&id=<?= (int)$n['id'] ?>" target="_blank">预览</a>
                    <button class="btn sm red" data-confirm="确定删除该公告?" data-del="<?= (int)$n['id'] ?>">删除</button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="7" class="dim" style="text-align:center">还没有公告, 在上方发布第一条吧</td></tr><?php endif; ?>
    </table>
    <p class="dim" style="margin:10px 0 0;font-size:12.5px">前台展示规则: 首页公告条显示排序最前的一条「显示中」公告, 点击进入公告列表/详情页。</p>
</div>

<div class="card">
    <h3>自定义单页</h3>
    <p class="dim" style="margin:4px 0 12px;font-size:12.5px">开启后前台导航出现独立页面入口, 内容自由编辑 — 常用于放客服联系方式、购买须知、站点介绍等。内容为纯文本, 自动换行并做安全转义。</p>
    <form method="post" action="<?= au('page_save') ?>" data-ajax>
        <?= csrf_field() ?>
        <div class="form-inline">
            <div class="form-row" style="margin-bottom:0">
                <label>页面开关</label>
                <select name="open" style="width:110px">
                    <option value="1" <?= $spOpen ? 'selected' : '' ?>>开启</option>
                    <option value="0" <?= !$spOpen ? 'selected' : '' ?>>关闭</option>
                </select>
            </div>
            <div class="form-row" style="margin-bottom:0">
                <label>页面标题(导航与页内显示)</label>
                <input type="text" name="title" maxlength="50" value="<?= e($spTitle) ?>" style="width:220px" placeholder="如: 联系我们 / 购买须知">
            </div>
        </div>
        <div class="form-row">
            <label>页面内容(示例: 客服微信 xxx001 / 工作时间 9:00-23:00 / 售后说明等)</label>
            <textarea name="content" style="min-height:160px" placeholder="在这里填写任意内容, 比如客服联系方式、公告说明、售后政策..."><?= e($spContent) ?></textarea>
        </div>
        <button class="btn" type="submit">保存单页</button>
    </form>
</div>
