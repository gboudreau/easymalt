<?php
namespace EasyMalt;
chdir(__DIR__.'/..');
require 'init.inc.php';

$q = "SELECT id, `name`, currency,  balance, SUBSTR(balance_date, 1, 16) AS last_updated, plaid_id FROM accounts WHERE account_number NOT LIKE '-%' ORDER BY `name`";
$data = DB::getAll($q);

$totals = [];
foreach ($data as $row) {
    $totals[$row->currency] = ($totals[$row->currency] ?? 0) + $row->balance;
}

renderView('accounts', [
    'back_link' => $_SESSION['previous_page'],
    'rows' => $data,
    'totals' => $totals,
    'is_dark' => $is_dark,
]);
