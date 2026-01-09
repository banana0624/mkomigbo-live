<?php
declare(strict_types=1);

/**
 * /public/staff/subjects/edit.php
 * Staff: Edit subject
 *
 * Robust bootstrap:
 * - Always load initialize.php first (defines APP_ROOT/PRIVATE_PATH/theme/db/url_for helpers)
 * - Then load /public/staff/_init.php if present (RBAC/session/flash/etc.)
 * - Never reference PRIVATE_PATH before it is defined (PHP 8+ fatal otherwise)
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
  // If initialize.php was found via scan, APP_ROOT should exist; but keep a safe fallback.
  $root = realpath(__DIR__ . '/../../../'); // /public/staff/subjects -> /public_html
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
   Auth
--------------------------------------------------------- */
if (function_exists('require_staff')) {
  require_staff();
} elseif (function_exists('require_login')) {
  require_login();
}

/* ---------------------------------------------------------
   Safety fallbacks (only if project helpers missing)
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

/* URL helper */
$u = static function(string $path): string {
  return function_exists('url_for') ? (string)url_for($path) : $path;
};

/* ---------------------------------------------------------
   CSRF – prefer project helpers if present, otherwise fallback.
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
   DB
--------------------------------------------------------- */
try {
  $pdo = function_exists('db') ? db() : null;
  if (!$pdo instanceof PDO) {
    throw new RuntimeException('db() did not return a PDO instance.');
  }
} catch (Throwable $e) {
  $page_title = 'Staff • Edit Subject — Mkomigbo';
  $active_nav = 'subjects';

  $staff_header = (defined('PRIVATE_PATH') ? (PRIVATE_PATH . '/shared/staff_header.php') : null);
  if ($staff_header && is_file($staff_header)) { require $staff_header; }

  echo '<div class="container" style="padding:24px 0;">';
  echo '<div class="notice error"><strong>DB Error:</strong> ' . h($e->getMessage()) . '</div>';
  echo '</div>';

  $staff_footer = (defined('PRIVATE_PATH') ? (PRIVATE_PATH . '/shared/staff_footer.php') : null);
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

/* Preserve legacy filters (q / only_unpub) */
$q = trim((string)($_GET['q'] ?? ''));
$only_unpub = ((string)($_GET['only_unpub'] ?? '') === '1');

$return_params = [];
if ($q !== '') { $return_params['q'] = $q; }
if ($only_unpub) { $return_params['only_unpub'] = '1'; }
$return_qs = $return_params ? ('?' . http_build_query($return_params, '', '&', PHP_QUERY_RFC3986)) : '';

$default_list_path = '/staff/subjects/index.php' . $return_qs;

/* Safe return */
$return_raw  = (string)($_GET['return'] ?? '');
$return_path = pf__safe_return_url($return_raw, $default_list_path);
$list_url    = $u($return_path);

/* Input */
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  redirect_to($list_url);
}

/* Detect optional columns */
$has_menu_name   = pf__column_exists($pdo, 'subjects', 'menu_name');
$has_name        = pf__column_exists($pdo, 'subjects', 'name');
$has_slug        = pf__column_exists($pdo, 'subjects', 'slug');
$has_description = pf__column_exists($pdo, 'subjects', 'description');
$has_nav_order   = pf__column_exists($pdo, 'subjects', 'nav_order');
$has_position    = pf__column_exists($pdo, 'subjects', 'position');
$has_is_public   = pf__column_exists($pdo, 'subjects', 'is_public');

$order_col = $has_nav_order ? 'nav_order' : ($has_position ? 'position' : null);

/* Load subject */
$subject = null;
try {
  $cols = ['id'];
  if ($has_menu_name)   $cols[] = 'menu_name';
  if ($has_name)        $cols[] = 'name';
  if ($has_slug)        $cols[] = 'slug';
  if ($has_description) $cols[] = 'description';
  if ($order_col)       $cols[] = $order_col;
  if ($has_is_public)   $cols[] = 'is_public';

  $sql = "SELECT " . implode(', ', $cols) . " FROM subjects WHERE id = ? LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([$id]);
  $subject = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
  $subject = null;
}

