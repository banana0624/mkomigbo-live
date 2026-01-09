<?php
declare(strict_types=1);

/**
 * /mkomigbo/public_html/index.php
 * Public Home (premium landing).
 *
 * Goals:
 * - Premium look that matches Subjects pages.
 * - Clean routes to Subjects / Platforms / Contributors / Staff.
 * - Auto subject logos (if present in /lib/images/subjects/{slug}.svg|png|webp|jpg|jpeg)
 * - Schema tolerant for subjects visibility/order/description.
 * - Performance-first: small query, no JS, minimal work.
 */

define('APP_ROOT', __DIR__ . '/app/mkomigbo');

$init = APP_ROOT . '/private/assets/initialize.php';
if (!is_file($init)) {
  header('Content-Type: text/plain; charset=utf-8');
  echo "Init not found\nExpected: {$init}\n";
  exit;
}
require_once $init;

$nav_active = 'home';

/* Shared public nav include (single source of truth) */
$public_nav = APP_ROOT . '/private/shared/public_nav.php';
if (!is_file($public_nav)) {
  header('Content-Type: text/plain; charset=utf-8');
  echo "Public nav include missing\nExpected: {$public_nav}\n";
  exit;
}

/* ---------------------------------------------------------
 * Helpers (local, safe)
 * --------------------------------------------------------- */
if (!function_exists('pf__seg')) {
  function pf__seg(string $s): string { return rawurlencode($s); }
}

if (!function_exists('pf__slug_key')) {
  // For logo filename lookups only; routing still uses DB slug as-is.
  function pf__slug_key(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9\-]+/', '-', $s) ?? $s;
    $s = preg_replace('/\-+/', '-', $s) ?? $s;
    return trim($s, '-');
  }
}

if (!function_exists('pf__column_exists')) {
  function pf__column_exists(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $key = strtolower($table . '.' . $column);
    if (array_key_exists($key, $cache)) return $cache[$key];

    try {
      $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
      if ($dbName === '') return $cache[$key] = false;

      $sql = "SELECT 1
              FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?
              LIMIT 1";
      $st = $pdo->prepare($sql);
      $st->execute([$dbName, $table, $column]);
      return $cache[$key] = (bool)$st->fetchColumn();
    } catch (Throwable $e) {
      // Do not throw; home must remain resilient
      return $cache[$key] = false;
    }
  }
}

if (!function_exists('pf__accent_for')) {
  function pf__accent_for(string $slug): string {
    $map = [
      'history'       => '#2F3A4A',
      'slavery'       => '#4A2F2F',
      'people'        => '#2F4A43',
      'persons'       => '#2E3F5B',
      'culture'       => '#4A3F2F',
      'religion'      => '#3C2F4A',
      'spirituality'  => '#2F4A59',
      'tradition'     => '#3B4A2F',
      'language1'     => '#2F3559',
      'language2'     => '#4A2F59',
      'struggles'     => '#59312F',
      'resistance'    => '#5A1F3B',
      'biafra'        => '#2F4A2F',
      'nigeria'       => '#1F5A3E',
      'africa'        => '#5A4A2F',
      'uk'            => '#2F2F59',
      'europe'        => '#2F4A5A',
      'arabs'         => '#4A3A2F',
      'about'         => '#3A3A3A',
    ];
    $k = strtolower(trim($slug));
    return $map[$k] ?? '#2563eb';
  }
}

if (!function_exists('pf__subject_logo_url')) {
  function pf__subject_logo_url(string $slug): ?string {
    $slug = trim($slug);
    if ($slug === '') return null;

    $key = pf__slug_key($slug);

    // Web root: /mkomigbo/public_html
    $baseDir = __DIR__ . '/lib/images/subjects';
    $baseWeb = '/lib/images/subjects';

    foreach (['svg', 'png', 'webp', 'jpg', 'jpeg'] as $ext) {
      $file = $baseDir . '/' . $key . '.' . $ext;
      if (is_file($file)) {
        return url_for($baseWeb . '/' . $key . '.' . $ext);
      }
    }
    return null;
  }
}

