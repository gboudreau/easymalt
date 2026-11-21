<?php

namespace EasyMalt;

function prepareTransactionTable(array $transactions, string $heading, $query): array {
    $rows = [];
    $totals = [];
    foreach ($transactions as $transaction) {
        if ($transaction->hidden != 'yes') {
            $currency = $transaction->currency;
            $totals[$currency] = ($totals[$currency] ?? 0) + $transaction->amount;
        }

        $category = $transaction->category ?? NO_CATEGORY_NAME;
        $rows[] = [
            'transaction' => $transaction,
            'category' => $category,
            'fuel' => getFuelMetrics($transaction),
        ];
    }

    return ['rows' => $rows, 'heading' => $heading, 'totals' => $totals, 'query' => $query];
}

function getFuelMetrics(object $transaction): ?array {
    if ($transaction->category != 'Auto: Fuel' || $transaction->amount >= 0) {
        return null;
    }

    // Use the first eligible car tag to find the previous fill-up.
    $car_tag = '';
    foreach (explode(',', $transaction->tags ?? '') as $tag) {
        if ($tag == 'Vacation' || string_contains($tag, ' ')) {
            continue;
        }
        $car_tag = $tag;
        break;
    }

    $q = "SELECT memo FROM transactions WHERE tags LIKE :like_tags AND category = 'Auto: Fuel' AND date <= :date AND amount < 0 AND id < :id ORDER BY date DESC, id DESC LIMIT 1";
    $last_memo = DB::getFirstValue($q, [
        'id' => $transaction->id,
        'date' => $transaction->date,
        'like_tags' => '%' . $car_tag . '%',
    ]);
    $memo = he($transaction->memo);
    $liters = preg_match('/ x ([\d.]+) ?L/m', $memo, $matches) ? (float) $matches[1] : 0;
    $km = preg_match('/^(\d+) ?km/m', $memo, $matches) ? (int) $matches[1] : 0;
    $km_last = preg_match('/^(\d+) ?km/m', $last_memo ?? '', $matches) ? (int) $matches[1] : 0;
    $distance = $km - $km_last;
    if ($liters <= 0 || $km_last <= 0 || $distance <= 0) {
        return null;
    }

    return [
        'distance' => $distance,
        'consumption' => round($liters / $distance * 100, 1),
        'price_per_km' => round(abs($transaction->amount) / $distance * 100, 1),
    ];
}

// Preserve the active filters while replacing a category or account drilldown.
function getCurrentUrlReplacing(string $query, string $replace_what, string $with_that): string {
    $conditions = explode(' AND ', $query);
    foreach ($conditions as $key => $condition) {
        if (stripos($condition, $replace_what) === 0) {
            unset($conditions[$key]);
        }
    }
    $conditions[] = $replace_what . ' = ' . $with_that;
    return './?q=' . urlencode(implode(' AND ', $conditions));
}
