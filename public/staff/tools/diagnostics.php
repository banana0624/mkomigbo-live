<?php
declare(strict_types=1);

/**
 * /public/staff/tools/diagnostics.php
 * Diagnostics + Scan (staff-only)
 *
 * Output:
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
if (!function_exists('pf__bool')) {
  function pf__bool($v): bool {
    if (is_bool($v)) return $v;
    $s = strtolower((string)$v);
    return in_array($s, ['1','true','yes','on'], true);
  }
}

/* URL helper (router-safe) */
function pf__url(string $path): string {
  $path = trim($path);
  if ($path === '') return '';
  if (preg_match('~^https?://~i', $path)) return $path;

  if ($path[0] !== '/') $path = '/' . $path;

  if (function_exists('url_for')) return (string)url_for($path);
  if (defined('WWW_ROOT') && is_string(WWW_ROOT) && WWW_ROOT !== '') return rtrim(WWW_ROOT, '/') . $path;

  return $path;
}

/* Tools nav */
function pf__tools_nav(string $active = 'diagnostics'): string {
  $items = [
    'index'       => [pf__url('/staff/tools/'),              'Tools Home'],
    'scan'        => [pf__url('/staff/tools/scan.php'),      'Scan'],
    'diagnostics' => [pf__url('/staff/tools/diagnostics.php'),'Diagnostics'],
    'error_log'   => [pf__url('/staff/tools/error_log.php'), 'Error Log'],
    'audit'       => [pf__url('/staff/tools/audit.php'),     'Audit'],
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
  $out .= '</div>';
  $out .= '<div style="font-size:12px; opacity:.8;">Staff Tools</div>';
  $out .= '</div></nav>';
  return $out;
}

/* Base URL */
function pf__site_base_url(): string {
  if (function_exists('env')) {
    $v = env('SITE_URL', '');
    if (is_string($v) && trim($v) !== '') return rtrim(trim($v), '/');
  }
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  $scheme = $https ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return $scheme . '://' . $host;
}

/* HTTP check helper */
function pf__http_check(string $url, int $timeout = 6): array {
  $res = [
    'url' => $url,
    'ok' => false,
    'status' => null,
    'content_type' => null,
    'bytes' => null,
    'note' => null,
  ];

  if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => $url,
      CURLOPT_NOBODY => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => false,
      CURLOPT_TIMEOUT => $timeout,
      CURLOPT_CONNECTTIMEOUT => $timeout,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_SSL_VERIFYHOST => 0,
      CURLOPT_HEADER => true,
      CURLOPT_USERAGENT => 'MkomigboDiagnostics/1.2',
    ]);
    $hdr = curl_exec($ch);
    if ($hdr === false) {
      $res['note'] = 'curl HEAD failed: ' . curl_error($ch);
      curl_close($ch);
      return $res;
    }
    $res['status'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $res['content_type'] = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if (!$res['status'] || in_array((int)$res['status'], [0,403,405], true)) {
      $ch = curl_init();
      curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'MkomigboDiagnostics/1.2',
        CURLOPT_RANGE => '0-1023',
      ]);
      $body = curl_exec($ch);
      $res['status'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $res['content_type'] = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
      $res['bytes'] = is_string($body) ? strlen($body) : null;
      curl_close($ch);

      if (is_string($body) && stripos($body, '<html') !== false) {
        $res['note'] = 'Body looks like HTML (rewrite/router?)';
      }
    }

    $res['ok'] = ($res['status'] !== null && (int)$res['status'] >= 200 && (int)$res['status'] < 300);
    return $res;
  }

  $ctx = stream_context_create([
    'http' => ['method' => 'GET', 'timeout' => $timeout, 'ignore_errors' => true],
    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
  ]);
  $body = @file_get_contents($url, false, $ctx);
  $res['bytes'] = is_string($body) ? strlen($body) : null;

  $status = null;
  $ctype = null;
  if (isset($http_response_header) && is_array($http_response_header)) {
    foreach ($http_response_header as $hline) {
      if (preg_match('~^HTTP/\S+\s+(\d{3})~i', $hline, $m)) $status = (int)$m[1];
      if (stripos($hline, 'Content-Type:') === 0) $ctype = trim(substr($hline, 13));
    }
  }
  $res['status'] = $status;
  $res['content_type'] = $ctype;
  $res['ok'] = ($status !== null && $status >= 200 && $status < 300);
  if (is_string($body) && stripos($body, '<html') !== false) {
    $res['note'] = 'Body looks like HTML (rewrite/router?)';
  }
  return $res;
}

