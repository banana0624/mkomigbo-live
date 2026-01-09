<?php
declare(strict_types=1);

/**
 * /public/staff/contributors/edit.php
 * Staff: Edit Contributor form (schema-tolerant, status-based)
 *
 * Your real schema includes:
 * - status (varchar(40)) default 'active'
 * - bio_raw, bio_html
 * - display_name, email, roles
 *
 * This form:
 * - edits whichever columns exist
 * - provides Status dropdown when status column exists
 * - edits bio_raw (preferred) or bio (legacy) when available
 * - no arrow functions
 */

require_once __DIR__ . '/../_init.php';

require_once PRIVATE_PATH . '/functions/slug.php';

$slug = mk_slugify($_POST['slug'] ?? $_POST['title'] ?? $_POST['display_name'] ?? '');
if ($slug === '') { $errors[] = "Slug is required."; }

$slug = mk_slug_unique(db(), 'subjects', $slug); // or contributors
$slug = mk_slug_unique(db(), 'pages', $slug, 'slug', 'subject_id = :sid', [':sid' => $subject_id]);


/* ---------------------------------------------------------
   Helpers
--------------------------------------------------------- */
if (!function_exists('h')) {
  function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('redirect_to')) {
  function redirect_to(string $location): void {
    $location = str_replace(["\r", "\n"], '', $location);
    header('Location: ' . $location, true, 302);
    exit;
  }
}

/* CSRF field */
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

/* Flash */
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

/* Schema helpers */
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
if (!function_exists('pf__table_exists')) {
  function pf__table_exists(PDO $pdo, string $table): bool {
    $st = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
    $st->execute([$table]);
    return (bool)$st->fetchColumn();
  }
}

/* Value getter */
if (!function_exists('pf__v')) {
  function pf__v(?array $row, string $key): string {
    if (!$row || !array_key_exists($key, $row)) return '';
    return (string)$row[$key];
  }
}

/* Friendly label */
if (!function_exists('pf__label')) {
  function pf__label(string $field): string {
    return ucwords(str_replace('_', ' ', $field));
  }
}

/* DB */
$pdo = function_exists('staff_pdo') ? staff_pdo() : (function_exists('db') ? db() : null);
if (!$pdo instanceof PDO) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Database handle not available.\n";
  exit;
}

/* Inputs */
$id = (int)($_GET['id'] ?? 0);
$default_return = '/staff/contributors/index.php';
$return = pf__safe_return_url((string)($_GET['return'] ?? $default_return), $default_return);

if ($id <= 0) redirect_to($return);

/* Flash */
$notice = pf__flash_get('notice');
$error  = pf__flash_get('error');

/* Load contributor */
$contributor = null;
$warn = '';

$table = 'contributors';

try {
  if (!pf__table_exists($pdo, $table)) {
    $warn = 'Table "contributors" not found yet.';
  } else {

    /* Columns we may render (schema-tolerant) */
    $candidates = [
      'id',
      'display_name','name','username','slug','email','roles','avatar_path',
      'status',
      'bio_raw','bio_html','bio'
    ];

    $select = [];
    foreach ($candidates as $c) {
      if ($c === 'id' || pf__column_exists($pdo, $table, $c)) $select[] = $c;
    }
    $select = array_values(array_unique($select));

    /* Quote identifiers safely */
    $quoted = [];
    foreach ($select as $c) {
      $c = (string)$c;
      $c = str_replace('`', '', $c);
      $quoted[] = '`' . $c . '`';
    }
    $cols_sql = implode(', ', $quoted);

    $sql = "SELECT {$cols_sql} FROM {$table} WHERE id = ? LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([$id]);
    $contributor = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$contributor) $warn = 'Contributor not found.';
  }
} catch (Throwable $e) {
  $warn = 'Failed to load contributor.';
  $contributor = null;
}

/* Name */
$name = $contributor
  ? (string)($contributor['display_name'] ?? $contributor['name'] ?? $contributor['username'] ?? $contributor['email'] ?? $contributor['slug'] ?? ('Contributor #' . $id))
  : ('Contributor #' . $id);

$name = trim($name) !== '' ? $name : ('Contributor #' . $id);

/* Schema flags */
$has_status   = pf__table_exists($pdo, $table) && pf__column_exists($pdo, $table, 'status');
$has_bio_raw  = pf__table_exists($pdo, $table) && pf__column_exists($pdo, $table, 'bio_raw');
$has_bio_html = pf__table_exists($pdo, $table) && pf__column_exists($pdo, $table, 'bio_html');
$has_bio      = pf__table_exists($pdo, $table) && pf__column_exists($pdo, $table, 'bio');

