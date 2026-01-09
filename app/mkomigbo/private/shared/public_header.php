<?php
declare(strict_types=1);

/**
 * /private/shared/public_header.php
 * Global public header (single source of truth).
 *
 * Inputs (set before include):
 *   - $page_title (string)
 *   - $page_desc  (string)
 *   - $active_nav / $nav_active (string)
 *   - $extra_css (array|string) additional css paths or full URLs
 *
 * Responsibilities:
 *   - Output <head> and open <body>
 *   - Render public nav
 *   - Load CSS in a predictable order with cache-busting
 */

if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

if (!isset($page_title) || !is_string($page_title) || trim($page_title) === '') {
  $page_title = 'Mkomi Igbo';
}
if (!isset($page_desc) || !is_string($page_desc) || trim($page_desc) === '') {
  $page_desc = 'Mkomi Igbo — knowledge, heritage, and people.';
}

if (!isset($active_nav) || !is_string($active_nav)) $active_nav = '';
if (!isset($nav_active) || !is_string($nav_active)) $nav_active = $active_nav;
$active_nav = trim($active_nav);
$nav_active = trim($nav_active);

/* normalize extra_css to array */
if (!isset($extra_css)) {
  $extra_css = [];
} elseif (is_string($extra_css)) {
  $extra_css = [$extra_css];
} elseif (!is_array($extra_css)) {
  $extra_css = [];
}

/* Absolute URL for current site (best-effort) */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = (string)($_SERVER['HTTP_HOST'] ?? '');
$site_url = ($host !== '') ? ($scheme . '://' . $host) : '';

/* Helper: normalize css path */
$mk_norm_css = static function ($p): ?string {
  if (!is_string($p)) return null;
  $p = trim($p);
  if ($p === '') return null;
  if (preg_match('~^https?://~i', $p)) return $p;
  if ($p[0] !== '/') $p = '/' . $p;
  return $p;
};

/* Helper: cache-bust local asset with filemtime (only if PUBLIC_PATH is defined) */
$mk_css_href = static function (string $href) : string {
  $href = trim($href);
  if ($href === '') return $href;

  // external URL, no local filemtime
  if (preg_match('~^https?://~i', $href)) return $href;

  $v = null;
  if (defined('PUBLIC_PATH')) {
    $p = rtrim((string)PUBLIC_PATH, DIRECTORY_SEPARATOR) . str_replace('/', DIRECTORY_SEPARATOR, $href);
    if (is_file($p)) {
      $t = @filemtime($p);
      if ($t !== false) $v = (string)$t;
    }
  }
  if ($v === null) return $href;
  return $href . (strpos($href, '?') === false ? '?v=' : '&v=') . rawurlencode($v);
};

/* Build CSS list:
   - ui.css is always first
   - then $extra_css (deduped)
*/
$css = ['/lib/css/ui.css'];
$seen = [strtolower('/lib/css/ui.css') => true];

foreach ($extra_css as $x) {
  $n = $mk_norm_css($x);
  if (!$n) continue;
  $k = strtolower($n);
  if (isset($seen[$k])) continue;
  $seen[$k] = true;
  $css[] = $n;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="index,follow">
  <title><?= h($page_title) ?></title>
  <meta name="description" content="<?= h($page_desc) ?>">

  <!-- Favicons -->
  <link rel="icon" href="/lib/images/favicon.ico" sizes="any">
  <link rel="icon" href="/lib/images/favicon.svg" type="image/svg+xml">

  <!-- Open Graph -->
  <meta property="og:site_name" content="Mkomi Igbo">
  <meta property="og:title" content="<?= h($page_title) ?>">
  <meta property="og:description" content="<?= h($page_desc) ?>">
  <meta property="og:type" content="website">
  <?php if ($site_url !== ''): ?>
    <meta property="og:url" content="<?= h($site_url . (string)($_SERVER['REQUEST_URI'] ?? '/')) ?>">
  <?php endif; ?>

  <!-- Twitter -->
  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="<?= h($page_title) ?>">
  <meta name="twitter:description" content="<?= h($page_desc) ?>">

  <!-- CSS -->
  <?php foreach ($css as $href): ?>
    <link rel="stylesheet" href="<?= h($mk_css_href($href)) ?>">
  <?php endforeach; ?>

  <!-- JS (defer) -->
</head>

<body>
<?php
/* Public nav */
$nav_file = __DIR__ . '/public_nav.php';
if (is_file($nav_file)) {
  include $nav_file;
}
?>
<main class="site-main" id="main">
