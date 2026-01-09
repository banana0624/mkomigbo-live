<?php
declare(strict_types=1);

/**
 * /private/shared/staff_header.php
 * Staff header: loads CSS/JS, renders nav, opens <main>.
 *
 * Contract:
 * - Opens <main class="site-main" id="main"> exactly once.
 * - Sets $GLOBALS['mk__main_open'] = true.
 *
 * Optional inputs:
 * - $page_title, $page_desc
 * - $extra_css (array|string)
 * - $extra_js  (array|string)
 * - $nav_active or $active_nav
 * - $staff_subnav (array): [['label'=>'...', 'href'=>'...', 'active'=>true], ...]
 * - $GLOBALS['mk_body_class']
 */

if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

/* Guard: do not print twice */
if (!empty($GLOBALS['__mk_staff_header_printed'])) {
  return;
}
$GLOBALS['__mk_staff_header_printed'] = true;

/* Ensure session */
if (session_status() !== PHP_SESSION_ACTIVE) {
  @session_start();
}

/* Active nav key */
$active_nav_key = 'staff';
if (isset($nav_active) && is_string($nav_active) && trim($nav_active) !== '') {
  $active_nav_key = trim($nav_active);
} elseif (isset($active_nav) && is_string($active_nav) && trim($active_nav) !== '') {
  $active_nav_key = trim($active_nav);
}

/* Defaults */
$page_title = (isset($page_title) && is_string($page_title) && trim($page_title) !== '')
  ? trim($page_title)
  : 'Staff • Mkomi Igbo';

$page_desc = (isset($page_desc) && is_string($page_desc) && trim($page_desc) !== '')
  ? trim($page_desc)
  : 'Staff dashboard and management tools.';

if (!isset($extra_css)) $extra_css = [];
if (!isset($extra_js))  $extra_js  = [];
if (is_string($extra_css)) $extra_css = [$extra_css];
if (is_string($extra_js))  $extra_js  = [$extra_js];
if (!is_array($extra_css)) $extra_css = [];
if (!is_array($extra_js))  $extra_js  = [];

/* Body class */
$body_class = 'staff';
if (isset($GLOBALS['mk_body_class']) && is_string($GLOBALS['mk_body_class']) && trim($GLOBALS['mk_body_class']) !== '') {
  $body_class = trim($GLOBALS['mk_body_class']);
  if (stripos($body_class, 'staff') === false) $body_class .= ' staff';
} else {
  $GLOBALS['mk_body_class'] = 'staff';
}

/* URL helper */
$to_url = static function (string $path): string {
  $path = trim($path);
  if ($path === '') return '';

  if (preg_match('~^https?://~i', $path)) return $path;

  if ($path[0] !== '/') $path = '/' . $path;

  if (function_exists('url_for')) {
    return (string)url_for($path);
  }

  if (defined('WWW_ROOT') && is_string(WWW_ROOT) && WWW_ROOT !== '') {
    return rtrim(WWW_ROOT, '/') . $path;
  }

  return $path;
};

$asset = $to_url;

/* CSS/JS sets (dedupe) */
$must_css = [
  '/lib/css/ui.css',
  '/lib/css/staff.css',
];

$must_js = [
  '/lib/js/ui.js',
  '/lib/js/staff.js',
];

$dedupe = static function (array $items) use ($asset): array {
  $out = [];
  $seen = [];
  foreach ($items as $p) {
    if (!is_string($p)) continue;
    $p = trim($p);
    if ($p === '') continue;

    $u = $asset($p);
    if ($u === '') continue;

    $k = strtolower($u);
    if (isset($seen[$k])) continue;
    $seen[$k] = true;
    $out[] = $u;
  }
  return $out;
};

$css_urls = $dedupe(array_merge($must_css, $extra_css));
$js_urls  = $dedupe(array_merge($must_js, $extra_js));

/* RBAC-aware login state + role */
$staff_logged_in = false;

if (function_exists('mk_is_staff_logged_in')) {
  $staff_logged_in = (bool)mk_is_staff_logged_in();
} else {
  $staff_logged_in = (isset($_SESSION['staff_user_id']) && is_numeric($_SESSION['staff_user_id']) && (int)$_SESSION['staff_user_id'] > 0);
}

if (!$staff_logged_in && function_exists('is_logged_in')) {
  $staff_logged_in = (bool)is_logged_in();
}

$staff_role = 'staff';
if ($staff_logged_in) {
  if (function_exists('mk_staff_role')) {
    $staff_role = (string)mk_staff_role();
  } else {
    $r = $_SESSION['staff_role'] ?? 'staff';
    $r = is_string($r) ? strtolower(trim($r)) : 'staff';
    $staff_role = in_array($r, ['admin','staff'], true) ? $r : 'staff';
  }
}
$is_admin = ($staff_logged_in && $staff_role === 'admin');

/* Nav URLs */
$u_home         = $asset('/');
$u_dashboard    = $asset('/staff/');
$u_subjects     = $asset('/staff/subjects/');
$u_pages        = $asset('/staff/subjects/pgs/');
$u_contributors = $asset('/staff/contributors/');
$u_platforms    = $asset('/staff/platforms/');
$u_tools        = $asset('/staff/tools/');
$u_audit        = $asset('/staff/audit/');
$u_logout       = $asset('/staff/logout.php');
$u_login        = $asset('/staff/login.php');

