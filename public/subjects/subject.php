<?php
declare(strict_types=1);

/**
 * /public_html/public/subjects/subject.php
 * Subject landing page:
 *   /subjects/{slug}/  -> via view.php router, or
 *   /subjects/subject.php?slug={slug} (real-file, LiteSpeed-safe)
 *
 * Performance:
 * - schema checks cached per-request
 * - avoids repeated information_schema calls
 *
 * Branding:
 * - Brand: "Mkomi Igbo" (display)
 * - Identifier: "mkomigbo" (system/domain continuity)
 */

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

require_once __DIR__ . '/../_init.php';

/* ---------------------------------------------------------
 * Helpers
 * --------------------------------------------------------- */
if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('redirect_to')) {
  function redirect_to(string $location, int $code = 302): void {
    $location = str_replace(["\r", "\n"], '', $location);
    header('Location: ' . $location, true, $code);
    exit;
  }
}

if (!function_exists('mk_subjects_not_found')) {
  function mk_subjects_not_found(string $title = 'Not Found', string $message = 'The page you requested does not exist.'): void {
    http_response_code(404);

    // Prefer Subjects header to ensure CSS bundle is present
    if (function_exists('mk_require_shared')) {
      mk_view_set([
        'page_title'  => $title . ' • Subjects • ' . (defined('MK_BRAND_NAME') ? (string)MK_BRAND_NAME : 'Mkomi Igbo'),
        'page_desc'   => $message,
        'active_nav'  => 'subjects',
        'nav_active'  => 'subjects',
        'brand_name'  => defined('MK_BRAND_NAME') ? (string)MK_BRAND_NAME : 'Mkomi Igbo',
        'site_name'   => defined('MK_SITE_NAME')  ? (string)MK_SITE_NAME  : 'mkomigbo',
      ]);

      mk_require_shared('subjects_header.php');

      echo '<div class="container" style="padding:18px 0;">';
      echo '  <header class="mk-hero" style="margin-top:14px;">';
      echo '    <div class="mk-hero__bar" aria-hidden="true"></div>';
      echo '    <div class="mk-hero__inner">';
      echo '      <h1 class="mk-hero__title">' . h($title) . '</h1>';
      echo '      <p class="mk-muted" style="margin-top:8px;">' . h($message) . '</p>';
      echo '      <p style="margin-top:12px;"><a class="mk-btn" href="/subjects/">← Back to Subjects</a></p>';
      echo '    </div>';
      echo '  </header>';
      echo '</div>';

      mk_require_shared('public_footer.php');
      exit;
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo "404 - " . $title;
    exit;
  }
}

/* ---------------------------------------------------------
 * Input
 * --------------------------------------------------------- */
$subject_slug = '';
if (isset($_GET['slug']) && is_string($_GET['slug'])) $subject_slug = trim($_GET['slug']);
if ($subject_slug === '' && isset($_GET['subject']) && is_string($_GET['subject'])) $subject_slug = trim($_GET['subject']);
$subject_slug = strtolower($subject_slug);

if ($subject_slug === '') redirect_to('/subjects/', 302);

if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,190}$/', $subject_slug)) {
  mk_subjects_not_found('Subject not found', 'Invalid subject slug.');
}

/* ---------------------------------------------------------
 * DB + schema helpers (cached per request)
 * --------------------------------------------------------- */
