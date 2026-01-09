<?php
declare(strict_types=1);

/**
 * /private/functions/seo_helpers.php
 * Safe loader + wrappers for registry SEO runtime/templates.
 *
 * Goals:
 * - Load /private/registry/seo_runtime.php only when needed
 * - Prevent redeclare fatals by loading once
 * - Provide simple helpers for headers/pages
 */

if (defined('MK_SEO_HELPERS_LOADED')) {
  return;
}
define('MK_SEO_HELPERS_LOADED', true);

/** Load SEO runtime (registry-based) exactly once */
function mk_require_seo_runtime(): void
{
  if (defined('MK_SEO_RUNTIME_LOADED')) return;

  if (!defined('PRIVATE_PATH')) return;

  $rt = PRIVATE_PATH . '/registry/seo_runtime.php';
  if (!is_file($rt)) return;

  require_once $rt;

  // Mark loaded AFTER include (works even if file has no guard)
  if (!defined('MK_SEO_RUNTIME_LOADED')) define('MK_SEO_RUNTIME_LOADED', true);
}

/**
 * Build SEO array for a subject, using:
 * - DB subject fields if present
 * - registry fallback fields if missing
 */
function mk_seo_for_subject_slug(string $slug, array $dbSubjectRow = []): array
{
  mk_require_seo_runtime();

  // Pull registry fallback if available
  $fallback = function_exists('mk_subject_meta_fallback')
    ? mk_subject_meta_fallback($slug)
    : ['meta_description' => '', 'meta_keywords' => '', 'icon' => ''];

  $subject = [
    'name'             => (string)($dbSubjectRow['name'] ?? $slug),
    'meta_description' => (string)($dbSubjectRow['meta_description'] ?? $fallback['meta_description'] ?? ''),
    'meta_keywords'    => (string)($dbSubjectRow['meta_keywords'] ?? $fallback['meta_keywords'] ?? ''),
    'icon'             => (string)($dbSubjectRow['icon'] ?? $fallback['icon'] ?? ''),
    'slug'             => $slug,
  ];

  if (function_exists('seo_for_subject')) {
    return seo_for_subject($subject);
  }

  // absolute fallback (no runtime)
  return [
    'title'       => $subject['name'] . ' • Mkomigbo',
    'description' => $subject['meta_description'],
    'keywords'    => $subject['meta_keywords'],
  ];
}

/** Render <title> + meta tags (simple, safe) */
function mk_seo_echo_tags(array $seo): void
{
  $title = (string)($seo['title'] ?? '');
  $desc  = (string)($seo['description'] ?? '');
  $keys  = (string)($seo['keywords'] ?? '');

  if ($title !== '') {
    echo "<title>" . h($title) . "</title>\n";
  }
  if ($desc !== '') {
    echo '<meta name="description" content="' . h($desc) . '">' . "\n";
  }
  if ($keys !== '') {
    echo '<meta name="keywords" content="' . h($keys) . '">' . "\n";
  }
}
