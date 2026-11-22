<?php
namespace EasyMalt;
use Delight\Cookie\Cookie;

chdir(__DIR__);

require 'init.inc.php';

if (!empty($_POST['do_search'])) {
    $query = [];
    foreach ($_POST as $k => $v) {
        if (empty($v)) {
            continue;
        }
        if ($k == 'amount' || $k == 'date' || $k == 'do_search') {
            continue;
        }
        if ($k == 'amount_op') {
            foreach ($v as $i => $amount_op) {
                $amount = $_POST['amount'][$i];
                if (!empty($amount)) {
                    $query[] = "amount $amount_op $amount";
                }
            }
        } elseif ($k == 'date_op') {
            foreach ($v as $i => $date_op) {
                $date = $_POST['date'][$i];
                if (!empty($date)) {
                    $query[] = "date $date_op $date";
                }
            }
        } else {
            $query[] = "$k = $v";
        }
    }
    header('Location: ./?q=' . urlencode(implode(' AND ', $query)));
    exit();
}


$view = [
    'is_dark' => $GLOBALS['is_dark'],
    'show_cookie_prompt' => !Cookie::get('ECK1C'),
];

if ($view['show_cookie_prompt']) {
    renderView('dashboard', $view);
    return;
}

require_once __DIR__ . '/transaction-report.inc.php';

$builder = new DBQueryBuilder();
$effective_get = $_GET;
$query = explode(' AND ', $effective_get['q'] ?? '');
$query_values = [];
foreach ($query as $k => $q) {
    if (preg_match('/(category|desc|amount|tag|date|account|ids|show_all_cats)\s?([=><]{1,2})\s?(.*)\s?/', $q, $matches)) {
        $query_type = strtolower($matches[1]);
        $operator = $matches[2];
        $value = trim($matches[3]);
        if (empty($value)) {
            continue;
        }
        if ($query_type == 'show_all_cats') {
            $query_values['show_all_cats'] = (bool) $value;
        }
        if ($query_type == 'ids') {
            $query_values['ids'] = $value;
            $builder->where("t.id IN (:ids)", explode(',', $value));
        }
        if ($query_type == 'category' || $query_type == 'cat') {
            $query_values['category'] = $value;
            if ($value == NO_CATEGORY_NAME) {
                $builder->where("t.category IS NULL");
            } else {
                $builder->where("t.category LIKE :cat", '%' . $value .'%');
            }
        }
        if ($query_type == 'desc') {
            $query_values['desc'] = $value;
            $builder->where("(t.memo LIKE :desc OR t.name LIKE :desc OR t.original_name LIKE :desc)", '%' . $value .'%');
        }
        if ($query_type == 'amount') {
            $query_values['amount'][$operator] = (float) $value;
            $builder->where('ABS(ROUND(t.amount*100)) ' . $operator . ' ABS(ROUND(:amount' . $k . '*100))', (float) $value);
        }
        if ($query_type == 'tag') {
            $query_values['tag'] = $value;
            $builder->where("t.tags LIKE :tag", '%' . $value .'%');
        }
        if ($query_type == 'date') {
            $date = date('Y-m-d', strtotime($value));
            $query_values['date'][$operator] = $date;
            $builder->where('DATE(t.date) ' . $operator . ' :date' . $k, $date);
            if ($operator == '>=') {
                $effective_get['from_date'] = $date;
            }
            if ($operator == '<=') {
                $effective_get['to_date'] = $date;
            }
        }
        if ($query_type == 'account') {
            $query_values['account'] = $value;
            $builder->where("a.name LIKE :account", '%' . $value .'%');
        }
    } else {
        if (is_numeric($q)) {
            $query_values['amount']['='] = (float) $q;
            $builder->where('ABS(ROUND(t.amount*100)) = ABS(ROUND(:amount*100))', (float) $q);
        } elseif (!empty($q)) {
            $query_values['desc'] = $q;
            $builder->where("(t.memo LIKE :desc OR t.name LIKE :desc)", '%' . $q .'%');
        }
    }
}

// Show quick dates instead of the search form when no explicit search was used.
$view['show_search_params'] = !empty($query_values);
if (!$view['show_search_params']) {
    $from_date = !empty($effective_get['from_date']) ? $effective_get['from_date'] : date('Y-m-d', strtotime('-15 days'));
    $to_date = !empty($effective_get['to_date']) ? $effective_get['to_date'] : date('Y-m-d');
    $view['from_to_query'] = 'from_date=' . date('Y-m-d', strtotime($from_date)) . '&to_date=' . date('Y-m-d', strtotime($to_date));

    $dates = [];
    foreach ([15, 30, 60, 90, 365] as $days) {
        $from = strtotime("-$days days");
        $to = time();
        $dates['from_date=' . date('Y-m-d', $from) . '&to_date=' . date('Y-m-d', $to)] = "Last $days days";
    }
    for ($t = time(); $t > strtotime('-12 months'); $t = strtotime('-1 month', $t)) {
        $dates['from_date=' . date('Y-m-01', $t) . '&to_date=' . date('Y-m-d', strtotime(date('Y-m-01', strtotime('next month', $t)))-1)] = date("M Y", $t);
    }
    $view['quick_dates'] = $dates;

    if (empty($query_values['date']) && empty($query_values['ids'])) {
        $builder->where('DATE(t.date) >= :from_date', $from_date);
        $builder->where('DATE(t.date) <= :to_date', $to_date);
        $query_values['date']['>='] = $from_date;
        $query_values['date']['<='] = $to_date;
    }
    if (empty($effective_get['q'])) {
        $effective_get['q'] = "date >= $from_date AND date <= $to_date";
    }
}
$effective_query = $effective_get['q'] ?? '';

