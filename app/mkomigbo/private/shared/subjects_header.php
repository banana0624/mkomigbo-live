<?php
declare(strict_types=1);

/**
 * /private/shared/subjects_header.php
 * Public Subjects section header.
 *
 * Ensures:
 * - $active_nav = 'subjects'
 * - Subjects CSS bundle always loads (in correct order)
 *
 * Optional (set before include):
 * - $extra_css   (array|string) additional css after subjects bundle
 * - $page_title  (string)
 * - $page_desc   (string)
 */

$active_nav = 'subjects';
$nav_active = 'subjects';

if (!isset($page_title) || !is_string($page_title) || trim($page_title) === '') {
  $page_title = 'Subjects • Mkomi Igbo';
}

/* Normalize extra_css to array */
if (!isset($extra_css)) {
  $extra_css = [];
} elseif (is_string($extra_css)) {
  $extra_css = [$extra_css];
} elseif (!is_array($extra_css)) {
  $extra_css = [];
}

/* Subjects bundle (authoritative order) */
$bundle = [
  '/lib/css/subjects-mk-bridge.css',
  '/lib/css/subjects-grid.css',
  '/lib/css/subjects-public.css',
];

/* Merge bundle + extra, dedupe */
$final = [];
$seen = [];

$norm = static function ($p): ?string {
  if (!is_string($p)) return null;
  $p = trim($p);
  if ($p === '') return null;
  if (preg_match('~^https?://~i', $p)) return $p;
  if ($p[0] !== '/') $p = '/' . $p;
  return $p;
};

foreach ($bundle as $b) {
  $b = $norm($b);
  if (!$b) continue;
  $k = strtolower($b);
  if (isset($seen[$k])) continue;
  $seen[$k] = true;
  $final[] = $b;
}

foreach ($extra_css as $x) {
  $x = $norm($x);
  if (!$x) continue;
  $k = strtolower($x);
  if (isset($seen[$k])) continue;
  $seen[$k] = true;
  $final[] = $x;
}

$extra_css = $final;

/* Delegate to public_header.php */
$public_header = __DIR__ . '/public_header.php';
if (!is_file($public_header)) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "public_header.php not found\nExpected: {$public_header}\n";
  exit;
}
require $public_header;
