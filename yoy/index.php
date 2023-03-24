<?php
namespace EasyMalt;
chdir(__DIR__.'/..');
require 'init.inc.php';

$category_selected = $_GET['cat'];

// Categories list; add values for 'root categories', to be able to get report for those, even if no transactions
$q = "SELECT DISTINCT name FROM categories ORDER BY name";
$categories = DB::getAllValues($q);
array_unshift($categories, NO_CATEGORY_NAME);
$categories_plus = [];
foreach ($categories as $cat) {
    $num = substr_count($cat, ':');
    if ($num > 0) {
        $root = substr($cat, 0, strrpos($cat, ':'));
        if (!array_search($root, $categories_plus)) {
            $categories_plus[] = $root;
        }
    }
    $categories_plus[] = $cat;
}
$categories = $categories_plus;

$q = "SELECT YEAR(t.date) AS x, SUM(t.amount) AS y FROM transactions t WHERE t.category LIKE CONCAT(:cat, '%') AND date > '2011-01-01' GROUP BY YEAR(t.date) ORDER BY t.date";
$datas = DB::getAll($q, $category_selected);

$data = [];
$labels = [];
foreach ($datas as $value) {
    $data[] = $value->y;
    $labels[] = $value->x;
}
$dataset = [
    'label' => 'Expense',
    'tooltipTemplate' => '<%= value.toLocaleString() %> <%= datasetLabel %>',
    'data' => $data,
    'backgroundColor' => 'rgba(0, 123, 255, 0.3)',
];
?>
<html>
<head>
    <title>EasyMalt - Year-over-Year Per Category Graph</title>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=yes, width=device-width">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Lato', sans-serif;
        }
        .chart {
            height: 400px;
        }
    </style>
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
</head>
<body class="<?php echo ($is_dark ? 'dark' : '') ?>">

&lt; <a href="/">Back</a>

<h3>Year-over-Year Expense for
    <select name="category" onchange="window.location.href='./?cat=' + encodeURIComponent(this.value)">
        <option value="">Choose a category</option>
        <?php foreach ($categories as $cat) : ?>
            <option value="<?php phe($cat) ?>" <?php echo_if($cat == $category_selected, 'selected="selected"') ?>><?php phe($cat) ?></option>
        <?php endforeach; ?>
    </select>
</h3>

<div>
    <canvas id="chart_bars" class="chart" width="100%" height="400"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.bundle.min.js" integrity="sha256-eA+ych7t31OjiXs3fYU0iWjn9HvXMiCLmunP2Gpghok=" crossorigin="anonymous"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.css" integrity="sha256-aa0xaJgmK/X74WM224KMQeNQC2xYKwlAt08oZqjeF0E=" crossorigin="anonymous">

<script>
    function drawBarChart() {
        let ctx = document.getElementById("chart_bars").getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels) ?>,
                datasets: [<?php echo json_encode($dataset) ?>]
            },
            options: {
                layout: {
                    padding: {
                        left: 17,
                        right: 26
                    }
                },
                tooltips: {
                    callbacks: {
                        label: function(tooltipItem, data) {
                            return tooltipItem.yLabel + ' $';
                        }
                    }
                },
                legend: {
                    display: false
                },
                maintainAspectRatio: false,
                scales: {
                    xAxes: [{
                        stacked: false,
                        ticks: {
                        },
                    }],
                    yAxes: [{
                        stacked: false,
                        ticks: {
                            beginAtZero: true,
                            callback: function(label, index, labels) {
                                return label;
                            }
                        },
                        scaleLabel: {
                            display: true,
                            labelString: 'Spent ($)'
                        }
                    }]
                }
            }
        });
    }
    drawBarChart();
</script>
</body>
</html>
