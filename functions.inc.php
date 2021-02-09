<?php

use EasyMalt\Config;
use EasyMalt\DB;

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

function last($array) {
    return array_pop($array);
}

function first($array) {
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
        throw new Exception("Error executing sendGET($url); cURL error: " . curl_errno($ch) . " " . curl_error($ch), curl_errno($ch));
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
    $default_currency = Config::get('DEFAULT_CURRENCY');
    $default_currency_name = array_keys($default_currency)[0];
    return ( $currency == $default_currency_name );
}

function echo_amount($amount, $currency = NULL) {
    $default_currency = Config::get('DEFAULT_CURRENCY');
    $default_currency_name = array_keys($default_currency)[0];
    $default_currency_symbol = array_pop($default_currency);
    if (empty($currency)) {
        $currency = $default_currency_name;
    }
    echo_if($currency == $default_currency_name, $default_currency_symbol, $currency);
    echo '&nbsp;' . number_format($amount, 2);
}

function postProcessNewTransactions() {
    echo "[PP] Post-processing transactions ... \n";
    $q = "SELECT * FROM post_processing ORDER BY prio DESC";
    $pp_settings = DB::getAll($q);

    $q = "SELECT * FROM transactions WHERE post_processed = 'no' ORDER BY id";
    $transactions = DB::getAll($q);
    foreach ($transactions as $t) {
        foreach ($pp_settings as $pp) {
            if (preg_match('@' . $pp->regex . '@i', $t->name . ' ' . $t->memo, $re) && (empty($pp->amount_equals) || $pp->amount_equals == $t->amount)) {
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
                if ($pp->memo !== NULL) {
                    $updates[] = 'memo = :memo';
                    $memo = $pp->memo;
                    for ($i=1; $i<count($re); $i++) {
                        $memo = str_replace('{'.$i.'}', $re[$i], $memo);
                    }
                    $params['memo'] = $memo;
                    $log_details[] = "Memo: $memo";
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
