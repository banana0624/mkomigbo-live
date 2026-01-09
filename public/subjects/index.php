<?php
declare(strict_types=1);

/**
 * /public/subjects/index.php
 * Subjects index (schema-tolerant, premium, stable assets).
 *
 * Routes (via .htaccess):
 *   /subjects/   -> this file
 */

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

require_once __DIR__ . '/../_init.php';

if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

/* Cache headers (GET only) */
if (function_exists('mk_public_cache_headers')) {
  mk_public_cache_headers(300);
}

/* -------------------------------
   Schema helpers
-------------------------------- */
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

/* -------------------------------
   Theme helpers (optional)
-------------------------------- */
$has_theme = function_exists('pf__accent_for') && function_exists('pf__subject_logo_url');

/* -------------------------------
   Load subjects
-------------------------------- */
$subjects = [];
$db_error = '';

try {
  if (!function_exists('db')) throw new RuntimeException('db() not available.');
  $pdo = db();
  if (!$pdo instanceof PDO) throw new RuntimeException('DB connection not available.');

  // Choose best available "display name" column
  $nameCol = 'name';
  if (!pf__column_exists($pdo, 'subjects', 'name')) {
    if (pf__column_exists($pdo, 'subjects', 'menu_name')) $nameCol = 'menu_name';
    elseif (pf__column_exists($pdo, 'subjects', 'subject_name')) $nameCol = 'subject_name';
    else $nameCol = 'slug';
  }

  // Choose best available description column
  $descCol = null;
  if (pf__column_exists($pdo, 'subjects', 'short_desc')) $descCol = 'short_desc';
  elseif (pf__column_exists($pdo, 'subjects', 'description')) $descCol = 'description';
  elseif (pf__column_exists($pdo, 'subjects', 'content')) $descCol = 'content';

  // Visibility columns (optional)
  $hasStatus  = pf__column_exists($pdo, 'subjects', 'status');
  $hasPublic  = pf__column_exists($pdo, 'subjects', 'is_public');
  $hasVisible = pf__column_exists($pdo, 'subjects', 'visible');

  // Ordering columns (optional)
  $hasNavOrder = pf__column_exists($pdo, 'subjects', 'nav_order');
  $hasPosition = pf__column_exists($pdo, 'subjects', 'position');

  $cols = [
    'id',
    'slug',
    "{$nameCol} AS name",
  ];
  if ($descCol) $cols[] = "{$descCol} AS description";
  if ($hasNavOrder) $cols[] = 'nav_order';
  if ($hasPosition) $cols[] = 'position';

  $where = "WHERE slug IS NOT NULL AND slug <> ''";
  if ($hasStatus) {
    $where .= " AND status IN ('active','published','public')";
  } elseif ($hasPublic) {
    $where .= " AND is_public = 1";
  } elseif ($hasVisible) {
    $where .= " AND visible = 1";
  }

  $orderCol = $hasNavOrder ? 'nav_order' : ($hasPosition ? 'position' : 'id');

  $sql = "SELECT " . implode(', ', $cols) . " FROM subjects {$where}
          ORDER BY {$orderCol} IS NULL, {$orderCol} ASC, id ASC";

  $st = $pdo->query($sql);
  $subjects = $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

} catch (Throwable $e) {
  $db_error = $e->getMessage();
}

/* -------------------------------
   Page vars + header
-------------------------------- */
$brand_name = defined('MK_BRAND_NAME') ? (string)MK_BRAND_NAME : 'Mkomi Igbo';
$site_name  = defined('MK_SITE_NAME')  ? (string)MK_SITE_NAME  : 'Mkomi Igbo';

$page_title = 'Subjects • ' . $brand_name;
$page_desc  = 'Browse topics and explore their pages.';
$active_nav = 'subjects';
$nav_active = 'subjects';

/**
 * IMPORTANT:
 * Use subjects_header.php so /lib/css/subjects-public.css is guaranteed to load.
 * This fixes your current issue where only ui.css appears on /subjects/.
 */
if (function_exists('mk_require_shared')) {
  mk_require_shared('subjects_header.php');
} else {
  if (defined('APP_ROOT')) {
    $hdr = APP_ROOT . '/private/shared/subjects_header.php';
    if (is_file($hdr)) require $hdr;
    else {
      // last resort fallback
      $hdr2 = APP_ROOT . '/private/shared/public_header.php';
      if (is_file($hdr2)) require $hdr2;
    }
  }
}

/* Diagnostics banner */
if ($db_error !== '') {
  echo '<div class="notice error" style="margin:12px 0;"><strong>DB Error:</strong> ' . h($db_error) . '</div>';
}

?>
<header class="mk-hero" style="margin-top:14px;">
  <div class="mk-hero__bar" aria-hidden="true"></div>
  <div class="mk-hero__inner">
    <h1 class="mk-hero__title">Subjects</h1>
    <p class="mk-hero__subtitle">Browse topics and explore their pages.</p>
  </div>
</header>

<section class="mk-grid mk-grid--subjects" style="margin-top:16px;">
  <?php if (count($subjects) === 0): ?>
    <div class="mk-card" style="padding:14px;">
      <p class="mk-muted" style="margin:0;">
        No public subjects available yet.
      </p>
    </div>
  <?php else: ?>
    <?php foreach ($subjects as $s): ?>
      <?php
        $slug = (string)($s['slug'] ?? '');
        if ($slug === '') continue;

        $name = trim((string)($s['name'] ?? ''));
        if ($name === '') $name = $slug;

        $desc = trim((string)($s['description'] ?? ''));
        $accent = $has_theme ? (string)pf__accent_for($slug) : '#2b6cb0';
        $logo   = $has_theme ? (string)pf__subject_logo_url($slug) : '';

        // LiteSpeed-safe real-file route
        $href = '/subjects/subject.php?slug=' . rawurlencode($slug);
      ?>
      <article class="mk-card mk-subject-card" style="--accent: <?= h($accent) ?>;">
        <div class="mk-card__bar" aria-hidden="true"></div>

        <a class="mk-card__link" href="<?= h($href) ?>">
          <div class="mk-card__body">
            <div class="mk-subject-card__top">
              <div class="mk-subject-card__logo" aria-hidden="true">
                <?php if ($logo !== ''): ?>
                  <img src="<?= h($logo) ?>" alt="<?= h($name) ?> logo">
                <?php else: ?>
                  <span class="mk-subject-card__initial"><?= h(strtoupper(substr($name, 0, 1))) ?></span>
                <?php endif; ?>
              </div>

              <div class="mk-subject-card__titlewrap">
                <h2 class="mk-subject-card__title"><?= h($name) ?></h2>
                <div class="mk-subject-card__slug mk-muted"><?= h($slug) ?></div>
              </div>
            </div>

            <?php if ($desc !== ''): ?>
              <p class="mk-muted" style="margin-top:10px;"><?= h($desc) ?></p>
            <?php endif; ?>

            <div class="mk-card__meta" style="margin-top:12px;">
              <span class="mk-pill">Explore</span>
            </div>
          </div>
        </a>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<?php
if (function_exists('mk_require_shared')) {
  mk_require_shared('public_footer.php');
} else {
  if (defined('APP_ROOT')) {
    $f = APP_ROOT . '/private/shared/public_footer.php';
    if (is_file($f)) require $f;
  }
}
