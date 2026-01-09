<?php
declare(strict_types=1);

/**
 * /public/contributors/index.php
 * Public: Contributors landing (premium, schema-tolerant)
 *
 * Visibility rule:
 * - If contributors.status exists: show only status='active'
 * - Else if contributors.is_public exists: show only is_public=1
 *
 * Linking rule:
 * - Prefer pretty URL /contributors/{slug}/ when slug exists
 * - Otherwise fall back to /contributors/view.php?id={id} (reliable even without slug)
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
}

$init = mk_find_init(__DIR__);
if (!$init) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Init not found.\nStart: " . __DIR__ . "\n";
  exit;
}
require_once $init;

/* ---------------------------------------------------------
   Fallback helpers
--------------------------------------------------------- */
if (!function_exists('h')) {
  function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('pf__u')) {
  function pf__u(string $path): string {
    return function_exists('url_for') ? url_for($path) : $path;
  }
}

/* ---------------------------------------------------------
   Schema helpers
--------------------------------------------------------- */
if (!function_exists('pf__column_exists')) {
  function pf__column_exists(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $key = strtolower($table . '.' . $column);
    if (array_key_exists($key, $cache)) return (bool)$cache[$key];

    try {
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
    } catch (Throwable $e) {
      $cache[$key] = false;
      return false;
    }
  }
}
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

/* ---------------------------------------------------------
   Shared header/footer resolver (for /app/mkomigbo layout)
--------------------------------------------------------- */
if (!function_exists('mk_find_shared_file')) {
  function mk_find_shared_file(string $filename, string $startDir, int $maxDepth = 14): ?string {
    $dir = $startDir;
    for ($i = 0; $i <= $maxDepth; $i++) {
      $candidates = [
        $dir . '/private/shared/' . $filename,
        $dir . '/app/mkomigbo/private/shared/' . $filename,
        $dir . '/app/private/shared/' . $filename,
      ];
      foreach ($candidates as $p) {
        if (is_file($p)) return $p;
      }
      $parent = dirname($dir);
      if ($parent === $dir) break;
      $dir = $parent;
    }
    return null;
  }
}

