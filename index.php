<?php

require_once __DIR__ . '/vendor/autoload.php';

use ROCKS\app;

$app = new app(__DIR__);

try {
    $app->run();
} catch (Exception $e) {
    echo "Ups! An error occurred: " . $e->getMessage();
}