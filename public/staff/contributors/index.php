<?php
declare(strict_types=1);

/**
 * /public/staff/contributors/index.php
 * Staff: Contributors list (premium) + bulk actions
 *
 * - schema-tolerant
 * - uses status column (active/draft) based on your real schema
 * - bulk actions: publish/unpublish/delete -> posts to /staff/contributors/bulk.php
 * - no arrow functions (compat)
 */

require_once __DIR__ . '/../_init.php';

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

/* ---------------------------------------------------------
   CSRF field (fallback)
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

/* ---------------------------------------------------------
   Flash messages (PRG)
--------------------------------------------------------- */
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
   Safe return (staff-only)
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
   Schema helpers
--------------------------------------------------------- */
if (!function_exists('pf__table_exists')) {
  function pf__table_exists(PDO $pdo, string $table): bool {
    try {
      $st = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
      $st->execute([$table]);
      return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
      return false;
    }
  }
}
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
   DB (PDO) — canonical staff accessor
--------------------------------------------------------- */
$pdo = function_exists('staff_pdo') ? staff_pdo() : (function_exists('db') ? db() : null);
if (!$pdo instanceof PDO) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Database handle not available.\n";
  exit;
}

/* ---------------------------------------------------------
   Filters
--------------------------------------------------------- */
$q = trim((string)($_GET['q'] ?? ''));

/* Return path for PRG links */
$return_params = [];
if ($q !== '') { $return_params['q'] = $q; }
$return_qs = $return_params ? ('?' . http_build_query($return_params, '', '&', PHP_QUERY_RFC3986)) : '';
$return_path = '/staff/contributors/index.php' . $return_qs;

/* ---------------------------------------------------------
   Header (shared)
--------------------------------------------------------- */
$active_nav = 'contributors';
$page_title = 'Manage Contributors • Staff';
$page_desc  = 'Manage contributor profiles, roles, and linking.';

$staff_subnav = [
  ['label' => 'Dashboard',    'href' => url_for('/staff/'),              'active' => false],
  ['label' => 'Contributors', 'href' => url_for('/staff/contributors/'), 'active' => true],
  ['label' => 'Public',       'href' => url_for('/contributors/'),       'active' => false],
];

require_once APP_ROOT . '/private/shared/staff_header.php';

/* URLs */
$u = static function(string $path): string {
  return function_exists('url_for') ? url_for($path) : $path;
};

$new_url    = $u('/staff/contributors/new.php') . '?return=' . rawurlencode($return_path);
$show_base  = $u('/staff/contributors/show.php');
$edit_base  = $u('/staff/contributors/edit.php');
$del_base   = $u('/staff/contributors/delete.php');
$bulk_post  = $u('/staff/contributors/bulk.php');
$self_url   = $u('/staff/contributors/index.php');

/* Flash */
$notice = pf__flash_get('notice');
$error  = pf__flash_get('error');

/* ---------------------------------------------------------
   Fetch list (schema-tolerant, aligned to your real schema)
--------------------------------------------------------- */
$rows = [];
$warn = '';

$table = 'contributors';

if (!pf__table_exists($pdo, $table)) {
  $warn = 'Table "contributors" not found yet. Create it first; this page will activate automatically.';
} else {

  $has_id       = pf__column_exists($pdo, $table, 'id');
  $has_display  = pf__column_exists($pdo, $table, 'display_name');
  $has_name     = pf__column_exists($pdo, $table, 'name');
  $has_user     = pf__column_exists($pdo, $table, 'username');
  $has_email    = pf__column_exists($pdo, $table, 'email');
  $has_slug     = pf__column_exists($pdo, $table, 'slug');
  $has_roles    = pf__column_exists($pdo, $table, 'roles');
  $has_status   = pf__column_exists($pdo, $table, 'status');

  if (!$has_id) {
    $warn = 'Contributors table exists, but is missing an "id" column. CRUD expects an id column.';
  } else {

    $label_expr =
      $has_display ? 'display_name' :
      ($has_name ? 'name' :
      ($has_user ? 'username' :
      ($has_email ? 'email' :
      "CONCAT('Contributor #', id)")));

    $select = [
      "id",
      "{$label_expr} AS label",
    ];
    if ($has_slug)   $select[] = "slug";
    if ($has_roles)  $select[] = "roles";
    if ($has_status) $select[] = "status";

    $sql = "SELECT " . implode(', ', $select) . " FROM {$table} WHERE 1=1";
    $params = [];

    if ($q !== '') {
      $parts = [];
      if ($has_display) $parts[] = "display_name LIKE :q";
      if ($has_name)    $parts[] = "name LIKE :q";
      if ($has_user)    $parts[] = "username LIKE :q";
      if ($has_email)   $parts[] = "email LIKE :q";
      if ($has_slug)    $parts[] = "slug LIKE :q";
      if ($has_roles)   $parts[] = "roles LIKE :q";
      if ($has_status)  $parts[] = "status LIKE :q";
      $parts[] = "CAST(id AS CHAR) LIKE :q";

      $sql .= " AND (" . implode(' OR ', $parts) . ")";
      $params[':q'] = '%' . $q . '%';
    }

    $sql .= " ORDER BY id DESC LIMIT 250";

    try {
      $st = $pdo->prepare($sql);
      $st->execute($params);
      $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
      $warn = 'Failed to query contributors safely (schema mismatch or permission issue).';
      $rows = [];
    }
  }
}

