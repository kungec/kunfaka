<?php
/**
 * 前台: 首页/商品列表
 */
class HomeController
{
    public function actionIndex()
    {
        $catId = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
        $categories = cat_list();
        $where = 'status = 1';
        $params = [];
        if ($catId > 0) {
            $where .= ' AND category_id = ?';
            $params[] = $catId;
        }
        $products = DB::fetchAll("SELECT * FROM products WHERE {$where} ORDER BY sort ASC, id DESC LIMIT 100", $params);
        // 分组等级可见性过滤(低于分组最低等级的会员/游客不可见)
        $level = viewer_level();
        $products = array_values(array_filter($products, function ($p) use ($level) {
            return product_visible($p, $level);
        }));
        $stock = [];
        $catName = [];
        foreach ($categories as $c) $catName[$c['id']] = $c['name'];
        // 单次聚合全部分类商品的库存(避免逐商品N+1查询)
        $stockMap = [];
        if ($products) {
            foreach (DB::fetchAll('SELECT product_id, COUNT(*) AS n FROM cards WHERE status = 0 AND product_id IN (' . implode(',', array_map(function ($p) { return (int)$p['id']; }, $products)) . ') GROUP BY product_id') as $r) {
                $stockMap[(int)$r['product_id']] = (int)$r['n'];
            }
        }
        $catCounts = [];
        foreach ($products as &$p) {
            $p['cat_name'] = isset($catName[$p['category_id']]) ? $catName[$p['category_id']] : '商品';
            $catCounts[$p['category_id']] = (isset($catCounts[$p['category_id']]) ? $catCounts[$p['category_id']] : 0) + 1;
            $stock[$p['id']] = $stockMap[(int)$p['id']] ?? 0;
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
