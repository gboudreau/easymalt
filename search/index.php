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

<form action="" method="get">
    Query: <input id="search-field" name="q" type="text" value="<?php phe($_GET['q']) ?>" placeholder="Search..."/>
</form>

<?php
$where_conditions = [];
$params = [];

$query = explode(' AND ', $_GET['q']);
foreach ($query as $q) {
    if (preg_match('/(category|cat|desc|amount|tag|date|account)\s?([=><])\s?(.+)\s?/', $q, $matches)) {
        $query_type = strtolower($matches[1]);
        $operator = $matches[2];
        $value = trim($matches[3]);
        if ($query_type == 'category' || $query_type == 'cat') {
            $where_conditions[] = 't.category LIKE :cat';
            $params['cat'] = '%' . $value .'%';
        }
        if ($query_type == 'desc') {
            $where_conditions[] = '(t.memo LIKE :desc OR t.name LIKE :desc)';
            $params['desc'] = '%' . $value .'%';
        }
        if ($query_type == 'amount') {
            $where_conditions[] = 'ABS(ROUND(t.amount*100)) ' . $operator . ' ABS(ROUND(:amount*100))';
            $params['amount'] = (float) $value;
        }
        if ($query_type == 'tag') {
            $where_conditions[] = 't.tags LIKE :tag';
            $params['tag'] = '%' . $value .'%';
        }
        if ($query_type == 'date') {
            $where_conditions[] = 'DATE(t.date) ' . $operator . ' :date';
            $params['date'] = date('Y-m-d', strtotime($value));
        }
        if ($query_type == 'account') {
            $where_conditions[] = 'a.name LIKE :account';
            $params['account'] = '%' . $value .'%';
        }
    } else {
        if (is_numeric($q)) {
            $where_conditions[] = 'ABS(ROUND(t.amount*100)) = ABS(ROUND(:amount*100))';
            $params['amount'] = (float) $q;
        } else {
            $where_conditions[] = '(t.memo LIKE :desc OR t.name LIKE :desc)';
            $params['desc'] = '%' . $q .'%';
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