/* ---------------------------------------------------------
   View helpers
--------------------------------------------------------- */
function pf__status_pill(?string $status): array {
  $s = strtolower(trim((string)$status));
  if ($s === '') $s = 'active';

  if ($s === 'active') return ['text' => 'Active', 'class' => 'pill pill--success'];
  if ($s === 'draft')  return ['text' => 'Draft',  'class' => 'pill pill--muted'];

  return ['text' => strtoupper($s), 'class' => 'pill pill--muted'];
}

?>
<div class="container">

  <div class="hero">
    <div class="hero__row">
      <div>
        <h1 class="hero__title">Contributors</h1>
        <p class="hero__sub">Manage contributor profiles, roles, and linking.</p>
      </div>
      <div class="hero__actions">
        <a class="btn btn--primary" href="<?php echo h($new_url); ?>">+ New Contributor</a>
      </div>
    </div>
  </div>

  <?php if ($notice !== ''): ?>
    <div class="alert alert--success"><?php echo h($notice); ?></div>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <div class="alert alert--danger"><?php echo h($error); ?></div>
  <?php endif; ?>
  <?php if ($warn !== ''): ?>
    <div class="alert alert--warning"><?php echo h($warn); ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card__body">

      <form class="stack" method="get" action="<?php echo h($self_url); ?>">
        <div class="row row--wrap row--gap">
          <div class="field">
            <label class="label" for="q">Search</label>
            <input class="input" id="q" name="q" value="<?php echo h($q); ?>" placeholder="name / email / username / slug / roles / status / id">
          </div>

          <div class="field" style="align-self:flex-end;">
            <button class="btn" type="submit">Apply</button>
            <a class="btn btn--ghost" href="<?php echo h($self_url); ?>">Clear</a>
          </div>

          <div class="muted" style="align-self:flex-end;">
            <?php echo (int)count($rows); ?> contributor(s)
          </div>
        </div>
      </form>

      <hr class="sep">

      <!-- Bulk form wraps table -->
      <form method="post" action="<?php echo h($bulk_post); ?>" class="stack" onsubmit="return (function(){
        var sel = document.getElementById('bulk_action');
        if (!sel || !sel.value) { alert('Select a bulk action first.'); return false; }
        return confirm('Apply this bulk action to selected contributors?');
      })();">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="return" value="<?php echo h($return_path); ?>">

        <div class="row row--wrap row--gap" style="align-items:flex-end;">
          <div class="field">
            <label class="label" for="bulk_action">Bulk action</label>
            <select class="input" id="bulk_action" name="action">
              <option value="">— Select —</option>
              <option value="publish">Publish (set status = active)</option>
              <option value="unpublish">Unpublish (set status = draft)</option>
              <option value="delete">Delete</option>
            </select>
          </div>

          <div class="field">
            <button class="btn" type="submit">Apply to selected</button>
          </div>

          <div class="muted" style="margin-left:auto;">
            Bulk publish/unpublish controls visibility via <code>status</code>.
          </div>
        </div>

        <div class="table-wrap" style="margin-top:10px;">
          <table class="table">
            <thead>
              <tr>
                <th style="width:46px;">
                  <input type="checkbox" id="mk_select_all" onclick="(function(box){
                    var items = document.querySelectorAll('input[name=&quot;ids[]&quot;]');
                    for (var i=0;i<items.length;i++){ items[i].checked = box.checked; }
                  })(this);">
                </th>
                <th style="width:90px;">ID</th>
                <th>Name</th>
                <th style="width:220px;">Roles</th>
                <th style="width:120px;">Status</th>
                <th style="width:240px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr>
                  <td colspan="6" class="muted">No contributors found (or table not ready).</td>
                </tr>
              <?php else: ?>
                <?php foreach ($rows as $r): ?>
                  <?php
                    $id = (int)($r['id'] ?? 0);
                    $label = trim((string)($r['label'] ?? ''));
                    if ($label === '') $label = 'Contributor #' . $id;

                    $slug  = trim((string)($r['slug'] ?? ''));
                    $roles = trim((string)($r['roles'] ?? ''));
                    $status = array_key_exists('status', $r) ? (string)$r['status'] : 'active';
                    $pill = pf__status_pill($status);

                    $show_url = $show_base . '?id=' . rawurlencode((string)$id) . '&return=' . rawurlencode($return_path);
                    $edit_url = $edit_base . '?id=' . rawurlencode((string)$id) . '&return=' . rawurlencode($return_path);
                    $del_url  = $del_base  . '?id=' . rawurlencode((string)$id) . '&return=' . rawurlencode($return_path);
                  ?>
                  <tr>
                    <td>
                      <input type="checkbox" name="ids[]" value="<?php echo h((string)$id); ?>" onclick="(function(){
                        var all = document.getElementById('mk_select_all');
                        if (!all) return;
                        if (!this.checked) { all.checked = false; return; }
                        var items = document.querySelectorAll('input[name=&quot;ids[]&quot;]');
                        for (var i=0;i<items.length;i++){ if (!items[i].checked) { all.checked = false; return; } }
                        all.checked = true;
                      }).call(this);">
                    </td>
                    <td class="mono"><?php echo h((string)$id); ?></td>
                    <td>
                      <div class="strong"><?php echo h($label); ?></div>
                      <?php if ($slug !== ''): ?>
                        <div class="muted mono"><?php echo h($slug); ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?php echo ($roles !== '') ? h($roles) : '<span class="muted">—</span>'; ?></td>
                    <td>
                      <span class="<?php echo h($pill['class']); ?>"><?php echo h($pill['text']); ?></span>
                    </td>
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

<?php require APP_ROOT . '/private/shared/staff_footer.php'; ?>
