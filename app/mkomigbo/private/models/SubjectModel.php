<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/query_helper.php';

class SubjectModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getPublicSubjects($limit = 12) {
        return fetchRows(
            $this->pdo,
            'subjects',
            ['id', 'name', 'slug', 'description'],
            ['is_public' => 1],
            ['id' => 'ASC'],
            $limit
        );
    }
}
?>
