<?php
declare(strict_types=1);

/**
 * /private/shared/contributor_footer.php
 * Wrapper that delegates to public_footer.php (which closes </main> etc).
 */

$public_footer = __DIR__ . '/public_footer.php';

if (is_file($public_footer)) {
  require_once $public_footer;
  return;
}

/* Last-resort fallback: do not fatal on public pages */
if (!empty($GLOBALS['mk__main_open'])) {
  echo "</main>";
}
echo "</body></html>";
