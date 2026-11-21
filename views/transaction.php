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
<!doctype html>
<html lang="en" data-bs-theme="<?php echo $view['is_dark'] ? 'dark' : 'light' ?>">
<head>
    <title>EasyMalt - Transaction</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../css/lib/bootstrap.min.css">
    <link rel="stylesheet" href="../css/lib/tom-select.bootstrap5.min.css">
    <link rel="stylesheet" href="../css/ui.css">
</head>
<body class="<?php echo ($view['is_dark'] ? 'dark' : '') ?>">

<main class="container py-3 py-md-4 em-transaction-page">
<header class="em-dashboard-header">
    <div class="em-dashboard-masthead">
        <div>
            <p class="em-dashboard-eyebrow">EasyMalt / Ledger</p>
            <h1><?php echo $txn->id == 'new' ? 'New transaction' : 'Edit transaction' ?></h1>
            <?php if ($txn->id != 'new') : ?>
                <div class="mt-2"><button type="button" class="em-copy-reference" data-reference="Ref: #<?php phe($txn->id) ?>" title="Copy transaction reference">Transaction #<?php phe($txn->id) ?></button></div>
                <div id="reference-status" class="small mt-1" role="status" aria-live="polite"></div>
            <?php endif; ?>
        </div>
        <a class="btn em-dashboard-secondary" href="<?php phe($goto_link) ?>">&larr; Back</a>
    </div>
    <?php if ($txn->id != 'new') : ?>
        <div class="em-transaction-summary">
            <div><span>Account</span><strong><?php phe($txn->account_name) ?></strong></div>
            <div><span>Original description</span><strong><?php phe($txn->name) ?></strong></div>
            <div><span>Amount</span><strong><?php echo_amount($txn->amount, $txn->currency) ?></strong></div>
        </div>
    <?php endif; ?>
</header>

