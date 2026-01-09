<?php
declare(strict_types=1);

/**
 * /public/staff/subjects/bulk.php
 * Staff: Bulk actions for Subjects (POST-only, PDO).
 *
 * Supported actions:
 * - publish        (sets is_public=1 OR visible=1 if available)
 * - unpublish      (sets is_public=0 OR visible=0 if available)
 * - delete         (deletes selected subjects; may be blocked by FK constraints)
 * - save_order     (updates nav_order OR position from nav_order[ID] inputs)
 *
 * Expected POST:
 * - csrf_token
 * - action: publish|unpublish|delete|save_order
 * - ids[] (for publish/unpublish/delete)
 * - nav_order[ID] = value (for save_order)
 * - return (optional, staff-only safe)
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
      $cand = $dir . '/app/mkomigbo/private/assets/initialize.php';
      if (is_file($cand)) return $cand;

      // fallback layout (older): /private/assets/initialize.php
      $cand2 = $dir . '/private/assets/initialize.php';
      if (is_file($cand2)) return $cand2;

      $parent = dirname($dir);
      if ($parent === $dir) break;
      $dir = $parent;
    }
    return null;
  }
}

$init = mk_find_init(__DIR__);
if ($init) {
  require_once $init;
}

/* Ensure APP_ROOT/PRIVATE_PATH are available for includes */
if (!defined('APP_ROOT')) {
  $root  = realpath(__DIR__ . '/../../../'); // /public/staff/subjects -> /public_html
  $guess = $root ? ($root . '/app/mkomigbo') : null;
  if ($guess && is_dir($guess)) define('APP_ROOT', $guess);
}
if (!defined('PRIVATE_PATH') && defined('APP_ROOT')) {
  define('PRIVATE_PATH', APP_ROOT . '/private');
}

/* Preferred staff bootstrap */
$staffInit = __DIR__ . '/../_init.php'; // /public/staff/_init.php
if (is_file($staffInit)) {
  require_once $staffInit;
}

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

/* ---------------------------------------------------------
   POST-only
--------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  header('Allow: POST');
  header('Content-Type: text/plain; charset=utf-8');
  echo "Method Not Allowed";
  exit;
}

/* ---------------------------------------------------------
   Auth (belt + suspenders)
--------------------------------------------------------- */
if (function_exists('require_staff')) {
  require_staff();
} elseif (function_exists('require_login')) {
  require_login();
}

/* ---------------------------------------------------------
   Safety helpers (fallbacks)
--------------------------------------------------------- */
if (!function_exists('redirect_to')) {
  function redirect_to(string $location): void {
    $location = str_replace(["\r", "\n"], '', $location);
    header('Location: ' . $location, true, 302);
    exit;
  }
}

/* URL helper */
$u = static function(string $path): string {
  return function_exists('url_for') ? (string)url_for($path) : $path;
};

/* Safe return URL helper (staff-only) */
if (!function_exists('pf__safe_return_url')) {
  function pf__safe_return_url(string $raw, string $default): string {
    $raw = trim($raw);
    if ($raw === '') { return $default; }
    $raw = rawurldecode($raw);

    if ($raw === '' || $raw[0] !== '/') { return $default; }
    if (preg_match('~^//~', $raw)) { return $default; }
    if (preg_match('~^[a-z]+:~i', $raw)) { return $default; }
    if (!preg_match('~^/staff/~', $raw)) { return $default; }

    return $raw;
  }
}

/* Flash messages (PRG) */
if (!function_exists('pf__flash_set')) {
  function pf__flash_set(string $key, string $msg): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
      $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][$key] = $msg;
  }
}

/* Column inspector */
if (!function_exists('pf__column_exists')) {
  function pf__column_exists(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $key = strtolower($table . '.' . $column);
    if (array_key_exists($key, $cache)) { return (bool)$cache[$key]; }

    $sql = "SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([$table, $column]);
    $cache[$key] = ((int)$st->fetchColumn() > 0);
    return (bool)$cache[$key];
  }
}

/* ---------------------------------------------------------
   Return URL (staff-only)
--------------------------------------------------------- */
$default_return = '/staff/subjects/index.php';
$return_raw  = (string)($_REQUEST['return'] ?? '');
$return_path = pf__safe_return_url($return_raw, $default_return);
$return_url  = $u($return_path);

/* ---------------------------------------------------------
   CSRF validation
--------------------------------------------------------- */
if (function_exists('csrf_require')) {
  csrf_require();
} else {
  if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
  $sent = (string)($_POST['csrf_token'] ?? '');
  $sess = (string)($_SESSION['csrf_token'] ?? '');

  if ($sent === '' || $sess === '' || !hash_equals($sess, $sent)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Invalid CSRF token.";
    exit;
  }
}

/* ---------------------------------------------------------
   DB (PDO)
--------------------------------------------------------- */
try {
  $pdo = function_exists('staff_pdo') ? staff_pdo() : (function_exists('db') ? db() : null);
  if (!$pdo instanceof PDO) {
    throw new RuntimeException('Database handle not available.');
  }
} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Database handle not available.\n";
  exit;
}

