<?php
namespace EasyMalt;

chdir(__DIR__.'/../..');
require_once 'init.inc.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Maybe later: verify webhooks? ref: https://plaid.com/docs/api/webhook-verification/

    //$plaid = new Plaid(Config::get('PLAID_CLIENT_ID'), Config::get('PLAID_SECRET'), Config::get('PLAID_ENVIRONMENT'));

    //error_log($_SERVER['HTTP_PLAID_VERIFICATION']);
    // eg: eyJhbGciOiJFUzI1NiIsImtpZCI6IjZjNTUxNmUxLTkyZGMtNDc5ZS1hOGZmLTVhNTE5OTJlMDAwMSIsInR5cCI6IkpXVCJ9.eyJpYXQiOjE2MTI4MDQ5NTMsInJlcXVlc3RfYm9keV9zaGEyNTYiOiI1MTM3Mzg3MzIzNDUzMDVhZGQ5YzY2MjJkZjBjMTMyZWZiZTE5NzNmM2RlZmRlMGIzMzk1NzJiMjk4MjhmMTM0In0.s9FswU2ip2GcRwp9OTPsR4BO-nDhR6NFsqEDAX4-0UZHywNBx93owzFv0hIHF6hxATGD4q5j_Z2_WNGYlWULmg

    //$verification_key = $plaid->webhooks->getVerificationKey($key_id);

    /* Example payload:
     * {
     *   "webhook_type": "ITEM",
     *   "webhook_code": "ERROR",
     *   "item_id": "wz666MBjYWTp2PDzzggYhM6oWWmBb",
     *   "error": {
     *      "display_message": null,
     *      "error_code": "ITEM_LOGIN_REQUIRED",
     *      "error_message": "the login details of this item have changed (credentials, MFA, or required user action) and a user login is required to update this information. use Link's update mode to restore the item to a good state",
     *      "error_type": "ITEM_ERROR",
     *     "status": 400
     *   }
     * }
     */

    $body = file_get_contents("php://input");
    $json = @json_decode($body);
    if (!$json) {
        header($_SERVER['SERVER_PROTOCOL'] . " 400 Bad Request", TRUE, 400);
        die("Not JSON.");
    }

    $q = "INSERT INTO plaid_events SET webhook_type = :type, webhook_code = :code, item_id = :item_id, json = :json";
    DB::insert($q, ['type' => $json->webhook_type, 'code' => $json->webhook_code, 'item_id' => $json->item_id ?? NULL, 'json' => json_encode($json)]);

    $subject = "[Plaid Webhook] $json->webhook_code";
    $body = json_encode($json, JSON_PRETTY_PRINT);

    if ($json->webhook_type == 'ITEM' && $json->webhook_code == 'ERROR' && $json->error->error_code == 'ITEM_LOGIN_REQUIRED') {
        $q = "SELECT metadata FROM plaid_tokens WHERE plaid_item_id = :id";
        $metadata = DB::getFirstValue($q, $json->item_id);
        $metadata = json_decode($metadata);

        $account_id = first($metadata->accounts)->id;
        $url = Config::get('BASE_URL') . "/link/?account=" . urlencode($account_id);
        $subject = "[Plaid Webhook] Account needs re-auth";
        $body = "{$metadata->institution->name} needs re-auth on Plaid.\n\nGo here to do so: $url";
    }
    if ($json->webhook_type == 'TRANSACTIONS') {
        switch ($json->webhook_code) {
        case 'TRANSACTIONS_REMOVED':
            $txns = [];
            foreach ($json->removed_transactions as $txn_id) {
                $q = "SELECT t.* FROM transactions t WHERE t.plaid_id = :id";
                $txn = DB::getFirst($q, $txn_id);
                if ($txn) {
                    $txns[] = json_encode($txn, JSON_PRETTY_PRINT);
                }
            }
            if (empty($txns)) {
                $body = NULL;
            } else {
                //$subject = "[Plaid Webhook] Transactions removed";
                //$body = "Removed transactions:\n" . implode("\n", $txns);
                $body = NULL;
            }
            break;
        case 'INITIAL_UPDATE':
        case 'HISTORICAL_UPDATE':
        case 'DEFAULT_UPDATE':
            if ((@$json->new_transactions ?? 0) > 0) {
                $start_date = $json->webhook_code == 'DEFAULT_UPDATE' ? '-1 week' : '-2 years';

                ob_start();
                Plaid::importTransactions($json->item_id, $start_date);
                $result = ob_get_clean();

                if (empty($result)) {
                    // Sandbox webhook
                    $body = NULL;
                } else {
                    //$subject = "[Plaid Webhook] Transactions added";
                    //$body = "Import result:\n\n" . $result;
                    $body = NULL;
                }
            }
            break;
        }
    }

    if (!empty($body)) {
        mail(Config::get('PLAID_USER')->email, $subject, $body);
    }
} else {
    die('meh');
}
