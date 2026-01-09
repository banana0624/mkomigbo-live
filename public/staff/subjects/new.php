<?php
declare(strict_types=1);

/**
 * /public/staff/subjects/new.php
 * Staff: Create subject (schema-tolerant, CSRF, safe return, PRG flash).
 *
 * Routes:
 *   /staff/subjects/new.php?return=/staff/subjects/index.php?q=...&only_unpub=1
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
        $dir . '/app/mkomigbo/private/assets/initialize.php', // current layout
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

$init = mk_find_init(__DIR__);
if ($init) {
  require_once $init;
}

/* Ensure APP_ROOT/PRIVATE_PATH exist before any include usage */
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
   Safety helpers (fallbacks)
--------------------------------------------------------- */
if (!function_exists('h')) {
  function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
  }
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
   Auth
--------------------------------------------------------- */
if (function_exists('require_staff')) {
  require_staff();
} elseif (function_exists('require_login')) {
  require_login();
}

/* ---------------------------------------------------------
   Safe return URL helper (staff-only)
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
   Flash messages (PRG)
--------------------------------------------------------- */
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
    if (isset($_SESSION['flash']) && is_array($_SESSION['flash']) && array_key_exists($key, $_SESSION['flash'])) {
      $msg = (string)$_SESSION['flash'][$key];
      unset($_SESSION['flash'][$key]);
    }
    return $msg;
  }
}

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
      return (string)$_SESSION['csrf_token'];
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
   DB (PDO)
--------------------------------------------------------- */
try {
  $pdo = function_exists('staff_pdo') ? staff_pdo() : (function_exists('db') ? db() : null);
  if (!$pdo instanceof PDO) throw new RuntimeException('Database handle not available.');
} catch (Throwable $e) {
  $page_title = 'Staff • New Subject — Mkomigbo';
  $active_nav = 'subjects';

  $staff_header = defined('PRIVATE_PATH')
    ? (PRIVATE_PATH . '/shared/staff_header.php')
    : (defined('APP_ROOT') ? (APP_ROOT . '/private/shared/staff_header.php') : null);

  if ($staff_header && is_file($staff_header)) { require $staff_header; }

  echo '<div class="container" style="padding:24px 0;">';
  echo '<div class="notice error"><strong>DB Error:</strong> ' . h($e->getMessage()) . '</div>';
  echo '</div>';

  $staff_footer = defined('PRIVATE_PATH')
    ? (PRIVATE_PATH . '/shared/staff_footer.php')
    : (defined('APP_ROOT') ? (APP_ROOT . '/private/shared/staff_footer.php') : null);

  if ($staff_footer && is_file($staff_footer)) { require $staff_footer; }
  exit;
}

