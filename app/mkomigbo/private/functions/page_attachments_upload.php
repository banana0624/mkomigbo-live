<?php
declare(strict_types=1);

/**
 * /private/functions/page_attachments_upload.php
 * Staff upload pipeline:
 * 1) Receive upload -> quarantine/{staff_id}/
 * 2) Validate: size, mime sniff, extension policy
 * 3) Move -> /uploads/pages/{page_id}/
 * 4) Insert page_files row
 */

if (!defined('PRIVATE_PATH')) {
  throw new RuntimeException('PRIVATE_PATH not defined.');
}

/* ---------------------------
   Policy
--------------------------- */

function mk_upload_max_bytes(): int {
  // 25MB default (tune as you like)
  return 25 * 1024 * 1024;
}

function mk_upload_allowed_mimes(): array {
  // Keep consistent with page_attachments.php allowlist
  return [
    'application/pdf',
    'image/png',
    'image/jpeg',
    'image/webp',
    'image/gif',
    'text/plain',
    'audio/mpeg',
    'audio/mp3',
    'audio/wav',
    'audio/x-wav',
    'audio/ogg',
    'video/mp4',
    'video/webm',
    'application/zip',
    'application/x-zip-compressed',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
  ];
}

function mk_upload_allowed_exts(): array {
  return [
    'pdf','png','jpg','jpeg','webp','gif','txt',
    'mp3','wav','ogg',
    'mp4','webm',
    'zip',
    'doc','docx','xls','xlsx','ppt','pptx',
  ];
}

/* ---------------------------
   Helpers
--------------------------- */

function mk_upload_fail(string $message): array {
  return ['ok' => false, 'error' => $message];
}

function mk_upload_sniff_mime(string $path): string {
  $mime = '';
  if (function_exists('finfo_open')) {
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    if ($fi) {
      $m = finfo_file($fi, $path);
      finfo_close($fi);
      if (is_string($m)) $mime = trim($m);
    }
  }
  return $mime !== '' ? $mime : 'application/octet-stream';
}

function mk_upload_safe_original_name(string $name): string {
  $name = trim((string)$name);
  $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? $name;
  $name = str_replace(['"', "'"], '', $name);
  $name = trim($name);
  if ($name === '') $name = 'upload';
  return $name;
}

function mk_upload_ext(string $name): string {
  $name = strtolower((string)$name);
  $pos = strrpos($name, '.');
  if ($pos === false) return '';
  return trim(substr($name, $pos + 1));
}

function mk_upload_mkdir(string $path): bool {
  if (is_dir($path)) return true;
  return @mkdir($path, 0755, true);
}

function mk_upload_random_stored_name(string $ext): string {
  $ext = strtolower(trim($ext));
  $token = bin2hex(random_bytes(16)); // 32 chars
  return $ext !== '' ? ($token . '.' . $ext) : $token;
}

function mk_upload_quarantine_dir(int $staffId): string {
  return rtrim(PRIVATE_PATH, '/\\') . '/uploads/quarantine/pages/' . max(1, $staffId);
}

function mk_upload_final_dir(int $pageId): string {
  return rtrim(PRIVATE_PATH, '/\\') . '/uploads/pages/' . max(1, $pageId);
}

/**
 * Insert into page_files safely. Skips optional columns if missing.
 */
function mk_upload_insert_page_file(PDO $pdo, array $row): int {
  // Detect columns once per request
  static $cols = null;
  if ($cols === null) {
    $cols = [];
    $st = $pdo->query("SHOW COLUMNS FROM page_files");
    $r = $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    foreach ($r as $c) {
      $f = (string)($c['Field'] ?? '');
      if ($f !== '') $cols[$f] = true;
    }
  }

  $fields = [];
  $params = [];

  $map = [
    'page_id'      => 'page_id',
    'filename'     => 'filename',
    'stored_name'  => 'stored_name',
    'mime'         => 'mime',
    'filesize'     => 'filesize',
    'is_external'  => 'is_external',
    'external_url' => 'external_url',
    'created_by_staff_id' => 'created_by_staff_id',
  ];

  foreach ($map as $col => $key) {
    if (!isset($cols[$col])) continue;
    $fields[] = $col;
    $params[":$col"] = $row[$key] ?? null;
  }

  // Hard requirements (must exist)
  foreach (['page_id','filename','stored_name'] as $req) {
    if (!in_array($req, $fields, true)) {
      throw new RuntimeException("page_files missing required column: {$req}");
    }
  }

  $sql = "INSERT INTO page_files (" . implode(', ', $fields) . ")
          VALUES (" . implode(', ', array_map(fn($f) => ':' . $f, $fields)) . ")";
  $st = $pdo->prepare($sql);
  $st->execute($params);

  return (int)$pdo->lastInsertId();
}

