<?php
declare(strict_types=1);

/**
 * scripts_block.php
 * Shared “two lenses” module: Culture lens + Language lens.
 *
 * Inputs (optional):
 * - $lens_context = 'culture' | 'language'
 * - $base_subject = '/subjects/culture/' etc (used for cross-links)
 */

$lens_context = $lens_context ?? 'culture';

$links = [
  'culture_overview'  => url_for('/subjects/culture/scripts.php'),
  'culture_nsibidi'   => url_for('/subjects/culture/nsibidi.php'),
  'language_overview' => url_for('/subjects/language1/writing.php'),
  'language_ndebe'    => url_for('/subjects/language1/ndebe.php'),
  'compare'           => url_for('/subjects/language1/writing.php#compare'),
];

?>
<section class="card" style="margin-top:16px;">
  <div class="card-body">
    <h2 style="margin:0 0 6px 0;">Igbo Scripts: Two Lenses</h2>
    <p class="muted" style="margin:0 0 14px 0;">
      This topic is presented in two academically correct frames:
      <strong>Culture</strong> (heritage/semiotics) and <strong>Language</strong> (writing Igbo/literacy).
    </p>

    <div class="grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px,1fr)); gap:12px;">
      <div class="card" style="border:1px solid var(--border);">
        <div class="card-body">
          <h3 style="margin:0 0 8px 0;">Culture lens</h3>
          <p class="muted" style="margin:0 0 12px 0;">
            Nsịbịdị as cultural knowledge: symbols, institutions, media (cloth/walls/objects),
            meaning-in-context, and ethics of publication.
          </p>
          <div class="cta-row" style="display:flex; gap:8px; flex-wrap:wrap;">
            <a class="btn" href="<?= h($links['culture_overview']) ?>">Overview</a>
            <a class="btn" href="<?= h($links['culture_nsibidi']) ?>">Nsịbịdị</a>
          </div>
        </div>
      </div>

      <div class="card" style="border:1px solid var(--border);">
        <div class="card-body">
          <h3 style="margin:0 0 8px 0;">Language lens</h3>
          <p class="muted" style="margin:0 0 12px 0;">
            How writing systems encode speech. Ndebe as a modern script designed to write Ìgbò.
            Nsịbịdị’s role is different: cultural semantics, not full phonographic encoding.
          </p>
          <div class="cta-row" style="display:flex; gap:8px; flex-wrap:wrap;">
            <a class="btn" href="<?= h($links['language_overview']) ?>">Writing Igbo</a>
            <a class="btn" href="<?= h($links['language_ndebe']) ?>">Ndebe</a>
          </div>
        </div>
      </div>
    </div>

    <div style="margin-top:12px;">
      <a class="btn" href="<?= h($links['compare']) ?>">Compare Nsịbịdị vs Ndebe</a>
    </div>
  </div>
</section>
