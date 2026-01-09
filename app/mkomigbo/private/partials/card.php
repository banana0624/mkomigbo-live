<?php
/**
 * Card partial
 * Required:
 *   $card_href
 *   $card_title
 * Optional:
 *   $card_desc
 *   $card_icon
 *   $card_accent
 *   $card_pills = []
 */
?>
<div class="card" style="--accent: <?= h($card_accent ?? '#111') ?>;">
  <div class="card-bar"></div>
  <a class="stretch" href="<?= h($card_href) ?>">
    <div class="card-body">
      <div class="top">
        <?php if (!empty($card_icon)): ?>
          <div class="icon"><?= h($card_icon) ?></div>
        <?php endif; ?>
        <div style="min-width:0;">
          <h3><?= h($card_title) ?></h3>
          <?php if (!empty($card_desc)): ?>
            <p class="muted"><?= h($card_desc) ?></p>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!empty($card_pills)): ?>
        <div class="meta">
          <?php foreach ($card_pills as $pill): ?>
            <span class="pill"><?= h($pill) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </a>
</div>
