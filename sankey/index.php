<?php
namespace EasyMalt;
chdir(__DIR__.'/..');
require 'init.inc.php';

$year_selected = (int) $_GET['year'];

$q = "SELECT t.category, ROUND(t.amount) AS amount, t.id, t.date, t.name, t.memo, t.tags, t.hidden, a.currency, a.name AS account
        FROM transactions t
        LEFT JOIN accounts a ON (a.id = t.account_id)
       WHERE t.date BETWEEN '$year_selected-01-01' AND '" . ($year_selected+1) . "-01-01'
         AND (t.category NOT LIKE 'Transfer%' OR t.category = 'Transfer: Investments')
       ORDER BY t.category, ABS(t.amount) DESC";
$transactions = DB::getAll($q);

$incomes = [];
$expenses = [];
$total_incomes = 0;
$total_expenses = 0;
foreach ($transactions as $txn) {
    if ((string_contains($txn->category, 'Income') || ($txn->amount > 0 && ($txn->category == 'Giving: Gift' || $txn->category == 'Music: Gig' || $txn->category == 'Taxes: Federal' || $txn->category == 'Taxes: Provincial' || $txn->category == 'Music: Rhapsodie')))) {
        if (preg_match('/Income: (.*)$/', $txn->category, $re)) {
            $category = "Income: $re[1]";
        } else {
            if ($txn->category == 'Income') {
                $txn->category = 'Other';
            }
            if ($txn->category == 'Giving: Gift') {
                $txn->category = 'Gift';
            }
            $category = "Income: $txn->category";
        }
        @$incomes[$category]['Budget'] += $txn->amount;
        $total_incomes += $txn->amount;
    } else {
        $from = 'Budget';
        $cats = [];
        foreach (explode(': ', $txn->category) as $cat) {
            $cats[] = $cat;
            $to = implode(': ', $cats);
            @$expenses[$from][$to] += $txn->amount;
            $from = $to;
        }
        $total_expenses += -$txn->amount;
    }
}

$diff_investing = 0;
if ($total_expenses > $total_incomes) {
    $diff_investing = $total_expenses - $total_incomes;
}

$datas = [];
$labels = [];

$labels['Budget'] = "Budget - " . number_format(round($total_incomes)) . '$';

foreach ($incomes as $from => $data_to) {
    foreach ($data_to as $to => $amount) {
        $datas[] = ['from' => $from, 'to' => $to, 'flow' => $amount];
        $labels[$from] = str_replace('Income: ', '', $from) . " - " . number_format(round($amount)) . '$';
    }
}

foreach ($expenses as $from => $data_to) {
    foreach ($data_to as $to => $amount) {
        if ($to == 'Transfer') {
            continue;
        }
        if ($from == 'Transfer') {
            if (-$amount > $diff_investing) {
                $amount += $diff_investing;
            }
            $datas[] = ['from' => 'Budget', 'to' => 'Investing', 'flow' => -$amount];
            $labels["Investing"] = "Investing - " . number_format(round(-$amount)) . '$';
            continue;
        }
        if ($from == 'Kids: School') {
            continue;
        }
        if (abs($amount) < 300) {
            continue;
        }
        $datas[] = ['from' => $from, 'to' => $to, 'flow' => -$amount];
        $labels[$to] = "$to - " . number_format(round(-$amount)) . '$';
    }
}
?>
<html>
<head>
    <title>EasyMalt - Sankey Yearly Graph</title>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=yes, width=device-width">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Lato', sans-serif;
        }
        .chart {
            height: 1000px;
        }
    </style>
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
</head>
<body class="<?php echo ($is_dark ? 'dark' : '') ?>">

&lt; <a href="/">Back</a>

<h3>Income vs Expenses for
    <select name="year" onchange="window.location.href='./?year=' + encodeURIComponent(this.value)">
        <?php for ($y = 2011; $y <= date('Y'); $y++) : ?>
            <option value="<?php phe($y) ?>" <?php echo_if($y == $year_selected, 'selected="selected"') ?>><?php phe($y) ?></option>
        <?php endfor; ?>
    </select>
</h3>

<div style="background-color: #fff; padding: 1em">
    <canvas id="chart_sankey_1" class="chart" width="100%"></canvas>
</div>

<?php printTransactionsTable($transactions, 'Transactions Details') ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js" integrity="sha512-ElRFoEQdI5Ht6kZvyzXhYG9NqjtkmlkfYk0wr6wHxU9JEHakS7UJZNeml5ALk+8IKlU6jDgMabC3vkumRokgJA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-chart-sankey@0.12.0/dist/chartjs-chart-sankey.min.js" integrity="sha256-Vp8loK4QI+oJDJo0ILHon0XzGptFPOjUQ5PUhjrxDWs=" crossorigin="anonymous"></script>
<script>
    function drawSankey() {
        const colors = {
            'Food & Dining': 'yellow',
            'Income':        'green',
            'Budget':        'green',
            'Kids':          'blue',
            'Home':          'red',
            'Giving':        'pink',
            'Investing':     'purple',
            'Auto':          'turquoise',
            'Music':         'orange',
        };

        const getColor = function (key) {
            if (key in colors) {
                return colors[key];
            }
            key = key.split(': ')[0];
            if (key in colors) {
                return colors[key];
            }
            return 'grey';
        };

        let ctx = document.getElementById("chart_sankey_1").getContext('2d');
        new Chart(ctx, {
            type: 'sankey',
            data: {
                datasets: [{
                    label: 'My sankey',
                    data: <?php echo json_encode($datas) ?>,
                    colorFrom: (c) => getColor(c.dataset.data[c.dataIndex].from),
                    colorTo: (c) => getColor(c.dataset.data[c.dataIndex].to),
                    colorMode: 'to', // or 'from' or 'to'/* optional labels */
                    labels: <?php echo json_encode($labels) ?>,
                    // priority: {
                    //     'Investing': 0,
                    //     'Kids': 1,
                    //     'Music': 2,
                    //     'Home': 3,
                    //     'Food & Dining': 4,
                    //     'Auto': 5,
                    // },
                    column: {
                        'Investing': 2
                    },
                    size: 'max', // or 'min' if flow overlap is preferred
                }],
            },
            options: {
                responsive: true,
                plugins: {
                    tooltip: {
                        enabled: false,
                    }
                }
            }
        });
    }
    drawSankey();
</script>
</body>
</html>
