<?php
declare(strict_types=1);

/**
 * /private/shared/public_header.php
 * Public HTML head + loads ui.css + includes public_nav.php.
 *
 * Optional vars before include:
 * - $brand_name (string) display name e.g. "Mkomi Igbo" (recommended)
 * - $site_name  (string) OG site_name (optional; defaults to brand name)
 *
 * - $page_title (string)
 * - $page_desc  (string)
 * - $page_image (string) absolute or site-relative path for OG image (optional)
 * - $canonical  (string) absolute or site-relative canonical URL (optional)
 * - $robots     (string) e.g. "index,follow" or "noindex,nofollow" (optional)
 *
 * Assets:
 * - $extra_css  (array|string) extra CSS hrefs like ['/lib/css/subjects-public.css']
 * - $extra_js   (array|string) extra JS srcs like ['/lib/js/app.js'] (loaded defer)
 *
 * Nav:
 * - $nav_active (string) preferred
 * - $active_nav (string) accepted fallback
 *
 * Optional layout hooks:
 * - $body_class (string) OR $GLOBALS['mk_body_class']
 * - $breadcrumbs (array) OR $GLOBALS['mk_breadcrumbs']
 *
 * GLOBAL CONTRACT:
 * - Opens <main class="site-main" id="main"> once.
 * - public_footer.php closes </main>.
 * - Guard: $GLOBALS['mk__main_open'] prevents double open.
 */

if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

/* ---------------------------------------------------------
 * Brand + site naming (DISPLAY vs IDENTIFIER)
 * --------------------------------------------------------- */
$brand_name = (isset($brand_name) && is_string($brand_name) && trim($brand_name) !== '')
  ? trim($brand_name)
  : 'Mkomi Igbo';

/**
 * og:site_name is display metadata, not a filesystem identifier.
 * Default to brand name. Override per-page if you want.
 */
$site_name = (isset($site_name) && is_string($site_name) && trim($site_name) !== '')
  ? trim($site_name)
  : $brand_name;

/* ---------------------------------------------------------
 * URL + Asset helpers (NEVER touch filesystem identifiers)
 * --------------------------------------------------------- */
if (!function_exists('mk_abs_path')) {
  function mk_abs_path(string $path): string {
    $path = trim($path);
    if ($path === '') return '/';
    if (preg_match('~^https?://~i', $path)) return $path;
    return ($path[0] === '/') ? $path : ('/' . $path);
  }
}

if (!function_exists('mk_list_unique')) {
  function mk_list_unique($value): array {
    $out = [];
    if (is_string($value)) $value = [$value];
    if (!is_array($value)) return $out;

    $seen = [];
    foreach ($value as $v) {
      if (!is_string($v)) continue;
      $t = trim($v);
      if ($t === '') continue;
      $k = strtolower($t);
      if (isset($seen[$k])) continue;
      $seen[$k] = true;
      $out[] = $t;
    }
    return $out;
  }
}

/**
 * Find asset on disk (supports:
 * - /public_html/lib/... (your working layout)
 * - /public_html/public/lib/... (fallback if ever used)
 */
if (!function_exists('mk_asset_disk_path')) {
  function mk_asset_disk_path(string $href): ?string {
    $href = trim($href);
    if ($href === '' || preg_match('~^https?://~i', $href)) return null;

    $href = mk_abs_path($href);
    $qpos = strpos($href, '?');
    $pathOnly = ($qpos === false) ? $href : substr($href, 0, $qpos);

    $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $candidates = [];

    if ($docRoot !== '') {
      $candidates[] = $docRoot . $pathOnly;              // /public_html + /lib/...
      $candidates[] = $docRoot . '/public' . $pathOnly;  // /public_html/public + /lib/...
    }

    // If APP_ROOT is defined, attempt to infer /public_html
    if (defined('APP_ROOT') && is_string(APP_ROOT) && APP_ROOT !== '') {
      $publicHtml = dirname(dirname(APP_ROOT)); // /public_html (from /public_html/app/mkomigbo)
      if (is_string($publicHtml) && $publicHtml !== '') {
        $candidates[] = rtrim($publicHtml, '/') . $pathOnly;
        $candidates[] = rtrim($publicHtml, '/') . '/public' . $pathOnly;
      }
    }

    foreach ($candidates as $p) {
      if (is_string($p) && $p !== '' && is_file($p)) return $p;
    }
    return null;
  }
}