$u_public_subjects = $asset('/subjects/');
$logo_url = $asset('/lib/images/mk-logo.png');

$staff_subnav = (isset($staff_subnav) && is_array($staff_subnav)) ? $staff_subnav : [];

/* Output */
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo h($page_title); ?></title>
  <meta name="description" content="<?php echo h($page_desc); ?>">

  <base href="<?php echo h($asset('/')); ?>">

  <?php foreach ($css_urls as $href): ?>
    <link rel="stylesheet" href="<?php echo h($href); ?>">
  <?php endforeach; ?>
</head>
<body class="<?php echo h($body_class); ?>">

<header class="site-header" role="banner">
  <div class="site-header__row container">
    <a class="brand" href="<?php echo h($u_home); ?>" aria-label="Mkomi Igbo Home">
      <span class="brand__mark" aria-hidden="true">
        <img src="<?php echo h($logo_url); ?>" alt="" style="display:block;width:34px;height:34px;border-radius:10px;">
      </span>
      <span class="brand__text">
        <span class="brand__title">Mkomi Igbo</span>
        <span class="brand__sub">Staff</span>
      </span>
    </a>

    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="siteNav">
      <span class="nav-toggle__bars" aria-hidden="true"></span>
      <span class="sr-only">Toggle navigation</span>
    </button>

    <nav id="siteNav" class="site-nav" role="navigation" aria-label="Primary">
      <?php if ($staff_logged_in): ?>
        <a class="site-nav__link <?php echo ($active_nav_key === 'staff' || $active_nav_key === 'dashboard') ? 'is-active' : ''; ?>" href="<?php echo h($u_dashboard); ?>">Dashboard</a>
        <a class="site-nav__link <?php echo ($active_nav_key === 'subjects') ? 'is-active' : ''; ?>" href="<?php echo h($u_subjects); ?>">Subjects</a>
        <a class="site-nav__link <?php echo ($active_nav_key === 'pgs' || $active_nav_key === 'pages') ? 'is-active' : ''; ?>" href="<?php echo h($u_pages); ?>">Pages</a>
        <a class="site-nav__link <?php echo ($active_nav_key === 'contributors') ? 'is-active' : ''; ?>" href="<?php echo h($u_contributors); ?>">Contributors</a>
        <a class="site-nav__link <?php echo ($active_nav_key === 'platforms') ? 'is-active' : ''; ?>" href="<?php echo h($u_platforms); ?>">Platforms</a>
        <a class="site-nav__link <?php echo ($active_nav_key === 'tools') ? 'is-active' : ''; ?>" href="<?php echo h($u_tools); ?>">Tools</a>

        <?php if ($is_admin): ?>
          <a class="site-nav__link <?php echo ($active_nav_key === 'audit') ? 'is-active' : ''; ?>" href="<?php echo h($u_audit); ?>">Audit Log</a>
        <?php endif; ?>

        <span class="site-nav__spacer"></span>

        <span class="site-nav__link" style="pointer-events:none; opacity:.75;">
          <?php echo $is_admin ? 'Admin' : 'Staff'; ?>
        </span>

        <a class="site-nav__link" href="<?php echo h($u_public_subjects); ?>">View site</a>
        <a class="site-nav__link site-nav__link--danger" href="<?php echo h($u_logout); ?>">Logout</a>
      <?php else: ?>
        <a class="site-nav__link <?php echo ($active_nav_key === 'login' || $active_nav_key === 'staff') ? 'is-active' : ''; ?>" href="<?php echo h($u_login); ?>">Staff Login</a>

        <span class="site-nav__spacer"></span>

        <a class="site-nav__link" href="<?php echo h($u_public_subjects); ?>">View site</a>
      <?php endif; ?>
    </nav>
  </div>

  <?php if ($staff_logged_in && !empty($staff_subnav)): ?>
    <div class="staff-subnav">
      <div class="container staff-subnav__row">
        <?php foreach ($staff_subnav as $it): ?>
          <?php
            $lbl = isset($it['label']) ? trim((string)$it['label']) : '';
            $href_raw = isset($it['href']) ? trim((string)$it['href']) : '#';
            $href = $href_raw;

            if ($href_raw !== '' && $href_raw !== '#' && !preg_match('~^https?://~i', $href_raw) && $href_raw[0] !== '#') {
              $href = $to_url($href_raw);
            }

            $is_active = !empty($it['active']);
          ?>
          <a class="staff-subnav__link <?php echo $is_active ? 'is-active' : ''; ?>" href="<?php echo h($href); ?>">
            <?php echo h($lbl); ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</header>

<?php $GLOBALS['mk__main_open'] = true; ?>
<main class="site-main" id="main">

<script>
(function () {
  if (window.__mkStaffNavInit) return;
  window.__mkStaffNavInit = true;

  var btn = document.querySelector('.nav-toggle');
  var nav = document.getElementById('siteNav');
  if (!btn || !nav) return;

  btn.addEventListener('click', function () {
    var open = btn.getAttribute('aria-expanded') === 'true';
    btn.setAttribute('aria-expanded', open ? 'false' : 'true');
    nav.classList.toggle('is-open', !open);
    document.body.classList.toggle('nav-open', !open);
  });
})();
</script>

<?php foreach ($js_urls as $src): ?>
  <script src="<?php echo h($src); ?>" defer></script>
<?php endforeach; ?>
