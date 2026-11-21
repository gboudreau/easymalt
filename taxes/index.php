<?php
namespace EasyMalt;
chdir(__DIR__.'/..');
require 'init.inc.php';

$year = date('Y', strtotime('last year'));
if (!empty($_GET['year'])) {
    $year = $_GET['year'];
}

$taxes_config = Config::get('TAXES_WHERE_CLAUSE');
$where = [];
$params = [];
$cat_idx = 1;
$tag_idx = 1;
foreach ($taxes_config as $tc) {
    $sub_wheres = [];
    if (!empty($tc['category'])) {
        if (is_array($tc['category'])) {
            $cats = [];
            foreach ($tc['category'] as $c) {
                $cats[] = "category LIKE :cat_$cat_idx";
                $params["cat_$cat_idx"] = $c;
                $cat_idx++;
            }
            $sub_wheres[] = "(".implode(' OR ', $cats).")";
        } else {
            $sub_wheres[] = "category LIKE :cat_$cat_idx";
            $params["cat_$cat_idx"] = $tc['category'];
            $cat_idx++;
        }
    }
    if (!empty($tc['tag'])) {
        if (is_array($tc['tag'])) {
            $cats = [];
            foreach ($tc['tag'] as $c) {
                $cats[] = "tags LIKE :tag_$tag_idx";
                $params["tag_$tag_idx"] = $c;
                $tag_idx++;
            }
            $sub_wheres[] = "(".implode(' OR ', $cats).")";
        } else {
            $sub_wheres[] = "tags LIKE :tag_$tag_idx";
            $params["tag_$tag_idx"] = $tc['tag'];
            $tag_idx++;
        }
    }
    $where[] = "(" . implode(" AND ", $sub_wheres) . ")";
}
$where = implode(" OR ", $where);

$params['year'] = $year;
$q = "SELECT * 
        FROM v_transactions_reports 
       WHERE DATE_FORMAT(`date`, '%Y') = :year
         AND ($where)
       ORDER BY category, `date`";
$transactions = DB::getAll($q, $params);

renderView('taxes', [
    'year' => $year,
    'taxes_config' => $taxes_config,
    'transactions' => $transactions,
    'scroll_pos' => $_GET['scrollPos'] ?? null,
]);

$_SESSION['previous_page'] = preg_replace('/&scrollPos=\d+/', '', $_SERVER['REQUEST_URI']);
