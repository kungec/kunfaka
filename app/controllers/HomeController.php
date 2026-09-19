<?php
/**
 * 前台: 首页/商品列表
 */
class HomeController
{
    public function actionIndex()
    {
        $catId = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
        $categories = DB::fetchAll('SELECT * FROM categories ORDER BY sort ASC, id ASC');
        $where = 'status = 1';
        $params = [];
        if ($catId > 0) {
            $where .= ' AND category_id = ?';
            $params[] = $catId;
        }
        $products = DB::fetchAll("SELECT * FROM products WHERE {$where} ORDER BY sort ASC, id DESC LIMIT 100", $params);
        $stock = [];
        $catName = [];
        foreach ($categories as $c) $catName[$c['id']] = $c['name'];
        $catCounts = [];
        foreach ($products as &$p) {
            $p['cat_name'] = isset($catName[$p['category_id']]) ? $catName[$p['category_id']] : '商品';
            $catCounts[$p['category_id']] = (isset($catCounts[$p['category_id']]) ? $catCounts[$p['category_id']] : 0) + 1;
            $stock[$p['id']] = product_stock($p['id']);
        }
        unset($p);
        foreach ($categories as $c) {
            if (!isset($catCounts[$c['id']])) $catCounts[$c['id']] = 0;
        }
        View::theme('home', [
            'categories' => $categories,
            'products' => $products,
            'stock' => $stock,
            'catId' => $catId,
            'catCounts' => $catCounts,
            'pageTitle' => setting('site_name', '坤发卡'),
        ]);
    }
}
