<?php
namespace EasyMalt;
chdir(__DIR__);
require 'init.inc.php';

$month = date('Y-m');
if (!empty($_GET['month'])) {
    $month = $_GET['month'];
}
$previous_month = date('Y-m', strtotime("$month-01 previous month"));
$next_month = date('Y-m', strtotime("$month-01 next month"));

define('NO_CATEGORY_NAME', 'Uncategorized');

define('OPT_NO_CAT', 1);
function getPageUrlForMonth($month, $options = 0) {
    $page_params = $_GET;
    $page_params['month'] = $month;
    if ($options & OPT_NO_CAT == OPT_NO_CAT) {
        unset($page_params['cat']);
    }
    unset($page_params['scrollPos']);
    return "?" . http_build_query($page_params);
}
?>
<html>
<head>
    <title>Easy mint Alternative</title>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=yes, width=device-width">
    <link rel="stylesheet" type="text/css" href="css/styles.css">
</head>
<body>

<div style="text-align: center">
    Go to:
    <a href="accounts/">Accounts</a>
    <?php if (Config::get('EXTRA_PAGES')) : ?>
        <?php foreach (Config::get('EXTRA_PAGES') as $url => $page_name) : ?>
            | <a href="<?php echo $url ?>"><?php phe($page_name) ?></a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div style="text-align: center">
    <br/>
    Selected month: <strong><?php echo $month ?></strong><br/>
    &lt; <a href="<?php echo getPageUrlForMonth($previous_month) ?>">Previous Month (<?php echo $previous_month ?>)</a>
    <?php if (strtotime("$next_month-01") < time()) : ?>
        | <a href="<?php echo getPageUrlForMonth($next_month) ?>">Next Month (<?php echo $next_month ?>)</a> &gt;
    <?php endif; ?>
</div>

<?php if (!empty($_GET['cat'])) : ?>
    <div style="text-align: center">
        <br/>
        Selected category: <strong><?php phe($_GET['cat']) ?></strong><br/>
        &lt; <a href="<?php echo getPageUrlForMonth($month, OPT_NO_CAT) ?>">Back</a> to all categories
    </div>
<?php endif; ?>

<?php if (empty($_GET['cat'])) : ?>
    <div class="search" style="text-align: center">
        <input id="search-field" type="text" value="" placeholder="Search..." />
        <div style="display:none" class="example">Example: <code>category=Home: Supplies AND desc=something cool AND amount=10.24 AND tag=Vacation</code></div>
    </div>
<?php endif; ?>

<?php
$sections = [
    'Expenses' => [
        'where'    => "`month` = :month AND (amount < 0 OR (amount = 0 AND type = 'DEBIT'))",
        'order_by' => "amount ASC"
    ],
    'Income' => [
        'where'    => "`month` = :month AND (amount > 0 OR (amount = 0 AND type = 'CREDIT'))",
        'order_by' => "amount DESC"
    ]
];
?>
<?php foreach ($sections as $what => $select) : ?>
    <?php
    $where = $select['where'];
    $order_by = $select['order_by'];

    $params = ['month' => $month];
    if (!empty($_GET['cat'])) {
        $_where = $where;
        if ($_GET['cat'] == NO_CATEGORY_NAME) {
            $_where .= " AND category IS NULL";
        } else {
            $_where .= " AND (group_by_category = :cat OR category = :cat)";
            $params['cat'] = $_GET['cat'];
        }
        $q = "SELECT t.id, `date`, t.`name`, memo, category, tags, amount, IFNULL(a.currency, 'CAD') AS currency, IFNULL(a.name, 'Unknown Account') AS account
                FROM v_transactions_reports t LEFT JOIN accounts a ON (t.account_id = a.id)
               WHERE $_where
               ORDER BY `date` DESC, id DESC";
        $data = DB::getAll($q, $params);

        if (empty($data)) {
            continue;
        }

        printTransactionsTable($data, $what);
    } else {
        $q = "SELECT `month`, IFNULL(group_by_category, :no_name_cat) AS category, SUM(amount) AS amount, IFNULL(a.currency, 'CAD') AS currency
                FROM v_transactions_reports t LEFT JOIN accounts a ON (t.account_id = a.id)
               WHERE `month` = :month
                 AND $where
                 AND t.hidden = 'no'
               GROUP BY `month`, group_by_category, currency
               ORDER BY $order_by";
        $params['no_name_cat'] = NO_CATEGORY_NAME;
        $data = DB::getAll($q, $params);

        if (empty($data)) {
            continue;
        }

        $total = [];
        ?>
        <h3><?php phe($what) ?></h3>

        <table class="with_pie_chart" cellspacing="0">
            <tr>
                <th>Category</th>
                <th>Spending</th>
            </tr>
            <?php foreach ($data as $row) : ?>
                <?php @$total[$row->currency] += $row->amount; ?>
                <tr class="<?php echo $what . " " . (($even=!@$even) ? 'even' : 'odd') . ($row->category == NO_CATEGORY_NAME ? ' uncategorized' : '') ?>">
                    <td><a href="?cat=<?php echo urlencode($row->category) ?>&month=<?php echo urlencode($month) ?>"><?php phe($row->category) ?></a></td>
                    <td style="text-align: right">
                        <?php echo_amount($row->amount, $row->currency) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr class="total <?php echo $what ?>">
                <th>Total</th>
                <th style="text-align: right">
                    <?php
                    foreach ($total as $currency => $tot) {
                        if ($tot == 0 && !is_default_currency($currency)) {
                            continue;
                        }
                        if (!is_default_currency($currency)) {
                            echo "+&nbsp;";
                        }
                        echo_amount($tot, $currency);
                        echo '<br/>';
                    }
                    ?>
                </th>
            </tr>
        </table>

        <div class="pie_chart" id="chart_div_<?php echo $what ?>"></div>
        <script>
            function drawPie<?php echo $what ?>() {
                <?php
                $js_data = [['Category', 'Spending']];
                foreach ($data as $row) {
                    $js_data[] = [$row->category, abs($row->amount)];
                }
                ?>
                var data = google.visualization.arrayToDataTable(<?php echo json_encode($js_data); ?>);

                var options = {
                    is3D: true,
                    sliceVisibilityThreshold: 3/100.0
                };

                var chart = new google.visualization.PieChart(document.getElementById('chart_div_<?php echo $what ?>'));

                function selectHandler() {
                    var selectedItem = chart.getSelection()[0];
                    if (selectedItem) {
                        var category = data.getValue(selectedItem.row, 0);
                        console.log(category);
                        window.location.href = '?cat=' + encodeURIComponent(category) + '&month=' + <?php pjs(urlencode($month)) ?>;
                    }
                }
                google.visualization.events.addListener(chart, 'select', selectHandler);

                chart.draw(data, options);
            }
        </script>
        <div style="clear: both"></div>
        <?php
    }
    ?>
