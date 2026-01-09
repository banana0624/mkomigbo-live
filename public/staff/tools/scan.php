<?php
declare(strict_types=1);

/**
 * /public/staff/tools/scan.php
 * Staff-only project scan (WEB UI)
 *
 * Modes:
 * - Scan selected file: ?file=/private/shared/public_header.php
 * - Scan whole project: default
 *
 * Extras:
 * - Tools navbar (links between tools)
 * - Browse safe picker (?browse=1)
 * - JSON output (?format=json)
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

/* ---------- Fatal error capture (prevents blank 500) ---------- */
register_shutdown_function(function (): void {
  $e = error_get_last();
  if (!$e) return;

  $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
  if (!in_array((int)$e['type'], $fatalTypes, true)) return;

  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "SCAN.PHP FATAL ERROR\n";
  echo "Type: {$e['type']}\n";
  echo "Message: {$e['message']}\n";
  echo "File: {$e['file']}\n";
  echo "Line: {$e['line']}\n";
});

/* ---------- Bootstrap ---------- */
require_once __DIR__ . '/../../_init.php';

/* Auth guard (staff-only) */
if (function_exists('require_staff')) {
  require_staff();
} elseif (function_exists('require_login')) {
  require_login();
}

/* basic esc */
if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

/* ---------- URL helper ---------- */
function t_url(string $path): string {
  if (function_exists('url_for')) return (string)url_for($path);
  return $path;
}

/* ---------- Tools mini-nav ---------- */
function tools_nav(string $active): void {
  $items = [
    ['k' => 'tools', 'label' => 'Tools',     'href' => t_url('/staff/tools/')],
    ['k' => 'health','label' => 'Health',    'href' => t_url('/staff/tools/diagnostics.php')],
    ['k' => 'audit', 'label' => 'Audit',     'href' => t_url('/staff/tools/audit.php')],
    ['k' => 'scan',  'label' => 'Scan',      'href' => t_url('/staff/tools/scan.php')],
    ['k' => 'logs',  'label' => 'Error Log', 'href' => t_url('/staff/tools/error_log.php')],
  ];

  echo '<div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-top:10px;">';
  foreach ($items as $it) {
    $is = ($it['k'] === $active);
    $style = 'text-decoration:none; padding:8px 12px; border-radius:999px; font:800 13px system-ui; border:1px solid #e5e7eb; background:#fff; color:#111827;';
    if ($is) $style = 'text-decoration:none; padding:8px 12px; border-radius:999px; font:900 13px system-ui; border:1px solid #111827; background:#111827; color:#fff;';
    echo '<a href="' . h($it['href']) . '" style="' . h($style) . '">' . h($it['label']) . '</a>';
  }
  echo '</div>';
}

/* ---------- Helpers ---------- */
function scan_exec_available(): bool {
  if (!function_exists('exec')) return false;
  $disabled = (string)ini_get('disable_functions');
  if ($disabled === '') return true;
  $list = array_map('trim', explode(',', $disabled));
  return !in_array('exec', $list, true);
}

function scan_norm(string $p): string {
  $p = str_replace('\\', '/', $p);
  return preg_replace('#/+#', '/', $p) ?? $p;
}

function scan_starts_with(string $haystack, string $prefix): bool {
  return strncmp($haystack, $prefix, strlen($prefix)) === 0;
}

function scan_read(string $path, int $maxBytes): ?string {
  if (!is_file($path) || !is_readable($path)) return null;
  $sz = filesize($path);
  if ($sz !== false && $sz > $maxBytes) return null;
  $c = file_get_contents($path);
  return $c === false ? null : $c;
}

function scan_is_template_value(string $v): bool {
  $v = trim($v);
  return $v === '' || str_contains($v, '<?') || str_contains($v, '<?=') || str_contains($v, '<?php');
}

