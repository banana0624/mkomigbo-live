<?php
declare(strict_types=1);

/**
 * /private/functions/helpers.php
 *
 * Shared small helpers used across Mkomigbo.
 *
 * Rules:
 * - No output, no redirects, no header() calls.
 * - Safe to include multiple times (idempotent).
 * - Keep dependencies minimal; functions should be pure where possible.
 */

if (defined('MK_HELPERS_LOADED')) {
  return;
}
define('MK_HELPERS_LOADED', true);

/* ---------------------------------------------------------
 * Environment / feature flags
 * --------------------------------------------------------- */

/**
 * Returns true if MK_DEBUG is enabled (constant or env).
 */
function mk_is_debug(): bool {
  if (defined('MK_DEBUG')) return (bool)MK_DEBUG;
  $v = getenv('MK_DEBUG');
  if ($v === false) return false;
  $v = strtolower(trim((string)$v));
  return in_array($v, ['1', 'true', 'yes', 'on'], true);
}

/* ---------------------------------------------------------
 * String helpers
 * --------------------------------------------------------- */

/**
 * Safe trim that normalizes whitespace.
 */
function mk_trim(string $s): string {
  $s = trim($s);
  // Normalize any weird whitespace to a single space
  $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
  return $s;
}

/**
 * Basic, predictable UTF-8 slugify.
 * - Keeps letters/numbers from any language (Unicode)
 * - Converts runs of non-letter/number into '-'
 * - Lowercases (mb_strtolower)
 * - Ensures non-empty fallback
 */
function mk_slugify(string $s, string $fallback = 'item'): string {
  $s = mk_trim($s);
  if ($s === '') return $fallback;

  // Lowercase safely
  if (function_exists('mb_strtolower')) {
    $s = mb_strtolower($s, 'UTF-8');
  } else {
    $s = strtolower($s);
  }

  // Replace any run of non-letter/number with dash
  $s = preg_replace('/[^\p{L}\p{N}]+/u', '-', $s) ?? $s;
  $s = trim($s, "- \t\n\r\0\x0B");

  // Safety: collapse multiple dashes
  $s = preg_replace('/-+/u', '-', $s) ?? $s;

  // Limit length to avoid extremely long slugs
  if (strlen($s) > 120) {
    $s = substr($s, 0, 120);
    $s = rtrim($s, '-');
  }

  return $s !== '' ? $s : $fallback;
}

/**
 * Tight HTML stripping helper (for building search excerpts or safe summaries).
 */
function mk_plaintext(string $s): string {
  $s = strip_tags($s);
  $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  return mk_trim($s);
}

/* ---------------------------------------------------------
 * Schema-tolerant helpers
 * --------------------------------------------------------- */

/**
 * Returns true if a table exists in the current DB.
 */
