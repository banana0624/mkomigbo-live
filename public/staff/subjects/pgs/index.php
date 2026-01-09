<?php
declare(strict_types=1);

/**
 * /public/staff/subjects/pgs/index.php
 * Staff: Pages list + bulk actions (via /staff/subjects/pgs/bulk.php)
 *
 * Uses centralized staff bootstrap: /public/staff/_init.php
 * Keeps:
 * - schema-tolerant columns
 * - correct dynamic colspan
 * - external JS include with cache-bust
 */

require_once __DIR__ . '/../../_init.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

/* ---------------------------------------------------------
   Minimal fallbacks (only if _init.php did not provide them)
--------------------------------------------------------- */
if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
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
if (!function_exists('staff_csrf_field')) {
  function staff_csrf_field(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf_token" value="' . h((string)$_SESSION['csrf_token']) . '">';
  }
}
if (!function_exists('staff_pdo')) {
  function staff_pdo(): ?PDO {
    return (function_exists('db') && db() instanceof PDO) ? db() : null;
  }
}

/* DB */
$pdo = staff_pdo();
if (!$pdo instanceof PDO) {
  $page_title = 'Manage Pages • Staff';
  $active_nav = 'pgs';
  require_once APP_ROOT . '/private/shared/staff_header.php';
  echo '<div class="container" style="padding:24px 0;">';
  echo '<div class="notice error"><strong>DB Error:</strong> Database handle not available.</div>';
  echo '</div>';
  require_once APP_ROOT . '/private/shared/staff_footer.php';
  exit;
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

/* Schema */
$has_title     = pf__column_exists($pdo, 'pages', 'title');
$has_name      = pf__column_exists($pdo, 'pages', 'name');
$has_menu_name = pf__column_exists($pdo, 'pages', 'menu_name');

$has_slug      = pf__column_exists($pdo, 'pages', 'slug');
$has_subject   = pf__column_exists($pdo, 'pages', 'subject_id');
$has_nav_order = pf__column_exists($pdo, 'pages', 'nav_order');
$has_position  = pf__column_exists($pdo, 'pages', 'position');
$has_is_public = pf__column_exists($pdo, 'pages', 'is_public');
$has_visible   = pf__column_exists($pdo, 'pages', 'visible');

$order_col = $has_nav_order ? 'nav_order' : ($has_position ? 'position' : null);
$pub_col   = $has_is_public ? 'is_public' : ($has_visible ? 'visible' : null);

/* Filters + return */
$q = trim((string)($_GET['q'] ?? ''));
$only_unpub = ((string)($_GET['only_unpub'] ?? '') === '1');

$return_params = [];
if ($q !== '') $return_params['q'] = $q;
if ($only_unpub) $return_params['only_unpub'] = '1';
$return_qs   = $return_params ? ('?' . http_build_query($return_params, '', '&', PHP_QUERY_RFC3986)) : '';
$return_path = '/staff/subjects/pgs/index.php' . $return_qs;

/* Flash */
$notice = pf__flash_get('notice');
$error  = pf__flash_get('error');

/* Fetch pages + subject title if available */
$cols = ['p.id'];
if ($has_title)     $cols[] = 'p.title';
if ($has_name)      $cols[] = 'p.name';
if ($has_menu_name) $cols[] = 'p.menu_name';
if ($has_slug)      $cols[] = 'p.slug';
if ($has_subject)   $cols[] = 'p.subject_id';
if ($order_col)     $cols[] = 'p.' . $order_col;
if ($pub_col)       $cols[] = 'p.' . $pub_col;

$join = '';
$sub_has_menu = pf__column_exists($pdo, 'subjects', 'menu_name');
$sub_has_name = pf__column_exists($pdo, 'subjects', 'name');

if ($has_subject && ($sub_has_menu || $sub_has_name)) {
  $join = ' LEFT JOIN subjects s ON s.id = p.subject_id ';
  $cols[] = $sub_has_menu ? 's.menu_name AS subject_title' : 's.name AS subject_title';
}

$sql = "SELECT " . implode(', ', $cols) . " FROM pages p {$join} WHERE 1=1";
$params = [];

if ($q !== '') {
  $parts = [];
  if ($has_title)     $parts[] = "p.title LIKE :q";
  if ($has_menu_name) $parts[] = "p.menu_name LIKE :q";
  if ($has_name)      $parts[] = "p.name LIKE :q";
  if ($has_slug)      $parts[] = "p.slug LIKE :q";
  if ($join !== '')   $parts[] = "subject_title LIKE :q";
  if (!$parts)        $parts[] = "CAST(p.id AS CHAR) LIKE :q";
  $sql .= " AND (" . implode(' OR ', $parts) . ")";
  $params[':q'] = '%' . $q . '%';
}

if ($only_unpub && $pub_col) {
  $sql .= " AND p.`{$pub_col}` = 0";
}

if ($order_col) $sql .= " ORDER BY p.`{$order_col}` IS NULL, p.`{$order_col}` ASC, p.id ASC";
else $sql .= " ORDER BY p.id DESC";

$st = $pdo->prepare($sql);
$st->execute($params);
$pages = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* Header */
$page_title = 'Manage Pages • Staff';
$active_nav = 'pgs';
require_once APP_ROOT . '/private/shared/staff_header.php';

/* URL helper */
$u = static function(string $path): string {
  return function_exists('url_for') ? (string)url_for($path) : $path;
};

/* URLs */
$list_url  = $u('/staff/subjects/pgs/index.php');
$new_url   = $u('/staff/subjects/pgs/new.php') . '?return=' . rawurlencode($return_path);
$bulk_post = $u('/staff/subjects/pgs/bulk.php');

$show_base = $u('/staff/subjects/pgs/show.php');
$edit_base = $u('/staff/subjects/pgs/edit.php');
$del_base  = $u('/staff/subjects/pgs/delete.php');

if (!function_exists('pf__page_title_of')) {
  function pf__page_title_of(array $row): string {
    foreach (['title','menu_name','name'] as $k) {
      if (isset($row[$k]) && is_string($row[$k]) && trim($row[$k]) !== '') return trim($row[$k]);
    }
    $id = (int)($row['id'] ?? 0);
    return $id > 0 ? "Page #{$id}" : "Page";
  }
}

/* colspan correctness */
$col_count = 1 /* checkbox */ + 1 /* title */
  + ($has_slug ? 1 : 0)
  + ($has_subject ? 1 : 0)
  + ($pub_col ? 1 : 0)
  + ($order_col ? 1 : 0)
  + 1 /* actions */;

?>
<div class="container" style="padding:24px 0;">

  <section class="hero">
    <div class="hero-bar"></div>
    <div class="hero-inner">
      <h1>Pages</h1>
      <p class="muted" style="margin:6px 0 0;">Manage public pages: status, ordering, and edits.</p>

      <div class="actions" style="margin-top:14px;">
        <a class="btn btn-primary" href="<?php echo h($new_url); ?>">+ New Page</a>
        <a class="btn" href="<?php echo h($u('/staff/')); ?>">Dashboard</a>
      </div>
    </div>
  </section>

  <?php if ($notice !== ''): ?>
    <div class="notice success" style="margin-top:14px;"><strong><?php echo h($notice); ?></strong></div>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <div class="notice error" style="margin-top:14px;"><strong><?php echo h($error); ?></strong></div>
  <?php endif; ?>

  <section class="card" style="margin-top:14px;">
    <div class="form-card">

      <form class="stack" method="get" action="<?php echo h($list_url); ?>">
        <div class="row" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
          <div class="field" style="min-width:260px;flex:1;">
            <label class="label" for="q">Search</label>
            <input class="input" id="q" name="q" value="<?php echo h($q); ?>" placeholder="title / slug / subject">
          </div>

          <?php if ($pub_col): ?>
            <div class="field" style="align-self:flex-end;">
              <label style="display:flex;align-items:center;gap:10px;font-weight:700;">
                <input type="checkbox" name="only_unpub" value="1" <?php echo $only_unpub ? 'checked' : ''; ?>>
                Only drafts
              </label>
            </div>
          <?php endif; ?>

          <div class="field">
            <button class="btn" type="submit">Apply</button>
            <a class="btn" href="<?php echo h($list_url); ?>">Clear</a>
          </div>
        </div>
      </form>

      <hr class="sep" style="margin:14px 0;">

      <form method="post" action="<?php echo h($bulk_post); ?>" id="bulkForm">
        <?php echo staff_csrf_field(); ?>
        <input type="hidden" name="return" value="<?php echo h($return_path); ?>">

        <div class="actions" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:10px;">
          <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
            <button type="button" class="btn" data-js="select-all">Select all</button>
            <button type="button" class="btn" data-js="select-none">Select none</button>
            <button type="button" class="btn" data-js="select-invert">Invert</button>

            <?php if ($pub_col): ?>
              <button class="btn" type="submit" name="action" value="publish"
                onclick="return confirm('Publish selected pages?');">Publish selected</button>
              <button class="btn" type="submit" name="action" value="unpublish"
                onclick="return confirm('Unpublish selected pages?');">Unpublish selected</button>
            <?php endif; ?>

            <?php if ($order_col): ?>
              <button class="btn btn-primary" type="submit" name="action" value="save_order"
                onclick="return confirm('Save ordering for all rows shown?');">Save order</button>
              <button class="btn" type="button" data-js="normalize"
                title="Fills selected rows with 10,20,30... then click Save order.">Normalize selected</button>
              <span class="muted small">(Blank order = unset.)</span>
            <?php endif; ?>
          </div>

          <div class="muted"><?php echo (int)count($pages); ?> page(s)</div>
        </div>

        <div style="overflow:auto;">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr>
                <th style="width:42px;text-align:left;padding:10px;border-bottom:1px solid rgba(0,0,0,.10);"></th>
                <th style="text-align:left;padding:10px;border-bottom:1px solid rgba(0,0,0,.10);">Title</th>
                <?php if ($has_slug): ?><th style="text-align:left;padding:10px;border-bottom:1px solid rgba(0,0,0,.10);">Slug</th><?php endif; ?>
                <?php if ($has_subject): ?><th style="text-align:left;padding:10px;border-bottom:1px solid rgba(0,0,0,.10);">Subject</th><?php endif; ?>
                <?php if ($pub_col): ?><th style="width:110px;text-align:left;padding:10px;border-bottom:1px solid rgba(0,0,0,.10);">Status</th><?php endif; ?>
                <?php if ($order_col): ?><th style="width:140px;text-align:left;padding:10px;border-bottom:1px solid rgba(0,0,0,.10);">Order</th><?php endif; ?>
                <th style="width:240px;text-align:left;padding:10px;border-bottom:1px solid rgba(0,0,0,.10);">Actions</th>
              </tr>
            </thead>

            <tbody>
              <?php if (!$pages): ?>
                <tr>
                  <td colspan="<?php echo (int)$col_count; ?>" class="muted" style="padding:12px;">No pages found.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($pages as $p): ?>
                  <?php
                    $id = (int)($p['id'] ?? 0);
                    $title = pf__page_title_of($p);
                    $slug  = $has_slug ? (string)($p['slug'] ?? '') : '';
                    $subject_title = isset($p['subject_title']) ? (string)$p['subject_title'] : '';
                    $status = $pub_col ? (int)($p[$pub_col] ?? 0) : null;
                    $order_val = $order_col ? (string)($p[$order_col] ?? '') : '';

                    $show_url = $show_base . '?id=' . rawurlencode((string)$id) . '&return=' . rawurlencode($return_path);
                    $edit_url = $edit_base . '?id=' . rawurlencode((string)$id) . '&return=' . rawurlencode($return_path);
                    $del_url  = $del_base  . '?id=' . rawurlencode((string)$id) . '&return=' . rawurlencode($return_path);
                  ?>
                  <tr>
                    <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);">
                      <input type="checkbox" class="js-rowcheck" name="ids[]" value="<?php echo h((string)$id); ?>">
                    </td>

                    <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);">
                      <strong><?php echo h($title); ?></strong>
                      <div class="muted small">ID: <?php echo h((string)$id); ?></div>
                    </td>

                    <?php if ($has_slug): ?>
                      <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);" class="mono">
                        <?php echo $slug !== '' ? '<span class="pill">' . h($slug) . '</span>' : '<span class="muted">—</span>'; ?>
                      </td>
                    <?php endif; ?>

                    <?php if ($has_subject): ?>
                      <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);">
                        <?php echo $subject_title !== '' ? h($subject_title) : '<span class="muted">—</span>'; ?>
                      </td>
                    <?php endif; ?>

                    <?php if ($pub_col): ?>
                      <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);">
                        <?php echo ((int)$status === 1) ? '<span class="pill">Public</span>' : '<span class="pill">Draft</span>'; ?>
                      </td>
                    <?php endif; ?>

                    <?php if ($order_col): ?>
                      <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);">
                        <input class="input mono js-order" type="number"
                          name="nav_order[<?php echo h((string)$id); ?>]"
                          value="<?php echo h($order_val); ?>"
                          style="max-width:120px;">
                      </td>
                    <?php endif; ?>

                    <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);">
                      <div class="actions" style="margin:0;">
                        <a class="btn btn-sm" href="<?php echo h($show_url); ?>">View</a>
                        <a class="btn btn-sm" href="<?php echo h($edit_url); ?>">Edit</a>
                        <a class="btn btn-sm btn-danger" href="<?php echo h($del_url); ?>">Delete</a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>

          </table>
        </div>
      </form>

    </div>
  </section>
</div>

<?php
/* External JS (absolute path safe) */
$js_public = '/lib/js/staff-pages-index.js';
$js_url = function_exists('url_for') ? (string)url_for($js_public) : $js_public;

$js_disk = (defined('PUBLIC_PATH') && is_string(PUBLIC_PATH) && PUBLIC_PATH !== '')
  ? (rtrim((string)PUBLIC_PATH, DIRECTORY_SEPARATOR) . str_replace('/', DIRECTORY_SEPARATOR, $js_public))
  : (dirname(__DIR__, 3) . str_replace('/', DIRECTORY_SEPARATOR, $js_public));

$ver = (is_string($js_disk) && $js_disk !== '' && is_file($js_disk)) ? (string)filemtime($js_disk) : '1';
?>
<script src="<?= h($js_url) ?>?v=<?= h($ver) ?>" defer></script>
<?php
require_once APP_ROOT . '/private/shared/staff_footer.php';
