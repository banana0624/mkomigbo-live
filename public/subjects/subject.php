<?php
declare(strict_types=1);

/**
 * /public/subjects/subject.php
 * Controller for a single subject.
 *
 * Supports:
 * - Canonical: /subjects/{slug}/  (via rewrite -> view.php?slug=...)
 * - Legacy:   /subjects/subject.php?slug={slug}
 *
 * Behavior:
 * - If accessed via legacy controller path, 301 redirect to canonical /subjects/{slug}/
 * - Robust schema tolerance for subjects/pages tables
 * - Uses shared header/footer and subjects css bundle via subjects_header.php (preferred)
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

if (!function_exists('mk_is_ssl')) {
  function mk_is_ssl(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
    if ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') return true;
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') return true;
    return false;
  }
}

if (!function_exists('mk_abs_base')) {
  function mk_abs_base(): string {
    if (defined('APP_URL') && is_string(APP_URL) && trim(APP_URL) !== '') return rtrim((string)APP_URL, '/');
    $scheme = mk_is_ssl() ? 'https' : 'http';
    $host   = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') return '';
    return $scheme . '://' . $host;
  }
}

if (!function_exists('mk_safe_redirect')) {
  function mk_safe_redirect(string $location, int $code = 302): void {
    $location = str_replace(["\r", "\n"], '', trim($location));
    if ($location === '') $location = '/';
    header('Location: ' . $location, true, $code);
    exit;
  }
}

if (!function_exists('mk_valid_slug')) {
  function mk_valid_slug(string $s): bool {
    $s = trim($s);
    return ($s !== '') && (bool)preg_match('/^[a-z0-9][a-z0-9_-]{0,190}$/', $s);
  }
}

if (!function_exists('pf__column_exists')) {
  function pf__column_exists(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $k = strtolower($table . '.' . $column);
    if (array_key_exists($k, $cache)) return (bool)$cache[$k];

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
      $cache[$k] = (bool)$st->fetchColumn();
      return (bool)$cache[$k];
    } catch (Throwable $e) {
      $cache[$k] = false;
      return false;
    }
  }
}

if (!function_exists('mk_subjects_not_found')) {
  function mk_subjects_not_found(string $title = 'Not Found', string $message = 'The page you requested does not exist.'): void {
    http_response_code(404);

    if (function_exists('mk_view_set') && function_exists('mk_require_shared')) {
      mk_view_set([
        'page_title' => $title . ' • Subjects • ' . (defined('MK_BRAND_NAME') ? (string)MK_BRAND_NAME : 'Mkomi Igbo'),
        'page_desc'  => $message,
        'active_nav' => 'subjects',
        'nav_active' => 'subjects',
      ]);

      // Prefer subjects_header so the subjects css bundle is present on 404s too
      try {
        mk_require_shared('subjects_header.php');
      } catch (Throwable $e) {
        mk_require_shared('public_header.php');
      }

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
 * Inputs
 * --------------------------------------------------------- */
$subject_slug = '';
if (isset($_GET['slug']) && is_string($_GET['slug'])) $subject_slug = trim($_GET['slug']);
if ($subject_slug === '' && isset($_GET['subject']) && is_string($_GET['subject'])) $subject_slug = trim($_GET['subject']);
$subject_slug = strtolower($subject_slug);

if (!mk_valid_slug($subject_slug)) {
  mk_subjects_not_found('Subject not found', 'Invalid subject slug.');
}

/* ---------------------------------------------------------
 * Canonical redirect
 * If someone hits /subjects/subject.php?slug=history, redirect to /subjects/history/
 * (Do NOT redirect when already on canonical route handled by /subjects/{slug}/)
 * --------------------------------------------------------- */
$reqPath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$reqPath = $reqPath !== '' ? $reqPath : '';

if (preg_match('~/(subjects/)?subject\.php$~i', $reqPath)) {
  $canonical = '/subjects/' . rawurlencode($subject_slug) . '/';
  mk_safe_redirect($canonical, 301);
}

/* ---------------------------------------------------------
 * Load subject + pages (schema tolerant)
 * --------------------------------------------------------- */
$subject  = null;
$pages    = [];
$db_error = null;

