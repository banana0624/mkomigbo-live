<?php
declare(strict_types=1);

/**
 * /public/staff/_init.php
 *
 * Staff bootstrap (single entry include for all staff pages).
 *
 * Responsibilities:
 * - Locate and load central bootstrap initialize.php (bounded scan)
 * - Start session (auth, CSRF, flash)
 * - Enforce staff auth if guards exist
 * - Provide canonical PDO accessor: staff_pdo()
 * - Provide unified staff helpers:
 *   - staff_id()
 *   - staff_csrf_token(), staff_csrf_field(), staff_csrf_verify(), staff_csrf_require()
 *   - staff_safe_return_url()
 *   - staff_redirect()
 *   - staff_require_shared()
 *   - staff_flash_set(), staff_flash_get()
 */

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

/* ---------------------------------------------------------
   Locate initialize.php (bounded upward scan)
--------------------------------------------------------- */
if (!function_exists('mk_find_init')) {
  function mk_find_init(string $startDir, int $maxDepth = 14): ?string {
    $dir = $startDir;
    for ($i = 0; $i <= $maxDepth; $i++) {
      $candidates = [
        $dir . '/app/mkomigbo/private/assets/initialize.php', // your current layout
        $dir . '/private/assets/initialize.php',              // legacy
        $dir . '/app/private/assets/initialize.php',          // optional
      ];
      foreach ($candidates as $c) {
        if (is_file($c)) return $c;
      }
      $parent = dirname($dir);
      if ($parent === $dir) break;
      $dir = $parent;
    }
    return null;
  }
}

/* Load initialize.php if not already loaded */
$init = mk_find_init(__DIR__);
/* If not found, fail in a controlled way (plain text, 500) */
if (!$init) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Staff bootstrap failed: initialize.php not found.\n";
  echo "Start: " . __DIR__ . "\n";
  echo "Expected one of:\n";
  echo " - {dir}/app/mkomigbo/private/assets/initialize.php\n";
  echo " - {dir}/private/assets/initialize.php\n";
  echo " - {dir}/app/private/assets/initialize.php\n";
  exit;
}
require_once $init;

/* ---------------------------------------------------------
   Ensure APP_ROOT / PRIVATE_PATH are defined (defensive)
--------------------------------------------------------- */
if (!defined('APP_ROOT')) {
  $root  = realpath(__DIR__ . '/../../'); // /public/staff -> /public_html
  $guess = $root ? ($root . '/app/mkomigbo') : null;
  if ($guess && is_dir($guess)) define('APP_ROOT', $guess);
}
if (!defined('PRIVATE_PATH') && defined('APP_ROOT')) {
  define('PRIVATE_PATH', APP_ROOT . '/private');
}

/* ---------------------------------------------------------
   Session (always)
--------------------------------------------------------- */
if (session_status() !== PHP_SESSION_ACTIVE) {
  @session_start();
}

/* ---------------------------------------------------------
   Body class: always includes "staff"
--------------------------------------------------------- */
if (!isset($GLOBALS['mk_body_class']) || !is_string($GLOBALS['mk_body_class']) || trim($GLOBALS['mk_body_class']) === '') {
  $GLOBALS['mk_body_class'] = 'staff';
} else {
  $bc = trim($GLOBALS['mk_body_class']);
  if (stripos($bc, 'staff') === false) {
    $GLOBALS['mk_body_class'] = $bc . ' staff';
  }
}

/* ---------------------------------------------------------
   Minimal helper: h()
--------------------------------------------------------- */
if (!function_exists('h')) {
  function h(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
  }
}

/* ---------------------------------------------------------
   Redirect helper (staff_redirect)
--------------------------------------------------------- */
if (!function_exists('staff_redirect')) {
  function staff_redirect(string $location, int $code = 302): never {
    $location = str_replace(["\r", "\n"], '', $location);
    header('Location: ' . $location, true, $code);
    exit;
  }
}

/* Optional alias for legacy pages */
if (!function_exists('redirect_to')) {
  function redirect_to(string $location): never {
    staff_redirect($location, 302);
  }
}

/* ---------------------------------------------------------
   Shared include helper
--------------------------------------------------------- */
if (!function_exists('staff_require_shared')) {
  function staff_require_shared(string $file): void {
    $file = ltrim($file, '/');

    if (function_exists('mk_require_shared')) {
      mk_require_shared($file);
      return;
    }

    if (defined('PRIVATE_PATH')) {
      $path = rtrim((string)PRIVATE_PATH, '/') . '/shared/' . $file;
      if (is_file($path)) {
        require_once $path;
        return;
      }
    }

    if (defined('APP_ROOT')) {
      $path = rtrim((string)APP_ROOT, '/') . '/private/shared/' . $file;
      if (is_file($path)) {
        require_once $path;
        return;
      }
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Shared include failed: {$file}\n";
    exit;
  }
}

/* ---------------------------------------------------------
   Flash helpers (PRG)
--------------------------------------------------------- */
if (!function_exists('staff_flash_set')) {
  function staff_flash_set(string $key, string $msg): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) $_SESSION['flash'] = [];
    $_SESSION['flash'][$key] = $msg;
  }
}
if (!function_exists('staff_flash_get')) {
  function staff_flash_get(string $key): string {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $msg = '';
    if (isset($_SESSION['flash']) && is_array($_SESSION['flash']) && array_key_exists($key, $_SESSION['flash'])) {
      $msg = (string)$_SESSION['flash'][$key];
      unset($_SESSION['flash'][$key]);
    }
    return $msg;
  }
}

