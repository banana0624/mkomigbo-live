<?php
declare(strict_types=1);

/**
 * /public/staff/subjects/delete.php
 * Staff: Delete a subject (confirmation + POST)
 *
 * Goals:
 * - Robust init locator for your current layout:
 *     /app/mkomigbo/private/assets/initialize.php
 * - Uses staff_header.php contract (it opens <main> already)
 * - Safe return= handling to prevent open redirects
 * - FK-safe messaging (if subject has pages, deletion may be blocked)
 */

/* ---------------------------------------------------------
   Locate initialize.php (bounded upward scan, supports your layout)
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
  echo "Expected one of:\n";
  echo " - {dir}/private/assets/initialize.php\n";
  echo " - {dir}/app/mkomigbo/private/assets/initialize.php\n";
  echo " - {dir}/app/private/assets/initialize.php\n";
  exit;
}
require_once $init;

/* ---------------------------------------------------------
   Auth
--------------------------------------------------------- */
if (function_exists('require_staff')) {
  require_staff();
} elseif (function_exists('require_login')) {
  require_login();
}

/* ---------------------------------------------------------
   Helpers (fallbacks only)
--------------------------------------------------------- */
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

/* ---------------------------------------------------------
   CSRF – prefer project helpers; fallback if missing
--------------------------------------------------------- */
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
  if (!function_exists('csrf_require')) {
    function csrf_require(): void {
      if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
      if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
      $sent = $_POST['csrf_token'] ?? '';
      $sess = $_SESSION['csrf_token'] ?? '';
      $ok = is_string($sent) && is_string($sess) && $sent !== '' && hash_equals($sess, $sent);
      if (!$ok) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Bad Request (CSRF)\n";
        exit;
      }
    }
  }
}

/* ---------------------------------------------------------
   Safe return= to avoid open redirects
--------------------------------------------------------- */
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

/* ---------------------------------------------------------
   DB
--------------------------------------------------------- */
try {
  $pdo = function_exists('db') ? db() : null;
  if (!$pdo instanceof PDO) {
    throw new RuntimeException('db() did not return a PDO instance.');
  }
} catch (Throwable $e) {
  $GLOBALS['page_title'] = 'Staff • Delete Subject — Mkomigbo';
  $GLOBALS['active_nav'] = 'subjects';

  $staff_header =
    (defined('PRIVATE_PATH') && is_file(PRIVATE_PATH . '/shared/staff_header.php'))
      ? (PRIVATE_PATH . '/shared/staff_header.php')
      : ((defined('APP_ROOT') && is_file(APP_ROOT . '/private/shared/staff_header.php'))
          ? (APP_ROOT . '/private/shared/staff_header.php')
          : null);

  if ($staff_header) require_once $staff_header;

  echo '<div class="container" style="padding:24px 0;">';
  echo '<div class="notice error"><strong>DB Error:</strong> ' . h($e->getMessage()) . '</div>';
  echo '</div>';

  $staff_footer =
    (defined('PRIVATE_PATH') && is_file(PRIVATE_PATH . '/shared/staff_footer.php'))
      ? (PRIVATE_PATH . '/shared/staff_footer.php')
      : ((defined('APP_ROOT') && is_file(APP_ROOT . '/private/shared/staff_footer.php'))
          ? (APP_ROOT . '/private/shared/staff_footer.php')
          : null);

  if ($staff_footer) require_once $staff_footer;
  exit;
}

/* Column inspector (schema-tolerant) */
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

/* ---------------------------------------------------------
   Inputs
--------------------------------------------------------- */
$id = (int)($_GET['id'] ?? 0);

$q = trim((string)($_GET['q'] ?? ''));
$only_unpub = ((string)($_GET['only_unpub'] ?? '') === '1');

$return_params = [];
if ($q !== '') $return_params['q'] = $q;
if ($only_unpub) $return_params['only_unpub'] = '1';
$return_qs = $return_params ? ('?' . http_build_query($return_params, '', '&', PHP_QUERY_RFC3986)) : '';

$default_list_path = '/staff/subjects/index.php' . $return_qs;

$return_raw  = (string)($_GET['return'] ?? '');
$return_path = pf__safe_return_url($return_raw, $default_list_path);
$list_url    = $u($return_path);

if ($id <= 0) {
  redirect_to($list_url);
}

/* ---------------------------------------------------------
   Load subject
--------------------------------------------------------- */
$has_menu_name = pf__column_exists($pdo, 'subjects', 'menu_name');
$has_name      = pf__column_exists($pdo, 'subjects', 'name');
$has_slug      = pf__column_exists($pdo, 'subjects', 'slug');

$cols = ['id'];
if ($has_menu_name) $cols[] = 'menu_name';
if ($has_name)      $cols[] = 'name';
if ($has_slug)      $cols[] = 'slug';

$st = $pdo->prepare("SELECT " . implode(', ', $cols) . " FROM subjects WHERE id = ? LIMIT 1");
$st->execute([$id]);
$subject = $st->fetch(PDO::FETCH_ASSOC) ?: null;

