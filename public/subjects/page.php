<?php
declare(strict_types=1);

/**
 * /public_html/public/subjects/page.php
 *
 * Render a single subject page (LiteSpeed-safe real-file routing):
 *   /subjects/page.php?subject=culture&slug=intro
 *   /subjects/page.php?subject=culture&page=intro   (legacy)
 *
 * Branding:
 * - Brand: "Mkomi Igbo" (display)
 * - Identifier: "mkomigbo" (system/domain continuity)
 *
 * Performance:
 * - schema checks cached per-request
 * - avoids unnecessary work on invalid slugs
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
if (!function_exists('pf__seg')) {
  function pf__seg(string $s): string { return rawurlencode($s); }
}
if (!function_exists('pf__excerpt')) {
  function pf__excerpt(string $html, int $limit = 220): string {
    $txt = trim(strip_tags($html));
    $txt = preg_replace('/\s+/u', ' ', $txt) ?? $txt;
    if ($txt === '') return '';
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
      if (mb_strlen($txt, 'UTF-8') <= $limit) return $txt;
      return rtrim(mb_substr($txt, 0, $limit, 'UTF-8')) . '…';
    }
    if (strlen($txt) <= $limit) return $txt;
    return rtrim(substr($txt, 0, $limit)) . '…';
  }
}

/* Cache headers (GET only) */
if (function_exists('mk_public_cache_headers')) {
  mk_public_cache_headers(300);
}

/* ---------------------------------------------------------
 * Subjects-safe 404 renderer (always styled)
 * --------------------------------------------------------- */
