<?php
declare(strict_types=1);

/**
 * /app/mkomigbo/public/404.php
 * Central PHP 404 page for app/router.
 *
 * Why this exists:
 * Your router/dispatcher tries to require APP_ROOT . '/public/404.php'.
 * Your server-level ErrorDocument is /404.shtml, but your app expects a PHP 404 too.
 *
 * This file is safe to include via require.
 */

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (!headers_sent()) {
  http_response_code(404);
  header('Content-Type: text/html; charset=utf-8');
  header('X-Robots-Tag: noindex, nofollow', true);
}

/* Try to use your public header/footer if present (keeps branding consistent) */
$header = defined('APP_ROOT') ? (APP_ROOT . '/private/shared/public_header.php') : null;
$footer = defined('APP_ROOT') ? (APP_ROOT . '/private/shared/public_footer.php') : null;

if ($header && is_file($header)) {
  $page_title = 'Page Not Found';
  $active_nav = '';
  require $header;
} else {
  echo "<!doctype html><html><head><meta charset='utf-8'><title>Page Not Found</title></head><body>";
}

?>
<div class="container" style="padding:30px 0;">
  <div class="card">
    <div class="card__body">
      <h1 style="margin:0 0 10px;">Page Not Found</h1>
      <p class="muted" style="margin:0 0 14px;">
        The page you requested does not exist or has been moved.
      </p>
      <div class="row row--gap">
        <a class="btn btn--primary" href="<?php echo function_exists('url_for') ? h(url_for('/')) : '/'; ?>">Go Home</a>
        <a class="btn btn--ghost" href="javascript:history.back()">Go Back</a>
      </div>
    </div>
  </div>
</div>
<?php

if ($footer && is_file($footer)) {
  require $footer;
} else {
  echo "</body></html>";
}