if (!function_exists('mk_asset_href')) {
  function mk_asset_href(string $href): string {
    $href = trim($href);
    if ($href === '') return '/';
    if (preg_match('~^https?://~i', $href)) return $href;

    $href = mk_abs_path($href);
    if (strpos($href, '?') !== false) return $href;

    $disk = mk_asset_disk_path($href);
    if ($disk && is_file($disk)) {
      $v = @filemtime($disk);
      if (is_int($v) && $v > 0) return $href . '?v=' . $v;
    }
    return $href;
  }
}

/* ---------------------------------------------------------
 * Inputs / defaults
 * --------------------------------------------------------- */
if (isset($nav_active) && is_string($nav_active) && trim($nav_active) !== '') {
  $active_nav = trim($nav_active);
} elseif (!isset($active_nav) || !is_string($active_nav) || trim($active_nav) === '') {
  $active_nav = 'home';
} else {
  $active_nav = trim($active_nav);
}

$page_title = (isset($page_title) && is_string($page_title) && trim($page_title) !== '')
  ? trim($page_title)
  : $brand_name;

$page_desc = (isset($page_desc) && is_string($page_desc) && trim($page_desc) !== '')
  ? trim($page_desc)
  : ($brand_name . ' — knowledge, heritage, and people.');

$robots = (isset($robots) && is_string($robots) && trim($robots) !== '')
  ? trim($robots)
  : 'index,follow';

/* Also set header robots tag (mirrors meta) */
if (!headers_sent()) {
  header('X-Robots-Tag: ' . $robots);
}

$resolved_body_class = '';
if (isset($body_class) && is_string($body_class) && trim($body_class) !== '') {
  $resolved_body_class = trim($body_class);
} elseif (isset($GLOBALS['mk_body_class']) && is_string($GLOBALS['mk_body_class']) && trim($GLOBALS['mk_body_class']) !== '') {
  $resolved_body_class = trim((string)$GLOBALS['mk_body_class']);
}

/* Breadcrumbs hook */
$resolved_breadcrumbs = [];
if (isset($breadcrumbs) && is_array($breadcrumbs)) {
  $resolved_breadcrumbs = $breadcrumbs;
} elseif (isset($GLOBALS['mk_breadcrumbs']) && is_array($GLOBALS['mk_breadcrumbs'])) {
  $resolved_breadcrumbs = $GLOBALS['mk_breadcrumbs'];
}

$crumbs = [];
if (is_array($resolved_breadcrumbs)) {
  foreach ($resolved_breadcrumbs as $c) {
    if (!is_array($c)) continue;
    $label = isset($c['label']) && is_string($c['label']) ? trim($c['label']) : '';
    if ($label === '') continue;

    $href = null;
    if (array_key_exists('href', $c) && is_string($c['href'])) {
      $t = trim($c['href']);
      if ($t !== '') $href = $t;
    }
    $crumbs[] = ['label' => $label, 'href' => $href];
  }
}

/* ---------------------------------------------------------
 * Assets
 * --------------------------------------------------------- */
$css = ['/lib/css/ui.css'];
$css = array_merge($css, mk_list_unique($extra_css ?? null));

$final_css = [];
$seen_css = [];
foreach ($css as $href) {
  if (!is_string($href)) continue;
  $href = trim($href);
  if ($href === '') continue;

  $norm = preg_match('~^https?://~i', $href) ? $href : mk_abs_path($href);
  $k = strtolower($norm);
  if (isset($seen_css[$k])) continue;
  $seen_css[$k] = true;
  $final_css[] = $norm;
}

$js = mk_list_unique($extra_js ?? null);
$final_js = [];
$seen_js = [];
foreach ($js as $src) {
  if (!is_string($src)) continue;
  $src = trim($src);
  if ($src === '') continue;

  $norm = preg_match('~^https?://~i', $src) ? $src : mk_abs_path($src);
  $k = strtolower($norm);
  if (isset($seen_js[$k])) continue;
  $seen_js[$k] = true;
  $final_js[] = $norm;
}

/* ---------------------------------------------------------
 * Canonical + OG
 * --------------------------------------------------------- */
$canonical_url = '';
if (isset($canonical) && is_string($canonical) && trim($canonical) !== '') {
  $canonical_url = trim($canonical);
  if (!preg_match('~^https?://~i', $canonical_url)) {
    $canonical_url = mk_abs_path($canonical_url);
  }
} else {
  if (defined('APP_URL') && is_string(APP_URL) && trim(APP_URL) !== '') {
    $base = rtrim((string)APP_URL, '/');
    $uri  = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($uri, PHP_URL_PATH);
    $path = is_string($path) && $path !== '' ? $path : '/';
    $canonical_url = $base . $path;
  }
}

