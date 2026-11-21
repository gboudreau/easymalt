<?php
namespace EasyMalt;
chdir(__DIR__.'/..');
require 'init.inc.php';
require_once __DIR__ . '/../transaction-report.inc.php';

$year_selected = (int) $_GET['year'];

$q = "SELECT t.category, ROUND(t.amount) AS amount, t.id, t.date, IFNULL(t.display_name, t.name) AS name, t.memo, t.tags, t.hidden, a.currency, a.name AS account
        FROM transactions t
        LEFT JOIN accounts a ON (a.id = t.account_id)
       WHERE t.date BETWEEN '$year_selected-01-01' AND '" . ($year_selected+1) . "-01-01'
         AND (t.category NOT LIKE 'Transfer%' OR t.category = 'Transfer: Investments')
       ORDER BY t.category, ABS(t.amount) DESC";
$transactions = DB::getAll($q);

$incomes = [];
$expenses = [];
$total_incomes = 0;
$total_expenses = 0;
foreach ($transactions as $txn) {
    if ((string_contains($txn->category, 'Income') || ($txn->amount > 0 && ($txn->category == 'Giving: Gift' || $txn->category == 'Music: Gig' || $txn->category == 'Taxes: Federal' || $txn->category == 'Taxes: Provincial' || $txn->category == 'Music: Rhapsodie')))) {
        if (preg_match('/Income: (.*)$/', $txn->category, $re)) {
            $category = "Income: $re[1]";
        } else {
            if ($txn->category == 'Income') {
                $txn->category = 'Other';
            }
            if ($txn->category == 'Giving: Gift') {
                $txn->category = 'Gift';
            }
            $category = "Income: $txn->category";
        }
        @$incomes[$category]['Budget'] += $txn->amount;
        $total_incomes += $txn->amount;
    } else {
        $from = 'Budget';
        $cats = [];
        if ($txn->category == 'Kids: School: Tuition Savings Plan') {
            $txn->category = 'Investing: Tuition Savings Plan';
            $tuition_savings_plan += $txn->amount;
        }
        foreach (explode(': ', $txn->category) as $cat) {
            $cats[] = $cat;
            $to = implode(': ', $cats);
            @$expenses[$from][$to] += $txn->amount;
            $from = $to;
        }
        $total_expenses += -$txn->amount;
    }
}

$diff_investing = 0;
if ($total_expenses > $total_incomes) {
    $diff_investing = $total_expenses - $total_incomes;
}

$datas = [];
$labels = [];

$labels['Budget'] = "Budget - " . number_format(round($total_incomes)) . '$';

foreach ($incomes as $from => $data_to) {
    foreach ($data_to as $to => $amount) {
        $datas[] = ['from' => $from, 'to' => $to, 'flow' => $amount];
        $labels[$from] = str_replace('Income: ', '', $from) . " - " . number_format(round($amount)) . '$';
    }
}

foreach ($expenses as $from => $data_to) {
    foreach ($data_to as $to => $amount) {
        if ($to == 'Transfer') {
            continue;
        }
        if ($from == 'Transfer') {
            if (-$amount > $diff_investing) {
                $amount += $diff_investing;
            }
            $datas[] = ['from' => 'Budget', 'to' => 'Investing', 'flow' => -$amount];
            $labels["Investing"] = "Investing - " . number_format(round(-$amount - ($tuition_savings_plan ?? 0))) . '$';
            continue;
        }
        // Example of how to NOT display the details of sub-categories
        //if ($from == 'Kids: School') {
        //    continue;
        //}
        if (abs($amount) < 300) {
            continue;
        }
        $datas[] = ['from' => $from, 'to' => $to, 'flow' => -$amount];
        $labels[$to] = "$to - " . number_format(round(-$amount)) . '$';
    }
}

renderView('sankey', [
    'is_dark' => $is_dark,
    'year_selected' => $year_selected,
    'years' => range(2011, (int) date('Y')),
    'flows' => $datas,
    'labels' => $labels,
    'transaction_table' => prepareTransactionTable($transactions, 'Transactions Details', $_GET['q'] ?? ''),
]);
