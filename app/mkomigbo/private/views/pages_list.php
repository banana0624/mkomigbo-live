<?php
// $pages is expected to be passed from PageController

foreach ($pages as $page) {
    echo "<div class='page-item'>";
    echo "<h3>" . htmlspecialchars($page['title']) . "</h3>";
    echo "<p>Slug: " . htmlspecialchars($page['slug']) . "</p>";
    echo "</div>";
}
?>
