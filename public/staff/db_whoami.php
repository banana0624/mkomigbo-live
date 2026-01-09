<?php
declare(strict_types=1);

$init = __DIR__ . '/../../private/assets/initialize.php';
require_once $init;

header('Content-Type: text/plain; charset=utf-8');

try {
  $pdo = db();
  echo "DATABASE(): " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n";
  echo "USER(): " . $pdo->query('SELECT USER()')->fetchColumn() . "\n";
  echo "CURRENT_USER(): " . $pdo->query('SELECT CURRENT_USER()')->fetchColumn() . "\n";
  $portRow = $pdo->query("SHOW VARIABLES LIKE 'port'")->fetch(PDO::FETCH_ASSOC);
  echo "PORT: " . ($portRow['Value'] ?? '') . "\n";
  echo "HOSTNAME: " . $pdo->query('SELECT @@hostname')->fetchColumn() . "\n";
} catch (Throwable $e) {
  echo "DB ERROR: " . $e->getMessage() . "\n";
}