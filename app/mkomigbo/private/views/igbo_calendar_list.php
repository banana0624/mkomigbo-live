<?php foreach ($events as $e): ?>
<div class="calendar-event">
    <strong><?= htmlspecialchars($e['event_date']) ?></strong>
    <h4><?= htmlspecialchars($e['event_name']) ?></h4>
    <p><?= htmlspecialchars($e['description']) ?></p>
</div>
<?php endforeach; ?>