<form class="txn_form" method="post" action="">
    <input type="hidden" name="id" value="<?php phe($txn->id) ?>" />
    <input type="hidden" name="goto" value="<?php phe($goto_link) ?>" />
    <section class="card mb-3">
        <div class="card-body">
        <div class="row g-3">
        <?php if ($txn->id == 'new') : ?>
            <div class="col-md-6">
                    <label class="form-label" for="account_id">Account</label>
                    <select class="form-select" id="account_id" name="account_id">
                        <?php foreach ($accounts as $account) : ?>
                            <option value="<?php phe($account->id) ?>"><?php phe($account->name) ?></option>
                        <?php endforeach; ?>
                    </select>
            </div>
        <?php endif; ?>
            <div class="col-md-6">
                <label class="form-label" for="date">Date</label>
                <input class="form-control" type="date" name="date" id="date" value="<?php phe(substr($txn->date, 0, 10)) ?>" />
                <?php
                $time = substr($txn->date, 11);
                if ($time != '00:00:00' && $time != '12:00:00' && $time != '05:00:00' && $time != '04:00:00') {
                    echo '<div class="form-text">Time: ';
                    phe($time);
                    echo '</div>';
                }
                ?>
            </div>
            <div class="col-md-6">
                <?php if ($txn->amount !== NULL) : ?>
                    <div class="form-label">Amount</div>
                    <div class="d-flex align-items-center gap-3 flex-wrap"><strong><?php echo_amount($txn->amount, $txn->currency) ?></strong> <a class="btn em-dashboard-secondary" href="#split" onclick="return splitTxn(this)">Split</a></div>
                    <input type="hidden" id="amount" value="<?php phe($txn->amount) ?>" />
                    <div class="split_amount mt-2" style="display: none">
                    </div>
                <?php else : ?>
                    <label class="form-label" for="new_amount">Amount</label>
                    <input class="form-control" id="new_amount" type="number" name="amount" value="" step="0.01" />
                <?php endif; ?>
            </div>
            <div class="col-12">
                <label class="form-label" for="display_name">Short description</label>
                <input class="form-control" id="display_name" type="text" name="display_name" value="<?php phe($txn->display_name) ?>" placeholder="Short description..." />
            </div>
            <div class="col-12">
                <label class="form-label" for="memo">Memo</label>
                <textarea class="form-control" id="memo" name="memo" rows="3"><?php phe($txn->memo) ?></textarea>
            </div>
        </div>
        </div>
    </section>
    <section class="card mb-3">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label" for="category">Category</label>
                <select class="form-select" id="category" name="category">
                <?php foreach ($cats as $cat) : ?>
                    <option value="<?php phe($cat) ?>" <?php echo_if($txn->category == $cat, 'selected="selected"') ?>><?php phe($cat) ?></option>
                <?php endforeach; ?>
                </select>
            </div>
            <fieldset>
                <legend class="form-label fs-6">Tags</legend>
                <?php
                $tagGroups = [
                    ['Guillaume', 'Nayiri', 'Sevan', 'Soline', 'Massis'],
                    ['IONIQ', 'Pacifica'],
                    ['Reimbursable', 'Reimbursed', 'Reimbursement'],
                ];
                $hiddenTag = static fn($tag) => in_array($tag, ['LEAF', 'Highlander'], true) || str_starts_with($tag, 'Voyage: Niagara');
                $tags = array_values(array_unique(array_merge($tags, array_filter($txn->tags, $hiddenTag))));
                $groupedTags = array_merge(...$tagGroups);
                $tagGroups[] = array_values(array_filter($tags, static fn($tag) => !in_array($tag, $groupedTags, true) && (!$hiddenTag($tag) || in_array($tag, $txn->tags, true))));
                ?>
                <div class="em-tag-groups">
                    <?php foreach ($tagGroups as $group) : ?>
                        <div>
                            <?php foreach ($group as $tag) : ?>
                                <?php if (!in_array($tag, $tags, true)) continue; ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="tag_<?php phe($tag) ?>" name="tags[]" value="<?php phe($tag) ?>" <?php echo_if(array_contains($txn->tags, $tag), 'checked="checked"') ?>>
                                    <label class="form-check-label" for="tag_<?php phe($tag) ?>"><?php phe($tag) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        </div>
    </section>
               <div class="apply_to_all card mb-3" style="display: none">
                   <div class="card-body">
                   <div class="form-check">
                       <input class="form-check-input" type="checkbox" id="post_processing_rule" name="post_processing_rule" value="true" />
                       <label class="form-check-label" for="post_processing_rule">Apply changed fields to matching transactions</label>
                   </div>
                   <div class="em-rule-details mt-3" style="display: none">
                   <div class="row g-3 mb-3">
                       <div class="col-md-6"><label class="form-label" for="rule_matching">Name contains</label>
                       <input class="form-control" id="rule_matching" type="text" name="post_processing_rule_matching" value="<?php phe($txn->name) ?>" />
                       <div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="post_processing_rule_matching_format" name="post_processing_rule_matching_format" value="regex" /> <label class="form-check-label" for="post_processing_rule_matching_format">is RegExp</label></div></div>
                       <div class="col-md-6"><label class="form-label" for="rule_amount">Amount equals</label>
                       <input class="form-control" id="rule_amount" type="text" name="post_processing_rule_amount_equals" value="" aria-describedby="rule_amount_help" />
                       <div class="form-text" id="rule_amount_help">Use a negative number for payments; leave empty to not match against amount.</div></div>
                   </div>
                   <div class="fw-bold small mb-2">Fields to apply</div>
                   <ul class="list-unstyled mb-1">
                        <li class="desc form-check">
                            <input class="form-check-input" type="checkbox" id="post_processing_rule_desc" name="post_processing_rule_desc" value="true" />
                            <label class="form-check-label" for="post_processing_rule_desc">
                               Short description: <span class="value"></span>
                           </label>
                       </li>
                   </ul>
                   <ul class="list-unstyled mb-1">
                        <li class="memo form-check">
                            <input class="form-check-input" type="checkbox" id="post_processing_rule_memo" name="post_processing_rule_memo" value="true" />
                            <label class="form-check-label" for="post_processing_rule_memo">
                               Memo: <span class="value"></span>
                           </label>
                       </li>
                   </ul>
                   <ul class="list-unstyled mb-1">
                        <li class="tags form-check">
                            <input class="form-check-input" type="checkbox" id="post_processing_rule_tags" name="post_processing_rule_tags" value="true" />
                            <label class="form-check-label" for="post_processing_rule_tags">
                               Tags: <span class="value"></span>
                           </label>
                       </li>
                   </ul>
                   <ul class="list-unstyled mb-0">
                        <li class="category form-check">
                            <input class="form-check-input" type="checkbox" id="post_processing_rule_category" name="post_processing_rule_category" value="true" />
                            <label class="form-check-label" for="post_processing_rule_category">
                               Category: <span class="value"></span>
                           </label>
                       </li>
                   </ul>
                   </div>
                   </div>
                </div>
     <div class="em-dashboard-actions em-transaction-actions">
         <input class="btn em-dashboard-primary px-4" type="submit" name="action" value="Save" />
         <a href="<?php phe($goto_link) ?>">Cancel</a>
     </div>