function scan_extract_assets(string $content): array {
  $found = [];
  if (preg_match_all('/<link\b[^>]*href\s*=\s*([\'"])(.*?)\1/is', $content, $m)) {
    foreach ($m[2] as $href) $found[] = ['kind' => 'link', 'attr' => 'href', 'value' => $href];
  }
  if (preg_match_all('/<script\b[^>]*src\s*=\s*([\'"])(.*?)\1/is', $content, $m)) {
    foreach ($m[2] as $src) $found[] = ['kind' => 'script', 'attr' => 'src', 'value' => $src];
  }
  if (preg_match_all('/<img\b[^>]*src\s*=\s*([\'"])(.*?)\1/is', $content, $m)) {
    foreach ($m[2] as $src) $found[] = ['kind' => 'img', 'attr' => 'src', 'value' => $src];
  }
  return $found;
}

function scan_extract_includes(string $content): array {
  $out = [];
  if (preg_match_all('/\b(?:include|include_once|require|require_once)\s*\(\s*([\'"])(.*?)\1\s*\)\s*;/i', $content, $m)) {
    foreach ($m[2] as $p) $out[] = $p;
  }
  if (preg_match_all('/\b(?:include|include_once|require|require_once)\s+([\'"])(.*?)\1\s*;/i', $content, $m)) {
    foreach ($m[2] as $p) $out[] = $p;
  }
  return $out;
}

function scan_map_asset(string $href, string $public_root): array {
  $href = trim($href);
  if ($href === '') return ['type' => 'empty'];
  if (scan_is_template_value($href)) return ['type' => 'template'];
  if (preg_match('#^https?://#i', $href)) return ['type' => 'external'];
  if (str_starts_with($href, '//') || str_starts_with($href, 'data:')) return ['type' => 'external'];

  $clean = preg_replace('/[?#].*$/', '', $href) ?? $href;

  if (str_starts_with($clean, '/')) {
    $disk = $public_root . str_replace('/', DIRECTORY_SEPARATOR, $clean);
    return ['type' => 'absolute', 'web' => $clean, 'disk' => $disk];
  }
  return ['type' => 'relative', 'web' => $clean];
}