/* Safe path checks */
function pf__is_within(string $path, string $base): bool {
  $rp = realpath($path);
  $rb = realpath($base);
  if ($rp === false || $rb === false) return false;
  $rp = rtrim($rp, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
  $rb = rtrim($rb, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
  return strncmp($rp, $rb, strlen($rb)) === 0;
}

function pf__collect_files(string $target, array $exts = ['php','phtml','html']): array {
  if (is_file($target)) return [$target];

  $files = [];
  $it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)
  );

  $extsLower = array_map('strtolower', $exts);
  foreach ($it as $f) {
    /** @var SplFileInfo $f */
    if (!$f->isFile()) continue;
    $ext = strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
    if (!in_array($ext, $extsLower, true)) continue;
    $files[] = $f->getPathname();
  }
  return $files;
}

function pf__scan_files(array $files, string $projectRoot, int $maxFindings = 1200): array {
  $findings = [];
  $count = 0;
  $reAsset = '~\b(?:href|src)\s*=\s*([\'"])([^\'"]+)\1~i';

  foreach ($files as $file) {
    if (!is_readable($file)) continue;
    $txt = @file_get_contents($file);
    if (!is_string($txt) || $txt === '') continue;

    $lines = preg_split("/\R/", $txt) ?: [];
    $n = count($lines);

    for ($i = 0; $i < $n; $i++) {
      $line = (string)$lines[$i];
      $ln = $i + 1;

      if (stripos($line, '/private/') !== false) {
        $findings[] = [
          'severity' => 'HIGH',
          'file' => str_replace($projectRoot, '', $file),
          'line' => $ln,
          'pattern' => 'private path in output/code',
          'snippet' => trim($line),
          'suggest' => 'Never link to /private/. Use public endpoints and shared includes.',
        ];
        if (++$count >= $maxFindings) break 2;
      }

      if (strpos($line, '/mkomigbo/public') !== false || strpos($line, 'public_html/mkomigbo/public') !== false) {
        $findings[] = [
          'severity' => 'MED',
          'file' => str_replace($projectRoot, '', $file),
          'line' => $ln,
          'pattern' => 'hard-coded deployment path',
          'snippet' => trim($line),
          'suggest' => 'Use url_for("/...") or absolute web paths like "/lib/css/ui.css".',
        ];
        if (++$count >= $maxFindings) break 2;
      }

      if (preg_match_all($reAsset, $line, $m, PREG_SET_ORDER)) {
        foreach ($m as $mm) {
          $url = trim($mm[2]);

          if ($url === '' ||
              $url[0] === '#' ||
              stripos($url, 'mailto:') === 0 ||
              stripos($url, 'tel:') === 0 ||
              stripos($url, 'http://') === 0 ||
              stripos($url, 'https://') === 0 ||
              strpos($url, '//') === 0 ||
              stripos($url, 'data:') === 0) {
            continue;
          }

          if ($url[0] !== '/' && $url[0] !== '.') {
            $findings[] = [
              'severity' => 'MED',
              'file' => str_replace($projectRoot, '', $file),
              'line' => $ln,
              'pattern' => 'relative asset link (no leading /)',
              'snippet' => trim($line),
              'suggest' => 'Change to absolute: "/' . ltrim($url, '/') . '" (or use url_for())',
            ];
            if (++$count >= $maxFindings) break 3;
          }

          if (strpos($url, '../') !== false) {
            $findings[] = [
              'severity' => 'LOW',
              'file' => str_replace($projectRoot, '', $file),
              'line' => $ln,
              'pattern' => 'dot-relative asset path',
              'snippet' => trim($line),
              'suggest' => 'Prefer absolute web paths like "/lib/css/..." to avoid depth issues.',
            ];
            if (++$count >= $maxFindings) break 3;
          }
        }
      }
    }
  }

  return $findings;
}

