<?php
declare(strict_types=1);

/**
 * /public/staff/subjects/index.php
 * Staff: Subjects list (premium) + bulk actions (via bulk.php)
 *
 * Hardened bootstrap:
 * - Load initialize.php first (bounded scan) so APP_ROOT/PRIVATE_PATH/url_for/db are available
 * - Then load /public/staff/_init.php (preferred staff bootstrap)
 * - Never assume APP_ROOT exists before includes (prevents PHP 8+ fatals)
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

/* URL helper */
$u = static function(string $path): string {
  return function_exists('url_for') ? (string)url_for($path) : $path;
};

/* ---------------------------------------------------------
   CSRF helpers (bulk.php expects name="csrf_token")
--------------------------------------------------------- */
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

/* Safe return URL helper */
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

/* ---------------------------------------------------------
   Flash messages (PRG)
--------------------------------------------------------- */
if (!function_exists('pf__flash_set')) {
  function pf__flash_set(string $key, string $msg): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
      $_SESSION['flash'] = [];
    }
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
   DB (PDO) — canonical staff accessor
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

/* Schema detection */
$has_slug      = pf__column_exists($pdo, 'subjects', 'slug');
$has_menu_name = pf__column_exists($pdo, 'subjects', 'menu_name');
$has_name      = pf__column_exists($pdo, 'subjects', 'name');
$has_position  = pf__column_exists($pdo, 'subjects', 'position');
$has_nav_order = pf__column_exists($pdo, 'subjects', 'nav_order');
$has_is_public = pf__column_exists($pdo, 'subjects', 'is_public');

/* For UI hints */
$order_col = $has_nav_order ? 'nav_order' : ($has_position ? 'position' : null);

/* ---------------------------------------------------------
   Filters
--------------------------------------------------------- */
$q = trim((string)($_GET['q'] ?? ''));
$only_unpub = ((string)($_GET['only_unpub'] ?? '') === '1');

$return_params = [];
if ($q !== '') { $return_params['q'] = $q; }
if ($only_unpub) { $return_params['only_unpub'] = '1'; }
$return_qs = $return_params ? ('?' . http_build_query($return_params, '', '&', PHP_QUERY_RFC3986)) : '';

/* Canonical return= for this list */
$return_path = '/staff/subjects/index.php' . $return_qs;

/* Flash */
$notice = pf__flash_get('notice');
$error  = pf__flash_get('error');

/* ---------------------------------------------------------
   Fetch list
--------------------------------------------------------- */
$title_expr = $has_menu_name ? 'menu_name' : ($has_name ? 'name' : 'id');

$cols = ['id'];
if ($has_menu_name) { $cols[] = 'menu_name'; }
if ($has_name)      { $cols[] = 'name'; }
if ($has_slug)      { $cols[] = 'slug'; }
if ($has_nav_order) { $cols[] = 'nav_order'; }
if ($has_position)  { $cols[] = 'position'; }
if ($has_is_public) { $cols[] = 'is_public'; }

$sql = "SELECT " . implode(', ', $cols) . " FROM subjects WHERE 1=1";
$params = [];

if ($q !== '') {
  $parts = [];
  if ($has_menu_name) { $parts[] = "menu_name LIKE :q"; }
  if ($has_name)      { $parts[] = "name LIKE :q"; }
  if ($has_slug)      { $parts[] = "slug LIKE :q"; }
  if (!$parts)        { $parts[] = "CAST(id AS CHAR) LIKE :q"; }
  $sql .= " AND (" . implode(" OR ", $parts) . ")";
  $params[':q'] = '%' . $q . '%';
}

if ($only_unpub && $has_is_public) {
  $sql .= " AND is_public = 0";
}

$sort_col = $has_nav_order ? 'nav_order' : ($has_position ? 'position' : 'id');
if ($has_nav_order || $has_position) {
  $sql .= " ORDER BY {$sort_col} IS NULL, {$sort_col} ASC, {$title_expr} ASC, id ASC";
} else {
  $sql .= " ORDER BY {$sort_col} ASC";
}

$st = $pdo->prepare($sql);
$st->execute($params);
$subjects = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* ---------------------------------------------------------
   Header (shared) — do NOT assume APP_ROOT is defined
--------------------------------------------------------- */
$active_nav = 'subjects';
$page_title = 'Manage Subjects • Staff';
$page_desc  = 'Manage subject navigation, visibility, and ordering.';

$staff_subnav = [
  ['label' => 'Dashboard', 'href' => $u('/staff/'),          'active' => false],
  ['label' => 'Subjects',  'href' => $u('/staff/subjects/'), 'active' => true],
  ['label' => 'Public',    'href' => $u('/subjects/'),       'active' => false],
];

$staff_header =
  (defined('PRIVATE_PATH') ? (PRIVATE_PATH . '/shared/staff_header.php') :
   (defined('APP_ROOT') ? (APP_ROOT . '/private/shared/staff_header.php') : null));

if ($staff_header && is_file($staff_header)) {
  require $staff_header;
} else {
  // last resort minimal shell so page doesn't white-screen
  echo "<!doctype html><html><head><meta charset='utf-8'><title>" . h($page_title) . "</title></head><body><main>";
}