if (!function_exists('pf__include_public_header')) {
  function pf__include_public_header(): void {
    $names = ['contributors_header.php', 'contributor_header.php', 'public_header.php'];
    foreach ($names as $name) {
      $p = mk_find_shared_file($name, __DIR__, 14);
      if ($p && is_file($p)) { require_once $p; return; }
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Header include missing.\n";
    exit;
  }
}

if (!function_exists('pf__include_public_footer')) {
  function pf__include_public_footer(): void {
    $names = ['contributors_footer.php', 'contributor_footer.php', 'public_footer.php'];
    foreach ($names as $name) {
      $p = mk_find_shared_file($name, __DIR__, 14);
      if ($p && is_file($p)) { require_once $p; return; }
    }
  }
}

/* ---------------------------------------------------------
   Page vars for header contract
--------------------------------------------------------- */
$page_title  = 'Contributors — Mkomigbo';
$page_desc   = 'Authors, editors, researchers, and collaborators helping to build and refine Mkomigbo.';
$nav_active  = 'contributors';
$active_nav  = 'contributors';

$extra_css = [];
$extra_css[] = pf__u('/lib/css/ui.css');
$extra_css[] = pf__u('/lib/css/subjects.css');

/* ---------------------------------------------------------
   Fetch contributors (schema-tolerant, visibility-aware)
--------------------------------------------------------- */
$items = [];
$note  = '';

try {
  $pdo = function_exists('db') ? db() : null;
  if (!$pdo instanceof PDO) throw new RuntimeException('DB not available.');

  if (!pf__table_exists($pdo, 'contributors')) {
    $items = [];
  } else {
    $has_display = pf__column_exists($pdo, 'contributors', 'display_name');
    $has_name    = pf__column_exists($pdo, 'contributors', 'name');
    $has_user    = pf__column_exists($pdo, 'contributors', 'username');
    $has_email   = pf__column_exists($pdo, 'contributors', 'email');
    $has_slug    = pf__column_exists($pdo, 'contributors', 'slug');

    $has_status  = pf__column_exists($pdo, 'contributors', 'status');     // preferred
    $has_public  = pf__column_exists($pdo, 'contributors', 'is_public');  // fallback

    // Decide display name column
    $display_col = '';
    if ($has_display) $display_col = 'display_name';
    elseif ($has_name) $display_col = 'name';
    elseif ($has_user) $display_col = 'username';
    elseif ($has_email) $display_col = 'email';
    else $display_col = '';

    $select = "id";
    if ($display_col !== '') $select .= ", `{$display_col}` AS display_name";
    else $select .= ", CAST(id AS CHAR) AS display_name";

    if ($has_slug) $select .= ", `slug` AS slug";
    else $select .= ", NULL AS slug";

    $sql = "SELECT {$select} FROM contributors";

    if ($has_status) {
      $sql .= " WHERE status = 'active'";
    } elseif ($has_public) {
      $sql .= " WHERE is_public = 1";
    }

    $sql .= " ORDER BY id ASC LIMIT 60";
    $items = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
} catch (Throwable $e) {
  $note = $e->getMessage();
  $items = [];
}

/* Helpers */
if (!function_exists('pf__initial')) {
  function pf__initial(string $name): string {
    $name = trim($name);
    if ($name === '') return 'C';
    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
      return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
    }
    return strtoupper(substr($name, 0, 1));
  }
}

if (!function_exists('pf__profile_href')) {
  /**
   * Prefer pretty URL when slug exists; otherwise use view.php?id=
   */
  function pf__profile_href(array $c): string {
    $id = (int)($c['id'] ?? 0);
    $slug = trim((string)($c['slug'] ?? ''));

    if ($slug !== '') {
      $path = '/contributors/' . rawurlencode($slug) . '/';
      return function_exists('url_for') ? url_for($path) : $path;
    }

    $path = '/contributors/view.php?id=' . rawurlencode((string)$id);
    return function_exists('url_for') ? url_for($path) : $path;
  }
}

/* ---------------------------------------------------------
   Render
--------------------------------------------------------- */
pf__include_public_header();
?>

<section class="hero">
  <div class="hero-bar"></div>
  <div class="hero-inner">
    <h1>Contributors</h1>
    <p class="muted" style="margin:6px 0 0; max-width:88ch; line-height:1.75;">
      Authors, editors, researchers, and collaborators helping to build and refine Mkomigbo.
      Over time, each article will credit its contributors.
    </p>

    <?php
      $ql_title = 'Quick links';
      $ql_tip = null;
      $ql_include_staff = false;

      $ql_candidates = [];
      if (defined('APP_ROOT') && is_string(APP_ROOT) && APP_ROOT !== '') {
        $ql_candidates[] = APP_ROOT . '/private/shared/quick_links.php';
      }
      if (defined('PRIVATE_PATH') && is_string(PRIVATE_PATH) && PRIVATE_PATH !== '') {
        $ql_candidates[] = PRIVATE_PATH . '/shared/quick_links.php';
      }
      foreach ($ql_candidates as $qp) {
        if (is_file($qp)) { require $qp; break; }
      }
    ?>

    <div class="hero-kpi" style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
      <span class="pill"><?php echo h((string)count($items)); ?> listed</span>
      <span class="pill">Profiles</span>
      <span class="pill">Credits</span>
    </div>

    <?php if ($note !== ''): ?>
      <p class="warn" style="margin-top:12px; color:#b02a37;"><?php echo h($note); ?></p>
    <?php endif; ?>
  </div>
</section>

<div class="section-title" style="margin:24px 0 10px; display:flex; align-items:baseline; justify-content:space-between; gap:10px; flex-wrap:wrap;">
  <h2 style="margin:0; font-size:1.2rem;">Featured contributors</h2>
</div>

<?php if (!is_array($items) || count($items) === 0): ?>
  <div class="card" style="border:1px solid rgba(0,0,0,.10); border-radius:18px; background:#fff; padding:14px; box-shadow: 0 12px 28px rgba(0,0,0,.05);">
    <div class="muted" style="line-height:1.7;">
      <div style="font-weight:800; color:#111; margin-bottom:6px;">No active contributors yet</div>
      <div>
        This section will populate automatically once staff publish contributor profiles
        (set <code>status</code> to <code>active</code>).
      </div>
    </div>

    <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
      <a class="btn" href="<?php echo h(pf__u('/subjects/')); ?>">Explore Subjects</a>
      <a class="btn" href="<?php echo h(pf__u('/platforms/')); ?>">Platforms</a>
      <a class="btn" href="<?php echo h(pf__u('/')); ?>">Home</a>
    </div>
  </div>
<?php else: ?>
  <section class="grid" style="margin-top:12px; display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:14px;">
    <?php foreach ($items as $c): ?>
      <?php
        $name = trim((string)($c['display_name'] ?? 'Contributor'));
        if ($name === '') $name = 'Contributor';

        $href = pf__profile_href($c);
        $initial = pf__initial($name);

        $rawSlug = trim((string)($c['slug'] ?? ''));
        $id      = (string)($c['id'] ?? '');
        $subline = ($rawSlug !== '') ? $rawSlug : (($id !== '') ? ('id: ' . $id) : 'profile coming soon');
      ?>
      <div class="card" style="border:1px solid rgba(0,0,0,.10); border-radius:18px; background:#fff; overflow:hidden; box-shadow: 0 12px 28px rgba(0,0,0,.05); min-height:140px;">
        <div class="card-bar" style="height:7px; background: var(--accent, #111);"></div>
        <a class="stretch" href="<?php echo h($href); ?>" style="text-decoration:none; color:inherit; display:block; height:100%;">
          <div class="card-body" style="padding:14px; display:flex; flex-direction:column; gap:10px;">
            <div class="top" style="display:flex; gap:12px; align-items:flex-start;">
              <div class="avatar" aria-hidden="true" style="width:56px; height:56px; border-radius:16px; border:1px solid rgba(0,0,0,.10); background: rgba(0,0,0,0.02); display:flex; align-items:center; justify-content:center; font-weight:900;">
                <?php echo h($initial); ?>
              </div>
              <div style="min-width:0;">
                <h3 style="margin:2px 0 6px; font-size:1.1rem; line-height:1.2;"><?php echo h($name); ?></h3>
                <p class="muted" style="margin-top:-2px; font-size:.92rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                  <?php echo h($subline); ?>
                </p>
              </div>
            </div>
            <div class="meta" style="margin-top:auto; display:flex; gap:10px; flex-wrap:wrap;">
              <span class="pill">Profile</span>
              <span class="pill">Credits</span>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </section>
<?php endif; ?>

<?php
pf__include_public_footer();
