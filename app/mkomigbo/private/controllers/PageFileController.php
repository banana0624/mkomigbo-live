<?php
require_once __DIR__ . '/../models/PageFileModel.php';

class PageFileController {
    private $pageFileModel;

    public function __construct($pdo) {
        $this->pageFileModel = new PageFileModel($pdo);
    }

    public function listPageFiles($limit = 15) {
        // Fetch active files from the model
        $page_files = $this->pageFileModel->getActiveFiles($limit);

        // Include the view to render HTML
        require_once __DIR__ . '/../views/page_files_list.php';
    }
}

// Usage example (in a public page like /public/page_files.php)
$controller = new PageFileController($pdo);
$controller->listPageFiles(15);
?>
