<?php
declare(strict_types=1);

/**
 * /public/subjects/_dispatch_debug.php
 * Debug wrapper to diagnose 503 from subject.php/view.php.
 * Remove after fixing.
 */

@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
@ini_set('log_errors', '1');

// Write logs to a file you control
$log = __DIR__ . '/_subjects_debug.log';
@ini_set('error_log', $log);

error_reporting(E_ALL);

header('Content-Type: text/plain; charset=UTF-8');

echo "DISPATCH_DEBUG\n";
echo "PHP=" . PHP_VERSION . "\n";
echo "URI=" . ($_SERVER['REQUEST_URI'] ?? '') . "\n";
echo "SCRIPT=" . (__FILE__) . "\n\n";

$slug = '';
foreach (['slug','subject','s'] as $k) {
  if (isset($_GET[$k]) && is_string($_GET[$k]) && trim($_GET[$k]) !== '') {
    $slug = trim($_GET[$k]);
    break;
  }
}
echo "INPUT_SLUG=" . $slug . "\n";
echo "GET="; var_export($_GET); echo "\n\n";

try {
  // Ensure canonical param name
  if ($slug !== '') {
    $_GET['slug'] = $slug;
    $_GET['subject'] = $_GET['subject'] ?? $slug;
  }

  // Bootstrap via your normal public init
  require_once __DIR__ . '/../_init.php';

  // Call the real handler (which will include view.php)
  require __DIR__ . '/subject.php';

  echo "\n\nOK: subject.php returned normally.\n";

} catch (Throwable $e) {
  echo "\n\nTHROWABLE_CAUGHT\n";
  echo "TYPE=" . get_class($e) . "\n";
  echo "MSG=" . $e->getMessage() . "\n";
  echo "FILE=" . $e->getFile() . ":" . $e->getLine() . "\n\n";
  echo $e->getTraceAsString() . "\n";
}
