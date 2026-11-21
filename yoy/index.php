<?php
namespace EasyMalt;
chdir(__DIR__.'/..');
require 'init.inc.php';

$category_selected = $_GET['cat'];

// Categories list; add values for 'root categories', to be able to get report for those, even if no transactions
$q = "SELECT DISTINCT name FROM categories ORDER BY name";
$categories = DB::getAllValues($q);
array_unshift($categories, NO_CATEGORY_NAME);
$categories_plus = [];
foreach ($categories as $cat) {
    $num = substr_count($cat, ':');
    if ($num > 0) {
        $root = substr($cat, 0, strrpos($cat, ':'));
        if (!array_search($root, $categories_plus)) {
            $categories_plus[] = $root;
        }
    }
    $categories_plus[] = $cat;
}
$categories = $categories_plus;

$q = "SELECT YEAR(t.date) AS x, SUM(t.amount) AS y FROM transactions t WHERE t.category LIKE CONCAT(:cat, '%') AND date > '2011-01-01' GROUP BY YEAR(t.date) ORDER BY t.date";
$datas = DB::getAll($q, $category_selected);

$data = [];
$labels = [];
foreach ($datas as $value) {
    $data[] = $value->y;
    $labels[] = $value->x;
}

renderView('yoy', [
    'is_dark' => $GLOBALS['is_dark'],
    'categories' => $categories,
    'category_selected' => $category_selected,
    'labels' => $labels,
    'series' => $data,
]);
