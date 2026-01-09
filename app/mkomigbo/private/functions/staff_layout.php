<?php
declare(strict_types=1);

/**
 * /private/functions/staff_layout.php
 * Wrapper helpers around /private/shared/staff_header.php + staff_footer.php
 *
 * Usage:
 *   mk_staff_header([
 *     'title' => 'Manage Subjects • Staff',
 *     'desc' => '...',
 *     'active_nav' => 'staff',
 *     'extra_css' => ['/lib/css/some-page.css'],
 *     'body_class' => 'staff staff-subjects',
 *     'breadcrumbs' => [
 *        ['label' => 'Staff', 'href' => '/staff/'],
 *        ['label' => 'Subjects', 'href' => '/staff/subjects/'],
 *     ],
 *   ]);
 *   ...
 *   mk_staff_footer();
 */

/**
 * Render staff header.
 * Accepts:
 * - title (string)
 * - desc (string)
 * - active_nav (string)
 * - extra_css (array|string)
 * - body_class (string)
 * - breadcrumbs (array of ['label'=>string,'href'=>string|null])
 */
function mk_staff_header(array $opts = []): void {
  // Map to include-vars used by /private/shared/staff_header.php
  if (isset($opts['active_nav']) && is_string($opts['active_nav']) && trim($opts['active_nav']) !== '') {
    $active_nav = trim($opts['active_nav']);
  } else {
    $active_nav = 'staff';
  }

  $page_title = (isset($opts['title']) && is_string($opts['title']) && trim($opts['title']) !== '')
    ? trim($opts['title'])
    : 'Staff • Mkomigbo';

  $page_desc = (isset($opts['desc']) && is_string($opts['desc']) && trim($opts['desc']) !== '')
    ? trim($opts['desc'])
    : 'Staff dashboard and management tools.';

  $extra_css = $opts['extra_css'] ?? [];
  // Optional UI hooks (if your public_header.php supports them)
  $body_class  = (isset($opts['body_class']) && is_string($opts['body_class'])) ? trim($opts['body_class']) : '';
  $breadcrumbs = (isset($opts['breadcrumbs']) && is_array($opts['breadcrumbs'])) ? $opts['breadcrumbs'] : [];

  // Expose optional vars for headers that might use them
  $GLOBALS['mk_body_class']  = $body_class;
  $GLOBALS['mk_breadcrumbs'] = $breadcrumbs;

  $staff_header = defined('PRIVATE_PATH')
    ? (rtrim(PRIVATE_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'staff_header.php')
    : (dirname(__DIR__) . '/shared/staff_header.php');

  if (!is_file($staff_header)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "staff_header.php not found\nExpected: {$staff_header}\n";
    exit;
  }

  require $staff_header;
}

/**
 * Render staff footer.
 */
function mk_staff_footer(): void {
  $staff_footer = defined('PRIVATE_PATH')
    ? (rtrim(PRIVATE_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'staff_footer.php')
    : (dirname(__DIR__) . '/shared/staff_footer.php');

  if (!is_file($staff_footer)) {
    // Safe last resort: avoid stray output / warnings.
    echo "</body></html>";
    return;
  }

  require $staff_footer;
}

/**
 * Back-compat aliases (in case older pages call these names)
 */
if (!function_exists('staff_render_header')) {
  function staff_render_header(string $title = 'Staff • Mkomigbo', string $active = 'staff', $extra_css = []): void {
    mk_staff_header([
      'title' => $title,
      'active_nav' => $active,
      'extra_css' => $extra_css,
    ]);
  }
}

if (!function_exists('staff_render_footer')) {
  function staff_render_footer(): void {
    mk_staff_footer();
  }
}
