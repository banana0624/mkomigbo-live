<?php
declare(strict_types=1);

/**
 * /public/staff/account/index.php
 * Staff account home
 */

require_once __DIR__ . '/../../_init.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

if (!function_exists('mk_require_staff_login')) {
  header('Location: /staff/login.php', true, 302);
  exit;
}
mk_require_staff_login();

if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('url_for')) {
  function url_for(string $path): string { return '/' . ltrim($path, '/'); }
}

$page_title = 'Account • Staff';
$active_nav = 'staff';
$nav_active = 'staff';

$staff_header = (defined('PRIVATE_PATH') && is_file(PRIVATE_PATH . '/shared/staff_header.php'))
  ? (PRIVATE_PATH . '/shared/staff_header.php')
  : (defined('APP_ROOT') ? (APP_ROOT . '/private/shared/staff_header.php') : null);

if (!$staff_header || !is_file($staff_header)) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Staff header missing.\n";
  exit;
}
require_once $staff_header;

$href_pwd = url_for('/staff/account/password.php');
?>

<div class="container" style="max-width: 900px; padding: 24px 0;">
  <div class="section-title" style="margin-bottom: 10px;">
    <h1 style="margin:0;">Account</h1>
    <p class="muted" style="margin:6px 0 0;">Manage your staff account settings.</p>
  </div>

  <div class="card">
    <div class="card__body" style="display:flex; align-items:flex-start; justify-content:space-between; gap:14px; flex-wrap:wrap;">
      <div>
        <h2 style="margin:0 0 8px;">Password</h2>
        <p class="muted" style="margin:0; line-height:1.6;">Change your password securely.</p>
      </div>
      <div style="display:flex; gap:10px;">
        <a class="btn" href="<?= h($href_pwd) ?>">Change password</a>
        <a class="btn btn--ghost" href="<?= h(url_for('/staff/')) ?>">Back to Dashboard</a>
      </div>
    </div>
  </div>
</div>

<?php
$staff_footer = (defined('PRIVATE_PATH') && is_file(PRIVATE_PATH . '/shared/staff_footer.php'))
  ? (PRIVATE_PATH . '/shared/staff_footer.php')
  : (defined('APP_ROOT') ? (APP_ROOT . '/private/shared/staff_footer.php') : null);

if ($staff_footer && is_file($staff_footer)) {
  require_once $staff_footer;
} else {
  echo "</main></body></html>";
}
