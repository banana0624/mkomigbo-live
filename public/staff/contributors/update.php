<?php
declare(strict_types=1);

/**
 * /public/staff/contributors/update.php
 * Staff: Update contributor (POST-only)
 *
 * Hardened:
 * - Recomputes slug safely (mk_slugify) and keeps it unique (mk_unique_contributor_slug), excluding current id
 * - Sanitizes bio_raw -> bio_html (mk_sanitize_bio_html preferred; else mk_sanitize_allowlist_html; else fail-safe)
 * - Schema-tolerant: updates only columns that exist
 * - No arrow functions
 */

require_once __DIR__ . '/../_init.php';

/* ---------------------------------------------------------
   Basic helpers
--------------------------------------------------------- */
if (!function_exists('redirect_to')) {
  function redirect_to(string $loc): void {
    $loc = str_replace(["\r", "\n"], '', $loc);
    header('Location: ' . $loc, true, 302);
    exit;
  }
}

if (!function_exists('pf__flash_set')) {
  function pf__flash_set(string $key, string $msg): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) $_SESSION['flash'] = [];
    $_SESSION['flash'][$key] = $msg;
  }
}

/* CSRF (field name: csrf_token) */
if (!function_exists('csrf_require')) {
  function csrf_require(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $sent = (string)($_POST['csrf_token'] ?? '');
    $sess = (string)($_SESSION['csrf_token'] ?? '');
    if ($sent === '' || $sess === '' || !hash_equals($sess, $sent)) {
      http_response_code(403);
      header('Content-Type: text/plain; charset=utf-8');
      echo "Invalid CSRF token.";
      exit;
    }
  }
}

/* Safe return (staff-only) */
if (!function_exists('pf__safe_return_url')) {
  function pf__safe_return_url(string $raw, string $default): string {
    $raw = trim($raw);
    if ($raw === '') return $default;
    $raw = rawurldecode($raw);
    if ($raw === '' || $raw[0] !== '/') return $default;
    if (preg_match('~^//~', $raw)) return $default;
    if (preg_match('~^[a-z]+:~i', $raw)) return $default;
    if (!preg_match('~^/staff/~', $raw)) return $default;
    return $raw;
  }
}

/* Schema helpers */
if (!function_exists('pf__table_exists')) {
  function pf__table_exists(PDO $pdo, string $table): bool {
    try {
      $st = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
      $st->execute([$table]);
      return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
      return false;
    }
  }
}
if (!function_exists('pf__column_exists')) {
  function pf__column_exists(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $k = strtolower($table . '.' . $column);
    if (array_key_exists($k, $cache)) return (bool)$cache[$k];
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
    $st->execute([$table, $column]);
    $cache[$k] = ((int)$st->fetchColumn() > 0);
    return (bool)$cache[$k];
  }
}

/* Roles normalization */
if (!function_exists('pf__normalize_roles')) {
  function pf__normalize_roles(string $raw): array {
    $raw = trim($raw);
    if ($raw === '') return ['ok' => true, 'value' => '[]'];

    if (isset($raw[0]) && $raw[0] === '[') {
      $decoded = json_decode($raw, true);
      if (!is_array($decoded)) {
        return [
          'ok' => false,
          'value' => '',
          'error' => 'Roles JSON is invalid. Use CSV (Author,Editor) or JSON array ["Author","Editor"].'
        ];
      }
      $out = [];
      foreach ($decoded as $v) {
        $v = trim((string)$v);
        if ($v !== '') $out[] = $v;
      }
      $out = array_values(array_unique($out));
      return ['ok' => true, 'value' => json_encode($out, JSON_UNESCAPED_UNICODE)];
    }

    $parts = preg_split('/\s*,\s*/', $raw) ?: [];
    $out = [];
    foreach ($parts as $p) {
      $p = trim((string)$p);
      if ($p !== '') $out[] = $p;
    }
    $out = array_values(array_unique($out));
    return ['ok' => true, 'value' => json_encode($out, JSON_UNESCAPED_UNICODE)];
  }
}

/* ---------------------------------------------------------
   Method / CSRF
--------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  header('Allow: POST');
  echo "Method Not Allowed";
  exit;
}
csrf_require();

/* Return + id */
$id = (int)($_POST['id'] ?? 0);
$default_return = '/staff/contributors/index.php';
$return = pf__safe_return_url((string)($_POST['return'] ?? $default_return), $default_return);

