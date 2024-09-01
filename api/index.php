<?php
namespace EasyMalt;
chdir(__DIR__.'/..');
require 'init.inc.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $access_token = getAccessTokenFromHttpHeaders();
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
        $q = "SELECT 1 FROM accounts WHERE routing_number = :routing_number AND account_number = CONCAT('-', :account_number)";
        $is_deleted = DB::getFirstValue($q, ['routing_number' => $account->routing_number, 'account_number' => $account->account_number]);
        if ($is_deleted) {
            continue;
        }

        if ($account->routing_number == 'questrade') {
            if ($account->account_number == '30103780') {
                $account->account_number = '7';
            }
            if ($account->account_number == '30104231') {
                $account->account_number = '8';
            }
            if ($account->account_number == '30104232') {
                $account->account_number = '9';
            }
        }

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
        if ($txn->name == 'Interest Paid' && abs($txn->amount) < 0.01) {
            continue;
        }

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

    postProcessNewTransactions();

    if (!empty($new_txn_ids)) {
        $url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . '/';
        $q = "ids=" . implode(',', array_unique($new_txn_ids));
        $url = "$url?q=" . urlencode($q);
        echo "\nReview new transactions here: \n";
        if (@$_REQUEST['format'] == 'html') {
            echo "<a href='$url'>$url</a>";
        } else {
            echo $url;
        }
    }
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
