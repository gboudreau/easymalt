<?php

function array_remove($array, $value_to_remove) {
    return array_diff($array, array($value_to_remove));
}

function he($text) {
    return htmlentities($text, ENT_COMPAT|ENT_QUOTES, 'UTF-8');
}

function phe($text) {
    echo he($text);
}

function js($text) {
    return json_encode($text);
}

function pjs($text) {
    echo js($text);
}

function strip_accents($text) {
	return strtr($text,'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ','aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
}

function _array_shift($array) {
    return array_shift($array);
}

function sendPOST($url, $data, $headers=array()) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    $result = curl_exec($ch);

    if (!$result) {
        error_log("Error executing sendPOST$url); cURL error: " . curl_errno($ch));
    }

    curl_close($ch);

    return $result;
}

function sendGET($url, $headers=array()) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    $result = curl_exec($ch);

    if (!$result) {
        error_log("Error executing sendGET($url); cURL error: " . curl_errno($ch));
    }

    curl_close($ch);

    return $result;
}

function string_contains($haystack, $needle) {
    return strpos($haystack, $needle) !== FALSE;
}

function array_contains($haystack, $needle) {
    if (empty($haystack)) {
        return FALSE;
    }
    return array_search($needle, $haystack) !== FALSE;
}

function echo_if($condition, $if_true, $if_false = NULL) {
    if ($condition) {
        echo $if_true;
    } elseif ($if_false !== NULL) {
        echo $if_false;
    }
}

function is_default_currency($currency) {
    $default_currency = \EasyMalt\Config::get('DEFAULT_CURRENCY');
    $default_currency_name = array_keys($default_currency)[0];
    return ( $currency == $default_currency_name );
}

function echo_amount($amount, $currency = NULL) {
    $default_currency = \EasyMalt\Config::get('DEFAULT_CURRENCY');
    $default_currency_name = array_keys($default_currency)[0];
    $default_currency_symbol = array_pop($default_currency);
    if (empty($currency)) {
        $currency = $default_currency_name;
    }
    echo_if($currency == $default_currency_name, $default_currency_symbol, $currency);
    echo '&nbsp;' . number_format($amount, 2);
}

function printTransactionsTable($data, $what) {
    global $month;
    $total = [];
    $even = FALSE;
    ?>
    <h3><?php phe($what) ?></h3>
    <table class="" cellspacing="0">
        <tr>
            <th>Date</th>
            <th>Description</th>
            <th>Category</th>
            <th>Amount</th>
            <th>Account</th>
            <th>&nbsp;</th>
        </tr>
        <?php foreach ($data as $row) : ?>
            <?php
            if (@$row->hidden != 'yes') {
                @$total[$row->currency] += $row->amount;
            }
            ?>
            <tr class="<?php echo ($row->amount >= 0 ? 'Income' : 'Expenses') . " " . (($even=!$even) ? 'even' : 'odd') . " " . (@$row->hidden == 'yes' ? 'hidden' : '') ?>">
                <td><?php echo substr($row->date, 0, 10) ?></td>
                <td>
                    <?php phe($row->name) ?><br/>
                    <small><?php echo nl2br(he($row->memo)) ?></small>
                </td>
                <td>
                    <?php if (!empty($_GET['cat']) && $row->category == $_GET['cat']) : ?>
                        <?php phe($row->category) ?>
                    <?php elseif (!empty($_GET['q'])): ?>
                        <a href="?q=<?php echo urlencode($_GET['q']) ?>&amp;cat=<?php echo urlencode($row->category) ?>"><?php phe($row->category) ?></a>
                    <?php else: ?>
                        <a href="?cat=<?php echo urlencode($row->category) ?>&amp;month=<?php echo urlencode($month) ?>"><?php phe($row->category) ?></a>
                    <?php endif; ?>
                    <br/><small><?php echo nl2br(he($row->tags)) ?></small>
                </td>
                <td style="text-align: right">
                    <?php echo_amount($row->amount, $row->currency) ?>
                </td>
                <td style="max-width: 200px">
                    <a href="/search?q=<?php echo urlencode("account=$row->account") ?>"><?php phe($row->account) ?></a>
                </td>
                <td>[<a href="/txn/?id=<?php echo $row->id ?>" onclick="return editTxn(this)">edit</a>]</td>
            </tr>
        <?php endforeach; ?>
        <tr class="total <?php echo $what ?>">
            <th>&nbsp;</th>
            <th>&nbsp;</th>
            <th>Total</th>
            <th style="text-align: right">
                <?php
                foreach ($total as $currency => $tot) {
                    if ($tot == 0 && !is_default_currency($currency)) {
                        continue;
                    }
                    if (!is_default_currency($currency)) {
                        echo "+&nbsp;";
                    };
                    echo_amount($tot, $currency);
                    echo '<br/>';
                }
                ?>
            </th>
            <th>&nbsp;</th>
            <th>&nbsp;</th>
        </tr>
    </table>
    <?php
}
