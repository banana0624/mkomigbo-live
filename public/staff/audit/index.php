<?php
declare(strict_types=1);

/**
 * /public/staff/audit/index.php
 * Staff: Audit Log Viewer (ADMIN ONLY)
 *
 * Requirements:
 * - /public/_init.php must define APP_ROOT and load db()
 * - /private/functions/auth.php must be available (mk_require_staff_role)
 */

require_once __DIR__ . '/../../_init.php';

if (!function_exists('mk_attempt_staff_login')) {
  $auth = null;
  if (defined('PRIVATE_PATH')) $auth = PRIVATE_PATH . '/functions/auth.php';
  elseif (defined('APP_ROOT')) $auth = APP_ROOT . '/private/functions/auth.php';
  if ($auth && is_file($auth)) require_once $auth;
}

if (function_exists('mk_require_staff_role')) {
  mk_require_staff_role('admin');
} else {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Auth system not loaded (mk_require_staff_role missing).";
  exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

$nav_active = 'audit';
$page_title = 'Audit Log • Staff';

/* Header/footer */
$staff_header = defined('APP_ROOT') ? (APP_ROOT . '/private/shared/staff_header.php') : null;
$staff_footer = defined('APP_ROOT') ? (APP_ROOT . '/private/shared/staff_footer.php') : null;

if (!$staff_header || !is_file($staff_header)) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Staff header not found. APP_ROOT is not set correctly.\n";
  exit;
}

/* Filters */
$event = trim((string)($_GET['event'] ?? ''));
$q     = trim((string)($_GET['q'] ?? ''));
$sid   = trim((string)($_GET['staff_user_id'] ?? ''));
$page  = (int)($_GET['page'] ?? 1);
$per   = (int)($_GET['per_page'] ?? 50);

if ($page < 1) $page = 1;
if ($per < 10) $per = 10;
if ($per > 200) $per = 200;

$staff_user_id = (is_numeric($sid) ? (int)$sid : 0);

$rows = [];
$total = 0;
$table_ok = true;
$db_error = '';

