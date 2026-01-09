<?php
declare(strict_types=1);

/**
 * /public_html/subjects.php
 * Subjects index: /subjects/
 */

define('APP_ROOT', __DIR__ . '/app/mkomigbo');
require_once APP_ROOT . '/private/assets/initialize.php';

$active_nav = 'subjects';

$public_nav = APP_ROOT . '/private/shared/public_nav.php';
if (!is_file($public_nav)) {
  app_log('warning', 'public_nav.php missing', ['expected' => $public_nav]);
}

if (!function_exists('pf__seg')) {
  function pf__seg(string $s): string { return rawurlencode($s); }
}
if (!function_exists('pf__shorten')) {
  function pf__shorten(string $s, int $max = 140): string {
    $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    if ($s === '') return '';
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
      return (mb_strlen($s, 'UTF-8') > $max) ? rtrim(mb_substr($s, 0, $max, 'UTF-8')) . '…' : $s;
    }
    return (strlen($s) > $max) ? rtrim(substr($s, 0, $max)) . '…' : $s;
  }
}
if (!function_exists('pf__column_exists')) {
  function pf__column_exists(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $key = strtolower($table . '.' . $column);
    if (array_key_exists($key, $cache)) return (bool)$cache[$key];
    try {
      $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
      if ($dbName === '') return $cache[$key] = false;
      $sql = "SELECT 1 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1";
      $st = $pdo->prepare($sql);
      $st->execute([$dbName, $table, $column]);
      return $cache[$key] = (bool)$st->fetchColumn();
    } catch (\Throwable $e) {
      return $cache[$key] = false;
    }
  }
}

$subjects  = [];
$db_error  = null;

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

  $sql = "SELECT {$select} FROM {$table} {$where} {$order}";
  $st  = $pdo->query($sql);
  $subjects = $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

  app_log('info', 'subjects loaded', ['count' => count($subjects)]);
} catch (\Throwable $e) {
  $db_error = $e->getMessage();
  app_log('error', 'subjects load failed', [
    'type' => get_class($e),
    'message' => $e->getMessage(),
    'file' => $e->getFile(),
    'line' => $e->getLine(),
  ]);
}

$page_title  = 'Subjects • Mkomi Igbo';
$home_url    = url_for('/');
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
  <?php if (is_file($public_nav)) { include $public_nav; } ?>

  <main class="container" style="padding:24px 0;">
    <section class="hero">
      <div class="hero-bar"></div>
      <div class="hero-inner">
        <h1>Subjects</h1>
        <p class="muted">
          Browse topics and open any subject to view its pages.
        </p>

        <div class="cta-row">
          <a class="btn" href="<?= h($home_url) ?>">Home</a>
          <a class="btn" href="<?= h(url_for('/platforms/')) ?>">Platforms</a>
          <a class="btn" href="<?= h(url_for('/contributors/')) ?>">Contributors</a>
          <a class="btn" href="<?= h(url_for('/igbo-calendar/')) ?>">Download Igbo Calendar</a>
        </div>

        <?php if ($db_error): ?>
          <p style="margin-top:12px;color:#b02a37;"><?= h($db_error) ?></p>
        <?php endif; ?>
      </div>
    </section>

    <div class="section-title">
      <h2>All subjects</h2>
      <div class="muted"><?= count($subjects) ?> total</div>
    </div>

    <?php if (count($subjects) === 0): ?>
      <p class="muted">No subjects available yet.</p>
    <?php else: ?>
      <section class="grid">
        <?php foreach ($subjects as $s): ?>
          <?php
            $slug = (string)($s['slug'] ?? '');
            $name = (string)($s['name'] ?? $slug);
            $desc = (string)($s['description'] ?? '');
            $href = url_for('/subjects/' . pf__seg($slug) . '/');
          ?>
          <div class="card" style="--accent:#111;">
            <div class="card-bar"></div>
            <a class="stretch" href="<?= h($href) ?>">
              <div class="card-body">
                <h3><?= h($name) ?></h3>
                <?php if ($desc !== ''): ?>
                  <p class="muted"><?= h(pf__shorten($desc, 150)) ?></p>
                <?php else: ?>
                  <p class="muted"><em>Open to browse pages.</em></p>
                <?php endif; ?>
                <div class="meta">
                  <span class="pill"><?= h($slug) ?></span>
                  <span class="pill">Open</span>
                </div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
  </main>

  <footer class="site-footer">
    <div class="container">© <?= date('Y') ?> Mkomigbo</div>
  </footer>
</body>
</html>
