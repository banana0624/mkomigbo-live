<?php
declare(strict_types=1);

/**
 * /private/shared/contributor_header.php
 *
 * Purpose:
 * - Public Contributors header wrapper
 * - Delegates to the canonical public_header.php (project contract)
 * - Never hard-fails due to brittle relative include assumptions
 *
 * Expected (optional) vars before include:
 * - $page_title, $page_desc, $extra_css, $extra_js
 * - $active_nav or $nav_active
 */

if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

/* Defaults */
if (!isset($active_nav) || !is_string($active_nav) || trim($active_nav) === '') {
  $active_nav = 'contributors';
}
if (!isset($nav_active) || !is_string($nav_active) || trim($nav_active) === '') {
  // your public_header accepts $nav_active; keep both in sync
  $nav_active = $active_nav;
}
if (!isset($page_title) || !is_string($page_title) || trim($page_title) === '') {
  $page_title = 'Contributors — Mkomi Igbo';
}
if (!isset($page_desc) || !is_string($page_desc) || trim($page_desc) === '') {
  $page_desc = 'Authors, editors, researchers, and collaborators helping to build and refine Mkomi Igbo.';
}

/* ---------------------------------------------------------
   Locate canonical public_header.php safely
--------------------------------------------------------- */
$public_header = null;

/* Prefer PRIVATE_PATH if your initialize.php defines it */
if (defined('PRIVATE_PATH') && is_string(PRIVATE_PATH) && PRIVATE_PATH !== '') {
  $cand = rtrim(PRIVATE_PATH, '/\\') . '/shared/public_header.php';
  if (is_file($cand)) $public_header = $cand;
}

/* Fall back to APP_ROOT if present */
if ($public_header === null && defined('APP_ROOT') && is_string(APP_ROOT) && APP_ROOT !== '') {
  $cand = rtrim(APP_ROOT, '/\\') . '/private/shared/public_header.php';
  if (is_file($cand)) $public_header = $cand;
}

/* Last resort: relative to this file (in case you keep public_header.php here too) */
if ($public_header === null) {
  $cand = __DIR__ . '/public_header.php';
  if (is_file($cand)) $public_header = $cand;
}

/* ---------------------------------------------------------
   Render
--------------------------------------------------------- */
if ($public_header !== null) {
  require_once $public_header;
  return;
}

/* Ultimate fail-safe: never fatal on public */
http_response_code(200);
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo h($page_title); ?></title>
  <meta name="description" content="<?php echo h($page_desc); ?>">
</head>
<body class="contributors">
<main id="main">
<?php