</form>
</main>

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

<script src="../js/lib/tom-select.complete.min.js"></script>
<script
        src="https://code.jquery.com/jquery-3.1.1.min.js"
        integrity="sha256-hVVnYaiADRTO2PzUGmuLJr8BLUSjGIZsDYGmIJLv2b8="
        crossorigin="anonymous"></script>
<script>
    const referenceButton = document.querySelector('.em-copy-reference');
    if (referenceButton) {
        referenceButton.addEventListener('click', async function () {
            const status = document.getElementById('reference-status');
            try {
                await navigator.clipboard.writeText(this.dataset.reference);
                status.textContent = 'Transaction reference copied.';
            } catch (error) {
                status.textContent = 'Could not copy the reference. Please copy ' + this.dataset.reference + ' manually.';
            }
        });
    }
    if (typeof TomSelect !== 'undefined') {
        new TomSelect('#category', {maxOptions: null, allowEmptyOption: true, refreshThrottle: 0});
    }
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

        var tags = $.map($('input[name="tags[]"]:checked'), function(e,i) {
            return e.value;
        });
        if (JSON.stringify([...tags].sort()) != JSON.stringify([...initial_tags].sort())) {
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
        $('.em-rule-details').toggle(enabled);
    }

    var $split_amount_div;
    $(function() {
        $split_amount_div = $('.split_amount');
    });

    function splitTxn() {
        if ($split_amount_div.hasClass('visible')) {
            // Cancel
            $('a[href="#split"]').text('Split');
            $split_amount_div.hide();
            $split_amount_div.removeClass('visible');
            $split_amount_div.html('');
        } else {
            $('a[href="#split"]').text('Cancel split');
            $split_amount_div.show();
            $split_amount_div.addClass('visible');
            $split_amount_div.append('<input class="form-control d-inline-block w-auto mw-100 me-2 mb-2" data-idx="0" type="text" inputmode="decimal" aria-label="Split amount 1" name="amounts[]" value="' + $('#amount').val() + '" /> ');
            $split_amount_div.append('<input class="form-control d-inline-block w-auto mw-100 me-2 mb-2" data-idx="1" type="text" inputmode="decimal" aria-label="Split amount 2" name="amounts[]" value="0.00" /> ');
            $split_amount_div.append('<a href="#add" class="btn btn-outline-secondary mb-2" onclick="addSplitField(); return false;">Add split</a>');
            $split_amount_div.find('input[type=text]').on('input', splitTxnAmounts);
        }

        return false;
    }

    function addSplitField() {
        var next_available_number = 2;
        while ($('.split_amount input[data-idx=' + next_available_number + ']').length) {
            next_available_number++;
        }
        $('.split_amount input[data-idx=' + (next_available_number-1) + ']').after('<input class="form-control d-inline-block w-auto mw-100 me-2 mb-2" data-idx="' + next_available_number + '" type="text" inputmode="decimal" aria-label="Split amount ' + (next_available_number + 1) + '" name="amounts[]" value="0.00" /> ');
        $split_amount_div.find('input[type=text]').off('input', splitTxnAmounts).on('input', splitTxnAmounts);
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
