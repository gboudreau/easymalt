<?php
namespace EasyMalt;

chdir(__DIR__);
require_once 'init.inc.php';

// Daily or weekly:

// 1. Import all transactions, in case we missed any webhook
Plaid::importTransactions(NULL, '-2 weeks');

// 2. Find new (unprocessed) transactions
$q = "SELECT id FROM transactions WHERE post_processed = 'no' ORDER BY id";
$ids = DB::getAllValues($q);

// 3. Run post-processing on those transactions
postProcessNewTransactions();

// 4. Send email notification to verify and classify unmatched transactions
$q = "SELECT COUNT(*) FROM transactions WHERE post_processed = 'no'";
$num_unmatched_txn = DB::getFirstValue($q);
$num_new_txn = count($ids);
$num_ok_txn = $num_new_txn - $num_unmatched_txn;

$url = Config::get('BASE_URL') . '/';
$q = "ids=" . implode(',', $ids);
$url = "$url?q=" . urlencode($q);

$subject = "[easymalt] New transactions to verify ($num_ok_txn) and classify ($num_unmatched_txn)";

$body = "$num_new_txn new transactions have been added.
Of those, $num_ok_txn were automatically categorized, and $num_unmatched_txn were not.

Review those new transactions here: $url";

echo "\n\n$body\n";
if ($num_new_txn > 0) {
    mail(Config::get('PLAID_USER')->email, $subject, $body);
}