/* ---------------------------
 * Inputs
 * --------------------------- */
$format   = strtolower((string)($_GET['format'] ?? 'html'));
$doScan   = pf__bool($_POST['scan'] ?? ($_GET['scan'] ?? '0'));
$doBrowse = pf__bool($_GET['browse'] ?? '0');

$projectRoot = (defined('APP_ROOT') && is_string(APP_ROOT) && APP_ROOT !== '')
  ? APP_ROOT
  : (realpath(dirname(__DIR__, 4)) ?: dirname(__DIR__, 4)); // fallback only

$baseUrl = pf__site_base_url();

/* Default browse roots */
$browseRoots = [
  'Project root'      => $projectRoot,
  'public'            => $projectRoot . DIRECTORY_SEPARATOR . 'public',
  'private'           => $projectRoot . DIRECTORY_SEPARATOR . 'private',
  'private/shared'    => $projectRoot . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'shared',
  'public/subjects'   => $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'subjects',
  'public/lib/css'    => $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'css',
  'public/staff/tools'=> $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'staff' . DIRECTORY_SEPARATOR . 'tools',
];

/* Target to scan */
$defaultTarget = $browseRoots['private/shared'] ?? $projectRoot . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'shared';
$target = trim((string)($_POST['target'] ?? ($_GET['target'] ?? $defaultTarget)));
if ($target === '') $target = $defaultTarget;

$targetReal = realpath($target);
$targetOk = ($targetReal !== false && (is_dir($targetReal) || is_file($targetReal)) && pf__is_within($targetReal, $projectRoot));

/* Browse directory */
$browseDir = trim((string)($_GET['dir'] ?? $defaultTarget));
if ($browseDir === '') $browseDir = $defaultTarget;

$browseReal = realpath($browseDir);
$browseOk = ($browseReal !== false && is_dir($browseReal) && pf__is_within($browseReal, $projectRoot));

/* ---------------------------
 * Build report
 * --------------------------- */
$report = [
  'generated_utc' => gmdate('Y-m-d H:i:s') . ' UTC',
  'php_version' => PHP_VERSION,
  'pretty_url' => $baseUrl . '/staff/tools/diagnostics.php',
  'project_root' => $projectRoot,
  'target' => $targetReal ?: $target,
  'target_ok' => $targetOk,
  'bootstrap' => [],
  'http_checks' => [],
  'scan' => [
    'ran' => false,
    'files_scanned' => 0,
    'findings' => [],
  ],
];

/* Bootstrap paths (NO $init usage) */
$bootstrapChecks = [
  'private/assets/initialize.php'      => $projectRoot . '/private/assets/initialize.php',
  'private/shared/staff_header.php'    => $projectRoot . '/private/shared/staff_header.php',
  'private/shared/staff_footer.php'    => $projectRoot . '/private/shared/staff_footer.php',
  'public/lib/css/ui.css'              => $projectRoot . '/public/lib/css/ui.css',
  'public/lib/css/staff.css'           => $projectRoot . '/public/lib/css/staff.css',
  'public/staff/tools/index.php'       => $projectRoot . '/public/staff/tools/index.php',
];

foreach ($bootstrapChecks as $name => $path) {
  $report['bootstrap'][] = [
    'name' => $name,
    'path' => $path,
    'exists' => is_file($path),
    'readable' => is_readable($path),
  ];
}

/* HTTP checks */
$httpUrls = [
  $baseUrl . '/lib/css/ui.css',
  $baseUrl . '/lib/css/staff.css',
  $baseUrl . '/staff/tools/',
];
foreach ($httpUrls as $u) {
  $report['http_checks'][] = pf__http_check($u);
}

