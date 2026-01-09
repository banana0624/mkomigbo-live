<?php
declare(strict_types=1);

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

/**
 * /public/subjects/submit_comment.php
 * Comment submission endpoint
 *
 * IMPORTANT:
 * - Never hardcode initialize.php paths here
 * - Always go through /public/_init.php
 */
require_once __DIR__ . '/../_init.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  @session_start();
}

/* -----------------------------
   Utilities
----------------------------- */
$redirect_safe = static function (string $to, string $fallback): void {
  $to = trim($to);
  if ($to === '' || $to[0] !== '/' || preg_match('~^\s*/{2,}~', $to) || preg_match('~^[a-z]+://~i', $to)) {
    $to = $fallback;
  }
  header('Location: ' . $to, true, 303);
  exit;
};

$canon_notice_url = static function (string $returnUrl, string $notice): string {
  $p = parse_url($returnUrl);
  $path = (string)($p['path'] ?? '/subjects/');
  $q = [];
  if (!empty($p['query'])) {
    parse_str((string)$p['query'], $q);
  }
  $q['notice'] = $notice;
  $qs = http_build_query($q);
  return $path . ($qs ? ('?' . $qs) : '') . (isset($p['fragment']) ? ('#' . $p['fragment']) : '#comments');
};

$ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
$ua = substr($ua, 0, 500);

/* -----------------------------
   Method
----------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Method Not Allowed";
  exit;
}

/* -----------------------------
   Return URL (must exist)
----------------------------- */
$return = isset($_POST['return']) ? (string)$_POST['return'] : '';
$return = trim($return);
$fallback_return = function_exists('url_for') ? (string)url_for('/subjects/') : '/subjects/';
$return_path = $return !== '' ? $return : $fallback_return;
if ($return_path !== '' && $return_path[0] !== '/') {
  $pp = parse_url($return_path, PHP_URL_PATH);
  $return_path = is_string($pp) && $pp !== '' ? $pp : $fallback_return;
}

/* -----------------------------
   Honeypot (spam)
----------------------------- */
$honeypot = isset($_POST['website']) ? (string)$_POST['website'] : '';
if (trim($honeypot) !== '') {
  $redirect_safe($canon_notice_url($return_path, 'sent'), $fallback_return);
}

/* -----------------------------
   CSRF
----------------------------- */
$csrf = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
$csrf_ok = false;

if (function_exists('csrf_verify')) {
  $csrf_ok = (bool)csrf_verify($csrf);
} else {
  $sess = $_SESSION['csrf_token'] ?? '';
  $csrf_ok = (is_string($sess) && $sess !== '' && hash_equals($sess, $csrf));
}

if (!$csrf_ok) {
  $redirect_safe($canon_notice_url($return_path, 'invalid'), $fallback_return);
}

/* -----------------------------
   Rate limit (fallback if helper not present)
----------------------------- */
$rate_ok = true;
if (function_exists('rate_limit')) {
  $rate_ok = (bool)rate_limit('comment:' . $ip, 20, 60);
} else {
  $key = 'rl_comment';
  $now = time();
  $window = 300;
  $max = 10;

  if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) $_SESSION[$key] = [];
  $_SESSION[$key] = array_values(array_filter($_SESSION[$key], static fn($t) => is_int($t) && ($now - $t) < $window));
  if (count($_SESSION[$key]) >= $max) $rate_ok = false;
  $_SESSION[$key][] = $now;
}

if (!$rate_ok) {
  $redirect_safe($canon_notice_url($return_path, 'rate'), $fallback_return);
}

/* -----------------------------
   Validate payload
----------------------------- */
$subject_id = isset($_POST['subject_id']) ? (string)$_POST['subject_id'] : '';
$page_id    = isset($_POST['page_id']) ? (string)$_POST['page_id'] : '';

$subject_id = trim($subject_id);
$page_id    = trim($page_id);

if (!ctype_digit($subject_id) || !ctype_digit($page_id)) {
  $redirect_safe($canon_notice_url($return_path, 'invalid'), $fallback_return);
}

$sid = (int)$subject_id;
$pid = (int)$page_id;
if ($sid <= 0 || $pid <= 0) {
  $redirect_safe($canon_notice_url($return_path, 'invalid'), $fallback_return);
}

$name = isset($_POST['name']) ? (string)$_POST['name'] : '';
$name = trim($name);
if ($name !== '') {
  $name = preg_replace('~\s+~', ' ', $name) ?? $name;
  $name = function_exists('mb_substr') ? mb_substr($name, 0, 80, 'UTF-8') : substr($name, 0, 80);
}

$body = isset($_POST['body']) ? (string)$_POST['body'] : '';
$body = trim($body);
$body = preg_replace("~\r\n?~", "\n", $body) ?? $body;

