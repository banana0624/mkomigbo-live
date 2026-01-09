<?php
declare(strict_types=1);

/**
 * tools/scan_project.php
 * Run from CLI:
 *   php tools/scan_project.php
 *
 * Optional:
 *   php tools/scan_project.php --root=/path/to/project
 *   php tools/scan_project.php --max=5000
 *
 * Output:
 * - Summary + findings + suggested fixes
 */

function cli_arg(string $name, ?string $default = null): ?string {
  global $argv;
  foreach ($argv as $a) {
    if (str_starts_with($a, "--{$name}=")) return substr($a, strlen("--{$name}="));
  }
  return $default;
}

function norm_path(string $p): string {
  $p = str_replace('\\', '/', $p);
  return preg_replace('#/+#', '/', $p) ?? $p;
}

function read_file_safe(string $path, int $maxBytes = 900_000): ?string {
  if (!is_file($path) || !is_readable($path)) return null;
  $sz = filesize($path);
  if ($sz !== false && $sz > $maxBytes) return null; // skip giant files
  $c = file_get_contents($path);
  return $c === false ? null : $c;
}

function is_template_value(string $v): bool {
  $v = trim($v);
  return $v === '' || str_contains($v, '<?') || str_contains($v, '<?=') || str_contains($v, '<?php');
}

