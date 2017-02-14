<?php
namespace EasyMalt;
chdir(__DIR__.'/..');
require 'init.inc.php';

$q = "SELECT * FROM transactions WHERE id = :id";
$txn = DB::getFirst($q, (int) @$_REQUEST['id']);
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

if (isset($_POST['id'])) {
    $params = ['id' => (int) $_POST['id']];
    $updates = ["post_processed = 'yes'"];

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
    $updates[] = 'tags = :tags';
    $params['tags'] = empty($_POST['tags']) ? NULL : implode(',', $_POST['tags']);

    $q = "UPDATE transactions SET " . implode(', ', $updates) . " WHERE id = :id";
    DB::execute($q, $params);

    if ($_POST['post_processing_rule'] == 'true') {
        $matches_name = $_POST['post_processing_rule_matching'];
        if (@$_POST['post_processing_rule_matching_format'] != 'regex') {
            $matches_name = preg_quote($matches_name);
        }
        $updates = [];
        $params = [];
        if (@$_POST['post_processing_rule_desc'] == 'true') {
            $updates[] = 'display_name = :display_name';
            $params['display_name'] = $_POST['display_name'];
        }
        if (@$_POST['post_processing_rule_category'] == 'true') {
            $updates[] = 'category = :category';
            $params['category'] = $_POST['category'];
        }
        if (@$_POST['post_processing_rule_tags'] == 'true') {
            $updates[] = 'tags = :tags';
            $params['tags'] = empty($_POST['tags']) ? NULL : implode(',', $_POST['tags']);
        }
        $q = "INSERT INTO post_processing SET regex = :regex, " . implode(", ", $updates) . " ON DUPLICATE KEY UPDATE " . implode(", ", $updates);
        DB::insert($q, array_merge($params, ['regex' => $matches_name]));

        $q = "SELECT id FROM transactions WHERE `name` REGEXP :regex";
        $txn_ids = DB::getAllValues($q, $matches_name);

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

$q = "SELECT DISTINCT category FROM transactions ORDER BY category";
$cats = DB::getAllValues($q);
?>
<html>
<head>
    <title>EasyMalt - Transaction</title>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=yes, width=device-width">
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
</head>
<body>

<div style="text-align: center">
    &lt; <a href="<?php phe($_SESSION['previous_page'] . '&scrollPos=' . $_GET['scrollPos']) ?>">Back</a>
</div>

<form class="txn_form" method="post" action="">
    <input type="hidden" name="id" value="<?php phe($txn->id) ?>" />
    <input type="hidden" name="goto" value="<?php phe($_SESSION['previous_page'] . '&scrollPos=' . $_GET['scrollPos']) ?>" />
    <table>
        <tr>
            <td>
                ID: <?php phe($txn->id) ?>
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
<script
        src="https://code.jquery.com/jquery-3.1.1.min.js"
        integrity="sha256-hVVnYaiADRTO2PzUGmuLJr8BLUSjGIZsDYGmIJLv2b8="
        crossorigin="anonymous"></script>
<script>
    $(function() {
        $('input, select').on('change', function() {
            displayApplyToAll()
        });
        $('input[type=text]').on('input', function() {
            displayApplyToAll()
        });
    });
    var initial_desc = <?php pjs($txn->display_name) ?>;
    var initial_tags = <?php pjs($txn->tags) ?>;
    var initial_category = <?php pjs($txn->category) ?>;
    function displayApplyToAll() {
        var changed = false;

        var desc = $('[name=display_name]').val();
        if (desc != initial_desc) {
            changed = true;
            $('.apply_to_all .desc .value').text(desc);
            $('.apply_to_all .desc').show();
            $('.apply_to_all .desc [type=checkbox]').prop('checked', true);
        } else {
            $('.apply_to_all .desc').hide();
            $('.apply_to_all .desc [type=checkbox]').prop('checked', false);
        }

        var tags = $.map($('input[id^=tag_]:checked'), function(e,i) {
            return e.value;
        });
        if (JSON.stringify(tags) != JSON.stringify(initial_tags)) {
            changed = true;
            $('.apply_to_all .tags .value').text(tags.join(', '));
            $('.apply_to_all .tags').show();
            $('.apply_to_all .tags [type=checkbox]').prop('checked', true);
        } else {
            $('.apply_to_all .tags').hide();
            $('.apply_to_all .tags [type=checkbox]').prop('checked', false);
        }

        var category = $('[name=category]').val();
        if (category != initial_category) {
            changed = true;
            $('.apply_to_all .category .value').text(category);
            $('.apply_to_all .category').show();
            $('.apply_to_all .category [type=checkbox]').prop('checked', true);
        } else {
            $('.apply_to_all .category').hide();
            $('.apply_to_all .category [type=checkbox]').prop('checked', false);
        }

        if (changed) {
            $('.apply_to_all').show();
        } else {
            $('.apply_to_all').hide();
        }

        var enabled = $('#post_processing_rule').prop('checked');
        if (enabled) {
            $('.apply_to_all').addClass('selected');
        } else {
            $('.apply_to_all').removeClass('selected');
        }
    }
</script>
</body>
</html>
