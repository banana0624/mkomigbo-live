<?php
declare(strict_types=1);

/**
 * /private/shared/staff_footer.php
 *
 * Responsibilities:
 * - Close <main> if staff_header opened it (mk__main_open contract).
 * - Delegate to /private/shared/public_footer.php for site footer + closing body/html.
 * - Provide a safe fallback if public_footer.php is missing.
 */

if (empty($GLOBALS['mk__main_open'])) {
  // Header did not open main (unexpected for staff pages),
  // but we still proceed to footer.
} else {
  echo "</main>\n";
  $GLOBALS['mk__main_open'] = false;
}

$public_footer = __DIR__ . '/public_footer.php';

if (is_file($public_footer)) {
  // Optional: let public footer know we're in staff context
  $footer_variant = $footer_variant ?? 'Staff';
  require $public_footer;
  return;
}

/* Safe fallback */
echo "</body></html>";
