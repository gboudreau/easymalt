<?php
namespace EasyMalt;
chdir(__DIR__.'/..');
require 'init.inc.php';
?>
<html>
<head>
    <title>EasyMalt - Search: <?php phe($_GET['q']) ?></title>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=yes, width=device-width">
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
</head>
<body>

&lt; <a href="/">Back</a>

<h3>Search results</h3>

<div>
    Query: <input id="search-field" type="text" value="<?php phe($_GET['q']) ?>" placeholder="Search..." />
</div>

<?php
$where_conditions = [];
$params = [];

$query = explode(' AND ', $_GET['q']);
foreach ($query as $q) {
    if (preg_match('/(category|desc|amount|tag|date|account)\s?=\s?(.+)\s?/', $q, $matches)) {
        $value = trim($matches[2]);
        if ($matches[1] == 'category') {
            $where_conditions[] = 't.category LIKE :cat';
            $params['cat'] = '%' . $value .'%';
        }
        if ($matches[1] == 'desc') {
            $where_conditions[] = '(t.memo LIKE :desc OR t.name LIKE :desc)';
            $params['desc'] = '%' . $value .'%';
        }
        if ($matches[1] == 'amount') {
            $where_conditions[] = 'ABS(ROUND(t.amount*100)) = ABS(ROUND(:amount*100))';
            $params['amount'] = (float) $value;
        }
        if ($matches[1] == 'tag') {
            $where_conditions[] = 't.tags LIKE :tag';
            $params['tag'] = '%' . $value .'%';
        }
        if ($matches[1] == 'date') {
            $where_conditions[] = 'DATE(t.date) = :date';
            $params['date'] = date('Y-m-d', strtotime($value));
        }
        if ($matches[1] == 'account') {
            $where_conditions[] = 'a.name LIKE :account';
            $params['account'] = '%' . $value .'%';
        }
    }
}

if (!empty($_GET['cat'])) {
    if (!isset($params['cat'])) {
        $where_conditions[] = 't.category LIKE :cat';
    }
    $params['cat'] = $_GET['cat'];
}

if (!empty($where_conditions)) {
    $q = "SELECT t.id, `date`, t.`name`, memo, category, tags, amount, a.currency, a.name AS account, t.hidden
                    FROM v_transactions_reports t JOIN accounts a ON (t.account_id = a.id)
                   WHERE " . implode(' AND ', $where_conditions) . "
                   ORDER BY `date` DESC, id DESC";
    $data = DB::getAll($q, $params);
}

if (empty($data)) {
    if (string_contains($_GET['q'], ' and ')) {
        $newq = str_replace(' and ', ' AND ', $_GET['q']);
        echo "<div>Did you mean: <a href='/search/?q=" . urlencode($newq) . "'>" . he($newq) . '</a></div>';
    }
    if (empty($where_conditions)) {
        die("Error: invalid search query.");
    }
}

printTransactionsTable($data, '');
?>

<script
        src="https://code.jquery.com/jquery-3.1.1.min.js"
        integrity="sha256-hVVnYaiADRTO2PzUGmuLJr8BLUSjGIZsDYGmIJLv2b8="
        crossorigin="anonymous"></script>
<script>
    var scrollPosition = 0;
    $(function() {
        scrollPosition = $(document).scrollTop();
        $(window).scroll(function() {
            scrollPosition = $(document).scrollTop();
            history.replaceState(null, '', '/search/?q=<?php echo $_GET['q'] ?>&scrollPos='+scrollPosition);
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
    $(function() {
        $('input[type=text]').on('keyup', function(e) {
            if (e.keyCode == 13) {
                search();
            }
        });
    });
    function search() {
        var query = $('#search-field').val();
        window.location.href = '/search/?q=' + encodeURIComponent(query);
    }
</script>
</body>
</html>
<?php
$_SESSION['previous_page'] = preg_replace('/&scrollPos=\d+/', '', $_SERVER['REQUEST_URI']);
?>
