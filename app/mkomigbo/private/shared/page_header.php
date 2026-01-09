<?php
declare(strict_types=1);

/**
 * /private/shared/page_header.php
 * Generic public content-page wrapper (articles, single views, etc.)
 *
 * Contract:
 * - Delegates to public_header.php
 * - Uses brand name for display defaults
 * - Does NOT alter filesystem identifiers (mkomigbo remains identifier)
 */

if (!isset($active_nav) || !is_string($active_nav)) {
  $active_nav = '';
}

/* Brand display name default */
$brand_name = (isset($brand_name) && is_string($brand_name) && trim($brand_name) !== '')
  ? trim($brand_name)
  : 'Mkomi Igbo';

/* Page title default */
if (!isset($page_title) || !is_string($page_title) || trim($page_title) === '') {
  $page_title = $brand_name;
}

require_once __DIR__ . '/public_header.php';
