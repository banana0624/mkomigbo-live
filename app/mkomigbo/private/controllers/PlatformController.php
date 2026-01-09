<?php
require_once __DIR__ . '/../models/PlatformModel.php';

class PlatformController {
    public function list(): void {
        global $pdo;
        $model = new PlatformModel($pdo);
        $platforms = $model->getPlatforms();
        require __DIR__ . '/../views/platforms_list.php';
    }
}
