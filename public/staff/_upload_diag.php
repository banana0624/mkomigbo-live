<?php
declare(strict_types=1);

// /public_html/public/staff/_upload_diag.php

@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/_init.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

/* Require staff login if available */
if (function_exists('require_staff_login')) require_staff_login();
elseif (function_exists('mk_require_staff_login')) mk_require_staff_login();
elseif (function_exists('require_staff')) require_staff();

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$max = static function(string $k): string {
  $v = ini_get($k);
  return ($v === false) ? 'N/A' : (string)$v;
};

$toBytes = static function(string $val): int {
  $val = trim($val);
  if ($val === '') return 0;
  $last = strtolower(substr($val, -1));
  $n = (int)$val;
  if ($last === 'g') return $n * 1024 * 1024 * 1024;
  if ($last === 'm') return $n * 1024 * 1024;
  if ($last === 'k') return $n * 1024;
  return (int)$val;
};

$files = $_FILES['attachments'] ?? null;

?>
<!doctype html><html><head><meta charset="utf-8"><title>Upload Diag</title></head>
<body style="font-family:system-ui,Segoe UI,Arial; padding:20px;">
<h1>Upload Diagnostic</h1>

<h3>PHP limits</h3>
<ul>
  <li>upload_max_filesize: <b><?=h($max('upload_max_filesize'))?></b></li>
  <li>post_max_size: <b><?=h($max('post_max_size'))?></b></li>
  <li>memory_limit: <b><?=h($max('memory_limit'))?></b></li>
  <li>max_file_uploads: <b><?=h($max('max_file_uploads'))?></b></li>
  <li>file_uploads: <b><?=h($max('file_uploads'))?></b></li>
  <li>max_input_time: <b><?=h($max('max_input_time'))?></b></li>
  <li>max_execution_time: <b><?=h($max('max_execution_time'))?></b></li>
</ul>

<h3>Effective caps (bytes)</h3>
<ul>
  <li>upload_max_filesize bytes: <b><?=$toBytes((string)$max('upload_max_filesize'))?></b></li>
  <li>post_max_size bytes: <b><?=$toBytes((string)$max('post_max_size'))?></b></li>
</ul>

<h3>Test upload</h3>
<form method="post" enctype="multipart/form-data">
  <input type="file" name="attachments[]" multiple>
  <button type="submit">Upload to diag (no saving)</button>
</form>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
  <h3>$_FILES dump</h3>
  <pre style="background:#f6f6f6;padding:12px;overflow:auto;"><?php
    var_export($files);
  ?></pre>

  <?php
    if (is_array($files) && isset($files['error']) && is_array($files['error'])) {
      echo "<h3>Per-file error codes</h3><ul>";
      foreach ($files['error'] as $i => $err) {
        echo "<li>File #".(int)$i.": error=".(int)$err."</li>";
      }
      echo "</ul>";
    }
  ?>
<?php endif; ?>

</body></html>
