<?php
declare(strict_types=1);

/**
 * /public/media/get.php?id=123
 * Securely streams local media assets (outside webroot).
 * Remote assets are NOT blindly proxied here (SSRF risk).
 */

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

/* Locate initialize.php */
if (!function_exists('mk_find_init')) {
  function mk_find_init(string $startDir, int $maxDepth = 14): ?string {
    $dir = $startDir;
    for ($i = 0; $i <= $maxDepth; $i++) {
      $candidates = [
        $dir . '/app/mkomigbo/private/assets/initialize.php',
        $dir . '/private/assets/initialize.php',
      ];
      foreach ($candidates as $c) if (is_file($c)) return $c;
      $parent = dirname($dir);
      if ($parent === $dir) break;
      $dir = $parent;
    }
    return null;
  }
}
$init = mk_find_init(__DIR__);
if (!$init) { http_response_code(500); echo "Initialize not found."; exit; }
require_once $init;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit; }

$pdo = db();
$st = $pdo->prepare("SELECT id, kind, title, local_relpath, mime, bytes, is_public
                     FROM media_assets
                     WHERE id = :id
                     LIMIT 1");
$st->execute([':id' => $id]);
$m = $st->fetch(PDO::FETCH_ASSOC);

if (!$m || (int)$m['is_public'] !== 1) { http_response_code(404); exit; }
if (($m['kind'] ?? '') !== 'local') { http_response_code(400); echo "Not a local asset."; exit; }

$rel = (string)($m['local_relpath'] ?? '');
$rel = ltrim($rel, "/\\");
if ($rel === '' || str_contains($rel, '..') || str_contains($rel, "\0")) { http_response_code(400); exit; }

/**
 * Set your real storage root (outside webroot).
 * Update this path to match your server.
 */
$MEDIA_ROOT = '/home/mkomigbo/uploads/mkomigbo_media';

$full = $MEDIA_ROOT . '/' . $rel;

/* Resolve realpath and ensure it stays inside MEDIA_ROOT */
$realRoot = realpath($MEDIA_ROOT);
$realFile = realpath($full);

if (!$realRoot || !$realFile || !is_file($realFile)) { http_response_code(404); exit; }
if (strpos($realFile, $realRoot . DIRECTORY_SEPARATOR) !== 0 && $realFile !== $realRoot) {
  http_response_code(403); exit;
}

$size = filesize($realFile);
if ($size === false) { http_response_code(404); exit; }

/* Hard limits */
$MAX_BYTES = 250 * 1024 * 1024; // 250MB
if ($size > $MAX_BYTES) { http_response_code(413); echo "File too large."; exit; }

/* MIME */
$mime = (string)($m['mime'] ?? '');
if ($mime === '' && function_exists('finfo_open')) {
  $fi = finfo_open(FILEINFO_MIME_TYPE);
  if ($fi) {
    $det = finfo_file($fi, $realFile);
    finfo_close($fi);
    if (is_string($det) && $det !== '') $mime = $det;
  }
}
if ($mime === '') $mime = 'application/octet-stream';

$title = (string)($m['title'] ?? basename($realFile));
$fname = preg_replace('/[^A-Za-z0-9\.\-\_\s]+/', '', $title) ?: basename($realFile);

/* Inline for common viewable types */
$inline = false;
if (preg_match('#^(image/|audio/|video/)#', $mime)) $inline = true;
if ($mime === 'application/pdf') $inline = true;

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)$size);
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $fname . '"');

readfile($realFile);
exit;
