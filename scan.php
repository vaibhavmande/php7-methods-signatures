<?php
require_once 'vendor/autoload.php';
require_once 'Scanner.php';

$path = $argv[1];
if (empty($path)) {
    echo 'Please provide path to scan' . PHP_EOL;
    exit(1);
}

$scanner = new Scanner();
$scanner->scan($path);
