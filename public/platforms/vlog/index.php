<?php
declare(strict_types=1);

$init = __DIR__ . '/../../../private/assets/initialize.php';
if (!is_file($init)) {
  header('Content-Type: text/plain; charset=utf-8');
  echo "Init not found\nExpected: {$init}\n";
  exit;
}
require_once $init;

$page_title = 'Vlog — Platforms';
$nav_active = 'platforms';

$public_nav = __DIR__ . '/../../../private/shared/public_nav.php';
if (!is_file($public_nav)) {
  header('Content-Type: text/plain; charset=utf-8');
  echo "Public nav include missing\nExpected: {$public_nav}\n";
  exit;
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($page_title) ?> • Mkomigbo</title>
  <link rel="stylesheet" href="<?= h(url_for('/lib/css/ui.css')) ?>">
  <link rel="stylesheet" href="<?= h(url_for('/lib/css/subjects.css')) ?>">
</head>
<body>
  <?php include_once $public_nav; ?>

  <main class="container" style="padding:24px 0;">
    <h1>Blog</h1>
    <p class="muted" style="max-width:80ch;">
      This platform is planned and will be enabled soon. For now, you can explore Subjects and Contributors.
    </p>

    <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap;">
      <a class="btn" href="<?= h(url_for('/subjects/')) ?>">Explore Subjects</a>
      <a class="btn" href="<?= h(url_for('/contributors/')) ?>">Meet Contributors</a>
      <a class="btn" href="<?= h(url_for('/platforms/')) ?>">Back to Platforms</a>
    </div>
  </main>

  <footer class="site-footer">
    <div class="container">© <?= date('Y') ?> Mkomigbo</div>
  </footer>
</body>
</html>