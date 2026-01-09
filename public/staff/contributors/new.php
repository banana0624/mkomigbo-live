<?php
declare(strict_types=1);

/**
 * /public/staff/contributors/new.php
 * Staff: New Contributor form (schema-tolerant)
 *
 * Improvements:
 * - UI consistency: textarea uses class="input"
 * - Slug UX: optional; auto-generated on save (when slug column exists)
 * - Bio note: stored as bio_raw, rendered as sanitized bio_html when available
 */

require_once __DIR__ . '/../_init.php';

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

/* CSRF helpers (field name: csrf_token) */
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

/* Schema */
if (!function_exists('pf__table_exists')) {
  function pf__table_exists(PDO $pdo, string $table): bool {
    $st = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
    $st->execute([$table]);
    return (bool)$st->fetchColumn();
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

if (!function_exists('pf__label')) {
  function pf__label(string $field): string {
    return ucwords(str_replace('_', ' ', $field));
  }
}

/* URL helper (no arrow functions) */
if (!function_exists('pf__u')) {
  function pf__u(string $path): string {
    return function_exists('url_for') ? url_for($path) : $path;
  }
}

/* ---------------------------------------------------------
   DB
--------------------------------------------------------- */
$pdo = function_exists('staff_pdo') ? staff_pdo() : (function_exists('db') ? db() : null);
if (!$pdo instanceof PDO) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Database handle not available.\n";
  exit;
}

$default_return = '/staff/contributors/index.php';
$return = pf__safe_return_url((string)($_GET['return'] ?? $default_return), $default_return);

$notice = pf__flash_get('notice');
$error  = pf__flash_get('error');

$table_ready = false;
$warn = '';
try {
  $table_ready = pf__table_exists($pdo, 'contributors');
  if (!$table_ready) $warn = 'Table "contributors" does not exist yet. Create it, then this form will activate.';
} catch (Throwable $e) {
  $table_ready = false;
  $warn = 'Unable to verify contributors table.';
}

/* Detect fields */
$fields = [];
$has_slug = false;

if ($table_ready) {
  $candidates = ['display_name','name','username','slug','email','roles','avatar_path','status'];
  foreach ($candidates as $f) {
    if (pf__column_exists($pdo, 'contributors', $f)) $fields[] = $f;
  }
  $has_slug = in_array('slug', $fields, true);
}

$has_bio_raw  = $table_ready && pf__column_exists($pdo, 'contributors', 'bio_raw');
$has_bio_html = $table_ready && pf__column_exists($pdo, 'contributors', 'bio_html');

$pub_col = null;
if ($table_ready && pf__column_exists($pdo, 'contributors', 'is_public')) $pub_col = 'is_public';
elseif ($table_ready && pf__column_exists($pdo, 'contributors', 'visible')) $pub_col = 'visible';

$nothing_writable = $table_ready && empty($fields) && !$has_bio_raw && !$has_bio_html && !$pub_col;

/* Header */
$active_nav = 'contributors';
$page_title = 'New Contributor • Staff';
$page_desc  = 'Create a new contributor profile.';

$staff_subnav = [
  ['label' => 'Dashboard',    'href' => pf__u('/staff/'),              'active' => false],
  ['label' => 'Contributors', 'href' => pf__u('/staff/contributors/'), 'active' => true],
  ['label' => 'Public',       'href' => pf__u('/contributors/'),       'active' => false],
];

require_once APP_ROOT . '/private/shared/staff_header.php';

$create_post = pf__u('/staff/contributors/create.php');
$back_url    = pf__u($return);

?>
<div class="container">

  <div class="hero">
    <div class="hero__row">
      <div>
        <h1 class="hero__title">New Contributor</h1>
        <p class="hero__sub">Create a contributor profile (schema-tolerant).</p>
      </div>
      <div class="hero__actions">
        <a class="btn btn--ghost" href="<?php echo h($back_url); ?>">← Back</a>
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

  <?php if ($nothing_writable): ?>
    <div class="alert alert--warning">
      Contributors table exists, but no recognized writable columns were found.
      Add at least one of: <code>display_name</code>, <code>email</code>, <code>roles</code>, <code>status</code>.
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card__body">
      <form method="post" action="<?php echo h($create_post); ?>" class="stack">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="return" value="<?php echo h($return); ?>">

        <div class="grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:14px;">

          <?php foreach ($fields as $f): ?>
            <div class="field">
              <label class="label" for="<?php echo h($f); ?>"><?php echo h(pf__label($f)); ?></label>
              <input
                class="input"
                id="<?php echo h($f); ?>"
                name="<?php echo h($f); ?>"
                value=""
                placeholder="<?php echo h($f === 'slug' ? 'Optional (auto-generated if blank)' : ''); ?>">
              <?php if ($f === 'slug'): ?>
                <div class="muted" style="font-size:.9rem; margin-top:6px;">
                  If you leave slug blank, it will be generated from the display name and kept unique automatically.
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>

          <?php if ($pub_col): ?>
            <div class="field" style="align-self:flex-end;">
              <label class="check">
                <input type="checkbox" name="<?php echo h($pub_col); ?>" value="1">
                <span>Public (visible on site)</span>
              </label>
            </div>
          <?php endif; ?>

        </div>

        <?php if ($has_bio_raw || $has_bio_html): ?>
          <div class="field">
            <label class="label" for="bio_raw">Bio (rich text allowed)</label>
            <textarea id="bio_raw" name="bio_raw" rows="10" class="input" style="width:100%;"></textarea>
            <div class="muted" style="font-size:.9rem; margin-top:6px;">
              Saved as <code>bio_raw</code> and rendered publicly from sanitized <code>bio_html</code>.
            </div>
          </div>
        <?php endif; ?>

        <div class="row row--gap">
          <button class="btn btn--primary" type="submit" <?php echo ($table_ready && !$nothing_writable) ? '' : 'disabled'; ?>>
            Create Contributor
          </button>
          <a class="btn btn--ghost" href="<?php echo h($back_url); ?>">Cancel</a>
        </div>

      </form>
    </div>
  </div>
</div>

<?php require APP_ROOT . '/private/shared/staff_footer.php'; ?>
