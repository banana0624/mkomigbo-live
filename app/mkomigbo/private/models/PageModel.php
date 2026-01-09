<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/query_helper.php';

class PageModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getActivePages($limit = 10) {
        return fetchRows(
            $this->pdo,
            'pages',
            ['id', 'title', 'slug'],
            ['status' => 1],
            ['title' => 'ASC'],
            $limit
        );
    }
}
?>
