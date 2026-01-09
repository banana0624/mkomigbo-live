<?php
declare(strict_types=1);

define('APP_ROOT', __DIR__ . '/app/mkomigbo');
require_once APP_ROOT . '/private/assets/initialize.php';

http_response_code(200);
header('Content-Type: application/json; charset=UTF-8');

echo json_encode([
  'ok' => true,
  'reference' => defined('REQUEST_ID') ? REQUEST_ID : null,
], JSON_UNESCAPED_SLASHES);
