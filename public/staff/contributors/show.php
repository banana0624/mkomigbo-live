<?php
declare(strict_types=1);

/**
 * /public/staff/contributors/show.php
 * Staff: Show contributor (aligned to real schema)
 *
 * - Uses contributors.status (active/draft)
 * - Renders bio_html (sanitized) only
 * - Fallback: render bio_raw/bio as plain text (escaped), never as HTML
 * - No arrow functions
 */

require_once __DIR__ . '/../_init.php';

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
if (!function_exists('pf__u')) {
  function pf__u(string $path): string {
    return function_exists('url_for') ? url_for($path) : $path;
  }
}
if (!function_exists('pf__quote_ident')) {
  function pf__quote_ident(string $col): string {
    return '`' . str_replace('`', '', $col) . '`';
  }
}
if (!function_exists('pf__pill_for_status')) {
  function pf__pill_for_status(?string $status): array {
    $s = strtolower(trim((string)$status));
    if ($s === '') $s = 'active';

    if ($s === 'active' || $s === 'published' || $s === 'public') {
      return ['text' => 'Active', 'class' => 'pill pill--success'];
    }
    if ($s === 'draft' || $s === 'inactive' || $s === 'hidden') {
      return ['text' => 'Draft', 'class' => 'pill pill--muted'];
    }
    return ['text' => strtoupper($s), 'class' => 'pill pill--muted'];
  }
}

/**
 * Roles display:
 * - If roles looks like JSON array, show as "A, B, C"
 * - Else show raw string
 */
if (!function_exists('pf__roles_display')) {
  function pf__roles_display(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') return '';
    if (isset($raw[0]) && $raw[0] === '[') {
      $decoded = json_decode($raw, true);
      if (is_array($decoded)) {
        $out = [];
        foreach ($decoded as $v) {
          $v = trim((string)$v);
          if ($v !== '') $out[] = $v;
        }
        $out = array_values(array_unique($out));
        return implode(', ', $out);
      }
    }
    return $raw;
  }
}

$pdo = function_exists('staff_pdo') ? staff_pdo() : (function_exists('db') ? db() : null);
if (!$pdo instanceof PDO) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Database handle not available.\n";
  exit;
}

$id = (int)($_GET['id'] ?? 0);
$default_return = '/staff/contributors/index.php';
$return = pf__safe_return_url((string)($_GET['return'] ?? $default_return), $default_return);

if ($id <= 0) redirect_to($return);

$notice = pf__flash_get('notice');
$error  = pf__flash_get('error');

$contributor = null;
$warn = '';

/* ---------------------------------------------------------
   Load contributor (schema-tolerant)
--------------------------------------------------------- */
try {
  if (!pf__table_exists($pdo, 'contributors')) {
    $warn = 'Table "contributors" not found yet.';
  } else {
    $select = ['id'];
    $wanted = [
      'display_name','email','roles','status',
      'created_at','updated_at',
      'bio_raw','bio_html',
      'name','username','slug','avatar_path','bio'
    ];

    foreach ($wanted as $c) {
      if (pf__column_exists($pdo, 'contributors', $c)) $select[] = $c;
    }

    $select = array_values(array_unique($select));
    $cols = [];
    foreach ($select as $c) { $cols[] = pf__quote_ident((string)$c); }
    $cols_sql = implode(', ', $cols);

    $sql = "SELECT {$cols_sql} FROM contributors WHERE id = ? LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([$id]);
    $contributor = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$contributor) $warn = 'Contributor not found.';
  }
} catch (Throwable $e) {
  $warn = 'Failed to load contributor.';
}

/* Name */
$name = 'Contributor #' . $id;
if ($contributor) {
  $name = (string)(
    $contributor['display_name']
    ?? $contributor['name']
    ?? $contributor['username']
    ?? $contributor['email']
    ?? $contributor['slug']
    ?? $name
  );
  $name = trim($name) !== '' ? $name : ('Contributor #' . $id);
}

/* Header */
$active_nav = 'contributors';
$page_title = $name . ' • Contributor • Staff';
$page_desc  = 'View contributor details.';

$staff_subnav = [
  ['label' => 'Dashboard',    'href' => pf__u('/staff/'),              'active' => false],
  ['label' => 'Contributors', 'href' => pf__u('/staff/contributors/'), 'active' => true],
  ['label' => 'Public',       'href' => pf__u('/contributors/'),       'active' => false],
];

require_once APP_ROOT . '/private/shared/staff_header.php';

$back_url = pf__u($return);
$edit_url = pf__u('/staff/contributors/edit.php') . '?id=' . rawurlencode((string)$id) . '&return=' . rawurlencode($return);
$del_url  = pf__u('/staff/contributors/delete.php') . '?id=' . rawurlencode((string)$id) . '&return=' . rawurlencode($return);

