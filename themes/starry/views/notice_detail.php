<?php /** 云商城主题 公告详情 */ ?>
<div class="panel">
    <div class="n-crumb"><a href="<?= u('notice/index') ?>">公告列表</a> <span>›</span> 正文</div>
    <h1 class="panel-title"><?= e($notice['title']) ?></h1>
    <p class="n-time">发布于 <?= e(date('Y-m-d H:i', $notice['created_at'])) ?></p>
    <div class="notice-body"><?= nl2br(e($notice['content'])) ?></div>
    <div class="pay-actions">
        <a class="btn-ghost" href="<?= u('home/index') ?>">去逛商品</a>
        <a class="btn-ghost" href="<?= u('notice/index') ?>">返回公告列表</a>
    </div>
</div>
