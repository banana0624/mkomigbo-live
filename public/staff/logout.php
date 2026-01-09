<?php
declare(strict_types=1);

/**
 * /public/staff/logout.php
 * Staff logout (RBAC) – secure session teardown + audit + redirect
 */

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

require_once __DIR__ . '/../_init.php';

/* ---------------------------------------------------------
   Load auth helpers
--------------------------------------------------------- */
if (!function_exists('mk_staff_logout')) {
  $auth = null;
  if (defined('PRIVATE_PATH')) {
    $auth = PRIVATE_PATH . '/functions/auth.php';
  } elseif (defined('APP_ROOT')) {
    $auth = APP_ROOT . '/private/functions/auth.php';
  }
  if ($auth && is_file($auth)) {
    require_once $auth;
  }
}

/* ---------------------------------------------------------
   Start session using RBAC policy when available
--------------------------------------------------------- */
if (function_exists('mk__session_start')) {
  mk__session_start();
} else {
  if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
}

/* ---------------------------------------------------------
   Perform RBAC logout (audit + session key cleanup)
--------------------------------------------------------- */
if (function_exists('mk_staff_logout')) {
  mk_staff_logout();
} else {
  // Minimal fallback if auth.php not loaded
  unset($_SESSION['staff_user_id'], $_SESSION['staff_email'], $_SESSION['staff_role']);
  @session_regenerate_id(true);
}

/* ---------------------------------------------------------
   Hard teardown: clear session array + cookie + destroy
   (prevents redirect loops / stale sessions)
--------------------------------------------------------- */
$_SESSION = [];

if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();

  // PHP 7.3+ supports options array
  @setcookie(session_name(), '', [
    'expires'  => time() - 42000,
    'path'     => $p['path'] ?? '/',
    'domain'   => $p['domain'] ?? '',
    'secure'   => (bool)($p['secure'] ?? (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')),
    'httponly' => (bool)($p['httponly'] ?? true),
    'samesite' => 'Lax',
  ]);
}

@session_destroy();

/* ---------------------------------------------------------
   Redirect to login with flash flag
--------------------------------------------------------- */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$dest = function_exists('url_for') ? url_for('/staff/login.php') : '/staff/login.php';
$dest = str_replace(["\r", "\n"], '', (string)$dest);
$dest .= (strpos($dest, '?') === false ? '?' : '&') . 'loggedout=1&ts=' . rawurlencode((string)time());

header('Location: ' . $dest, true, 302);
exit;
