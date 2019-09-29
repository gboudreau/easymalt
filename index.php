<?php
namespace EasyMalt;
chdir(__DIR__);
require 'init.inc.php';

if (!empty($_POST['do_search'])) {
    $query = [];
    foreach ($_POST as $k => $v) {
        if (empty($v)) {
            continue;
        }
        if ($k == 'amount' || $k == 'date' || $k == 'do_search') {
            continue;
        }
        if ($k == 'amount_op') {
            foreach ($v as $i => $amount_op) {
                $amount = $_POST['amount'][$i];
                if (!empty($amount)) {
                    $query[] = "amount $amount_op $amount";
                }
            }
        } elseif ($k == 'date_op') {
            foreach ($v as $i => $date_op) {
                $date = $_POST['date'][$i];
                if (!empty($date)) {
                    $query[] = "date $date_op $date";
                }
            }
        } else {
            $query[] = "$k = $v";
        }
    }
    header('Location: ./?q=' . urlencode(implode(' AND ', $query)));
    exit();
}
?>
<html>
<head>
    <title>Easy mint Alternative</title>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=no, width=device-width">
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

<?php
$builder = new DBQueryBuilder();

$query = explode(' AND ', @$_GET['q']);
$query_values = [];
foreach ($query as $k => $q) {
    if (preg_match('/(category|desc|amount|tag|date|account|ids)\s?([=><]{1,2})\s?(.*)\s?/', $q, $matches)) {
        $query_type = strtolower($matches[1]);
        $operator = $matches[2];
        $value = trim($matches[3]);
        if (empty($value)) {
            continue;
        }
        if ($query_type == 'ids') {
            $query_values['ids'] = $value;
            $builder->where("t.id IN (:ids)", explode(',', $value));
        }
        if ($query_type == 'category' || $query_type == 'cat') {
            $query_values['category'] = $value;
            if ($value == NO_CATEGORY_NAME) {
                $builder->where("t.category IS NULL");
            } else {
                $builder->where("t.category LIKE :cat", '%' . $value .'%');
            }
        }
        if ($query_type == 'desc') {
            $query_values['desc'] = $value;
            $builder->where("(t.memo LIKE :desc OR t.name LIKE :desc)", '%' . $value .'%');
        }
        if ($query_type == 'amount') {
            $query_values['amount'][$operator] = (float) $value;
            $builder->where('ABS(ROUND(t.amount*100)) ' . $operator . ' ABS(ROUND(:amount' . $k . '*100))', (float) $value);
        }
        if ($query_type == 'tag') {
            $query_values['tag'] = $value;
            $builder->where("t.tags LIKE :tag", '%' . $value .'%');
        }
        if ($query_type == 'date') {
            $query_values['date'][$operator] = date('Y-m-d', strtotime($value));
            $builder->where('DATE(t.date) ' . $operator . ' :date' . $k, date('Y-m-d', strtotime($value)));
            if ($operator == '>=') {
                $_GET['from_date'] = date('Y-m-d', strtotime($value));
            }
            if ($operator == '<=') {
                $_GET['to_date'] = date('Y-m-d', strtotime($value));
            }
        }
        if ($query_type == 'account') {
            $query_values['account'] = $value;
            $builder->where("a.name LIKE :account", '%' . $value .'%');
        }
    } else {
        if (is_numeric($q)) {
            $query_values['amount']['='] = (float) $q;
            $builder->where('ABS(ROUND(t.amount*100)) = ABS(ROUND(:amount*100))', (float) $q);
        } elseif (!empty($q)) {
            $query_values['desc'] = $q;
            $builder->where("(t.memo LIKE :desc OR t.name LIKE :desc)", '%' . $q .'%');
        }
    }
}

// Show the Search Parameters form only if the user used it to execute a search
// Otherwise, show the Quick dates selector, and a "Search..." button
$show_search_params = !empty($query_values);
?>