/* SPL-safe walker */
function scan_walk_php(string $root, int $maxFiles): array {
  $out = [];
  $root = rtrim($root, "/\\");
  if (!is_dir($root)) return $out;

  $useIter = class_exists('RecursiveIteratorIterator') && class_exists('RecursiveDirectoryIterator');

  if ($useIter) {
    $it = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
      /** @var SplFileInfo $f */
      if (!$f->isFile()) continue;
      $p = $f->getPathname();
      if (str_contains($p, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;
      if (str_contains($p, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)) continue;
      if (str_contains($p, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR)) continue;
      if (strtolower($f->getExtension()) !== 'php') continue;
      $out[] = $p;
      if (count($out) >= $maxFiles) break;
    }
  } else {
    $stack = [$root];
    while ($stack && count($out) < $maxFiles) {
      $dir = array_pop($stack);
      $items = @scandir($dir);
      if (!is_array($items)) continue;
      foreach ($items as $name) {
        if ($name === '.' || $name === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (is_dir($path)) {
          if ($name === 'vendor' || $name === '.git' || $name === 'node_modules') continue;
          $stack[] = $path;
          continue;
        }
        if (is_file($path) && str_ends_with(strtolower($path), '.php')) {
          $out[] = $path;
          if (count($out) >= $maxFiles) break;
        }
      }
    }
  }

  sort($out);
  return $out;
}

function scan_php_lint(string $file): array {
  if (!scan_exec_available()) return ['ok' => null, 'output' => 'exec() disabled', 'code' => null];
  $cmd = 'php -l ' . escapeshellarg($file) . ' 2>&1';
  $out = [];
  $code = 0;
  @exec($cmd, $out, $code);
  return ['ok' => ($code === 0), 'output' => implode("\n", $out), 'code' => $code];
}

/* ---------- Project roots ---------- */
$project_root = dirname(__DIR__, 3);
$public_root  = $project_root . '/public';
$private_root = $project_root . '/private';

/* ---------- Inputs ---------- */
$maxFiles = 2200;
$maxBytes = 650_000;
$maxRows  = 450;

$format = $_GET['format'] ?? '';
$wantJson = is_string($format) && strtolower($format) === 'json';

$doLint = $_GET['lint'] ?? '';
$doLint = is_string($doLint) ? strtolower(trim($doLint)) : '';
$doLint = ($doLint === '1' || $doLint === 'yes' || $doLint === 'true');

$input = $_GET['file'] ?? '';
$input = is_string($input) ? trim($input) : '';

$browse = $_GET['browse'] ?? '';
$browse = is_string($browse) ? strtolower(trim($browse)) : '';
$showBrowse = ($browse === '1' || $browse === 'yes' || $browse === 'true');

$action = $_GET['action'] ?? '';
$action = is_string($action) ? strtolower(trim($action)) : '';

$basePath = strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '/staff/tools/scan.php';
if ($action === 'clear' || $action === 'reset') {
  header('Location: ' . $basePath, true, 303);
  exit;
}

/* ---------- File selection validation ---------- */
$selected = null;
$selected_error = null;

if ($input !== '') {
  $input = str_replace('\\', '/', $input);
  if ($input[0] !== '/') $input = '/' . $input;

  $candidate = null;
  if (scan_starts_with($input, '/public/')) {
    $candidate = $project_root . $input;
  } elseif (scan_starts_with($input, '/private/')) {
    $candidate = $project_root . $input;
  } else {
    $selected_error = "Only /public/... or /private/... paths are allowed.";
  }

  if ($candidate !== null) {
    $real = realpath($candidate);
    $realRoot = realpath($project_root);

    if (!$real || !$realRoot || !scan_starts_with($real, $realRoot)) {
      $selected_error = "Invalid path (outside project root).";
    } elseif (!is_file($real) || !is_readable($real)) {
      $selected_error = "File not found or not readable: {$candidate}";
    } else {
      $selected = $real;
    }
  }
}

/* ---------- Browse (safe picker) ---------- */
function scan_list_files(string $root, array $allowExt, int $maxFiles = 250, int $maxDepth = 7): array {
  $rootReal = realpath($root);
  if (!$rootReal || !is_dir($rootReal)) return [];

  $out = [];
  $iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootReal, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
  );

  foreach ($iter as $fi) {
    if (!$fi instanceof SplFileInfo) continue;
    if ($iter->getDepth() > $maxDepth) continue;
    if (!$fi->isFile()) continue;

    $ext = strtolower($fi->getExtension());
    if (!in_array($ext, $allowExt, true)) continue;

    $full = $fi->getRealPath();
    if (!$full) continue;

    $out[] = $full;
    if (count($out) >= $maxFiles) break;
  }

  sort($out);
  return $out;
}

function scan_to_project_relative(string $absPath, string $projectRoot): ?string {
  $pRoot = realpath($projectRoot);
  $abs = realpath($absPath);
  if (!$pRoot || !$abs) return null;
  if (!scan_starts_with($abs, $pRoot)) return null;

  $rel = str_replace('\\', '/', substr($abs, strlen($pRoot)));
  if ($rel === '') $rel = '/';
  if ($rel[0] !== '/') $rel = '/' . $rel;

  if (scan_starts_with($rel, '/public/') || scan_starts_with($rel, '/private/')) return $rel;
  return null;
}

$browse_list = [];
if ($showBrowse) {
  $exts = ['php', 'css', 'js', 'htaccess'];
  $abs = array_merge(
    scan_list_files($private_root, $exts, 200, 8),
    scan_list_files($public_root, $exts, 200, 8)
  );

  $rel = [];
  foreach ($abs as $p) {
    $r = scan_to_project_relative($p, $project_root);
    if ($r) $rel[] = $r;
    if (count($rel) >= 250) break;
  }
  $browse_list = $rel;
}

/* ---------- Scan engine ---------- */
function scan_one_or_many(array $paths, string $public_root, string $project_root, bool $doLint, int $maxBytes, int $maxRows): array {
  $findings = [
    'relative_assets' => [],
    'missing_assets' => [],
    'missing_includes' => [],
    'lint_errors' => [],
  ];

  $counts = [
    'files_scanned' => 0,
    'templates_skipped' => 0,
    'external_assets' => 0,
    'empty_assets' => 0,
    'lint_attempted' => 0,
  ];

  foreach ($paths as $path) {
    $counts['files_scanned']++;

    $content = scan_read($path, $maxBytes);
    if ($content === null) continue;

    if ($doLint) {
      $counts['lint_attempted']++;
      $lint = scan_php_lint($path);
      if ($lint['ok'] === false) {
        $findings['lint_errors'][] = ['file' => $path, 'code' => $lint['code'], 'output' => $lint['output']];
        if (count($findings['lint_errors']) >= 120) $doLint = false;
      }
    }

    foreach (scan_extract_assets($content) as $a) {
      $mapped = scan_map_asset((string)$a['value'], $public_root);

      if ($mapped['type'] === 'template') { $counts['templates_skipped']++; continue; }
      if ($mapped['type'] === 'external') { $counts['external_assets']++; continue; }
      if ($mapped['type'] === 'empty') { $counts['empty_assets']++; continue; }

      if ($mapped['type'] === 'relative') {
        $findings['relative_assets'][] = [
          'file' => $path,
          'tag' => "{$a['kind']} {$a['attr']}",
          'value' => (string)$a['value'],
          'suggest' => "Fix to '/lib/...'(preferred) OR url_for('/lib/...') so it works at any URL depth.",
        ];
      }

      if ($mapped['type'] === 'absolute') {
        $disk = (string)$mapped['disk'];
        if (!is_file($disk)) {
          $findings['missing_assets'][] = [
            'file' => $path,
            'tag' => "{$a['kind']} {$a['attr']}",
            'value' => (string)$a['value'],
            'disk' => $disk,
            'suggest' => "Asset points to {$mapped['web']} but file is missing under /public. Fix href or add file.",
          ];
        }
      }
    }

    foreach (scan_extract_includes($content) as $inc) {
      $inc = trim((string)$inc);
      if ($inc === '') continue;
      if (str_contains($inc, '$')) continue;

      $base = dirname($path);
      $try1 = $base . DIRECTORY_SEPARATOR . $inc;
      $try2 = $project_root . DIRECTORY_SEPARATOR . ltrim($inc, '/\\');

      if (!is_file($try1) && !is_file($try2)) {
        $findings['missing_includes'][] = [
          'file' => $path,
          'include' => $inc,
          'try_relative' => $try1,
          'try_root' => $try2,
          'suggest' => "Prefer stable includes: dirname(__DIR__, N).'/private/shared/...' or route via initialize.php.",
        ];
      }
    }

    $totalRows =
      count($findings['relative_assets']) +
      count($findings['missing_assets']) +
      count($findings['missing_includes']) +
      count($findings['lint_errors']);

    if ($totalRows >= $maxRows) break;
  }

  return ['counts' => $counts, 'findings' => $findings];
}

/* Decide scan targets */
$scan_mode = 'project';
$targets = [];

if ($selected) {
  $scan_mode = 'selected';
  $targets = [$selected];
} else {
  $targets = scan_walk_php($project_root, $maxFiles);
}

$res = scan_one_or_many($targets, $public_root, $project_root, $doLint, $maxBytes, $maxRows);

$report = [
  'meta' => [
    'generated_at_utc' => gmdate('Y-m-d H:i:s') . ' UTC',
    'php_version' => PHP_VERSION,
    'exec_available' => scan_exec_available(),
    'project_root' => scan_norm($project_root),
    'public_root' => scan_norm($public_root),
    'mode' => $scan_mode,
    'selected' => $selected,
    'caps' => ['maxFiles' => $maxFiles, 'maxBytes' => $maxBytes, 'maxRows' => $maxRows],
    'inputs' => ['lint' => $doLint, 'file' => $input],
  ],
  'counts' => $res['counts'],
  'summary' => [
    'relative_assets' => count($res['findings']['relative_assets']),
    'missing_assets' => count($res['findings']['missing_assets']),
    'missing_includes' => count($res['findings']['missing_includes']),
    'lint_errors' => count($res['findings']['lint_errors']),
  ],
  'findings' => $res['findings'],
];

if ($wantJson) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  exit;
}

