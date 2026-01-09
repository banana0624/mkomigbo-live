<?php
// Pages page: public entry point
require_once __DIR__ . '/../private/controllers/PageController.php';

// Initialize controller and fetch pages
$controller = new PageController($pdo);
$controller->listPages(10); // Pass limit as needed
?>
