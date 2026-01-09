<?php
/**
 * Section title partial
 * Required:
 *   $section_title
 * Optional:
 *   $section_link_label
 *   $section_link_href
 */
?>
<div class="section-title">
  <h2><?= h($section_title) ?></h2>

  <?php if (!empty($section_link_label) && !empty($section_link_href)): ?>
    <a href="<?= h($section_link_href) ?>">
      <?= h($section_link_label) ?>
    </a>
  <?php endif; ?>
</div>
