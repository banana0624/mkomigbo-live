<?php
require_once __DIR__ . '/../helpers/query_helper.php';

class StaffModel {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getStaff(int $limit = 20): array {
        return fetchRows(
            $this->pdo,
            'staff',
            ['id', 'name', 'position', 'bio'],
            ['is_active' => 1],
            ['name' => 'ASC'],
            $limit
        );
    }
}
