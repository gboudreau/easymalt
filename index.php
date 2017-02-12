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

$default_currency = Config::get('DEFAULT_CURRENCY');
$default_currency_name = array_keys($default_currency)[0];
$default_currency_symbol = array_pop($default_currency);

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
        &lt; <a href="/<?php echo getPageUrlForMonth($month, OPT_NO_CAT) ?>">Back</a> to all categories
    </div>
<?php endif; ?>

<?php
function printTransactionsTable($data, $what) {
    global $month, $default_currency_name, $default_currency_symbol;
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
                    <?php else: ?>
                        <a href="?cat=<?php echo urlencode($row->category) ?>&month=<?php echo urlencode($month) ?>"><?php phe($row->category) ?></a>
                    <?php endif; ?>
                    <br/><small><?php echo nl2br(he($row->tags)) ?></small>
                </td>
                <td style="text-align: right">
                    <?php echo (($row->currency == $default_currency_name) ? $default_currency_symbol : $row->currency) .'&nbsp;' . number_format($row->amount, 2) ?>
                </td>
                <td style="max-width: 200px">
                    <?php phe($row->account) ?>
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
                    if ($tot == 0 && $currency != $default_currency_name) {
                        continue;
                    }
                    echo ($currency == $default_currency_name ? $default_currency_symbol : "+&nbsp;$currency") . '&nbsp;';
                    echo number_format($tot, 2);
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
?>
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
        $q = "SELECT t.id, `date`, t.`name`, memo, category, tags, amount, a.currency, a.name AS account
                FROM v_transactions_reports t JOIN accounts a ON (t.account_id = a.id)
               WHERE $_where
               ORDER BY `date` DESC, id DESC";
        $data = DB::getAll($q, $params);

        if (empty($data)) {
            continue;
        }

        printTransactionsTable($data, $what);
    } else {
        $q = "SELECT `month`, IFNULL(group_by_category, :no_name_cat) AS category, SUM(amount) AS amount, a.currency
                FROM v_transactions_reports t JOIN accounts a ON (t.account_id = a.id)
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
                        <?php if ($row->currency == $default_currency_name) echo $default_currency_symbol; else echo $row->currency; ?>
                        <?php echo number_format($row->amount, 2) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr class="total <?php echo $what ?>">
                <th>Total</th>
                <th style="text-align: right">
                    <?php
                    foreach ($total as $currency => $tot) {
                        if ($tot == 0 && $currency != $default_currency_name) {
                            continue;
                        }
                        echo ($currency == $default_currency_name ? $default_currency_symbol : "+&nbsp;$currency") . '&nbsp;';
                        echo number_format($tot, 2);
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
    $q = "SELECT t.id, `date`, t.`name`, memo, category, tags, amount, a.currency, a.name AS account, t.hidden
                    FROM v_transactions_reports t JOIN accounts a ON (t.account_id = a.id)
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
        google.charts.setOnLoadCallback(drawPieExpenses);
        google.charts.setOnLoadCallback(drawPieIncome);
    <?php endif; ?>
</script>

</body>
</html>
<?php
$_SESSION['previous_page'] = preg_replace('/&scrollPos=\d+/', '', $_SERVER['REQUEST_URI']);
?>
