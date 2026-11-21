<?php
namespace EasyMalt;

if (!isset($view)) {
    http_response_code(404);
    return;
}

$is_dark = $view['is_dark'];
?>
<html lang="en">
<head>
    <title>Easy mint Alternative</title>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=no, width=device-width">
    <link rel="stylesheet" type="text/css" href="css/styles.css">
</head>
<body class="<?php echo ($is_dark ? 'dark' : '') ?>">

<div id="dark_switcher">
    <a href="./?dark=<?php echo ($is_dark ? 0 : 1)?>">Go <?php echo ($is_dark ? 'light' : 'dark')?></a>
</div>
<div style="text-align: center">
    Go to:
    <a href="accounts/">Accounts</a>
    | <a href="txn/?id=new">New Transaction</a>
    <?php if ($view['extra_pages']) : ?>
        <?php foreach ($view['extra_pages'] as $url => $page_name) : ?>
            | <a href="<?php echo $url ?>"><?php phe($page_name) ?></a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
if ($view['show_cookie_prompt']) {
    ?>
    <h3>Missing ECK1C value in cookies. Find it in 1Password.</h3>
    <form method="post" action="">
        <input type="text" name="ECK1C" /><br/><br/>
        <input type="submit" value="Save" />
    </form>
    </body>
    </html>
    <?php
    return;
}

$show_search_params = $view['show_search_params'];
$query_values = $view['query_values'];
$ops_avail = $view['ops_avail'];
?>

<?php if (!$show_search_params) : ?>
    <!-- Quick dates selector; much faster to select common dates with this than using the Date search parameters -->
    <div style="margin-top: 1em; text-align: center">
        Report dates:
        <select onchange="window.location.href='./?' + this.value">
            <?php foreach ($view['quick_dates'] as $value => $name) : ?>
                <option value="<?php phe($value) ?>" <?php echo_if($value == $view['from_to_query'], 'selected="selected"') ?>><?php phe($name) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
<?php endif; ?>

<?php if (!$show_search_params) : ?>
    <div style="text-align: center; margin-top: 1em">
        <button onclick="$('#search_form').show(); $(this).hide();">Search...</button>
    </div>
<?php endif; ?>

