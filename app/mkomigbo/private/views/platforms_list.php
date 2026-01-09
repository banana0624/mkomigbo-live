<?php foreach ($platforms as $p): ?>
<div class="platform">
    <h3><?= htmlspecialchars($p['name']) ?></h3>
    <p><?= htmlspecialchars($p['description']) ?></p>
</div>
<?php endforeach; ?>
