<?php
namespace EasyMalt;
chdir(__DIR__.'/..');
require 'init.inc.php';

$default_currency = Config::get('DEFAULT_CURRENCY');
$default_currency_name = array_keys($default_currency)[0];

if (empty($_GET['scrollPos'])) {
    $_GET['scrollPos'] = 0;
}

if (!empty($_REQUEST['id']) && $_REQUEST['id'] != 'new') {
    $q = "SELECT t.*, IFNULL(a.currency, :default_currency) AS currency FROM transactions t LEFT JOIN accounts a ON (t.account_id = a.id) WHERE t.id = :id";
    $txn = DB::getFirst($q, ['id' => (int) @$_REQUEST['id'], 'default_currency' => $default_currency_name]);

    if (empty($txn->tags)) {
        $txn->tags = [];
    } else {
        $txn->tags = explode(',', $txn->tags);
    }
    if ($txn->display_name === NULL) {
        $txn->display_name = '';
    }
    if ($txn->category === NULL) {
        $txn->category = '';
    }

    if (empty($txn)) {
        die("Transaction (ID " . @$_REQUEST['id'] . ") not found.");
    }
} else {
    $txn = (object) [
        'id' => 'new',
        'name' => '',
        'display_name' => '',
        'memo' => '',
        'date' => date('Y-m-d'),
        'amount' => NULL,
        'category' => '',
        'tags' => [],
    ];
}

if (isset($_POST['postprocess'])) {
    $q = "SELECT * FROM transactions WHERE id = :id";
    $t = DB::getFirst($q, $_POST['postprocess']);
    $post_process_result = postProcessTransaction($t);
} elseif (isset($_POST['id'])) {
    $updates = [];
    $params = [];

    $updates[] = "post_processed = 'yes'";

    if ($_POST['id'] == 'new') {
        $updates[] = 'account_id = :account_id';
        $params['account_id'] = $_POST['account_id'];
        $updates[] = 'amount = :amount';
        $params['amount'] = $_POST['amount'];
        $updates[] = 'type = :type';
        $params['type'] = $_POST['amount'] < 0 ? 'DEBIT' : 'CREDIT';

        $q = "SELECT MAX(CAST(SUBSTRING(unique_id, 8) AS INT)) FROM transactions WHERE account_id = :account_id AND unique_id LIKE 'manual-%'";
        $max_unique_id = (int) DB::getFirstValue($q, $_POST['account_id']);
        $updates[] = 'unique_id = :unique_id';
        $params['unique_id'] = "manual-" . ($max_unique_id + 1);
    }

    if (isset($_POST['display_name'])) {
        $updates[] = 'display_name = :display_name';
        $params['display_name'] = empty($_POST['display_name']) ? NULL : $_POST['display_name'];
    }
    if (isset($_POST['memo'])) {
        $updates[] = 'memo = :memo';
        $params['memo'] = empty($_POST['memo']) ? NULL : $_POST['memo'];
    }
    if (isset($_POST['category'])) {
        $updates[] = 'category = :category';
        $params['category'] = empty($_POST['category']) ? NULL : $_POST['category'];
    }
    if (!empty($_POST['amounts'])) {
        $updates[] = 'amount = :amount';
        $params['amount'] = (float) $_POST['amounts'][0];
    }
    if (!empty($_POST['date'])) {
        $updates[] = 'date = :date';
        $params['date'] = $_POST['date'];
    }
    $updates[] = 'tags = :tags';
    $params['tags'] = empty($_POST['tags']) ? NULL : implode(',', $_POST['tags']);

    if ($_POST['id'] == 'new') {
        $q = "INSERT INTO transactions SET " . implode(', ', $updates);
    } else {
        $q = "UPDATE transactions SET " . implode(', ', $updates) . " WHERE id = :id";
        $params['id'] = (int) $_POST['id'];
    }
    DB::execute($q, $params);

    if ($_POST['id'] == 'new') {
        $q = "UPDATE accounts SET balance = balance + :amount, balance_date = NOW() WHERE id = :account_id";
        DB::execute($q, ['account_id' => $_POST['account_id'], 'amount' => $_POST['amount']]);
    }

    if (!empty($_POST['amounts'])) {
        $q = "SELECT unique_id FROM transactions WHERE id = :id";
        $txn_unique_id = DB::getFirstValue($q, $_POST['id']);

        $next_available_unique_id = 2;
        while (TRUE) {
            $q = "SELECT 1 FROM transactions WHERE unique_id = :unique_id";
            $txn_found = DB::getFirstValue($q, $txn_unique_id . "-" . $next_available_unique_id);
            if (!$txn_found) {
                break;
            }
            $next_available_unique_id++;
        }

        for ($i = 1; $i<count($_POST['amounts']); $i++) {
            $amount = (float) $_POST['amounts'][$i];
            $q = "INSERT INTO transactions (account_id, unique_id, `date`, type, amount, display_name, name, memo, tags, category, post_processed) SELECT account_id, CONCAT(unique_id, '-', :unique_id), `date`, type, :amount, display_name, name, memo, tags, category, post_processed FROM transactions WHERE id = :id";
            $params = [
                'id' => (int) $_POST['id'],
                'unique_id' => $next_available_unique_id,
                'amount' => $amount,
            ];
            DB::insert($q, $params);
            $next_available_unique_id++;
        }
    }

    if (@$_POST['post_processing_rule'] == 'true') {
        $matches_name = $_POST['post_processing_rule_matching'];
        if (@$_POST['post_processing_rule_matching_format'] != 'regex') {
            $matches_name = preg_quote($matches_name);
        }
        $matches_amount = NULL;
        if (!empty($_POST['post_processing_rule_amount_equals'])) {
            $matches_amount = $_POST['post_processing_rule_amount_equals'];
        }
        $updates = [];
        $params = [];
        if (@$_POST['post_processing_rule_desc'] == 'true') {
            $updates[] = 'display_name = :display_name';
            $params['display_name'] = $_POST['display_name'];
        }
        if (@$_POST['post_processing_rule_memo'] == 'true') {
            $updates[] = 'memo = :memo';
            $params['memo'] = $_POST['memo'];
        }
        if (@$_POST['post_processing_rule_category'] == 'true') {
            $updates[] = 'category = :category';
            $params['category'] = $_POST['category'];
        }
        if (@$_POST['post_processing_rule_tags'] == 'true') {
            $updates[] = 'tags = :tags';
            $params['tags'] = empty($_POST['tags']) ? NULL : implode(',', $_POST['tags']);
        }
        $q = "INSERT INTO post_processing SET regex = :regex, amount_equals = :amount_equals, " . implode(", ", $updates) . " ON DUPLICATE KEY UPDATE " . implode(", ", $updates);
        DB::insert($q, array_merge($params, ['regex' => $matches_name, 'amount_equals' => $matches_amount]));

        $q = "SELECT id FROM transactions WHERE `name` REGEXP :regex AND (post_processed = 'no' OR id >= :id)";
        $pp = ['regex' => $matches_name, 'id' => $_POST['id']];
        if (!empty($matches_amount)) {
            $q .= " AND amount = :matches_amount";
            $pp['matches_amount'] = $matches_amount;
        }
        $txn_ids = DB::getAllValues($q, $pp);

        $updates[] = "post_processed = 'yes'";
        foreach ($txn_ids as $txn_id) {
            $q = "UPDATE transactions SET " . implode(', ', $updates) . " WHERE id = :id";
            DB::execute($q, array_merge($params, ['id' => $txn_id]));
        }
    }

    header('Location: ' . $_POST['goto']);
    exit(0);
}

