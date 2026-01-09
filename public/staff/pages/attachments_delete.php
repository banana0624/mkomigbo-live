<?php
declare(strict_types=1);

/**
 * /public/staff/pages/attachments_delete.php
 * Staff: delete an attachment (DB + file) safely.
 *
 * Unified on /public/staff/_init.php helpers:
 * - staff_csrf_verify()
 * - staff_safe_return_url()
 * - staff_redirect()
 * - staff_pdo()
 */

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

require_once __DIR__ . '/../_init.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'POST') {
  http_response_code(405);
  header('Allow: POST');
  exit;
}

$return = staff_safe_return_url((string)($_POST['return'] ?? ''), '/staff/subjects/pgs/index.php');

$go = static function (string $return, string $code): never {
  $target = $return . (strpos($return, '?') === false ? '?' : '&') . 'attach=' . rawurlencode($code);
  $target = str_replace(["\r", "\n"], '', $target);
  staff_redirect(function_exists('url_for') ? (string)url_for($target) : $target, 302);
};

/* Staff guard fallback (in case require_staff() wasn’t executed upstream) */
if (function_exists('staff_id') && staff_id() < 1) {
  $go($return, 'denied');
}

/* CSRF (accept csrf_token canonical + legacy csrf) */
$token = (string)($_POST['csrf_token'] ?? ($_POST['csrf'] ?? ''));
if (!staff_csrf_verify($token)) {
  $go($return, 'csrf');
}

/* DB */
$pdo = staff_pdo();
if (!$pdo instanceof PDO) {
  http_response_code(500);
  exit;
}

$id     = (int)($_POST['id'] ?? 0);
$pageId = (int)($_POST['page_id'] ?? 0);

if ($id < 1 || $pageId < 1) {
  $go($return, 'invalid');
}

/* Inspect page_files columns (schema tolerant) */
$have = [];
try {
  $stc = $pdo->query("SHOW COLUMNS FROM page_files");
  $rows = $stc ? ($stc->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
  foreach ($rows as $r) {
    $f = (string)($r['Field'] ?? '');
    if ($f !== '') $have[$f] = true;
  }
} catch (Throwable $e) {
  // If page_files itself is missing or inaccessible
  $go($return, 'missing');
}

if (!isset($have['id'], $have['page_id'])) {
  $go($return, 'missing');
}

/* Build SELECT dynamically */
$cols = ['id', 'page_id'];
foreach (['stored_name','filename','is_external','external_url','path','url'] as $c) {
  if (isset($have[$c])) $cols[] = $c;
}

$sql = "SELECT " . implode(', ', array_unique($cols)) . " FROM page_files WHERE id = :id LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

if (!$row || (int)($row['page_id'] ?? 0) !== $pageId) {
  $go($return, 'missing');
}

$isExternal = !empty($row['is_external'] ?? 0);

/* Delete DB row first */
try {
  $pdo->beginTransaction();
  $del = $pdo->prepare("DELETE FROM page_files WHERE id = :id LIMIT 1");
  $del->execute([':id' => $id]);
  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  $go($return, 'error');
}

/* If external, do not touch disk */
if ($isExternal) {
  $go($return, 'deleted');
}

/* Local file delete (best-effort, strict path guard) */
$missing = false;

/* Prefer stored_name, fall back to basename(path/url) if present */
$stored = '';
if (isset($row['stored_name']) && is_string($row['stored_name'])) {
  $stored = basename(trim($row['stored_name']));
}
if ($stored === '' && isset($row['path']) && is_string($row['path'])) {
  $stored = basename(trim($row['path']));
}
if ($stored === '' && isset($row['url']) && is_string($row['url'])) {
  $stored = basename(trim($row['url']));
}

if ($stored === '') {
  $missing = true;
} else {
  $base = rtrim((string)PRIVATE_PATH, '/\\') . '/uploads/pages/' . $pageId;
  $file = $base . '/' . $stored;

  $baseReal = realpath($base);
  $fileReal = realpath($file);

  if ($baseReal !== false && $fileReal !== false) {
    $baseReal = rtrim(str_replace('\\', '/', $baseReal), '/') . '/';
    $fileReal = str_replace('\\', '/', $fileReal);

    if (strpos($fileReal, $baseReal) === 0 && is_file($fileReal)) {
      if (!@unlink($fileReal)) $missing = true;
    } else {
      $missing = true;
    }
  } else {
    $missing = true;
  }
}

$go($return, $missing ? 'missing' : 'deleted');
