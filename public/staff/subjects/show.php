<?php
declare(strict_types=1);

/**
 * /public/staff/subjects/show.php
 * Staff: Show one subject + list its pages
 *
 * Contract:
 * - staff_header.php is include-based and opens <main>.
 * - This page MUST NOT open another <main>.
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

/* Preferred staff bootstrap (if present) */
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
   Safety helpers (fallbacks)
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

/* URL helper */
$u = static function(string $path): string {
  return function_exists('url_for') ? (string)url_for($path) : $path;
};

/* Safe staff-only return URL */
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
   Return path: prefer explicit return=, otherwise preserve q/only_unpub
--------------------------------------------------------- */
$q = trim((string)($_GET['q'] ?? ''));
$only_unpub = ((string)($_GET['only_unpub'] ?? '') === '1');

$return_params = [];
if ($q !== '') $return_params['q'] = $q;
if ($only_unpub) $return_params['only_unpub'] = '1';
$return_qs = $return_params ? ('?' . http_build_query($return_params, '', '&', PHP_QUERY_RFC3986)) : '';

$default_list_path = '/staff/subjects/index.php' . $return_qs;

$return_raw  = (string)($_GET['return'] ?? '');
$return_path = pf__safe_return_url($return_raw, $default_list_path);
$list_href   = $u($return_path);

$return_qs_amp = (str_contains($return_path, '?') ? '&' : '?') . 'return=' . rawurlencode($return_path);

/* ---------------------------------------------------------
   Input
--------------------------------------------------------- */
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  redirect_to($list_href);
}

/* ---------------------------------------------------------
   DB (PDO)
--------------------------------------------------------- */
try {
  $pdo = function_exists('staff_pdo') ? staff_pdo() : (function_exists('db') ? db() : null);
  if (!$pdo instanceof PDO) throw new RuntimeException('Database handle not available.');
} catch (Throwable $e) {
  $page_title = 'Staff • Subject — Mkomigbo';
  $active_nav = 'subjects';

  $staff_header = defined('PRIVATE_PATH')
    ? (PRIVATE_PATH . '/shared/staff_header.php')
    : (defined('APP_ROOT') ? (APP_ROOT . '/private/shared/staff_header.php') : null);

  if ($staff_header && is_file($staff_header)) { require $staff_header; }

  echo '<div class="container" style="padding:24px 0;">';
  echo '<div class="notice error"><strong>DB Error:</strong> ' . h($e->getMessage()) . '</div>';
  echo '<div class="actions" style="margin-top:12px;"><a class="btn" href="' . h($list_href) . '">← Back to Subjects</a></div>';
  echo '</div>';

  $staff_footer = defined('PRIVATE_PATH')
    ? (PRIVATE_PATH . '/shared/staff_footer.php')
    : (defined('APP_ROOT') ? (APP_ROOT . '/private/shared/staff_footer.php') : null);

  if ($staff_footer && is_file($staff_footer)) { require $staff_footer; }
  exit;
}

/* ---------------------------------------------------------
   Schema flags
--------------------------------------------------------- */
$has_subject_menu_name   = pf__column_exists($pdo, 'subjects', 'menu_name');
$has_subject_name        = pf__column_exists($pdo, 'subjects', 'name');
$has_subject_slug        = pf__column_exists($pdo, 'subjects', 'slug');
$has_subject_description = pf__column_exists($pdo, 'subjects', 'description');
$has_subject_is_public   = pf__column_exists($pdo, 'subjects', 'is_public');
$has_subject_visible     = pf__column_exists($pdo, 'subjects', 'visible'); // optional legacy

$pub_col = $has_subject_is_public ? 'is_public' : ($has_subject_visible ? 'visible' : null);

$has_page_title          = pf__column_exists($pdo, 'pages', 'title');
$has_page_menu_name      = pf__column_exists($pdo, 'pages', 'menu_name');
$has_page_slug           = pf__column_exists($pdo, 'pages', 'slug');
$has_page_nav_order      = pf__column_exists($pdo, 'pages', 'nav_order');
$has_page_position       = pf__column_exists($pdo, 'pages', 'position');
$has_page_is_public      = pf__column_exists($pdo, 'pages', 'is_public');

/* ---------------------------------------------------------
   Load subject
--------------------------------------------------------- */
$subject = null;
try {
  $cols = ['id'];
  if ($has_subject_menu_name)   $cols[] = 'menu_name';
  if ($has_subject_name)        $cols[] = 'name';
  if ($has_subject_slug)        $cols[] = 'slug';
  if ($has_subject_description) $cols[] = 'description';
  if ($pub_col)                 $cols[] = $pub_col;

  $st = $pdo->prepare("SELECT " . implode(', ', $cols) . " FROM subjects WHERE id = ? LIMIT 1");
  $st->execute([$id]);
  $subject = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
  $subject = null;
}

