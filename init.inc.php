<?php

namespace EasyMalt;

require_once 'vendor/autoload.php';

require_once 'functions.inc.php';

// Connect to DB ASAP, since it's where we'll save PHP errors
try {
    DB::connect();
} catch (\Exception $ex) {
    die($ex->getMessage());
}

ini_set('error_reporting', E_ALL);

session_start();

if (@$_GET['dark'] === '1' || $_COOKIE['is_dark']) {
    $_SESSION['is_dark'] = TRUE;
}
if (@$_GET['dark'] === '0') {
    $_SESSION['is_dark'] = FALSE;
}
$is_dark = @$_SESSION['is_dark'];

if (!empty(session_save_path()) && !is_writable(session_save_path())) {
    echo '<div style="color:red">Warning: Session path "'.session_save_path().'" is not writable for PHP!</div>';
}