/* URLs */
$new_url    = $u('/staff/subjects/new.php') . '?return=' . rawurlencode($return_path);
$bulk_post  = $u('/staff/subjects/bulk.php');

$show_base  = $u('/staff/subjects/show.php');
$edit_base  = $u('/staff/subjects/edit.php');
$del_base   = $u('/staff/subjects/delete.php');

$col_count = 1 /* select */ + 1 /* title */ + 1 /* actions */;
if ($has_slug)      { $col_count++; }
if ($has_is_public) { $col_count++; }
if ($order_col)     { $col_count++; }

?>
<div class="container">

  <div class="hero">
    <div class="hero__row">
      <div>
        <h1 class="hero__title">Subjects</h1>
        <p class="hero__sub">Manage subject navigation, visibility, and ordering.</p>
      </div>
      <div class="hero__actions">
        <a class="btn btn--primary" href="<?php echo h($new_url); ?>">+ New Subject</a>
      </div>
    </div>
  </div>

  <?php if ($notice !== ''): ?>
    <div class="alert alert--success"><?php echo h($notice); ?></div>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <div class="alert alert--danger"><?php echo h($error); ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card__body">

      <form class="stack" method="get" action="<?php echo h($u('/staff/subjects/index.php')); ?>">
        <div class="row row--wrap row--gap">
          <div class="field">
            <label class="label" for="q">Search</label>
            <input class="input" id="q" name="q" value="<?php echo h($q); ?>" placeholder="menu name / slug / id">
          </div>

          <?php if ($has_is_public): ?>
            <div class="field" style="align-self:flex-end;">
              <label class="check">
                <input type="checkbox" name="only_unpub" value="1" <?php echo $only_unpub ? 'checked' : ''; ?>>
                <span>Only drafts</span>
              </label>
            </div>
          <?php endif; ?>

          <div class="field" style="align-self:flex-end;">
            <button class="btn" type="submit">Apply</button>
            <a class="btn btn--ghost" href="<?php echo h($u('/staff/subjects/index.php')); ?>">Clear</a>
          </div>
        </div>
      </form>

      <hr class="sep">

      <form method="post" action="<?php echo h($bulk_post); ?>" id="bulkForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="return" value="<?php echo h($return_path); ?>">

        <div class="row row--wrap row--gap" style="align-items:center; justify-content:space-between;">
          <div class="row row--wrap row--gap" style="align-items:center;">
            <button type="button" class="btn btn--ghost" id="btnSelectAll">Select all</button>
            <button type="button" class="btn btn--ghost" id="btnSelectNone">Select none</button>
            <button type="button" class="btn btn--ghost" id="btnSelectInvert">Invert</button>

            <?php if ($has_is_public): ?>
              <button type="button" class="btn btn--ghost" id="btnSelectDrafts">Drafts only</button>
              <button type="button" class="btn btn--ghost" id="btnSelectPublic">Public only</button>

              <button class="btn" type="submit" name="action" value="publish"
                onclick="return confirm('Publish selected subjects?');">
                Publish selected
              </button>
              <button class="btn" type="submit" name="action" value="unpublish"
                onclick="return confirm('Unpublish selected subjects?');">
                Unpublish selected
              </button>
            <?php endif; ?>

            <?php if ($order_col): ?>
              <button class="btn btn--primary" type="submit" name="action" value="save_order"
                onclick="return confirm('Save ordering for all rows shown?');">
                Save order
              </button>

              <button class="btn" type="button" id="btnNormalize"
                title="Fills selected rows with 10,20,30... in the table order. Then click Save order.">
                Normalize selected
              </button>

              <span class="muted small">(Blank order = unset.)</span>
            <?php endif; ?>
          </div>

          <div class="muted"><?php echo (int)count($subjects); ?> subject(s)</div>
        </div>

        <?php if (!$has_is_public && !$order_col): ?>
          <div class="alert alert--info" style="margin-top:12px;">
            Bulk actions are limited because this schema is missing
            <code>subjects.is_public</code> and <code>subjects.nav_order</code>/<code>position</code>.
          </div>
        <?php endif; ?>

        <div class="table-wrap">
          <table class="table" id="subjectsTable">
            <thead>
              <tr>
                <th style="width:42px;"><span class="sr-only">Select</span></th>
                <th>Title</th>
                <?php if ($has_slug): ?><th>Slug</th><?php endif; ?>
                <?php if ($has_is_public): ?><th style="width:110px;">Status</th><?php endif; ?>
                <?php if ($order_col): ?><th style="width:140px;">Order</th><?php endif; ?>
                <th style="width:240px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($subjects)): ?>
                <tr><td colspan="<?php echo (int)$col_count; ?>" class="muted">No subjects found.</td></tr>
              <?php else: ?>
                <?php foreach ($subjects as $s): ?>
                  <?php
                    $sid = (int)($s['id'] ?? 0);

                    $title =
                      ($has_menu_name && isset($s['menu_name']) && trim((string)$s['menu_name']) !== '') ? (string)$s['menu_name'] :
                      (($has_name && isset($s['name']) && trim((string)$s['name']) !== '') ? (string)$s['name'] : ('Subject #' . $sid));

                    $slug = $has_slug ? (string)($s['slug'] ?? '') : '';

                    $is_public = $has_is_public ? (int)($s['is_public'] ?? 0) : null;
                    $status_attr = ($has_is_public ? ($is_public === 1 ? 'public' : 'draft') : '');

                    $order_val = '';
                    if ($has_nav_order) {
                      $order_val = (string)($s['nav_order'] ?? '');
                    } elseif ($has_position) {
                      $order_val = (string)($s['position'] ?? '');
                    }

                    $show_url = $show_base . '?id=' . rawurlencode((string)$sid) . '&return=' . rawurlencode($return_path);
                    $edit_url = $edit_base . '?id=' . rawurlencode((string)$sid) . '&return=' . rawurlencode($return_path);
                    $del_url  = $del_base  . '?id=' . rawurlencode((string)$sid) . '&return=' . rawurlencode($return_path);
                  ?>
                  <tr data-status="<?php echo h($status_attr); ?>">
                    <td>
                      <input type="checkbox" class="js-rowcheck" name="ids[]" value="<?php echo h((string)$sid); ?>">
                    </td>

                    <td>
                      <div class="strong"><?php echo h($title); ?></div>
                      <div class="muted">ID: <?php echo h((string)$sid); ?></div>
                    </td>

                    <?php if ($has_slug): ?>
                      <td class="mono">
                        <?php if ($slug !== ''): ?>
                          <?php echo h($slug); ?>
                        <?php else: ?>
                          <span class="muted">—</span>
                        <?php endif; ?>
                      </td>
                    <?php endif; ?>

                    <?php if ($has_is_public): ?>
                      <td>
                        <?php if ($is_public === 1): ?>
                          <span class="pill pill--success">Public</span>
                        <?php else: ?>
                          <span class="pill pill--muted">Draft</span>
                        <?php endif; ?>
                      </td>
                    <?php endif; ?>

                    <?php if ($order_col): ?>
                      <td>
                        <input class="input input--sm mono js-order" type="number"
                          name="nav_order[<?php echo h((string)$sid); ?>]"
                          value="<?php echo h($order_val); ?>"
                          style="max-width:120px;">
                      </td>
                    <?php endif; ?>

                    <td class="row row--gap">
                      <a class="btn btn--sm" href="<?php echo h($show_url); ?>">View</a>
                      <a class="btn btn--sm" href="<?php echo h($edit_url); ?>">Edit</a>
                      <a class="btn btn--sm btn--danger" href="<?php echo h($del_url); ?>">Delete</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </form>

    </div>
  </div>
