<?php
namespace EasyMalt;
chdir(__DIR__.'/..');
require 'init.inc.php';

$default_currency = Config::get('DEFAULT_CURRENCY');
$default_currency_name = array_keys($default_currency)[0];

if (empty($_GET['scrollPos'])) {
    $_GET['scrollPos'] = 0;
}

if (!empty($_REQUEST['id']) && $_REQUEST['id'] != 'new') {
    $q = "SELECT t.*, IFNULL(a.currency, :default_currency) AS currency, a.name AS account_name 
            FROM transactions t 
            LEFT JOIN accounts a ON (t.account_id = a.id) WHERE t.id = :id";
    $txn = DB::getFirst($q, ['id' => (int) @$_REQUEST['id'], 'default_currency' => $default_currency_name]);

    if (empty($txn)) {
        die("Transaction (ID " . @$_REQUEST['id'] . ") not found.");
    }

    if (empty($txn->tags)) {
        $txn->tags = [];
    } else {
        $txn->tags = explode(',', $txn->tags);
    }
    if ($txn->display_name === NULL) {
        $txn->display_name = '';
    }
    if ($txn->category === NULL) {
        $txn->category = '';
    }
} else {
    $txn = (object) [
        'id' => 'new',
        'name' => '',
        'display_name' => '',
        'memo' => '',
        'date' => date('Y-m-d'),
        'amount' => NULL,
        'category' => '',
        'tags' => [],
    ];
}