function extract_assets(string $content): array {
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

function extract_includes(string $content): array {
  $out = [];
  if (preg_match_all('/\b(?:include|include_once|require|require_once)\s*\(\s*([\'"])(.*?)\1\s*\)\s*;/i', $content, $m)) {
    foreach ($m[2] as $p) $out[] = $p;
  }
  if (preg_match_all('/\b(?:include|include_once|require|require_once)\s+([\'"])(.*?)\1\s*;/i', $content, $m)) {
    foreach ($m[2] as $p) $out[] = $p;
  }
  return $out;
}

function map_asset_to_disk(string $href, string $public_root): array {
  $href = trim($href);
  if ($href === '') return ['type' => 'empty'];
  if (is_template_value($href)) return ['type' => 'template'];
  if (preg_match('#^https?://#i', $href)) return ['type' => 'external'];
  if (str_starts_with($href, '//') || str_starts_with($href, 'data:')) return ['type' => 'external'];

  $clean = preg_replace('/[?#].*$/', '', $href) ?? $href;

  if (str_starts_with($clean, '/')) {
    $disk = $public_root . str_replace('/', DIRECTORY_SEPARATOR, $clean);
    return ['type' => 'absolute', 'disk' => $disk, 'web' => $clean];
  }
  return ['type' => 'relative', 'web' => $clean];
}

function walk_files(string $root, array $exts = ['php'], int $maxFiles = 6000): array {
  $out = [];
  $it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
  );

  foreach ($it as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) continue;

    $p = $file->getPathname();

    // skip heavy/irrelevant
    if (str_contains($p, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;
    if (str_contains($p, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)) continue;
    if (str_contains($p, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR)) continue;

    $ext = strtolower($file->getExtension());
    if (!in_array($ext, $exts, true)) continue;

    $out[] = $p;
    if (count($out) >= $maxFiles) break;
  }

  sort($out);
  return $out;
}

function php_lint(string $file): array {
  // Use "php -l" if available; if not, it may return non-zero or fail silently.
  $cmd = 'php -l ' . escapeshellarg($file) . ' 2>&1';
  $out = [];
  $code = 0;
  @exec($cmd, $out, $code);
  return ['ok' => ($code === 0), 'output' => implode("\n", $out), 'code' => $code];
}

/* ---- roots ---- */
$root = cli_arg('root');
if (!$root) $root = dirname(__DIR__); // tools/.. -> project root
$root = rtrim($root, "/\\");
$root = norm_path($root);

$max = cli_arg('max');
$maxFiles = $max ? max(100, (int)$max) : 6000;

$public_root = norm_path($root . DIRECTORY_SEPARATOR . 'public');

echo "Mkomigbo Project Scan\n";
echo "Root: {$root}\n";
echo "Public root: {$public_root}\n";
echo "Max files: {$maxFiles}\n\n";

$files = walk_files($root, ['php'], $maxFiles);
echo "PHP files found: " . count($files) . "\n\n";

$lint_errors = [];
$asset_missing = [];
$relative_assets = [];
$template_assets = 0;
$include_issues = [];

$scanned = 0;

foreach ($files as $path) {
  $scanned++;

  // Lint
  $lint = php_lint($path);
  if (!$lint['ok']) {
    $lint_errors[] = ['file' => $path, 'msg' => $lint['output'], 'code' => $lint['code']];
  }

  $content = read_file_safe($path);
  if ($content === null) continue;

  // Asset links
  $assets = extract_assets($content);
  foreach ($assets as $a) {
    $mapped = map_asset_to_disk((string)$a['value'], $public_root);

    if ($mapped['type'] === 'template') {
      $template_assets++;
      continue; // template expression, not a literal URL
    }

    if ($mapped['type'] === 'absolute') {
      $disk = (string)$mapped['disk'];
      if (!is_file($disk)) {
        $asset_missing[] = [
          'file' => $path,
          'href' => $a['value'],
          'disk' => $disk,
          'suggest' => "Ensure file exists under /public{$mapped['web']} OR correct the href to a valid asset.",
        ];
      }
    } elseif ($mapped['type'] === 'relative') {
      $relative_assets[] = [
        'file' => $path,
        'href' => $a['value'],
        'suggest' => "Replace with absolute: /lib/... OR use url_for('/lib/...') so assets work at any URL depth.",
      ];
    }
  }

  // Includes (literal only)
  $incs = extract_includes($content);
  foreach ($incs as $inc) {
    $inc = trim((string)$inc);
    if ($inc === '') continue;

    // skip dynamic includes
    if (str_contains($inc, '$')) continue;

    $base = dirname($path);
    $try1 = $base . DIRECTORY_SEPARATOR . $inc;
    $try2 = $root . DIRECTORY_SEPARATOR . ltrim($inc, '/\\');
    if (!is_file($try1) && !is_file($try2)) {
      $include_issues[] = [
        'file' => $path,
        'include' => $inc,
        'try_relative' => $try1,
        'try_root' => $try2,
        'suggest' => "Prefer stable includes: dirname(__DIR__, N) . '/private/shared/...' or load via initialize.php.",
      ];
    }
  }

  // Safety: avoid endless runtime on slow hosts
  if ($scanned >= $maxFiles) break;
}

/* ---- print report ---- */
echo "==== SUMMARY ====\n";
echo "Scanned: {$scanned}\n";
echo "Lint errors: " . count($lint_errors) . "\n";
echo "Broken absolute assets: " . count($asset_missing) . "\n";
echo "Relative assets (likely CSS/JS breakage): " . count($relative_assets) . "\n";
echo "Template assets skipped: {$template_assets}\n";
echo "Missing literal includes: " . count($include_issues) . "\n";
echo "=================\n\n";

echo "==== LINT ERRORS (" . count($lint_errors) . ") ====\n";
foreach ($lint_errors as $e) {
  echo "\nFILE: {$e['file']}\n";
  echo "Exit code: {$e['code']}\n";
  echo "{$e['msg']}\n";
}

echo "\n==== BROKEN ABSOLUTE ASSET LINKS (" . count($asset_missing) . ") ====\n";
foreach ($asset_missing as $e) {
  echo "\nFILE: {$e['file']}\n";
  echo "href: {$e['href']}\n";
  echo "disk missing: {$e['disk']}\n";
  echo "fix: {$e['suggest']}\n";
}

echo "\n==== RELATIVE ASSET LINKS (LIKELY CSS ISSUES) (" . count($relative_assets) . ") ====\n";
foreach ($relative_assets as $e) {
  echo "\nFILE: {$e['file']}\n";
  echo "relative href/src: {$e['href']}\n";
  echo "fix: {$e['suggest']}\n";
}

echo "\n==== MISSING LITERAL INCLUDES (" . count($include_issues) . ") ====\n";
foreach ($include_issues as $e) {
  echo "\nFILE: {$e['file']}\n";
  echo "include: {$e['include']}\n";
  echo "try relative: {$e['try_relative']}\n";
  echo "try root: {$e['try_root']}\n";
  echo "fix: {$e['suggest']}\n";
}

echo "\n==== DONE ====\n";
