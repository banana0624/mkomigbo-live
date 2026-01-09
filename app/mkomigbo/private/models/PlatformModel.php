<?php
require_once __DIR__ . '/../helpers/query_helper.php';

class PlatformModel {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getPlatforms(): array {
        return fetchRows(
            $this->pdo,
            'platforms',
            ['id', 'name', 'description'],
            ['is_public' => 1],
            ['id' => 'ASC'],
            50
        );
    }
}
