<?php $activeMenu = 'products'; $isEdit = $product !== null; ?>
<div class="card">
    <h3><?= $isEdit ? '编辑商品' : '新增商品' ?></h3>
    <form method="post" action="<?= au('product_save') ?>" data-ajax>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $isEdit ? (int)$product['id'] : 0 ?>">
        <div class="form-row">
            <label>商品名称 *</label>
            <input type="text" name="name" value="<?= $isEdit ? e($product['name']) : '' ?>" required>
        </div>
        <div class="form-row">
            <label>所属分类</label>
            <select name="category_id">
                <option value="0">未分类</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $isEdit && (int)$product['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label>商品价格(元) *</label>
            <input type="number" name="price" step="0.01" min="0" value="<?= $isEdit ? e(nf($product['price'])) : '0.00' ?>" required>
        </div>
        <div class="form-row">
            <label>限购数量(最少/最多)</label>
            <div class="form-inline">
                <input type="number" name="min_num" value="<?= $isEdit ? (int)$product['min_num'] : 1 ?>" min="1" style="width:100px">
                <span class="dim">至</span>
                <input type="number" name="max_num" value="<?= $isEdit ? (int)$product['max_num'] : 5 ?>" min="1" style="width:100px">
            </div>
        </div>
        <div class="form-row">
            <label>商品介绍(购买页展示)</label>
            <textarea name="description"><?= $isEdit ? e($product['description']) : '' ?></textarea>
        </div>
        <div class="form-row">
            <label>排序(越小越靠前)</label>
            <input type="number" name="sort" value="<?= $isEdit ? (int)$product['sort'] : 0 ?>" style="width:100px">
        </div>
        <div class="form-row">
            <label>上架状态</label>
            <select name="status">
                <option value="1" <?= !$isEdit || (int)$product['status'] === 1 ? 'selected' : '' ?>>上架</option>
                <option value="0" <?= $isEdit && (int)$product['status'] === 0 ? 'selected' : '' ?>>下架</option>
            </select>
        </div>
        <?php if (!$isEdit): ?>
        <div class="form-row">
            <label>导入卡密(每行一张, 创建商品时可同时导入)</label>
            <textarea name="cards_import" placeholder="卡密1&#10;卡密2&#10;..."></textarea>
        </div>
        <?php endif; ?>
        <button class="btn" type="submit">保存商品</button>
        <a class="btn gray" href="<?= au('products') ?>">返回</a>
    </form>
</div>