if (!$subject) {
  $page_title = 'Staff • Edit Subject — Mkomigbo';
  $active_nav = 'subjects';

  $staff_header = (defined('PRIVATE_PATH') ? (PRIVATE_PATH . '/shared/staff_header.php') : null);
  if ($staff_header && is_file($staff_header)) { require $staff_header; }

  echo '<div class="container" style="padding:24px 0;">';
  echo '<div class="notice error"><strong>Not found:</strong> Subject id ' . h((string)$id) . ' does not exist.</div>';
  echo '<div class="actions"><a class="btn" href="' . h($list_url) . '">← Back to Subjects</a></div>';
  echo '</div>';

  $staff_footer = (defined('PRIVATE_PATH') ? (PRIVATE_PATH . '/shared/staff_footer.php') : null);
  if ($staff_footer && is_file($staff_footer)) { require $staff_footer; }
  exit;
}

/* Defaults */
$errors  = [];
$notice  = '';

$menu_name   = $has_menu_name ? trim((string)($subject['menu_name'] ?? '')) : '';
$name        = $has_name ? trim((string)($subject['name'] ?? '')) : '';
$slug        = $has_slug ? trim((string)($subject['slug'] ?? '')) : '';
$description = $has_description ? trim((string)($subject['description'] ?? '')) : '';
$is_public   = $has_is_public ? (int)($subject['is_public'] ?? 0) : 0;

$order_raw = ($order_col && array_key_exists($order_col, $subject)) ? trim((string)$subject[$order_col]) : '';

/* POST: update */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  csrf_require();

  $posted_return = (string)($_POST['return'] ?? '');
  $return_path   = pf__safe_return_url($posted_return, $default_list_path);
  $list_url      = $u($return_path);

  if ($has_menu_name)     $menu_name = trim((string)($_POST['menu_name'] ?? ''));
  if ($has_name)          $name      = trim((string)($_POST['name'] ?? ''));
  if ($has_slug)          $slug      = trim((string)($_POST['slug'] ?? ''));
  if ($has_description)   $description = trim((string)($_POST['description'] ?? ''));
  if ($has_is_public)     $is_public = isset($_POST['is_public']) ? 1 : 0;
  if ($order_col)         $order_raw = trim((string)($_POST['nav_order'] ?? ''));

  /* Validate */
  if ($has_menu_name && $has_name) {
    if ($menu_name === '' && $name === '') $errors[] = 'Provide at least Menu Name or Name.';
  } elseif ($has_menu_name) {
    if ($menu_name === '') $errors[] = 'Menu Name is required.';
  } else {
    if ($has_name && $name === '') $errors[] = 'Name is required.';
    if (!$has_name && !$has_menu_name) $errors[] = 'Your subjects table has no name columns.';
  }

  if ($has_slug && $slug === '') {
    $base = ($menu_name !== '') ? $menu_name : (($name !== '') ? $name : 'subject');
    $slug = pf__slugify($base);
  } elseif ($has_slug) {
    // normalize slug if user typed spaces/odd chars
    $slug = pf__slugify($slug);
  }

  $order_val = null; // NULL means "unset"
  if ($order_col) {
    if ($order_raw === '') {
      $order_val = null;
    } elseif (!preg_match('/^-?\d+$/', $order_raw)) {
      $errors[] = 'Nav order must be a whole number.';
    } else {
      $order_val = (int)$order_raw;
    }
  }

  if (!$errors) {
    try {
      $sets = [];
      $vals = [];

      if ($has_menu_name)     { $sets[] = 'menu_name = ?';   $vals[] = $menu_name; }
      if ($has_name)          { $sets[] = 'name = ?';        $vals[] = $name; }
      if ($has_slug)          { $sets[] = 'slug = ?';        $vals[] = $slug; }
      if ($has_description)   { $sets[] = 'description = ?'; $vals[] = $description; }
      if ($order_col)         { $sets[] = "{$order_col} = ?"; $vals[] = $order_val; }
      if ($has_is_public)     { $sets[] = 'is_public = ?';   $vals[] = $is_public; }

      if (!$sets) throw new RuntimeException('No editable columns detected for this subject.');

      $vals[] = $id;

      $sqlU = "UPDATE subjects SET " . implode(', ', $sets) . " WHERE id = ? LIMIT 1";
      $stU = $pdo->prepare($sqlU);
      $stU->execute($vals);

      $dest = $u('/staff/subjects/edit.php?id=' . $id . '&return=' . rawurlencode($return_path) . '&saved=1');
      redirect_to($dest);

    } catch (Throwable $e) {
      $msg = $e->getMessage();
      if (stripos($msg, 'duplicate') !== false && stripos($msg, 'slug') !== false) {
        $errors[] = 'Slug already exists. Choose a different slug.';
      } else {
        $errors[] = 'Save failed: ' . $msg;
      }
    }
  }
}