$og_image = '';
if (isset($page_image) && is_string($page_image) && trim($page_image) !== '') {
  $og_image = trim($page_image);
  if (!preg_match('~^https?://~i', $og_image)) {
    $og_image = mk_abs_path($og_image);
  }
}

/* Favicons */
$favicon_ico = mk_asset_href('/lib/images/favicon.ico');
$favicon_svg = mk_asset_href('/lib/images/favicon.svg');

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="<?= h($robots) ?>">
  <title><?= h($page_title) ?></title>
  <meta name="description" content="<?= h($page_desc) ?>">

  <?php if ($canonical_url !== ''): ?>
    <link rel="canonical" href="<?= h($canonical_url) ?>">
  <?php endif; ?>

  <!-- Favicons -->
  <link rel="icon" href="<?= h($favicon_ico) ?>" sizes="any">
  <link rel="icon" href="<?= h($favicon_svg) ?>" type="image/svg+xml">

  <!-- Open Graph -->
  <meta property="og:site_name" content="<?= h($site_name) ?>">
  <meta property="og:title" content="<?= h($page_title) ?>">
  <meta property="og:description" content="<?= h($page_desc) ?>">
  <meta property="og:type" content="website">
  <?php if ($canonical_url !== ''): ?>
    <meta property="og:url" content="<?= h($canonical_url) ?>">
  <?php endif; ?>
  <?php if ($og_image !== ''): ?>
    <meta property="og:image" content="<?= h($og_image) ?>">
  <?php endif; ?>

  <!-- Twitter -->
  <meta name="twitter:card" content="<?= $og_image !== '' ? 'summary_large_image' : 'summary' ?>">
  <meta name="twitter:title" content="<?= h($page_title) ?>">
  <meta name="twitter:description" content="<?= h($page_desc) ?>">
  <?php if ($og_image !== ''): ?>
    <meta name="twitter:image" content="<?= h($og_image) ?>">
  <?php endif; ?>

  <!-- CSS -->
  <?php foreach ($final_css as $href): ?>
    <?php $out = preg_match('~^https?://~i', $href) ? $href : mk_asset_href($href); ?>
    <link rel="stylesheet" href="<?= h($out) ?>">
  <?php endforeach; ?>

  <!-- JS (defer) -->
  <?php foreach ($final_js as $src): ?>
    <?php $out = preg_match('~^https?://~i', $src) ? $src : mk_asset_href($src); ?>
    <script defer src="<?= h($out) ?>"></script>
  <?php endforeach; ?>
</head>

<body<?= $resolved_body_class !== '' ? ' class="' . h($resolved_body_class) . '"' : '' ?>>

<?php
/* Include shared public nav if present */
$public_nav = __DIR__ . '/public_nav.php';
if (is_file($public_nav)) {
  // contract: public_nav.php may use $nav_active
  $nav_active = $active_nav;
  // pass brand label if nav supports it
  $GLOBALS['mk_brand_name'] = $brand_name;
  include $public_nav;
}

/* Breadcrumbs (optional) */
if (!empty($crumbs)): ?>
  <div class="container" style="margin-top:10px;">
    <nav class="crumbs" aria-label="Breadcrumb">
      <ol class="crumbs__list" style="list-style:none; padding:0; margin:0; display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
        <?php foreach ($crumbs as $i => $c): ?>
          <li class="crumbs__item" style="display:flex; gap:8px; align-items:center;">
            <?php if ($i > 0): ?><span class="crumbs__sep" aria-hidden="true" style="opacity:.6;">/</span><?php endif; ?>
            <?php if (is_string($c['href']) && $c['href'] !== ''): ?>
              <a class="crumbs__link" href="<?= h(mk_abs_path($c['href'])) ?>"><?= h($c['label']) ?></a>
            <?php else: ?>
              <span class="crumbs__current" aria-current="page" style="opacity:.8;"><?= h($c['label']) ?></span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </nav>
  </div>
<?php endif; ?>

<?php
/* Open <main> once (global contract) */
if (!isset($GLOBALS['mk__main_open']) || $GLOBALS['mk__main_open'] !== true) {
  $GLOBALS['mk__main_open'] = true;
  echo '<main class="site-main" id="main">' . "\n";
}
?>
