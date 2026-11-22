<?php
namespace EasyMalt;

if (!isset($view)) { http_response_code(404); return; }
?>
<!doctype html>
<html lang="en" data-bs-theme="<?php echo $view['is_dark'] ? 'dark' : 'light' ?>">
<head>
    <title>EasyMalt - Accounts</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../css/lib/bootstrap.min.css">
    <link rel="stylesheet" href="../css/ui.css">
</head>
<body class="<?php echo $view['is_dark'] ? 'dark' : '' ?>">
<main class="container-fluid py-3">
<?php renderPageHeader('Accounts', $view['is_dark'], back_link: $view['back_link']) ?>
<div class="table-responsive">
<table class="table table-sm table-striped align-middle w-auto mw-100 mx-auto accounts">
    <thead>
    <tr>
        <th>Account</th>
        <th class="text-end">Balance</th>
        <th class="text-center ps-3">Last Updated</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($view['rows'] as $row) : ?>
        <tr class="<?php echo (($even=!@$even) ? 'even' : 'odd') ?>">
            <td class="name">
                <a href="/search?q=<?php echo urlencode("account=$row->name") ?>"><?php phe($row->name) ?></a>
            </td>
            <td class="text-end text-nowrap">
                <?php echo_amount($row->balance, $row->currency) ?>
            </td>
            <td class="text-start ps-3">
                <?php echo (empty($row->last_updated) ? 'N/A' : he($row->last_updated)) ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot class="fw-bold">
    <?php foreach ($view['totals'] as $currency => $total) : ?>
        <tr class="<?php echo (($even=!@$even) ? 'even' : 'odd') ?>">
            <td class="name">
                Total (<?php phe($currency) ?>)
            </td>
            <td class="text-end text-nowrap">
                <?php echo_amount($total, $currency) ?>
            </td>
            <td>
            </td>
        </tr>
    <?php endforeach; ?>
    </tfoot>
</table>
</div>
</main>
</body>
</html>
