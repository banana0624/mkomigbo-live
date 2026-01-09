<?php
declare(strict_types=1);

/**
 * public/_bootstrap.php
 * Deterministic bootstrap (correct path for your server layout).
 */

if (defined('APP_BOOTSTRAPPED') && APP_BOOTSTRAPPED === true) {
    return;
}
define('APP_BOOTSTRAPPED', true);

/**
 * __DIR__ here is:
 *   /home/mkomigbo/public_html/public
 *
 * We want:
 *   /home/mkomigbo/public_html/app/mkomigbo
 */
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__) . '/app/mkomigbo');
}

$init = APP_ROOT . '/private/assets/initialize.php';

if (!is_file($init)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Init not found: {$init}\n";
    exit;
}

require_once $init;