if (!$subject) {
  $page_title = 'Staff • Subject — Mkomigbo';
  $active_nav = 'subjects';

  $staff_header = defined('PRIVATE_PATH')
    ? (PRIVATE_PATH . '/shared/staff_header.php')
    : (defined('APP_ROOT') ? (APP_ROOT . '/private/shared/staff_header.php') : null);

  if ($staff_header && is_file($staff_header)) { require $staff_header; }

  echo '<div class="container" style="padding:24px 0;">';
  echo '<div class="notice error"><strong>Not found:</strong> Subject id ' . h((string)$id) . ' does not exist.</div>';
  echo '<div class="actions" style="margin-top:12px;"><a class="btn" href="' . h($list_href) . '">← Back to Subjects</a></div>';
  echo '</div>';

  $staff_footer = defined('PRIVATE_PATH')
    ? (PRIVATE_PATH . '/shared/staff_footer.php')
    : (defined('APP_ROOT') ? (APP_ROOT . '/private/shared/staff_footer.php') : null);

  if ($staff_footer && is_file($staff_footer)) { require $staff_footer; }
  exit;
}

$subject_id = (int)$subject['id'];

$subject_name =
  ($has_subject_menu_name && trim((string)($subject['menu_name'] ?? '')) !== '') ? trim((string)$subject['menu_name']) :
  (($has_subject_name && trim((string)($subject['name'] ?? '')) !== '') ? trim((string)$subject['name']) : ('Subject #' . $subject_id));

$subject_slug = $has_subject_slug ? trim((string)($subject['slug'] ?? '')) : '';
$subject_desc = $has_subject_description ? trim((string)($subject['description'] ?? '')) : '';

$subject_pub = 0;
if ($pub_col && array_key_exists($pub_col, $subject)) {
  $subject_pub = (int)($subject[$pub_col] ?? 0);
}

/* ---------------------------------------------------------
   Load pages under subject
--------------------------------------------------------- */
$pages = [];
$db_error = null;

try {
  $pcols = ['id', 'subject_id'];
  if ($has_page_title)     $pcols[] = 'title';
  if ($has_page_menu_name) $pcols[] = 'menu_name';
  if ($has_page_slug)      $pcols[] = 'slug';
  if ($has_page_nav_order) $pcols[] = 'nav_order';
  if ($has_page_position)  $pcols[] = 'position';
  if ($has_page_is_public) $pcols[] = 'is_public';

  $order_col = $has_page_nav_order ? 'nav_order' : ($has_page_position ? 'position' : 'id');

  $st = $pdo->prepare(
    "SELECT " . implode(', ', $pcols) . "
     FROM pages
     WHERE subject_id = ?
     ORDER BY {$order_col} IS NULL, {$order_col} ASC, id ASC"
  );
  $st->execute([$subject_id]);
  $pages = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $db_error = $e->getMessage();
  $pages = [];
}

/* ---------------------------------------------------------
   URLs
--------------------------------------------------------- */
$edit_href     = $u('/staff/subjects/edit.php?id=' . $subject_id) . '&return=' . rawurlencode($return_path);
$delete_href   = $u('/staff/subjects/delete.php?id=' . $subject_id) . '&return=' . rawurlencode($return_path);
$pages_href    = $u('/staff/subjects/pgs/?subject_id=' . $subject_id) . '&return=' . rawurlencode($return_path);
$new_page_href = $u('/staff/subjects/pgs/new.php?subject_id=' . $subject_id) . '&return=' . rawurlencode($return_path);

$public_subject_href = $u('/subjects/');
if ($subject_slug !== '') {
  $public_subject_href = $u('/subjects/' . rawurlencode($subject_slug) . '/');
}