/* ---------------------------------------------------------
   Helpers
--------------------------------------------------------- */
if (!function_exists('pf__column_exists')) {
  function pf__column_exists(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $key = strtolower($table . '.' . $column);
    if (array_key_exists($key, $cache)) return (bool)$cache[$key];

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
if (!function_exists('pf__slugify')) {
  function pf__slugify(string $s): string {
    $s = trim($s);
    if ($s === '') return 'subject';
    $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    $s = preg_replace('~[^\pL\pN]+~u', '-', $s) ?? '';
    $s = trim($s, '-');
    $s = preg_replace('~-{2,}~', '-', $s) ?? '';
    return $s !== '' ? $s : 'subject';
  }
}

/* ---------------------------------------------------------
   Return path (preserve list filters if present)
--------------------------------------------------------- */
$q = trim((string)($_GET['q'] ?? ''));
$only_unpub = ((string)($_GET['only_unpub'] ?? '') === '1');

$return_params = [];
if ($q !== '') $return_params['q'] = $q;
if ($only_unpub) $return_params['only_unpub'] = '1';
$return_qs = $return_params ? ('?' . http_build_query($return_params, '', '&', PHP_QUERY_RFC3986)) : '';

$default_return = '/staff/subjects/index.php' . $return_qs;

$return_raw  = (string)($_GET['return'] ?? '');
$return_path = pf__safe_return_url($return_raw, $default_return);
$list_url    = $u($return_path);

/* ---------------------------------------------------------
   Schema detection
--------------------------------------------------------- */
$has_menu_name   = pf__column_exists($pdo, 'subjects', 'menu_name');
$has_name        = pf__column_exists($pdo, 'subjects', 'name');
$has_slug        = pf__column_exists($pdo, 'subjects', 'slug');
$has_description = pf__column_exists($pdo, 'subjects', 'description');
$has_nav_order   = pf__column_exists($pdo, 'subjects', 'nav_order');
$has_position    = pf__column_exists($pdo, 'subjects', 'position');
$has_is_public   = pf__column_exists($pdo, 'subjects', 'is_public');

$order_col = $has_nav_order ? 'nav_order' : ($has_position ? 'position' : null);

/* ---------------------------------------------------------
   Defaults
--------------------------------------------------------- */
$errors = [];
$notice = pf__flash_get('notice'); // not typical on new.php, but harmless

$menu_name = '';
$name = '';
$slug = '';
$description = '';
$is_public = 0;
$order_raw = '';

/* ---------------------------------------------------------
   POST: create
--------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  csrf_require();

  $posted_return = (string)($_POST['return'] ?? '');
  $return_path   = pf__safe_return_url($posted_return, $default_return);
  $list_url      = $u($return_path);

  if ($has_menu_name)     $menu_name = trim((string)($_POST['menu_name'] ?? ''));
  if ($has_name)          $name      = trim((string)($_POST['name'] ?? ''));
  if ($has_slug)          $slug      = trim((string)($_POST['slug'] ?? ''));
  if ($has_description)   $description = trim((string)($_POST['description'] ?? ''));
  if ($has_is_public)     $is_public = isset($_POST['is_public']) ? 1 : 0;
  if ($order_col)         $order_raw = trim((string)($_POST['nav_order'] ?? ''));

  // Validate
  if ($has_menu_name && $has_name) {
    if ($menu_name === '' && $name === '') $errors[] = 'Provide at least Menu Name or Name.';
  } elseif ($has_menu_name) {
    if ($menu_name === '') $errors[] = 'Menu Name is required.';
  } elseif ($has_name) {
    if ($name === '') $errors[] = 'Name is required.';
  } else {
    $errors[] = 'Your subjects table has no name columns (menu_name/name). Add one to create subjects.';
  }

  // Slug autogen
  if ($has_slug && $slug === '') {
    $base = ($menu_name !== '') ? $menu_name : (($name !== '') ? $name : 'subject');
    $slug = pf__slugify($base);
  }

  // Order value (blank => NULL)
  $order_val = null;
  if ($order_col && $order_raw !== '') {
    if (!preg_match('/^-?\d+$/', $order_raw)) {
      $errors[] = 'Nav order must be a whole number (or blank).';
    } else {
      $order_val = (int)$order_raw;
    }
  }

  if (!$errors) {
    try {
      $cols = [];
      $phs  = [];
      $vals = [];

      if ($has_menu_name)   { $cols[] = 'menu_name';   $phs[] = '?'; $vals[] = $menu_name; }
      if ($has_name)        { $cols[] = 'name';        $phs[] = '?'; $vals[] = $name; }
      if ($has_slug)        { $cols[] = 'slug';        $phs[] = '?'; $vals[] = $slug; }
      if ($has_description) { $cols[] = 'description'; $phs[] = '?'; $vals[] = $description; }
      if ($order_col)       { $cols[] = $order_col;    $phs[] = '?'; $vals[] = $order_val; }
      if ($has_is_public)   { $cols[] = 'is_public';   $phs[] = '?'; $vals[] = $is_public; }

      if (!$cols) throw new RuntimeException('No insertable columns detected on subjects.');

      $sql = "INSERT INTO subjects (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $phs) . ")";
      $st = $pdo->prepare($sql);
      $st->execute($vals);

      $new_id = (int)$pdo->lastInsertId();

      // Best workflow: go straight to edit page, still preserving return=
      pf__flash_set('notice', "Created subject #{$new_id} successfully.");
      redirect_to($u('/staff/subjects/edit.php?id=' . $new_id . '&return=' . rawurlencode($return_path)));

    } catch (Throwable $e) {
      $msg = $e->getMessage();
      if (stripos($msg, 'Duplicate') !== false && stripos($msg, 'slug') !== false) {
        $errors[] = 'Slug already exists. Choose a different slug.';
      } else {
        $errors[] = 'Create failed: ' . $msg;
      }
    }
  }
}

/* ---------------------------------------------------------
   Render (header opens <main>)
--------------------------------------------------------- */
$page_title = 'Staff • New Subject — Mkomigbo';
$active_nav = 'subjects';

$staff_header = defined('PRIVATE_PATH')
  ? (PRIVATE_PATH . '/shared/staff_header.php')
  : (defined('APP_ROOT') ? (APP_ROOT . '/private/shared/staff_header.php') : null);

if ($staff_header && is_file($staff_header)) {
  require $staff_header;
} else {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Staff header not found.\n";
  exit;
}
?>
<div class="container" style="padding:24px 0;">

  <section class="hero">
    <div class="hero-bar"></div>
    <div class="hero-inner">
      <h1>New Subject</h1>
      <p class="muted" style="margin:6px 0 0;">Create a new subject and control its public visibility and ordering.</p>

      <div class="actions" style="margin-top:14px;">
        <a class="btn" href="<?= h($list_url) ?>">← Back to Subjects</a>
      </div>
    </div>
  </section>

  <?php if ($notice !== ''): ?>
    <div class="notice success" style="margin-top:14px;"><strong><?= h($notice) ?></strong></div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="notice error" style="margin-top:14px;">
      <strong>Fix the following:</strong>
      <ul class="small" style="margin:8px 0 0 18px;">
        <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <section class="card form-card" style="margin-top:14px;">
    <form method="post" action="">
      <?= csrf_field() ?>
      <input type="hidden" name="return" value="<?= h($return_path) ?>">

      <?php if ($has_menu_name): ?>
        <div class="field">
          <label class="label" for="menu_name">Menu Name<?= $has_name ? ' (optional)' : '' ?></label>
          <input class="input" id="menu_name" type="text" name="menu_name" value="<?= h($menu_name) ?>">
        </div>
      <?php endif; ?>

      <?php if ($has_name || !$has_menu_name): ?>
        <div class="field">
          <label class="label" for="name">Name<?= $has_menu_name ? ' (optional)' : '' ?></label>
          <input class="input" id="name" type="text" name="name" value="<?= h($name) ?>">
        </div>
      <?php endif; ?>

      <?php if ($has_slug): ?>
        <div class="field">
          <label class="label" for="slug">Slug</label>
          <input class="input mono" id="slug" type="text" name="slug" value="<?= h($slug) ?>" placeholder="auto-generated if blank">
          <p class="muted small" style="margin:6px 0 0;">Used for public URL: /subjects/slug/</p>
        </div>
      <?php endif; ?>

      <?php if ($has_description): ?>
        <div class="field">
          <label class="label" for="description">Description</label>
          <textarea class="input" id="description" name="description" rows="6"><?= h($description) ?></textarea>
        </div>
      <?php endif; ?>

      <div class="row" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end;">
        <?php if ($order_col): ?>
          <div class="field" style="min-width:220px;flex:0 0 auto;">
            <label class="label" for="nav_order">Nav Order</label>
            <input class="input mono" id="nav_order" type="number" name="nav_order" value="<?= h((string)$order_raw) ?>" placeholder="e.g. 1" step="1">
            <p class="muted small" style="margin:6px 0 0;">Optional. Leave blank to unset.</p>
          </div>
        <?php endif; ?>

        <?php if ($has_is_public): ?>
          <div class="field" style="flex:1;min-width:220px;">
            <label style="display:flex; align-items:center; gap:10px; font-weight:700;">
              <input type="checkbox" name="is_public" value="1" <?= ((int)$is_public === 1) ? 'checked' : '' ?>>
              Public (visible on site)
            </label>
          </div>
        <?php endif; ?>
      </div>

      <div class="actions" style="margin-top:14px;">
        <button class="btn btn-primary" type="submit">Create Subject</button>
        <a class="btn" href="<?= h($list_url) ?>">Cancel</a>
      </div>
    </form>
  </section>

</div>
<?php
$staff_footer = defined('PRIVATE_PATH')
  ? (PRIVATE_PATH . '/shared/staff_footer.php')
  : (defined('APP_ROOT') ? (APP_ROOT . '/private/shared/staff_footer.php') : null);

if ($staff_footer && is_file($staff_footer)) {
  require $staff_footer;
} else {
  echo "</main></body></html>";
}