/* Run scan */
if ($doScan) {
  $report['scan']['ran'] = true;

  if ($targetOk) {
    $files = pf__collect_files($targetReal, ['php','phtml','html']);
    $report['scan']['files_scanned'] = count($files);
    $report['scan']['findings'] = pf__scan_files($files, $projectRoot);
  } else {
    $report['scan']['files_scanned'] = 0;
    $report['scan']['findings'] = [[
      'severity' => 'HIGH',
      'file' => '(input)',
      'line' => 0,
      'pattern' => 'invalid target',
      'snippet' => $target,
      'suggest' => 'Target must be an existing file/folder within project root: ' . $projectRoot,
    ]];
  }
}

/* Output JSON */
if ($format === 'json') {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  exit;
}

/* HTML output */
$page_title = 'Diagnostics • Staff Tools';
$nav_active = 'tools';
$staff_header = $projectRoot . '/private/shared/staff_header.php';
$staff_footer = $projectRoot . '/private/shared/staff_footer.php';

if (is_file($staff_header)) {
  include $staff_header;
} else {
  echo "<!doctype html><html><head><meta charset='utf-8'><title>" . h($page_title) . "</title></head><body>";
}

echo pf__tools_nav('diagnostics');
?>
<style>
  .diag-input { pointer-events:auto !important; user-select:auto !important; opacity:1 !important; }
  .diag-btn   { cursor:pointer; }
  .diag-mono  { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12px; }
</style>

<div class="card" style="padding:16px; border:1px solid rgba(0,0,0,.08); border-radius:14px; background:#fff;">
  <h2 style="margin:0 0 6px;">Diagnostics (CSS + Linking + Scan)</h2>
  <div style="opacity:.8; font-size:13px;">
    Generated: <?= h($report['generated_utc']) ?> • PHP <?= h($report['php_version']) ?>
  </div>

  <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
    <a class="diag-btn" href="?format=json" style="text-decoration:none; padding:8px 10px; border-radius:10px; border:1px solid rgba(0,0,0,.1);">View JSON</a>
    <a class="diag-btn" href="<?= h($report['pretty_url']) ?>" style="text-decoration:none; padding:8px 10px; border-radius:10px; border:1px solid rgba(0,0,0,.1);">Refresh</a>
    <a class="diag-btn" href="?browse=1&amp;dir=<?= urlencode($browseReal ?: $defaultTarget) ?>" style="text-decoration:none; padding:8px 10px; border-radius:10px; border:1px solid rgba(0,0,0,.1);">Browse Project</a>
  </div>

  <hr style="margin:14px 0; border:none; border-top:1px solid rgba(0,0,0,.08);">

  <h3 style="margin:0 0 8px;">Open diagnostics:</h3>
  <div class="diag-mono">
    Pretty URL: <?= h($report['pretty_url']) ?><br>
    Server root: <?= h($report['project_root']) ?>
  </div>

  <h3 style="margin:14px 0 8px;">Scan target (server path)</h3>
  <form method="post" style="margin-top:10px;">
    <input type="hidden" name="scan" value="1">
    <input
      class="diag-input"
      name="target"
      value="<?= h($targetReal ?: $target) ?>"
      style="width:100%; padding:10px 12px; border-radius:10px; border:1px solid rgba(0,0,0,.15);"
      placeholder="<?= h($defaultTarget) ?>"
      autocomplete="off"
      spellcheck="false"
    >
    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;">
      <button class="diag-btn" type="submit" style="padding:10px 12px; border-radius:10px; border:1px solid rgba(0,0,0,.15); background:#fff; font-weight:700;">
        Run Scan
      </button>
      <a class="diag-btn" href="<?= h($report['pretty_url']) ?>" style="padding:10px 12px; border-radius:10px; border:1px solid rgba(0,0,0,.1); text-decoration:none;">
        Reset
      </a>
      <span style="opacity:.75; align-self:center; font-size:12px;">
        Tip: click <b>Browse Project</b> and choose a file.
      </span>
    </div>
  </form>
</div>

