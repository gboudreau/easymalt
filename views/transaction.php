<?php
namespace EasyMalt;

if (!isset($view)) {
    http_response_code(404);
    return;
}

$txn = $view['txn'];
$tags = $view['tags'];
$cats = $view['cats'];
$goto_link = $view['goto_link'];
$accounts = $view['accounts'];
$post_process_result = $view['post_process_result'];
?>
<html>
<head>
    <title>EasyMalt - Transaction</title>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=yes, width=device-width">
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
</head>
<body class="<?php echo ($view['is_dark'] ? 'dark' : '') ?>">

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
                <?php
                $time = substr($txn->date, 11);
                if ($time != '00:00:00' && $time != '12:00:00' && $time != '05:00:00' && $time != '04:00:00') {
                    echo "&nbsp;";
                    phe($time);
                }
                ?>
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
                Account: <?php phe($txn->account_name) ?>
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
