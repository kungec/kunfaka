<?php $activeMenu = 'apps'; ?>
<div class="page-head">
    <div>
        <h2><?= ico('settings') ?> <?= e($title) ?> · 管理</h2>
        <div class="sub">营销插件管理页 · <a href="<?= au('apps', ['type' => 'marketing']) ?>">返回应用商店</a></div>
    </div>
</div>
<?= $html ?>
