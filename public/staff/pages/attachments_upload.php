<?php
declare(strict_types=1);

/**
 * /public/staff/pages/attachments_upload.php
 * Staff: upload page attachments
 *
 * Requires:
 * - /public/staff/_init.php providing:
 *   staff_id(), staff_pdo(), staff_csrf_verify(), staff_safe_return_url(), staff_redirect()
 *
 * Redirect notice:
 * - attach=sent | partial | error | invalid | csrf | too_large | nofile
 */

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

require_once __DIR__ . '/../_init.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'POST') {
  http_response_code(405);
  header('Allow: POST');
  exit;
}

$return = staff_safe_return_url((string)($_POST['return'] ?? ''), '/staff/subjects/pgs/index.php');

$go = static function (string $return, string $code): never {
  $target = $return . (strpos($return, '?') === false ? '?' : '&') . 'attach=' . rawurlencode($code);
  $target = str_replace(["\r", "\n"], '', $target);
  staff_redirect(function_exists('url_for') ? (string)url_for($target) : $target, 302);
};

/* Require login */
$staffId = (int)staff_id();
if ($staffId < 1) {
  $login = function_exists('url_for') ? (string)url_for('/staff/login.php?notice=login') : '/staff/login.php?notice=login';
  $login = str_replace(["\r", "\n"], '', $login);
  header('Location: ' . $login, true, 302);
  exit;
}

/* CSRF (accept csrf_token canonical + legacy csrf) */
$token = (string)($_POST['csrf_token'] ?? ($_POST['csrf'] ?? ''));
if (!staff_csrf_verify($token)) {
  $go($return, 'csrf');
}

/* Validate page */
$pageId = (int)($_POST['page_id'] ?? 0);
if ($pageId < 1) {
  $go($return, 'invalid');
}

/* Detect “POST too large” (PHP drops $_POST/$_FILES silently) */
$cl = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($cl > 0 && empty($_FILES)) {
  $go($return, 'too_large');
}

/* Validate files exist */
$files = $_FILES['attachments'] ?? null;
if (!is_array($files) || (!isset($files['name']) && !isset($files['tmp_name']))) {
  $go($return, 'nofile');
}

/* DB */
$pdo = staff_pdo();
if (!$pdo instanceof PDO) {
  http_response_code(500);
  exit;
}

/* Upload handler */
if (!defined('PRIVATE_PATH') || !is_string(PRIVATE_PATH) || PRIVATE_PATH === '') {
  $go($return, 'error');
}

$fn = PRIVATE_PATH . '/functions/page_attachments_upload.php';
if (!is_file($fn)) {
  $go($return, 'error');
}
require_once $fn;

$res = mk_staff_upload_page_attachments($pdo, $pageId, $staffId, $files);

/* Decide notice */
$notice = 'sent';
if (!is_array($res) || empty($res['ok'])) $notice = 'error';
if (is_array($res) && !empty($res['errors'])) $notice = 'partial';

$go($return, $notice);
