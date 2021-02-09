<?php

namespace EasyMalt;

use DateTime;
use Exception;
use TomorrowIdeas\Plaid\Entities\User;

class Plaid
{
    private static function getClient() : \TomorrowIdeas\Plaid\Plaid {
        return new \TomorrowIdeas\Plaid\Plaid(Config::get('PLAID_CLIENT_ID'), Config::get('PLAID_SECRET'), Config::get('PLAID_ENVIRONMENT'));
    }

    public static function createToken(?string $access_token = NULL) {
        $webhook = Config::get('BASE_URL') . '/api/plaid-webhooks/';
        $user = Config::get('PLAID_USER');
        $puser = new User(1, $user->name, $user->phone, date('c', strtotime('2021-01-01 09:00:00')), $user->email);

        $plaid = static::getClient();
        $token = $plaid->tokens->create('easymalt', $user->language, $user->countries, $puser, empty($access_token) ? ['transactions'] : [], $webhook, NULL, NULL, $access_token);
        if (empty($token->link_token)) {
            throw new Exception("No link_token found in response: " . json_encode($token));
        }
        return $token;
    }

    public static function exchangeToken($public_token, $metadata) {
        $plaid = static::getClient();
        $response = $plaid->items->exchangeToken($public_token);
        if (empty($response->access_token)) {
            throw new Exception("No access_token found in response: " . json_encode($response));
        }

        $q = "SELECT 1 FROM plaid_tokens WHERE access_token = :access_token";
        $exists = DB::getFirstValue($q, $response->access_token);
        if (!$exists) {
            $q = "INSERT INTO plaid_tokens SET plaid_item_id = :item_id, access_token = :access_token, metadata = :metadata";
            DB::insert($q, ['item_id' => $response->item_id, 'access_token' => $response->access_token, 'metadata' => $metadata]);
        } else {
            $q = "UPDATE plaid_tokens SET plaid_item_id = :item_id, metadata = :metadata WHERE access_token = :access_token";
            DB::execute($q, ['item_id' => $response->item_id, 'access_token' => $response->access_token, 'metadata' => $metadata]);
        }
    }

    public static function importTransactions(?string $item_id = NULL, string $start_date = '-4 weeks', string $end_date = 'now') {
        if (isset($item_id)) {
            $q = "SELECT * FROM plaid_tokens WHERE plaid_item_id = :id";
            $tokens = DB::getAll($q, $item_id);
        } else {
            $q = "SELECT * FROM plaid_tokens";
            $tokens = DB::getAll($q);
        }

        $plaid = static::getClient();
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);

        //var_dump($start_date);
        //var_dump($end_date);
        //die();

        if (!isset($item_id)) {
            header('Content-type: text/plain; charset=UTF-8');
        }

