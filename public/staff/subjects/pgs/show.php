<?php
declare(strict_types=1);

/**
 * /public/staff/subjects/pgs/show.php
 * Staff: View a single page details.
 */

/* ---------------------------------------------------------
   Locate initialize.php (bounded upward scan)
--------------------------------------------------------- */
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


/* Auth */
if (function_exists('require_staff')) {
  require_staff();
} elseif (function_exists('require_login')) {
  require_login();
}

/* Helpers */
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

/* Flash */
if (!function_exists('pf__flash_get')) {
  function pf__flash_get(string $key): string {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $msg = '';
    if (isset($_SESSION['flash']) && is_array($_SESSION['flash']) && isset($_SESSION['flash'][$key])) {
      $msg = (string)$_SESSION['flash'][$key];
      unset($_SESSION['flash'][$key]);
    }
    return $msg;
  }
}

/* url_for() fallback */
if (!function_exists('url_for')) {
  function url_for(string $script_path): string {
    if ($script_path === '') return $script_path;
    if ($script_path[0] !== '/') $script_path = '/' . $script_path;
    if (defined('WWW_ROOT') && is_string(WWW_ROOT) && WWW_ROOT !== '') {
      return rtrim(WWW_ROOT, '/') . $script_path;
    }
    return $script_path;
  }
}

/* DB */
$pdo = function_exists('db') ? db() : null;
if (!$pdo instanceof PDO) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Database handle db() not available.\n";
  exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  redirect_to(url_for('/staff/subjects/pgs/index.php'));
}

$return = pf__safe_return_url((string)($_GET['return'] ?? ''), '/staff/subjects/pgs/index.php');

$notice = pf__flash_get('notice');
$error  = pf__flash_get('error');

/* Fetch page + subject title */
$sub_has_menu = false;
$sub_has_name = false;

try {
  $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='subjects' AND COLUMN_NAME='menu_name'");
  $st->execute(); $sub_has_menu = ((int)$st->fetchColumn() > 0);

  $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='subjects' AND COLUMN_NAME='name'");
  $st->execute(); $sub_has_name = ((int)$st->fetchColumn() > 0);
} catch (Throwable $e) {
  // ignore
}

$sub_title_expr = $sub_has_menu ? 's.menu_name' : ($sub_has_name ? 's.name' : "CONCAT('Subject #', s.id)");

$sql = "
  SELECT
    p.id, p.subject_id, p.title, p.slug, p.body, p.nav_order, p.is_public, p.created_at, p.updated_at,
    {$sub_title_expr} AS subject_title
  FROM pages p
  LEFT JOIN subjects s ON s.id = p.subject_id
  WHERE p.id = :id
  LIMIT 1
";
$st = $pdo->prepare($sql);
$st->execute([':id' => $id]);
$page = $st->fetch(PDO::FETCH_ASSOC);

if (!$page) {
  if (function_exists('pf__flash_set')) {
    pf__flash_set('error', 'Page not found.');
  }
  redirect_to(url_for($return));
}

/* URLs */
$index_url = url_for('/staff/subjects/pgs/index.php');

$edit_url = url_for('/staff/subjects/pgs/edit.php')
  . '?id=' . rawurlencode((string)$id) . '&return=' . rawurlencode($return);

$del_url = url_for('/staff/subjects/pgs/delete.php')
  . '?id=' . rawurlencode((string)$id) . '&return=' . rawurlencode($return);

/* Public preview link (best-effort) */
$public_preview = '';
if (isset($page['slug'], $page['subject_id']) && is_string($page['slug'])) {
  $sub_slug = '';
  try {
    $st = $pdo->prepare("SELECT slug FROM subjects WHERE id = :sid LIMIT 1");
    $st->execute([':sid' => (int)$page['subject_id']]);
    $sub_slug = (string)($st->fetchColumn() ?: '');
  } catch (Throwable $e) {}

  if ($sub_slug !== '' && $page['slug'] !== '') {
    $public_preview = url_for('/subjects/' . rawurlencode($sub_slug) . '/' . rawurlencode((string)$page['slug']) . '/');
  }
}

/* Render header */
$active_nav = 'pgs';
$page_title = 'View Page • Staff';

$staff_subnav = [
  ['label' => 'Dashboard', 'href' => url_for('/staff/index.php'), 'active' => false],
  ['label' => 'Pages',     'href' => $index_url,                 'active' => true],
  ['label' => 'Subjects',  'href' => url_for('/staff/subjects/index.php'), 'active' => false],
  ['label' => 'Tools',     'href' => url_for('/staff/tools/diagnostics.php'), 'active' => false],
];

require_once APP_ROOT . '/private/shared/staff_header.php';

?>
<div class="container">

  <div class="hero">
    <div class="hero__row">
      <div>
        <h1 class="hero__title">Page</h1>
        <p class="hero__sub"><?php echo h((string)$page['title']); ?></p>
      </div>
      <div class="hero__actions">
        <a class="btn btn--ghost" href="<?php echo h(url_for($return)); ?>">← Back</a>
        <a class="btn" href="<?php echo h($edit_url); ?>">Edit</a>
        <a class="btn btn--danger" href="<?php echo h($del_url); ?>">Delete</a>
      </div>
    </div>
  </div>

  <?php if ($notice !== ''): ?><div class="alert alert--success"><?php echo h($notice); ?></div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="alert alert--danger"><?php echo h($error); ?></div><?php endif; ?>

  <div class="card">
    <div class="card__body stack">

      <div class="row row--wrap row--gap">
        <div class="pill <?php echo ((int)$page['is_public'] === 1) ? 'pill--success' : 'pill--muted'; ?>">
          <?php echo ((int)$page['is_public'] === 1) ? 'Public' : 'Draft'; ?>
        </div>

        <?php if ($public_preview !== ''): ?>
          <a class="btn btn--sm" href="<?php echo h($public_preview); ?>" target="_blank" rel="noopener">Preview public</a>
        <?php endif; ?>
      </div>

      <div class="grid" style="gap:12px;">
        <div><span class="muted">ID</span><div class="strong"><?php echo h((string)$page['id']); ?></div></div>
        <div><span class="muted">Subject</span><div class="strong"><?php echo h((string)($page['subject_title'] ?? '')); ?></div></div>
        <div><span class="muted">Slug</span><div class="mono"><?php echo h((string)$page['slug']); ?></div></div>
        <div><span class="muted">Nav order</span><div class="mono"><?php echo h((string)($page['nav_order'] ?? '')); ?></div></div>
        <div><span class="muted">Created</span><div class="mono"><?php echo h((string)($page['created_at'] ?? '')); ?></div></div>
        <div><span class="muted">Updated</span><div class="mono"><?php echo h((string)($page['updated_at'] ?? '')); ?></div></div>
      </div>

      <hr class="sep">

      <div>
        <div class="muted" style="margin-bottom:6px;">Body</div>
        <div class="card" style="margin:0;">
          <div class="card__body">
            <?php
              $body = (string)($page['body'] ?? '');
              echo $body !== '' ? nl2br(h($body)) : '<span class="muted">— empty —</span>';
            ?>
          </div>
        </div>
      </div>

    </div>
  </div>

</div>

<?php
$staff_footer = APP_ROOT . '/private/shared/staff_footer.php';
if (is_file($staff_footer)) require $staff_footer;
else echo "</main></body></html>";
