<?php
require_once __DIR__ . '/../models/ContributorModel.php';

class ContributorController {
    public function list(): void {
        global $pdo;
        $model = new ContributorModel($pdo);
        $contributors = $model->getActiveContributors();
        require __DIR__ . '/../views/contributors_list.php';
    }
}