<?php if (!$show_search_params) : ?>
    <!-- Quick dates selector; much faster to select common dates with this than using the Date search parameters -->
    <?php
    $from_date = !empty($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-d', strtotime('-15 days'));
    $to_date = !empty($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');
    $from_to_query = 'from_date=' . date('Y-m-d', strtotime($from_date)) . '&to_date=' . date('Y-m-d', strtotime($to_date));

    $dates = [];
    // Last X days
    foreach (array(15, 30, 60, 90, 365) as $days) {
        $from = strtotime("-$days days");
        $to = time();
        $dates['from_date=' . date('Y-m-d', $from) . '&to_date=' . date('Y-m-d', $to)] = "Last $days days";
    }

    // Month Year (for last 12 months)
    for ($t = time(); $t > strtotime('-12 months'); $t = strtotime('-1 month', $t)) {
        $dates['from_date=' . date('Y-m-01', $t) . '&to_date=' . date('Y-m-d', strtotime(date('Y-m-01', strtotime('next month', $t)))-1)] = date("M Y", $t);
    }
    ?>
    <div style="margin-top: 1em; text-align: center">
        Report dates:
        <select onchange="window.location.href='./?' + this.value">
            <?php foreach ($dates as $value => $name) : ?>
                <option value="<?php phe($value) ?>" <?php echo_if($value == $from_to_query, 'selected="selected"') ?>><?php phe($name) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
    // Use the $from_date and $to_date in the Query, unless there are already dates in there, or we are searching by IDs
    if (empty($query_values['date']) && empty($query_values['ids'])) {
        $builder->where('DATE(t.date) >= :from_date', $from_date);
        $builder->where('DATE(t.date) <= :to_date', $to_date);
        $query_values['date']['>='] = $from_date;
        $query_values['date']['<='] = $to_date;
    }
    if (empty($_GET['q'])) {
        $_GET['q'] = "date >= $from_date AND date <= $to_date";
    }
    ?>
<?php endif; ?>

<?php
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

// Tags list
$q = "SELECT DISTINCT name FROM tags ORDER BY name";
$tags = DB::getAllValues($q);

// Accounts list
$q = "SELECT DISTINCT name FROM accounts WHERE name <> '' ORDER BY name";
$accounts = DB::getAllValues($q);

// Allow two Date parameters (after and before)
if (!is_array(@$query_values['date']) || count($query_values['date']) < 2) {
    $query_values['date'][''] = '';
}
// Allow two Amount parameters (higher than and lower than)
if (!is_array(@$query_values['amount']) || count($query_values['amount']) < 2) {
    $query_values['amount'][''] = '';
}
$ops_avail = ['=', '<', '<=', '>', '>='];
?>

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
            <td><input name="ids" type="text" value="<?php phe(@$query_values['ids']) ?>" /></td>
        </tr>
        <?php foreach ($query_values['date'] as $op => $value) : ?>
            <tr class="table-row-date" style="display: <?php echo_if(!empty($value), 'table-row', 'none') ?>">
                <td>Date</td>
                <td><select name="date_op[]">
                        <?php foreach ($ops_avail as $opa) : ?>
                            <option value="<?php phe($opa) ?>" <?php echo_if($op == $opa, 'selected="selected"') ?>><?php phe($opa) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input name="date[]" type="date" value="<?php phe($value) ?>" /></td>
            </tr>
        <?php endforeach; ?>
        <tr class="table-row-category" style="display: <?php echo_if(!empty($query_values['category']), 'table-row', 'none') ?>">
            <td>Category</td>
            <td>
                <select name="category">
                    <option value="">Choose a category</option>
                    <?php foreach ($categories as $cat) : ?>
                        <option value="<?php phe($cat) ?>" <?php echo_if(@$query_values['category'] == $cat, 'selected="selected"') ?>><?php phe($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr class="table-row-desc" style="display: <?php echo_if(!empty($query_values['desc']), 'table-row', 'none') ?>">
            <td>Description</td>
            <td><input name="desc" type="text" value="<?php phe(@$query_values['desc']) ?>" /></td>
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
                    <?php foreach ($tags as $tag) : ?>
                        <option value="<?php phe($tag) ?>" <?php echo_if(@$query_values['tag'] == $tag, 'selected="selected"') ?>><?php phe($tag) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr class="table-row-account" style="display: <?php echo_if(!empty($query_values['account']), 'table-row', 'none') ?>">
            <td>Account</td>
            <td>
                <select name="account">
                    <option value="">Choose an account</option>
                    <?php foreach ($accounts as $account) : ?>
                        <option value="<?php phe($account) ?>" <?php echo_if(@$query_values['account'] == $account, 'selected="selected"') ?>><?php phe($account) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td>Add parameter</td>
            <td>
                |
                <?php
                $params_avail = ['date' => 2, 'category' => 1, 'desc' => 1, 'amount' => 2, 'tag' => 1, 'account' => 1];
                foreach ($params_avail as $param => $num_max) {
                    if ($num_max > 1) {
                        $show = count($query_values[$param]) < 2 || @$query_values[$param][''] === '';
                    } else {
                        $show = empty($query_values[$param]);
                    }
                    if ($show) {
                        echo '<a href="#" onclick=\'showSearchParameter(event, ' . json_encode($param) . ', this)\'>' . ucfirst($param) . '</a> | ';
                    }
                }
                ?>
                <script>
                    function showSearchParameter(event, el_class, link) {
                        event = event || window.event;
                        event.preventDefault();
                        $('.table-row-' + el_class).show();
                        $(link).hide();
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

<?php
$sections = [
    'Expenses' => [
        'where'    => "amount < 0 OR (amount = 0 AND type = 'DEBIT')",
        'order_by' => "amount ASC"
    ],
    'Income' => [
        'where'    => "amount > 0 OR (amount = 0 AND type = 'CREDIT')",
        'order_by' => "amount DESC"
    ]
];
?>
<?php foreach ($sections as $what => $select) : ?>
    <?php
    $builder_s = clone $builder;

    $builder_s->select("SUM(amount) AS amount, IFNULL(a.currency, 'CAD') AS currency")
        ->from("v_transactions_reports", 't')
        ->leftJoin("accounts a", 'a.id = t.account_id')
        ->where('t.hidden', 'no')
        ->where($select['where'])
        ->groupBy('currency')
        ->orderBy($select['order_by']);

    if (!empty($query_values['category'])) {
        $builder_s->select('category')->groupBy('category');
    } else {
        $builder_s->select('group_by_category AS category')->groupBy('group_by_category');
    }

    $data = $builder_s->getAll();

    if (empty($data)) {
        continue;
    }

    $total = [];
    ?>
    <h3 class="with_pie_chart"><?php phe($what) ?></h3>
    <table class="with_pie_chart" cellspacing="0">
        <tr>
            <th>Category</th>
            <th>Spending</th>
        </tr>
        <?php foreach ($data as $row) : ?>
            <?php @$total[$row->currency] += $row->amount; ?>
            <tr class="<?php echo $what . " " . (($even=!@$even) ? 'even' : 'odd') . ($row->category == NULL ? ' uncategorized' : '') ?>">
                <?php if ($row->category == NULL) { $row->category = NO_CATEGORY_NAME; } ?>
                <td><a href="<?php echo getCurrentUrlReplacing('category', $row->category) ?>"><?php phe($row->category) ?></a></td>
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
                    var current_url = <?php echo json_encode(getCurrentUrlReplacing('category', '__category__')) ?>;
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
$builder->select("t.id, t.date, t.name, t.memo, t.category, t.tags, t.amount, t.hidden, a.currency, a.name AS account")
    ->from("v_transactions_reports", 't')
    ->join('accounts a', "a.id = t.account_id")
    ->orderBy("t.date DESC, t.id DESC");

$data = $builder->getAll();

if (empty($data)) {
    echo "No transactions.";
} else {
    printTransactionsTable($data, 'Transactions Details');
}

// Calculate a generic 'current URL' to be used for category & account links, in tables and charts
function getCurrentUrlReplacing($replace_what, $with_that) {
    $query_no_cat = explode(' AND ', @$_GET['q']);
    foreach ($query_no_cat as $k => $q) {
        if (stripos($q, $replace_what) === 0) {
            unset($query_no_cat[$k]);
        }
    }
    $query_no_cat[] = $replace_what . " = " . $with_that;
    return './?q=' . urlencode(implode(' AND ', $query_no_cat));
}

function printTransactionsTable($data, $what) {
    $total = [];
    $even = FALSE;
    ?>
    <h3><?php phe($what) ?></h3>
    <table class="txn_details" cellspacing="0">
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
            if ($row->category === NULL) {
                $row->category = NO_CATEGORY_NAME;
            }
            ?>
            <tr class="<?php echo ($row->amount >= 0 ? 'Income' : 'Expenses') . " " . (($even=!$even) ? 'even' : 'odd') . " " . (@$row->hidden == 'yes' ? 'hidden' : '') ?>">
                <td class="date first"><?php echo substr($row->date, 0, 10) ?></td>
                <td class="name">
                    <?php phe($row->name) ?><br/>
                    <small><?php echo nl2br(he($row->memo)) ?></small>
                </td>
                <td class="category">
                    <a href="<?php echo getCurrentUrlReplacing('category', $row->category) ?>"><?php phe($row->category) ?></a>
                    <br/><small><?php echo nl2br(he($row->tags)) ?></small>
                </td>
                <td class="amount">
                    <?php echo_amount($row->amount, $row->currency) ?>
                </td>
                <td class="account">
                    <a href="<?php echo getCurrentUrlReplacing('account', $row->account) ?>"><?php phe($row->account) ?></a>
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
                    if ($tot == 0 && !is_default_currency($currency)) {
                        continue;
                    }
                    if (!is_default_currency($currency)) {
                        echo "+&nbsp;";
                    };
                    echo_amount($tot, $currency);
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

<script src="https://code.jquery.com/jquery-3.1.1.min.js"
        integrity="sha256-hVVnYaiADRTO2PzUGmuLJr8BLUSjGIZsDYGmIJLv2b8="
        crossorigin="anonymous"></script>
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script>
    var scrollPosition = 0;
    $(function() {
        scrollPosition = $(document).scrollTop();
        $(window).scroll(function() {
            scrollPosition = $(document).scrollTop();
            history.replaceState(null, '', <?php $get = $_GET; unset($get['scrollPos']); echo json_encode('/?' . http_build_query($get) . '&scrollPos=') ?>+scrollPosition);
        });
        <?php if (!empty($_GET['scrollPos'])) : ?>
        $('html, body').animate({
            scrollTop: <?php echo $_GET['scrollPos'] ?>
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
<?php
$_SESSION['previous_page'] = preg_replace('/&scrollPos=\d+/', '', $_SERVER['REQUEST_URI']);
