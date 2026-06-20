<?php
declare(strict_types=1);

/**
 * /app/mkomigbo/private/shared/tools_header.php
 * Staff Tools header wrapper (RBAC-first).
 *
 * Guarantees:
 * - Enforces RBAC staff login (NO legacy session compatibility).
 * - Enforces RBAC permission: tools.view (when permission helper exists).
 * - Ensures staff chrome + staff.css are always loaded.
 * - Provides tools subnav to staff_header.php via $staff_subnav and mk_view_set().
 *
 * Notes:
 * - Does NOT output <base>.
 * - Delegates actual HTML chrome to staff_header.php (via mk_require_shared()).
 */

if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

/* Ensure session */
if (function_exists('mk__session_start')) {
  mk__session_start();
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
  @session_start();
}

/* ---------------------------------------------------------
   View-seeded defaults (if your view store already has them)
--------------------------------------------------------- */
if (function_exists('mk_view_get')) {
  try {
    foreach (['page_title','page_desc','nav_active','active_nav','extra_css'] as $k) {
      if (!isset($$k) || $$k === null || (is_string($$k) && trim($$k) === '')) {
        $tmp = mk_view_get($k);
        if ($tmp !== null) $$k = $tmp;
      }
    }
  } catch (Throwable $e) {
    // ignore
  }
}

/* ---------------------------------------------------------
   Page vars
--------------------------------------------------------- */
$page_title = (isset($page_title) && is_string($page_title) && trim($page_title) !== '')
  ? trim($page_title)
  : 'Tools • Staff';

$page_desc  = (isset($page_desc) && is_string($page_desc) && trim($page_desc) !== '')
  ? trim($page_desc)
  : 'Diagnostics and maintenance utilities.';

$nav_active = (isset($nav_active) && is_string($nav_active) && trim($nav_active) !== '')
  ? trim($nav_active)
  : 'tools';

$active_nav = (isset($active_nav) && is_string($active_nav) && trim($active_nav) !== '')
  ? trim($active_nav)
  : $nav_active;

/* ---------------------------------------------------------
   ENFORCE RBAC STAFF LOGIN (no legacy session keys)
--------------------------------------------------------- */
$mk_is_staff = (isset($_SESSION['staff_user_id']) && (int)$_SESSION['staff_user_id'] > 0);

if (!$mk_is_staff) {
  if (function_exists('mk_require_staff_login')) {
    mk_require_staff_login();
  } else {
    $login = function_exists('url_for') ? (string)url_for('/staff/login.php') : '/staff/login.php';
    header('Location: ' . $login, true, 302);
    exit;
  }
}

/* ---------------------------------------------------------
   [ADDED] Default: enforce Tools RBAC
--------------------------------------------------------- */
if (!defined('MK_ENFORCE_TOOLS_RBAC')) { define('MK_ENFORCE_TOOLS_RBAC', true); }

/* ---------------------------------------------------------
   ENFORCE RBAC PERMISSION FOR TOOLS
--------------------------------------------------------- */
if (MK_ENFORCE_TOOLS_RBAC && function_exists('mk_require_staff_permission')) {
  try {
    mk_require_staff_permission('tools.view');
  } catch (Throwable $e) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden: tools.view required.";
    exit;
  }
}

/* ---------------------------------------------------------
   Normalize extra_css and ensure ui.css + staff.css exist
--------------------------------------------------------- */
$extra_css_in = $extra_css ?? [];
if (is_string($extra_css_in)) $extra_css_in = [$extra_css_in];
if (!is_array($extra_css_in)) $extra_css_in = [];

$norm_css = static function($p): ?string {
  if (!is_string($p)) return null;
  $p = trim($p);
  if ($p === '') return null;
  if (preg_match('~^https?://~i', $p)) return $p;
  if ($p[0] !== '/') $p = '/' . $p;
  return $p;
};

