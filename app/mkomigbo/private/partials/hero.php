<?php
/**
 * Hero partial
 * Required:
 *   $hero_title
 * Optional:
 *   $hero_desc
 *   $hero_ctas = [ ['label'=>..., 'href'=>...], ... ]
 */
?>
<section class="hero">
  <div class="hero-bar"></div>
  <div class="hero-inner">
    <h1><?= h($hero_title) ?></h1>

    <?php if (!empty($hero_desc)): ?>
      <p class="muted"><?= h($hero_desc) ?></p>
    <?php endif; ?>

    <?php if (!empty($hero_ctas)): ?>
      <div class="cta-row">
        <?php foreach ($hero_ctas as $cta): ?>
          <a class="btn" href="<?= h($cta['href']) ?>">
            <?= h($cta['label']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
