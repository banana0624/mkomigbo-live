<?php
declare(strict_types=1);

/**
 * /private/shared/public_nav.php
 * Shared Header + Nav (single source of truth).
 *
 * Usage (after initialize.php):
 *   $nav_active = 'home'|'subjects'|'platforms'|'contributors'|'igbo-calendar'|'staff';
 *   include __DIR__ . '/public_nav.php';
 *
 * Requires:
 *   - url_for()
 *   - h()   (fallback provided if missing)
 */

if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

if (!isset($nav_active) || !is_string($nav_active)) {
  $nav_active = '';
}
$nav_active = trim($nav_active);

/* Display brand (DO NOT use this for filesystem/paths) */
$brand_label = 'Mkomi Igbo';
if (isset($GLOBALS['mk_brand_name']) && is_string($GLOBALS['mk_brand_name']) && trim($GLOBALS['mk_brand_name']) !== '') {
  $brand_label = trim((string)$GLOBALS['mk_brand_name']);
}

/* URLs */
$home_url          = url_for('/');
$subjects_url      = url_for('/subjects/');
$platforms_url     = url_for('/platforms/');
$contributors_url  = url_for('/contributors/');
$igbo_calendar_url = url_for('/igbo-calendar/');
$staff_url         = url_for('/staff/');

/* Staff quick links (shown only when in staff context) */
$staff_subjects_url  = url_for('/staff/subjects/');
$staff_pages_url     = url_for('/staff/subjects/pgs/');
$staff_contrib_url   = url_for('/staff/contributors/');
$staff_platforms_url = url_for('/staff/platforms/');
$staff_tools_url     = url_for('/staff/tools/');

if (!function_exists('pf__nav_a')) {
  function pf__nav_a(string $key, string $href, string $label, string $active_key): string {
    $class = ($key === $active_key) ? 'active' : '';
    return '<a class="' . h($class) . '" href="' . h($href) . '">' . h($label) . '</a>';
  }
}

if (!function_exists('pf__subnav_a')) {
  function pf__subnav_a(string $href, string $label, bool $is_active = false): string {
    $class = $is_active ? 'active' : '';
    return '<a class="' . h($class) . '" href="' . h($href) . '">' . h($label) . '</a>';
  }
}

/* Detect staff context */
$is_staff = ($nav_active === 'staff');
if (!$is_staff && isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])) {
  $uri = (string)$_SERVER['REQUEST_URI'];
  if (strpos($uri, '/staff/') === 0) $is_staff = true;
}

$uri  = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
$path = $uri;
if (($qpos = strpos($path, '?')) !== false) $path = substr($path, 0, $qpos);

$staff_section = '';
if ($is_staff) {
  if (strpos($path, '/staff/subjects/pgs') === 0) $staff_section = 'pages';
  elseif (strpos($path, '/staff/subjects') === 0) $staff_section = 'subjects';
  elseif (strpos($path, '/staff/contributors') === 0) $staff_section = 'contributors';
  elseif (strpos($path, '/staff/platforms') === 0) $staff_section = 'platforms';
  elseif (strpos($path, '/staff/tools') === 0) $staff_section = 'tools';
  else $staff_section = 'staff';
}
?>
<header class="site-header<?= $is_staff ? ' site-header--staff' : '' ?>">
  <div class="container site-header__row">
    <a class="brand" href="<?= h($home_url) ?>"><?= h($brand_label) ?></a>

    <button class="nav-toggle" type="button" aria-controls="primaryNav" aria-expanded="false">
      <span class="sr-only">Menu</span>
      ☰
    </button>

    <nav class="nav" id="primaryNav" aria-label="Primary">
      <?= pf__nav_a('home',          $home_url,          'Home',          $nav_active) ?>
      <?= pf__nav_a('subjects',      $subjects_url,      'Subjects',      $nav_active) ?>
      <?= pf__nav_a('platforms',     $platforms_url,     'Platforms',     $nav_active) ?>
      <?= pf__nav_a('contributors',  $contributors_url,  'Contributors',  $nav_active) ?>
      <?= pf__nav_a('igbo-calendar', $igbo_calendar_url, 'Igbo Calendar', $nav_active) ?>
      <?= pf__nav_a('staff',         $staff_url,         'Staff',         $nav_active) ?>
    </nav>
  </div>

  <?php if ($is_staff): ?>
    <div class="container staff-subnav" aria-label="Staff navigation">
      <nav class="staff-subnav__nav" aria-label="Staff sections">
        <?= pf__subnav_a($staff_url,            'Dashboard',    $staff_section === 'staff') ?>
        <?= pf__subnav_a($staff_subjects_url,   'Subjects',     $staff_section === 'subjects') ?>
        <?= pf__subnav_a($staff_pages_url,      'Pages',        $staff_section === 'pages') ?>
        <?= pf__subnav_a($staff_contrib_url,    'Contributors', $staff_section === 'contributors') ?>
        <?= pf__subnav_a($staff_platforms_url,  'Platforms',    $staff_section === 'platforms') ?>
        <?= pf__subnav_a($staff_tools_url,      'Tools',        $staff_section === 'tools') ?>
      </nav>
    </div>
  <?php endif; ?>
</header>

<script>
(function(){
  const btn = document.querySelector('.nav-toggle');
  const nav = document.getElementById('primaryNav');
  if (!btn || !nav) return;

  btn.addEventListener('click', function(){
    const expanded = btn.getAttribute('aria-expanded') === 'true';
    btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    nav.classList.toggle('is-open', !expanded);
  });
})();
</script>
