<?php

namespace EasyMalt;

use Delight\Cookie\Cookie;

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

if (isset($_GET['dark'])) {
    $_COOKIE['is_dark'] = $_GET['dark'];
}
$is_dark = (Cookie::get('is_dark', '0') === '1');
Cookie::setcookie('is_dark', $is_dark ? '1' : '0', time() + 365*24*60*60, '/');

if (!empty(session_save_path()) && !is_writable(session_save_path())) {
    echo '<div style="color:red">Warning: Session path "'.session_save_path().'" is not writable for PHP!</div>';
}
