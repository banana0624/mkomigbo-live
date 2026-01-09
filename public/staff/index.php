<?php
declare(strict_types=1);

/**
 * /public/staff/index.php
 * Staff dashboard (uses staff_header.php contract).
 *
 * Robustness:
 * - Single RBAC guard: mk_require_staff_login()
 * - Session validation against DB is handled in auth.php (prevents redirect loops)
 * - Adds Account + Audit links
 */

require_once __DIR__ . '/../_init.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

/* No-cache for staff */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/* Ensure auth helpers exist */
if (!function_exists('mk_require_staff_login')) {
  $auth = null;
  if (defined('PRIVATE_PATH') && is_string(PRIVATE_PATH) && PRIVATE_PATH !== '') {
    $auth = PRIVATE_PATH . '/functions/auth.php';
  } elseif (defined('APP_ROOT')) {
    $auth = APP_ROOT . '/private/functions/auth.php';
  }
  if ($auth && is_file($auth)) require_once $auth;
}

/* Guard */
if (function_exists('mk_require_staff_login')) {
  mk_require_staff_login();
} else {
  header('Location: /staff/login.php', true, 302);
  exit;
}

/* Safety fallbacks */
if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('url_for')) {
  function url_for(string $path): string { return '/' . ltrim($path, '/'); }
}

/* ---------------------------------------------------------
   Safe disk path helpers (optional "exists" checks)
--------------------------------------------------------- */
function pf__public_root(): string {
  if (defined('PUBLIC_PATH') && is_string(PUBLIC_PATH) && PUBLIC_PATH !== '') {
    return rtrim(PUBLIC_PATH, "/\\");
  }
  $guess = realpath(dirname(__DIR__)); // /public
  return is_string($guess) && $guess !== '' ? rtrim($guess, "/\\") : rtrim(dirname(__DIR__), "/\\");
}

function pf__public_fs(string $web_path): string {
  $root = pf__public_root();
  $rel  = ltrim($web_path, "/\\");
  return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
}

function pf__file_exists_web(string $web_path): bool {
  return is_file(pf__public_fs($web_path));
}

function pf__dir_has_index(string $web_dir): bool {
  $web_dir = trim($web_dir, "/\\") . '/';
  $dirFs = pf__public_fs($web_dir);
  if (!is_dir($dirFs)) return false;
  return is_file(pf__public_fs($web_dir . 'index.php'));
}

function pf__route_if_exists(string $web_path): ?string {
  $web_path = '/' . ltrim($web_path, '/');

  if (str_ends_with($web_path, '/')) {
    return pf__dir_has_index($web_path) ? url_for($web_path) : null;
  }
  return pf__file_exists_web($web_path) ? url_for($web_path) : null;
}

/* ---------------------------------------------------------
   Routes
--------------------------------------------------------- */
$href_subjects = url_for('/staff/subjects/');
$href_pages    = url_for('/staff/subjects/pgs/');
$href_tools    = url_for('/staff/tools/');
$href_logout   = url_for('/staff/logout.php');

$href_account_password = url_for('/staff/account/password.php');
$href_account_audit    = url_for('/staff/account/audit.php');
$href_account_home     = pf__route_if_exists('/staff/account/') ?? url_for('/staff/account/');

/* Optional modules */
$href_platforms = pf__route_if_exists('/staff/platforms/') ?? pf__route_if_exists('/staff/platforms/index.php');
if (!$href_platforms) $href_platforms = url_for('/platforms/');

$href_contrib = pf__route_if_exists('/staff/contributors/') ?? pf__route_if_exists('/staff/contributors/index.php');
if (!$href_contrib) $href_contrib = url_for('/staff/contributors/');

/* ---------------------------------------------------------
   Page + header
--------------------------------------------------------- */
$page_title = 'Staff Dashboard • Mkomigbo';
$page_desc  = 'Manage subjects, pages, contributors, platforms, and tools.';
$active_nav = 'staff';
$nav_active = 'staff';

$staff_header = (defined('PRIVATE_PATH') && is_string(PRIVATE_PATH) && PRIVATE_PATH !== '')
  ? (PRIVATE_PATH . '/shared/staff_header.php')
  : (defined('APP_ROOT') ? (APP_ROOT . '/private/shared/staff_header.php') : null);

if ($staff_header && is_file($staff_header)) {
  require $staff_header;
} else {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Staff header missing.\n";
  exit;
}

/* ---------------------------------------------------------
   Cards
--------------------------------------------------------- */
$cards = [
  [
    'title' => 'Manage Subjects',
    'desc'  => 'Create, edit, order, and publish subjects.',
    'href'  => $href_subjects,
    'tag'   => 'Staff',
    'icon'  => 'S',
  ],
  [
    'title' => 'Manage Pages',
    'desc'  => 'Manage pages under subjects (publish/order/edit).',
    'href'  => $href_pages,
    'tag'   => 'Staff',
    'icon'  => 'P',
  ],
  [
    'title' => 'Contributors',
    'desc'  => 'Manage contributor profiles and credits.',
    'href'  => $href_contrib,
    'tag'   => 'Staff',
    'icon'  => 'C',
  ],
  [
    'title' => 'Platforms',
    'desc'  => 'Manage platforms and feature sections.',
    'href'  => $href_platforms,
    'tag'   => (strpos((string)$href_platforms, '/staff/') !== false) ? 'Staff' : 'Public',
    'icon'  => 'F',
  ],
  [
    'title' => 'Tools',
    'desc'  => 'Diagnostics and internal utilities.',
    'href'  => $href_tools,
    'tag'   => 'Staff',
    'icon'  => 'T',
  ],
  [
    'title' => 'Account',
    'desc'  => 'Password, audit log, and account settings.',
    'href'  => $href_account_home,
    'tag'   => 'Account',
    'icon'  => 'A',
  ],
  [
    'title' => 'Change Password',
    'desc'  => 'Update your password securely.',
    'href'  => $href_account_password,
    'tag'   => 'Account',
    'icon'  => '🔒',
  ],
  [
    'title' => 'Audit Log',
    'desc'  => 'Review login/logout and security events.',
    'href'  => $href_account_audit,
    'tag'   => 'Security',
    'icon'  => '⎘',
  ],
  [
    'title' => 'Logout',
    'desc'  => 'Sign out of staff account securely.',
    'href'  => $href_logout,
    'tag'   => 'Account',
    'icon'  => 'L',
  ],
];

