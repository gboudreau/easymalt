<?php
namespace EasyMalt;
chdir(__DIR__.'/..');
require 'init.inc.php';

$year = date('Y', strtotime('last year'));
if (!empty($_GET['year'])) {
    $year = $_GET['year'];
}

$taxes_config = Config::get('TAXES_WHERE_CLAUSE');
$where = [];
$params = [];
$cat_idx = 1;
$tag_idx = 1;
foreach ($taxes_config as $tc) {
    $sub_wheres = [];
    if (!empty($tc['category'])) {
        if (is_array($tc['category'])) {
            $cats = [];
            foreach ($tc['category'] as $c) {
                $cats[] = "category LIKE :cat_$cat_idx";
                $params["cat_$cat_idx"] = $c;
                $cat_idx++;
            }
            $sub_wheres[] = "(".implode(' OR ', $cats).")";
        } else {
            $sub_wheres[] = "category LIKE :cat_$cat_idx";
            $params["cat_$cat_idx"] = $tc['category'];
            $cat_idx++;
        }
    }
    if (!empty($tc['tag'])) {
        if (is_array($tc['tag'])) {
            $cats = [];
            foreach ($tc['tag'] as $c) {
                $cats[] = "tags LIKE :tag_$tag_idx";
                $params["tag_$tag_idx"] = $c;
                $tag_idx++;
            }
            $sub_wheres[] = "(".implode(' OR ', $cats).")";
        } else {
            $sub_wheres[] = "tags LIKE :tag_$tag_idx";
            $params["tag_$tag_idx"] = $tc['tag'];
            $tag_idx++;
        }
    }
    $where[] = "(" . implode(" AND ", $sub_wheres) . ")";
}
$where = implode(" OR ", $where);

$params['year'] = $year;
$q = "SELECT * 
        FROM v_transactions_reports 
       WHERE DATE_FORMAT(`date`, '%Y') = :year
         AND ($where)
       ORDER BY category, `date`";
$transactions = DB::getAll($q, $params);
?>

&lt; <a href="/">Back</a>

<h3>Expenses & Income for <?php phe($year) ?></h3>

<div>
    Listing all transactions that matches any of the following rules:
    <ul>
        <?php foreach ($taxes_config as $tc) : ?>
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
    <?php foreach ($transactions as $txn) : ?>
        <?php if ($txn->category != @$last_cat) : $last_cat = $txn->category; ?>
            <tr>
                <td colspan="6">&nbsp;</td>
            </tr>
            <tr>
                <td colspan="6"><?php phe($txn->category) ?></td>
            </tr>
        <?php endif; ?>
        <tr>
            <td><a href="/txn/?id=<?php phe($txn->id) ?>"><?php phe($txn->id) ?></a></td>
            <td><?php phe(substr($txn->date, 0, 10)) ?></td>
            <td><?php phe($txn->name) ?></td>
            <td><?php phe($txn->memo) ?></td>
            <td><?php phe($txn->tags) ?></td>
            <td><?php phe($txn->amount) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<?php $_SESSION['previous_page'] = $_SERVER['REQUEST_URI'] ?>