if (!$subject) {
  $GLOBALS['page_title'] = 'Staff • Delete Subject — Mkomigbo';
  $GLOBALS['active_nav'] = 'subjects';

  $staff_header =
    (defined('PRIVATE_PATH') && is_file(PRIVATE_PATH . '/shared/staff_header.php'))
      ? (PRIVATE_PATH . '/shared/staff_header.php')
      : ((defined('APP_ROOT') && is_file(APP_ROOT . '/private/shared/staff_header.php'))
          ? (APP_ROOT . '/private/shared/staff_header.php')
          : null);

  if ($staff_header) require_once $staff_header;

  echo '<div class="container" style="padding:24px 0;">';
  echo '<div class="notice error"><strong>Not found:</strong> Subject does not exist.</div>';
  echo '<div class="actions"><a class="btn" href="' . h($list_url) . '">← Back to Subjects</a></div>';
  echo '</div>';

  $staff_footer =
    (defined('PRIVATE_PATH') && is_file(PRIVATE_PATH . '/shared/staff_footer.php'))
      ? (PRIVATE_PATH . '/shared/staff_footer.php')
      : ((defined('APP_ROOT') && is_file(APP_ROOT . '/private/shared/staff_footer.php'))
          ? (APP_ROOT . '/private/shared/staff_footer.php')
          : null);

  if ($staff_footer) require_once $staff_footer;
  exit;
}

$subject_id = (int)$subject['id'];

$subject_title = '';
if ($has_menu_name && trim((string)($subject['menu_name'] ?? '')) !== '') {
  $subject_title = trim((string)$subject['menu_name']);
} elseif ($has_name && trim((string)($subject['name'] ?? '')) !== '') {
  $subject_title = trim((string)$subject['name']);
} else {
  $subject_title = "Subject #{$subject_id}";
}

$subject_slug = $has_slug ? trim((string)($subject['slug'] ?? '')) : '';

/* If pages exist, deletion may be blocked (FK). Pre-check count. */
$pages_count = 0;
try {
  $stc = $pdo->prepare("SELECT COUNT(*) FROM pages WHERE subject_id = ?");
  $stc->execute([$subject_id]);
  $pages_count = (int)$stc->fetchColumn();
} catch (Throwable $e) {
  $pages_count = 0;
}

/* ---------------------------------------------------------
   POST: delete
--------------------------------------------------------- */
$errors = [];
$notice = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  csrf_require();

  $posted_return = (string)($_POST['return'] ?? '');
  $return_path   = pf__safe_return_url($posted_return, $default_list_path);
  $list_url      = $u($return_path);

  try {
    $std = $pdo->prepare("DELETE FROM subjects WHERE id = ? LIMIT 1");
    $std->execute([$subject_id]);

    redirect_to($list_url);

  } catch (Throwable $e) {
    $msg = $e->getMessage();

    if (stripos($msg, 'foreign key') !== false || stripos($msg, 'constraint') !== false) {
      $errors[] = 'Cannot delete this subject because related records exist (likely pages). Delete or reassign those pages first.';
    } else {
      $errors[] = 'Delete failed: ' . $msg;
    }
  }
}

/* ---------------------------------------------------------
   Render
--------------------------------------------------------- */
$GLOBALS['page_title'] = 'Staff • Delete Subject — Mkomigbo';
$GLOBALS['active_nav'] = 'subjects';

$staff_header =
  (defined('PRIVATE_PATH') && is_file(PRIVATE_PATH . '/shared/staff_header.php'))
    ? (PRIVATE_PATH . '/shared/staff_header.php')
    : ((defined('APP_ROOT') && is_file(APP_ROOT . '/private/shared/staff_header.php'))
        ? (APP_ROOT . '/private/shared/staff_header.php')
        : null);

if ($staff_header) require_once $staff_header;

$pages_url = $u('/staff/subjects/pgs/?subject_id=' . rawurlencode((string)$subject_id) . '&return=' . rawurlencode($return_path));
$show_url  = $u('/staff/subjects/show.php?id=' . rawurlencode((string)$subject_id) . '&return=' . rawurlencode($return_path));

?>
<div class="container" style="padding:24px 0;">

  <section class="hero">
    <div class="hero-bar"></div>
    <div class="hero-inner">
      <h1>Delete Subject</h1>
      <p class="muted" style="margin:6px 0 0;">
        You are about to delete <span class="pill">ID <?= h((string)$subject_id) ?></span>
        <span class="pill"><?= h($subject_title) ?></span>
        <?php if ($subject_slug !== ''): ?><span class="pill"><?= h($subject_slug) ?></span><?php endif; ?>
      </p>

      <div class="actions" style="margin-top:14px;">
        <a class="btn" href="<?= h($list_url) ?>">← Back to Subjects</a>
        <a class="btn" href="<?= h($show_url) ?>">Details</a>
        <a class="btn" href="<?= h($pages_url) ?>">Pages</a>
      </div>
    </div>
  </section>

  <?php if ($notice !== ''): ?>
    <div class="notice success"><strong><?= h($notice) ?></strong></div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="notice error">
      <strong>Cannot delete:</strong>
      <ul class="small" style="margin:8px 0 0 18px;">
        <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <section class="card form-card" style="margin-top:14px;">
    <?php if ($pages_count > 0): ?>
      <div class="notice" style="margin-bottom:12px;">
        <strong>Warning:</strong> This subject currently has <span class="pill"><?= h((string)$pages_count) ?></span> page(s).
        Deletion may be blocked by database constraints.
        <div class="actions" style="margin-top:10px;">
          <a class="btn" href="<?= h($pages_url) ?>">Manage Pages for this Subject</a>
        </div>
      </div>
    <?php endif; ?>

    <form method="post" action="">
      <?= csrf_field() ?>
      <input type="hidden" name="return" value="<?= h($return_path) ?>">

      <p class="muted" style="margin:0 0 12px;">
        This action is permanent. If you proceed, the subject will be removed.
      </p>

      <div class="actions">
        <button class="btn btn-danger" type="submit"
          onclick="return confirm('Delete this subject permanently?');">Yes, delete</button>
        <a class="btn" href="<?= h($list_url) ?>">Cancel</a>
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
