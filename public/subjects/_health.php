<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=UTF-8');
echo "OK\n";
echo "URI=" . ($_SERVER['REQUEST_URI'] ?? '') . "\n";
echo "GET=";
var_export($_GET);
echo "\n";
