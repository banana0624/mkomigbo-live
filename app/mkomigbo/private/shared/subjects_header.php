<?php
declare(strict_types=1);

/**
 * /private/shared/subjects_header.php
 * Public Subjects section header.
 *
 * Ensures:
 * - $active_nav = 'subjects'
 * - ui.css + Subjects CSS bundle are included (delegates to public_header.php)
 *
 * Bundle candidates (included if present):
 * - /lib/css/subjects-mk-bridge.css
 * - /lib/css/subjects-grid.css
 * - /lib/css/subjects-public.css   (preferred)
 * - /lib/css/subjects.css          (fallback)
 * - /lib/css/subject-public.css    (legacy fallback)
 */

$active_nav = 'subjects'; // force

/* Brand + site metadata for public_header.php (DISPLAY only) */
if (!isset($brand_name) || !is_string($brand_name) || trim($brand_name) === '') {
  $brand_name = defined('MK_BRAND_NAME') ? (string)MK_BRAND_NAME : 'Mkomi Igbo';
}
if (!isset($site_name) || !is_string($site_name) || trim($site_name) === '') {
  $site_name = defined('MK_SITE_NAME') ? (string)MK_SITE_NAME : $brand_name;
}

if (!isset($page_title) || !is_string($page_title) || trim($page_title) === '') {
  $page_title = 'Subjects • ' . $brand_name;
}

/* Normalize extra_css to array */
if (!isset($extra_css)) {
  $extra_css = [];
} elseif (is_string($extra_css)) {
  $extra_css = [$extra_css];
} elseif (!is_array($extra_css)) {
  $extra_css = [];
}

/* Normalize CSS path to absolute "/..." and allow full URLs */
$pf__norm_css = static function ($p): ?string {
  if (!is_string($p)) return null;
  $p = trim($p);
  if ($p === '') return null;
  if (preg_match('~^https?://~i', $p)) return $p;
  return ($p[0] === '/') ? $p : ('/' . $p);
};

/* Determine plausible public roots for existence checks */
$roots = [];
if (defined('PUBLIC_PATH') && is_string(PUBLIC_PATH) && trim((string)PUBLIC_PATH) !== '') {
  $roots[] = (string)PUBLIC_PATH; // ideally /home/.../public_html/public
}
$docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), "/\\");
if ($docRoot !== '') {
  $roots[] = $docRoot . DIRECTORY_SEPARATOR . 'public';
  $roots[] = $docRoot;
}
if (defined('APP_ROOT') && is_string(APP_ROOT) && trim((string)APP_ROOT) !== '') {
  $ph = dirname(dirname((string)APP_ROOT)); // /public_html
  if (is_string($ph) && $ph !== '') {
    $roots[] = rtrim($ph, "/\\") . DIRECTORY_SEPARATOR . 'public';
  }
}
$roots = array_values(array_unique(array_filter($roots, static fn($r) => is_string($r) && $r !== '')));

/* Best-effort existence check under a public root */
$pf__public_file_exists = static function (string $publicRoot, string $webPath) use ($pf__norm_css): bool {
  $publicRoot = rtrim($publicRoot, "/\\");
  if ($publicRoot === '') return false;

  $webPath = $pf__norm_css($webPath);
  if (!$webPath || preg_match('~^https?://~i', $webPath)) return false;

  $fs = $publicRoot . str_replace('/', DIRECTORY_SEPARATOR, $webPath);
  return is_file($fs);
};

/* Include path only if present; if we cannot verify, we still include it (safe) */
$pf__pick_if_present = static function (string $webPath) use ($roots, $pf__public_file_exists): ?string {
  foreach ($roots as $root) {
    if ($pf__public_file_exists($root, $webPath)) return $webPath;
  }
  // If roots are unknown/unreliable, do not block inclusion; return the web path.
  return $webPath;
};

/* Build Subjects bundle (ordered) */
$bundle = [
  '/lib/css/ui.css',
  $pf__pick_if_present('/lib/css/subjects-mk-bridge.css'),
  $pf__pick_if_present('/lib/css/subjects-grid.css'),
];

/* Choose the best “main” subjects stylesheet */
$main_candidates = [
  '/lib/css/subjects-public.css',
  '/lib/css/subjects.css',
  '/lib/css/subject-public.css',
];

$main_css = null;
foreach ($main_candidates as $c) {
  $picked = $pf__pick_if_present($c);
  if (is_string($picked) && $picked !== '') {
    $main_css = $picked;
    // Prefer the first; do not overthink existence here
    break;
  }
}
if ($main_css) $bundle[] = $main_css;

/* Merge must-have bundle + extras, de-dupe */
$final = [];
$seen  = [];

$add = static function ($href) use (&$final, &$seen, $pf__norm_css): void {
  $href = $pf__norm_css($href);
  if (!$href) return;
  $k = strtolower($href);
  if (isset($seen[$k])) return;
  $seen[$k] = true;
  $final[] = $href;
};

foreach ($bundle as $b) $add($b);
foreach ($extra_css as $x) $add($x);

$extra_css = $final;

/* Delegate to the main public header */
$public_header = dirname(__DIR__) . '/shared/public_header.php';
if (!is_file($public_header)) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "public_header.php not found\nExpected: {$public_header}\n";
  exit;
}

require $public_header;