if (!function_exists('mk_subjects_404_page')) {
  function mk_subjects_404_page(string $title, string $message): void {
    http_response_code(404);

    if (function_exists('mk_require_shared')) {
      $brand = defined('MK_BRAND_NAME') ? (string)MK_BRAND_NAME : 'Mkomi Igbo';
      $site  = defined('MK_SITE_NAME')  ? (string)MK_SITE_NAME  : 'mkomigbo';

      mk_view_set([
        'page_title'  => $title . ' • Subjects • ' . $brand,
        'page_desc'   => $message,
        'active_nav'  => 'subjects',
        'nav_active'  => 'subjects',
        'brand_name'  => $brand,
        'site_name'   => $site,
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
 * Inputs (support both ?slug= and legacy ?page=)
 * --------------------------------------------------------- */
$subject_slug = isset($_GET['subject']) ? trim((string)$_GET['subject']) : '';
$page_slug    = '';

if (isset($_GET['slug'])) {
  $page_slug = trim((string)$_GET['slug']);
} elseif (isset($_GET['page'])) {
  $page_slug = trim((string)$_GET['page']);
}

$subject_slug = strtolower($subject_slug);
$page_slug    = strtolower($page_slug);

/* Validate slugs early */
$valid = static function (string $s): bool {
  return ($s !== '') && (bool)preg_match('/^[a-z0-9][a-z0-9_-]{0,190}$/', $s);
};

if (!$valid($subject_slug) || !$valid($page_slug)) {
  mk_subjects_404_page('Page not found', 'Invalid subject/page slug.');
}

/* ---------------------------------------------------------
 * Schema helper (cached per-request)
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
 * Load subject + page + sidebar (schema-tolerant)
 * --------------------------------------------------------- */
$subject = null;
$page = null;
$sidebar_pages = [];
$db_error = '';

try {
  if (!function_exists('db')) throw new RuntimeException('db() not available.');
  $pdo = db();
  if (!$pdo instanceof PDO) throw new RuntimeException('DB connection not available.');

  /* SUBJECT columns */
  $subNameCol = 'name';
  if (!$column_exists($pdo, 'subjects', 'name')) {
    if ($column_exists($pdo, 'subjects', 'menu_name')) $subNameCol = 'menu_name';
    elseif ($column_exists($pdo, 'subjects', 'subject_name')) $subNameCol = 'subject_name';
    else $subNameCol = 'slug';
  }

  $subDescCol = null;
  if ($column_exists($pdo, 'subjects', 'meta_description')) $subDescCol = 'meta_description';
  elseif ($column_exists($pdo, 'subjects', 'description')) $subDescCol = 'description';
  elseif ($column_exists($pdo, 'subjects', 'content')) $subDescCol = 'content';

  $subCols = ['id', 'slug', "{$subNameCol} AS name"];
  if ($subDescCol) $subCols[] = "{$subDescCol} AS description";

  $st = $pdo->prepare("SELECT " . implode(', ', $subCols) . " FROM subjects WHERE slug = ? LIMIT 1");
  $st->execute([$subject_slug]);
  $subject = $st->fetch(PDO::FETCH_ASSOC) ?: null;

  if ($subject) {
    $sid = (int)$subject['id'];

    /* PAGES columns present? */
    $hasTitle    = $column_exists($pdo, 'pages', 'title');
    $hasMenuName = $column_exists($pdo, 'pages', 'menu_name');
    $hasBodyHtml = $column_exists($pdo, 'pages', 'body_html');
    $hasBody     = $column_exists($pdo, 'pages', 'body');
    $hasContent  = $column_exists($pdo, 'pages', 'content');

    $hasIsPublic = $column_exists($pdo, 'pages', 'is_public');
    $hasVisible  = $column_exists($pdo, 'pages', 'visible');

    $hasNavOrder = $column_exists($pdo, 'pages', 'nav_order');
    $hasPosition = $column_exists($pdo, 'pages', 'position');

    /* PAGE (single) — build SELECT only from existing columns */
    $pCols = ['id', 'subject_id', 'slug'];
    if ($hasTitle)    $pCols[] = 'title';
    if ($hasMenuName) $pCols[] = 'menu_name';
    if ($hasBodyHtml) $pCols[] = 'body_html';
    if ($hasBody)     $pCols[] = 'body';
    if ($hasContent)  $pCols[] = 'content';
    if ($hasIsPublic) $pCols[] = 'is_public';
    if ($hasVisible)  $pCols[] = 'visible';
    if ($hasNavOrder) $pCols[] = 'nav_order';
    if ($hasPosition) $pCols[] = 'position';

    $where = "subject_id = ? AND slug = ?";
    if ($hasIsPublic) $where .= " AND is_public = 1";
    elseif ($hasVisible) $where .= " AND visible = 1";

    $sqlPage = "SELECT " . implode(', ', array_unique($pCols)) . " FROM pages WHERE {$where} LIMIT 1";
    $st2 = $pdo->prepare($sqlPage);
    $st2->execute([$sid, $page_slug]);
    $page = $st2->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($page) {
      /* Normalize title */
      $t = '';
      if (isset($page['title']) && is_string($page['title'])) $t = trim($page['title']);
      if ($t === '' && isset($page['menu_name']) && is_string($page['menu_name'])) $t = trim($page['menu_name']);
      if ($t === '') $t = (string)($page['slug'] ?? '');
      $page['title'] = $t;

      /* Normalize body_html */
      $body_html = '';
      if (isset($page['body_html']) && is_string($page['body_html']) && trim($page['body_html']) !== '') {
        $body_html = (string)$page['body_html'];
      } elseif (isset($page['body']) && is_string($page['body']) && trim($page['body']) !== '') {
        $body_html = (string)$page['body'];
      } elseif (isset($page['content']) && is_string($page['content']) && trim($page['content']) !== '') {
        $body_html = (string)$page['content'];
      }
      $page['body_html'] = $body_html;
    }

    /* SIDEBAR list */
    $sCols = ['id', 'slug'];
    if ($hasTitle)    $sCols[] = 'title';
    if ($hasMenuName) $sCols[] = 'menu_name';
    if ($hasNavOrder) $sCols[] = 'nav_order';
    if ($hasPosition) $sCols[] = 'position';
    if ($hasIsPublic) $sCols[] = 'is_public';
    if ($hasVisible)  $sCols[] = 'visible';

    $whereS = "subject_id = ?";
    if ($hasIsPublic) $whereS .= " AND is_public = 1";
    elseif ($hasVisible) $whereS .= " AND visible = 1";

    $orderCol = $hasNavOrder ? 'nav_order' : ($hasPosition ? 'position' : 'id');

    $sqlSide = "SELECT " . implode(', ', array_unique($sCols)) . "
               FROM pages
               WHERE {$whereS}
               ORDER BY {$orderCol} IS NULL, {$orderCol} ASC, id ASC";
    $st3 = $pdo->prepare($sqlSide);
    $st3->execute([$sid]);
    $sidebar_pages = $st3->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($sidebar_pages as &$sp) {
      $lbl = '';
      if (isset($sp['title']) && is_string($sp['title'])) $lbl = trim($sp['title']);
      if ($lbl === '' && isset($sp['menu_name']) && is_string($sp['menu_name'])) $lbl = trim($sp['menu_name']);
      if ($lbl === '') $lbl = (string)($sp['slug'] ?? '');
      $sp['title'] = $lbl;
    }
    unset($sp);
  }

} catch (Throwable $e) {
  $db_error = $e->getMessage();
}

/* ---------------------------------------------------------
 * Meta vars + header include
 * --------------------------------------------------------- */
$active_nav = 'subjects';
$nav_active = 'subjects';

$brand_name = defined('MK_BRAND_NAME') ? (string)MK_BRAND_NAME : 'Mkomi Igbo';
$site_name  = defined('MK_SITE_NAME')  ? (string)MK_SITE_NAME  : 'mkomigbo';

$subject_title = $subject ? (string)($subject['name'] ?? $subject_slug) : $subject_slug;
$subject_title = trim($subject_title) !== '' ? $subject_title : $subject_slug;

$page_title_txt = $page ? (string)($page['title'] ?? $page_slug) : 'Page not found';
$page_title_txt = trim($page_title_txt) !== '' ? $page_title_txt : $page_slug;

$lede = ($page && isset($page['body_html']) && is_string($page['body_html']) && trim($page['body_html']) !== '')
  ? pf__excerpt((string)$page['body_html'], 220)
  : '';

$page_title = ($subject && $page)
  ? ($page_title_txt . ' • ' . $subject_title . ' • ' . $brand_name)
  : ('Page not found • Subjects • ' . $brand_name);

$page_desc = ($subject && $page)
  ? (trim($lede) !== '' ? $lede : ('Read “' . $page_title_txt . '” under ' . $subject_title . ' on ' . $brand_name . '.'))
  : ('Page not found on ' . $brand_name . '.');

// IMPORTANT: use Subjects header so deep pages get full styling bundle
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

/* URLs (real-file links, LiteSpeed-safe) */
$home_url     = function_exists('url_for') ? (string)url_for('/') : '/';
$subjects_url = function_exists('url_for') ? (string)url_for('/subjects/') : '/subjects/';
$subject_url  = '/subjects/subject.php?slug=' . pf__seg($subject_slug);
$page_url     = '/subjects/page.php?subject=' . pf__seg($subject_slug) . '&slug=' . pf__seg($page_slug);

/* ---------------------------------------------------------
 * Render
 * --------------------------------------------------------- */
if (!$subject || !$page) {
  http_response_code(404);
}

/* Optional diagnostic banner */
if ($db_error !== '') {
  echo '<div class="notice error" style="margin:12px 0;"><strong>DB Error:</strong> ' . h($db_error) . '</div>';
}

?>
<div class="container" style="padding:18px 0;">

  <section class="mk-crumbs" aria-label="Breadcrumb">
    <a href="<?= h($home_url) ?>">Home</a>
    <span class="mk-crumbs__sep">›</span>
    <a href="<?= h($subjects_url) ?>">Subjects</a>
    <span class="mk-crumbs__sep">›</span>
    <?php if ($subject): ?>
      <a href="<?= h($subject_url) ?>"><?= h($subject_title) ?></a>
      <span class="mk-crumbs__sep">›</span>
      <span class="mk-crumbs__current"><?= h($page_title_txt) ?></span>
    <?php else: ?>
      <span class="mk-crumbs__current">Page</span>
    <?php endif; ?>
  </section>

  <?php if (!$subject): ?>
    <header class="mk-hero" style="margin-top:14px;">
      <div class="mk-hero__bar" aria-hidden="true"></div>
      <div class="mk-hero__inner">
        <h1 class="mk-hero__title">Subject not found</h1>
        <p style="margin-top:12px;"><a class="mk-btn" href="<?= h($subjects_url) ?>">Back to Subjects</a></p>
      </div>
    </header>

  <?php elseif (!$page): ?>
    <header class="mk-hero" style="margin-top:14px;">
      <div class="mk-hero__bar" aria-hidden="true"></div>
      <div class="mk-hero__inner">
        <h1 class="mk-hero__title">Page not found</h1>
        <p class="mk-muted" style="margin-top:8px;">
          Subject: <strong><?= h($subject_slug) ?></strong> · Page: <strong><?= h($page_slug) ?></strong>
        </p>
        <p style="margin-top:12px;"><a class="mk-btn" href="<?= h($subject_url) ?>">Back to <?= h($subject_title) ?></a></p>
      </div>
    </header>

  <?php else: ?>
    <header class="mk-article-hero" style="margin-top:14px;">
      <div class="mk-article-hero__bar" aria-hidden="true"></div>
      <div class="mk-article-hero__inner">
        <h1 class="mk-article-hero__title"><?= h($page_title_txt) ?></h1>
        <?php if (trim($lede) !== ''): ?>
          <p class="mk-article-hero__lede mk-muted"><?= h($lede) ?></p>
        <?php endif; ?>
      </div>
    </header>

    <section class="mk-article-shell" style="margin-top:14px;">
      <article class="mk-card mk-article">
        <div class="mk-article__body">
          <?php
            $body_html = (string)($page['body_html'] ?? '');
            if ($body_html !== '' && function_exists('mk_sanitize_allowlist_html')) {
              $body_html = (string)mk_sanitize_allowlist_html($body_html);
            } elseif ($body_html !== '') {
              // minimal safety fallback
              $tmp = preg_replace('~<\s*(script|style)\b[^>]*>.*?<\s*/\s*\1\s*>~is', '', $body_html);
              if (is_string($tmp)) $body_html = $tmp;
            }
          ?>
          <?= $body_html !== '' ? $body_html : '<p class="mk-muted"><em>(No content yet)</em></p>' ?>
        </div>
      </article>

      <aside class="mk-card mk-sidebar" style="margin-top:14px; padding:14px;">
        <div style="display:flex; align-items:baseline; justify-content:space-between; gap:10px;">
          <h3 style="margin:0;">More in <?= h($subject_title) ?></h3>
          <a class="mk-muted" href="<?= h($subject_url) ?>">All pages</a>
        </div>

        <?php if (count($sidebar_pages) === 0): ?>
          <p class="mk-muted" style="margin-top:10px;"><em>No other pages yet.</em></p>
        <?php else: ?>
          <ul class="mk-side-list" style="margin-top:10px;">
            <?php foreach ($sidebar_pages as $sp): ?>
              <?php
                $sp_slug = (string)($sp['slug'] ?? '');
                if ($sp_slug === '') continue;
                $sp_url = '/subjects/page.php?subject=' . pf__seg($subject_slug) . '&slug=' . pf__seg($sp_slug);
                $label  = trim((string)($sp['title'] ?? '')) !== '' ? (string)$sp['title'] : $sp_slug;
                $is_active = ($sp_slug === (string)($page['slug'] ?? ''));
              ?>
              <li class="mk-side-list__item">
                <a class="mk-side-list__link <?= $is_active ? 'is-active' : '' ?>" href="<?= h($sp_url) ?>">
                  <?= h($label) ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </aside>
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