<form id="search_form" action="./" method="post" style="text-align: center; display: <?php echo_if($show_search_params, 'block', 'none') ?>">
    <h3>Search Parameters</h3>
    <input name="do_search" value="1" type="hidden" />
    <table class="search_query" style="display: inline">
        <tr class="table-row-ids" style="display: <?php echo_if(!empty($query_values['ids']), 'table-row', 'none') ?>">
            <td>IDs</td>
            <td><input name="ids" type="text" value="<?php phe($query_values['ids'] ?? '') ?>" /></td>
        </tr>
        <?php foreach ($query_values['date'] as $op => $value) : ?>
            <tr class="table-row-date" style="display: <?php echo_if(!empty($value), 'table-row', 'none') ?>">
                <td>Date</td>
                <td>
                    <select name="date_op[]">
                        <?php foreach ($ops_avail as $opa) : ?>
                            <option value="<?php phe($opa) ?>" <?php echo_if($op == $opa, 'selected="selected"') ?>><?php phe($opa) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input name="date[]" type="date" value="<?php phe($view['date_input_values'][$op]) ?>" />
                    [<a href="#clear" onclick="$(this).parent().find('input').val(''); return false">clear</a>]
                </td>
            </tr>
        <?php endforeach; ?>
        <tr class="table-row-category" style="display: <?php echo_if(!empty($query_values['category']), 'table-row', 'none') ?>">
            <td>Category</td>
            <td>
                <select name="category">
                    <option value="">Choose a category</option>
                    <?php foreach ($view['categories'] as $cat) : ?>
                        <option value="<?php phe($cat) ?>" <?php echo_if(($query_values['category'] ?? null) == $cat, 'selected="selected"') ?>><?php phe($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr class="table-row-desc" style="display: <?php echo_if(!empty($query_values['desc']), 'table-row', 'none') ?>">
            <td>Description</td>
            <td><input name="desc" type="text" value="<?php phe($query_values['desc'] ?? '') ?>" /></td>
        </tr>
        <?php foreach ($query_values['amount'] as $op => $value) : ?>
            <tr class="table-row-amount" style="display: <?php echo_if(!empty($value), 'table-row', 'none') ?>">
                <td>Amount</td>
                <td>
                    <select name="amount_op[]">
                        <?php foreach ($ops_avail as $opa) : ?>
                            <option value="<?php phe($opa) ?>" <?php echo_if($op == $opa, 'selected="selected"') ?>><?php phe($opa) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input name="amount[]" type="number" step="0.01" value="<?php phe($value) ?>" />
                </td>
            </tr>
        <?php endforeach; ?>
        <tr class="table-row-tag" style="display: <?php echo_if(!empty($query_values['tag']), 'table-row', 'none') ?>">
            <td>Tag</td>
            <td>
                <select name="tag">
                    <option value="">Choose a tag</option>
                    <?php foreach ($view['tags'] as $tag) : ?>
                        <option value="<?php phe($tag) ?>" <?php echo_if(($query_values['tag'] ?? null) == $tag, 'selected="selected"') ?>><?php phe($tag) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr class="table-row-account" style="display: <?php echo_if(!empty($query_values['account']), 'table-row', 'none') ?>">
            <td>Account</td>
            <td>
                <select name="account">
                    <option value="">Choose an account</option>
                    <?php foreach ($view['accounts'] as $account) : ?>
                        <option value="<?php phe($account) ?>" <?php echo_if(($query_values['account'] ?? null) == $account, 'selected="selected"') ?>><?php phe($account) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr class="table-row-show_all_cats" style="display: <?php echo_if(!empty($query_values['show_all_cats']), 'table-row', 'none') ?>">
            <td>Show all categories?</td>
            <td>
                <input name="show_all_cats" type="checkbox" value="1" <?php echo_if($query_values['show_all_cats'] ?? false, 'checked') ?> />
            </td>
        </tr>
        <tr>
            <td>Add parameter</td>
            <td>
                |
                <?php
                foreach ($view['addable_params'] as $param) {
                    echo '<a href="#" onclick=\'showSearchParameter(event, ' . json_encode($param) . ', this)\'>' . ucfirst($param) . '</a> | ';
                }
                ?>
                <script>
                    function showSearchParameter(event, el_class, link) {
                        event = event || window.event;
                        event.preventDefault();
                        $('.table-row-' + el_class).show();
                        $(link).hide();
                        if (el_class === 'show_all_cats') {
                            $('[name=show_all_cats]').prop('checked', true);
                            $('[name=show_all_cats]').closest('form')[0].submit();
                        }
                    }
                </script>
            </td>
        </tr>
        <tr>
            <td></td>
            <td>
                <input type="submit" value="Search" />
                <small style="margin-left: 0.5em"><a href="./">Clear search parameters</a></small>
            </td>
        </tr>
    </table>
</form>

<?php foreach ($view['sections'] as $what => $section) : ?>
    <h3 class="with_pie_chart"><?php phe($what) ?></h3>
    <table class="with_pie_chart" cellspacing="0">
        <tr>
            <th>Category</th>
            <th>Spending</th>
        </tr>
        <?php foreach ($section['rows'] as $row) : ?>
            <tr class="<?php echo $row['class'] ?>">
                <td><a href="<?php echo $row['url'] ?>"><?php phe($row['category']) ?></a></td>
                <td style="text-align: right">
                    <?php echo_amount($row['amount'], $row['currency']) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <tr class="total <?php echo $what ?>">
            <th>Total</th>
            <th style="text-align: right">
                <?php
                foreach ($section['totals'] as $total) {
                    if (!$total['is_default_currency']) {
                        echo "+&nbsp;";
                    }
                    echo_amount($total['amount'], $total['currency']);
                    echo '<br/>';
                }
                ?>
            </th>
        </tr>
    </table>

    <div class="pie_chart" id="chart_div_<?php echo $what ?>"></div>
    <script>
        function drawPie<?php echo $what ?>() {
            var data = google.visualization.arrayToDataTable(<?php echo json_encode($section['pie_data']); ?>);

            var is_dark = $('body').hasClass('dark');
            var options = {
                is3D: true,
                sliceVisibilityThreshold: 3/100.0,
                backgroundColor: is_dark ? '#333' : 'white',
                legend: {textStyle: {color: is_dark ? '#eee' : 'black'}}
            };

            var chart = new google.visualization.PieChart(document.getElementById('chart_div_<?php echo $what ?>'));

            function selectHandler() {
                var selectedItem = chart.getSelection()[0];
                if (selectedItem) {
                    var category = data.getValue(selectedItem.row, 0);
                    var current_url = <?php echo json_encode($view['pie_url']) ?>;
                    window.location.href = current_url.replace('__category__', encodeURIComponent(category));
                }
            }
            google.visualization.events.addListener(chart, 'select', selectHandler);

            chart.draw(data, options);
        }
    </script>
    <div style="clear: both"></div>
<?php endforeach; ?>

<?php
if ($view['transaction_table'] === null) {
    echo "No transactions.";
} else {
    renderView('partials/transactions-table', $view['transaction_table']);
}
?>

<script src="https://code.jquery.com/jquery-3.1.1.min.js"
        integrity="sha256-hVVnYaiADRTO2PzUGmuLJr8BLUSjGIZsDYGmIJLv2b8="
        crossorigin="anonymous"></script>
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script>
    var scrollPosition = 0;
    $(function() {
        scrollPosition = Math.round($(document).scrollTop());
        $(window).scroll(function() {
            scrollPosition = Math.round($(document).scrollTop());
            history.replaceState(null, '', <?php echo json_encode($view['history_url']) ?>+scrollPosition);
        });
        <?php if ($view['scroll_position'] !== null) : ?>
        $('html, body').animate({
            scrollTop: <?php echo json_encode($view['scroll_position']) ?>
        }, 300);
        <?php endif; ?>
    });

    function editTxn(el) {
        $(el).attr('href', $(el).attr('href') + '&scrollPos=' + scrollPosition);
        return true;
    }

    $(function() {
        $('table.txn_details td.amount').each(function () {
            if (!window.matchMedia('screen and (max-device-width: 480px)').matches) {
                return;
            }
            var date_top_pos = $(this).parent().find('td.first').position().top;
            var amount_top_pos = $(this).position().top;
            var diff = amount_top_pos - date_top_pos;
            $(this).css('margin-top', '-' + diff + 'px');
        });
    });

    google.charts.load('current', {packages: ['corechart']});
    if (typeof drawPieExpenses === 'function') {
        google.charts.setOnLoadCallback(drawPieExpenses);
    }
    if (typeof drawPieIncome === 'function') {
        google.charts.setOnLoadCallback(drawPieIncome);
    }
</script>
</body>
</html>
