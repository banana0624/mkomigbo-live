<?php
/**
 * Grid partial
 * Required:
 *   $grid_items (array of rendered card HTML strings)
 */
?>
<section class="grid">
  <?php foreach ($grid_items as $item): ?>
    <?= $item ?>
  <?php endforeach; ?>
</section>