?>
<style>
  .mk-sdash-hero{
    border:1px solid rgba(0,0,0,.10);
    border-radius:20px;
    background: linear-gradient(180deg, rgba(0,0,0,0.02), #fff);
    box-shadow: 0 18px 48px rgba(0,0,0,.10);
    overflow:hidden;
  }
  .mk-sdash-hero__bar{ height:8px; background:#111; opacity:.88; }
  .mk-sdash-hero__inner{ padding:22px 18px 18px; }
  .mk-sdash-hero h1{ margin:0 0 10px; font-size: clamp(1.6rem, 3vw, 2.4rem); line-height:1.08; letter-spacing:-0.02em; }
  .mk-sdash-grid{
    margin-top:14px;
    display:grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap:14px;
  }
  .mk-sdash-card{
    border:1px solid rgba(0,0,0,.10);
    border-radius:18px;
    background:#fff;
    overflow:hidden;
    box-shadow: 0 12px 28px rgba(0,0,0,.05);
    transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
    min-height: 150px;
  }
  .mk-sdash-card:hover{ transform: translateY(-1px); border-color: rgba(0,0,0,.22); box-shadow: 0 18px 36px rgba(0,0,0,.08); }
  .mk-sdash-card__bar{ height:7px; background:#111; }
  .mk-sdash-card__body{ padding:14px; display:flex; flex-direction:column; gap:10px; }
  .mk-sdash-top{ display:flex; gap:12px; align-items:flex-start; }
  .mk-sdash-icon{
    width:56px; height:56px; border-radius:16px;
    border:1px solid rgba(0,0,0,.10);
    background: rgba(0,0,0,0.02);
    display:flex; align-items:center; justify-content:center;
    font-weight:900;
    flex:0 0 auto;
  }
  .mk-sdash-card h3{ margin:2px 0 6px; font-size:1.1rem; line-height:1.2; letter-spacing:-0.01em; }
  .mk-sdash-card p{ margin:0; line-height:1.6; }
  .mk-sdash-pill{
    display:inline-block; padding:4px 10px;
    border:1px solid rgba(0,0,0,0.12);
    border-radius:999px; background:#fff;
    font-size:.85rem; color:#495057;
  }
  .mk-sdash-meta{ margin-top:auto; display:flex; gap:10px; flex-wrap:wrap; }
  .mk-sdash-link{ text-decoration:none; color:inherit; display:block; height:100%; }
</style>

<div class="container" style="padding:24px 0;">
  <section class="mk-sdash-hero">
    <div class="mk-sdash-hero__bar"></div>
    <div class="mk-sdash-hero__inner">
      <h1>Staff Dashboard</h1>
      <p class="muted" style="margin:0;max-width:88ch;">
        Manage subjects, pages, contributors, platforms, accounts, and audits — with stable routing and no dead-end flows.
      </p>
      <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
        <span class="mk-sdash-pill">Protected</span>
        <span class="mk-sdash-pill">Stable Links</span>
        <span class="mk-sdash-pill">Fast</span>
      </div>
    </div>
  </section>

  <div class="section-title" style="margin-top:22px;">
    <h2 style="margin:0;">Actions</h2>
  </div>

  <section class="mk-sdash-grid" aria-label="Staff actions">
    <?php foreach ($cards as $c): ?>
      <div class="mk-sdash-card">
        <div class="mk-sdash-card__bar"></div>
        <a class="mk-sdash-link" href="<?= h((string)$c['href']) ?>">
          <div class="mk-sdash-card__body">
            <div class="mk-sdash-top">
              <div class="mk-sdash-icon" aria-hidden="true"><?= h((string)$c['icon']) ?></div>
              <div style="min-width:0;">
                <h3><?= h((string)$c['title']) ?></h3>
                <p class="muted"><?= h((string)$c['desc']) ?></p>
              </div>
            </div>
            <div class="mk-sdash-meta">
              <span class="mk-sdash-pill"><?= h((string)$c['tag']) ?></span>
              <span class="mk-sdash-pill">Open</span>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </section>
</div>

<?php
$staff_footer = (defined('PRIVATE_PATH') && is_string(PRIVATE_PATH) && PRIVATE_PATH !== '')
  ? (PRIVATE_PATH . '/shared/staff_footer.php')
  : (defined('APP_ROOT') ? (APP_ROOT . '/private/shared/staff_footer.php') : null);

if ($staff_footer && is_file($staff_footer)) {
  require $staff_footer;
} else {
  echo "</main></body></html>";
}
