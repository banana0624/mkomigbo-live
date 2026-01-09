<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/query_helper.php';

class PageFileModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getActiveFiles($limit = 15) {
        return fetchRows(
            $this->pdo,
            'page_files',
            ['id', 'page_id', 'filename', 'file_type'],
            ['active' => 1],
            ['id' => 'ASC'],
            $limit
        );
    }
}
?>
