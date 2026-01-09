<?php
declare(strict_types=1);

/**
 * /app/mkomigbo/private/functions/auth.php
 * Staff RBAC auth helpers for staff_users (supports admin-only pages via role column)
 *
 * Session keys:
 * - $_SESSION['staff_user_id'] (int)
 * - $_SESSION['staff_email']   (string)
 * - $_SESSION['staff_role']    ('admin'|'staff')
 *
 * Public API:
 * - mk_attempt_staff_login(email, password) => ['ok'=>bool,'id'=>int,'error'=>string]
 * - mk_is_staff_logged_in() => bool
 * - mk_require_staff_login() => redirects if not logged in (validates session vs DB)
 * - mk_require_staff_role('admin'|'staff') => gates admin-only pages
 * - mk_staff_logout() => clears session, regenerates id
 * - mk_staff_current_id() => int
 * - mk_staff_role() => string
 * - mk_is_admin() => bool
 */

/* ---------------------------------------------------------
   HTTPS detection (works behind proxies)
--------------------------------------------------------- */
if (!function_exists('mk__is_https')) {
  function mk__is_https(): bool {
    $https = $_SERVER['HTTPS'] ?? '';
    if (is_string($https) && ($https === 'on' || $https === '1')) return true;

    $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    if (is_string($proto) && strtolower($proto) === 'https') return true;

    $port = $_SERVER['SERVER_PORT'] ?? '';
    if ((is_string($port) && $port === '443') || (is_int($port) && $port === 443)) return true;

    return false;
  }
}

/* ---------------------------------------------------------
   Session start (secure cookies on HTTPS only)
--------------------------------------------------------- */
if (!function_exists('mk__session_start')) {
  function mk__session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    if (!headers_sent()) {
      @session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => mk__is_https(), // do NOT force secure=true on HTTP dev env
        'httponly' => true,
        'samesite' => 'Lax',
      ]);
    }

    @session_start();
  }
}

/* ---------------------------------------------------------
   Optional app logger bridge
--------------------------------------------------------- */
if (!function_exists('mk__auth_log')) {
  function mk__auth_log(string $level, string $message, array $context = []): void {
    if (function_exists('app_log')) { app_log($level, $message, $context); return; }
    if (function_exists('mk_log'))  { mk_log($level, $message, $context); return; }
  }
}

/* ---------------------------------------------------------
   Audit logger (best-effort only)
--------------------------------------------------------- */
if (!function_exists('mk_audit_log')) {
  function mk_audit_log(string $event, ?int $staff_user_id = null, array $context = []): void {
    try {
      if (!function_exists('db')) return;
      $pdo = db();
      if (!$pdo instanceof PDO) return;

      $ip  = $_SERVER['REMOTE_ADDR'] ?? null;
      $ua  = $_SERVER['HTTP_USER_AGENT'] ?? null;
      $uri = $_SERVER['REQUEST_URI'] ?? null;

      $ctx = null;
      if (!empty($context)) {
        $ctx = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($ctx)) $ctx = null;
        if (is_string($ctx) && strlen($ctx) > 8000) $ctx = substr($ctx, 0, 8000);
      }

      $st = $pdo->prepare(
        "INSERT INTO staff_audit_log (staff_user_id, event, ip, user_agent, uri, context_json)
         VALUES (:sid, :event, :ip, :ua, :uri, :ctx)"
      );
      $st->execute([
        ':sid'   => $staff_user_id,
        ':event' => $event,
        ':ip'    => $ip ? substr((string)$ip, 0, 64) : null,
        ':ua'    => $ua ? substr((string)$ua, 0, 255) : null,
        ':uri'   => $uri ? substr((string)$uri, 0, 255) : null,
        ':ctx'   => $ctx,
      ]);
    } catch (Throwable $e) {
      // swallow
    }
  }
}

if (!function_exists('mk_audit_from_session')) {
  function mk_audit_from_session(string $event, array $context = []): void {
    mk__session_start();
    $sid = (isset($_SESSION['staff_user_id']) && is_numeric($_SESSION['staff_user_id']))
      ? (int)$_SESSION['staff_user_id']
      : null;
    mk_audit_log($event, $sid, $context);
  }
}