/* ---------- UI helpers ---------- */
function scan_card(string $title, string $desc, array $items, int $limit): void {
  echo '<section style="margin:18px 0; padding:14px; border:1px solid #e5e7eb; border-radius:12px; background:#fff;">';
  echo '<h2 style="margin:0 0 8px; font:800 18px system-ui;">' . h($title) . ' (' . h((string)count($items)) . ')</h2>';
  echo '<div style="margin:0 0 10px; color:#6b7280; font:13px system-ui;">' . h($desc) . '</div>';

  if (!$items) {
    echo '<div style="padding:10px; border:1px solid #f0f2f5; border-radius:10px; color:#065f46; background:#ecfdf5; font:14px system-ui;">✅ None found</div>';
    echo '</section>';
    return;
  }

  $shown = 0;
  echo '<div style="display:grid; gap:10px;">';
  foreach ($items as $it) {
    $shown++;
    echo '<div style="padding:10px; border:1px solid #f0f2f5; border-radius:12px;">';
    echo '<div style="font:800 13px system-ui; color:#111827;">' . h((string)($it['file'] ?? '')) . '</div>';
    echo '<pre style="margin:8px 0 0; padding:10px; background:#0b1020; color:#e5e7eb; border-radius:12px; overflow:auto; font:12px ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, \'Liberation Mono\', \'Courier New\', monospace; white-space:pre-wrap;">'
      . h(json_encode($it, JSON_UNESCAPED_SLASHES)) . '</pre>';
    echo '</div>';
    if ($shown >= $limit) break;
  }
  echo '</div>';

  if (count($items) > $limit) {
    echo '<div style="margin-top:10px; color:#6b7280; font:13px system-ui;">Showing first ' . h((string)$limit) . '. Use <code>?format=json</code> for more (still capped).</div>';
  }

  echo '</section>';
}

