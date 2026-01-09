<?php
declare(strict_types=1);

/**
 * /public/subjects/view.php
 * Canonical subjects router:
 *   /subjects/                 -> /public/subjects/index.php
 *   /subjects/{subject-slug}/  -> /public/subjects/subject.php
 *   /subjects/{subject}/{page}/-> /public/subjects/page.php
 *
 * IMPORTANT:
 * - Do NOT include initialize.php here.
 * - Do NOT scan for initialize.php here.
 * - Always go through /public/_init.php once.
 */

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

require_once __DIR__ . '/../_init.php';

/* ---------------------------------------------------------
   Compat: str_starts_with for older PHP
--------------------------------------------------------- */
if (!function_exists('mk_starts_with')) {
  function mk_starts_with(string $haystack, string $needle): bool {
    return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
  }
}

/* ---------------------------------------------------------
   404 helper (never 500)
--------------------------------------------------------- */
if (!function_exists('mk_subjects_404')) {
  function mk_subjects_404(): void
  {
    http_response_code(404);

    try {
      if (function_exists('mk_require_shared')) {
        $GLOBALS['page_title'] = 'Not Found • Mkomi Igbo';
        $GLOBALS['active_nav'] = 'subjects';
        $GLOBALS['nav_active'] = 'subjects';

        if (defined('PRIVATE_PATH') && is_file(PRIVATE_PATH . '/shared/subjects_header.php')) {
          mk_require_shared('subjects_header.php');
        } elseif (defined('PRIVATE_PATH') && is_file(PRIVATE_PATH . '/shared/public_header.php')) {
          mk_require_shared('public_header.php');
        }

        echo '<div class="container" style="padding:24px 0;">';
        echo '<section class="hero">';
        echo '  <div class="hero-bar"></div>';
        echo '  <div class="hero-inner">';
        echo '    <h1>Page not found</h1>';
        echo '    <p class="muted" style="margin:6px 0 0;">The page you requested does not exist.</p>';
        echo '    <div class="actions" style="margin-top:14px;">';
        echo '      <a class="btn" href="/subjects/">← Back to Subjects</a>';
        echo '      <a class="btn" href="/">Home</a>';
        echo '    </div>';
        echo '  </div>';
        echo '</section>';
        echo '</div>';

        if (defined('PRIVATE_PATH') && is_file(PRIVATE_PATH . '/shared/subjects_footer.php')) {
          mk_require_shared('subjects_footer.php');
        } elseif (defined('PRIVATE_PATH') && is_file(PRIVATE_PATH . '/shared/public_footer.php')) {
          mk_require_shared('public_footer.php');
        }
        return;
      }
    } catch (Throwable $e) {
      // swallow; final fallback below
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo "404 - Page not found";
  }
}

/* ---------------------------------------------------------
   Parse route
--------------------------------------------------------- */
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($uriPath) || $uriPath === '') $uriPath = '/';

$uriPath = rtrim($uriPath, '/') . '/';

/**
 * Supported:
 * - /subjects/...
 * - /public/subjects/... (if someone hits physical path)
 */
$rel = '';
if (function_exists('str_starts_with')) {
  if (str_starts_with($uriPath, '/public/subjects/')) {
    $rel = substr($uriPath, strlen('/public/subjects/'));
  } elseif (str_starts_with($uriPath, '/subjects/')) {
    $rel = substr($uriPath, strlen('/subjects/'));
  } else {
    mk_subjects_404();
    exit;
  }
} else {
  if (mk_starts_with($uriPath, '/public/subjects/')) {
    $rel = substr($uriPath, strlen('/public/subjects/'));
  } elseif (mk_starts_with($uriPath, '/subjects/')) {
    $rel = substr($uriPath, strlen('/subjects/'));
  } else {
    mk_subjects_404();
    exit;
  }
}

$rel = trim($rel, '/');
$parts = ($rel === '') ? [] : explode('/', $rel);

/* /subjects/ -> index */
if (count($parts) === 0) {
  require __DIR__ . '/index.php';
  exit;
}

$subjectSlug = strtolower((string)($parts[0] ?? ''));
$pageSlug    = strtolower((string)($parts[1] ?? ''));

/* slug hardening */
$slugOk = static function(string $s): bool {
  return (bool)preg_match('/^[a-z0-9][a-z0-9_-]{0,190}$/', $s);
};

if (!$slugOk($subjectSlug) || ($pageSlug !== '' && !$slugOk($pageSlug))) {
  mk_subjects_404();
  exit;
}

/* subject landing */
if ($pageSlug === '') {
  $_GET['slug'] = $subjectSlug;
  require __DIR__ . '/subject.php';
  exit;
}

/* subject page (IMPORTANT: page.php expects subject + slug) */
$_GET['subject'] = $subjectSlug;
$_GET['slug']    = $pageSlug;
require __DIR__ . '/page.php';
exit;
