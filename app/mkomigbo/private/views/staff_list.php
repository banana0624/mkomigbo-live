<?php foreach ($staff as $s): ?>
<div class="staff-member">
    <h3><?= htmlspecialchars($s['name']) ?></h3>
    <p><?= htmlspecialchars($s['position']) ?></p>
    <p><?= htmlspecialchars($s['bio']) ?></p>
</div>
<?php endforeach; ?>
