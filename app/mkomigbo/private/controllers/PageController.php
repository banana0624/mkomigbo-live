<?php
require_once __DIR__ . '/../models/PageModel.php';

class PageController {
    private $pageModel;

    public function __construct($pdo) {
        $this->pageModel = new PageModel($pdo);
    }

    public function listPages($limit = 10) {
        // Fetch active pages from the model
        $pages = $this->pageModel->getActivePages($limit);

        // Include the view to render HTML
        require_once __DIR__ . '/../views/pages_list.php';
    }
}

// Usage example (in a public page like /public/pages.php)
$controller = new PageController($pdo);
$controller->listPages(10);
?>
