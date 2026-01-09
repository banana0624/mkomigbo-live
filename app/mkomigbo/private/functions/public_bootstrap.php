<?php
declare(strict_types=1);

/**
 * /private/functions/public_bootstrap.php
 *
 * DRY helpers used by public pages (and optionally staff pages):
 * - Ensures h(), url_for(), mk_require_shared() exist (safe fallbacks)
 * - Applies cache headers (GET only) if mk_public_cache_headers exists
 * - Optionally asserts theme helpers exist
 * - Normalizes/injects $extra_css and $extra_js safely
 * - Sets common header variables ($active_nav, $page_title, $page_desc, $page_accent)
 *
 * Usage:
 *   mk_public_bootstrap([
 *     'active_nav' => 'subjects',
 *     'title'      => 'Subjects • Mkomigbo',
 *     'desc'       => 'Explore…',
 *     'accent'     => null, // if null and theme helpers exist, uses pf__accent_for(active_nav)
 *     'cache'      => 300,
 *     'need_theme' => true,
 *     'css'        => ['/lib/css/ui.css', '/lib/css/subjects-public.css'],
 *     'js'         => [],
 *   ]);
 */

if (!function_exists('mk_public_bootstrap')) {

  function mk_public_bootstrap(array $opts = []): void
  {
    /* ---------------------------
     * 1) Minimal safe fallbacks
     * --------------------------- */

    if (!function_exists('h')) {
      function h(string $value): string
      {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
      }
    }

    if (!function_exists('mk_require_shared')) {
      /**
       * Fallback include from /private/shared
       * initialize.php should normally define mk_require_shared().
       */
      function mk_require_shared(string $file): void
      {
        $path = dirname(__DIR__) . '/shared/' . ltrim($file, '/'); // /private/functions -> /private/shared
        if (!is_file($path)) {
          header('Content-Type: text/plain; charset=utf-8');
          echo "Shared include not found\nExpected: {$path}\n";
          exit;
        }
        require $path;
      }
    }

    if (!function_exists('url_for')) {
      /**
       * Last-resort fallback: output absolute-from-root.
       * Prefer initialize.php url_for().
       */
      function url_for(string $path): string
      {
        if ($path === '') return '/';
        return ($path[0] === '/') ? $path : ('/' . $path);
      }
    }

    /* ---------------------------
     * 2) Cache headers (GET only)
     * --------------------------- */
    $cache = isset($opts['cache']) ? (int)$opts['cache'] : 0;
    if ($cache > 0 && function_exists('mk_public_cache_headers')) {
      mk_public_cache_headers($cache);
    }

    /* ---------------------------
     * 3) Theme helper assertion
     * --------------------------- */
    $need_theme = isset($opts['need_theme']) ? (bool)$opts['need_theme'] : false;
    if ($need_theme) {
      $missing = [];
      foreach (['pf__accent_for', 'pf__subject_logo_url', 'pf__seg'] as $fn) {
        if (!function_exists($fn)) $missing[] = $fn;
      }
      if ($missing) {
        throw new RuntimeException(
          'Theme helpers missing: ' . implode(', ', $missing) .
          '. Expected private/functions/theme_functions.php to define them.'
        );
      }
    }

    /* ---------------------------
     * 4) Common header variables
     * --------------------------- */
    if (isset($opts['active_nav']) && is_string($opts['active_nav']) && trim($opts['active_nav']) !== '') {
      $GLOBALS['active_nav'] = trim($opts['active_nav']);
    }

    if (isset($opts['title']) && is_string($opts['title']) && trim($opts['title']) !== '') {
      $GLOBALS['page_title'] = trim($opts['title']);
    }

    if (isset($opts['desc']) && is_string($opts['desc']) && trim($opts['desc']) !== '') {
      $GLOBALS['page_desc'] = trim($opts['desc']);
    }

    // Accent: explicit wins, otherwise infer from active_nav if theme exists
    if (array_key_exists('accent', $opts) && is_string($opts['accent']) && trim($opts['accent']) !== '') {
      $GLOBALS['page_accent'] = trim($opts['accent']);
    } else {
      if (!isset($GLOBALS['page_accent']) || !is_string($GLOBALS['page_accent']) || trim($GLOBALS['page_accent']) === '') {
        if (function_exists('pf__accent_for') && isset($GLOBALS['active_nav']) && is_string($GLOBALS['active_nav'])) {
          $GLOBALS['page_accent'] = (string)pf__accent_for($GLOBALS['active_nav']);
        }
      }
    }

    /* ---------------------------
     * 5) Normalize/inject extra_css
     * --------------------------- */
    if (!isset($GLOBALS['extra_css']) || !is_array($GLOBALS['extra_css'])) {
      $GLOBALS['extra_css'] = [];
    }

    $need_css = [];
    if (isset($opts['css']) && is_array($opts['css'])) {
      $need_css = $opts['css'];
    }

    // Build set of existing normalized paths
    $seen = [];
    foreach ($GLOBALS['extra_css'] as $x) {
      if (!is_string($x)) continue;
      $t = trim($x);
      if ($t === '') continue;
      if ($t[0] !== '/') $t = '/' . $t;
      $seen[$t] = true;
    }

    // Inject required CSS (absolute-from-root)
    foreach ($need_css as $x) {
      if (!is_string($x)) continue;
      $t = trim($x);
      if ($t === '') continue;
      if ($t[0] !== '/') $t = '/' . $t;
      if (!isset($seen[$t])) {
        $GLOBALS['extra_css'][] = $t;
        $seen[$t] = true;
      }
    }

    /* ---------------------------
     * 6) Normalize/inject extra_js
     * --------------------------- */
    if (!isset($GLOBALS['extra_js']) || !is_array($GLOBALS['extra_js'])) {
      $GLOBALS['extra_js'] = [];
    }

    if (isset($opts['js']) && is_array($opts['js'])) {
      $seen_js = [];
      foreach ($GLOBALS['extra_js'] as $x) {
        if (!is_string($x)) continue;
        $t = trim($x);
        if ($t === '') continue;
        if ($t[0] !== '/') $t = '/' . $t;
        $seen_js[$t] = true;
      }
      foreach ($opts['js'] as $x) {
        if (!is_string($x)) continue;
        $t = trim($x);
        if ($t === '') continue;
        if ($t[0] !== '/') $t = '/' . $t;
        if (!isset($seen_js[$t])) {
          $GLOBALS['extra_js'][] = $t;
          $seen_js[$t] = true;
        }
      }
    }
  }
}
