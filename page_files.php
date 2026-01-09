<?php
// Page Files page: public entry point
require_once __DIR__ . '/../private/controllers/PageFileController.php';

// Initialize controller and fetch page files
$controller = new PageFileController($pdo);
$controller->listPageFiles(15); // Pass limit as needed
?>