if (!function_exists('db')) {
  $table_ok = false;
  $db_error = 'db() helper is not available.';
} else {
  try {
    $pdo = db();
    if (!$pdo instanceof PDO) {
      $table_ok = false;
      $db_error = 'db() did not return PDO.';
    } else {
      // Confirm table exists
      $chk = $pdo->query("SHOW TABLES LIKE 'staff_audit_log'");
      $exists = $chk ? (bool)$chk->fetchColumn() : false;
      if (!$exists) {
        $table_ok = false;
        $db_error = "Table staff_audit_log does not exist.";
      } else {
        $where = [];
        $params = [];

        if ($event !== '') {
          $where[] = "al.event = :event";
          $params[':event'] = $event;
        }

        if ($staff_user_id > 0) {
          $where[] = "al.staff_user_id = :sid";
          $params[':sid'] = $staff_user_id;
        }

        if ($q !== '') {
          // search across event, uri, ip, email, context_json (best-effort)
          $where[] = "(al.event LIKE :q OR al.uri LIKE :q OR al.ip LIKE :q OR su.email LIKE :q OR al.context_json LIKE :q)";
          $params[':q'] = '%' . $q . '%';
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Count
        $countSql = "SELECT COUNT(*) AS c
                    FROM staff_audit_log al
                    LEFT JOIN staff_users su ON su.id = al.staff_user_id
                    $whereSql";
        $stc = $pdo->prepare($countSql);
        $stc->execute($params);
        $total = (int)($stc->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

        $offset = ($page - 1) * $per;

        $sql = "SELECT
                  al.id,
                  al.created_at,
                  al.staff_user_id,
                  su.email AS staff_email,
                  al.event,
                  al.ip,
                  al.uri,
                  al.context_json
                FROM staff_audit_log al
                LEFT JOIN staff_users su ON su.id = al.staff_user_id
                $whereSql
                ORDER BY al.id DESC
                LIMIT :lim OFFSET :off";

        $st = $pdo->prepare($sql);

        foreach ($params as $k => $v) {
          $st->bindValue($k, $v);
        }
        $st->bindValue(':lim', $per, PDO::PARAM_INT);
        $st->bindValue(':off', $offset, PDO::PARAM_INT);

        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
      }
    }
  } catch (Throwable $e) {
    $table_ok = false;
    $db_error = $e->getMessage();
  }
}

/* Simple pager */
$total_pages = ($per > 0) ? (int)ceil($total / $per) : 1;
if ($total_pages < 1) $total_pages = 1;
if ($page > $total_pages) $page = $total_pages;

$build_url = function (array $overrides = []) use ($event, $q, $staff_user_id, $per, $page): string {
  $base = '/staff/audit/';
  $qs = [
    'event' => $event,
    'q' => $q,
    'staff_user_id' => ($staff_user_id > 0 ? (string)$staff_user_id : ''),
    'per_page' => (string)$per,
    'page' => (string)$page,
  ];
  foreach ($overrides as $k => $v) {
    $qs[$k] = $v;
  }
  // drop empties
  foreach ($qs as $k => $v) {
    if ($v === '' || $v === null) unset($qs[$k]);
  }
  return $base . (empty($qs) ? '' : ('?' . http_build_query($qs)));
};

require_once $staff_header;
?>

<div class="container">
  <div class="card" style="margin-top: 18px;">
    <div class="card__body">
      <div style="display:flex; gap:14px; align-items:flex-start; justify-content:space-between; flex-wrap:wrap;">
        <div>
          <h1 style="margin:0 0 6px;">Audit Log</h1>
          <p class="muted" style="margin:0; line-height:1.6;">
            Admin-only activity trail for staff authentication and sensitive actions.
          </p>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
          <a class="btn btn--ghost" href="<?= h($build_url(['event'=>'','q'=>'','staff_user_id'=>'','page'=>'1'])) ?>">Reset</a>
          <a class="btn" href="<?= h($build_url(['page'=>'1'])) ?>">Refresh</a>
        </div>
      </div>

      <form method="get" action="<?= function_exists('url_for') ? h(url_for('/staff/audit/')) : '/staff/audit/' ?>" style="margin-top:14px;">
        <div style="display:grid; grid-template-columns: 1.2fr 1fr 1fr 0.7fr; gap:10px;">
          <div class="field">
            <label class="label" for="q">Search</label>
            <input class="input" type="text" id="q" name="q" value="<?= h($q) ?>" placeholder="event, email, uri, ip, context...">
          </div>

          <div class="field">
            <label class="label" for="event">Event</label>
            <input class="input" type="text" id="event" name="event" value="<?= h($event) ?>" placeholder="e.g. staff_login_ok">
          </div>

          <div class="field">
            <label class="label" for="staff_user_id">Staff User ID</label>
            <input class="input" type="number" min="0" id="staff_user_id" name="staff_user_id" value="<?= $staff_user_id > 0 ? h((string)$staff_user_id) : '' ?>" placeholder="e.g. 1">
          </div>

          <div class="field">
            <label class="label" for="per_page">Per page</label>
            <input class="input" type="number" min="10" max="200" id="per_page" name="per_page" value="<?= h((string)$per) ?>">
          </div>
        </div>

        <div style="display:flex; gap:10px; align-items:center; margin-top:12px;">
          <button class="btn" type="submit">Apply</button>
          <span class="muted">
            Total: <strong><?= (int)$total ?></strong>
            <?php if ($total > 0): ?>
              • Page <strong><?= (int)$page ?></strong> / <strong><?= (int)$total_pages ?></strong>
            <?php endif; ?>
          </span>
        </div>
      </form>

      <?php if (!$table_ok): ?>
        <div class="alert alert--danger" role="alert" style="margin-top:14px;">
          <strong>Audit log not available.</strong>
          <div style="margin-top:6px; line-height:1.6;">
            <?= h($db_error) ?>
          </div>
          <div style="margin-top:10px;">
            <div class="muted">If you haven’t created the table yet, run:</div>
            <pre style="white-space:pre-wrap; margin:10px 0 0; padding:12px; border:1px solid #e5e7eb; border-radius:12px; background:#fff;">
CREATE TABLE IF NOT EXISTS staff_audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_user_id INT NULL,
  event VARCHAR(191) NOT NULL,
  ip VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  uri VARCHAR(255) NULL,
  context_json TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_staff_audit_log_created_at (created_at),
  KEY idx_staff_audit_log_event (event),
  KEY idx_staff_audit_log_staff_user_id (staff_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            </pre>
          </div>
        </div>
      <?php else: ?>

        <?php if (!$rows): ?>
          <div class="alert alert--info" role="alert" style="margin-top:14px;">
            No audit events match your filters.
          </div>
        <?php else: ?>
          <div style="overflow:auto; margin-top:14px; border:1px solid #e5e7eb; border-radius:14px;">
            <table class="table" style="min-width: 980px;">
              <thead>
                <tr>
                  <th style="white-space:nowrap;">ID</th>
                  <th style="white-space:nowrap;">Time</th>
                  <th style="white-space:nowrap;">Staff</th>
                  <th style="white-space:nowrap;">Event</th>
                  <th style="white-space:nowrap;">IP</th>
                  <th>URI</th>
                  <th>Context</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $r): ?>
                  <?php
                    $rid = (int)($r['id'] ?? 0);
                    $ts  = (string)($r['created_at'] ?? '');
                    $sid2 = (int)($r['staff_user_id'] ?? 0);
                    $email = (string)($r['staff_email'] ?? '');
                    $evt = (string)($r['event'] ?? '');
                    $ip = (string)($r['ip'] ?? '');
                    $uri = (string)($r['uri'] ?? '');
                    $ctx = (string)($r['context_json'] ?? '');
                    if (strlen($ctx) > 220) $ctx = substr($ctx, 0, 220) . '…';
                  ?>
                  <tr>
                    <td><?= (int)$rid ?></td>
                    <td style="white-space:nowrap;"><?= h($ts) ?></td>
                    <td style="white-space:nowrap;">
                      <?php if ($sid2 > 0): ?>
                        <a href="<?= h($build_url(['staff_user_id' => (string)$sid2, 'page' => '1'])) ?>"><?= h((string)$sid2) ?></a>
                        <?php if ($email !== ''): ?>
                          <div class="muted" style="font-size:.85em;"><?= h($email) ?></div>
                        <?php endif; ?>
                      <?php else: ?>
                        <span class="muted">—</span>
                      <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;">
                      <a href="<?= h($build_url(['event' => $evt, 'page' => '1'])) ?>"><?= h($evt) ?></a>
                    </td>
                    <td style="white-space:nowrap;"><?= h($ip) ?></td>
                    <td style="max-width:360px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= h($uri) ?></td>
                    <td style="max-width:340px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= h($ctx) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div style="display:flex; gap:10px; align-items:center; justify-content:space-between; flex-wrap:wrap; margin-top:12px;">
            <div class="muted">
              Showing <?= count($rows) ?> of <?= (int)$total ?>.
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
              <?php if ($page > 1): ?>
                <a class="btn btn--ghost" href="<?= h($build_url(['page' => (string)($page - 1)])) ?>">Prev</a>
              <?php endif; ?>
              <?php if ($page < $total_pages): ?>
                <a class="btn btn--ghost" href="<?= h($build_url(['page' => (string)($page + 1)])) ?>">Next</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

      <?php endif; ?>
    </div>
  </div>
</div>

<?php
if ($staff_footer && is_file($staff_footer)) {
  require_once $staff_footer;
}
?>
