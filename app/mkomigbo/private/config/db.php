<?php
declare(strict_types=1);

/**
 * Database configuration for Mkomigbo project
 */

$host = 'localhost';
$db   = 'mkomigbo_mkomigbo_app';
$user = 'mkomigbo_appuser';       // your DB username
$pass = 'WM1gHE6OmH?R4#?h';       // your DB password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Stop execution and display a clear message if connection fails
    die('Database connection failed: ' . $e->getMessage());
}