$must = [
  '/lib/css/ui.css',
  '/lib/css/staff.css?v='.filemtime('/home/mkomigbo/lib/css/staff.css'),
];

$seen = [];
$final_css = [];

foreach (array_merge($must, $extra_css_in) as $x) {
  $x = $norm_css($x);
  if (!$x) continue;
  $k = strtolower($x);
  if (isset($seen[$k])) continue;
  $seen[$k] = true;
  $final_css[] = $x;
}

$extra_css = $final_css;

/* ---------------------------------------------------------
   Body class: ensure "staff tools"
--------------------------------------------------------- */
$bc = '';
if (isset($GLOBALS['mk_body_class']) && is_string($GLOBALS['mk_body_class'])) {
  $bc = trim($GLOBALS['mk_body_class']);
}
if ($bc === '') $bc = 'staff tools';
if (stripos($bc, 'staff') === false) $bc .= ' staff';
if (stripos($bc, 'tools') === false) $bc .= ' tools';
$GLOBALS['mk_body_class'] = trim($bc);

/* ---------------------------------------------------------
   Tools subnav (contract: $staff_subnav for staff_header.php)
--------------------------------------------------------- */
$u = static function(string $path): string {
  $path = '/' . ltrim($path, '/');
  return function_exists('url_for') ? (string)url_for($path) : $path;
};

$toolsIndex = $u('/staff/tools/');
$dashboard  = $u('/staff/');

$currentTool = '';
if (isset($_GET['tool']) && is_string($_GET['tool'])) $currentTool = trim($_GET['tool']);
if ($currentTool !== '' && !preg_match('~^[a-z0-9_]{1,64}$~', $currentTool)) $currentTool = '';

$isRunner = (isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])
  && strpos($_SERVER['REQUEST_URI'], '/staff/tools/run.php') !== false);

$runHref = ($currentTool !== '')
  ? $u('/staff/tools/run.php?tool=' . rawurlencode($currentTool) . '&render=pre')
  : $toolsIndex;

if (!isset($staff_subnav) || !is_array($staff_subnav) || !$staff_subnav) {
  $staff_subnav = [
    ['label' => 'Tools Index', 'href' => $toolsIndex, 'active' => !$isRunner],
    ['label' => 'Run',        'href' => $runHref,   'active' => $isRunner],
    ['label' => 'Dashboard',  'href' => $dashboard, 'active' => false],
  ];
}

$GLOBALS['staff_subnav'] = $staff_subnav;

/* ---------------------------------------------------------
   Push into view store (preferred) + globals fallback
--------------------------------------------------------- */
if (function_exists('mk_view_set')) {
  try {
    mk_view_set([
      'page_title'   => $page_title,
      'page_desc'    => $page_desc,
      'nav_active'   => $nav_active,
      'active_nav'   => $active_nav,
      'extra_css'    => $extra_css,
      'staff_subnav' => $staff_subnav,
    ]);
  } catch (Throwable $e) {
    // ignore
  }
}

$GLOBALS['page_title'] = (string)$page_title;
$GLOBALS['page_desc']  = (string)$page_desc;
$GLOBALS['nav_active'] = (string)$nav_active;
$GLOBALS['active_nav'] = (string)$active_nav;
$GLOBALS['extra_css']  = $extra_css;

/* ---------------------------------------------------------
   Delegate to staff header
--------------------------------------------------------- */
if (function_exists('mk_require_shared')) {
  try { mk_require_shared('staff_header.php'); return; } catch (Throwable $e) {}
}

/* Hard fallback (should rarely be used) */
header('Content-Type: text/html; charset=UTF-8');
echo "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>";
echo "<title>" . h($page_title) . "</title>";
foreach ($extra_css as $href) {
  echo "<link rel='stylesheet' href='" . h($href) . "'>";
}
echo "</head><body class='" . h($GLOBALS['mk_body_class']) . "'><main class='site-main' id='main'>";
$GLOBALS['mk__main_open'] = true;