$q = "SELECT `name` FROM tags ORDER BY `name`";
$tags = DB::getAllValues($q);

$q = "SELECT DISTINCT category FROM (SELECT category FROM transactions UNION ALL SELECT name FROM categories) a ORDER BY category";
$cats = DB::getAllValues($q);

$goto_link = $_SESSION['previous_page'] . (string_contains($_SESSION['previous_page'], '?') ? '&' : '?') .'scrollPos=' . $_GET['scrollPos'];
?>
<html>
<head>
    <title>EasyMalt - Transaction</title>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=yes, width=device-width">
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
</head>
<body class="<?php echo ($is_dark ? 'dark' : '') ?>">

<div style="text-align: left">
    &lt; <a href="<?php phe($goto_link) ?>">Back</a>
</div>

<form class="txn_form" method="post" action="">
    <input type="hidden" name="id" value="<?php phe($txn->id) ?>" />
    <input type="hidden" name="goto" value="<?php phe($goto_link) ?>" />
    <table>
        <?php if ($txn->id == 'new') : ?>
            <tr>
                <td>
                    Account:<br/>
                    <?php
                    $q = "SELECT id, name FROM accounts ORDER by name";
                    $accounts = DB::getAll($q);
                    ?>
                    <select name="account_id">
                        <?php foreach ($accounts as $account) : ?>
                            <option value="<?php phe($account->id) ?>"><?php phe($account->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        <?php else : ?>
            <tr>
                <td>
                    ID: <?php phe($txn->id) ?>
                </td>
            </tr>
        <?php endif; ?>
        <tr>
            <td>
                <input type="date" name="date" id="date" value="<?php phe(substr($txn->date, 0, 10)) ?>" />
            </td>
        </tr>
        <tr>
            <td>
                Amount:
                <?php if ($txn->amount !== NULL) : ?>
                    <?php echo_amount($txn->amount, $txn->currency) ?> [<a href="#split" onclick="splitTxn(this)">split</a>]
                    <input type="hidden" id="amount" value="<?php phe($txn->amount) ?>" />
                    <div class="split_amount">
                    </div>
                <?php else : ?>
                    <br/>
                    <input type="number" name="amount" value="" step="0.01" style="width: 100px;" />
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td>
                <?php phe($txn->name) ?>
            </td>
        </tr>
        <tr>
            <td>
                <input type="text" name="display_name" value="<?php phe($txn->display_name) ?>" placeholder="Short description..." />
            </td>
        </tr>
        <tr>
            <td>
                <textarea name="memo" cols="60" rows="3"><?php phe($txn->memo) ?></textarea>
            </td>
        </tr>
        <tr>
            <td>
                Tags:<br/>
                <?php foreach ($tags as $tag) : ?>
                    <input type="checkbox" id="tag_<?php echo he($tag) ?>" name="tags[]" value="<?php phe($tag) ?>" <?php echo_if(array_contains($txn->tags, $tag), 'checked="checked"') ?>/>
                    <label for="tag_<?php echo he($tag) ?>"><?php phe($tag) ?></label>
                <?php endforeach; ?>
            </td>
        </tr>
        <tr>
            <td>
                <label for="category">Category:</label><br/>
                <select id="category" name="category">
                <?php foreach ($cats as $cat) : ?>
                    <option value="<?php phe($cat) ?>" <?php echo_if($txn->category == $cat, 'selected="selected"') ?>><?php phe($cat) ?></option>
                <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td>
               <div class="apply_to_all">
                   <div>
                       <input type="checkbox" id="post_processing_rule" name="post_processing_rule" value="true" />
                       <label for="post_processing_rule">
                           Apply to all transactions whose name contains:
                       </label>
                       <input type="text" name="post_processing_rule_matching" value="<?php phe($txn->name) ?>" />
                       (<label for="post_processing_rule_matching_format">is RegExp</label>: <input type="checkbox" id="post_processing_rule_matching_format" name="post_processing_rule_matching_format" value="regex" />)
                       AND
                       amount is equal to
                       <input type="text" name="post_processing_rule_amount_equals" value="" style="width: 50px; text-align: center" />
                       (use a negative number for payments; leave empty to NOT match against amount)
                   </div>
                   <ul>
                       <li class="desc">
                           <input type="checkbox" id="post_processing_rule_desc" name="post_processing_rule_desc" value="true" />
                           <label for="post_processing_rule_desc">
                               Short description: <span class="value"></span>
                           </label>
                       </li>
                   </ul>
                   <ul>
                       <li class="memo">
                           <input type="checkbox" id="post_processing_rule_memo" name="post_processing_rule_memo" value="true" />
                           <label for="post_processing_rule_memo">
                               Memo: <span class="value"></span>
                           </label>
                       </li>
                   </ul>
                   <ul>
                       <li class="tags">
                           <input type="checkbox" id="post_processing_rule_tags" name="post_processing_rule_tags" value="true" />
                           <label for="post_processing_rule_tags">
                               Tags: <span class="value"></span>
                           </label>
                       </li>
                   </ul>
                   <ul>
                       <li class="category">
                           <input type="checkbox" id="post_processing_rule_category" name="post_processing_rule_category" value="true" />
                           <label for="post_processing_rule_category">
                               Category: <span class="value"></span>
                           </label>
                       </li>
                   </ul>
               </div>
            </td>
        </tr>
        <tr>
            <td><input type="submit" name="action" value="Save" /></td>
        </tr>
    </table>
</form>

<?php /*
<?= he(@$post_process_result) ?>
<form class="txn_form" method="post" action="">
    <input type="hidden" name="postprocess" value="<?php phe($txn->id) ?>" />
    <table>
        <tr>
            <td><input type="submit" name="action" value="Post-Process this txn" /></td>
        </tr>
    </table>
</form> */ ?>

<script
        src="https://code.jquery.com/jquery-3.1.1.min.js"
        integrity="sha256-hVVnYaiADRTO2PzUGmuLJr8BLUSjGIZsDYGmIJLv2b8="
        crossorigin="anonymous"></script>
<script>
    $(function() {
        $('input, select, textarea').on('change', function(event) {
            displayApplyToAll(event)
        });
        $('input[type=text]').on('input', function(event) {
            displayApplyToAll(event)
        });
    });
    var initial_desc = <?php pjs($txn->display_name) ?>;
    var initial_tags = <?php pjs($txn->tags) ?>;
    var initial_category = <?php pjs($txn->category) ?>;
    var initial_memo = <?php pjs($txn->memo) ?>;
    function displayApplyToAll(event) {
        var changed = false;

        console.log($(event.target));

        var initial = $(event.target).attr('id') == 'post_processing_rule';

        var enabled = $('#post_processing_rule').prop('checked');

        var desc = $('[name=display_name]').val();
        if (desc != initial_desc) {
            changed = true;
            $('.apply_to_all .desc .value').text(desc);
            $('.apply_to_all .desc').show();
            if (initial) {
                $('.apply_to_all .desc [type=checkbox]').prop('checked', enabled);
            }
        } else {
            $('.apply_to_all .desc').hide();
            $('.apply_to_all .desc [type=checkbox]').prop('checked', false);
        }

        var memo = $('[name=memo]').val();
        if (memo != initial_memo) {
            changed = true;
            $('.apply_to_all .memo .value').text(memo);
            $('.apply_to_all .memo').show();
            if (initial) {
                $('.apply_to_all .memo [type=checkbox]').prop('checked', enabled);
            }
        } else {
            $('.apply_to_all .memo').hide();
            $('.apply_to_all .memo [type=checkbox]').prop('checked', false);
        }

        var tags = $.map($('input[id^=tag_]:checked'), function(e,i) {
            return e.value;
        });
        if (JSON.stringify(tags) != JSON.stringify(initial_tags)) {
            changed = true;
            $('.apply_to_all .tags .value').text(tags.join(', '));
            $('.apply_to_all .tags').show();
            if (initial) {
                $('.apply_to_all .tags [type=checkbox]').prop('checked', enabled);
            }
        } else {
            $('.apply_to_all .tags').hide();
            $('.apply_to_all .tags [type=checkbox]').prop('checked', false);
        }

        var category = $('[name=category]').val();
        if (category != initial_category) {
            changed = true;
            $('.apply_to_all .category .value').text(category);
            $('.apply_to_all .category').show();
            if (initial) {
                $('.apply_to_all .category [type=checkbox]').prop('checked', enabled);
            }
        } else {
            $('.apply_to_all .category').hide();
            $('.apply_to_all .category [type=checkbox]').prop('checked', false);
        }

        if (changed) {
            $('.apply_to_all').show();
        } else {
            $('.apply_to_all').hide();
        }

        if (enabled) {
            $('.apply_to_all').addClass('selected');
        } else {
            $('.apply_to_all').removeClass('selected');
        }
    }

    var $split_amount_div;
    $(function() {
        $split_amount_div = $('.split_amount');
    });

    function splitTxn() {
        if ($split_amount_div.hasClass('visible')) {
            // Cancel
            $('a[href="#split"]').text('split');
            $split_amount_div.hide();
            $split_amount_div.removeClass('visible');
            $split_amount_div.html('');
        } else {
            $('a[href="#split"]').text('cancel');
            $split_amount_div.show();
            $split_amount_div.addClass('visible');
            $split_amount_div.append('<input data-idx="0" type="text" name="amounts[]" value="' + $('#amount').val() + '" /> ');
            $split_amount_div.append('<input data-idx="1" type="text" name="amounts[]" value="0.00" /> ');
            $split_amount_div.append('[<a href="#add" onclick="addSplitField()">+</a>]');
            $split_amount_div.find('input[type=text]').on('input', splitTxnAmounts);
        }

        return false;
    }

    function addSplitField() {
        var next_available_number = 2;
        while ($('.split_amount input[data-idx=' + next_available_number + ']').length) {
            next_available_number++;
        }
        $('.split_amount input[data-idx=' + (next_available_number-1) + ']').after('<input data-idx="' + next_available_number + '" type="text" name="amounts[]" value="0.00" /> ');
        $split_amount_div.find('input[type=text]').on('input', splitTxnAmounts);
    }

    function splitTxnAmounts(ev) {
        var input_el = ev.target;
        var total_amount = $('#amount').val();
        var amount = $(input_el).val();
        
        var el_index = $(input_el).data('idx');

        var has_more_fields = $(input_el).parent().find('input[data-idx=' + (el_index + 1) + ']').length;
        if (has_more_fields) {
            // Is not the last field; adjust next field
            var next_amount = total_amount - amount;
            var prev_index = el_index - 1;
            while ($(input_el).parent().find('input[data-idx=' + prev_index + ']').length) {
                next_amount -= $(input_el).parent().find('input[data-idx=' + prev_index + ']').val();
                prev_index--;
            }
            $(input_el).parent().find('input[data-idx=' + (el_index + 1) + ']').val(next_amount.toFixed(2));
        } else {
            // Is last field; adjust preceding field
            var prev_amount = total_amount - amount;

            var prev_index = el_index - 2;
            while ($(input_el).parent().find('input[data-idx=' + prev_index + ']').length) {
                prev_amount -= $(input_el).parent().find('input[data-idx=' + prev_index + ']').val();
                prev_index--;
            }

            $(input_el).parent().find('input[data-idx=' + (el_index - 1) + ']').val(prev_amount.toFixed(2));
        }
    }
</script>
</body>
</html>