<?php endforeach; ?>

<?php
if (empty($_GET['cat'])) {
    $q = "SELECT t.id, `date`, t.`name`, memo, category, tags, amount, t.hidden, IFNULL(a.currency, 'CAD') AS currency, IFNULL(a.name, 'Unknown Account') AS account
                    FROM v_transactions_reports t LEFT JOIN accounts a ON (t.account_id = a.id)
                   WHERE `month` = :month
                   ORDER BY `date` DESC, id DESC";
    $data = DB::getAll($q, $month);

    if (empty($data)) {
        echo "No transactions.";
    } else {
        printTransactionsTable($data, 'All Transactions');
    }
}
?>

<script
        src="https://code.jquery.com/jquery-3.1.1.min.js"
        integrity="sha256-hVVnYaiADRTO2PzUGmuLJr8BLUSjGIZsDYGmIJLv2b8="
        crossorigin="anonymous"></script>
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script>
    var scrollPosition = 0;
    $(function() {
        scrollPosition = $(document).scrollTop();
        $(window).scroll(function() {
            scrollPosition = $(document).scrollTop();
            history.replaceState(null, '', '/?<?php echo (!empty($_GET['cat'])) ? "cat=" . urlencode($_GET['cat']) . "&" : "" ?>month=<?php echo $month ?>&scrollPos='+scrollPosition);
        });
        <?php if (!empty($_GET['scrollPos'])) : ?>
        $('html, body').animate({
            scrollTop: <?php echo $_GET['scrollPos'] ?>
        }, 300);
        <?php endif; ?>
    });

    function editTxn(el) {
        var uri = $(el).attr('href') + '&scrollPos=' + scrollPosition;
        window.location.href = uri;
        return false;
    }

    <?php if (empty($_GET['cat'])) : ?>
        google.charts.load('current', {packages: ['corechart']});
        if (typeof drawPieExpenses === 'function') {
            google.charts.setOnLoadCallback(drawPieExpenses);
        }
        if (typeof drawPieIncome === 'function') {
            google.charts.setOnLoadCallback(drawPieIncome);
        }

        $(function() {
            $('#search-field').on('focus', function(e) {
                $('.search .example').show();
            });
            $('#search-field').on('keyup', function(e) {
                if (e.keyCode == 13) {
                    search();
                }
            });
        });
        function search() {
            var query = $('#search-field').val();
            window.location.href = '/search/?q=' + encodeURIComponent(query);
        }
    <?php endif; ?>
</script>

</body>
</html>
<?php
$_SESSION['previous_page'] = preg_replace('/&scrollPos=\d+/', '', $_SERVER['REQUEST_URI']);
?>
