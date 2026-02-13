<?php

if (getenv('APP_DEBUG') === 'true') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/config/bootstrap.php';

Flight::start();
