<?php $activeMenu = 'apps'; ?>
<div class="card">
    <h3>配置「<?= e($meta['title']) ?>」
        <span class="tag <?= !empty($meta['pro']) ? 'pro' : 'ok' ?>"><?= !empty($meta['pro']) ? '专业版专享' : '免费' ?></span>
    </h3>
    <p class="dim"><?= e($meta['desc']) ?></p>
    <form method="post" action="<?= au('app_config_save') ?>" data-ajax style="margin-top:10px">
        <?= csrf_field() ?>
        <input type="hidden" name="name" value="<?= e($meta['name']) ?>">
        <?php if (!$fields): ?>
            <p class="dim">该应用无需配置。</p>
        <?php else: ?>
            <?php foreach ($fields as $f): ?>
                <div class="form-row">
                    <label><?= e($f['label']) ?></label>
                    <?php $val = isset($config[$f['key']]) && $config[$f['key']] !== '' ? $config[$f['key']] : (isset($f['default']) ? $f['default'] : ''); ?>
                    <?php if ($f['type'] === 'textarea'): ?>
                        <textarea name="<?= e($f['key']) ?>" placeholder="<?= isset($f['default']) ? e($f['default']) : '' ?>" style="max-width:600px"><?= e($val) ?></textarea>
                    <?php elseif ($f['type'] === 'select'): ?>
                        <select name="<?= e($f['key']) ?>" style="max-width:600px">
                            <?php foreach (($f['options'] ?: []) as $ok => $ov): ?>
                                <option value="<?= e($ok) ?>" <?= $val === (string)$ok ? 'selected' : '' ?>><?= e($ov) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($f['type'] === 'checkboxes'): ?>
                        <?php
                        $cur = $val;
                        if (is_string($cur)) $cur = array_filter(array_map('trim', explode(',', (string)$cur)));
                        $cur = array_map('strval', (array)$cur);
                        ?>
                        <div class="chk-group" style="display:flex;gap:18px;flex-wrap:wrap">
                            <?php foreach (($f['options'] ?: []) as $ok => $ov): ?>
                                <label class="chk"><input type="checkbox" name="<?= e($f['key']) ?>[]" value="<?= e($ok) ?>" <?= in_array((string)$ok, $cur, true) ? 'checked' : '' ?>> <?= e($ov) ?></label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <input type="text" name="<?= e($f['key']) ?>" value="<?= e($val) ?>" style="max-width:600px" class="<?= strlen((string)$val) > 40 ? 'mono' : '' ?>">
                    <?php endif; ?>
                    <?php if (!empty($f['desc'])): ?><div class="desc"><?= e($f['desc']) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <button class="btn" type="submit">保存配置</button>
        <a class="btn gray" href="<?= au('apps', ['type' => $type]) ?>">返回商店</a>
    </form>
</div>