/* ---------------------------------------------------------
   Inputs
--------------------------------------------------------- */
$action = trim((string)($_POST['action'] ?? ''));

$allowed = ['publish', 'unpublish', 'delete', 'save_order'];
if (!in_array($action, $allowed, true)) {
  pf__flash_set('error', "Unknown bulk action.");
  redirect_to($return_url);
}

/* ids[] for publish/unpublish/delete */
$ids = $_POST['ids'] ?? [];
if (!is_array($ids)) { $ids = []; }
$ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($v) => $v > 0)));

/* Schema detection */
$has_is_public = pf__column_exists($pdo, 'subjects', 'is_public');
$has_visible   = pf__column_exists($pdo, 'subjects', 'visible');
$pub_col       = $has_is_public ? 'is_public' : ($has_visible ? 'visible' : null);

$has_nav_order = pf__column_exists($pdo, 'subjects', 'nav_order');
$has_position  = pf__column_exists($pdo, 'subjects', 'position');
$order_col     = $has_nav_order ? 'nav_order' : ($has_position ? 'position' : null);

/* ---------------------------------------------------------
   Execute actions
--------------------------------------------------------- */
try {

  /* Save ordering (nav_order[ID] => value). Updates all rows present in POST map. */
  if ($action === 'save_order') {
    if (!$order_col) {
      pf__flash_set('error', "No order column found (nav_order/position).");
      redirect_to($return_url);
    }

    $map = $_POST['nav_order'] ?? [];
    if (!is_array($map)) { $map = []; }

    $pdo->beginTransaction();

    // Note: $order_col comes from our schema allow-list only (nav_order/position).
    $sqlU = "UPDATE subjects SET `{$order_col}` = :v WHERE id = :id LIMIT 1";
    $st = $pdo->prepare($sqlU);

    $count = 0;
    foreach ($map as $id => $val) {
      $id = (int)$id;
      if ($id <= 0) continue;

      $raw = trim((string)$val);

      // Blank => NULL (unset)
      if ($raw === '') {
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->bindValue(':v', null, PDO::PARAM_NULL);
        $st->execute();
        $count++;
        continue;
      }

      // Enforce whole number
      if (!preg_match('/^-?\d+$/', $raw)) {
        // Reject the whole bulk update if any invalid order value is posted
        throw new RuntimeException("Invalid order value for ID {$id}. Use whole numbers or leave blank.");
      }

      $v = (int)$raw;
      $st->bindValue(':id', $id, PDO::PARAM_INT);
      $st->bindValue(':v', $v, PDO::PARAM_INT);
      $st->execute();
      $count++;
    }

    $pdo->commit();
    pf__flash_set('notice', "Saved order for {$count} subject(s).");
    redirect_to($return_url);
  }

  /* Publish / unpublish */
  if ($action === 'publish' || $action === 'unpublish') {
    if (!$pub_col) {
      pf__flash_set('error', "No publish column found (is_public/visible).");
      redirect_to($return_url);
    }
    if (!$ids) {
      pf__flash_set('error', "No subjects selected.");
      redirect_to($return_url);
    }

    $value = ($action === 'publish') ? 1 : 0;

    $in = implode(',', array_fill(0, count($ids), '?'));
    $sqlU = "UPDATE subjects SET `{$pub_col}` = ? WHERE id IN ({$in})";
    $st = $pdo->prepare($sqlU);
    $st->execute(array_merge([$value], $ids));

    pf__flash_set('notice', ($value ? "Published" : "Unpublished") . " selected subject(s).");
    redirect_to($return_url);
  }

  /* Delete */
  if ($action === 'delete') {
    if (!$ids) {
      pf__flash_set('error', "No subjects selected.");
      redirect_to($return_url);
    }

    $in = implode(',', array_fill(0, count($ids), '?'));
    $sqlD = "DELETE FROM subjects WHERE id IN ({$in})";

    try {
      $st = $pdo->prepare($sqlD);
      $st->execute($ids);

      pf__flash_set('notice', "Deleted selected subject(s).");
      redirect_to($return_url);

    } catch (Throwable $e) {
      $msg = $e->getMessage();
      if (stripos($msg, 'foreign key') !== false || stripos($msg, 'constraint') !== false) {
        pf__flash_set(
          'error',
          "Cannot delete one or more subjects because related records exist (likely pages). Delete or reassign those pages first."
        );
      } else {
        pf__flash_set('error', "Delete failed: " . $msg);
      }
      redirect_to($return_url);
    }
  }

  // Should never reach here because of allow-list.
  pf__flash_set('error', "Unknown bulk action.");
  redirect_to($return_url);

} catch (Throwable $e) {
  if ($pdo instanceof PDO && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  pf__flash_set('error', "Bulk failed: " . $e->getMessage());
  redirect_to($return_url);
}
