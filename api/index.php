<?php
namespace EasyMalt;
chdir(__DIR__.'/..');
require 'init.inc.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $access_token = getAccessTokenFromHttpHeaders();
    error_log($access_token);
    if (!$access_token || $access_token != Config::get('API_AUTH_ACCESS_TOKEN')) {
        header($_SERVER['SERVER_PROTOCOL'] . " 403 Forbidden", TRUE, 403);
        die("Failed authentication.");
    }

    $body = file_get_contents("php://input");
    $json = @json_decode($body);
    if (!$json) {
        header($_SERVER['SERVER_PROTOCOL'] . " 400 Bad Request", TRUE, 400);
        die("Not JSON.");
    }

    echo "[R] Importing accounts ... \n";
    foreach ($json->accounts as $account) {
        $q = "INSERT INTO accounts 
                 SET routing_number = :routing_number, account_number = :account_number, balance = :balance, currency = :currency, balance_date = :balance_date 
                  ON DUPLICATE KEY UPDATE balance = VALUES(balance), balance_date = VALUES(balance_date)";
        DB::insert(
            $q,
            [
                'routing_number' => $account->routing_number,
                'account_number' => $account->account_number,
                'balance' => $account->balance,
                'currency' => $account->currency,
                'balance_date' => date('Y-m-d H:i:s', strtotime($account->balance_date))
            ]
        );
    }

    echo "[R] Importing transactions ... \n";
    $new_txn_ids = [];
    foreach ($json->transactions as $txn) {
        $q = "INSERT IGNORE INTO transactions
                 SET account_id = :account_id, unique_id = :unique_id, `date` = :date, `type` = :type, amount = :amount, name = :name, memo = :memo";
        $id = DB::insert(
            $q,
            [
                'account_id' => $txn->account_id,
                'unique_id' => $txn->unique_id,
                'date' => date('Y-m-d H:i:s', strtotime($txn->date)),
                'type' => $txn->type,
                'amount' => $txn->amount,
                'name' => $txn->name,
                'memo' => $txn->memo
            ]
        );
        if (!empty($id)) {
            $new_txn_ids[] = $id;
        }
    }

    postProcess();

    $url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . '/search/';
    $q = "ids=" . implode(',', $new_txn_ids);
    echo "\n\nReview new transactions here: $url?q=" . urlencode($q);
} else {
    die('meh');
}

function getAccessTokenFromHttpHeaders() {
    if (isset($_GET['token'])) {
        return $_GET['token'];
    } elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $auth_header = @$headers['Authorization'];
    } else {
        $auth_header = @$_SERVER['HTTP_AUTHORIZATION'];
    }
    if (preg_match('/Bearer (.*)/', $auth_header, $regs)) {
        return $regs[1];
    }
    return FALSE;
}

function postProcess() {
    echo "[PP] Post-processing transactions ... \n";
    $q = "SELECT * FROM post_processing ORDER BY prio DESC";
    $pp_settings = DB::getAll($q);

    $q = "SELECT * FROM transactions WHERE post_processed = 'no' ORDER BY id";
    $transactions = DB::getAll($q);
    foreach ($transactions as $t) {
        foreach ($pp_settings as $pp) {
            if (preg_match('@' . $pp->regex . '@i', $t->name . ' ' . $t->memo, $re)) {
                $params = ['txn_id' => (int) $t->id];
                $updates = [];
                $log_details = [];
                if (!empty($pp->display_name)) {
                    $updates[] = 'display_name = :display_name';
                    $display_name = $pp->display_name;
                    for ($i=1; $i<count($re); $i++) {
                        $display_name = str_replace('{'.$i.'}', $re[$i], $display_name);
                    }
                    $params['display_name'] = $display_name;
                    $log_details[] = "Name: $display_name";
                }
                if (!empty($pp->category)) {
                    $updates[] = 'category = :category';
                    $params['category'] = $pp->category;
                    $log_details[] = "Category: $pp->category";
                }
                if (!empty($pp->tags)) {
                    $updates[] = 'tags = :tags';
                    $params['tags'] = $pp->tags;
                    $log_details[] = "Tags: $pp->tags";
                }
                if (!empty($updates)) {
                    echo "  $t->name => " . implode("  ", $log_details) . "\n";
                    $updates[] = "post_processed = 'yes'";
                    $q = "UPDATE transactions SET " . implode(", ", $updates) . " WHERE id = :txn_id";
                    DB::execute($q, $params);
                    break;
                }
            }
        }
    }
}
