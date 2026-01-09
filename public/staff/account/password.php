<?php
declare(strict_types=1);

/**
 * /public/staff/account/password.php
 * Staff: Change own password (permanent)
 *
 * Security:
 * - Requires login (RBAC)
 * - Validates session user against DB (via mk_require_staff_login)
 * - Requires current password
 * - Updates password_hash using PASSWORD_DEFAULT
 * - Regenerates session id on success
 * - Writes DB audit record on success (staff_password_changed)
 */

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

require_once __DIR__ . '/../../_init.php';

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

/* No-cache for account pages */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('is_post_request')) {
  function is_post_request(): bool { return (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'); }
}
if (!function_exists('url_for')) {
  function url_for(string $path): string { return '/' . ltrim($path, '/'); }
}
if (!function_exists('redirect_to')) {
  function redirect_to(string $url): void {
    $url = str_replace(["\r","\n"], '', $url);
    header('Location: ' . $url, true, 302);
    exit;
  }
}

/* Ensure auth helpers exist */
if (!function_exists('mk_require_staff_login') || !function_exists('mk_staff_current_id')) {
  $auth = null;
  if (defined('PRIVATE_PATH') && is_string(PRIVATE_PATH) && PRIVATE_PATH !== '') {
    $auth = PRIVATE_PATH . '/functions/auth.php';
  } elseif (defined('APP_ROOT')) {
    $auth = APP_ROOT . '/private/functions/auth.php';
  }
  if ($auth && is_file($auth)) require_once $auth;
}

/* Require login (auth.php will also validate session vs DB and clear invalid sessions) */
if (function_exists('mk_require_staff_login')) {
  mk_require_staff_login();
} else {
  redirect_to(url_for('/staff/login.php'));
}

/* Resolve logged-in staff id */
$staff_id = function_exists('mk_staff_current_id')
  ? (int)mk_staff_current_id()
  : ((isset($_SESSION['staff_user_id']) && is_numeric($_SESSION['staff_user_id'])) ? (int)$_SESSION['staff_user_id'] : 0);

if ($staff_id <= 0) {
  redirect_to(url_for('/staff/login.php'));
}

/* Page meta */
$page_title = 'Change Password • Staff';
$page_desc  = 'Update your staff account password.';
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

/* Policy */
$MIN_LEN = 12; // stronger default than 10

/* State */
$errors = [];
$ok_msg = '';

/**
 * Optional strength check (simple, offline).
 * You can relax this if you want.
 */
function mk_password_strength_errors(string $pw): array {
  $errs = [];
  if (!preg_match('/[A-Z]/', $pw)) $errs[] = 'Include at least one uppercase letter.';
  if (!preg_match('/[a-z]/', $pw)) $errs[] = 'Include at least one lowercase letter.';
  if (!preg_match('/[0-9]/', $pw)) $errs[] = 'Include at least one number.';
  return $errs;
}

/* Handle POST */
if (is_post_request()) {
  if (function_exists('csrf_require')) { csrf_require(); }

  $current = (string)($_POST['current_password'] ?? '');
  $new1    = (string)($_POST['new_password'] ?? '');
  $new2    = (string)($_POST['confirm_password'] ?? '');

  if ($current === '') { $errors[] = 'Current password is required.'; }
  if ($new1 === '')    { $errors[] = 'New password is required.'; }
  if ($new2 === '')    { $errors[] = 'Please confirm the new password.'; }

  if ($new1 !== '' && strlen($new1) < $MIN_LEN) {
    $errors[] = "New password must be at least {$MIN_LEN} characters.";
  }
  if ($new1 !== $new2) {
    $errors[] = 'New password and confirmation do not match.';
  }
  if ($current !== '' && $new1 !== '' && hash_equals($current, $new1)) {
    $errors[] = 'New password must be different from the current password.';
  }

  // Strength (optional; comment out if you want only length)
  if ($new1 !== '') {
    foreach (mk_password_strength_errors($new1) as $se) {
      $errors[] = $se;
    }
  }

  if (!$errors) {
    if (!function_exists('db')) {
      $errors[] = 'Database not available.';
    } else {
      try {
        $pdo = db();
        if (!$pdo instanceof PDO) {
          $errors[] = 'Database not available.';
        } else {
          // Fetch current hash
          $st = $pdo->prepare("SELECT email, password_hash, is_active FROM staff_users WHERE id = ? LIMIT 1");
          $st->execute([$staff_id]);
          $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;

          if (!$row || (int)($row['is_active'] ?? 0) !== 1) {
            $errors[] = 'Account not available.';
          } else {
            $hash = (string)($row['password_hash'] ?? '');
            if ($hash === '' || !password_verify($current, $hash)) {
              // audit failed attempt (safe: no secrets)
              if (function_exists('mk_audit_log')) {
                mk_audit_log('staff_password_change_failed_current', $staff_id, []);
              }
              $errors[] = 'Current password is incorrect.';
            } else {
              $new_hash = password_hash($new1, PASSWORD_DEFAULT);
              if (!$new_hash) {
                $errors[] = 'Unable to secure the new password.';
              } else {
                $up = $pdo->prepare("UPDATE staff_users SET password_hash = ? WHERE id = ? LIMIT 1");
                $up->execute([$new_hash, $staff_id]);

                // Regenerate session to reduce fixation risk
                @session_regenerate_id(true);

                // Audit success (DB)
                if (function_exists('mk_audit_log')) {
                  mk_audit_log('staff_password_changed', $staff_id, ['method' => 'self']);
                }

                // Optional file logger (if present)
                if (function_exists('mk__auth_log')) {
                  mk__auth_log('INFO', 'Staff password changed.', ['id' => $staff_id, 'email' => (string)($row['email'] ?? '')]);
                }

                $ok_msg = 'Password updated successfully.';
              }
            }
          }
        }
      } catch (Throwable $e) {
        $errors[] = 'Error: ' . $e->getMessage();
      }
    }
  }
}
?>

<div class="container" style="max-width: 720px; padding: 24px 0;">
  <div class="section-title" style="margin-bottom: 10px;">
    <h1 style="margin:0;">Change Password</h1>
    <p class="muted" style="margin:6px 0 0;">Update your staff account password securely.</p>
  </div>

  <?php if ($ok_msg): ?>
    <div class="alert alert--success" role="alert"><?= h($ok_msg) ?></div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="alert alert--danger" role="alert">
      <ul style="margin: 0; padding-left: 18px;">
        <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card__body">
      <form method="post" action="<?= h(url_for('/staff/account/password.php')) ?>" autocomplete="off">
        <?php if (function_exists('csrf_token')): ?>
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <?php endif; ?>

        <div class="field">
          <label class="label" for="current_password">Current password</label>
          <input class="input" type="password" id="current_password" name="current_password" required autocomplete="current-password">
        </div>

        <div class="field">
          <label class="label" for="new_password">New password</label>
          <input class="input" type="password" id="new_password" name="new_password" required autocomplete="new-password">
          <p class="muted" style="margin:.4rem 0 0;">Minimum <?= (int)$MIN_LEN ?> characters; include upper/lowercase and a number.</p>
        </div>

        <div class="field">
          <label class="label" for="confirm_password">Confirm new password</label>
          <input class="input" type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
        </div>

        <div style="display:flex; gap:10px; align-items:center; margin-top:14px;">
          <button class="btn" type="submit">Update password</button>
          <a class="btn btn--ghost" href="<?= h(url_for('/staff/')) ?>">Back to Dashboard</a>
          <a class="btn btn--ghost" href="<?= h(url_for('/staff/account/audit.php')) ?>">View Audit</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card" style="margin-top: 14px;">
    <div class="card__body">
      <h2 style="margin:0 0 8px;">Security notes</h2>
      <ul style="margin:0; padding-left:18px; line-height:1.7;">
        <li>Use a long, unique password.</li>
        <li>If you suspect compromise, change password, then visit <code>/staff/logout.php</code> to reset cookies.</li>
      </ul>
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
