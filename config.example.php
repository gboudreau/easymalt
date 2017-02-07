<?php

$CONFIG = new stdClass;

$CONFIG->DB_ENGINE   = 'mysql';
$CONFIG->DB_HOST     = 'localhost';
$CONFIG->DB_USER     = 'loc_fin_user';
$CONFIG->DB_PWD      = 'some_password_here_';
$CONFIG->DB_NAME     = 'easymalt';
$CONFIG->DB_TIMEZONE = 'America/Montreal'; // PHP format

// The name of your default currency, and the symbol to use to display it.
// See the transactions table, currency column, for the currency name to use here.
$CONFIG->DEFAULT_CURRENCY = ['CAD' => '$'];

// This is the token you need to configure in easymalt.ini, in your local Downloader/Importer config
// Ref: [remote] section in https://github.com/gboudreau/easymalt-local/blob/master/easymalt.example.ini
$CONFIG->API_AUTH_ACCESS_TOKEN = 'some_random_string_here';

// Defines which categories/tags to display in the /taxes/ page
$CONFIG->TAXES_WHERE_CLAUSE = [
    [
        'category' => [
            'Professional Licenses',
            // OR
            'Home: Insurance',
            // OR
            'Home: Electricity',
            // OR
            'Home: Repairs',
            // OR
            'Home: Phone',
            // OR
            'Home: Natural Gas',
            // OR
            'Home: Condo Fees',
            // OR
            'Taxes: Municipal',
        ]
    ],
    // OR
    [
        'category' => 'Auto%',
        // AND
        'tag' => [
            '%Highlander%',
            // OR
            '%Jane Doe%'
        ]
    ],
];
