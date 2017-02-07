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
