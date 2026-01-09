<?php
declare(strict_types=1);

/**
 * /public/staff/subjects/pgs/files/download.php
 * Staff-controlled attachment download endpoint (POST-only).
 *
 * POST:
 *   file_id, page_id, subject_id (optional)
 *   csrf_token (if enabled by your framework)
 *
 * Security:
 * - requires staff auth
 * - CSRF (if csrf_require() exists)
 * - verifies (file_id, page_id) ownership in page_files
 * - only serves files located under /public/lib/uploads/page_files/
 * - streams file with safe headers (no path traversal)
 */

$init = dirname(__DIR__, 5) . '/private/assets/initialize.php';
if (!is_file($init)) {
  header('Content-Type: text/plain; charset=utf-8');
  echo "Init not found\nExpected: {$init}\n";
  exit;
}
require_once $init;

/* ---------------------------------------------------------
   Fallback helpers (redirect + flash)
--------------------------------------------------------- */
if (!function_exists('redirect_to')) {
  function redirect_to(string $url): void {
    $url = str_replace(["\r", "\n"], '', $url);
    header('Location: ' . $url, true, 302);
    exit;
  }
}

if (!function_exists('pf__flash_set')) {
  function pf__flash_set(string $key, string $msg): void {
    if (function_exists('mk_flash_set')) {
      mk_flash_set($key, $msg);
      return;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $_SESSION[$key] = $msg;
  }
}

if (!function_exists('pf__fail')) {
  /**
   * Fail with flash+redirect when possible; otherwise plain-text status.
   */
  function pf__fail(int $code, string $message, string $back_url): void {
    // Prefer redirect UX (because this endpoint is typically triggered from edit.php)
    if (!headers_sent() && $back_url !== '') {
      pf__flash_set('flash_error', $message);
      redirect_to($back_url);
    }

    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message . "\n";
    exit;
  }
}

/* ---------------------------------------------------------
   Auth guard
--------------------------------------------------------- */
if (function_exists('require_staff')) {
  require_staff();
} elseif (function_exists('require_login')) {
  require_login();
}

/* ---------------------------------------------------------
   Require POST
--------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Method Not Allowed\n";
  exit;
}

/* ---------------------------------------------------------
   CSRF (if available)
--------------------------------------------------------- */
if (function_exists('csrf_require')) {
  csrf_require();
}

/* ---------------------------------------------------------
   Inputs + back link
--------------------------------------------------------- */
$file_id    = (int)($_POST['file_id'] ?? 0);
$page_id    = (int)($_POST['page_id'] ?? 0);
$subject_id = (int)($_POST['subject_id'] ?? 0);

$back_to_edit = ($page_id > 0)
  ? url_for('/staff/subjects/pgs/edit.php?id=' . $page_id . ($subject_id > 0 ? '&subject_id=' . $subject_id : ''))
  : url_for('/staff/subjects/');

if ($file_id <= 0 || $page_id <= 0) {
  pf__fail(400, 'Invalid download request.', $back_to_edit);
}

/* ---------------------------------------------------------
   DB
--------------------------------------------------------- */
$pdo = function_exists('db') ? db() : null;
if (!$pdo instanceof PDO) {
  pf__fail(500, 'DB unavailable.', $back_to_edit);
}

/* Ensure table accessible */
try {
  $pdo->query("SELECT 1 FROM `page_files` LIMIT 1");
} catch (Throwable $e) {
  pf__fail(404, 'Attachments are not enabled yet (page_files table not accessible).', $back_to_edit);
}

/* ---------------------------------------------------------
   Load attachment row (ownership enforced by page_id)
--------------------------------------------------------- */
$row = null;
try {
  $st = $pdo->prepare("
    SELECT id, page_id, original_name, stored_name, file_path, mime_type, file_size
    FROM page_files
    WHERE id = ? AND page_id = ?
    LIMIT 1
  ");
  $st->execute([$file_id, $page_id]);
  $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
  $row = null;
}

if (!$row) {
  pf__fail(404, 'Attachment not found.', $back_to_edit);
}

$original_name = (string)($row['original_name'] ?? 'attachment');
$file_path     = (string)($row['file_path'] ?? '');
$mime_type_db  = (string)($row['mime_type'] ?? '');
$file_size_db  = isset($row['file_size']) ? (int)$row['file_size'] : 0;

/* ---------------------------------------------------------
   Path restrictions
--------------------------------------------------------- */
$uploads_rel_dir = '/lib/uploads/page_files/';

/* Resolve filesystem path safely */
$project_root = dirname(__DIR__, 5); // .../public/staff/subjects/pgs/files -> project root
$public_root  = realpath($project_root . '/public');

if (!$public_root) {
  pf__fail(500, 'Could not resolve public root.', $back_to_edit);
}

if ($file_path === '' || $file_path[0] !== '/' || strpos($file_path, $uploads_rel_dir) !== 0) {
  pf__fail(403, 'Blocked download (invalid file location).', $back_to_edit);
}

/* Build candidate and ensure it stays inside /public */
$candidate = $public_root . str_replace('/', DIRECTORY_SEPARATOR, $file_path);
$real_file = is_file($candidate) ? realpath($candidate) : null;

if (!$real_file || strpos($real_file, $public_root . DIRECTORY_SEPARATOR) !== 0) {
  pf__fail(404, 'File missing on disk.', $back_to_edit);
}

if (!is_readable($real_file)) {
  pf__fail(403, 'File is not readable by PHP.', $back_to_edit);
}

/* ---------------------------------------------------------
   Determine MIME type (best effort)
--------------------------------------------------------- */
$mime = trim($mime_type_db);
if ($mime === '' || $mime === 'application/octet-stream') {
  if (function_exists('finfo_open')) {
    try {
      $fi = finfo_open(FILEINFO_MIME_TYPE);
      if ($fi) {
        $det = finfo_file($fi, $real_file);
        finfo_close($fi);
        if (is_string($det) && $det !== '') {
          $mime = $det;
        }
      }
    } catch (Throwable $e) {
      // ignore
    }
  }
}
if ($mime === '') {
  $mime = 'application/octet-stream';
}

/* ---------------------------------------------------------
   Safe filename (Content-Disposition)
--------------------------------------------------------- */
$dl_name = trim($original_name);
if ($dl_name === '') $dl_name = 'attachment';
$dl_name = str_replace(["\r", "\n"], '', $dl_name); // header injection guard

// ASCII fallback (for older clients)
$dl_ascii = preg_replace('/[^\x20-\x7E]/', '_', $dl_name);
$dl_ascii = $dl_ascii !== null && trim($dl_ascii) !== '' ? $dl_ascii : 'attachment';

/* ---------------------------------------------------------
   Send headers + stream
--------------------------------------------------------- */
$size_fs = @filesize($real_file);
$size = ($size_fs !== false) ? (int)$size_fs : ($file_size_db > 0 ? $file_size_db : null);

// Avoid keeping session locked during streaming
if (session_status() === PHP_SESSION_ACTIVE) {
  @session_write_close();
}

// Clean buffers (don’t fatal if none)
while (ob_get_level() > 0) {
  @ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');

// RFC 5987 for UTF-8 filenames + ASCII fallback
header('Content-Disposition: attachment; filename="' . addcslashes($dl_ascii, "\"\\") . '"; filename*=UTF-8\'\'' . rawurlencode($dl_name));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

if (is_int($size) && $size >= 0) {
  header('Content-Length: ' . (string)$size);
}

$fp = fopen($real_file, 'rb');
if ($fp === false) {
  pf__fail(500, 'Could not open file for reading.', $back_to_edit);
}

while (!feof($fp)) {
  $buf = fread($fp, 8192);
  if ($buf === false) break;
  echo $buf;
}
fclose($fp);
exit;
