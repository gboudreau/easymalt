<?php

namespace EasyMalt;

if (!isset($view)) {
    http_response_code(404);
    return;
}

$even = false;
?>
    <h3><?php phe($view['heading']) ?></h3>
    <table class="txn_details" cellspacing="0">
        <tr>
            <th>Date</th>
            <th>Description</th>
            <th>Category</th>
            <th>Amount</th>
            <th>Account</th>
            <th>&nbsp;</th>
        </tr>
        <?php foreach ($view['rows'] as $entry) : ?>
            <?php
            $row = $entry['transaction'];
            $memo = he($row->memo);
            if (preg_match_all('/Ref: #(\d+)/', $memo, $re)) {
                for ($i=0; $i<count($re[0]); $i++) {
                    $memo = str_replace($re[0][$i], 'Ref: <a href="/txn/?id='. $re[1][$i].'" onclick="return editTxn(this)">#'. $re[1][$i].'</a>', $memo);
                }
            }
            $classes = [];
            $classes[] = $row->amount >= 0 ? 'Income' : 'Expenses';
            $classes[] = $even =! $even ? 'even' : 'odd';
            if ($row->hidden == 'yes') {
                $classes[] = 'hidden';
            }
            if ($entry['category'] == 'Netlift') {
                $classes[] = 'netlift';
            }

            if ($entry['fuel'] !== null) {
                ['distance' => $distance, 'consumption' => $consumption, 'price_per_km' => $price_per_km] = $entry['fuel'];
                $memo .= "\n<em>Distance: $distance km</em> &nbsp;|&nbsp; <em>$consumption L/100km</em> &nbsp;|&nbsp; <em>$price_per_km ¢/km</em>";
            }
            ?>
            <tr class="<?php echo implode(' ', $classes) ?>">
                <td class="date first"><?php echo substr($row->date, 0, 10) ?></td>
                <td class="name">
                    <?php phe($row->name) ?><br/>
                    <small><?php echo nl2br($memo) ?></small>
                </td>
                <td class="category">
                    <a href="<?php echo getCurrentUrlReplacing($view['query'], 'category', $entry['category']) ?>"><?php phe($entry['category']) ?></a>
                    <br/><small><?php echo nl2br(he($row->tags)) ?></small>
                </td>
                <td class="amount">
                    <?php echo_amount($row->amount, $row->currency) ?>
                </td>
                <td class="account">
                    <a href="<?php echo getCurrentUrlReplacing($view['query'], 'account', (string) $row->account) ?>"><?php phe($row->account) ?></a>
                </td>
                <td>[<a href="/txn/?id=<?php echo $row->id ?>" onclick="return editTxn(this)">edit</a>]</td>
            </tr>
        <?php endforeach; ?>
        <tr class="total <?php echo $view['heading'] ?>">
            <th>&nbsp;</th>
            <th>&nbsp;</th>
            <th>Total</th>
            <th style="text-align: right">
                <?php
                foreach ($view['totals'] as $currency => $total) {
                    if ($total == 0 && !is_default_currency($currency)) {
                        continue;
                    }
                    if (!is_default_currency($currency)) {
                        echo "+&nbsp;";
                    }
                    echo_amount($total, $currency);
                    echo '<br/>';
                }
                ?>
            </th>
            <th>&nbsp;</th>
            <th>&nbsp;</th>
        </tr>
    </table>