// Include root categories as filter options even if they have no transactions.
$categories = DB::getAllValues("SELECT DISTINCT name FROM categories ORDER BY name");
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
$view['categories'] = $categories_plus;
$view['tags'] = DB::getAllValues("SELECT DISTINCT name FROM tags ORDER BY name");
$view['accounts'] = DB::getAllValues("SELECT DISTINCT name FROM accounts WHERE name <> '' ORDER BY name");

// Allow a second date or amount parameter if fewer than two were supplied.
foreach (['date', 'amount'] as $param) {
    if (count($query_values[$param] ?? []) < 2) {
        $query_values[$param][''] = '';
    }
}
$view['query_values'] = $query_values;
$view['ops_avail'] = ['=', '<', '<=', '>', '>='];
$view['date_input_values'] = $query_values['date'];
if (($query_values['date']['<='] ?? null) == date('Y-m-d')) {
    $view['date_input_values']['<='] = '';
}

$section_queries = [
    'Expenses' => [
        'where'    => "t.amount < 0 OR (t.amount = 0 AND t.type = 'DEBIT')",
        'order_by' => "amount ASC"
    ],
    'Income' => [
        'where'    => "t.amount > 0 OR (t.amount = 0 AND t.type = 'CREDIT')",
        'order_by' => "amount DESC"
    ]
];
$view['sections'] = [];
$view['pie_url'] = getCurrentUrlReplacing($effective_query, 'category', '__category__');
$first_row = true;

foreach ($section_queries as $what => $select) {
    $builder_s = clone $builder;
    $builder_s->select("SUM(t.amount) AS amount, IFNULL(a.currency, 'CAD') AS currency, t.hidden")
        ->from("v_transactions_reports", 't')
        ->leftJoin("accounts a", 'a.id = t.account_id')
        ->where($select['where'])
        ->groupBy('currency')
        ->orderBy($select['order_by']);

    if (!empty($query_values['category']) || !empty($query_values['show_all_cats'])) {
        $builder_s->select('category')->groupBy('category');
    } else {
        $builder_s->select('group_by_category AS category')->groupBy('group_by_category');
    }

    $data = $builder_s->getAll();
    if (empty($data)) {
        continue;
    }

    $rows = [];
    $totals_by_currency = [];
    $pie_data = [['Category', 'Spending']];
    foreach ($data as $row) {
        if ($row->hidden == 'no') {
            $totals_by_currency[$row->currency] = ($totals_by_currency[$row->currency] ?? 0) + $row->amount;
        }
        // The existing markup labels only the first grouped row as even.
        $classes = [$what, $first_row ? 'even' : 'odd'];
        $first_row = false;
        $uncategorized = $row->category == null;
        if ($uncategorized) {
            $classes[] = 'uncategorized';
        }
        if ($row->hidden == 'yes') {
            $classes[] = 'hidden';
        }
        if ($row->category == 'Netlift') {
            $classes[] = 'netlift';
        }

        $category = $uncategorized ? NO_CATEGORY_NAME : $row->category;
        $rows[] = [
            'class' => implode(' ', $classes),
            'url' => getCurrentUrlReplacing($effective_query, 'category', $category),
            'category' => $category,
            'amount' => $row->amount,
            'currency' => $row->currency,
        ];
        if ($row->hidden != 'yes') {
            $pie_data[] = [$category, abs($row->amount)];
        }
    }

    $totals = [];
    foreach ($totals_by_currency as $currency => $amount) {
        $is_default_currency = is_default_currency($currency);
        if ($amount == 0 && !$is_default_currency) {
            continue;
        }
        $totals[] = [
            'currency' => $currency,
            'amount' => $amount,
            'is_default_currency' => $is_default_currency,
        ];
    }
    $view['sections'][$what] = ['rows' => $rows, 'totals' => $totals, 'pie_data' => $pie_data];
}

$builder->select("t.id, t.date, t.name, t.memo, t.category, t.tags, t.amount, t.hidden, a.currency, a.name AS account")
    ->from("v_transactions_reports", 't')
    ->join('accounts a', "a.id = t.account_id")
    ->orderBy("t.date DESC, t.id DESC");

$transactions = $builder->getAll();
$view['transaction_table'] = empty($transactions)
    ? null
    : prepareTransactionTable($transactions, 'Transactions Details', $effective_query);

$scroll_position = $effective_get['scrollPos'] ?? null;
$view['scroll_position'] = !empty($scroll_position) && is_numeric($scroll_position) ? $scroll_position + 0 : null;
unset($effective_get['scrollPos']);
$view['history_url'] = '/?' . http_build_query($effective_get) . '&scrollPos=';

renderView('dashboard', $view);
$_SESSION['previous_page'] = preg_replace('/&scrollPos=\d+/', '', $_SERVER['REQUEST_URI']);
