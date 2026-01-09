<?php
declare(strict_types=1);

/**
 * /public/staff/subjects/pgs/delete.php
 * Staff: Delete a page (confirm UI + POST).
 *
 * Notes:
 * - Uses robust init locator (works on live + local)
 * - Prefers project CSRF + flash helpers when available
 * - Safe return= guard prevents open redirects
 * - Best-effort cleanup of page_files before deleting page (if table exists)
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

/* Helpers */
if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}
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

/* Flash (prefer project, fallback) */
if (!function_exists('pf__flash_set')) {
  function pf__flash_set(string $key, string $msg): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) $_SESSION['flash'] = [];
    $_SESSION['flash'][$key] = $msg;
  }
}
if (!function_exists('pf__flash_get')) {
  function pf__flash_get(string $key): string {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $msg = '';
    if (isset($_SESSION['flash'][$key])) {
      $msg = (string)$_SESSION['flash'][$key];
      unset($_SESSION['flash'][$key]);
    }
    return $msg;
  }
}

/* CSRF – prefer project csrf_require/csrf_field; fallback verify */
$csrf_mode = (function_exists('csrf_field') && function_exists('csrf_require')) ? 'project' : 'fallback';

if ($csrf_mode === 'fallback') {
  if (!function_exists('csrf_token')) {
    function csrf_token(): string {
      if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
      if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
      }
      return $_SESSION['csrf_token'];
    }
  }
  if (!function_exists('csrf_field')) {
    function csrf_field(): string {
      return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
    }
  }
  if (!function_exists('csrf_verify')) {
    function csrf_verify(?string $token): bool {
      if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
      $sess = $_SESSION['csrf_token'] ?? '';
      if (!is_string($sess) || $sess === '' || !is_string($token) || $token === '') return false;
      return hash_equals($sess, $token);
    }
  }
  if (!function_exists('csrf_require')) {
    function csrf_require(): void {
      if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
      if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Bad Request (CSRF)\n";
        exit;
      }
    }
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

/* DB */
$pdo = function_exists('db') ? db() : null;
if (!$pdo instanceof PDO) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Database handle db() not available.\n";
  exit;
}

/* Inputs */
$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
if ($id <= 0) {
  redirect_to($u('/staff/subjects/pgs/index.php'));
}

$default_return = '/staff/subjects/pgs/index.php';
$return_raw = (string)($_GET['return'] ?? ($_POST['return'] ?? ''));
$return_path = pf__safe_return_url($return_raw, $default_return);
$return_url  = $u($return_path);

/* Flash messages */
$notice = pf__flash_get('notice');
$error  = pf__flash_get('error');

/* Fetch page for confirm (schema-tolerant title) */
$has_title = pf__column_exists($pdo, 'pages', 'title');
$has_menu  = pf__column_exists($pdo, 'pages', 'menu_name');
$cols = ['id'];
if ($has_title) $cols[] = 'title';
if ($has_menu)  $cols[] = 'menu_name';

$st = $pdo->prepare("SELECT " . implode(', ', $cols) . " FROM pages WHERE id = :id LIMIT 1");
$st->execute([':id' => $id]);
$page = $st->fetch(PDO::FETCH_ASSOC) ?: null;

if (!$page) {
  pf__flash_set('error', 'Page not found.');
  redirect_to($return_url);
}

$page_title = '';
if ($has_menu && trim((string)($page['menu_name'] ?? '')) !== '') {
  $page_title = trim((string)$page['menu_name']);
} elseif ($has_title && trim((string)($page['title'] ?? '')) !== '') {
  $page_title = trim((string)$page['title']);
} else {
  $page_title = 'Page #' . (string)$id;
}

