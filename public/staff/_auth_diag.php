<?php
declare(strict_types=1);

/**
 * /public/staff/_auth_diag.php
 * Minimal auth/DB diagnostics for staff login.
 * Delete this file after we fix the issue.
 */

require_once __DIR__ . '/../_init.php';

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

header('Content-Type: text/plain; charset=utf-8');

echo "RUNNING FILE: " . (__FILE__) . "\n";
echo "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? '') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? '') . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? '') . "\n\n";

echo "APP_ROOT: " . (defined('APP_ROOT') ? APP_ROOT : '(not defined)') . "\n";
echo "PRIVATE_PATH: " . (defined('PRIVATE_PATH') ? PRIVATE_PATH : '(not defined)') . "\n\n";

/* Is auth.php being loaded? */
$auth_path = (defined('APP_ROOT') ? (APP_ROOT . '/private/functions/auth.php') : '');
echo "AUTH EXPECTED PATH: " . ($auth_path ?: '(none)') . "\n";
echo "AUTH FILE EXISTS: " . (($auth_path && is_file($auth_path)) ? 'YES' : 'NO') . "\n";
if ($auth_path && is_file($auth_path)) {
  require_once $auth_path;
}
echo "mk_attempt_staff_login exists: " . (function_exists('mk_attempt_staff_login') ? 'YES' : 'NO') . "\n\n";

/* DB sanity */
if (!function_exists('db')) {
  echo "db() function: MISSING\n";
  exit;
}

try {
  $pdo = db();
  echo "db() returns PDO: " . (($pdo instanceof PDO) ? 'YES' : 'NO') . "\n";
  if (!$pdo instanceof PDO) exit;

  $dbName = (string)$pdo->query("SELECT DATABASE()")->fetchColumn();
  echo "DATABASE(): " . $dbName . "\n";

  $hasStaffUsers = (bool)$pdo->query("SHOW TABLES LIKE 'staff_users'")->fetchColumn();
  echo "staff_users table exists: " . ($hasStaffUsers ? 'YES' : 'NO') . "\n";

  if ($hasStaffUsers) {
    $email = 'mkomigbo24@gmail.com';
    $st = $pdo->prepare("SELECT id, email, is_active, LENGTH(password_hash) AS hash_len FROM staff_users WHERE email=? LIMIT 1");
    $st->execute([$email]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    echo "Lookup staff_users by email: " . ($row ? 'FOUND' : 'NOT FOUND') . "\n";
    if ($row) {
      echo "id={$row['id']} email={$row['email']} is_active={$row['is_active']} hash_len={$row['hash_len']}\n";
    }
  }

} catch (Throwable $e) {
  echo "DB ERROR: " . $e->getMessage() . "\n";
}
