<?php
// $page_files is expected to be passed from PageFileController

foreach ($page_files as $file) {
    echo "<div class='file-item'>";
    echo "<h4>File ID: " . htmlspecialchars($file['id']) . "</h4>";
    echo "<p>Page ID: " . htmlspecialchars($file['page_id']) . "</p>";
    echo "<p>Filename: " . htmlspecialchars($file['filename']) . "</p>";
    echo "<p>Type: " . htmlspecialchars($file['file_type']) . "</p>";
    echo "</div>";
}
?>
