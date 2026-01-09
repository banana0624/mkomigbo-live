<?php
declare(strict_types=1);

/**
 * /public/staff/subjects/pgs/files/upload.php
 * Staff: Upload an attachment for a page.
 *
 * POST multipart/form-data:
 *   page_id, subject_id (optional), sort_order (optional), file
 *   csrf_token (if enabled)
 *
 * Stores uploads under:
 *   /public/lib/uploads/page_files/
 * And writes a DB row in page_files table.
 */

$init = dirname(__DIR__, 5) . '/private/assets/initialize.php';
if (!is_file($init)) {
  header('Content-Type: text/plain; charset=utf-8');
  echo "Init not found\nExpected: {$init}\n";
  exit;
}
require_once $init;

/* -----------------------------
   Safety fallbacks
----------------------------- */
if (!function_exists('h')) {
  function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
  }
}
if (!function_exists('redirect_to')) {
  function redirect_to(string $url): void {
    $url = str_replace(["\r", "\n"], '', $url);
    header('Location: ' . $url, true, 302);
    exit;
  }
}

/* Flash helpers */
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

/* Auth guard */
if (function_exists('require_staff')) {
  require_staff();
} elseif (function_exists('require_login')) {
  require_login();
}

/* -----------------------------
   Helpers: redirect back
----------------------------- */
function pf__redirect_back(int $page_id, int $subject_id): void {
  if ($page_id > 0) {
    $q = 'id=' . $page_id;
    if ($subject_id > 0) $q .= '&subject_id=' . $subject_id;
    redirect_to(url_for('/staff/subjects/pgs/edit.php?' . $q));
  }
  if ($subject_id > 0) {
    redirect_to(url_for('/staff/subjects/pgs/?subject_id=' . $subject_id));
  }
  redirect_to(url_for('/staff/subjects/'));
}

/* -----------------------------
   Helpers: schema hard-checks (no information_schema)
----------------------------- */
if (!function_exists('pf__table_exists_hard')) {
  function pf__table_exists_hard(PDO $pdo, string $table): bool {
    try {
      $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
      return true;
    } catch (Throwable $e) {
      return false;
    }
  }
}

if (!function_exists('pf__column_exists_hard')) {
  function pf__column_exists_hard(PDO $pdo, string $table, string $column): bool {
    try {
      $pdo->query("SELECT `{$column}` FROM `{$table}` LIMIT 1");
      return true;
    } catch (Throwable $e) {
      return false;
    }
  }
}

/* -----------------------------
   CSRF normalization helper
   (fixes "Bad Request (CSRF)" when field name differs)
----------------------------- */
if (!function_exists('pf__csrf_normalize_post')) {
  function pf__csrf_normalize_post(): void {
    if (!is_array($_POST)) return;

    // If already present, keep it
    $cur = $_POST['csrf_token'] ?? null;
    if (is_string($cur) && $cur !== '') return;

    // Common alternate field names used by different CSRF helpers
    $candidates = [
      '_csrf_token',
      'csrf',
      '_csrf',
      'token',
      '_token',
      'csrfToken',
      'csrf-token',
      'csrf_token',
    ];

    foreach ($candidates as $k) {
      $v = $_POST[$k] ?? null;
      if (is_string($v) && $v !== '') {
        $_POST['csrf_token'] = $v; // normalize to the most common name
        return;
      }
    }

    // As a last resort, if your CSRF system stores it in session, copy it (optional)
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $sv = $_SESSION['csrf_token'] ?? $_SESSION['_csrf_token'] ?? null;
    if (is_string($sv) && $sv !== '') {
      $_POST['csrf_token'] = $sv;
    }
  }
}

/* ---------------------------------------------------------
   Require POST (avoid direct visiting)
--------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  pf__flash_set('flash_error', 'Invalid request method.');
  redirect_to(url_for('/staff/subjects/'));
}

/* ---------------------------------------------------------
   Inputs (allow GET fallback for redirect safety)
   If uploads exceed limits, PHP may empty $_POST.
--------------------------------------------------------- */
$page_id    = (int)(($_POST['page_id'] ?? 0) ?: ($_GET['page_id'] ?? 0));
$subject_id = (int)(($_POST['subject_id'] ?? 0) ?: ($_GET['subject_id'] ?? 0));

