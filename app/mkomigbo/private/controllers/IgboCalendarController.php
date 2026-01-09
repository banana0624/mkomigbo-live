<?php
require_once __DIR__ . '/../models/IgboCalendarModel.php';

class IgboCalendarController {
    public function list(): void {
        global $pdo;
        $model = new IgboCalendarModel($pdo);
        $events = $model->getCalendarEvents();
        require __DIR__ . '/../views/igbo_calendar_list.php';
    }
}
