<?php
namespace EasyMalt;

if (!isset($view)) {
    http_response_code(404);
    return;
}

$is_dark = $view['is_dark'];
?>
<!doctype html>
<html lang="en" data-bs-theme="<?php echo $is_dark ? 'dark' : 'light' ?>">
<head>
    <title>EasyMalt - Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/lib/bootstrap.min.css">
    <link rel="stylesheet" href="css/ui.css">
</head>
<body class="em-dashboard <?php echo ($is_dark ? 'dark' : '') ?>">
<main class="container-fluid py-3">

<header class="em-dashboard-header em-dashboard-frame">
    <div class="em-dashboard-masthead">
        <div>
            <p class="em-dashboard-eyebrow">EasyMalt</p>
            <h1>Dashboard</h1>
        </div>
        <div class="em-dashboard-actions">
            <a class="btn em-dashboard-secondary" href="<?php phe($view['theme_url']) ?>"><?php echo $is_dark ? 'Light mode' : 'Dark mode' ?></a>
            <a class="btn em-dashboard-primary" href="txn/?id=new">New transaction</a>
        </div>
    </div>
    <nav class="em-dashboard-nav" aria-label="Main navigation">
        <a href="accounts/">Accounts</a>
        <?php foreach (($view['extra_pages'] ?: []) as $url => $page_name) : ?>
            <a href="<?php phe($url) ?>"><?php phe($page_name) ?></a>
        <?php endforeach; ?>
    </nav>
</header>

<?php
if ($view['show_cookie_prompt']) {
    ?>
    <section class="em-dashboard-frame em-filter-panel p-3" aria-labelledby="access-heading">
        <h2 class="h5" id="access-heading">Connect to your ledger</h2>
        <p>Missing ECK1C value in cookies. Find it in 1Password.</p>
        <form method="post" action="" class="em-dashboard-actions">
            <label class="visually-hidden" for="eck1c">ECK1C</label>
            <input class="form-control w-auto" id="eck1c" type="text" name="ECK1C" placeholder="ECK1C">
            <button class="btn em-dashboard-primary" type="submit">Save</button>
        </form>
    </section>
    </main></body>
    </html>
    <?php
    return;
}

$show_search_params = $view['show_search_params'];
$query_values = $view['query_values'];
?>