<?php if ($doBrowse): ?>
  <div style="height:14px;"></div>
  <div class="card" style="padding:16px; border:1px solid rgba(0,0,0,.08); border-radius:14px; background:#fff;">
    <h3 style="margin:0 0 10px;">Browse project (server-side)</h3>

    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
      <?php foreach ($browseRoots as $label => $path): ?>
        <?php $rp = realpath($path); if ($rp === false) continue; ?>
        <a class="diag-btn" href="?browse=1&amp;dir=<?= urlencode($rp) ?>"
           style="text-decoration:none; padding:8px 10px; border-radius:10px; border:1px solid rgba(0,0,0,.1);">
          <?= h($label) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="diag-mono" style="opacity:.85; margin-bottom:10px;">
      Current: <?= h($browseOk ? $browseReal : $browseDir) ?>
      <?php if (!$browseOk): ?>
        <div style="color:#b00; margin-top:6px;">Invalid browse directory (must be inside project root).</div>
      <?php endif; ?>
    </div>

    <?php
      $entries = [];
      if ($browseOk) {
        $it = new DirectoryIterator($browseReal);
        foreach ($it as $fi) {
          if ($fi->isDot()) continue;
          $name = $fi->getFilename();
          if ($name === '') continue;
          $entries[] = $fi;
          if (count($entries) >= 400) break;
        }

        usort($entries, function($a, $b) {
          if ($a->isDir() && !$b->isDir()) return -1;
          if (!$a->isDir() && $b->isDir()) return 1;
          return strcasecmp($a->getFilename(), $b->getFilename());
        });
      }

      $parent = $browseOk ? dirname($browseReal) : '';
      $parentOk = ($parent !== '' && pf__is_within($parent, $projectRoot) && is_dir($parent));
    ?>

    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px;">
      <?php if ($parentOk): ?>
        <a class="diag-btn" href="?browse=1&amp;dir=<?= urlencode($parent) ?>"
           style="text-decoration:none; padding:8px 10px; border-radius:10px; border:1px solid rgba(0,0,0,.1);">
          ← Up
        </a>
      <?php endif; ?>
      <?php if ($browseOk): ?>
        <a class="diag-btn" href="?browse=1&amp;dir=<?= urlencode($browseReal) ?>"
           style="text-decoration:none; padding:8px 10px; border-radius:10px; border:1px solid rgba(0,0,0,.1);">
          Refresh list
        </a>
      <?php endif; ?>
    </div>

    <?php if (!$browseOk): ?>
      <div style="padding:12px; border-radius:12px; border:1px dashed rgba(0,0,0,.2); background:rgba(0,0,0,.02);">
        Cannot browse this directory.
      </div>
    <?php else: ?>
      <div style="border:1px solid rgba(0,0,0,.08); border-radius:12px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse;">
          <thead>
            <tr>
              <th style="text-align:left; padding:10px; border-bottom:1px solid rgba(0,0,0,.08);">Name</th>
              <th style="text-align:left; padding:10px; border-bottom:1px solid rgba(0,0,0,.08); width:120px;">Type</th>
              <th style="text-align:left; padding:10px; border-bottom:1px solid rgba(0,0,0,.08); width:120px;">Action</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($entries as $fi): ?>
            <?php
              $full = $fi->getPathname();
              $isDir = $fi->isDir();
              $type = $isDir ? 'folder' : 'file';
              $ext = strtolower(pathinfo($fi->getFilename(), PATHINFO_EXTENSION));
              $scanable = (!$isDir && in_array($ext, ['php','phtml','html'], true));
            ?>
            <tr>
              <td style="padding:10px; border-bottom:1px solid rgba(0,0,0,.06);">
                <?php if ($isDir): ?>
                  <a href="?browse=1&amp;dir=<?= urlencode($full) ?>" style="text-decoration:none;">
                    <?= h($fi->getFilename()) ?>
                  </a>
                <?php else: ?>
                  <?= h($fi->getFilename()) ?>
                <?php endif; ?>
              </td>
              <td style="padding:10px; border-bottom:1px solid rgba(0,0,0,.06);"><?= h($type) ?></td>
              <td style="padding:10px; border-bottom:1px solid rgba(0,0,0,.06);">
                <?php if ($scanable): ?>
                  <a href="?target=<?= urlencode($full) ?>" style="text-decoration:none; padding:6px 9px; border-radius:10px; border:1px solid rgba(0,0,0,.12); display:inline-block;">
                    Use
                  </a>
                <?php else: ?>
                  <span style="opacity:.6;">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div style="margin-top:10px; font-size:12px; opacity:.75;">
        Only <b>.php/.phtml/.html</b> files can be selected for scanning.
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div style="height:14px;"></div>