if (!function_exists('pf__shorten')) {
  function pf__shorten(string $s, int $max = 120): string {
    $s = trim($s);
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    if ($s === '') return '';
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
      return (mb_strlen($s, 'UTF-8') > $max) ? rtrim(mb_substr($s, 0, $max, 'UTF-8')) . '…' : $s;
    }
    return (strlen($s) > $max) ? rtrim(substr($s, 0, $max)) . '…' : $s;
  }
}

/* ---------------------------------------------------------
 * Load subjects (fast, safe)
 * --------------------------------------------------------- */
$subjects = [];
$db_error_public = null;

try {
  $pdo = db();
  $table = 'subjects';

  $has_desc      = pf__column_exists($pdo, $table, 'description');
  $has_is_public = pf__column_exists($pdo, $table, 'is_public');
  $has_visible   = pf__column_exists($pdo, $table, 'visible');
  $has_nav       = pf__column_exists($pdo, $table, 'nav_order');
  $has_position  = pf__column_exists($pdo, $table, 'position');

  $select = "id, name, slug" . ($has_desc ? ", description" : "");
  $where  = "WHERE 1=1";

  if ($has_is_public) {
    $where .= " AND is_public = 1";
  } elseif ($has_visible) {
    $where .= " AND visible = 1";
  }

  $order = "ORDER BY ";
  if ($has_nav) {
    $order .= "COALESCE(nav_order, 999999), id ASC";
  } elseif ($has_position) {
    $order .= "COALESCE(position, 999999), id ASC";
  } else {
    $order .= "id ASC";
  }

  $sql = "SELECT {$select} FROM {$table} {$where} {$order} LIMIT 12";
  $st = $pdo->query($sql);
  $subjects = $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

} catch (Throwable $e) {
  // Log full error for diagnostics
  if (function_exists('app_log')) {
    app_log('error', 'Home subject load failed', [
      'type' => get_class($e),
      'message' => $e->getMessage(),
      'file' => $e->getFile(),
      'line' => $e->getLine(),
    ]);
  }

  // Show a safe message unless debugging
  $db_error_public = (defined('APP_DEBUG') && APP_DEBUG)
    ? $e->getMessage()
    : 'Some content may be temporarily unavailable.';
}

/* ---------------------------------------------------------
 * Page vars
 * --------------------------------------------------------- */
$page_title = 'Mkomi Igbo';

$home_url           = url_for('/');
$subjects_url       = url_for('/subjects/');
$platforms_url      = url_for('/platforms/');
$contributors_url   = url_for('/contributors/');
$igbo_calendar_url  = url_for('/igbo-calendar/');

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($page_title) ?></title>

  <link rel="stylesheet" href="<?= h(url_for('/lib/css/ui.css')) ?>">
  <link rel="stylesheet" href="<?= h(url_for('/lib/css/subjects.css')) ?>">
  <link rel="stylesheet" href="<?= h(url_for('/lib/css/home.css')) ?>">
</head>

