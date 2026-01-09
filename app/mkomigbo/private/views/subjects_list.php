<?php
require_once __DIR__ . '/../models/SubjectModel.php';

$subjectModel = new SubjectModel($pdo);
$subjects = $subjectModel->getPublicSubjects(12);

foreach ($subjects as $subject) {
    echo "<div class='subject-item'>";
    echo "<h3>" . htmlspecialchars($subject['name']) . "</h3>";
    echo "<p>" . htmlspecialchars($subject['description']) . "</p>";
    echo "</div>";
}
?>