/* POST delete */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  csrf_require();

  $posted_return = (string)($_POST['return'] ?? '');
  $return_path   = pf__safe_return_url($posted_return, $default_return);
  $return_url    = $u($return_path);

  try {
    // Best-effort: delete page_files if table/column exists
    try {
      $has_page_files = false;
      $stt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'page_files'
        LIMIT 1
      ");
      $stt->execute();
      $has_page_files = ((int)$stt->fetchColumn() > 0);

      if ($has_page_files && pf__column_exists($pdo, 'page_files', 'page_id')) {
        $pdo->prepare("DELETE FROM page_files WHERE page_id = :id")->execute([':id' => $id]);
      }
    } catch (Throwable $e) {
      // ignore
    }

    $std = $pdo->prepare("DELETE FROM pages WHERE id = :id LIMIT 1");
    $std->execute([':id' => $id]);

    pf__flash_set('notice', 'Page deleted.');
    redirect_to($return_url);

  } catch (Throwable $e) {
    $msg = $e->getMessage();
    if (stripos($msg, 'foreign key') !== false || stripos($msg, 'constraint') !== false) {
      pf__flash_set('error', 'Cannot delete this page because related records exist (attachments, links, or constraints). Remove those first.');
    } else {
      pf__flash_set('error', 'Delete failed: ' . $msg);
    }
    redirect_to($u('/staff/subjects/pgs/delete.php?id=' . rawurlencode((string)$id) . '&return=' . rawurlencode($return_path)));
  }
}

/* Render header (contract: opens <main>) */
$GLOBALS['page_title'] = 'Staff • Delete Page — Mkomigbo';
$GLOBALS['active_nav'] = 'subjects';

$staff_header =
  (defined('PRIVATE_PATH') && is_file(PRIVATE_PATH . '/shared/staff_header.php'))
    ? (PRIVATE_PATH . '/shared/staff_header.php')
    : ((defined('APP_ROOT') && is_file(APP_ROOT . '/private/shared/staff_header.php'))
        ? (APP_ROOT . '/private/shared/staff_header.php')
        : null);

if ($staff_header) require_once $staff_header;

$action = $u('/staff/subjects/pgs/delete.php');

?>
<div class="container" style="padding:24px 0;">

  <section class="hero">
    <div class="hero-bar"></div>
    <div class="hero-inner">
      <h1>Delete Page</h1>
      <p class="muted" style="margin:6px 0 0;">
        You are about to delete <span class="pill">ID <?= h((string)$page['id']) ?></span>
        <span class="pill"><?= h($page_title) ?></span>
      </p>

      <div class="actions" style="margin-top:14px;">
        <a class="btn" href="<?= h($return_url) ?>">← Back</a>
      </div>
    </div>
  </section>

  <?php if ($notice !== ''): ?>
    <div class="notice success"><strong><?= h($notice) ?></strong></div>
  <?php endif; ?>

  <?php if ($error !== ''): ?>
    <div class="notice error"><strong><?= h($error) ?></strong></div>
  <?php endif; ?>

  <section class="card form-card" style="margin-top:14px;">
    <form method="post" action="<?= h($action) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= h((string)$id) ?>">
      <input type="hidden" name="return" value="<?= h($return_path) ?>">

      <p class="muted" style="margin:0 0 12px;">
        This action is permanent. If you proceed, the page will be removed.
      </p>

      <div class="actions">
        <button class="btn btn-danger" type="submit"
          onclick="return confirm('Delete this page permanently?');">Yes, delete</button>
        <a class="btn" href="<?= h($return_url) ?>">Cancel</a>
      </div>
    </form>
  </section>

</div>
<?php
$staff_footer =
  (defined('PRIVATE_PATH') && is_file(PRIVATE_PATH . '/shared/staff_footer.php'))
    ? (PRIVATE_PATH . '/shared/staff_footer.php')
    : ((defined('APP_ROOT') && is_file(APP_ROOT . '/private/shared/staff_footer.php'))
        ? (APP_ROOT . '/private/shared/staff_footer.php')
        : null);

if ($staff_footer) require_once $staff_footer;
