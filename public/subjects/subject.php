<?php
declare(strict_types=1);

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

require_once __DIR__ . '/../_init.php';

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

    $GLOBALS['page_title'] = $title . ' • Mkomi Igbo';
    $GLOBALS['page_desc']  = $message;
    $GLOBALS['active_nav'] = 'subjects';
    $GLOBALS['nav_active'] = 'subjects';

    if (function_exists('mk_require_shared')) {
      mk_require_shared('public_header.php');

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

/* Cache headers (GET only) */
if (function_exists('mk_public_cache_headers')) {
  mk_public_cache_headers(300);
}

/* Accept slug */
$subject_slug = '';
if (isset($_GET['slug']) && is_string($_GET['slug'])) $subject_slug = trim($_GET['slug']);
if ($subject_slug === '' && isset($_GET['subject']) && is_string($_GET['subject'])) $subject_slug = trim($_GET['subject']);
$subject_slug = strtolower($subject_slug);

if ($subject_slug === '') redirect_to('/subjects/', 302);

if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,190}$/', $subject_slug)) {
  mk_subjects_not_found('Subject not found', 'Invalid subject slug.');
}

/* Canonical URL enforcement:
   If accessed as /subjects/subject.php?slug=history (or any query form),
   redirect to /subjects/history/ (301). */
$req_uri = (string)($_SERVER['REQUEST_URI'] ?? '');
if (stripos($req_uri, '/subjects/subject.php') === 0) {
  redirect_to('/subjects/' . rawurlencode($subject_slug) . '/', 301);
}

if (!function_exists('pf__column_exists')) {
  function pf__column_exists(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $k = strtolower($table . '.' . $column);
    if (array_key_exists($k, $cache)) return (bool)$cache[$k];

    $st = $pdo->prepare("
      SELECT COUNT(*)
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
      LIMIT 1
    ");
    $st->execute([$table, $column]);
    $cache[$k] = ((int)$st->fetchColumn() > 0);
    return (bool)$cache[$k];
  }
}

$subject = null;
$pages = [];
$db_error = null;

try {
  $pdo = db();

  $nameCol = 'name';
  if (!pf__column_exists($pdo, 'subjects', 'name')) {
    if (pf__column_exists($pdo, 'subjects', 'menu_name')) $nameCol = 'menu_name';
    elseif (pf__column_exists($pdo, 'subjects', 'subject_name')) $nameCol = 'subject_name';
    else $nameCol = 'slug';
  }

  $descCol = 'description';
  if (!pf__column_exists($pdo, 'subjects', 'description')) {
    $descCol = pf__column_exists($pdo, 'subjects', 'content') ? 'content' : 'slug';
  }

  $st = $pdo->prepare("SELECT id, slug, {$nameCol} AS name, {$descCol} AS description
                       FROM subjects
                       WHERE slug = ?
                       LIMIT 1");
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

    $pst = $pdo->prepare("SELECT id, slug, {$titleCol} AS title
                          FROM pages
                          {$where}
                          ORDER BY {$orderCol} IS NULL, {$orderCol} ASC, id ASC");
    $pst->execute([$sid]);
    $pages = $pst->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
} catch (Throwable $e) {
  $db_error = $e->getMessage();
}

$active_nav = 'subjects';
$nav_active = 'subjects';

/* IMPORTANT: subject pages must use Subjects header so the full CSS bundle loads */
if (function_exists('mk_require_shared')) {
  mk_require_shared('subjects_header.php');
} else {
  if (defined('APP_ROOT') && is_file(APP_ROOT . '/private/shared/subjects_header.php')) {
    require APP_ROOT . '/private/shared/subjects_header.php';
  }
}

if (!$subject) {
  mk_subjects_not_found('Subject not found', 'No subject matched the requested slug: ' . $subject_slug);
}

$s_slug = (string)($subject['slug'] ?? $subject_slug);
$s_name = (string)($subject['name'] ?? $s_slug);
$s_desc = trim((string)($subject['description'] ?? ''));

$page_title = $s_name . ' • Subjects • Mkomi Igbo';
$page_desc  = $s_desc !== '' ? $s_desc : 'Browse pages under this subject.';

if ($db_error) {
  echo '<div class="container" style="padding:12px 0;">';
  echo '<div class="notice error"><strong>DB Error:</strong> ' . h($db_error) . '</div>';
  echo '</div>';
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

          // Canonical pretty URL
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
  mk_require_shared('subjects_footer.php');
} else {
  if (defined('APP_ROOT') && is_file(APP_ROOT . '/private/shared/subjects_footer.php')) {
    require APP_ROOT . '/private/shared/subjects_footer.php';
  } elseif (function_exists('mk_require_shared')) {
    mk_require_shared('public_footer.php');
  }
}
