<?php
declare(strict_types=1);
define('APP_ROOT', __DIR__ . '/app/mkomigbo');
require_once APP_ROOT . '/private/assets/initialize.php';

header('Content-Type: text/plain; charset=utf-8');
echo "ok\n";
echo "has_h=" . (function_exists('h') ? 'yes' : 'no') . "\n";
echo "has_url_for=" . (function_exists('url_for') ? 'yes' : 'no') . "\n";
echo "has_db=" . (function_exists('db') ? 'yes' : 'no') . "\n";