/* ---------------------------------------------------------
   Role helpers
--------------------------------------------------------- */
if (!function_exists('mk_staff_role_normalize')) {
  function mk_staff_role_normalize($role): string {
    $role = is_string($role) ? strtolower(trim($role)) : 'staff';
    return in_array($role, ['admin', 'staff'], true) ? $role : 'staff';
  }
}

if (!function_exists('mk_staff_role')) {
  function mk_staff_role(): string {
    mk__session_start();
    return mk_staff_role_normalize($_SESSION['staff_role'] ?? 'staff');
  }
}

if (!function_exists('mk_is_admin')) {
  function mk_is_admin(): bool {
    return mk_staff_role() === 'admin';
  }
}

/* ---------------------------------------------------------
   Identity helpers
--------------------------------------------------------- */
if (!function_exists('mk_staff_current_id')) {
  function mk_staff_current_id(): int {
    mk__session_start();
    return (isset($_SESSION['staff_user_id']) && is_numeric($_SESSION['staff_user_id']))
      ? (int)$_SESSION['staff_user_id']
      : 0;
  }
}

if (!function_exists('mk_is_staff_logged_in')) {
  function mk_is_staff_logged_in(): bool {
    return mk_staff_current_id() > 0;
  }
}

if (!function_exists('mk_staff_current_email')) {
  function mk_staff_current_email(): string {
    mk__session_start();
    $email = $_SESSION['staff_email'] ?? '';
    return is_string($email) ? trim($email) : '';
  }
}

/* ---------------------------------------------------------
   Redirect helpers
--------------------------------------------------------- */
if (!function_exists('mk__safe_redirect')) {
  function mk__safe_redirect(string $path, int $code = 302): void {
    $dest = $path;

    if (function_exists('url_for')) {
      $dest = (string)url_for($path);
    }

    $dest = str_replace(["\r", "\n"], '', (string)$dest);
    header('Location: ' . $dest, true, $code);
    exit;
  }
}