/* Back-compat aliases used by your subjects pages */
if (!function_exists('pf__flash_set')) {
  function pf__flash_set(string $key, string $msg): void { staff_flash_set($key, $msg); }
}
if (!function_exists('pf__flash_get')) {
  function pf__flash_get(string $key): string { return staff_flash_get($key); }
}

/* ---------------------------------------------------------
   Canonical PDO accessor: staff_pdo()
--------------------------------------------------------- */
if (!function_exists('staff_pdo')) {
  function staff_pdo(): ?PDO {
    static $cached = null;
    if ($cached instanceof PDO) return $cached;

    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
      return $cached = $GLOBALS['pdo'];
    }

    if (function_exists('db')) {
      try {
        $d = db();
        if ($d instanceof PDO) {
          $GLOBALS['pdo'] = $d;
          return $cached = $d;
        }
      } catch (Throwable $e) {
        // caller handles null
      }
    }

    foreach (['db', 'database', 'dbh'] as $k) {
      if (isset($GLOBALS[$k]) && $GLOBALS[$k] instanceof PDO) {
        $GLOBALS['pdo'] = $GLOBALS[$k];
        return $cached = $GLOBALS[$k];
      }
    }

    return null;
  }
}

/* Optional alias for convenience */
if (!function_exists('pdo')) {
  function pdo(): ?PDO { return staff_pdo(); }
}

/* ---------------------------------------------------------
   staff_id()
--------------------------------------------------------- */
if (!function_exists('staff_id')) {
  function staff_id(): int {
    if (isset($_SESSION['staff_user_id'])) return (int)$_SESSION['staff_user_id'];
    if (isset($_SESSION['staff']['id']))   return (int)$_SESSION['staff']['id'];
    if (isset($_SESSION['staff_id']))      return (int)$_SESSION['staff_id'];
    if (isset($_SESSION['user_id']))       return (int)$_SESSION['user_id']; // legacy compatibility
    return 0;
  }
}

/* ---------------------------------------------------------
   CSRF helpers (canonical field: csrf_token)
--------------------------------------------------------- */
if (!function_exists('staff_csrf_token')) {
  function staff_csrf_token(): string {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
  }
}
if (!function_exists('staff_csrf_field')) {
  function staff_csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(staff_csrf_token()) . '">';
  }
}
if (!function_exists('staff_csrf_verify')) {
  function staff_csrf_verify(?string $postedToken): bool {
    $postedToken = is_string($postedToken) ? trim($postedToken) : '';

    if (function_exists('csrf_token_is_valid')) {
      return (bool)csrf_token_is_valid($postedToken);
    }

    $sess = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sess) || $sess === '' || $postedToken === '') return false;
    return hash_equals($sess, $postedToken);
  }
}
if (!function_exists('staff_csrf_require')) {
  function staff_csrf_require(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;

    $token = null;
    if (isset($_POST['csrf_token'])) $token = (string)$_POST['csrf_token'];
    elseif (isset($_POST['csrf']))   $token = (string)$_POST['csrf']; // legacy

    if (!staff_csrf_verify($token)) {
      http_response_code(403);
      header('Content-Type: text/plain; charset=utf-8');
      echo "Invalid CSRF token.\n";
      exit;
    }
  }
}

/* Back-compat aliases your subjects module already uses */
if (!function_exists('csrf_token')) {
  function csrf_token(): string { return staff_csrf_token(); }
}
if (!function_exists('csrf_field')) {
  function csrf_field(): string { return staff_csrf_field(); }
}
if (!function_exists('csrf_require')) {
  function csrf_require(): void { staff_csrf_require(); }
}

/* ---------------------------------------------------------
   Safe return URL (staff-only)
--------------------------------------------------------- */
if (!function_exists('staff_safe_return_url')) {
  function staff_safe_return_url(string $raw, string $default): string {
    $raw = trim($raw);
    if ($raw === '') return $default;

    $raw = rawurldecode($raw);
    if ($raw === '' || $raw[0] !== '/') return $default;
    if (preg_match('~^//~', $raw)) return $default;
    if (preg_match('~^[a-z]+:~i', $raw)) return $default;

    if (strpos($raw, '/staff/') !== 0) return $default;
    return $raw;
  }
}

/* Back-compat alias used across your staff pages */
if (!function_exists('pf__safe_return_url')) {
  function pf__safe_return_url(string $raw, string $default): string {
    return staff_safe_return_url($raw, $default);
  }
}

/* ---------------------------------------------------------
   Auth guard (centralized) — after helpers exist
--------------------------------------------------------- */
if (function_exists('require_staff')) {
  require_staff();
} elseif (function_exists('require_login')) {
  require_login();
}

/* End of staff bootstrap */