/* Status pill */
$status_val = ($contributor && array_key_exists('status', $contributor)) ? (string)$contributor['status'] : 'active';
$status_pill = pf__pill_for_status($status_val);

/* Bio rendering rule:
   - Render bio_html (already sanitized) if present
   - Else show bio_raw or legacy bio as escaped text (never as HTML)
*/
$bio_html = '';
$bio_text = '';
if ($contributor) {
  if (isset($contributor['bio_html']) && trim((string)$contributor['bio_html']) !== '') {
    $bio_html = (string)$contributor['bio_html'];
  } else {
    $candidate = '';
    if (isset($contributor['bio_raw']) && trim((string)$contributor['bio_raw']) !== '') $candidate = (string)$contributor['bio_raw'];
    elseif (isset($contributor['bio']) && trim((string)$contributor['bio']) !== '')   $candidate = (string)$contributor['bio'];
    $bio_text = $candidate;
  }
}

$created_at = ($contributor && array_key_exists('created_at', $contributor)) ? (string)$contributor['created_at'] : '';
$updated_at = ($contributor && array_key_exists('updated_at', $contributor)) ? (string)$contributor['updated_at'] : '';

$roles_display = '';
if ($contributor && array_key_exists('roles', $contributor) && trim((string)$contributor['roles']) !== '') {
  $roles_display = pf__roles_display((string)$contributor['roles']);
}

$slug_val = ($contributor && array_key_exists('slug', $contributor)) ? trim((string)$contributor['slug']) : '';

?>
<div class="container">

  <div class="hero">
    <div class="hero__row">
      <div>
        <h1 class="hero__title"><?php echo h($name); ?></h1>
        <p class="hero__sub">Contributor profile details.</p>
      </div>
      <div class="hero__actions">
        <a class="btn btn--ghost" href="<?php echo h($back_url); ?>">← Back</a>
        <a class="btn" href="<?php echo h($edit_url); ?>">Edit</a>
        <a class="btn btn--danger" href="<?php echo h($del_url); ?>">Delete</a>
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
        <p class="muted">No contributor data to display.</p>
      <?php else: ?>

        <div class="grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:14px;">

          <div>
            <div class="muted small">ID</div>
            <div class="strong mono"><?php echo h((string)$id); ?></div>
          </div>

          <div>
            <div class="muted small">Status</div>
            <span class="<?php echo h($status_pill['class']); ?>"><?php echo h($status_pill['text']); ?></span>
          </div>

          <?php if ($slug_val !== ''): ?>
            <div>
              <div class="muted small">Slug</div>
              <div class="mono"><?php echo h($slug_val); ?></div>
            </div>
          <?php endif; ?>

          <?php if (array_key_exists('email', $contributor) && trim((string)$contributor['email']) !== ''): ?>
            <div>
              <div class="muted small">Email</div>
              <div class="mono"><?php echo h((string)$contributor['email']); ?></div>
            </div>
          <?php endif; ?>

          <?php if ($roles_display !== ''): ?>
            <div>
              <div class="muted small">Roles</div>
              <div><?php echo h($roles_display); ?></div>
            </div>
          <?php endif; ?>

          <?php foreach (['username','avatar_path'] as $k): ?>
            <?php if (array_key_exists($k, $contributor) && trim((string)$contributor[$k]) !== ''): ?>
              <div>
                <div class="muted small"><?php echo h($k); ?></div>
                <div class="mono"><?php echo h((string)$contributor[$k]); ?></div>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>

          <?php if ($created_at !== '' || $updated_at !== ''): ?>
            <div>
              <div class="muted small">Timestamps</div>
              <div class="mono">
                <?php if ($created_at !== ''): ?>
                  <div>created: <?php echo h($created_at); ?></div>
                <?php endif; ?>
                <?php if ($updated_at !== ''): ?>
                  <div>updated: <?php echo h($updated_at); ?></div>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

        </div>

        <hr class="sep">

        <h3 style="margin:0 0 10px;">Bio</h3>

        <?php if ($bio_html !== ''): ?>
          <div class="muted" style="line-height:1.75;">
            <?php echo $bio_html; ?>
          </div>
        <?php elseif (trim($bio_text) !== ''): ?>
          <div class="muted" style="line-height:1.75; white-space:pre-wrap;">
            <?php echo h($bio_text); ?>
          </div>
        <?php else: ?>
          <p class="muted">(No bio yet)</p>
        <?php endif; ?>

      <?php endif; ?>

    </div>
  </div>
</div>

<?php require APP_ROOT . '/private/shared/staff_footer.php'; ?>