$column_exists = static function (PDO $pdo, string $table, string $column): bool {
  static $cache = [];
  $key = strtolower($table . '.' . $column);
  if (array_key_exists($key, $cache)) return (bool)$cache[$key];

  try {
    $st = $pdo->prepare("
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
      LIMIT 1
    ");
    $st->execute([$table, $column]);
    $cache[$key] = (bool)$st->fetchColumn();
    return (bool)$cache[$key];
  } catch (Throwable $e) {
    $cache[$key] = false;
    return false;
  }
};

/* ---------------------------------------------------------
 * Load subject + pages
 * --------------------------------------------------------- */
$subject = null;
$pages = [];
$db_error = '';

try {
  if (!function_exists('db')) throw new RuntimeException('db() not available.');
  $pdo = db();
  if (!$pdo instanceof PDO) throw new RuntimeException('DB connection not available.');

  // Best available subject display columns
  $nameCol = 'name';
  if (!$column_exists($pdo, 'subjects', 'name')) {
    if ($column_exists($pdo, 'subjects', 'menu_name')) $nameCol = 'menu_name';
    elseif ($column_exists($pdo, 'subjects', 'subject_name')) $nameCol = 'subject_name';
    else $nameCol = 'slug';
  }

  $descCol = null;
  if ($column_exists($pdo, 'subjects', 'meta_description')) $descCol = 'meta_description';
  elseif ($column_exists($pdo, 'subjects', 'description')) $descCol = 'description';
  elseif ($column_exists($pdo, 'subjects', 'content')) $descCol = 'content';

  $cols = ['id', 'slug', "{$nameCol} AS name"];
  if ($descCol) $cols[] = "{$descCol} AS description";

  $st = $pdo->prepare("SELECT " . implode(', ', $cols) . " FROM subjects WHERE slug = ? LIMIT 1");
  $st->execute([$subject_slug]);
  $subject = $st->fetch(PDO::FETCH_ASSOC) ?: null;

  if ($subject) {
    $sid = (int)($subject['id'] ?? 0);

    // Best available page title column
    $titleCol = 'menu_name';
    if (!$column_exists($pdo, 'pages', 'menu_name')) {
      $titleCol = $column_exists($pdo, 'pages', 'title') ? 'title' : 'slug';
    }

    // Visibility conditions
    $where = "WHERE subject_id = ?";
    if ($column_exists($pdo, 'pages', 'status')) {
      $where .= " AND status IN ('active','published','public')";
    } elseif ($column_exists($pdo, 'pages', 'is_public')) {
      $where .= " AND is_public = 1";
    } elseif ($column_exists($pdo, 'pages', 'visible')) {
      $where .= " AND visible = 1";
    }

    // Ordering
    $orderCol = $column_exists($pdo, 'pages', 'nav_order') ? 'nav_order'
             : ($column_exists($pdo, 'pages', 'position') ? 'position' : 'id');

    $pst = $pdo->prepare("
      SELECT id, slug, {$titleCol} AS title
      FROM pages
      {$where}
      ORDER BY {$orderCol} IS NULL, {$orderCol} ASC, id ASC
    ");
    $pst->execute([$sid]);
    $pages = $pst->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

} catch (Throwable $e) {
  $db_error = $e->getMessage();
}

/* ---------------------------------------------------------
 * Not found / header vars
 * --------------------------------------------------------- */
if (!$subject) {
  // If DB is down, this is still a 404-ish user experience, but preserve the message
  $msg = ($db_error !== '')
    ? 'Temporarily unable to load this subject.'
    : ('No subject matched the requested slug: ' . $subject_slug);
  mk_subjects_not_found('Subject not found', $msg);
}

$s_slug = (string)($subject['slug'] ?? $subject_slug);
$s_name = (string)($subject['name'] ?? $s_slug);
$s_desc = trim((string)($subject['description'] ?? ''));

// Page meta
$brand = defined('MK_BRAND_NAME') ? (string)MK_BRAND_NAME : 'Mkomi Igbo';
$page_title = $s_name . ' • Subjects • ' . $brand;
$page_desc  = $s_desc !== '' ? $s_desc : 'Browse pages under this subject.';

// Ensure nav
$active_nav = 'subjects';
$nav_active = 'subjects';

// Provide brand/site to header
$brand_name = $brand;
$site_name  = defined('MK_SITE_NAME') ? (string)MK_SITE_NAME : 'mkomigbo';

// IMPORTANT: load Subjects header bundle (fixes missing deep styles)
if (function_exists('mk_require_shared')) {
  mk_view_set([
    'page_title'  => $page_title,
    'page_desc'   => $page_desc,
    'active_nav'  => $active_nav,
    'nav_active'  => $nav_active,
    'brand_name'  => $brand_name,
    'site_name'   => $site_name,
  ]);
  mk_require_shared('subjects_header.php');
} else {
  if (defined('APP_ROOT')) {
    $hdr = APP_ROOT . '/private/shared/subjects_header.php';
    if (is_file($hdr)) require $hdr;
  }
}

// Optional diagnostic banner (keep; safe)
if ($db_error !== '') {
  echo '<div class="notice error" style="margin:12px 0;"><strong>DB Error:</strong> ' . h($db_error) . '</div>';
}

?>
<div class="container" style="padding:18px 0;">

  <header class="mk-hero" style="margin-top:14px;">
    <div class="mk-hero__bar" aria-hidden="true"></div>
    <div class="mk-hero__inner">
      <h1 class="mk-hero__title"><?= h($s_name) ?></h1>
      <?php if ($s_desc !== ''): ?>
        <p class="mk-hero__subtitle"><?= h($s_desc) ?></p>
      <?php endif; ?>
    </div>
  </header>

  <?php if (count($pages) === 0): ?>
    <p class="mk-muted" style="margin-top:16px;">No pages found under this subject yet.</p>
  <?php else: ?>
    <h2 style="margin:18px 0 10px;">Pages</h2>
    <section class="mk-grid mk-grid--subjects" style="margin-top:10px;">
      <?php foreach ($pages as $p): ?>
        <?php
          $p_slug  = (string)($p['slug'] ?? '');
          if ($p_slug === '') continue;
          $p_title = trim((string)($p['title'] ?? '')) ?: $p_slug;

          // LiteSpeed-safe real-file route (canonical controller params)
          $p_href  = '/subjects/page.php?subject=' . rawurlencode($s_slug) . '&slug=' . rawurlencode($p_slug);
        ?>
        <article class="mk-card mk-subject-card">
          <div class="mk-card__bar" aria-hidden="true"></div>
          <a class="mk-card__link" href="<?= h($p_href) ?>">
            <div class="mk-card__body">
              <div class="mk-subject-card__titlewrap">
                <h2 class="mk-subject-card__title"><?= h($p_title) ?></h2>
                <div class="mk-subject-card__slug mk-muted"><?= h($p_slug) ?></div>
              </div>
              <div class="mk-card__meta" style="margin-top:12px;">
                <span class="mk-pill">Read</span>
              </div>
            </div>
          </a>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

</div>
<?php
if (function_exists('mk_require_shared')) {
  mk_require_shared('public_footer.php');
} else {
  if (defined('APP_ROOT')) {
    $f = APP_ROOT . '/private/shared/public_footer.php';
    if (is_file($f)) require $f;
  }
}