if (isset($_POST['postprocess'])) {
    $q = "SELECT * FROM transactions WHERE id = :id";
    $t = DB::getFirst($q, $_POST['postprocess']);
    $post_process_result = postProcessTransaction($t);
} elseif (isset($_POST['id'])) {
    $updates = [];
    $params = [];

    $updates[] = "post_processed = 'yes'";

    if ($_POST['id'] == 'new') {
        $updates[] = 'account_id = :account_id';
        $params['account_id'] = $_POST['account_id'];
        $updates[] = 'amount = :amount';
        $params['amount'] = $_POST['amount'];
        $updates[] = 'type = :type';
        $params['type'] = $_POST['amount'] < 0 ? 'DEBIT' : 'CREDIT';

        $q = "SELECT MAX(CAST(SUBSTRING(unique_id, 8) AS INT)) FROM transactions WHERE account_id = :account_id AND unique_id LIKE 'manual-%'";
        $max_unique_id = (int) DB::getFirstValue($q, $_POST['account_id']);
        $updates[] = 'unique_id = :unique_id';
        $params['unique_id'] = "manual-" . ($max_unique_id + 1);
    }

    if (isset($_POST['display_name'])) {
        $updates[] = 'display_name = :display_name';
        $params['display_name'] = empty($_POST['display_name']) ? NULL : $_POST['display_name'];
    }
    if (isset($_POST['memo'])) {
        $updates[] = 'memo = :memo';
        $params['memo'] = empty($_POST['memo']) ? NULL : $_POST['memo'];
    }
    if (isset($_POST['category'])) {
        $updates[] = 'category = :category';
        $params['category'] = empty($_POST['category']) ? NULL : $_POST['category'];
    }
    if (!empty($_POST['amounts'])) {
        $updates[] = 'amount = :amount';
        $params['amount'] = (float) $_POST['amounts'][0];
    }
    if (!empty($_POST['date'])) {
        $updates[] = $_POST['id'] == 'new'
            ? 'date = :date'
            : 'date = TIMESTAMP(:date, TIME(date))';
        $params['date'] = $_POST['date'];
    }
    $updates[] = 'tags = :tags';
    $params['tags'] = empty($_POST['tags']) ? NULL : implode(',', $_POST['tags']);

    if ($_POST['id'] == 'new') {
        $q = "INSERT INTO transactions SET " . implode(', ', $updates);
    } else {
        $q = "UPDATE transactions SET " . implode(', ', $updates) . " WHERE id = :id";
        $params['id'] = (int) $_POST['id'];
    }
    DB::execute($q, $params);

    if ($_POST['id'] == 'new') {
        $q = "UPDATE accounts SET balance = balance + :amount, balance_date = NOW() WHERE id = :account_id";
        DB::execute($q, ['account_id' => $_POST['account_id'], 'amount' => $_POST['amount']]);
    }

    if (!empty($_POST['amounts'])) {
        $q = "SELECT unique_id FROM transactions WHERE id = :id";
        $txn_unique_id = DB::getFirstValue($q, $_POST['id']);

        $next_available_unique_id = 2;
        while (TRUE) {
            $q = "SELECT 1 FROM transactions WHERE unique_id = :unique_id";
            $txn_found = DB::getFirstValue($q, $txn_unique_id . "-" . $next_available_unique_id);
            if (!$txn_found) {
                break;
            }
            $next_available_unique_id++;
        }

        // Split transaction
        for ($i = 1; $i<count($_POST['amounts']); $i++) {
            $amount = (float) $_POST['amounts'][$i];
            $q = "INSERT INTO transactions (account_id, unique_id, `date`, type, amount, display_name, name, memo, tags, category, post_processed) SELECT account_id, CONCAT(unique_id, '-', :unique_id), `date`, type, :amount, display_name, name, memo, tags, category, post_processed FROM transactions WHERE id = :id";
            $params = [
                'id' => (int) $_POST['id'],
                'unique_id' => $next_available_unique_id,
                'amount' => $amount,
            ];
            DB::insert($q, $params);
            $next_available_unique_id++;
        }
    }

    if (@$_POST['post_processing_rule'] == 'true') {
        $matches_name = $_POST['post_processing_rule_matching'];
        if (@$_POST['post_processing_rule_matching_format'] != 'regex') {
            $matches_name = preg_quote($matches_name);
        }
        $matches_amount = NULL;
        if (!empty($_POST['post_processing_rule_amount_equals'])) {
            $matches_amount = $_POST['post_processing_rule_amount_equals'];
        }
        $updates = [];
        $params = [];
        if (@$_POST['post_processing_rule_desc'] == 'true') {
            $updates[] = 'display_name = :display_name';
            $params['display_name'] = $_POST['display_name'];
        }
        if (@$_POST['post_processing_rule_memo'] == 'true') {
            $updates[] = 'memo = :memo';
            $params['memo'] = $_POST['memo'];
        }
        if (@$_POST['post_processing_rule_category'] == 'true') {
            $updates[] = 'category = :category';
            $params['category'] = $_POST['category'];
        }
        if (@$_POST['post_processing_rule_tags'] == 'true') {
            $updates[] = 'tags = :tags';
            $params['tags'] = empty($_POST['tags']) ? NULL : implode(',', $_POST['tags']);
        }
        $q = "INSERT INTO post_processing SET regex = :regex, amount_equals = :amount_equals, " . implode(", ", $updates) . " ON DUPLICATE KEY UPDATE " . implode(", ", $updates);
        DB::insert($q, array_merge($params, ['regex' => $matches_name, 'amount_equals' => $matches_amount]));

        $q = "SELECT id FROM transactions WHERE `name` REGEXP :regex AND (post_processed = 'no' OR id >= :id)";
        $pp = ['regex' => $matches_name, 'id' => $_POST['id']];
        if (!empty($matches_amount)) {
            $q .= " AND amount = :matches_amount";
            $pp['matches_amount'] = $matches_amount;
        }
        $txn_ids = DB::getAllValues($q, $pp);

        $updates[] = "post_processed = 'yes'";
        foreach ($txn_ids as $txn_id) {
            $q = "UPDATE transactions SET " . implode(', ', $updates) . " WHERE id = :id";
            DB::execute($q, array_merge($params, ['id' => $txn_id]));
        }
    }

    header('Location: ' . $_POST['goto']);
    exit(0);
}

$q = "SELECT `name` FROM tags ORDER BY `name`";
$tags = DB::getAllValues($q);

$q = "SELECT DISTINCT category FROM (SELECT category FROM transactions UNION ALL SELECT name FROM categories) a ORDER BY category";
$cats = DB::getAllValues($q);

$goto_link = $_SESSION['previous_page'] . (string_contains($_SESSION['previous_page'], '?') ? '&' : '?') .'scrollPos=' . $_GET['scrollPos'];

$accounts = [];
if ($txn->id == 'new') {
    $q = "SELECT id, name FROM accounts ORDER by name";
    $accounts = DB::getAll($q);
}

renderView('transaction', [
    'txn' => $txn,
    'tags' => $tags,
    'cats' => $cats,
    'goto_link' => $goto_link,
    'accounts' => $accounts,
    'is_dark' => $is_dark,
    'post_process_result' => $post_process_result ?? null,
]);
