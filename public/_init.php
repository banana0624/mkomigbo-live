<?php
declare(strict_types=1);

/**
 * /public/_init.php
 * Standard bootstrap for ALL public entrypoints (public pages + staff pages).
 *
 * Goals:
 * - Locate correct initialize.php for production layout:
 *     /public_html/app/mkomigbo/private/assets/initialize.php
 * - Tolerant optional libs
 * - Safe session start
 * - Provide stable shared include helpers WITHOUT breaking variable passing
 */

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

/* ---------------------------------------------------------
   0) Idempotency
--------------------------------------------------------- */
if (defined('MK_PUBLIC_INIT_LOADED') && MK_PUBLIC_INIT_LOADED === true) {
  return;
}
define('MK_PUBLIC_INIT_LOADED', true);

/* ---------------------------------------------------------
   0.5) Brand vs Identifier (DISPLAY vs TECH)
--------------------------------------------------------- */
if (!defined('MK_BRAND_NAME')) define('MK_BRAND_NAME', 'Mkomi Igbo'); // human-facing label

/**
 * Display metadata (OG site_name etc.)
 * Default to the brand. Override per-page if you ever want otherwise.
 */
if (!defined('MK_SITE_NAME'))  define('MK_SITE_NAME',  'Mkomi Igbo');

/* ---------------------------------------------------------
   1) Locate initialize.php (bounded upward scan)
--------------------------------------------------------- */
if (!function_exists('mk_public_find_initialize')) {
  function mk_public_find_initialize(string $startDir, int $maxDepth = 14): array {
    $searched = [];
    $dir = $startDir;

    for ($i = 0; $i <= $maxDepth; $i++) {
      $candidates = [
        // Current production layout (authoritative) — IDENTIFIER: mkomigbo
        $dir . '/app/mkomigbo/private/assets/initialize.php',

        // Legacy fallback layout
        $dir . '/private/assets/initialize.php',

        // Optional fallback if app folder differs
        $dir . '/app/private/assets/initialize.php',
      ];

      foreach ($candidates as $c) {
        $searched[] = $c;
        if (is_file($c)) {
          return [$c, $searched];
        }
      }

      $parent = dirname($dir);
      if ($parent === $dir) break;
      $dir = $parent;
    }

    return [null, $searched];
  }
}

/* Cache within the request */
static $__mk_init_path = null;
static $__mk_searched = [];

if (!is_string($__mk_init_path) || $__mk_init_path === '') {
  [$found, $searched] = mk_public_find_initialize(__DIR__, 16);
  $__mk_init_path = is_string($found) ? $found : null;
  $__mk_searched = is_array($searched) ? $searched : [];
}

if (!$__mk_init_path || !is_file($__mk_init_path)) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Init not found\n";
  echo "Start: " . __DIR__ . "\n";
  echo "Expected one of:\n";
  foreach (array_values(array_unique($__mk_searched)) as $p) {
    echo " - {$p}\n";
  }
  exit;
}

require_once $__mk_init_path;

/* ---------------------------------------------------------
   2) Ensure core constants exist (do NOT override if already set)
--------------------------------------------------------- */
if (!defined('APP_ROOT')) {
  $guess = dirname($__mk_init_path); // .../private/assets
  $guess = dirname($guess);          // .../private
  $guess = dirname($guess);          // .../app/mkomigbo
  $rp = @realpath($guess);
  if (is_string($rp) && $rp !== '' && is_dir($rp)) {
    define('APP_ROOT', $rp);
  } elseif (is_dir($guess)) {
    define('APP_ROOT', $guess);
  }
}

if (defined('APP_ROOT') && !defined('PRIVATE_PATH')) {
  define('PRIVATE_PATH', rtrim((string)APP_ROOT, '/') . '/private');
}

/**
 * PUBLIC_PATH must point to the *webroot public directory*:
 *   /public_html/public
 * NOT /public_html/app/mkomigbo/public
 *
 * Used for asset existence checks and css autodetection.
 */
