<?php
require_once __DIR__ . '/../models/StaffModel.php';

class StaffController {
    public function list(): void {
        global $pdo;
        $model = new StaffModel($pdo);
        $staff = $model->getStaff();
        require __DIR__ . '/../views/staff_list.php';
    }
}
