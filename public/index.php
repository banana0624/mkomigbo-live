<?php
declare(strict_types=1);

/**
 * public_html/public/index.php
 * ------------------------------------------------------------
 * Mkomigbo front controller (bridge router under /public)
 *
 * Responsibilities:
 * - Accept requests rewritten to /public/* and dispatch to modules.
 * - Prevent traversal / dotfile access.
 * - Provide consistent 404 handling.
 *
 * Notes:
 * - Static assets should be served by Apache directly via -f/-d checks in .htaccess.
 * - The primary site home is /index.php at the site root (public_html/index.php).
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
  http_response_code(405);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Method Not Allowed";
  exit;
}

/* ----------------------------
 * 1) Resolve request path
 * ---------------------------- */
$uri  = (string)($_SERVER['REQUEST_URI'] ?? '/');
$path = parse_url($uri, PHP_URL_PATH);
$path = is_string($path) ? $path : '/';

/**
 * Normalize by stripping a leading "/public" segment if present.
 */
if (strpos($path, '/public/') === 0) {
  $path = substr($path, 7); // remove "/public"
} elseif ($path === '/public') {
  $path = '/';
}

$path = '/' . ltrim($path, '/');
$path = preg_replace('~/{2,}~', '/', $path) ?? $path;

/* ----------------------------
 * 2) Basic security checks
 * ---------------------------- */
if (strpos($path, "\0") !== false) {
  http_response_code(400);
  exit;
}

/* Block dotfiles and traversal attempts */
if (preg_match('~(^|/)\.~', $path) || strpos($path, '..') !== false) {
  http_response_code(404);
  exit;
}

/* ----------------------------
 * 3) Module dispatch table
 * ---------------------------- */
$PUBLIC_ROOT = __DIR__;

/**
 * Map top-level routes to module dirs under /public.
 * Keep this list tight to avoid accidental exposure.
 */
$modules = [
  'subjects'       => $PUBLIC_ROOT . '/subjects',
  'contributors'   => $PUBLIC_ROOT . '/contributors',
  'platforms'      => $PUBLIC_ROOT . '/platforms',
  'staff'          => $PUBLIC_ROOT . '/staff',
  'igbo-calendar'  => $PUBLIC_ROOT . '/igbo-calendar',
];

$segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
$first = $segments[0] ?? '';

/* ----------------------------
 * 4) Home routing
 * ---------------------------- */
if ($first === '') {
  /**
   * The canonical home is the site root "/"
   * which serves public_html/index.php (via DirectoryIndex).
   */
  header('Location: /', true, 302);
  exit;
}

/* ----------------------------
 * 5) If module exists, dispatch
 * ---------------------------- */
if (isset($modules[$first])) {
  $moduleDir = $modules[$first];

  if (!is_dir($moduleDir)) {
    http_response_code(404);
    return_not_found();
  }

  $subPath = implode('/', array_slice($segments, 1));
  $subPath = trim($subPath);

  // Module root: /module/ -> module index controller
  if ($subPath === '') {
    $candidate = module_index_controller($moduleDir);
    if ($candidate !== null) {
      require $candidate;
      exit;
    }
    http_response_code(404);
    return_not_found();
  }

  // Direct explicit *.php inside module (allowed only within module)
  if (preg_match('~^[a-zA-Z0-9/_-]+\.php$~', $subPath)) {
    $candidate = realpath($moduleDir . '/' . $subPath);
    if ($candidate && strpos($candidate, realpath($moduleDir) . DIRECTORY_SEPARATOR) === 0 && is_file($candidate)) {
      require $candidate;
      exit;
    }
    http_response_code(404);
    return_not_found();
  }

  // Pretty URLs like /subjects/history/ -> module index.php handles routing
  $moduleFront = $moduleDir . '/index.php';
  if (is_file($moduleFront)) {
    $_GET['_path'] = $subPath;
    require $moduleFront;
    exit;
  }

  // Fallback: optional alternative routers
  $fallbacks = [
    $moduleDir . '/' . $first . '.php',
    $moduleDir . '/router.php',
  ];

  foreach ($fallbacks as $f) {
    if (is_file($f)) {
      $_GET['_path'] = $subPath;
      require $f;
      exit;
    }
  }

  http_response_code(404);
  return_not_found();
}

http_response_code(404);
return_not_found();
exit;


/* ============================================================
 * Helper functions
 * ============================================================ */

function module_index_controller(string $moduleDir): ?string {
  $candidates = [
    $moduleDir . '/index.php',
    $moduleDir . '/subjects.php',
    $moduleDir . '/home.php',
  ];
  foreach ($candidates as $c) {
    if (is_file($c)) return $c;
  }
  return null;
}

function return_not_found(): void {
  $root404 = dirname(__DIR__) . '/404.shtml';
  if (is_file($root404)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($root404);
    exit;
  }

  header('Content-Type: text/plain; charset=utf-8');
  echo "404 Not Found";
  exit;
}
