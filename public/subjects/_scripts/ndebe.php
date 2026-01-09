<?php
declare(strict_types=1);

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

require_once __DIR__ . '/../../_init.php';

/**
 * /public/subjects/_scripts/ndebe.php
 * Script pages
 */

if (!defined('APP_ROOT')) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Bootstrap failed: APP_ROOT not defined\n";
  exit;
}

require_once APP_ROOT . '/private/functions/scripts_repository.php';

$page_title = 'Language · Ndebe (Write Ìgbò)';
$nav_active = 'subjects';

include APP_ROOT . '/private/shared/public_header.php';

$concepts = mk_concepts_for_script('ndebe', [
  'sensitivity_max' => 'public',
  'limit' => 200
]);

?>
<section class="hero">
  <div class="hero-inner">
    <h1>Ndebe</h1>
    <p class="muted">
      A modern writing system presented as a script for the Ìgbò language, with public materials for learning and typing.
    </p>
  </div>
</section>

<?php
$lens_context = 'language';
include APP_ROOT . '/private/shared/scripts_block.php';
?>

<section class="card" style="margin-top:16px;">
  <div class="card-body">
    <h2 style="margin:0 0 10px 0;">Learning path (structured)</h2>
    <ol style="margin:0; padding-left:18px;">
      <li>Start with vowels and tone conventions (if you adopt tone-marking).</li>
      <li>Move to consonant units and common syllable patterns.</li>
      <li>Practice reading short sentences; then write your own.</li>
      <li>Use a keyboard/font tool for consistency in digital publishing.</li>
    </ol>
    <p class="muted" style="margin:12px 0 0 0;">
      This page can later embed your chosen font/keyboard method. For now, it anchors the scholarship and the learning journey.
    </p>
  </div>
</section>

<section class="card" style="margin-top:16px;">
  <div class="card-body">
    <h2 style="margin:0 0 10px 0;">Ndebe concepts (public notes)</h2>
    <?php if (empty($concepts)): ?>
      <p class="muted">Entries will appear here after you run the migration + seed below.</p>
    <?php else: ?>
      <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px,1fr)); gap:12px;">
        <?php foreach ($concepts as $c): ?>
          <article class="card" style="border:1px solid var(--border);">
            <div class="card-body">
              <h3 style="margin:0 0 6px 0;"><?= h($c['english_gloss']) ?></h3>
              <div class="muted" style="margin:0 0 10px 0;">
                <strong><?= h($c['igbo_term']) ?></strong> · <?= h($c['domain']) ?>
              </div>
              <p style="margin:0 0 10px 0;"><?= h($c['description']) ?></p>
              <?php if (!empty($c['citation_key'])): ?>
                <div class="muted" style="margin-top:8px;">
                  Source: <?= h($c['authors']) ?> (<?= h((string)$c['year']) ?>) — <?= h($c['source_title']) ?>
                </div>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include APP_ROOT . '/private/shared/public_footer.php'; ?>