if ($id <= 0) {
  pf__flash_set('error', 'Invalid contributor id.');
  redirect_to($return);
}

/* DB */
$pdo = function_exists('staff_pdo') ? staff_pdo() : (function_exists('db') ? db() : null);
if (!$pdo instanceof PDO) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Database handle not available.\n";
  exit;
}
if (!pf__table_exists($pdo, 'contributors')) {
  pf__flash_set('error', 'contributors table not found.');
  redirect_to($return);
}

/* ---------------------------------------------------------
   Detect columns (schema-tolerant)
--------------------------------------------------------- */
$table = 'contributors';

$cols = [
  'display_name' => pf__column_exists($pdo, $table, 'display_name'),
  'name'         => pf__column_exists($pdo, $table, 'name'),
  'username'     => pf__column_exists($pdo, $table, 'username'),
  'slug'         => pf__column_exists($pdo, $table, 'slug'),
  'email'        => pf__column_exists($pdo, $table, 'email'),
  'roles'        => pf__column_exists($pdo, $table, 'roles'),
  'status'       => pf__column_exists($pdo, $table, 'status'),
  'avatar_path'  => pf__column_exists($pdo, $table, 'avatar_path'),
  'bio_raw'      => pf__column_exists($pdo, $table, 'bio_raw'),
  'bio_html'     => pf__column_exists($pdo, $table, 'bio_html'),
  'bio'          => pf__column_exists($pdo, $table, 'bio'),
];

/* ---------------------------------------------------------
   Read inputs
--------------------------------------------------------- */
$display_name = trim((string)($_POST['display_name'] ?? ''));
$name         = trim((string)($_POST['name'] ?? ''));
$username     = trim((string)($_POST['username'] ?? ''));
$slug_input   = trim((string)($_POST['slug'] ?? ''));
$email        = trim((string)($_POST['email'] ?? ''));
$roles_raw    = (string)($_POST['roles'] ?? '');
$status_raw   = strtolower(trim((string)($_POST['status'] ?? '')));
$avatar_path  = trim((string)($_POST['avatar_path'] ?? ''));
$bio_raw_in   = (string)($_POST['bio_raw'] ?? '');

/* Required display_name in your real schema */
if ($cols['display_name'] && $display_name === '') {
  pf__flash_set('error', 'Display name is required.');
  redirect_to('/staff/contributors/edit.php?id=' . rawurlencode((string)$id) . '&return=' . rawurlencode($return));
}

/* Status validation (only accept active/draft) */
$status = '';
if ($cols['status']) {
  if ($status_raw === '') $status_raw = 'active';
  if ($status_raw !== 'active' && $status_raw !== 'draft') {
    pf__flash_set('error', 'Invalid status. Use active or draft.');
    redirect_to('/staff/contributors/edit.php?id=' . rawurlencode((string)$id) . '&return=' . rawurlencode($return));
  }
  $status = $status_raw;
}

/* ---------------------------------------------------------
   Slug recompute + uniqueness (only if slug column exists)
--------------------------------------------------------- */
$slug_final = null;
if ($cols['slug']) {
  // Base is explicit slug if provided, else display_name, else name/username as fallback.
  $base_source = $slug_input;
  if ($base_source === '') $base_source = $display_name;
  if ($base_source === '') $base_source = $name;
  if ($base_source === '') $base_source = $username;
  if ($base_source === '') $base_source = 'contributor';

  if (function_exists('mk_slugify')) {
    $base = mk_slugify($base_source, 'contributor');
  } else {
    // Safe fallback slugify
    $base = strtolower(trim($base_source));
    $base = preg_replace('/[^\p{L}\p{N}]+/u', '-', $base) ?? $base;
    $base = trim($base, '-');
    if ($base === '') $base = 'contributor';
  }

  if (function_exists('mk_unique_contributor_slug')) {
    $slug_final = mk_unique_contributor_slug($pdo, $base, $id);
  } else {
    // Fallback: do not attempt uniqueness without helper; still set sanitized base
    $slug_final = $base;
  }
}

/* ---------------------------------------------------------
   Bio sanitize (bio_raw -> bio_html) with fail-safe behavior
--------------------------------------------------------- */
$bio_raw_to_save  = $bio_raw_in;
$bio_html_to_save = null; // only set if we are allowed to update it safely

