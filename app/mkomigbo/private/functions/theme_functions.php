<?php
declare(strict_types=1);

/**
 * /private/functions/theme_functions.php
 * Theme + UI helper functions (DRY).
 *
 * Expected available from initialize.php:
 * - url_for()
 * - PUBLIC_PATH constant
 * - (optional) MK_IS_DEV constant
 *
 * Notes:
 * - pf__seg() MUST remain a URL-encoding helper (string -> string).
 *   Do NOT repurpose it for URI segment extraction; use pf__uri_seg() instead.
 */

/* ---------------------------------------------------------
 * URL helpers
 * --------------------------------------------------------- */
if (!function_exists('pf__seg')) {
  /**
   * URL-encode a single path segment safely.
   * Example: pf__seg('Igbo & History') => 'Igbo%20%26%20History'
   */
  function pf__seg(string $s): string {
    return rawurlencode($s);
  }
}

if (!function_exists('pf__uri_seg')) {
  /**
   * Return the Nth URI path segment (0-based).
   * Example: /subjects/history => seg0="subjects", seg1="history"
   *
   * This is intentionally NOT named pf__seg() to avoid breaking existing code.
   */
  function pf__uri_seg(int $index, string $default = ''): string {
    $uri  = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH);
    $path = is_string($path) ? $path : '/';

    $parts = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
    return $parts[$index] ?? $default;
  }
}

if (!function_exists('pf__slug_key')) {
  /**
   * Normalize a string for filename keys (logos etc.).
   * Routing slugs should remain DB-defined; this is for file lookup only.
   */
  function pf__slug_key(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9\-]+/', '-', $s) ?? $s;
    $s = preg_replace('/\-+/', '-', $s) ?? $s;
    return trim($s, '-');
  }
}

/* ---------------------------------------------------------
 * Public caching (DRY)
 * --------------------------------------------------------- */
if (!function_exists('mk_public_cache_headers')) {
  /**
   * Emit conservative public cache headers for GET requests.
   * No-op for CLI, when headers already sent, or non-GET.
   */
  function mk_public_cache_headers(int $maxAge = 300, int $staleWhileRevalidate = 60): void {
    if (PHP_SAPI === 'cli') return;
    if (headers_sent()) return;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') return;

    header('Cache-Control: public, max-age=' . $maxAge . ', stale-while-revalidate=' . $staleWhileRevalidate);
    header('Pragma: public');
    header('Vary: Accept-Encoding');
  }
}

/* ---------------------------------------------------------
 * Accent colors
 * --------------------------------------------------------- */
if (!function_exists('pf__accent_for')) {
  /**
   * Return a hex accent color for a given subject slug.
   * This is used directly by UI templates; keep return type stable.
   */
  function pf__accent_for(string $slug): string {
    $map = [
      'history'       => '#2b6cb0',
      'slavery'       => '#7b341e',
      'people'        => '#1f7a8c',
      'persons'       => '#1f7a8c',
      'culture'       => '#2f855a',
      'religion'      => '#805ad5',
      'spirituality'  => '#805ad5',
      'tradition'     => '#805ad5',
      'language1'     => '#6b46c1',
      'language2'     => '#6b46c1',
      'struggle'      => '#c05621',
      'struggles'     => '#c05621',
      'biafra'        => '#b83280',
      'nigeria'       => '#2f855a',
      'resistance'    => '#c53030',
      'africa'        => '#276749',
      'uk'            => '#2c5282',
      'europe'        => '#2b6cb0',
      'arabs'         => '#8a6d3b',
      'about'         => '#4a5568',
    ];

    $k = strtolower(trim($slug));
    return $map[$k] ?? '#2563eb';
  }
}

/* ---------------------------------------------------------
 * Logos
 * --------------------------------------------------------- */
if (!function_exists('pf__subject_logo_url')) {
  /**
   * Returns a cache-busted URL for a subject logo if present.
   * Looks in: /public/lib/images/subjects/{slug_key}.(svg|png|webp|jpg|jpeg)
   *
   * Returns null if not found or slug empty.
   */
  function pf__subject_logo_url(string $slug): ?string {
    $slug = trim($slug);
    if ($slug === '') return null;

    $key = pf__slug_key($slug);

    // Filesystem + web roots
    $baseDir = rtrim((string)PUBLIC_PATH, '/') . '/lib/images/subjects';
    $baseWeb = '/lib/images/subjects';

    $exts = ['svg', 'png', 'webp', 'jpg', 'jpeg'];
    foreach ($exts as $ext) {
      $file = $baseDir . '/' . $key . '.' . $ext;
      if (is_file($file)) {
        $url = function_exists('url_for')
          ? url_for($baseWeb . '/' . $key . '.' . $ext)
          : ($baseWeb . '/' . $key . '.' . $ext);

        $v = @filemtime($file);
        if ($v) {
          $url .= '?v=' . rawurlencode((string)$v);
        }
        return $url;
      }
    }

    return null;
  }
}

/* ---------------------------------------------------------
 * Excerpt helper (plain text)
 * --------------------------------------------------------- */
if (!function_exists('pf__excerpt')) {
  /**
   * Create a plain-text excerpt from HTML.
   */
  function pf__excerpt(string $html, int $max = 170): string {
    $text = trim(strip_tags($html));
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    if ($text === '') return '';

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
      return (mb_strlen($text, 'UTF-8') > $max)
        ? rtrim(mb_substr($text, 0, $max, 'UTF-8')) . '…'
        : $text;
    }

    return (strlen($text) > $max) ? rtrim(substr($text, 0, $max)) . '…' : $text;
  }
}