function mk_table_exists(PDO $db, string $table): bool {
  $table = trim($table);
  if ($table === '') return false;

  $stmt = $db->prepare("
    SELECT 1
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = :t
    LIMIT 1
  ");
  $stmt->execute([':t' => $table]);
  return (bool)$stmt->fetchColumn();
}

/**
 * Returns true if a column exists on a table in the current DB.
 */
function mk_column_exists(PDO $db, string $table, string $column): bool {
  $table = trim($table);
  $column = trim($column);
  if ($table === '' || $column === '') return false;

  $stmt = $db->prepare("
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = :t
      AND COLUMN_NAME = :c
    LIMIT 1
  ");
  $stmt->execute([':t' => $table, ':c' => $column]);
  return (bool)$stmt->fetchColumn();
}

/* ---------------------------------------------------------
 * Contributor slug helpers (your requested logic, hardened)
 * --------------------------------------------------------- */

/**
 * Checks whether a contributor slug exists.
 * - Uses prepared statements
 * - Supports excluding an id (for updates)
 * - Guards if contributors/slug column is missing
 */
function mk_contributor_slug_exists(PDO $db, string $slug, ?int $excludeId = null): bool {
  $slug = trim($slug);
  if ($slug === '') return false;

  // Schema guard (prevents fatal errors during migrations)
  if (!mk_table_exists($db, 'contributors') || !mk_column_exists($db, 'contributors', 'slug')) {
    return false;
  }

  if ($excludeId !== null && $excludeId > 0) {
    $stmt = $db->prepare("SELECT 1 FROM contributors WHERE slug = :slug AND id <> :id LIMIT 1");
    $stmt->execute([':slug' => $slug, ':id' => $excludeId]);
  } else {
    $stmt = $db->prepare("SELECT 1 FROM contributors WHERE slug = :slug LIMIT 1");
    $stmt->execute([':slug' => $slug]);
  }

  return (bool)$stmt->fetchColumn();
}

/**
 * Generates a unique contributor slug.
 *
 * Behavior:
 * - Start with $baseSlug
 * - If taken: append "-2", "-3", ...
 * - Safety valve: if too many collisions, append short random suffix
 */
function mk_unique_contributor_slug(PDO $db, string $baseSlug, ?int $excludeId = null): string {
  $baseSlug = mk_slugify($baseSlug, 'contributor');

  $slug = $baseSlug;
  $i = 2;

  while (mk_contributor_slug_exists($db, $slug, $excludeId)) {
    $slug = $baseSlug . '-' . $i;
    $i++;

    if ($i > 9999) { // safety valve
      // random_bytes may throw; handle safely
      try {
        $slug = $baseSlug . '-' . bin2hex(random_bytes(3)); // 6 hex chars
      } catch (Throwable $e) {
        $slug = $baseSlug . '-' . (string)mt_rand(100000, 999999);
      }
      break;
    }
  }

  return $slug;
}

/**
 * Convenience: derive a contributor slug from a display name and make it unique.
 */
function mk_contributor_slug_from_name(PDO $db, string $displayName, ?int $excludeId = null): string {
  $base = mk_slugify($displayName, 'contributor');
  return mk_unique_contributor_slug($db, $base, $excludeId);
}

/* ---------------------------------------------------------
 * Small request helpers (safe, no side effects)
 * --------------------------------------------------------- */

function mk_is_post(): bool {
  return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function mk_is_get(): bool {
  return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
}

/**
 * Safely fetch an int from GET/POST arrays.
 */
function mk_int_param(array $src, string $key, ?int $default = null): ?int {
  if (!array_key_exists($key, $src)) return $default;
  $v = filter_var($src[$key], FILTER_VALIDATE_INT);
  return ($v !== false) ? (int)$v : $default;
}

/**
 * Safely fetch a trimmed string from GET/POST arrays.
 */
function mk_str_param(array $src, string $key, string $default = ''): string {
  if (!array_key_exists($key, $src)) return $default;
  $v = (string)$src[$key];
  $v = mk_trim($v);
  return $v !== '' ? $v : $default;
}

/* ---------------------------------------------------------
 * Optional: HTML sanitizer for contributor bios
 * --------------------------------------------------------- */

/**
 * Basic safe bio sanitizer:
 * - Removes script/style/iframe/object embeds
 * - Allows a modest set of tags
 * - Strips event handler attributes
 *
 * Note: This is not a full HTML purifier. If you later want richer HTML,
 * integrate a proper sanitizer library. For now, this is a safe baseline.
 */
function mk_sanitize_bio_html(string $html): string {
  $html = trim($html);
  if ($html === '') return '';

  // Remove dangerous blocks outright
  $html = preg_replace('~<(script|style|iframe|object|embed|link|meta)\b[^>]*>.*?</\1>~is', '', $html) ?? $html;
  $html = preg_replace('~<(script|style|iframe|object|embed|link|meta)\b[^>]*/?>~is', '', $html) ?? $html;

  // Allow a conservative set of tags
  $allowed = '<p><br><b><strong><i><em><u><ul><ol><li><blockquote><code><pre><a><h3><h4><h5><h6>';
  $html = strip_tags($html, $allowed);

  // Remove inline event handlers like onclick=, onload= etc.
  $html = preg_replace('/\son\w+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $html) ?? $html;

  // Neutralize javascript: URLs
  $html = preg_replace('/href\s*=\s*("|\')\s*javascript:[^"\']*\1/i', 'href="#"', $html) ?? $html;

  return trim($html);
}
