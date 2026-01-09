<?php
declare(strict_types=1);

/**
 * /public/staff/subjects/pgs/bulk.php
 * Staff: Bulk actions for Pages (POST-only, PDO).
 *
 * Actions:
 * - publish / unpublish (updates pages.is_public OR pages.visible)
 * - save_order          (updates pages.nav_order OR pages.position)
 * - delete              (deletes pages)  [optionally deletes page_files first if present]
 *
 * POST:
 * - csrf_token (or project CSRF)
 * - action
 * - ids[] (for publish/unpublish/delete)
 * - nav_order[ID] = value (for save_order)
 * - return
 */

/* ---------------------------------------------------------
   Locate initialize.php (bounded upward scan)
--------------------------------------------------------- */
if (!function_exists('mk_find_init')) {
  function mk_find_init(string $startDir, int $maxDepth = 14): ?string {
    $dir = $startDir;

    for ($i = 0; $i <= $maxDepth; $i++) {
      $candidates = [
        $dir . '/private/assets/initialize.php',
        $dir . '/app/mkomigbo/private/assets/initialize.php',
        $dir . '/app/private/assets/initialize.php',
      ];

      foreach ($candidates as $candidate) {
        if (is_file($candidate)) return $candidate;
      }

      $parent = dirname($dir);
      if ($parent === $dir) break;
      $dir = $parent;
    }

    return null;
  }
}

$init = mk_find_init(__DIR__);
if (!$init) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Init not found.\n";
  echo "Start: " . __DIR__ . "\n";
  exit;
}
require_once $init;

/* Auth */
if (function_exists('require_staff')) {
  require_staff();
} elseif (function_exists('require_login')) {
  require_login();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  header('Allow: POST');
  header('Content-Type: text/plain; charset=utf-8');
  echo "Method Not Allowed";
  exit;
}

/* Helpers */
if (!function_exists('redirect_to')) {
  function redirect_to(string $location): void {
    $location = str_replace(["\r", "\n"], '', $location);
    header('Location: ' . $location, true, 302);
    exit;
  }
}
$u = static function(string $path): string {
  return function_exists('url_for') ? (string)url_for($path) : $path;
};

/* Flash */
if (!function_exists('pf__flash_set')) {
  function pf__flash_set(string $key, string $msg): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) $_SESSION['flash'] = [];
    $_SESSION['flash'][$key] = $msg;
  }
}

/* Column inspector */
if (!function_exists('pf__column_exists')) {
  function pf__column_exists(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $key = strtolower($table . '.' . $column);
    if (array_key_exists($key, $cache)) return (bool)$cache[$key];

    $st = $pdo->prepare("
      SELECT COUNT(*)
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
      LIMIT 1
    ");
    $st->execute([$table, $column]);
    $cache[$key] = ((int)$st->fetchColumn() > 0);
    return (bool)$cache[$key];
  }
}

/* Safe return */
if (!function_exists('pf__safe_return_url')) {
  function pf__safe_return_url(string $raw, string $default): string {
    $raw = trim($raw);
    if ($raw === '') return $default;
    $raw = rawurldecode($raw);
    if ($raw === '' || $raw[0] !== '/') return $default;
    if (preg_match('~^//~', $raw)) return $default;
    if (preg_match('~^[a-z]+:~i', $raw)) return $default;
    if (!preg_match('~^/staff/~', $raw)) return $default;
    return $raw;
  }
}

/* CSRF – prefer project csrf_require/csrf_field; otherwise verify token */
$csrf_mode = (function_exists('csrf_require') && function_exists('csrf_field')) ? 'project' : 'fallback';

if ($csrf_mode === 'project') {
  csrf_require();
} else {
  if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
  $token = (string)($_POST['csrf_token'] ?? '');
  $sess  = (string)($_SESSION['csrf_token'] ?? '');
  if ($token === '' || $sess === '' || !hash_equals($sess, $token)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Invalid CSRF token.";
    exit;
  }
}

/* Return */
$default_return = '/staff/subjects/pgs/index.php';
$return_raw  = (string)($_REQUEST['return'] ?? '');
$return_path = pf__safe_return_url($return_raw, $default_return);
$return_url  = $u($return_path);

/* DB */
$pdo = function_exists('db') ? db() : null;
if (!$pdo instanceof PDO) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Database handle db() not available.\n";
  exit;
}