$len = function_exists('mb_strlen') ? mb_strlen($body, 'UTF-8') : strlen($body);
if ($body === '' || $len < 2 || $len > 2000) {
  $redirect_safe($canon_notice_url($return_path, 'invalid'), $fallback_return);
}

/* -----------------------------
   DB insert (schema tolerant)
----------------------------- */
try {
  if (!function_exists('db')) throw new RuntimeException('db() helper not available.');
  $pdo = db();
  if (!$pdo instanceof PDO) throw new RuntimeException('DB connection not available.');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
  if ($dbName === '') throw new RuntimeException('No database selected.');

  $stT = $pdo->prepare("
    SELECT 1
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'comments'
    LIMIT 1
  ");
  $stT->execute([':db' => $dbName]);
  if (!(bool)$stT->fetchColumn()) {
    $redirect_safe($canon_notice_url($return_path, 'pending'), $fallback_return);
  }

  $have = [];
  $stC = $pdo->query("SHOW COLUMNS FROM comments");
  $cols = $stC ? ($stC->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
  foreach ($cols as $r) {
    $f = (string)($r['Field'] ?? '');
    if ($f !== '') $have[$f] = true;
  }

  $fields = [];
  $params = [];

  if (isset($have['subject_id'])) { $fields[] = 'subject_id'; $params[':subject_id'] = $sid; }
  if (isset($have['page_id']))    { $fields[] = 'page_id';    $params[':page_id']    = $pid; }

  if (isset($have['body']))       { $fields[] = 'body';       $params[':body']       = $body; }
  elseif (isset($have['content'])){ $fields[] = 'content';    $params[':body']       = $body; }
  else {
    $redirect_safe($canon_notice_url($return_path, 'pending'), $fallback_return);
  }

  if ($name !== '') {
    if (isset($have['author_name'])) { $fields[] = 'author_name'; $params[':author_name'] = $name; }
    elseif (isset($have['name']))    { $fields[] = 'name';        $params[':author_name'] = $name; }
  }

  if (isset($have['status']))       { $fields[] = 'status';    $params[':status']    = 'pending'; }
  elseif (isset($have['is_public'])){ $fields[] = 'is_public'; $params[':is_public'] = 0; }

  if (isset($have['ip_address'])) { $fields[] = 'ip_address'; $params[':ip'] = $ip; }
  elseif (isset($have['ip']))     { $fields[] = 'ip';         $params[':ip'] = $ip; }

  if (isset($have['user_agent'])) { $fields[] = 'user_agent'; $params[':ua'] = $ua; }
  elseif (isset($have['ua']))     { $fields[] = 'ua';         $params[':ua'] = $ua; }

  if (isset($have['author_id']) && isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $fields[] = 'author_id';
    $params[':author_id'] = (int)$_SESSION['user_id'];
  }

  $colList = implode(', ', array_map(static fn($f) => "`{$f}`", $fields));

  $phList  = implode(', ', array_map(static function ($f) {
    return match ($f) {
      'subject_id'  => ':subject_id',
      'page_id'     => ':page_id',
      'body'        => ':body',
      'content'     => ':body',
      'author_name' => ':author_name',
      'name'        => ':author_name',
      'status'      => ':status',
      'is_public'   => ':is_public',
      'ip_address'  => ':ip',
      'ip'          => ':ip',
      'user_agent'  => ':ua',
      'ua'          => ':ua',
      'author_id'   => ':author_id',
      default       => ':' . $f,
    };
  }, $fields));

  $sql = "INSERT INTO comments ({$colList}) VALUES ({$phList})";
  $stI = $pdo->prepare($sql);
  $stI->execute($params);

  $comment_id = (int)$pdo->lastInsertId();

  /* -----------------------------
     Attachments -> PRIVATE quarantine
  ----------------------------- */
  $save_attachments = static function () use ($pdo, $dbName, $comment_id, $sid, $pid, $ip, $ua): void {
    if ($comment_id <= 0) return;
    if (!isset($_FILES['attachments'])) return;

    $files = $_FILES['attachments'];
    if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) return;

    $max_files = 3;
    $max_bytes_each = 5 * 1024 * 1024; // 5MB
    $allowed_ext = ['pdf','png','jpg','jpeg','webp','txt'];
    $allowed_mime = [
      'application/pdf',
      'image/png',
      'image/jpeg',
      'image/webp',
      'text/plain',
    ];

    if (!defined('APP_ROOT') || !is_string(APP_ROOT) || APP_ROOT === '') return;

    $base = APP_ROOT . '/private/uploads/quarantine/comments/' . date('Y') . '/' . date('m');
    if (!is_dir($base)) {
      @mkdir($base, 0755, true);
    }
    if (!is_dir($base) || !is_writable($base)) {
      return;
    }

    $count = count($files['name']);
    $count = min($count, $max_files);

    $table = null;
    foreach (['comment_files','comments_files','comment_attachments','comments_attachments'] as $t) {
      $stT = $pdo->prepare("
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t LIMIT 1
      ");
      $stT->execute([':db' => $dbName, ':t' => $t]);
      if ((bool)$stT->fetchColumn()) { $table = $t; break; }
    }

    $table_cols = [];
    if ($table) {
      try {
        $stC = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        $rows = $stC ? ($stC->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        foreach ($rows as $r) {
          $f = (string)($r['Field'] ?? '');
          if ($f !== '') $table_cols[$f] = true;
        }
      } catch (Throwable $e) {
        $table = null;
        $table_cols = [];
      }
    }

    for ($i = 0; $i < $count; $i++) {
      $err = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
      if ($err !== UPLOAD_ERR_OK) continue;

      $orig = (string)($files['name'][$i] ?? '');
      $tmp  = (string)($files['tmp_name'][$i] ?? '');
      $size = (int)($files['size'][$i] ?? 0);

      if ($tmp === '' || !is_uploaded_file($tmp)) continue;
      if ($size <= 0 || $size > $max_bytes_each) continue;

      $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
      if ($ext === '' || !in_array($ext, $allowed_ext, true)) continue;

      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $mime = (string)$finfo->file($tmp);
      if ($mime === '' || !in_array($mime, $allowed_mime, true)) continue;

      $rand = bin2hex(random_bytes(16));
      $safe_name = $rand . '.' . $ext;
      $dest = rtrim($base, '/') . '/' . $safe_name;

      if (!@move_uploaded_file($tmp, $dest)) continue;
      @chmod($dest, 0644);

      $sha256 = '';
      try { $sha256 = hash_file('sha256', $dest) ?: ''; } catch (Throwable $e) { $sha256 = ''; }

      if ($table) {
        $f = [];
        $p = [];

        if (isset($table_cols['comment_id'])) { $f[]='comment_id'; $p[':comment_id']=$comment_id; }
        if (isset($table_cols['subject_id'])) { $f[]='subject_id'; $p[':subject_id']=$sid; }
        if (isset($table_cols['page_id']))    { $f[]='page_id';    $p[':page_id']=$pid; }

        if (isset($table_cols['original_name'])) { $f[]='original_name'; $p[':original_name']=$orig; }
        elseif (isset($table_cols['filename']))  { $f[]='filename';      $p[':original_name']=$orig; }

        if (isset($table_cols['stored_name'])) { $f[]='stored_name'; $p[':stored_name']=$safe_name; }
        elseif (isset($table_cols['filename'])){ $f[]='filename';    $p[':stored_name']=$safe_name; }

        if (isset($table_cols['path'])) { $f[]='path'; $p[':path']=$dest; }

        if (isset($table_cols['mime_type'])) { $f[]='mime_type'; $p[':mime']=$mime; }
        elseif (isset($table_cols['mime']))  { $f[]='mime';      $p[':mime']=$mime; }

        if (isset($table_cols['filesize'])) { $f[]='filesize'; $p[':size']=$size; }
        elseif (isset($table_cols['size'])) { $f[]='size';     $p[':size']=$size; }

        if (isset($table_cols['sha256'])) { $f[]='sha256'; $p[':sha256']=$sha256; }
        if (isset($table_cols['status'])) { $f[]='status'; $p[':status']='quarantine'; }

        if (isset($table_cols['ip_address'])) { $f[]='ip_address'; $p[':ip']=$ip; }
        elseif (isset($table_cols['ip']))     { $f[]='ip';         $p[':ip']=$ip; }

        if (isset($table_cols['user_agent'])) { $f[]='user_agent'; $p[':ua']=$ua; }
        elseif (isset($table_cols['ua']))     { $f[]='ua';         $p[':ua']=$ua; }

        if ($f) {
          $cols = implode(', ', array_map(static fn($x)=>"`{$x}`", $f));
          $vals = implode(', ', array_map(static fn($x)=>':' . $x, $f));

          $vals = str_replace(
            [':comment_id',':subject_id',':page_id',':original_name',':stored_name',':path',':mime_type',':mime',':filesize',':size',':sha256',':status',':ip_address',':ip',':user_agent',':ua'],
            [':comment_id',':subject_id',':page_id',':original_name',':stored_name',':path',':mime',':mime',':size',':size',':sha256',':status',':ip',':ip',':ua',':ua'],
            $vals
          );

          $sql = "INSERT INTO `{$table}` ({$cols}) VALUES ({$vals})";
          $st = $pdo->prepare($sql);
          $st->execute($p);
        }
      }
    }
  };

  $save_attachments();

  $redirect_safe($canon_notice_url($return_path, 'pending'), $fallback_return);

} catch (Throwable $e) {
  if (function_exists('mk_log_exception')) {
    mk_log_exception($e, ['where' => 'submit_comment.php']);
  }
  $redirect_safe($canon_notice_url($return_path, 'error'), $fallback_return);
}
