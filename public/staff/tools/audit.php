<?php
declare(strict_types=1);

/**
 * /public/staff/tools/audit.php
 * Security + Performance Audit (Staff-only)
 *
 * What it does:
 * - Scans PHP files for common risky patterns (heuristics)
 * - Reports severity (HIGH/MED/LOW) + file + line + suggestion
 * - Checks runtime hardening flags (php.ini + session cookie flags)
 *
 * Output:
 * - HTML by default
 * - JSON if ?format=json
 *
 * Notes:
 * - This tool DOES NOT use eval() or unserialize().
 * - It tries to avoid self-flagging as much as possible (simple exclusions).
 */

require_once __DIR__ . '/../../_init.php';

if (function_exists('require_staff')) {
  require_staff();
} elseif (function_exists('require_login')) {
  require_login();
}

/* Helpers */
if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

/* ---------------------------------------------------------
   Tools nav
--------------------------------------------------------- */
function pf__tools_nav(string $active = 'audit'): string {
  $items = [
    'index'       => ['/staff/tools/',                'Tools Home'],
    'scan'        => ['/staff/tools/scan.php',        'Scan'],
    'diagnostics' => ['/staff/tools/diagnostics.php', 'Diagnostics'],
    'error_log'   => ['/staff/tools/error_log.php',   'Error Log'],
    'audit'       => ['/staff/tools/audit.php',       'Audit'],
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

/* ---------------------------------------------------------
   Helpers
--------------------------------------------------------- */
function pf__real(string $path): string {
  $rp = realpath($path);
  return is_string($rp) && $rp !== '' ? $rp : $path;
}

function pf__is_within(string $path, string $base): bool {
  $rp = pf__real($path);
  $rb = pf__real($base);

  $rp = rtrim($rp, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
  $rb = rtrim($rb, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

  return strncmp($rp, $rb, strlen($rb)) === 0;
}

function pf__collect_php(string $root): array {
  $files = [];

  $it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
  );

  foreach ($it as $f) {
    /** @var SplFileInfo $f */
    if (!$f->isFile()) continue;

    $ext = strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
    if (!in_array($ext, ['php','phtml'], true)) continue;

    $files[] = $f->getPathname();
  }

  return $files;
}

/**
 * Patterns:
 * - IMPORTANT: "eval()" and "unserialize()" are still flagged as HIGH
 *   (because you want to find them elsewhere),
 *   but THIS file does not contain them anymore.
 */
function pf__audit_patterns(): array {
  return [
    [
      'severity' => 'HIGH',
      'label' => 'eval() usage',
      'regex' => '~\beval\s*\(~i',
      'suggest' => 'Avoid eval(). Replace with safe parsing/whitelisting.',
    ],
    [
      'severity' => 'HIGH',
      'label' => 'unserialize()',
      'regex' => '~\bunserialize\s*\(~i',
      'suggest' => 'Avoid unserialize on untrusted input. Prefer JSON. If needed, use allowed_classes=false.',
    ],
    [
      'severity' => 'HIGH',
      'label' => 'OS command execution',
      'regex' => '~\b(exec|shell_exec|system|passthru|proc_open|popen)\s*\(~i',
      'suggest' => 'Avoid OS command execution; if required, hard whitelist + escapeshellarg.',
    ],
    [
      'severity' => 'MED',
      'label' => 'Direct $_GET/$_POST use',
      'regex' => '~\$_(GET|POST|REQUEST)\b~',
      'suggest' => 'Prefer validated input helpers + strict casting + allowlists.',
    ],
    [
      'severity' => 'MED',
      'label' => 'header(Location:) without exit',
      'regex' => '~header\s*\(\s*[\'"]Location:~i',
      'suggest' => 'After redirect header(), call exit; to stop execution.',
    ],
    [
      'severity' => 'LOW',
      'label' => 'var_dump/print_r',
      'regex' => '~\b(var_dump|print_r)\s*\(~i',
      'suggest' => 'Remove debug output in production or guard behind DEBUG flag.',
    ],
  ];
}

/**
 * Try to avoid self-flagging:
 * - Skip scanning the tools directory itself (common false positives)
 * - You can remove this exclusion later if you want tools included.
 */
function pf__should_skip_file(string $absPath): bool {
  $p = str_replace('\\', '/', $absPath);
  if (strpos($p, '/public/staff/tools/') !== false) return true;
  return false;
}

function pf__scan_for_patterns(array $files, string $projectRoot, int $max = 2000): array {
  $findings = [];
  $count = 0;
  $patterns = pf__audit_patterns();

  foreach ($files as $file) {
    if (!is_readable($file)) continue;
    if (pf__should_skip_file($file)) continue;

    $txt = @file_get_contents($file);
    if (!is_string($txt) || $txt === '') continue;

    $lines = preg_split("/\R/", $txt) ?: [];

    foreach ($patterns as $p) {
      foreach ($lines as $i => $line) {
        $line = (string)$line;

        if (!preg_match($p['regex'], $line)) continue;

        $findings[] = [
          'severity' => $p['severity'],
          'file' => str_replace($projectRoot, '', $file),
          'line' => $i + 1,
          'pattern' => $p['label'],
          'snippet' => trim($line),
          'suggest' => $p['suggest'],
        ];

        if (++$count >= $max) break 3;
      }
    }
  }

  return $findings;
}

function pf__runtime_hardening(): array {
  $bool = static function($v): bool {
    if (is_bool($v)) return $v;
    $s = strtolower((string)$v);
    return in_array($s, ['1','on','true','yes'], true);
  };

  $out = [];
  $out[] = ['name' => 'display_errors', 'value' => ini_get('display_errors')];
  $out[] = ['name' => 'log_errors', 'value' => ini_get('log_errors')];
  $out[] = ['name' => 'error_log', 'value' => ini_get('error_log')];
  $out[] = ['name' => 'expose_php', 'value' => ini_get('expose_php')];

  $out[] = ['name' => 'session.cookie_secure',   'value' => ini_get('session.cookie_secure'),   'ok' => $bool(ini_get('session.cookie_secure'))];
  $out[] = ['name' => 'session.cookie_httponly', 'value' => ini_get('session.cookie_httponly'), 'ok' => $bool(ini_get('session.cookie_httponly'))];
  $out[] = ['name' => 'session.cookie_samesite', 'value' => ini_get('session.cookie_samesite')];

  return $out;
}

/* ---------------------------------------------------------
   Main
--------------------------------------------------------- */
$format = strtolower((string)($_GET['format'] ?? 'html'));

/**
 * Prefer APP_ROOT if defined; otherwise fall back.
 * Your diagnostics shows: /home/mkomigbo/public_html/app/mkomigbo
 */
if (defined('APP_ROOT') && is_string(APP_ROOT) && APP_ROOT !== '') {
  $projectRoot = pf__real(APP_ROOT);
} else {
  $projectRoot = pf__real(dirname(__DIR__, 3));
}

$report = [
  'generated_utc' => gmdate('Y-m-d H:i:s') . ' UTC',
  'php_version'   => PHP_VERSION,
  'project_root'  => $projectRoot,
  'runtime'       => pf__runtime_hardening(),
  'findings'      => [],
];

$files = pf__collect_php($projectRoot);
$report['findings'] = pf__scan_for_patterns($files, $projectRoot);

if ($format === 'json') {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  exit;
}

/* ---------------------------------------------------------
   Render HTML
--------------------------------------------------------- */
$page_title = 'Audit • Staff Tools';

$staff_header = (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 3) . '/app/mkomigbo') . '/private/shared/staff_header.php';
if (is_file($staff_header)) {
  include $staff_header;
} else {
  echo "<!doctype html><html><head><meta charset='utf-8'><title>" . h($page_title) . "</title></head><body>";
}

echo pf__tools_nav('audit');
?>
<div class="card" style="padding:16px; border:1px solid rgba(0,0,0,.08); border-radius:14px; background:#fff;">
  <h2 style="margin:0 0 6px;">Security + Performance Audit</h2>
  <div style="opacity:.8; font-size:13px;">
    Generated: <?= h($report['generated_utc']) ?> • PHP <?= h($report['php_version']) ?>
  </div>

  <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
    <a class="btn" href="?format=json">View JSON</a>
  </div>

  <hr style="margin:14px 0; border:none; border-top:1px solid rgba(0,0,0,.08);">

  <h3 style="margin:0 0 10px;">Runtime hardening</h3>
  <table style="width:100%; border-collapse:collapse;">
    <thead>
      <tr>
        <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(0,0,0,.08);">Setting</th>
        <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(0,0,0,.08);">Value</th>
        <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(0,0,0,.08);">OK</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($report['runtime'] as $r): ?>
        <tr>
          <td style="padding:8px; border-bottom:1px solid rgba(0,0,0,.06);"><?= h((string)$r['name']) ?></td>
          <td style="padding:8px; border-bottom:1px solid rgba(0,0,0,.06); font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12px;"><?= h((string)($r['value'] ?? '')) ?></td>
          <td style="padding:8px; border-bottom:1px solid rgba(0,0,0,.06);"><?= array_key_exists('ok', $r) ? ($r['ok'] ? '✅' : '⚠️') : '—' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div style="height:14px;"></div>

  <h3 style="margin:0 0 10px;">Findings</h3>
  <div style="opacity:.75; font-size:13px; margin-bottom:10px;">
    Scanned files: <?= (int)count($files) ?> • Findings: <?= (int)count($report['findings']) ?>
    <span style="margin-left:10px;" class="muted">(Tools directory excluded to reduce self-noise.)</span>
  </div>

  <?php if (empty($report['findings'])): ?>
    <div style="padding:12px; border-radius:12px; border:1px solid rgba(0,0,0,.08); background:rgba(46, 204, 113, .07);">
      ✅ No findings (based on this heuristic scan).
    </div>
  <?php else: ?>
    <table style="width:100%; border-collapse:collapse;">
      <thead>
        <tr>
          <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(0,0,0,.08);">Severity</th>
          <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(0,0,0,.08);">File</th>
          <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(0,0,0,.08);">Line</th>
          <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(0,0,0,.08);">Pattern</th>
          <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(0,0,0,.08);">Suggestion</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($report['findings'] as $f): ?>
          <tr>
            <td style="padding:8px; border-bottom:1px solid rgba(0,0,0,.06); font-weight:800;"><?= h((string)$f['severity']) ?></td>
            <td style="padding:8px; border-bottom:1px solid rgba(0,0,0,.06); font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12px;"><?= h((string)$f['file']) ?></td>
            <td style="padding:8px; border-bottom:1px solid rgba(0,0,0,.06);"><?= h((string)$f['line']) ?></td>
            <td style="padding:8px; border-bottom:1px solid rgba(0,0,0,.06);"><?= h((string)$f['pattern']) ?></td>
            <td style="padding:8px; border-bottom:1px solid rgba(0,0,0,.06);">
              <div><?= h((string)$f['suggest']) ?></div>
              <?php if (!empty($f['snippet'])): ?>
                <div style="margin-top:6px; padding:8px; border-radius:10px; background:rgba(0,0,0,.03); font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12px; overflow:auto;">
                  <?= h((string)$f['snippet']) ?>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php
$staff_footer = (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 3) . '/app/mkomigbo') . '/private/shared/staff_footer.php';
if (is_file($staff_footer)) {
  include $staff_footer;
} else {
  echo "</main></body></html>";
}
