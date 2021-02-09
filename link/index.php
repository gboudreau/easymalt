<?php
namespace EasyMalt;

use Exception;

chdir(__DIR__.'/..');
require_once 'init.inc.php';

$access_token = NULL;
if (!empty($_GET['account'])) {
    $q = "SELECT access_token FROM plaid_tokens WHERE metadata LIKE :like";
    $access_token = DB::getFirstValue($q, '%' . $_GET['account'] . '%');
    $_POST['start'] = 1;
}

if (!empty($_POST['start'])) {
    try {
        $token = Plaid::createToken($access_token);
    } catch (Exception $ex) {
        echo "Failed to create Plaid token: " . $ex->getMessage();
        exit();
    }
} elseif (!empty($_POST['public_token'])) {
    header('Content-type: application/json; charset=UTF-8');
    try {
        Plaid::exchangeToken($_POST['public_token'], $_POST['metadata']);
        echo json_encode(['success' => TRUE]);
    } catch (Exception $ex) {
        echo json_encode(['error' => "Failed to exchange public token for access token: " . $ex->getMessage()]);
    }
    exit();
} elseif (!empty($_GET['remove_account'])) {
    $q = "SELECT access_token FROM plaid_tokens WHERE metadata LIKE :like";
    $access_token = DB::getFirstValue($q, '%' . $_GET['remove_account'] . '%');
    Plaid::removeItem($access_token);
}
?>
<html lang="en">
<head>
    <title>EasyMalt - Link bank account</title>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=yes, width=device-width">
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
</head>
<body>

&lt; <a href="/accounts/">Back</a>

<h2>Link a bank account</h2>

<?php if (empty($_POST['start'])) : ?>
    <form action="" method="post">
        <input type="hidden" name="start" value="1">
        <button type="submit">Start...</button>
    </form>
<?php else : ?>
    <script src="https://cdn.plaid.com/link/v2/stable/link-initialize.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
    <script>
        <?php if (!empty($token->link_token)) : ?>
        const handler = Plaid.create({
            token: <?php echo json_encode($token->link_token) ?>,
            onSuccess: (public_token, metadata) => {
                console.log("Success. Got public token:", public_token);
                console.log("Metadata: ", metadata);
                $.ajax({
                    method: 'POST',
                    url: '/link/',
                    data: {public_token: public_token, metadata: JSON.stringify(metadata)},
                    success: function(data, textStatus, jqXHR) {
                        if ('error' in data) {
                            alert(data.error);
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        alert('Error: ' + textStatus + ' ' + errorThrown);
                    },
                });
            },
            onLoad: () => {},
            onExit: (err, metadata) => {},
            onEvent: (eventName, metadata) => {
                console.log("onEvent:", eventName, metadata);
                if (eventName === 'HANDOFF') {
                    window.location.href = '/link/';
                }
            },
            receivedRedirectUri: null,
        });
        handler.open();
        <?php endif; ?>
    </script>
<?php endif; ?>

</body>
</html>
