<?php /** 云商城主题 自定义单页(联系方式/购买须知等) */ ?>
<div class="panel">
    <h1 class="panel-title"><?= e($spTitle) ?></h1>
    <div class="notice-body"><?= nl2br(e($spContent)) ?></div>
    <div class="pay-actions">
        <a class="btn-ghost" href="<?= u('home/index') ?>">返回首页</a>
    </div>
</div>
