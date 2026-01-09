<?php
declare(strict_types=1);

/**
 * public_html/public/staff/_reset_staff_password.php
 * TEMP: Reset staff password for a known email.
 * Delete this file immediately after successful reset.
 */

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

require_once __DIR__ . '/../_init.php';

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

if (!function_exists('db')) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "db() not available\n";
  exit;
}

$email = 'mkomigbo24@gmail.com';

/**
 * Set your desired password here.
 * Use something you can type reliably.
 */
$newPassword = 'ChangeMeNow_12345';

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
if (!$hash) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "password_hash() failed\n";
  exit;
}

try {
  $pdo = db();

  // Ensure the user exists
  $st = $pdo->prepare("SELECT id, email, is_active FROM staff_users WHERE email = ? LIMIT 1");
  $st->execute([$email]);
  $u = $st->fetch(PDO::FETCH_ASSOC);

  if (!$u) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "User not found for email: {$email}\n";
    exit;
  }

  // Update hash
  $up = $pdo->prepare("UPDATE staff_users SET password_hash = ? WHERE email = ? LIMIT 1");
  $up->execute([$hash, $email]);

  // Verify what was saved (length + prefix only)
  $st2 = $pdo->prepare("SELECT id, email, is_active, CHAR_LENGTH(password_hash) AS hash_len, password_hash FROM staff_users WHERE email = ? LIMIT 1");
  $st2->execute([$email]);
  $r = $st2->fetch(PDO::FETCH_ASSOC);

  header('Content-Type: text/plain; charset=utf-8');
  echo "OK\n";
  echo "email={$r['email']}\n";
  echo "active={$r['is_active']}\n";
  echo "hash_len={$r['hash_len']}\n";
  echo "hash_prefix=" . substr((string)$r['password_hash'], 0, 4) . "\n";
  echo "new_password={$newPassword}\n";

} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Reset error: " . $e->getMessage() . "\n";
  exit;
}
