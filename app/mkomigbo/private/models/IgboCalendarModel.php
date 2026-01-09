<?php
require_once __DIR__ . '/../helpers/query_helper.php';

class IgboCalendarModel {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getCalendarEvents(int $limit = 30): array {
        return fetchRows(
            $this->pdo,
            'igbo_calendar',
            ['id', 'event_name', 'event_date', 'description'],
            ['is_public' => 1],
            ['event_date' => 'ASC'],
            $limit
        );
    }
}