/* ---------------------------------------------------------
   Detect oversize BEFORE CSRF
--------------------------------------------------------- */
$f   = $_FILES['file'] ?? null;
$err = is_array($f) ? (int)($f['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;

if (empty($_POST) && is_array($f) && ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE)) {
  pf__flash_set('flash_error', 'Upload too large (server limit). Please upload a smaller file.');
  pf__redirect_back($page_id, $subject_id);
}

/* ---------------------------------------------------------
   CSRF (normalize field names, then require)
--------------------------------------------------------- */
if (function_exists('csrf_require')) {
  pf__csrf_normalize_post();

  $tok = $_POST['csrf_token'] ?? '';
  if (!is_string($tok) || $tok === '') {
    pf__flash_set('flash_error', 'Security token missing/expired. Please reload the edit page and try again.');
    pf__redirect_back($page_id, $subject_id);
  }

  // Now call the framework verifier
  csrf_require();
}

/* ---------------------------------------------------------
   DB
--------------------------------------------------------- */
$pdo = function_exists('db') ? db() : null;
if (!$pdo instanceof PDO) {
  pf__flash_set('flash_error', 'DB unavailable.');
  redirect_to(url_for('/staff/subjects/'));
}

/* More inputs */
$sort_order_raw = trim((string)($_POST['sort_order'] ?? ''));

if ($page_id <= 0) {
  pf__flash_set('flash_error', 'Missing or invalid page_id.');
  pf__redirect_back(0, $subject_id);
}

/* Attachments enabled? */
if (!pf__table_exists_hard($pdo, 'page_files')) {
  pf__flash_set('flash_error', 'Attachments are not enabled yet (page_files table not accessible).');
  pf__redirect_back($page_id, $subject_id);
}

/* Parse sort_order */
$sort_order = null;
if ($sort_order_raw !== '') {
  if (!ctype_digit($sort_order_raw)) {
    pf__flash_set('flash_error', 'Sort order must be a whole number.');
    pf__redirect_back($page_id, $subject_id);
  }
  $sort_order = (int)$sort_order_raw;
}

/* Ensure page exists + load subject_id if missing */
try {
  $stP = $pdo->prepare("SELECT subject_id FROM pages WHERE id = ? LIMIT 1");
  $stP->execute([$page_id]);
  $sid = (int)($stP->fetchColumn() ?: 0);
  if ($sid <= 0) {
    pf__flash_set('flash_error', 'Page not found.');
    pf__redirect_back(0, $subject_id);
  }
  if ($subject_id <= 0) $subject_id = $sid;
} catch (Throwable $e) {
  pf__flash_set('flash_error', 'Could not verify page: ' . $e->getMessage());
  pf__redirect_back($page_id, $subject_id);
}

/* ---------------------------------------------------------
   Validate file upload
--------------------------------------------------------- */
if (!$f || !is_array($f)) {
  pf__flash_set('flash_error', 'No file uploaded.');
  pf__redirect_back($page_id, $subject_id);
}

if ($err !== UPLOAD_ERR_OK) {
  $msg = 'Upload failed (code ' . $err . ').';
  if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
    $msg = 'Upload too large (server limit).';
  } elseif ($err === UPLOAD_ERR_NO_FILE) {
    $msg = 'No file selected.';
  }
  pf__flash_set('flash_error', $msg);
  pf__redirect_back($page_id, $subject_id);
}

$original_name = trim((string)($f['name'] ?? ''));
$tmp_name      = (string)($f['tmp_name'] ?? '');
$file_size     = (int)($f['size'] ?? 0);

if ($original_name === '') $original_name = 'attachment';
if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
  pf__flash_set('flash_error', 'Upload was not received correctly.');
  pf__redirect_back($page_id, $subject_id);
}

/* Max bytes: env override if you later add one, else 10MB */
$max_bytes = 10 * 1024 * 1024;
if (defined('ATTACHMENT_MAX_BYTES')) {
  $mb = (int)ATTACHMENT_MAX_BYTES;
  if ($mb > 0) $max_bytes = $mb;
}

if ($file_size <= 0) {
  pf__flash_set('flash_error', 'Empty file not allowed.');
  pf__redirect_back($page_id, $subject_id);
}
if ($file_size > $max_bytes) {
  pf__flash_set('flash_error', 'File too large. Max is ' . (string)round($max_bytes / 1024 / 1024, 2) . 'MB.');
  pf__redirect_back($page_id, $subject_id);
}

/* Allowed extensions */
$allowed_ext = [
  'pdf','txt','md',
  'doc','docx','xls','xlsx','ppt','pptx',
  'jpg','jpeg','png','webp','gif','avif','svg',
  'zip'
];

$ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
$ext = (string)preg_replace('/[^a-z0-9]+/', '', $ext);

if ($ext === '' || !in_array($ext, $allowed_ext, true)) {
  pf__flash_set('flash_error', 'File type not allowed. Allowed: ' . implode(', ', $allowed_ext));
  pf__redirect_back($page_id, $subject_id);
}

