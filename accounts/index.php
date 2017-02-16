<?php
namespace EasyMalt;
chdir(__DIR__.'/..');
require 'init.inc.php';

$q = "SELECT `name`, currency,  balance, SUBSTR(balance_date, 1, 16) AS last_updated FROM accounts WHERE balance <> 0 AND account_number NOT LIKE '-%' ORDER BY `name`";
$data = DB::getAll($q);
?>
<html>
<head>
    <title>EasyMalt - Accounts</title>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=yes, width=device-width">
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
</head>
<body>
<div style="text-align: center">
    &lt; <a href="<?php phe($_SESSION['previous_page']) ?>">Back</a>
</div>

<table class="" cellspacing="0">
    <tr>
        <th>Account</th>
        <th>Balance</th>
        <th>Last Updated</th>
    </tr>
    <?php foreach ($data as $row) : ?>
        <tr class="<?php echo (($even=!@$even) ? 'even' : 'odd') ?>">
            <td>
                <?php phe($row->name) ?>
            </td>
            <td style="text-align: right">
                <?php echo_amount($row->balance, $row->currency) ?>
            </td>
            <td>
                <?php echo (empty($row->last_updated) ? 'N/A' : he($row->last_updated)) ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
