<?php
namespace EasyMalt;

if (!isset($view)) { http_response_code(404); return; }

$dataset = [
    'label' => 'Expense',
    'tooltipTemplate' => '<%= value.toLocaleString() %> <%= datasetLabel %>',
    'data' => $view['series'],
    'backgroundColor' => 'rgba(0, 123, 255, 0.3)',
];
?>
<!doctype html>
<html lang="en" data-bs-theme="<?php echo $view['is_dark'] ? 'dark' : 'light' ?>">
<head>
    <title>EasyMalt - Year-over-Year Per Category Graph</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../css/lib/bootstrap.min.css">
    <link rel="stylesheet" href="../css/ui.css">
</head>
<body class="<?php echo ($view['is_dark'] ? 'dark' : '') ?>">

<main class="container-fluid py-3">
<?php renderPageHeader('Year-over-Year Expense', $view['is_dark'], ['cat']) ?>
<div class="em-dashboard-frame">
<div class="mb-3" style="max-width: 24rem">
    <label for="category" class="form-label">Category</label>
    <select id="category" class="form-select" name="category" onchange="window.location.href='./?cat=' + encodeURIComponent(this.value)">
        <option value="">Choose a category</option>
        <?php foreach ($view['categories'] as $cat) : ?>
            <option value="<?php phe($cat) ?>" <?php echo_if($cat == $view['category_selected'], 'selected="selected"') ?>><?php phe($cat) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div style="height: 400px">
    <canvas id="chart_bars" class="chart" width="100%" height="400"></canvas>
</div>
</div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.bundle.min.js" integrity="sha256-eA+ych7t31OjiXs3fYU0iWjn9HvXMiCLmunP2Gpghok=" crossorigin="anonymous"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.css" integrity="sha256-aa0xaJgmK/X74WM224KMQeNQC2xYKwlAt08oZqjeF0E=" crossorigin="anonymous">

<script>
    function drawBarChart() {
        const dark = document.documentElement.dataset.bsTheme === 'dark';
        const theme = getComputedStyle(document.documentElement);
        Chart.defaults.global.defaultFontColor = dark ? theme.getPropertyValue('--em-ink').trim() : '#394c45';
        Chart.defaults.scale.gridLines.color = dark ? theme.getPropertyValue('--em-line').trim() : '#d4dfd9';
        let ctx = document.getElementById("chart_bars").getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($view['labels']) ?>,
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
