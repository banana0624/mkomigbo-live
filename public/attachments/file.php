<?php
declare(strict_types=1);

/**
 * /public/attachments/file.php
 * Public: Stream a registered page attachment (read-only).
 *
 * Security:
 * - No direct file path input (id only)
 * - Prepared statement lookup in page_files
 * - Streams only via PRIVATE_PATH resolver (mk_page_attachment_stream)
 */

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

require_once __DIR__ . '/../_init.php';

if (!defined('PRIVATE_PATH') || !is_string(PRIVATE_PATH) || PRIVATE_PATH === '') {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  exit("Server misconfiguration (PRIVATE_PATH).\n");
}

$attachmentsFn = PRIVATE_PATH . '/functions/page_attachments.php';
if (!is_file($attachmentsFn)) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  exit("Attachment handler missing.\n");
}
require_once $attachmentsFn;

/* Allow only GET/HEAD */
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'GET' && $method !== 'HEAD') {
  http_response_code(405);
  header('Allow: GET, HEAD');
  exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id < 1) {
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  exit("Bad request.\n");
}

if (!function_exists('db')) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  exit("DB helper unavailable.\n");
}

$pdo = db();
if (!$pdo instanceof PDO) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  exit("DB unavailable.\n");
}

/* Optional: public caching for file endpoint (safe defaults) */
if (function_exists('mk_public_cache_headers')) {
  mk_public_cache_headers(600);
} else {
  header('Cache-Control: public, max-age=600');
}

/* Schema-tolerant column set (supports slightly older page_files tables) */
$cols = [
  'id', 'page_id', 'filename', 'stored_name', 'mime', 'is_external', 'external_url'
];

try {
  $stmt = $pdo->prepare("
    SELECT " . implode(', ', $cols) . "
    FROM page_files
    WHERE id = :id
    LIMIT 1
  ");
  $stmt->execute([':id' => $id]);
  $file = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  exit("Attachment lookup failed.\n");
}

if (!$file) {
  http_response_code(404);
  header('Content-Type: text/plain; charset=utf-8');
  exit("Not found.\n");
}

/* Normalize missing keys to prevent notices */
$file += [
  'id' => $id,
  'page_id' => 0,
  'filename' => '',
  'stored_name' => '',
  'mime' => '',
  'is_external' => 0,
  'external_url' => null,
];

/* Stream through the private resolver */
mk_page_attachment_stream($file);