if ($cols['bio_raw'] || $cols['bio_html'] || $cols['bio']) {
  // Save raw to bio_raw if supported, else legacy bio
  // For bio_html: only overwrite if we have a sanitizer OR user is clearing bio.
  $can_sanitize = false;

  if (function_exists('mk_sanitize_bio_html')) {
    $can_sanitize = true;
    $bio_html_to_save = mk_sanitize_bio_html($bio_raw_to_save);
  } else {
    // Try your existing sanitizer file (if present)
    if (!function_exists('mk_sanitize_allowlist_html')) {
      $san = defined('APP_ROOT') ? (APP_ROOT . '/private/functions/sanitize.php') : null;
      if ($san && is_file($san)) require_once $san;
    }
    if (function_exists('mk_sanitize_allowlist_html')) {
      $can_sanitize = true;
      $bio_html_to_save = mk_sanitize_allowlist_html($bio_raw_to_save);
    }
  }

  if (!$can_sanitize) {
    // Fail-safe: allow clearing bio_html, but do not overwrite with unsafe raw
    if (trim($bio_raw_to_save) === '') {
      $bio_html_to_save = '';
    } else {
      $bio_html_to_save = null; // leave existing bio_html untouched
    }
  }
}

/* ---------------------------------------------------------
   Build UPDATE
--------------------------------------------------------- */
$set = [];
$params = [':id' => $id];

/* Basic fields */
if ($cols['display_name']) { $set[] = 'display_name = :display_name'; $params[':display_name'] = $display_name; }
if ($cols['name'])         { $set[] = 'name = :name';                 $params[':name'] = ($name === '' ? null : $name); }
if ($cols['username'])     { $set[] = 'username = :username';         $params[':username'] = ($username === '' ? null : $username); }
if ($cols['email'])        { $set[] = 'email = :email';               $params[':email'] = ($email === '' ? null : $email); }
if ($cols['avatar_path'])  { $set[] = 'avatar_path = :avatar_path';   $params[':avatar_path'] = ($avatar_path === '' ? null : $avatar_path); }

if ($cols['status']) {
  $set[] = 'status = :status';
  $params[':status'] = $status;
}

/* Slug */
if ($cols['slug']) {
  // Store NULL only if user intentionally clears AND you want that. Most sites want a slug always.
  // Here we always store a computed slug_final (never null).
  $set[] = 'slug = :slug';
  $params[':slug'] = (string)$slug_final;
}

/* Roles */
if ($cols['roles']) {
  $roles_norm = pf__normalize_roles($roles_raw);
  if (!$roles_norm['ok']) {
    pf__flash_set('error', $roles_norm['error'] ?? 'Invalid roles value.');
    redirect_to('/staff/contributors/edit.php?id=' . rawurlencode((string)$id) . '&return=' . rawurlencode($return));
  }
  $set[] = 'roles = :roles';
  $params[':roles'] = $roles_norm['value'];
}

/* Bio fields */
if ($cols['bio_raw']) {
  $set[] = 'bio_raw = :bio_raw';
  $params[':bio_raw'] = $bio_raw_to_save;
} elseif ($cols['bio']) {
  // legacy bio
  $set[] = 'bio = :bio';
  $params[':bio'] = $bio_raw_to_save;
}

if ($cols['bio_html'] && $bio_html_to_save !== null) {
  $set[] = 'bio_html = :bio_html';
  $params[':bio_html'] = $bio_html_to_save;
}

if (!$set) {
  pf__flash_set('error', 'No writable columns detected.');
  redirect_to($return);
}

try {
  $sql = "UPDATE contributors SET " . implode(', ', $set) . " WHERE id = :id LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute($params);

  pf__flash_set('notice', 'Contributor updated.');
  redirect_to('/staff/contributors/show.php?id=' . rawurlencode((string)$id) . '&return=' . rawurlencode($return));

} catch (Throwable $e) {
  $msg = $e->getMessage();

  // Friendlier messages for common unique collisions
  if (stripos($msg, 'Duplicate') !== false) {
    if (stripos($msg, 'slug') !== false) {
      pf__flash_set('error', 'Update failed: slug already exists for another contributor.');
    } elseif (stripos($msg, 'email') !== false) {
      pf__flash_set('error', 'Update failed: email already exists for another contributor.');
    } else {
      pf__flash_set('error', 'Update failed: duplicate value.');
    }
  } else {
    pf__flash_set('error', 'Update failed: ' . $msg);
  }

  redirect_to('/staff/contributors/edit.php?id=' . rawurlencode((string)$id) . '&return=' . rawurlencode($return));
}