try {
  $pdo = db();

  $nameCol = 'name';
  if (!pf__column_exists($pdo, 'subjects', 'name')) {
    if (pf__column_exists($pdo, 'subjects', 'menu_name')) $nameCol = 'menu_name';
    elseif (pf__column_exists($pdo, 'subjects', 'subject_name')) $nameCol = 'subject_name';
    else $nameCol = 'slug';
  }

  $descCol = null;
  if (pf__column_exists($pdo, 'subjects', 'meta_description')) $descCol = 'meta_description';
  elseif (pf__column_exists($pdo, 'subjects', 'description')) $descCol = 'description';
  elseif (pf__column_exists($pdo, 'subjects', 'content')) $descCol = 'content';

  $cols = ['id','slug', "{$nameCol} AS name"];
  if ($descCol) $cols[] = "{$descCol} AS description";

  $st = $pdo->prepare("SELECT " . implode(', ', $cols) . " FROM subjects WHERE slug = ? LIMIT 1");
  $st->execute([$subject_slug]);
  $subject = $st->fetch(PDO::FETCH_ASSOC) ?: null;

  if ($subject) {
    $sid = (int)($subject['id'] ?? 0);

    $titleCol = 'menu_name';
    if (!pf__column_exists($pdo, 'pages', 'menu_name')) {
      $titleCol = pf__column_exists($pdo, 'pages', 'title') ? 'title' : 'slug';
    }

    $where = "WHERE subject_id = ?";
    if (pf__column_exists($pdo, 'pages', 'status')) {
      $where .= " AND status IN ('active','published','public')";
    } elseif (pf__column_exists($pdo, 'pages', 'is_public')) {
      $where .= " AND is_public = 1";
    } elseif (pf__column_exists($pdo, 'pages', 'visible')) {
      $where .= " AND visible = 1";
    }

    $orderCol = pf__column_exists($pdo, 'pages', 'nav_order') ? 'nav_order'
             : (pf__column_exists($pdo, 'pages', 'position') ? 'position' : 'id');

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
 * Not found
 * --------------------------------------------------------- */
if (!$subject) {
  mk_subjects_not_found('Subject not found', 'No subject matched the requested slug: ' . $subject_slug);
}

/* ---------------------------------------------------------
 * Header vars
 * --------------------------------------------------------- */
$brand = defined('MK_BRAND_NAME') ? (string)MK_BRAND_NAME : 'Mkomi Igbo';

$s_name = trim((string)($subject['name'] ?? $subject['slug'] ?? 'Subject'));
if ($s_name === '') $s_name = (string)$subject_slug;

$s_desc = trim((string)($subject['description'] ?? ''));

if (function_exists('mk_view_set')) {
  mk_view_set([
    'active_nav' => 'subjects',
    'nav_active' => 'subjects',
    'page_title' => $s_name . ' • Subjects • ' . $brand,
    'page_desc'  => ($s_desc !== '' ? $s_desc : 'Browse pages under this subject.'),
    // Canonical for SEO
    'canonical'  => (mk_abs_base() !== '' ? (mk_abs_base() . '/subjects/' . rawurlencode($subject_slug) . '/') : '/subjects/' . rawurlencode($subject_slug) . '/'),
  ]);
}

/* Prefer subjects_header to ensure full subjects css bundle */
if (function_exists('mk_require_shared')) {
  try {
    mk_require_shared('subjects_header.php');
  } catch (Throwable $e) {
    mk_require_shared('public_header.php');
  }
} else {
  if (defined('APP_ROOT') && is_file(APP_ROOT . '/private/shared/public_header.php')) {
    require APP_ROOT . '/private/shared/public_header.php';
  }
}

/* ---------------------------------------------------------
 * Render
 * --------------------------------------------------------- */
if ($db_error) {
  echo '<div class="notice error" style="margin:12px 0;"><strong>DB Error:</strong> ' . h($db_error) . '</div>';
}

$s_slug = (string)$subject['slug'];

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

          // Canonical clean URL
          $p_href  = '/subjects/' . rawurlencode($s_slug) . '/' . rawurlencode($p_slug) . '/';
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
}
