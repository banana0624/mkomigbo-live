<?php
declare(strict_types=1);

// Simple forwarder so /hash.php always uses the canonical script in /public/hash.php
$dest = '/public/hash.php';

if (!empty($_SERVER['QUERY_STRING'])) {
  $dest .= '?' . $_SERVER['QUERY_STRING'];
}

header('Location: ' . $dest, true, 302);
exit;