if (isset($_GET['saved']) && (string)$_GET['saved'] === '1') {
  $notice = 'Saved successfully.';
}

/* Context links (always carry return=) */
$show_href   = $u('/staff/subjects/show.php?id=' . $id . '&return=' . rawurlencode($return_path));
$pages_href  = $u('/staff/subjects/pgs/?subject_id=' . $id . '&return=' . rawurlencode($return_path));
$delete_href = $u('/staff/subjects/delete.php?id=' . $id . '&return=' . rawurlencode($return_path));

$public_href = $u('/subjects/');
if ($has_slug && $slug !== '') {
  $public_href = $u('/subjects/' . rawurlencode($slug) . '/');
}

/* ---------------------------------------------------------
   Staff header (opens <main>)
--------------------------------------------------------- */
$page_title = 'Staff • Edit Subject — Mkomigbo';
$active_nav = 'subjects';

$staff_header = (defined('PRIVATE_PATH') ? (PRIVATE_PATH . '/shared/staff_header.php') : null);
if ($staff_header && is_file($staff_header)) {
  require $staff_header;
}
?>
<div class="container" style="padding:24px 0;">

  <section class="hero">
    <div class="hero-bar"></div>
    <div class="hero-inner">
      <h1>Edit Subject</h1>
      <p class="muted" style="margin:6px 0 0;">
        Editing <span class="pill">ID <?= h((string)$id) ?></span>
        <?php if ($has_menu_name && $menu_name !== ''): ?><span class="pill"><?= h($menu_name) ?></span><?php endif; ?>
        <?php if ($has_name && $name !== ''): ?><span class="pill"><?= h($name) ?></span><?php endif; ?>
        <?php if ($has_slug && $slug !== ''): ?><span class="pill">/<?= h($slug) ?>/</span><?php endif; ?>
      </p>

      <div class="actions" style="margin-top:14px;">
        <a class="btn" href="<?= h($list_url) ?>">← Back to Subjects</a>
        <a class="btn" href="<?= h($show_href) ?>">Details</a>
        <a class="btn" href="<?= h($pages_href) ?>">Pages</a>
        <a class="btn" href="<?= h($public_href) ?>">View Public</a>
        <a class="btn btn-danger" href="<?= h($delete_href) ?>">Delete Subject</a>
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
        <button class="btn btn-primary" type="submit">Save Changes</button>
        <a class="btn" href="<?= h($list_url) ?>">Cancel</a>
      </div>
    </form>
  </section>

</div>
<?php
$staff_footer = (defined('PRIVATE_PATH') ? (PRIVATE_PATH . '/shared/staff_footer.php') : null);
if ($staff_footer && is_file($staff_footer)) {
  require $staff_footer;
} else {
  echo "</main></body></html>";
}
