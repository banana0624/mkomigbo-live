<?php foreach ($contributors as $c): ?>
<div class="contributor">
    <h3><?= htmlspecialchars($c['name']) ?></h3>
    <p><?= htmlspecialchars($c['role']) ?></p>
    <p><?= htmlspecialchars($c['bio']) ?></p>
</div>
<?php endforeach; ?>
