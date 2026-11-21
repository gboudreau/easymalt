<?php
namespace EasyMalt;

if (!isset($view)) { http_response_code(404); return; }

$year = $view['year'];
$scroll_pos = $view['scroll_pos'];
?>
<html>
<head>
    <title>EasyMalt - Taxes Report</title>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=yes, width=device-width">
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
</head>
<body>

&lt; <a href="/">Back</a>

<h3>Expenses & Income for <?php phe($year) ?></h3>

<div>
    Listing all transactions that matches any of the following rules:
    <ul>
        <?php foreach ($view['taxes_config'] as $tc) : ?>
            <li>
                <?php
                if (!empty($tc['category'])) {
                    echo "Category ";
                    if (is_array($tc['category'])) {
                        phe(" IN " . implode(', ', $tc['category']));
                    } else {
                        phe(" LIKE " . $tc['category']);
                    }
                    echo "<br/>";
                }
                if (!empty($tc['tag'])) {
                    echo "Tags ";
                    if (is_array($tc['tag'])) {
                        phe(" IN " . implode(', ', $tc['tag']));
                    } else {
                        phe(" LIKE " . $tc['tag']);
                    }
                }
                ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<table>
    <?php foreach ($view['transactions'] as $txn) : ?>
        <?php if ($txn->category != @$last_cat) : $last_cat = $txn->category; ?>
            <tr>
                <td colspan="6">&nbsp;</td>
            </tr>
            <tr>
                <td colspan="6"><?php phe($txn->category) ?></td>
            </tr>
        <?php endif; ?>
        <tr>
            <td><a href="/txn/?id=<?php echo $txn->id ?>" onclick="return editTxn(this)"><?php phe($txn->id) ?></a></td>
            <td><?php phe(substr($txn->date, 0, 10)) ?></td>
            <td><?php phe($txn->name) ?></td>
            <td><?php phe($txn->memo) ?></td>
            <td><?php phe($txn->tags) ?></td>
            <td><?php phe($txn->amount) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<script
        src="https://code.jquery.com/jquery-3.1.1.min.js"
        integrity="sha256-hVVnYaiADRTO2PzUGmuLJr8BLUSjGIZsDYGmIJLv2b8="
        crossorigin="anonymous"></script>
<script>
    var scrollPosition = 0;
    $(function() {
        scrollPosition = Math.round($(document).scrollTop());
        $(window).scroll(function() {
            scrollPosition = Math.round($(document).scrollTop());
            history.replaceState(null, '', '/taxes/?year=<?php echo $year ?>&scrollPos='+scrollPosition);
        });
        <?php if (!empty($scroll_pos)) : ?>
        $('html, body').animate({
            scrollTop: <?php echo $scroll_pos ?>
        }, 300);
        <?php endif; ?>
    });
    function editTxn(el) {
        $(el).attr('href', $(el).attr('href') + '&scrollPos=' + scrollPosition);
        return true;
    }
</script>
</body>
</html>
