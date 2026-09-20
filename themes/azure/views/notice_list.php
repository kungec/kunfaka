<?php /** 云商城主题 公告列表 */ ?>
<div class="panel">
    <h1 class="panel-title">站点公告</h1>
    <?php if (!$list): ?>
        <div class="empty-box">
            <div class="empty-face">📣</div>
            <p>暂时没有公告</p>
        </div>
    <?php else: ?>
        <div class="notice-list">
            <?php foreach ($list as $n): ?>
                <a class="notice-item" href="<?= u('notice/detail', ['id' => (int)$n['id']]) ?>">
                    <div class="n-title"><?= e($n['title']) ?></div>
                    <?php if (trim((string)$n['content']) !== ''): ?>
                        <div class="n-excerpt"><?= e(mb_substr(preg_replace('/\s+/u', ' ', strip_tags($n['content'])), 0, 66)) ?>…</div>
                    <?php endif; ?>
                    <div class="n-meta"><?= e(date('Y-m-d', $n['created_at'])) ?><span class="n-more">阅读详情 ›</span></div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
