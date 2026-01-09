<?php
declare(strict_types=1);

/**
 * /public/staff/tools/error_log.php
 * View recent PHP error log snippets (staff-only)
 *
 * - HTML by default
 * - JSON via ?format=json
 */

require_once __DIR__ . '/../../_init.php';

/* Auth guard */
if (function_exists('require_staff')) {
  require_staff();
} elseif (function_exists('require_login')) {
  require_login();
}

/* Helpers */
if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}
function pf__tools_nav(string $active = 'error_log'): string {
  $items = [
    'index'       => ['/staff/tools/',               'Tools Home'],
    'scan'        => ['/staff/tools/scan.php',       'Scan'],
    'diagnostics' => ['/staff/tools/diagnostics.php','Diagnostics'],
    'error_log'   => ['/staff/tools/error_log.php',  'Error Log'],
    'audit'       => ['/staff/tools/audit.php',      'Audit'],
  ];
  $out = '<nav class="tools-nav" style="margin:12px 0 18px; padding:10px 12px; border:1px solid rgba(0,0,0,.08); border-radius:12px; background:#fff;">';
  $out .= '<div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between;">';
  $out .= '<div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">';
  foreach ($items as $key => [$href, $label]) {
    $is = ($key === $active);
    $style = $is
      ? 'style="display:inline-block; padding:8px 10px; border-radius:10px; text-decoration:none; font-weight:700; border:1px solid rgba(0,0,0,.15); background:rgba(0,0,0,.04);"'
      : 'style="display:inline-block; padding:8px 10px; border-radius:10px; text-decoration:none; border:1px solid rgba(0,0,0,.08); background:#fff;"';
    $out .= '<a '.$style.' href="'.h($href).'">'.h($label).'</a>';
  }
  $out .= '</div><div style="font-size:12px; opacity:.8;">Staff Tools</div></div></nav>';
  return $out;
}

$format = strtolower((string)($_GET['format'] ?? 'html'));
$tail = (int)($_GET['tail'] ?? 200);
if ($tail < 20) $tail = 20;
if ($tail > 2000) $tail = 2000;

/**
 * Determine candidate error log paths.
 * You can add your known live path here if needed.
 */
$candidates = [
  ini_get('error_log') ?: '',
  dirname(__DIR__, 3) . '/private/logs/php-error.log',
  dirname(__DIR__, 3) . '/private/logs/error.log',
  dirname(__DIR__, 3) . '/error_log',
];

$logPath = '';
foreach ($candidates as $c) {
  $c = trim((string)$c);
  if ($c !== '' && is_file($c) && is_readable($c)) { $logPath = $c; break; }
}

$lines = [];
$note = null;

if ($logPath !== '') {
  // Read last N lines without loading huge file into memory
  $fp = fopen($logPath, 'rb');
  if ($fp) {
    $buffer = '';
    $pos = -1;
    $lineCount = 0;
    fseek($fp, 0, SEEK_END);
    $size = ftell($fp);

    while ($size + $pos >= 0 && $lineCount < $tail) {
      fseek($fp, $pos, SEEK_END);
      $ch = fgetc($fp);
      if ($ch === "\n") $lineCount++;
      $buffer = $ch . $buffer;
      $pos--;
      if (strlen($buffer) > 2_000_000) { // safety cap
        $note = 'Output truncated for safety.';
        break;
      }
    }
    fclose($fp);
    $lines = preg_split("/\R/", trim($buffer)) ?: [];
  } else {
    $note = 'Could not open log file.';
  }
} else {
  $note = 'No readable error log found. Check ini_get("error_log") and your server log path.';
}

$out = [
  'generated_utc' => gmdate('Y-m-d H:i:s') . ' UTC',
  'log_path' => $logPath !== '' ? $logPath : null,
  'tail' => $tail,
  'note' => $note,
  'lines' => $lines,
];

if ($format === 'json') {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  exit;
}

$page_title = 'Error Log • Staff Tools';
$staff_header = APP_ROOT . '/private/shared/staff_header.php';
if (is_file($staff_header)) {
  include $staff_header;
} else {
  echo "<!doctype html><html><head><meta charset='utf-8'><title>" . h($page_title) . "</title></head><body>";
}

echo pf__tools_nav('error_log');
?>
<div class="card" style="padding:16px; border:1px solid rgba(0,0,0,.08); border-radius:14px; background:#fff;">
  <h2 style="margin:0 0 6px;">Error Log</h2>
  <div style="opacity:.8; font-size:13px;">
    Generated: <?= h($out['generated_utc']) ?> • Tail: <?= (int)$tail ?>
  </div>

  <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
    <a href="?format=json&amp;tail=<?= (int)$tail ?>" style="text-decoration:none; padding:8px 10px; border-radius:10px; border:1px solid rgba(0,0,0,.1);">View JSON</a>
    <a href="?tail=200" style="text-decoration:none; padding:8px 10px; border-radius:10px; border:1px solid rgba(0,0,0,.1);">Tail 200</a>
    <a href="?tail=800" style="text-decoration:none; padding:8px 10px; border-radius:10px; border:1px solid rgba(0,0,0,.1);">Tail 800</a>
  </div>

  <hr style="margin:14px 0; border:none; border-top:1px solid rgba(0,0,0,.08);">

  <div style="font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12px; opacity:.85;">
    Log path: <?= h((string)($out['log_path'] ?? '—')) ?>
  </div>

  <?php if (!empty($out['note'])): ?>
    <div style="margin-top:10px; padding:12px; border-radius:12px; border:1px solid rgba(0,0,0,.08); background:rgba(255, 200, 0, .10);">
      <?= h((string)$out['note']) ?>
    </div>
  <?php endif; ?>

  <div style="margin-top:12px; padding:12px; border-radius:12px; border:1px solid rgba(0,0,0,.08); background:rgba(0,0,0,.03); overflow:auto; max-height:65vh;">
    <?php if (empty($lines)): ?>
      <div style="opacity:.75;">No lines to show.</div>
    <?php else: ?>
      <?php foreach ($lines as $ln): ?>
        <div><?= h((string)$ln) ?></div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php
$footer = dirname(__DIR__, 3) . '/private/shared/footer.php';
if (is_file($footer)) {
  include $footer;
} else {
  echo "</body></html>";
}