<div class="card" style="padding:16px; border:1px solid rgba(0,0,0,.08); border-radius:14px; background:#fff;">
  <h3 style="margin:0 0 10px;">HTTP checks (asset serving)</h3>
  <table style="width:100%; border-collapse:collapse;">
    <thead>
      <tr>
        <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(0,0,0,.08);">OK</th>
        <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(0,0,0,.08);">Status</th>
        <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(0,0,0,.08);">Content-Type</th>
        <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(0,0,0,.08);">URL</th>
        <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(0,0,0,.08);">Note</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($report['http_checks'] as $c): ?>
      <tr>
        <td style="padding:8px; border-bottom:1px solid rgba(0,0,0,.06);"><?= $c['ok'] ? 'OK' : 'NO' ?></td>
        <td style="padding:8px; border-bottom:1px solid rgba(0,0,0,.06);"><?= h((string)($c['status'] ?? '—')) ?></td>
        <td style="padding:8px; border-bottom:1px solid rgba(0,0,0,.06);"><?= h((string)($c['content_type'] ?? '—')) ?></td>
        <td style="padding:8px; border-bottom:1px solid rgba(0,0,0,.06);" class="diag-mono"><?= h((string)$c['url']) ?></td>
        <td style="padding:8px; border-bottom:1px solid rgba(0,0,0,.06);"><?= h((string)($c['note'] ?? '')) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div style="height:14px;"></div>

<div class="card" style="padding:16px; border:1px solid rgba(0,0,0,.08); border-radius:14px; background:#fff;">
  <h3 style="margin:0 0 10px;">Scan results</h3>

  <div style="opacity:.75; font-size:13px; margin-bottom:10px;">
    Scan ran: <?= $report['scan']['ran'] ? 'Yes' : 'No' ?> •
    Files scanned: <?= (int)$report['scan']['files_scanned'] ?> •
    Findings: <?= is_array($report['scan']['findings']) ? count($report['scan']['findings']) : 0 ?>
  </div>

  <?php if (!$report['scan']['ran']): ?>
    <div style="padding:12px; border-radius:12px; border:1px dashed rgba(0,0,0,.2); background:rgba(0,0,0,.02);">
      Scan hasn’t run yet. Click <strong>Run Scan</strong> above.
    </div>
  <?php else: ?>
    <?php if (empty($report['scan']['findings'])): ?>
      <div style="padding:12px; border-radius:12px; border:1px solid rgba(0,0,0,.08); background:rgba(46, 204, 113, .07);">
        No findings in the scanned target.
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
          <?php foreach ($report['scan']['findings'] as $f): ?>
            <tr>
              <td style="padding:8px; border-bottom:1px solid rgba(0,0,0,.06); font-weight:800;">
                <?= h((string)($f['severity'] ?? '')) ?>
              </td>
              <td style="padding:8px; border-bottom:1px solid rgba(0,0,0,.06);" class="diag-mono">
                <?= h((string)($f['file'] ?? '')) ?>
              </td>
              <td style="padding:8px; border-bottom:1px solid rgba(0,0,0,.06);"><?= h((string)($f['line'] ?? '')) ?></td>
              <td style="padding:8px; border-bottom:1px solid rgba(0,0,0,.06);"><?= h((string)($f['pattern'] ?? '')) ?></td>
              <td style="padding:8px; border-bottom:1px solid rgba(0,0,0,.06);">
                <div><?= h((string)($f['suggest'] ?? '')) ?></div>
                <?php if (!empty($f['snippet'])): ?>
                  <div style="margin-top:6px; padding:8px; border-radius:10px; background:rgba(0,0,0,.03);" class="diag-mono">
                    <?= h((string)$f['snippet']) ?>
                  </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php
if (is_file($staff_footer)) {
  include $staff_footer;
} else {
  echo "</body></html>";
}
