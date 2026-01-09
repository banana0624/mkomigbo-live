<?php
declare(strict_types=1);

/**
 * /public/staff/account/audit.php
 * Staff: View audit log (database-backed)
 */

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

require_once __DIR__ . '/../../_init.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

/* Ensure auth */
if (!function_exists('mk_require_staff_login')) {
  $auth = null;
  if (defined('PRIVATE_PATH')) $auth = PRIVATE_PATH . '/functions/auth.php';
  elseif (defined('APP_ROOT')) $auth = APP_ROOT . '/private/functions/auth.php';
  if ($auth && is_file($auth)) require_once $auth;
}

if (function_exists('mk_require_staff_login')) {
  mk_require_staff_login();
} else {
  header('Location: /staff/login.php', true, 302);
  exit;
}

/* Helpers */
if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('url_for')) {
  function url_for(string $path): string { return '/' . ltrim($path, '/'); }
}

$page_title = 'Audit Log • Staff';
$page_desc  = 'Login/logout and security events.';
$active_nav = 'staff';
$nav_active = 'staff';

/* Header */
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

/* Validate DB */
$pdo = (function_exists('db') ? db() : null);
if (!$pdo instanceof PDO) {
  echo '<div class="container" style="padding:24px 0;"><div class="alert alert--danger">Database not available.</div></div>';
  // footer
  $staff_footer = (defined('PRIVATE_PATH') && is_file(PRIVATE_PATH . '/shared/staff_footer.php'))
    ? (PRIVATE_PATH . '/shared/staff_footer.php')
    : (defined('APP_ROOT') ? (APP_ROOT . '/private/shared/staff_footer.php') : null);
  if ($staff_footer && is_file($staff_footer)) require_once $staff_footer;
  exit;
}

/* Filters */
$staff_id = isset($_GET['staff_user_id']) && $_GET['staff_user_id'] !== '' ? (int)$_GET['staff_user_id'] : null;
$event    = isset($_GET['event']) ? trim((string)$_GET['event']) : '';
$from     = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
$to       = isset($_GET['to']) ? trim((string)$_GET['to']) : '';
$limit    = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

if ($limit < 10) $limit = 10;
if ($limit > 500) $limit = 500;

// dates must be YYYY-MM-DD
$from_ok = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $from);
$to_ok   = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $to);

$where = [];
$params = [];

if ($staff_id !== null && $staff_id > 0) {
  $where[] = "staff_user_id = :sid";
  $params[':sid'] = $staff_id;
}

if ($event !== '') {
  // allow exact match or prefix match with *
  if (str_ends_with($event, '*')) {
    $where[] = "event LIKE :ev";
    $params[':ev'] = rtrim($event, '*') . '%';
  } else {
    $where[] = "event = :ev";
    $params[':ev'] = $event;
  }
}

if ($from_ok) {
  $where[] = "ts >= :from_dt";
  $params[':from_dt'] = $from . " 00:00:00";
}

if ($to_ok) {
  $where[] = "ts <= :to_dt";
  $params[':to_dt'] = $to . " 23:59:59";
}

$sql = "SELECT id, ts, staff_user_id, event, ip, uri, user_agent, context_json
        FROM staff_audit_log";

if ($where) {
  $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY id DESC LIMIT " . (int)$limit;

$rows = [];
$err = '';

try {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $err = $e->getMessage();
}

?>
<div class="container" style="padding:24px 0;">
  <div class="section-title" style="margin-bottom: 10px;">
    <h1 style="margin:0;">Audit Log</h1>
    <p class="muted" style="margin:6px 0 0;">Database-backed login/logout/security events.</p>
  </div>

  <div class="card" style="margin-bottom: 14px;">
    <div class="card__body">
      <form method="get" action="<?= h(url_for('/staff/account/audit.php')) ?>" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; align-items:end;">
        <div class="field">
          <label class="label" for="staff_user_id">Staff ID</label>
          <input class="input" type="number" id="staff_user_id" name="staff_user_id" value="<?= h((string)($staff_id ?? '')) ?>" min="1" placeholder="e.g. 1">
        </div>

        <div class="field">
          <label class="label" for="event">Event</label>
          <input class="input" type="text" id="event" name="event" value="<?= h($event) ?>" placeholder="staff_login_ok or staff_login_*">
          <p class="muted" style="margin:.35rem 0 0;">Use <code>*</code> suffix for prefix match.</p>
        </div>

        <div class="field">
          <label class="label" for="from">From (YYYY-MM-DD)</label>
          <input class="input" type="text" id="from" name="from" value="<?= h($from) ?>" placeholder="2026-01-01">
        </div>

        <div class="field">
          <label class="label" for="to">To (YYYY-MM-DD)</label>
          <input class="input" type="text" id="to" name="to" value="<?= h($to) ?>" placeholder="2026-01-05">
        </div>

        <div class="field">
          <label class="label" for="limit">Limit</label>
          <input class="input" type="number" id="limit" name="limit" value="<?= h((string)$limit) ?>" min="10" max="500">
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <button class="btn" type="submit">Filter</button>
          <a class="btn btn--ghost" href="<?= h(url_for('/staff/account/audit.php')) ?>">Reset</a>
          <a class="btn btn--ghost" href="<?= h(url_for('/staff/')) ?>">Dashboard</a>
        </div>
      </form>
    </div>
  </div>

  <?php if ($err): ?>
    <div class="alert alert--danger" role="alert">
      <?= h($err) ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card__body" style="overflow:auto;">
      <table class="table" style="width:100%; border-collapse:collapse;">
        <thead>
          <tr>
            <th style="text-align:left;">ID</th>
            <th style="text-align:left;">Time</th>
            <th style="text-align:left;">Staff ID</th>
            <th style="text-align:left;">Event</th>
            <th style="text-align:left;">IP</th>
            <th style="text-align:left;">URI</th>
            <th style="text-align:left;">Context</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr>
              <td colspan="7" class="muted" style="padding:12px;">No audit records found for this filter.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td style="padding:10px; border-top:1px solid rgba(0,0,0,.08);"><?= h((string)$r['id']) ?></td>
                <td style="padding:10px; border-top:1px solid rgba(0,0,0,.08); white-space:nowrap;"><?= h((string)$r['ts']) ?></td>
                <td style="padding:10px; border-top:1px solid rgba(0,0,0,.08);"><?= h((string)($r['staff_user_id'] ?? '')) ?></td>
                <td style="padding:10px; border-top:1px solid rgba(0,0,0,.08);">
                  <code><?= h((string)$r['event']) ?></code>
                </td>
                <td style="padding:10px; border-top:1px solid rgba(0,0,0,.08); white-space:nowrap;"><?= h((string)($r['ip'] ?? '')) ?></td>
                <td style="padding:10px; border-top:1px solid rgba(0,0,0,.08);"><?= h((string)($r['uri'] ?? '')) ?></td>
                <td style="padding:10px; border-top:1px solid rgba(0,0,0,.08); max-width:360px;">
                  <div class="muted" style="white-space:pre-wrap; word-break:break-word; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size:.9rem;">
                    <?= h((string)($r['context_json'] ?? '')) ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
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