/* ---------------------------
   Main entry point
--------------------------- */

/**
 * Process upload(s) from an <input type="file" name="attachments[]" multiple>
 *
 * Returns:
 * - ok true/false
 * - added: list of inserted ids + filenames
 * - errors: list of per-file errors
 */
function mk_staff_upload_page_attachments(PDO $pdo, int $pageId, int $staffId, array $files): array {
  $pageId = max(1, (int)$pageId);
  $staffId = max(1, (int)$staffId);

  if ($pageId < 1) return mk_upload_fail('Invalid page id.');
  if ($staffId < 1) return mk_upload_fail('Invalid staff id.');

  if (!isset($files['name'])) return mk_upload_fail('No files received.');

  $max = mk_upload_max_bytes();
  $allowMimes = mk_upload_allowed_mimes();
  $allowExts  = mk_upload_allowed_exts();

  $qdir = mk_upload_quarantine_dir($staffId);
  $fdir = mk_upload_final_dir($pageId);

  if (!mk_upload_mkdir($qdir)) return mk_upload_fail('Cannot create quarantine directory.');
  if (!mk_upload_mkdir($fdir)) return mk_upload_fail('Cannot create final directory.');

  $added = [];
  $errors = [];

  $names = (array)$files['name'];
  $tmps  = (array)$files['tmp_name'];
  $errs  = (array)$files['error'];
  $sizes = (array)$files['size'];

  $count = count($names);
  for ($i = 0; $i < $count; $i++) {
    $origName = mk_upload_safe_original_name((string)($names[$i] ?? ''));
    $tmpPath  = (string)($tmps[$i] ?? '');
    $errCode  = (int)($errs[$i] ?? UPLOAD_ERR_NO_FILE);
    $size     = (int)($sizes[$i] ?? 0);

    if ($errCode === UPLOAD_ERR_NO_FILE) continue;

    if ($errCode !== UPLOAD_ERR_OK) {
      $errors[] = "{$origName}: upload error ({$errCode})";
      continue;
    }

    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
      $errors[] = "{$origName}: invalid upload";
      continue;
    }

    if ($size < 1 || $size > $max) {
      $errors[] = "{$origName}: file too large (max " . number_format($max/1024/1024, 0) . "MB)";
      continue;
    }

    $ext = mk_upload_ext($origName);
    if ($ext === '' || !in_array($ext, $allowExts, true)) {
      $errors[] = "{$origName}: extension not allowed";
      continue;
    }

    $stored = mk_upload_random_stored_name($ext);
    $qPath = rtrim($qdir, '/\\') . '/' . $stored;

    // Step 1: move into quarantine
    if (!@move_uploaded_file($tmpPath, $qPath)) {
      $errors[] = "{$origName}: failed to move to quarantine";
      continue;
    }

    // Step 2: sniff MIME in quarantine
    $mime = mk_upload_sniff_mime($qPath);
    if (!in_array($mime, $allowMimes, true)) {
      @unlink($qPath);
      $errors[] = "{$origName}: file type not allowed ({$mime})";
      continue;
    }

    // Step 3: move from quarantine to final
    $finalPath = rtrim($fdir, '/\\') . '/' . $stored;
    if (!@rename($qPath, $finalPath)) {
      // fallback copy+unlink
      if (!@copy($qPath, $finalPath)) {
        @unlink($qPath);
        $errors[] = "{$origName}: failed to move to final directory";
        continue;
      }
      @unlink($qPath);
    }

    @chmod($finalPath, 0644);

    // Step 4: insert DB row
    try {
      $newId = mk_upload_insert_page_file($pdo, [
        'page_id' => $pageId,
        'filename' => $origName,
        'stored_name' => $stored,
        'mime' => $mime,
        'filesize' => $size,
        'is_external' => 0,
        'external_url' => null,
        'created_by_staff_id' => $staffId,
      ]);

      $added[] = ['id' => $newId, 'filename' => $origName];
    } catch (Throwable $e) {
      // Roll back file if DB insert fails
      @unlink($finalPath);
      $errors[] = "{$origName}: DB insert failed";
      continue;
    }
  }

  if (!$added && !$errors) {
    return mk_upload_fail('No files selected.');
  }

  return [
    'ok' => true,
    'added' => $added,
    'errors' => $errors,
  ];
}
