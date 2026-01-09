<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

echo "RUNNING FILE: " . __FILE__ . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? '') . "\n";
echo "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? '') . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? '') . "\n\n";

$pw = (string)($_GET['pw'] ?? '');
if ($pw === '') {
  echo "Usage: /hash.php?pw=YOUR_PASSWORD\n";
  exit;
}

echo password_hash($pw, PASSWORD_DEFAULT) . "\n";
