<?php
declare(strict_types=1);

/**
 * /private/functions/authz.php
 * RBAC authorization helpers (capabilities).
 *
 * Expects:
 * - db(): PDO exists (from your database/bootstrap layer)
 * - $_SESSION['staff_user_id'] holds the logged-in staff user id
 */

if (defined('MK_AUTHZ_LOADED')) {
  return;
}
define('MK_AUTHZ_LOADED', true);

function mk_db(): PDO {
  if (function_exists('db')) {
    /** @var PDO $pdo */
    $pdo = db();
    return $pdo;
  }
  throw new RuntimeException('DB accessor not found. Provide db() or replace mk_db().');
}

function mk_staff_user_id(bool $startSessionIfNeeded = true): ?int {
  if ($startSessionIfNeeded && session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    @session_start();
  }

  $id = $_SESSION['staff_user_id'] ?? null;
  if ($id === null) return null;

  $id = filter_var($id, FILTER_VALIDATE_INT);
  return ($id !== false && (int)$id > 0) ? (int)$id : null;
}

/**
 * Clear in-request capability cache (useful after login/logout or role changes).
 */
function mk_authz_clear_cache(): void {
  $GLOBALS['mk__authz_caps_cache'] = null;
  $GLOBALS['mk__authz_caps_cache_uid'] = null;
}

/**
 * Get cached capabilities for current user as a set: ['cap.name' => true, ...]
 */
function mk_current_caps(): array {
  $uid = mk_staff_user_id(true);
  if (!$uid) return [];

  $cachedUid = $GLOBALS['mk__authz_caps_cache_uid'] ?? null;
  $cachedSet = $GLOBALS['mk__authz_caps_cache'] ?? null;

  if (is_int($cachedUid) && $cachedUid === $uid && is_array($cachedSet)) {
    return $cachedSet;
  }

  $sql = "
    SELECT c.name
    FROM staff_user_roles sur
    JOIN role_capabilities rc ON rc.role_id = sur.role_id
    JOIN capabilities c ON c.id = rc.capability_id
    WHERE sur.staff_user_id = :uid
  ";

  $stmt = mk_db()->prepare($sql);
  $stmt->execute([':uid' => $uid]);

  $set = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $name = isset($row['name']) ? trim((string)$row['name']) : '';
    if ($name !== '') $set[$name] = true;
  }

  $GLOBALS['mk__authz_caps_cache_uid'] = $uid;
  $GLOBALS['mk__authz_caps_cache'] = $set;

  return $set;
}

function mk_user_can(string $capability): bool {
  $capability = trim($capability);
  if ($capability === '') return false;
  $caps = mk_current_caps();
  return isset($caps[$capability]);
}

function mk_user_can_any(array $capabilities): bool {
  foreach ($capabilities as $cap) {
    if (is_string($cap) && $cap !== '' && mk_user_can($cap)) return true;
  }
  return false;
}

function mk_require_cap(string $capability, ?string $message = null): void {
  if (mk_user_can($capability)) return;

  http_response_code(403);
  header('Content-Type: text/plain; charset=utf-8');

  $msg = $message ?? "Forbidden: missing capability '{$capability}'.";
  echo $msg;
  exit;
}

function mk_require_any(array $capabilities, ?string $message = null): void {
  if (mk_user_can_any($capabilities)) return;

  http_response_code(403);
  header('Content-Type: text/plain; charset=utf-8');
  echo $message ?? 'Forbidden: insufficient permissions.';
  exit;
}
