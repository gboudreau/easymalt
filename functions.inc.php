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

    //error_log("sendPOST($url); data = $data");

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
    // Copy Questrade transactions to gbnp_investments DB
    $q = "SELECT t.*, a2.id AS investment_account_id FROM transactions t JOIN accounts a ON (a.id = t.account_id) JOIN gbnp_investments.accounts a2 ON (a2.number = a.account_number) WHERE t.post_processed = 'no' AND a.routing_number = 'questrade' ORDER BY id";
    $qt_transactions = DB::getAll($q);
    foreach ($qt_transactions as $t) {
        if ($t->type == 'DEP' || $t->type == 'FXT') {
            $type = 'deposit';
        } elseif ($t->type == 'DIV') {
            $type = 'dividends';
        } elseif ($t->type == 'BUY') {
            $type = 'buy';
        } elseif ($t->type == 'SELL') {
            $type = 'sell';
        } else {
            $type = $t->amount < 0 ? 'buy' : 'sell';
        }
        if (preg_match('/(\d+) x (.+) @ ([\d.]+) ([CADUSD]+)/', trim($t->memo), $re)) {
            $qty = (int) $re[1];
            $symbol = $re[2];
            $price_per_share = (float) $re[3];
            //$currency = $re[4];
        } elseif (preg_match('/^([CADUSD]+)$/', trim($t->memo), $re)) {
            $qty = 0;
            $price_per_share = 0;
            $symbol = $re[1];
        } elseif (string_contains(trim($t->name), "ISHARES GLOBAL REIT ETF")) {
            $type = 'dividends';
            $qty = 0;
            $price_per_share = 0;
            $symbol = 'REET';
        } elseif (string_contains(trim($t->name), "ISHARES CORE MSCI EAFE IMI INDEX ETF")) {
            $type = 'dividends';
            $qty = 0;
            $price_per_share = 0;
            $symbol = 'XEF.TO';
        } elseif (string_contains(trim($t->name), "SPDR SERIES TRUST SPDR PORTFOLIO S&P 1500 COMPOSITE STOCK MARKET ETF CASH DIV")) {
            $type = 'dividends';
            $qty = 0;
            $price_per_share = 0;
            $symbol = 'SPTM';
        } elseif (string_contains(trim($t->name), "SPDR INDEX SHARES FUNDS SPDR PORTFOLIO EMERGING MARKETS ETF CASH DIV")) {
            $type = 'dividends';
            $qty = 0;
            $price_per_share = 0;
            $symbol = 'SPEM';
        } elseif (string_contains(trim($t->name), "BMO S&P/TSX CAPPED COMPOSITE INDEX ETF")) {
            $type = 'dividends';
            $qty = 0;
            $price_per_share = 0;
            $symbol = 'ZCN.TO';
        } else {
            echo "WARNING: unparseable Questwealth transaction\nIn: " . json_encode($t, JSON_PRETTY_PRINT);
            continue;
        }
        $q = "SELECT MAX(id) FROM gbnp_investments.symbols WHERE symbol_yahoo = :symbol";
        $symbol_id = DB::getFirstValue($q, $symbol);
        if (empty($symbol_id)) {
            echo "WARNING: missing symbol in gbnp_investments.symbols: '$symbol'\nIn: " . json_encode($t, JSON_PRETTY_PRINT);
            continue;
        }

        $params = [
            'account_id'  => $t->investment_account_id,
            'txn_date'    => substr($t->date, 0, 10),
            'settle_date' => substr($t->date, 0, 10),
            'type'        => $type,
            'symbol_id'   => $symbol_id,
            'quantity'    => $qty,
            'price_per_share' => $price_per_share,
            'amount' => $t->amount,
        ];

        $q = "INSERT IGNORE INTO gbnp_investments.transactions SET account_id = :account_id, txn_date = :txn_date, settle_date = :settle_date, type = :type, symbol_id = :symbol_id, 
                                                     quantity = :quantity, price_per_share = :price_per_share, gross_amount = :amount, net_amount = :amount";
        $id = DB::insert($q, $params);
        if ($id) {
            echo "Importing Questrade transaction: " . implode("\t", (array) $t) . "\n";
        }
    }

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