if (!defined('PUBLIC_PATH')) {
  $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
  $candidate = '';

  if ($docRoot !== '') {
    $candidate = $docRoot . '/public';
    if (!is_dir($candidate)) {
      // Some hosts already set DOCUMENT_ROOT to /public_html/public
      $candidate = $docRoot;
    }
  }

  if ($candidate !== '' && is_dir($candidate)) {
    define('PUBLIC_PATH', $candidate);
  } elseif (defined('APP_ROOT')) {
    // Infer /public_html from /public_html/app/mkomigbo
    $publicHtml = dirname(dirname((string)APP_ROOT));
    $fallback = rtrim($publicHtml, '/') . '/public';
    if (is_dir($fallback)) {
      define('PUBLIC_PATH', $fallback);
    }
  }
}

/* ---------------------------------------------------------
   3) Session: start safely (best-effort)
--------------------------------------------------------- */
if (session_status() !== PHP_SESSION_ACTIVE) {
  if (!headers_sent()) {
    $https =
      (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
      || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
      || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

    @session_set_cookie_params([
      'lifetime' => 0,
      'path'     => '/',
      'secure'   => $https ? true : false,
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
  }
  @session_start();
}

/* ---------------------------------------------------------
   4) Optional libs (tolerant)
--------------------------------------------------------- */
$maybe_require = static function (?string $path): void {
  if (is_string($path) && $path !== '' && is_file($path)) {
    require_once $path;
  }
};

if (defined('PRIVATE_PATH')) {
  $private = (string)PRIVATE_PATH;

  $maybe_require($private . '/assets/core_shim.php');
  $maybe_require($private . '/functions/helpers.php');
  $maybe_require($private . '/functions/util.php');
  $maybe_require($private . '/functions/csrf.php');
  $maybe_require($private . '/functions/auth.php');
}

/* Defensive auth fallback */
if (!function_exists('mk_attempt_staff_login') && defined('APP_ROOT')) {
  $fallback = rtrim((string)APP_ROOT, '/') . '/private/functions/auth.php';
  if (is_file($fallback)) require_once $fallback;
}

/* ---------------------------------------------------------
   5) View-variable bridge + shared include helper
   Fixes: variable-scope loss when including templates via function.
--------------------------------------------------------- */
if (!function_exists('mk_view_set')) {
  /**
   * Store variables intended for templates (public_header.php, etc.).
   * Call this BEFORE mk_require_shared().
   */
  function mk_view_set(array $vars): void {
    if (!isset($GLOBALS['mk_view_vars']) || !is_array($GLOBALS['mk_view_vars'])) {
      $GLOBALS['mk_view_vars'] = [];
    }
    foreach ($vars as $k => $v) {
      if (!is_string($k) || $k === '') continue;
      $GLOBALS['mk_view_vars'][$k] = $v;
    }
  }
}

if (!function_exists('mk_require_shared')) {
  /**
   * Include a shared template from PRIVATE_PATH/shared with extracted view vars.
   * This preserves $extra_css, $page_title, etc., without requiring risky search/replace.
   */
  function mk_require_shared(string $filename): void {
    $filename = trim($filename);
    if ($filename === '') return;

    if (!defined('PRIVATE_PATH')) {
      throw new RuntimeException('PRIVATE_PATH not defined; cannot include shared template.');
    }

    $base = rtrim((string)PRIVATE_PATH, '/') . '/shared/';
    $file = $base . ltrim($filename, '/');

    if (!is_file($file)) {
      throw new RuntimeException('Shared template not found: ' . $file);
    }

    // Always provide brand to templates/nav (display only)
    $GLOBALS['mk_brand_name'] = defined('MK_BRAND_NAME') ? (string)MK_BRAND_NAME : 'Mkomi Igbo';
    $GLOBALS['mk_site_name']  = defined('MK_SITE_NAME')  ? (string)MK_SITE_NAME  : 'Mkomi Igbo';

    // Extract view variables for the template scope
    if (isset($GLOBALS['mk_view_vars']) && is_array($GLOBALS['mk_view_vars'])) {
      /** @noinspection PhpUnusedLocalVariableInspection */
      extract($GLOBALS['mk_view_vars'], EXTR_OVERWRITE);
    }

    require $file;
  }
}

/* Done */
