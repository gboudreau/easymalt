<?php
namespace EasyMalt;

if (!isset($view)) {
    http_response_code(404);
    return;
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
<body class="<?php echo ($view['is_dark'] ? 'dark' : '') ?>">

&lt; <a href="/">Back</a>

<h3>Income vs Expenses for
    <select name="year" onchange="window.location.href='./?year=' + encodeURIComponent(this.value)">
        <?php foreach ($view['years'] as $y) : ?>
            <option value="<?php phe($y) ?>" <?php echo_if($y == $view['year_selected'], 'selected="selected"') ?>><?php phe($y) ?></option>
        <?php endforeach; ?>
    </select>
</h3>

<div style="background-color: #fff; padding: 1em">
    <canvas id="chart_sankey_1" class="chart" width="100%"></canvas>
</div>

<?php renderView('partials/transactions-table', $view['transaction_table']) ?>

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
            'Travel':        'green',
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
                    data: <?php echo json_encode($view['flows']) ?>,
                    colorFrom: (c) => getColor(c.dataset.data[c.dataIndex].from),
                    colorTo: (c) => getColor(c.dataset.data[c.dataIndex].to),
                    colorMode: 'to', // or 'from' or 'to'
                    // optional labels
                    labels: <?php echo json_encode($view['labels']) ?>,
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
