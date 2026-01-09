<?php
require_once __DIR__ . '/../models/SubjectModel.php';

$subjectModel = new SubjectModel($pdo);
$subjects = $subjectModel->getPublicSubjects(12);

// Include view to render HTML
require_once __DIR__ . '/../views/subjects_list.php';
?>