<body>
  <?php include_once $public_nav; ?>

  <main class="container" style="padding:24px 0;">
    <section class="hero">
      <div class="hero-bar"></div>
      <div class="hero-inner">
        <h1>Mkomigbo</h1>
        <p class="muted">
          A growing knowledge library — history, culture, language, people, struggles, and more —
          organized into subjects and curated pages you can browse like a handbook.
        </p>

        <div class="cta-row">
          <a class="btn" href="<?= h($subjects_url) ?>">Explore Subjects</a>
          <a class="btn" href="<?= h($platforms_url) ?>">Browse Platforms</a>
          <a class="btn" href="<?= h($contributors_url) ?>">Meet Contributors</a>
          <a class="btn" href="<?= h($igbo_calendar_url) ?>">Download Igbo Calendar</a>
        </div>

        <?php if ($db_error_public): ?>
          <p style="margin-top:12px;color:#b02a37;"><?= h($db_error_public) ?></p>
        <?php endif; ?>

        <p class="mini-note muted">
          Tip: logos load automatically from <code>/lib/images/subjects/{slug}.svg</code>. If a logo is missing, the card falls back gracefully.
        </p>
      </div>
    </section>

    <div class="section-title">
      <h2>Quick links</h2>
    </div>

    <section class="quick">
      <a href="<?= h($subjects_url) ?>">
        <div class="q-icon">S</div>
        <div>
          <p class="q-title">Subjects</p>
          <p class="q-desc muted">Browse all topics and dive into pages.</p>
        </div>
      </a>

      <a href="<?= h($platforms_url) ?>">
        <div class="q-icon">P</div>
        <div>
          <p class="q-title">Platforms</p>
          <p class="q-desc muted">Explore tools and sections like calendars, posts, and more.</p>
        </div>
      </a>

      <a href="<?= h($contributors_url) ?>">
        <div class="q-icon">C</div>
        <div>
          <p class="q-title">Contributors</p>
          <p class="q-desc muted">Meet authors, editors, and collaborators.</p>
        </div>
      </a>

      <a href="<?= h(url_for('/staff/')) ?>">
        <div class="q-icon">A</div>
        <div>
          <p class="q-title">Staff</p>
          <p class="q-desc muted">Admin dashboard for managing subjects and pages.</p>
        </div>
      </a>

      <a href="<?= h($igbo_calendar_url) ?>">
        <div class="q-icon">I</div>
        <div>
          <p class="q-title">Igbo Calendar</p>
          <p class="q-desc muted">Download the Igbo Calendar for reference.</p>
        </div>
      </a>
    </section>

    <div class="section-title">
      <h2>Featured subjects</h2>
      <a href="<?= h($subjects_url) ?>">View all →</a>
    </div>

    <?php if (count($subjects) === 0): ?>
      <p class="muted">No subjects available yet.</p>
    <?php else: ?>
      <section class="grid">
        <?php foreach ($subjects as $s): ?>
          <?php
            $s_slug = (string)($s['slug'] ?? '');
            $s_name = (string)($s['name'] ?? $s_slug);
            $s_desc = (string)($s['description'] ?? '');
            $accent = pf__accent_for($s_slug);
            $logo   = pf__subject_logo_url($s_slug);

            $href = url_for('/subjects/' . pf__seg($s_slug) . '/');
            $desc = ($s_desc !== '') ? pf__shorten($s_desc, 120) : '';
          ?>
          <div class="card" style="--accent: <?= h($accent) ?>;">
            <div class="card-bar"></div>
            <a class="stretch" href="<?= h($href) ?>">
              <div class="card-body">
                <div class="top">
                  <div class="logo" aria-hidden="true" style="box-shadow: inset 0 0 0 2px rgba(37, 99, 235, .15);">
                    <?php if ($logo): ?>
                      <img src="<?= h($logo) ?>" alt="" loading="lazy" decoding="async">
                    <?php else: ?>
                      <span style="font-weight:900;color:var(--accent);font-size:1.1rem;">
                        <?= h(strtoupper(substr($s_name, 0, 1))) ?>
                      </span>
                    <?php endif; ?>
                  </div>

                  <div style="min-width:0;">
                    <h3><?= h($s_name) ?></h3>
                    <div class="muted" style="font-size:.9rem; margin-top:-2px;"><?= h($s_slug) ?></div>
                  </div>
                </div>

                <?php if ($desc !== ''): ?>
                  <p class="muted"><?= h($desc) ?></p>
                <?php else: ?>
                  <p class="muted"><em>Browse pages under this subject.</em></p>
                <?php endif; ?>

                <div class="meta">
                  <span class="pill">Explore</span>
                  <span class="pill">Curated</span>
                </div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <div style="margin-top:18px;">
      <a class="btn" href="<?= h($subjects_url) ?>">Browse all subjects</a>
    </div>
  </main>

  <footer class="site-footer">
    <div class="container">© <?= date('Y') ?> Mkomigbo</div>
  </footer>
</body>
</html>