/* ---------------------------------------------------------
   Header include (contract)
--------------------------------------------------------- */
$page_title = 'Staff • Subject — Mkomigbo';
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
      <h1>Subject</h1>

      <p class="muted" style="margin:6px 0 0;">
        <span class="pill">ID <?= h((string)$subject_id) ?></span>
        <?php if ($subject_slug !== ''): ?><span class="pill"><?= h($subject_slug) ?></span><?php endif; ?>
        <?php if ($pub_col): ?>
          <span class="pill"><?= ($subject_pub === 1) ? 'Public: Yes' : 'Public: No' ?></span>
        <?php endif; ?>
      </p>

      <div class="actions" style="margin-top:14px;">
        <a class="btn" href="<?= h($list_href) ?>">← Back to Subjects</a>
        <a class="btn" href="<?= h($edit_href) ?>">Edit Subject</a>
        <a class="btn btn-danger" href="<?= h($delete_href) ?>">Delete Subject</a>
        <a class="btn" href="<?= h($public_subject_href) ?>">View Public</a>
      </div>

      <div class="actions" style="margin-top:10px;">
        <a class="btn btn-primary" href="<?= h($new_page_href) ?>">+ New Page in this Subject</a>
        <a class="btn" href="<?= h($pages_href) ?>">Manage Pages →</a>
        <a class="btn" href="<?= h($u('/staff/')) ?>">Dashboard</a>
      </div>
    </div>
  </section>

  <section class="card form-card" style="margin-top:14px;">
    <h2 style="margin:0 0 6px;font-size:1.15rem;"><?= h($subject_name) ?></h2>
    <?php if ($subject_desc !== ''): ?>
      <p class="muted" style="margin:0;max-width:90ch;"><?= h($subject_desc) ?></p>
    <?php else: ?>
      <p class="muted" style="margin:0;">No description set for this subject.</p>
    <?php endif; ?>
  </section>

  <?php if ($db_error): ?>
    <div class="notice error" style="margin-top:14px;"><strong>DB Error:</strong> <?= h($db_error) ?></div>
  <?php endif; ?>

  <section class="card" style="margin-top:14px;">
    <div class="form-card">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <h2 style="margin:0;font-size:1.15rem;">Pages under this subject</h2>
        <span class="pill"><?= h((string)count($pages)) ?> total</span>
      </div>

      <?php if (!$pages): ?>
        <div class="notice" style="margin-top:12px;">
          <strong>No pages found for this subject.</strong>
          <div class="muted small">Create one to start populating your public subject page.</div>
          <div class="actions" style="margin-top:10px;">
            <a class="btn btn-primary" href="<?= h($new_page_href) ?>">+ New Page in this Subject</a>
            <a class="btn" href="<?= h($pages_href) ?>">Manage Pages</a>
          </div>
        </div>
      <?php else: ?>
        <div style="overflow:auto;margin-top:12px;">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr>
                <th style="text-align:left;padding:10px;border-bottom:1px solid rgba(0,0,0,.10);">Title</th>
                <?php if ($has_page_slug): ?><th style="text-align:left;padding:10px;border-bottom:1px solid rgba(0,0,0,.10);">Slug</th><?php endif; ?>
                <?php if ($has_page_nav_order || $has_page_position): ?><th style="text-align:left;padding:10px;border-bottom:1px solid rgba(0,0,0,.10);">Nav</th><?php endif; ?>
                <?php if ($has_page_is_public): ?><th style="text-align:left;padding:10px;border-bottom:1px solid rgba(0,0,0,.10);">Public</th><?php endif; ?>
                <th style="text-align:left;padding:10px;border-bottom:1px solid rgba(0,0,0,.10);">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pages as $p): ?>
                <?php
                  $pid = (int)($p['id'] ?? 0);

                  if ($has_page_title && trim((string)($p['title'] ?? '')) !== '') $pt = trim((string)$p['title']);
                  elseif ($has_page_menu_name && trim((string)($p['menu_name'] ?? '')) !== '') $pt = trim((string)$p['menu_name']);
                  else $pt = 'Page #' . $pid;

                  $ps = $has_page_slug ? trim((string)($p['slug'] ?? '')) : '';

                  $nav = null;
                  if ($has_page_nav_order) $nav = $p['nav_order'] ?? null;
                  elseif ($has_page_position) $nav = $p['position'] ?? null;

                  $ppub = $has_page_is_public ? (int)($p['is_public'] ?? 0) : 0;

                  $public_page_url = $u('/subjects/');
                  if ($subject_slug !== '' && $ps !== '') {
                    $public_page_url = $u('/subjects/' . rawurlencode($subject_slug) . '/' . rawurlencode($ps) . '/');
                  } elseif ($subject_slug !== '') {
                    $public_page_url = $u('/subjects/' . rawurlencode($subject_slug) . '/');
                  }

                  $edit_p = $u('/staff/subjects/pgs/edit.php?id=' . $pid . '&subject_id=' . $subject_id) . '&return=' . rawurlencode($return_path);
                  $show_p = $u('/staff/subjects/pgs/show.php?id=' . $pid . '&subject_id=' . $subject_id) . '&return=' . rawurlencode($return_path);
                  $del_p  = $u('/staff/subjects/pgs/delete.php?id=' . $pid . '&subject_id=' . $subject_id) . '&return=' . rawurlencode($return_path);
                ?>
                <tr>
                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);">
                    <strong><?= h($pt) ?></strong>
                    <div class="muted small">ID: <?= h((string)$pid) ?></div>
                  </td>

                  <?php if ($has_page_slug): ?>
                    <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);">
                      <?= ($ps !== '') ? '<span class="pill">' . h($ps) . '</span>' : '<span class="muted">—</span>'; ?>
                    </td>
                  <?php endif; ?>

                  <?php if ($has_page_nav_order || $has_page_position): ?>
                    <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);">
                      <span class="pill"><?= h(($nav === null || $nav === '') ? '—' : (string)$nav) ?></span>
                    </td>
                  <?php endif; ?>

                  <?php if ($has_page_is_public): ?>
                    <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);">
                      <span class="pill"><?= ($ppub === 1) ? 'Yes' : 'No' ?></span>
                    </td>
                  <?php endif; ?>

                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);">
                    <div class="actions" style="margin:0;">
                      <a class="btn" href="<?= h($edit_p) ?>">Edit</a>
                      <a class="btn" href="<?= h($show_p) ?>">Details</a>
                      <a class="btn" href="<?= h($public_page_url) ?>">View Public</a>
                      <a class="btn btn-danger" href="<?= h($del_p) ?>">Delete</a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
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
