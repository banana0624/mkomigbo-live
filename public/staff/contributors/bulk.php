<?php
declare(strict_types=1);

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

require_once __DIR__ . '/../_init.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

if (function_exists('require_staff_login')) { require_staff_login(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect_to(url_for('/staff/contributors/'));
}

$token = (string)($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
  http_response_code(403);
  echo "Invalid CSRF token.";
  exit;
}

$action = (string)($_POST['action'] ?? '');
$ids = $_POST['ids'] ?? [];
if (!is_array($ids) || !$ids) {
  redirect_to(url_for('/staff/contributors/?msg=No+rows+selected'));
}

$ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v)=>$v>0)));
if (!$ids) redirect_to(url_for('/staff/contributors/?msg=No+valid+IDs'));

if (!db_column_exists('contributors','status')) {
  redirect_to(url_for('/staff/contributors/?msg=Status+column+missing'));
}

$newStatus = null;
if ($action === 'set_active') $newStatus = 'active';
if ($action === 'set_draft')  $newStatus = 'draft';
if (!$newStatus) {
  redirect_to(url_for('/staff/contributors/?msg=Invalid+action'));
}

$pdo = db();
$in = implode(',', array_fill(0, count($ids), '?'));

$sql = "UPDATE contributors SET status = ? WHERE id IN ($in)";
$params = array_merge([$newStatus], $ids);

$st = $pdo->prepare($sql);
$st->execute($params);

redirect_to(url_for('/staff/contributors/?msg=Updated+' . count($ids) . '+contributors'));