/* ---------------------------------------------------------
   Validate session vs DB (and refresh role/email)
--------------------------------------------------------- */
if (!function_exists('mk_staff_session_validate')) {
  function mk_staff_session_validate(): bool {
    static $checked = false;
    static $ok = false;

    if ($checked) return $ok;
    $checked = true;

    $sid = mk_staff_current_id();
    if ($sid <= 0) { $ok = false; return $ok; }

    // fail-open if db() missing (prevents accidental lockout)
    if (!function_exists('db')) { $ok = true; return $ok; }

    try {
      $pdo = db();
      if (!$pdo instanceof PDO) { $ok = true; return $ok; }

      // Prefer selecting role; fallback if role column not present.
      $row = null;
      try {
        $st = $pdo->prepare("SELECT id, is_active, email, role FROM staff_users WHERE id = ? LIMIT 1");
        $st->execute([$sid]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
      } catch (Throwable $e) {
        $st = $pdo->prepare("SELECT id, is_active, email FROM staff_users WHERE id = ? LIMIT 1");
        $st->execute([$sid]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
      }

      if (!$row || (int)($row['is_active'] ?? 0) !== 1) {
        unset($_SESSION['staff_user_id'], $_SESSION['staff_email'], $_SESSION['staff_role']);
        @session_regenerate_id(true);
        $ok = false;
        return $ok;
      }

      if (isset($row['email']) && is_string($row['email'])) {
        $_SESSION['staff_email'] = trim($row['email']);
      }

      if (isset($row['role'])) {
        $_SESSION['staff_role'] = mk_staff_role_normalize($row['role']);
      } elseif (!isset($_SESSION['staff_role'])) {
        $_SESSION['staff_role'] = 'staff';
      }

      $ok = true;
      return $ok;

    } catch (Throwable $e) {
      // fail-open on transient DB errors
      $ok = true;
      return $ok;
    }
  }
}

/* ---------------------------------------------------------
   Require login
--------------------------------------------------------- */
if (!function_exists('mk_require_staff_login')) {
  function mk_require_staff_login(): void {
    mk__session_start();

    if (!mk_is_staff_logged_in()) {
      mk_audit_log('staff_access_denied', null, []);
      mk__safe_redirect('/staff/login.php', 302);
    }

    if (!mk_staff_session_validate()) {
      mk_audit_log('staff_access_denied_invalid_session', null, []);
      mk__safe_redirect('/staff/login.php', 302);
    }
  }
}

/* ---------------------------------------------------------
   Professional 403 page
--------------------------------------------------------- */
if (!function_exists('mk_forbidden_page')) {
  function mk_forbidden_page(string $title = 'Access denied', string $message = 'This page is restricted.'): void {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<style>
      body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:0;background:#f8f9fb;color:#111827}
      .wrap{max-width:860px;margin:48px auto;padding:0 18px}
      .card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:22px;box-shadow:0 10px 30px rgba(0,0,0,.06)}
      h1{margin:0 0 8px;font-size:22px}
      p{margin:0 0 16px;color:#4b5563;line-height:1.5}
      .actions{display:flex;gap:12px;flex-wrap:wrap}
      .btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:12px;border:1px solid #e5e7eb;background:#fff;text-decoration:none;color:#111827}
      .btn.primary{background:#111827;color:#fff;border-color:#111827}
    </style></head><body>';
    echo '<div class="wrap"><div class="card">';
    echo '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
    echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<div class="actions">';
    echo '<a class="btn primary" href="/staff/">Return to dashboard</a>';
    echo '<a class="btn" href="/staff/">Staff home</a>';
    echo '</div>';
    echo '</div></div></body></html>';
    exit;
  }
}

/* ---------------------------------------------------------
   Require role (admin-only gating)
--------------------------------------------------------- */
if (!function_exists('mk_require_staff_role')) {
  function mk_require_staff_role(string $requiredRole = 'admin'): void {
    mk_require_staff_login();

    $requiredRole = mk_staff_role_normalize($requiredRole);
    $currentRole  = mk_staff_role();

    $ok = ($requiredRole === 'staff')
      ? in_array($currentRole, ['staff', 'admin'], true)
      : ($currentRole === $requiredRole);

    if (!$ok) {
      mk_audit_log('staff_access_denied_role', mk_staff_current_id(), [
        'required' => $requiredRole,
        'role'     => $currentRole,
      ]);
      mk_forbidden_page('Access denied', 'This page is restricted to administrators.');
    }
  }
}

/* ---------------------------------------------------------
   Logout
--------------------------------------------------------- */
if (!function_exists('mk_staff_logout')) {
  function mk_staff_logout(): void {
    mk__session_start();

    $sid = mk_staff_current_id();
    mk_audit_log('staff_logout', ($sid > 0 ? $sid : null), []);

    unset($_SESSION['staff_user_id'], $_SESSION['staff_email'], $_SESSION['staff_role']);
    @session_regenerate_id(true);
  }
}

/* ---------------------------------------------------------
   Login attempt
--------------------------------------------------------- */
if (!function_exists('mk_attempt_staff_login')) {
  function mk_attempt_staff_login(string $email, string $password): array {
    $email = trim($email);
    $password = (string)$password;

    if ($email === '' || $password === '') {
      return ['ok' => false, 'id' => 0, 'error' => 'Email and password are required.'];
    }

    $email_norm = function_exists('mb_strtolower') ? mb_strtolower($email, 'UTF-8') : strtolower($email);
    $email_norm = trim($email_norm);

    $fail = 'Login failed. Confirm your email and password match staff_users.';

    if (!function_exists('db')) {
      return ['ok' => false, 'id' => 0, 'error' => 'DB helper db() not available.'];
    }

    try {
      $pdo = db();
      if (!$pdo instanceof PDO) {
        mk__auth_log('ERROR', 'Staff login: db() did not return PDO.');
        return ['ok' => false, 'id' => 0, 'error' => 'DB not available.'];
      }

      // Prefer selecting role; fallback if role column doesn't exist yet.
      $u = null;
      try {
        $sql = "SELECT id, email, password_hash, is_active, role
                FROM staff_users
                WHERE email = ?
                LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute([$email_norm]);
        $u = $st->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$u && $email_norm !== $email) {
          $st->execute([$email]);
          $u = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        }
      } catch (Throwable $e) {
        $sql = "SELECT id, email, password_hash, is_active
                FROM staff_users
                WHERE email = ?
                LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute([$email_norm]);
        $u = $st->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$u && $email_norm !== $email) {
          $st->execute([$email]);
          $u = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        }
      }

      if (!$u) {
        mk__auth_log('INFO', 'Staff login failed: email not found.', ['email' => $email_norm]);
        mk_audit_log('staff_login_failed_email', null, ['email' => $email_norm]);
        @usleep(150000);
        return ['ok' => false, 'id' => 0, 'error' => $fail];
      }

      $uid = (int)($u['id'] ?? 0);

      if ((int)($u['is_active'] ?? 0) !== 1) {
        mk__auth_log('INFO', 'Staff login failed: account inactive.', ['id' => $uid]);
        mk_audit_log('staff_login_failed_inactive', $uid, ['email' => (string)($u['email'] ?? $email_norm)]);
        @usleep(150000);
        return ['ok' => false, 'id' => 0, 'error' => 'Account is disabled.'];
      }

      $hash = trim((string)($u['password_hash'] ?? ''));
      if ($hash === '' || strlen($hash) < 20) {
        mk__auth_log('ERROR', 'Staff login failed: password_hash missing/short.', ['id' => $uid, 'hash_len' => strlen($hash)]);
        mk_audit_log('staff_login_failed_hash_invalid', $uid, ['hash_len' => strlen($hash)]);
        @usleep(150000);
        return ['ok' => false, 'id' => 0, 'error' => $fail];
      }

      if (!password_verify($password, $hash)) {
        mk__auth_log('INFO', 'Staff login failed: bad password.', ['id' => $uid]);
        mk_audit_log('staff_login_failed_password', $uid, ['email' => (string)($u['email'] ?? $email_norm)]);
        @usleep(150000);
        return ['ok' => false, 'id' => 0, 'error' => $fail];
      }

      // Non-blocking rehash
      if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        try {
          $new = password_hash($password, PASSWORD_DEFAULT);
          if ($new) {
            $up = $pdo->prepare("UPDATE staff_users SET password_hash = ? WHERE id = ? LIMIT 1");
            $up->execute([$new, $uid]);
            mk_audit_log('staff_login_rehash_ok', $uid, []);
          }
        } catch (Throwable $e) {
          mk__auth_log('WARN', 'Staff login: rehash update failed.', ['id' => $uid, 'err' => $e->getMessage()]);
          mk_audit_log('staff_login_rehash_failed', $uid, ['err' => $e->getMessage()]);
        }
      }

      // Commit session
      mk__session_start();
      @session_regenerate_id(true);

      $_SESSION['staff_user_id'] = $uid;
      $_SESSION['staff_email']   = (string)($u['email'] ?? $email_norm);
      $_SESSION['staff_role']    = mk_staff_role_normalize($u['role'] ?? 'staff');

      mk__auth_log('INFO', 'Staff login OK.', ['id' => $uid, 'role' => $_SESSION['staff_role']]);
      mk_audit_log('staff_login_ok', $uid, ['email' => $_SESSION['staff_email'], 'role' => $_SESSION['staff_role']]);

      return ['ok' => true, 'id' => $uid, 'error' => ''];

    } catch (Throwable $e) {
      mk__auth_log('ERROR', 'Staff login exception.', ['err' => $e->getMessage()]);
      mk_audit_log('staff_login_exception', null, ['err' => $e->getMessage()]);
      return ['ok' => false, 'id' => 0, 'error' => 'Login error.'];
    }
  }
}