/* MIME detection (best effort) */
$mime_type = null;
if (function_exists('finfo_open')) {
  try {
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    if ($fi) {
      $mime_type = finfo_file($fi, $tmp_name) ?: null;
      finfo_close($fi);
    }
  } catch (Throwable $e) {
    $mime_type = null;
  }
}
if ($mime_type === null || $mime_type === '') {
  $mime_type = 'application/octet-stream';
}

/* ---------------------------------------------------------
   Destination paths (FS + web)
--------------------------------------------------------- */
$upload_dir_web = '/lib/uploads/page_files';

/* Resolve /public filesystem root */
$public_root_fs = null;
if (defined('PUBLIC_PATH')) {
  $public_root_fs = realpath((string)PUBLIC_PATH);
}
if (!$public_root_fs) {
  // __DIR__ = .../public/staff/subjects/pgs/files
  $public_root_fs = realpath(dirname(__DIR__, 4)); // -> .../public
}

if (!$public_root_fs) {
  pf__flash_set('flash_error', 'Could not resolve PUBLIC path.');
  pf__redirect_back($page_id, $subject_id);
}

$upload_dir_fs = rtrim($public_root_fs, '/\\') . str_replace('/', DIRECTORY_SEPARATOR, $upload_dir_web);

if (!is_dir($upload_dir_fs)) {
  @mkdir($upload_dir_fs, 0775, true);
}
if (!is_dir($upload_dir_fs) || !is_writable($upload_dir_fs)) {
  pf__flash_set('flash_error', 'Upload folder not writable. Please make writable: public/lib/uploads/page_files/');
  pf__redirect_back($page_id, $subject_id);
}

/* Build stored filename */
$base = pathinfo($original_name, PATHINFO_FILENAME);
$base = trim((string)$base);
if ($base === '') $base = 'attachment';
$base = mb_strtolower($base);
$base = (string)preg_replace('~[^\pL\pN]+~u', '-', $base);
$base = trim($base, '-');
if ($base === '') $base = 'attachment';

$stored_name = 'p' . $page_id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '_' . $base . '.' . $ext;

$dest_fs  = $upload_dir_fs . DIRECTORY_SEPARATOR . $stored_name;
$dest_web = $upload_dir_web . '/' . $stored_name;

/* Move file */
if (!move_uploaded_file($tmp_name, $dest_fs)) {
  pf__flash_set('flash_error', 'Upload failed. Could not move uploaded file.');
  pf__redirect_back($page_id, $subject_id);
}

/* ---------------------------------------------------------
   Insert DB record (schema tolerant)
--------------------------------------------------------- */
try {
  // Detect which columns exist (hard checks)
  $has_stored_name  = pf__column_exists_hard($pdo, 'page_files', 'stored_name');
  $has_file_path    = pf__column_exists_hard($pdo, 'page_files', 'file_path');
  $has_mime_type     = pf__column_exists_hard($pdo, 'page_files', 'mime_type');
  $has_file_size     = pf__column_exists_hard($pdo, 'page_files', 'file_size');
  $has_sort_order    = pf__column_exists_hard($pdo, 'page_files', 'sort_order');
  $has_created_at    = pf__column_exists_hard($pdo, 'page_files', 'created_at');
  $has_original_name = pf__column_exists_hard($pdo, 'page_files', 'original_name');

  // Build INSERT list dynamically
  $cols = ['page_id'];
  $vals = [$page_id];

  if ($has_original_name) { $cols[] = 'original_name'; $vals[] = $original_name; }
  if ($has_stored_name)   { $cols[] = 'stored_name';   $vals[] = $stored_name; }
  if ($has_file_path)     { $cols[] = 'file_path';     $vals[] = $dest_web; }

  if ($has_mime_type)     { $cols[] = 'mime_type';     $vals[] = $mime_type; }
  if ($has_file_size)     { $cols[] = 'file_size';     $vals[] = $file_size; }

  if ($has_sort_order)    { $cols[] = 'sort_order';    $vals[] = $sort_order; }

  // created_at: use NOW() only if column exists; otherwise skip
  $placeholders = array_fill(0, count($cols), '?');

  $created_at_sql = '';
  if ($has_created_at) {
    $cols[] = 'created_at';
    $created_at_sql = ', NOW()';
  }

  $sqlI = "INSERT INTO page_files (" . implode(', ', $cols) . ")
           VALUES (" . implode(', ', $placeholders) . $created_at_sql . ")";

  $stI = $pdo->prepare($sqlI);
  $stI->execute($vals);

  pf__flash_set('flash_success', 'Attachment uploaded: ' . $original_name);
} catch (Throwable $e) {
  @unlink($dest_fs);
  pf__flash_set('flash_error', 'DB insert failed: ' . $e->getMessage());
}

pf__redirect_back($page_id, $subject_id);
