<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "diag ok\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "SAPI: " . PHP_SAPI . "\n";
echo "FILE: " . __FILE__ . "\n";
echo "DIR: " . __DIR__ . "\n";
echo "URI: " . ($_SERVER['REQUEST_URI'] ?? '') . "\n";