</div>

<script>
(function(){
  const qs = (sel, root) => (root || document).querySelector(sel);
  const qsa = (sel, root) => Array.from((root || document).querySelectorAll(sel));

  const btnAll     = qs('#btnSelectAll');
  const btnNone    = qs('#btnSelectNone');
  const btnInvert  = qs('#btnSelectInvert');
  const btnDrafts  = qs('#btnSelectDrafts');
  const btnPublic  = qs('#btnSelectPublic');
  const btnNorm    = qs('#btnNormalize');

  function checks(){ return qsa('.js-rowcheck'); }
  function setAll(val){ checks().forEach(ch => ch.checked = val); }
  function invert(){ checks().forEach(ch => ch.checked = !ch.checked); }

  function selectByStatus(status){
    const rows = qsa('#subjectsTable tbody tr');
    rows.forEach(tr => {
      const st = tr.getAttribute('data-status');
      const cb = qs('.js-rowcheck', tr);
      if (!cb) return;
      cb.checked = (st === status);
    });
  }

  function normalizeSelected(){
    const rows = qsa('#subjectsTable tbody tr');
    let v = 10;
    let count = 0;

    rows.forEach(tr => {
      const cb = qs('.js-rowcheck', tr);
      if (!cb || !cb.checked) return;
      const inp = qs('.js-order', tr);
      if (!inp) return;
      inp.value = String(v);
      v += 10;
      count++;
    });

    if (count === 0) alert('Select at least one row to normalize.');
  }

  if (btnAll)    btnAll.addEventListener('click', () => setAll(true));
  if (btnNone)   btnNone.addEventListener('click', () => setAll(false));
  if (btnInvert) btnInvert.addEventListener('click', () => invert());

  if (btnDrafts) btnDrafts.addEventListener('click', () => selectByStatus('draft'));
  if (btnPublic) btnPublic.addEventListener('click', () => selectByStatus('public'));

  if (btnNorm) btnNorm.addEventListener('click', () => {
    if (!confirm('Normalize selected rows to 10,20,30...?\nThen click “Save order” to persist.')) return;
    normalizeSelected();
  });
})();
</script>

<?php
$staff_footer =
  (defined('PRIVATE_PATH') ? (PRIVATE_PATH . '/shared/staff_footer.php') :
   (defined('APP_ROOT') ? (APP_ROOT . '/private/shared/staff_footer.php') : null));

if ($staff_footer && is_file($staff_footer)) {
  require $staff_footer;
} else {
  echo "</main></body></html>";
}