/* Detect columns */
$has_is_public = pf__column_exists($pdo, 'pages', 'is_public');
$has_visible   = pf__column_exists($pdo, 'pages', 'visible');
$pub_col       = $has_is_public ? 'is_public' : ($has_visible ? 'visible' : null);

$has_nav_order = pf__column_exists($pdo, 'pages', 'nav_order');
$has_position  = pf__column_exists($pdo, 'pages', 'position');
$order_col     = $has_nav_order ? 'nav_order' : ($has_position ? 'position' : null);

/* Inputs */
$action = (string)($_POST['action'] ?? '');

$ids = $_POST['ids'] ?? [];
if (!is_array($ids)) $ids = [];
$ids = array_values(array_filter(array_map('intval', $ids), static fn($v) => $v > 0));

/* Optional: detect page_files table for cascade delete */
$has_page_files = false;
try {
  $stt = $pdo->prepare("
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'page_files'
    LIMIT 1
  ");
  $stt->execute();
  $has_page_files = ((int)$stt->fetchColumn() > 0) && pf__column_exists($pdo, 'page_files', 'page_id');
} catch (Throwable $e) {
  $has_page_files = false;
}

try {
  if ($action === 'save_order') {
    if (!$order_col) {
      pf__flash_set('error', "No order column found (nav_order/position).");
      redirect_to($return_url);
    }

    $map = $_POST['nav_order'] ?? [];
    if (!is_array($map)) $map = [];

    $pdo->beginTransaction();
    $st = $pdo->prepare("UPDATE pages SET `{$order_col}` = :v WHERE id = :id LIMIT 1");

    $count = 0;
    foreach ($map as $id => $val) {
      $id = (int)$id;
      if ($id <= 0) continue;

      $v = trim((string)$val);
      $v = ($v === '') ? null : (int)$v;

      $st->bindValue(':id', $id, PDO::PARAM_INT);
      if ($v === null) {
        $st->bindValue(':v', null, PDO::PARAM_NULL);
      } else {
        $st->bindValue(':v', $v, PDO::PARAM_INT);
      }
      $st->execute();
      $count++;
    }

    $pdo->commit();
    pf__flash_set('notice', "Saved order for {$count} page(s).");
    redirect_to($return_url);
  }

  if ($action === 'publish' || $action === 'unpublish') {
    if (!$pub_col) {
      pf__flash_set('error', "No publish column found (is_public/visible).");
      redirect_to($return_url);
    }
    if (!$ids) {
      pf__flash_set('error', "No pages selected.");
      redirect_to($return_url);
    }

    $value = ($action === 'publish') ? 1 : 0;
    $in = implode(',', array_fill(0, count($ids), '?'));

    $st = $pdo->prepare("UPDATE pages SET `{$pub_col}` = ? WHERE id IN ({$in})");
    $st->execute(array_merge([$value], $ids));

    pf__flash_set('notice', ($value ? "Published" : "Unpublished") . " selected page(s).");
    redirect_to($return_url);
  }

  if ($action === 'delete') {
    if (!$ids) {
      pf__flash_set('error', "No pages selected.");
      redirect_to($return_url);
    }

    $pdo->beginTransaction();

    if ($has_page_files) {
      $in = implode(',', array_fill(0, count($ids), '?'));
      $pdo->prepare("DELETE FROM page_files WHERE page_id IN ({$in})")->execute($ids);
    }

    $in = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("DELETE FROM pages WHERE id IN ({$in})")->execute($ids);

    $pdo->commit();
    pf__flash_set('notice', "Deleted selected page(s).");
    redirect_to($return_url);
  }

  pf__flash_set('error', "Unknown bulk action.");
  redirect_to($return_url);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  $msg = $e->getMessage();

  if (stripos($msg, 'foreign key') !== false || stripos($msg, 'constraint') !== false) {
    pf__flash_set('error', "Bulk failed due to constraints. Remove dependent records (attachments/links) then retry.");
  } else {
    pf__flash_set('error', "Bulk failed: " . $msg);
  }
  redirect_to($return_url);
}