/* Header */
$active_nav = 'contributors';
$page_title = 'Edit • ' . $name . ' • Staff';
$page_desc  = 'Edit contributor details (schema-tolerant).';

$staff_subnav = [
  ['label' => 'Dashboard',    'href' => url_for('/staff/'),              'active' => false],
  ['label' => 'Contributors', 'href' => url_for('/staff/contributors/'), 'active' => true],
  ['label' => 'Public',       'href' => url_for('/contributors/'),       'active' => false],
];

require_once APP_ROOT . '/private/shared/staff_header.php';

/* URLs */
$update_post = function_exists('url_for') ? url_for('/staff/contributors/update.php') : '/staff/contributors/update.php';
$back_url    = function_exists('url_for') ? url_for($return) : $return;
$show_url    = (function_exists('url_for') ? url_for('/staff/contributors/show.php') : '/staff/contributors/show.php')
             . '?id=' . rawurlencode((string)$id) . '&return=' . rawurlencode($return);

/* Current status */
$current_status = $contributor ? trim((string)($contributor['status'] ?? '')) : '';
if ($current_status === '') $current_status = 'active';

?>
<div class="container">

  <div class="hero">
    <div class="hero__row">
      <div>
        <h1 class="hero__title">Edit Contributor</h1>
        <p class="hero__sub"><?php echo h($name); ?></p>
      </div>
      <div class="hero__actions">
        <a class="btn btn--ghost" href="<?php echo h($back_url); ?>">← Back</a>
        <a class="btn" href="<?php echo h($show_url); ?>">View</a>
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

      <?php if (!$contributor): ?>
        <p class="muted">No contributor data to edit.</p>
      <?php else: ?>

      <form method="post" action="<?php echo h($update_post); ?>" class="stack">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo h((string)$id); ?>">
        <input type="hidden" name="return" value="<?php echo h($return); ?>">

        <div class="grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:14px;">

          <?php
          $fields = ['display_name','name','username','slug','email','roles','avatar_path'];
          foreach ($fields as $f):
            if (array_key_exists($f, $contributor)):
          ?>
            <div class="field">
              <label class="label" for="<?php echo h($f); ?>"><?php echo h(pf__label($f)); ?></label>
              <input class="input" id="<?php echo h($f); ?>" name="<?php echo h($f); ?>" value="<?php echo h(pf__v($contributor, $f)); ?>">
            </div>
          <?php
            endif;
          endforeach;
          ?>

          <?php if ($has_status && array_key_exists('status', $contributor)): ?>
            <div class="field" style="align-self:flex-end;">
              <label class="label" for="status">Status</label>
              <select class="input" id="status" name="status">
                <option value="active" <?php echo ($current_status === 'active') ? 'selected' : ''; ?>>Active</option>
                <option value="draft"  <?php echo ($current_status === 'draft')  ? 'selected' : ''; ?>>Draft</option>
              </select>
              <div class="muted" style="font-size:.9rem; margin-top:6px;">
                Active profiles can be shown publicly; Draft profiles should be hidden.
              </div>
            </div>
          <?php endif; ?>

        </div>

        <?php
          $show_bio_box = ($has_bio_raw || $has_bio_html || $has_bio);
          $bio_value = '';
          if ($has_bio_raw && array_key_exists('bio_raw', $contributor)) $bio_value = (string)$contributor['bio_raw'];
          elseif ($has_bio && array_key_exists('bio', $contributor))     $bio_value = (string)$contributor['bio'];
          elseif ($has_bio_html && array_key_exists('bio_html', $contributor)) $bio_value = (string)$contributor['bio_html'];
        ?>

        <?php if ($show_bio_box): ?>
          <div class="field">
            <label class="label" for="bio_raw">Bio (rich text allowed)</label>
            <textarea id="bio_raw" name="bio_raw" rows="10" class="input" style="width:100%;"><?php echo h($bio_value); ?></textarea>
            <div class="mk-muted" style="font-size:.9rem; margin-top:6px;">
              This is sanitized on save and displayed publicly as formatted bio.
            </div>
          </div>
        <?php endif; ?>

        <div class="row row--gap">
          <button class="btn btn--primary" type="submit">Save changes</button>
          <a class="btn btn--ghost" href="<?php echo h($back_url); ?>">Cancel</a>
        </div>

      </form>

      <?php endif; ?>

    </div>
  </div>
</div>

<?php require APP_ROOT . '/private/shared/staff_footer.php'; ?>