<section class="em-dashboard-frame em-filter-panel" aria-label="Transaction search">
    <?php if (!$show_search_params) : ?>
        <div class="em-report-period">
            <label for="report-dates">Report dates:</label>
            <select class="form-select" id="report-dates" onchange="window.location.href='./?' + this.value">
                <?php if (!isset($view['quick_dates'][$view['from_to_query']])) : ?>
                    <option value="<?php phe($view['from_to_query']) ?>" selected>Custom date range</option>
                <?php endif; ?>
                <?php foreach ($view['quick_dates'] as $value => $name) : ?>
                    <option value="<?php phe($value) ?>" <?php echo_if($value === $view['from_to_query'], 'selected') ?>><?php phe($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <details class="em-search-details" <?php echo_if($show_search_params, 'open') ?>>
        <summary>Filters<?php if ($show_search_params) : ?> <span class="em-filter-status">Active search</span><?php endif; ?></summary>
        <form class="em-search-form" id="search_form" action="./" method="post">
            <input name="do_search" value="1" type="hidden">
            <div class="em-filter-fields">
                <div>
                    <label class="form-label" for="filter-desc">Description</label>
                    <input class="form-control" id="filter-desc" name="desc" type="search" value="<?php phe($query_values['desc'] ?? '') ?>" placeholder="Name or memo">
                </div>
                <?php foreach (['category' => ['Category', 'categories'], 'account' => ['Account', 'accounts'], 'tag' => ['Tag', 'tags']] as $param => [$label, $options]) : ?>
                    <div>
                        <label class="form-label" for="filter-<?php phe($param) ?>"><?php phe($label) ?></label>
                        <select class="form-select" id="filter-<?php phe($param) ?>" name="<?php phe($param) ?>">
                            <option value="">Any <?php phe(strtolower($label)) ?></option>
                            <?php $selected = $query_values[$param] ?? ''; ?>
                            <?php if ($selected !== '' && !in_array($selected, $view[$options], true)) : ?>
                                <option value="<?php phe($selected) ?>" selected><?php phe($selected) ?></option>
                            <?php endif; ?>
                            <?php foreach ($view[$options] as $option) : ?>
                                <option value="<?php phe($option) ?>" <?php echo_if($selected === $option, 'selected') ?>><?php phe($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="em-filter-fields">
                <?php foreach (['date' => 'Date', 'amount' => 'Amount'] as $param => $label) : ?>
                    <fieldset>
                        <legend><?php phe($label) ?></legend>
                        <?php
                        $values = $param === 'date' && !$show_search_params ? $view['date_input_values'] : $query_values[$param];
                        $conditions = array_filter($values, static fn($op) => $op !== '', ARRAY_FILTER_USE_KEY);
                        foreach (array_pad(array_keys($conditions), 2, '') as $i => $op) :
                        ?>
                            <div class="em-filter-condition">
                                <select class="form-select" aria-label="<?php phe($label . ' condition ' . ($i + 1) . ' operator') ?>" name="<?php phe($param) ?>_op[]">
                                    <?php foreach ($view['ops_avail'] as $operator) : ?>
                                        <option value="<?php phe($operator) ?>" <?php echo_if($op === $operator, 'selected') ?>><?php phe($operator) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input class="form-control" aria-label="<?php phe($label . ' condition ' . ($i + 1) . ' value') ?>" name="<?php phe($param) ?>[]" type="<?php echo $param === 'date' ? 'date' : 'number' ?>" <?php echo_if($param === 'amount', 'step="0.01" placeholder="Any amount"') ?> value="<?php phe($values[$op] ?? '') ?>">
                            </div>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endforeach; ?>
                <div>
                    <label class="form-label" for="filter-ids">Transaction IDs</label>
                    <input class="form-control" id="filter-ids" name="ids" type="text" value="<?php phe($query_values['ids'] ?? '') ?>" placeholder="e.g. 123,456" aria-describedby="filter-ids-hint">
                    <p class="form-text mb-0" id="filter-ids-hint">Separate multiple IDs with commas.</p>
                </div>
            </div>
            <div class="form-check">
                <input class="form-check-input" id="filter-all-categories" name="show_all_cats" type="checkbox" value="1" <?php echo_if($query_values['show_all_cats'] ?? false, 'checked') ?>>
                <label class="form-check-label" for="filter-all-categories">Show all categories in the summary</label>
            </div>
            <div class="em-dashboard-actions em-filter-actions">
                <button class="btn em-dashboard-primary" type="submit">Search transactions</button>
                <a href="./">Reset filters</a>
            </div>
        </form>
    </details>
</section>

<div class="row g-3 my-3">
<?php foreach ($view['sections'] as $what => $section) : ?>
    <section class="col-12 col-xl-6 em-summary" aria-label="<?php phe($what) ?> summary">
    <h2 class="h4"><?php phe($what) ?></h2>
    <div class="row g-3">
    <div class="col-12 col-md-7">
    <table class="table table-sm" cellspacing="0">
        <tr>
            <th>Category</th>
            <th>Spending</th>
        </tr>
        <?php foreach ($section['rows'] as $row) : ?>
            <tr class="<?php echo $row['class'] ?>">
                <td><a href="<?php phe($row['url']) ?>"><?php phe($row['category']) ?></a></td>
                <td style="text-align: right">
                    <?php echo_amount($row['amount'], $row['currency']) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <tr class="total <?php echo $what ?>">
            <th>Total</th>
            <th style="text-align: right">
                <?php
                foreach ($section['totals'] as $total) {
                    if (!$total['is_default_currency']) {
                        echo "+&nbsp;";
                    }
                    echo_amount($total['amount'], $total['currency']);
                    echo '<br/>';
                }
                ?>
            </th>
        </tr>
    </table>

    </div>
    <div class="col-12 col-md-5">
        <?php if (array_sum(array_column(array_slice($section['pie_data'], 1), 1)) > 0) : ?>
            <div class="em-chart">
                <div class="em-chart-plot">
                    <canvas id="chart_<?php phe($what) ?>" role="img" aria-label="<?php phe($what) ?> by category; use the category links for details"></canvas>
                </div>
                <ul class="em-chart-legend d-flex flex-wrap justify-content-center gap-1 list-unstyled mt-2 mb-0" aria-label="<?php phe($what) ?> chart legend"></ul>
            </div>
        <?php else : ?>
            <p>No chart data.</p>
        <?php endif; ?>
    </div>
    </div>
    </section>
<?php endforeach; ?>
</div>

<?php
if ($view['transaction_table'] === null) {
    echo "No transactions.";
} else {
    renderView('partials/transactions-table', $view['transaction_table']);
}
?>

<script src="js/lib/chart.umd.min.js"></script>
<script>
    window.addEventListener('scroll', function () {
        history.replaceState(null, '', <?php echo json_encode($view['history_url'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> + Math.round(window.scrollY));
    });
        <?php if ($view['scroll_position'] !== null) : ?>
    window.scrollTo(0, <?php echo json_encode($view['scroll_position']) ?>);
        <?php endif; ?>

    document.querySelectorAll('[data-edit-transaction]').forEach(function (link) {
        link.addEventListener('click', function () {
            var url = new URL(link.href);
            url.searchParams.set('scrollPos', Math.round(window.scrollY));
            link.href = url.href;
        });
    });

    if (typeof Chart !== 'undefined') {
        const sections = <?php echo json_encode($view['sections'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const pieUrl = <?php echo json_encode($view['pie_url'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const colors = ['#397c75', '#d18b50', '#5678a0', '#b95e58', '#8a719d', '#a89951', '#4d96a2', '#a86d83'];
        Object.entries(sections).forEach(function ([name, section]) {
            const canvas = document.getElementById('chart_' + name);
            if (!canvas) return;
            const rows = section.pie_data.slice(1);
            const labels = rows.map(row => row[0]);
            const sliceColors = rows.map((row, i) => colors[i % colors.length]);
            const chart = new Chart(canvas, {
                type: 'pie',
                data: {labels, datasets: [{data: rows.map(row => row[1]), backgroundColor: sliceColors}]},
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {legend: {display: false}},
                    onClick: function (event, elements) {
                        if (elements.length) window.location.href = pieUrl.replace('__category__', encodeURIComponent(rows[elements[0].index][0]));
                    }
                }
            });

            // Keep wrapping legend items outside the canvas so they cannot shrink the pie.
            const legend = canvas.closest('.em-chart').querySelector('.em-chart-legend');
            labels.forEach((label, i) => {
                const item = document.createElement('li');
                const button = document.createElement('button');
                const swatch = document.createElement('span');
                swatch.style.backgroundColor = sliceColors[i];
                swatch.setAttribute('aria-hidden', 'true');
                button.type = 'button';
                button.className = 'em-chart-key';
                button.setAttribute('aria-pressed', 'true');
                button.append(swatch, label);
                button.addEventListener('click', () => {
                    chart.toggleDataVisibility(i);
                    button.setAttribute('aria-pressed', String(chart.getDataVisibility(i)));
                    chart.update();
                });
                item.append(button);
                legend.append(item);
            });
        });
    }
</script>
</main>
</body>
</html>
