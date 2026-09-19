<?php /** 二次元主题 公告详情 */ ?>
<div class="panel">
    <h1 class="panel-title"><?= e($notice['title']) ?></h1>
    <p class="n-time">发布于 <?= e(date('Y-m-d H:i', $notice['created_at'])) ?></p>
    <div class="notice-body"><?= nl2br(e($notice['content'])) ?></div>
    <p class="tip-line"><a class="btn-buy" href="<?= u('notice/index') ?>">返回公告列表</a></p>
</div>
