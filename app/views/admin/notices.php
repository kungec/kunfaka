<?php $activeMenu = 'notices'; ?>
<style>
.nt-grid{display:grid;grid-template-columns:minmax(0,1fr) 260px;gap:16px;align-items:start}
@media (max-width:1100px){.nt-grid{grid-template-columns:1fr}}
.nt-side .form-row{margin-bottom:14px}
.nt-side select,.nt-side input{width:100%}
.nt-publish{display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-top:4px}
.nt-list{display:flex;flex-direction:column;gap:10px}
.nt-item{display:flex;gap:12px;align-items:flex-start;padding:13px 15px;border:1px solid var(--input-border);border-radius:12px;background:var(--input-bg);transition:border-color .15s}
.nt-item:hover{border-color:var(--muted)}
.nt-item .nt-badge{flex:none;font-size:10.5px;font-weight:700;padding:3px 9px;border-radius:999px;margin-top:2px}
.nt-item .nt-badge.on{background:var(--info-bg);color:var(--info)}
.nt-item .nt-badge.off{background:var(--bad-bg);color:var(--bad)}
.nt-main{flex:1;min-width:0}
.nt-main b{display:block;font-size:13.5px;margin-bottom:3px}
.nt-main small{display:block;color:var(--muted);font-size:12px;line-height:1.6;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.nt-foot{margin-left:auto;flex:none;display:flex;flex-direction:column;align-items:flex-end;gap:8px}
.nt-foot .meta{font-size:11px;color:var(--muted);text-align:right;line-height:1.6}
.nt-foot .ops{display:flex;gap:6px}
.sp-head{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:14px}
.sp-toggle{display:inline-flex;align-items:center;gap:10px;cursor:pointer;user-select:none}
.sp-toggle .sw{width:44px;height:24px;border-radius:999px;background:var(--input-border);position:relative;transition:background .2s;flex:none}
.sp-toggle .sw::after{content:'';position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.35);transition:left .2s cubic-bezier(.2,.7,.3,1.4)}
.sp-toggle:has(input:checked) .sw{background:#22c55e}
.sp-toggle:has(input:checked) .sw::after{left:23px}
.sp-toggle input{position:absolute;opacity:0;pointer-events:none}
.sp-state{font-size:12px;font-weight:700}
.sp-toggle:has(input:checked) ~ * .sp-state{color:inherit}
</style>

<div class="page-head">
    <div>
        <h2>📣 公告单页</h2>
        <div class="sub">前台公告管理 · 自定义单页</div>
    </div>
</div>

<div class="card">
    <h3><?= $edit ? '✏️ 编辑公告 #' . (int)$edit['id'] : '📢 发布公告' ?></h3>
    <form method="post" action="<?= au('notice_save') ?>" data-ajax>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <div class="nt-grid">
            <div>
                <div class="form-row">
                    <label>公告标题(显示在前台公告条与列表)</label>
                    <input type="text" name="title" maxlength="100" required value="<?= e($edit['title'] ?? '') ?>" placeholder="如: 新用户首单立减5元">
                </div>
                <div class="form-row">
                    <label>公告正文(详情页展示, 支持换行; 可写活动说明/联系方式等内容)</label>
                    <textarea name="content" style="min-height:150px" placeholder="公告详细内容..."><?= e($edit['content'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="nt-side">
                <div class="form-row">
                    <label>状态</label>
                    <select name="status">
                        <option value="1" <?= (int)($edit['status'] ?? 1) === 1 ? 'selected' : '' ?>>显示</option>
                        <option value="0" <?= (int)($edit['status'] ?? 1) === 0 ? 'selected' : '' ?>>隐藏</option>
                    </select>
                </div>
                <div class="form-row">
                    <label>排序(越大越靠前)</label>
                    <input type="number" name="sort" value="<?= (int)($edit['sort'] ?? 0) ?>">
                </div>
                <div class="nt-publish">
                    <button class="btn" type="submit"><?= $edit ? '💾 保存修改' : '📢 发布公告' ?></button>
                    <?php if ($edit): ?><a class="btn gray" href="<?= au('notices') ?>">取消编辑</a><?php endif; ?>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <h3>📋 公告列表 <span class="tag"><?= count($list) ?> 条</span></h3>
    <?php if ($list): ?>
    <div class="nt-list">
        <?php foreach ($list as $n): ?>
            <div class="nt-item">
                <span class="nt-badge <?= (int)$n['status'] === 1 ? 'on' : 'off' ?>"><?= (int)$n['status'] === 1 ? '显示中' : '已隐藏' ?></span>
                <div class="nt-main">
                    <b><?= e(mb_substr($n['title'], 0, 40)) ?></b>
                    <small><?= e(mb_substr(preg_replace('/\s+/u', ' ', (string)$n['content']), 0, 60)) ?: '—' ?></small>
                </div>
                <div class="nt-foot">
                    <div class="meta">排序 <?= (int)$n['sort'] ?> · <?= e(date('m-d H:i', $n['created_at'])) ?></div>
                    <div class="ops">
                        <a class="btn sm gray" href="<?= au('notices', ['edit' => (int)$n['id']]) ?>">编辑</a>
                        <a class="btn sm gray" href="<?= site_url('index.php') ?>?s=/notice/detail&id=<?= (int)$n['id'] ?>" target="_blank">预览</a>
                        <button class="btn sm red" data-confirm="确定删除该公告?" data-del="<?= (int)$n['id'] ?>">删除</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:26px 0;color:var(--muted);font-size:12.5px">还没有公告, 在上方发布第一条吧</div>
    <?php endif; ?>
    <p class="dim" style="margin:12px 0 0;font-size:12px">前台展示规则: 首页公告条显示排序最前的一条「显示中」公告, 点击进入公告列表/详情页。</p>
</div>

<div class="card">
    <h3>📄 自定义单页</h3>
    <p class="dim" style="margin:4px 0 14px;font-size:12.5px">开启后前台导航出现独立页面入口 — 常用于客服联系方式、购买须知、站点介绍。内容纯文本自动换行并安全转义。</p>
    <form method="post" action="<?= au('page_save') ?>" data-ajax>
        <?= csrf_field() ?>
        <div class="sp-head">
            <label class="sp-toggle">
                <input type="checkbox" name="open" value="1" <?= $spOpen ? 'checked' : '' ?>>
                <span class="sw"></span>
                <span class="sp-state"><?= $spOpen ? '已开启' : '已关闭' ?></span>
            </label>
        </div>
        <div class="form-row">
            <label>页面标题(导航与页内显示)</label>
            <input type="text" name="title" maxlength="50" value="<?= e($spTitle) ?>" style="max-width:320px" placeholder="如: 联系我们 / 购买须知">
        </div>
        <div class="form-row">
            <label>页面内容(示例: 客服微信 xxx001 / 工作时间 9:00-23:00 / 售后说明等)</label>
            <textarea name="content" style="min-height:160px" placeholder="在这里填写任意内容, 比如客服联系方式、公告说明、售后政策..."><?= e($spContent) ?></textarea>
        </div>
        <button class="btn" type="submit">💾 保存单页</button>
    </form>
</div>
