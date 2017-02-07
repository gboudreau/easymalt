<?php
namespace EasyMalt;
chdir(__DIR__.'/..');
require 'init.inc.php';

$q = "SELECT * FROM transactions WHERE id = :id";
$txn = DB::getFirst($q, (int) @$_REQUEST['id']);
$txn->tags = explode(',', $txn->tags);

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
                    <input type="checkbox" id="tag_<?php echo he($tag) ?>" name="tags[]" value="<?php phe($tag) ?>" <?php if (array_contains($txn->tags, $tag)) { echo 'checked="checked"'; } ?>/>
                    <label for="tag_<?php echo he($tag) ?>"><?php phe($tag) ?></label>
                <?php endforeach; ?>
            </td>
        </tr>
        <tr>
            <td>
                Category:<br/>
                <select name="category">
                <?php foreach ($cats as $cat) : ?>
                    <option value="<?php phe($cat) ?>" <?php if ($txn->category == $cat) { echo 'selected="selected"'; } ?>><?php phe($cat) ?></option>
                <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><input type="submit" name="action" value="Save" /></td>
        </tr>
    </table>
</form>
</body>
</html>