/* current file value from query param */
$currentFileValue = is_string($_GET['file'] ?? null) ? trim((string)$_GET['file']) : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Scan • Mkomigbo</title>
</head>
<body style="margin:0; background:#f6f7fb;">
  <div style="max-width:1150px; margin:0 auto; padding:20px;">

    <div style="padding:16px 18px; border:1px solid #e5e7eb; border-radius:14px; background:#fff;">
      <h1 style="margin:0; font:900 22px system-ui;">Scan</h1>
      <div style="margin-top:6px; color:#4b5563; font:400 14px system-ui;">
        Mode: <strong><?= h((string)($report['meta']['mode'] ?? 'project')) ?></strong>
        • Generated <?= h($report['meta']['generated_at_utc'] ?? '') ?> • PHP <?= h($report['meta']['php_version'] ?? '') ?>
      </div>

      <?php tools_nav('scan'); ?>

      <div style="margin-top:12px; padding:12px; border-radius:12px; background:#f9fafb; border:1px dashed #e5e7eb; color:#374151; font:13px system-ui;">
        <strong>Why CSS disappears:</strong> after splitting headers/footers, the most common break is <em>relative</em> asset paths like
        <code>lib/css/ui.css</code>. They work on <code>/</code> but fail on <code>/subjects/history/</code>.
        Fix them to <code>/lib/css/ui.css</code> or <code>url_for('/lib/css/ui.css')</code>.
      </div>

      <!-- Scan form -->
      <form method="get" action="<?= h($basePath) ?>"
            style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap; align-items:center; position:relative; z-index:5;">
        <input
          type="text"
          name="file"
          value="<?= h($currentFileValue) ?>"
          placeholder="/private/shared/public_header.php  or  /public/subjects/page.php"
          autocomplete="off"
          spellcheck="false"
          style="min-width:420px; flex:1; padding:10px 12px; border-radius:10px; border:1px solid #e5e7eb; font:14px system-ui; background:#fff; color:#111827;"
        >

        <button type="submit"
          style="padding:10px 14px; border-radius:10px; border:1px solid #111827; background:#111827; color:#fff; font:800 13px system-ui; cursor:pointer;">
          Scan File
        </button>

        <a href="<?= h($basePath) ?>"
          style="text-decoration:none; padding:10px 14px; border-radius:10px; border:1px solid #e5e7eb; background:#fff; color:#111827; font:800 13px system-ui;">
          Scan Project
        </a>

        <button type="submit" name="action" value="clear"
          style="padding:10px 14px; border-radius:10px; border:1px solid #e5e7eb; background:#fff; color:#111827; font:800 13px system-ui; cursor:pointer;">
          Clear
        </button>

        <a href="<?= h($basePath) ?>?browse=1"
          style="text-decoration:none; padding:10px 14px; border-radius:10px; border:1px solid #e5e7eb; background:#f9fafb; color:#111827; font:800 13px system-ui;">
          Browse
        </a>

        <a href="<?= h($basePath) ?>?format=json"
          style="text-decoration:none; padding:10px 14px; border-radius:10px; border:1px solid #e5e7eb; background:#fff; color:#111827; font:800 13px system-ui;">
          JSON
        </a>

        <a href="<?= h($basePath) ?>?lint=1"
          style="text-decoration:none; padding:10px 14px; border-radius:10px; border:1px solid #111827; background:#111827; color:#fff; font:800 13px system-ui;">
          Lint
        </a>
      </form>

      <?php if (is_string($selected_error) && $selected_error !== ''): ?>
        <div style="margin-top:10px; padding:10px; border-radius:12px; background:#fff7ed; border:1px solid #fed7aa; color:#7c2d12; font:13px system-ui;">
          <?= h($selected_error) ?>
        </div>
      <?php elseif ($selected): ?>
        <div style="margin-top:10px; padding:10px; border-radius:12px; background:#ecfeff; border:1px solid #a5f3fc; color:#0e7490; font:13px system-ui;">
          Scanning selected file: <strong><?= h($selected) ?></strong>
        </div>
      <?php endif; ?>

      <?php if ($showBrowse): ?>
        <div style="margin-top:14px; padding:12px; border-radius:12px; background:#f9fafb; border:1px solid #e5e7eb;">
          <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; align-items:center;">
            <div style="font:900 14px system-ui; color:#111827;">Browse server files (safe picker)</div>
            <a href="<?= h($basePath) ?>"
               style="text-decoration:none; font:800 13px system-ui; color:#111827;">Close</a>
          </div>

          <div style="margin-top:8px; font:13px system-ui; color:#4b5563;">
            Click a file to scan it. List is limited to <code>/public</code> and <code>/private</code>.
          </div>

          <div style="margin-top:10px; max-height:240px; overflow:auto; border:1px solid #e5e7eb; border-radius:10px; background:#fff;">
            <?php if (!$browse_list): ?>
              <div style="padding:10px; font:13px system-ui; color:#991b1b;">No files found to browse (or permissions blocked).</div>
            <?php else: ?>
              <?php foreach ($browse_list as $p): ?>
                <div style="padding:8px 10px; border-bottom:1px solid #f3f4f6; font:13px ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;">
                  <a href="<?= h($basePath) ?>?file=<?= h($p) ?>" style="text-decoration:none; color:#111827;"><?= h($p) ?></a>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <?php
      scan_card('Relative asset paths (LIKELY cause of missing CSS)', 'Fix each to /lib/... or url_for("/lib/...").', $report['findings']['relative_assets'], 160);
      scan_card('Missing absolute assets', 'These are /... paths that point to files that do not exist under /public.', $report['findings']['missing_assets'], 120);
      scan_card('Missing literal includes', 'Literal include/require strings not found on disk (dynamic includes ignored).', $report['findings']['missing_includes'], 120);
      scan_card('PHP lint errors (optional)', 'Only appears if exec() works and you used ?lint=1.', $report['findings']['lint_errors'], 60);
    ?>

  </div>
</body>
</html>
