<?php
declare(strict_types=1);

/**
 * /private/functions/contain.php
 * Dependency container / include hub (no output, no redirects, no queries).
 */

if (defined('MK_CONTAIN_LOADED')) {
  return;
}
define('MK_CONTAIN_LOADED', true);

/* ---------------------------------------------------------
 * Required constants (normally defined by initialize.php)
 * --------------------------------------------------------- */
if (!defined('PRIVATE_PATH')) {
  $p = realpath(dirname(__DIR__));
  define('PRIVATE_PATH', $p !== false ? $p : dirname(__DIR__));
}
if (!defined('FUNCTIONS_PATH')) define('FUNCTIONS_PATH', PRIVATE_PATH . '/functions');
if (!defined('ASSETS_PATH'))    define('ASSETS_PATH', PRIVATE_PATH . '/assets');
if (!defined('SHARED_PATH'))    define('SHARED_PATH', PRIVATE_PATH . '/shared');

/* ---------------------------------------------------------
 * Public bootstrap (optional)
 * --------------------------------------------------------- */
$public_bootstrap = FUNCTIONS_PATH . '/public_bootstrap.php';
if (is_file($public_bootstrap)) {
  require_once $public_bootstrap;
}

/* ---------------------------------------------------------
 * Core helpers (project helpers should load early)
 * --------------------------------------------------------- */
$helpers_file = FUNCTIONS_PATH . '/helpers.php';
if (is_file($helpers_file)) {
  require_once $helpers_file;
}

// General helpers: h(), u(), url_for(), redirect_to(), request helpers, etc.
$helpers = PRIVATE_PATH . '/common/helper_functions.php';
if (is_file($helpers)) {
  require_once $helpers;
}

// Validation helpers
$validation = ASSETS_PATH . '/validation_functions.php';
if (is_file($validation)) {
  require_once $validation;
}

// Misc utilities (optional)
$other_utils = ASSETS_PATH . '/other_utilities.php';
if (is_file($other_utils)) {
  require_once $other_utils;
}

require_once APP_ROOT . '/private/functions/helpers.php';
require_once APP_ROOT . '/private/functions/auth.php';

/* ---------------------------------------------------------
 * Database + schema helpers (load only if needed)
 * --------------------------------------------------------- */
$db_file = ASSETS_PATH . '/database.php';
if (!function_exists('db') && is_file($db_file)) {
  require_once $db_file;
}

$schema = ASSETS_PATH . '/config.php';
if (
  (!function_exists('mk_has_column') || !function_exists('mk_has_table') || !function_exists('mk_table_columns'))
  && is_file($schema)
) {
  require_once $schema;
}

/* ---------------------------------------------------------
 * AuthN + AuthZ helpers
 * --------------------------------------------------------- */
$auth = ASSETS_PATH . '/auth_functions.php';
if (is_file($auth)) {
  require_once $auth;
}

$authz = FUNCTIONS_PATH . '/authz.php';
if (is_file($authz)) {
  require_once $authz;
}

/* ---------------------------------------------------------
 * Domain functions
 * --------------------------------------------------------- */
$subject_functions = ASSETS_PATH . '/subject_functions.php';
if (is_file($subject_functions)) {
  require_once $subject_functions;
}

$page_functions = ASSETS_PATH . '/page_functions.php';
if (is_file($page_functions)) {
  require_once $page_functions;
}

$admin_functions = ASSETS_PATH . '/admin_functions.php';
if (is_file($admin_functions)) {
  require_once $admin_functions;
}

$image_functions = ASSETS_PATH . '/image_functions.php';
if (is_file($image_functions)) {
  require_once $image_functions;
}

/* ---------------------------------------------------------
 * Theme helpers
 * --------------------------------------------------------- */
$theme = FUNCTIONS_PATH . '/theme_functions.php';
if (is_file($theme)) {
  require_once $theme;
}

/* ---------------------------------------------------------
 * Registry + SEO helpers
 * --------------------------------------------------------- */
$subjects_registry_helpers = FUNCTIONS_PATH . '/subjects_registry_helpers.php';
if (is_file($subjects_registry_helpers)) {
  require_once $subjects_registry_helpers;
}

$seo_helpers = FUNCTIONS_PATH . '/seo_helpers.php';
if (is_file($seo_helpers)) {
  require_once $seo_helpers;
}

/* ---------------------------------------------------------
 * Optional external utilities
 * --------------------------------------------------------- */
$send_email = ASSETS_PATH . '/send_email.php';
if (is_file($send_email)) {
  require_once $send_email;
}
