<?php
namespace EasyMalt;
chdir(__DIR__.'/..');
require 'init.inc.php';

$q = "SELECT id, `name`, currency,  balance, SUBSTR(balance_date, 1, 16) AS last_updated, plaid_id FROM accounts WHERE account_number NOT LIKE '-%' ORDER BY `name`";
$data = DB::getAll($q);

$totals = [];
?>
<html>
<head>
    <title>EasyMalt - Accounts</title>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=yes, width=device-width">
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
</head>
<body>
<div style="text-align: left">
    &lt; <a href="<?php phe($_SESSION['previous_page']) ?>">Back</a>
</div>

<div style="margin-top: 1em; margin-bottom: 1em">
    [<a href="/link/">Add bank account using Plaid</a>]
</div>

<table class="accounts" cellspacing="0">
    <tr>
        <th>Account</th>
        <th>Balance</th>
        <th>Last Updated</th>
        <th></th>
    </tr>
    <?php foreach ($data as $row) : ?>
        <?php $totals[$row->currency] += $row->balance; ?>
        <tr class="<?php echo (($even=!@$even) ? 'even' : 'odd') ?>">
            <td class="name">
                <a href="/search?q=<?php echo urlencode("account=$row->name") ?>"><?php phe($row->name) ?></a>
            </td>
            <td style="text-align: right">
                <?php echo_amount($row->balance, $row->currency) ?>
            </td>
            <td>
                <?php echo (empty($row->last_updated) ? 'N/A' : he($row->last_updated)) ?>
            </td>
            <td>
                <?php if (!empty($row->plaid_id)) : ?>
                    <a href="/link/?account=<?php echo urlencode($row->plaid_id) ?>">Update Plaid link</a> |
                    <a href="/link/?remove_account=<?php echo urlencode($row->plaid_id) ?>">Remove Plaid link</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php foreach ($totals as $currency => $total) : ?>
        <tr class="<?php echo (($even=!@$even) ? 'even' : 'odd') ?>">
            <td class="name">
                Total (<?php echo $currency ?>)
            </td>
            <td style="text-align: right">
                <?php echo_amount($total, $currency) ?>
            </td>
            <td>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