        foreach ($tokens as $token) {
            // client is not authorized to access the following products: ["transactions_refresh"], probably because environment <> production
            // $plaid->transactions->refresh($token->access_token);

            $md = json_decode($token->metadata);
            echo "Institution: {$md->institution->name} (ID $token->plaid_item_id)\n";

            $offset = 0;
            $all_transactions = [];
            try {
                do {
                    $count = 500;
                    $response = $plaid->transactions->list($token->access_token, $start, $end, ['count' => $count, 'offset' => $offset]);
                    $all_transactions = array_merge($all_transactions, $response->transactions);
                    $offset += $count;
                } while ($response->total_transactions > count($all_transactions) && count($response->transactions) > 0);
                $response->transactions = $all_transactions;
            } catch (Exception $ex) {
                echo "Error importing transactions: " . $ex->getMessage() . "\n\n";
                continue;
            }

            $accounts = [];
            foreach ($response->accounts as $account) {
                $plaid_id = $account->account_id;
                $balance = ($account->type == 'credit' ? -1 : 1) * $account->balances->current;
                $currency = $account->balances->iso_currency_code;
                $name = $account->name . ' ' . $account->mask;
                echo "Account: $name [ID $plaid_id] = $balance $currency\n";

                $q = "SELECT * FROM accounts WHERE plaid_id = :id";
                $account_local = DB::getFirst($q, $plaid_id);
                if (empty($account_local)) {
                    mail('guillaume@danslereseau.com', "Plaid returned an unknown account", json_encode($account, JSON_PRETTY_PRINT));
                } else {
                    $accounts[$plaid_id] = $account_local;

                    $q = "UPDATE accounts SET balance = :balance WHERE id = :id";
                    DB::execute($q, ['id' => $account_local->id, 'balance' => $balance]);
                }
            }

            foreach ($response->transactions as $txn) {
                $plaid_id = $txn->transaction_id;
                $is_pending = $txn->pending ?? FALSE;
                $name = $txn->name;
                $date = $txn->date ?? $txn->authorized_date;
                $amount = -$txn->amount; // Positive values when money moves out of the account; negative values when money moves in.
                $account = @$accounts[$txn->account_id];
                if ($is_pending) {
                    echo "  - Transaction [ID $plaid_id]: $name - is pending; skipping\n";
                    continue;
                }
                if (empty($account)) {
                    echo "  - Transaction [ID $plaid_id]: $name - unknown account; skipping\n";
                    continue;
                }
                echo "  - Transaction [ID $plaid_id]: $date $name $amount\n";

                $q = "SELECT * FROM transactions WHERE plaid_id = :id";
                $txn_local = DB::getFirst($q, $plaid_id);
                if (!$txn_local) {
                    $q = "INSERT INTO transactions SET account_id = :account, `date` = :date, `type` = :type, amount = :amount, name = :name, plaid_id = :plaid_id, unique_id = :plaid_id";
                    DB::insert($q, ['account' => $account->id, 'date' => $date, 'type' => ($amount > 0 ? 'CREDIT' : 'DEBIT'), 'amount' => $amount, 'name' => $name, 'plaid_id' => $plaid_id]);

                    // Old code to match Plaid transactions to previously-imported transactions
                    //$num_days = string_contains($name, 'PERSONNELLE') ? 10 : (string_contains($name, 'Maxi') || string_contains($name, 'McDo') || string_contains($name, 'Muffin') || string_contains($name, 'Aramark') || string_contains($name, 'RESTAURANT INVITATION') || string_contains($name, 'Microsoft*Office 365') ? 4 : 2);
                    //$q = "SELECT * FROM transactions WHERE DATE(`date`) BETWEEN DATE_SUB(:date, INTERVAL $num_days DAY) AND :date AND amount BETWEEN :amount-0.001 AND :amount+0.001 AND account_id = :account AND plaid_id IS NULL";
                    //$txns_local = DB::getAll($q, ['date' => $date, 'amount' => $amount, 'account' => $account->id]);
                    //if (count($txns_local) == 0) {
                    //    if (string_contains($name, 'EFT Withdrawal to BELL CANADA') || string_contains($name, 'Amazon')) {
                    //        continue;
                    //    }
                    //    die("Transaction not found in local DB: " . json_encode($txn, JSON_PRETTY_PRINT) . json_encode($txns_local, JSON_PRETTY_PRINT));
                    //    //continue;
                    //}
                    //if (count($txns_local) > 1) {
                    //    die("Too many transaction found in local DB: " . json_encode($txns_local, JSON_PRETTY_PRINT));
                    //    //continue;
                    //}
                    //
                    //$txn_local = $txns_local[0];
                    //$q = "UPDATE transactions SET plaid_id = :plaid_id WHERE id = :id";
                    //DB::execute($q, ['id' => $txn_local->id, 'plaid_id' => $plaid_id]);
                }
            }
            echo "\n";
        }
    }

    public static function removeItem($access_token) {
        $plaid = static::getClient();
        try {
            $plaid->items->remove($access_token);

            $q = "SELECT metadata FROM plaid_tokens WHERE access_token = :token";
            $metadata = DB::getFirstValue($q, $access_token);
            $metadata = json_decode($metadata);
            $accounts = [];
            foreach ($metadata->accounts as $account) {
                $accounts[] = $account->id;
            }
            $q = "UPDATE accounts SET plaid_id = NULL WHERE plaid_id IN (:accounts)";
            DB::execute($q, ['accounts' => $accounts]);

            $q = "DELETE FROM plaid_tokens WHERE access_token = :token";
            DB::execute($q, $access_token);
        } catch (Exception $ex) {
            error_log("Failed to remove item: " . $ex->getMessage());
        }
    }
}
