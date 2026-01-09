<?php
declare(strict_types=1);

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

require_once __DIR__ . '/../../_init.php';

/**
 * /public/subjects/_scripts/nsibidi.php
 * Script pages
 */

if (!defined('APP_ROOT')) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Bootstrap failed: APP_ROOT not defined\n";
  exit;
}

require_once APP_ROOT . '/private/functions/scripts_repository.php';

$page_title = 'Culture · Nsịbịdị (Heritage & Meaning)';
$nav_active = 'subjects';

include APP_ROOT . '/private/shared/public_header.php';

$script = mk_script_by_slug('nsibidi');
$domains = mk_domains_for_script('nsibidi', 'public');

$domain = $_GET['domain'] ?? '';
$domain = is_string($domain) ? trim($domain) : '';
$concepts = mk_concepts_for_script('nsibidi', [
  'domain' => ($domain !== '' ? $domain : null),
  'sensitivity_max' => 'public',
  'limit' => 500
]);

?>
<section class="hero">
  <div class="hero-inner">
    <h1>Nsịbịdị</h1>
    <p class="muted">
      A documented indigenous symbol system used historically across parts of southeastern Nigeria and the Cross River region.
      Here we treat it as cultural semiotics: meanings in context, institutions, and ethics.
    </p>
  </div>
</section>

<?php
$lens_context = 'culture';
include APP_ROOT . '/private/shared/scripts_block.php';
?>

<section class="card" style="margin-top:16px;">
  <div class="card-body">
    <h2 style="margin:0 0 8px 0;">Concept Explorer (Public Layer)</h2>
    <p class="muted" style="margin:0 0 12px 0;">
      This explorer shows concept-level mappings with provenance and confidence—appropriate for public scholarship.
    </p>

    <form method="get" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:12px;">
      <label class="muted" for="domain">Domain</label>
      <select id="domain" name="domain" class="input" onchange="this.form.submit()">
        <option value="">All domains</option>
        <?php foreach ($domains as $d): ?>
          <option value="<?= h($d) ?>" <?= $domain===$d ? 'selected' : '' ?>><?= h($d) ?></option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="btn" type="submit">Filter</button></noscript>
    </form>

    <?php if (empty($concepts)): ?>
      <p class="muted">No entries found.</p>
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
              <div class="muted" style="display:flex; gap:10px; flex-wrap:wrap;">
                <span>Confidence: <strong><?= h($c['confidence']) ?></strong></span>
                <span>Sensitivity: <strong><?= h($c['sensitivity']) ?></strong></span>
              </div>
              <?php if (!empty($c['context_tag'])): ?>
                <div class="muted" style="margin-top:8px;">
                  Context: <?= h($c['context_tag']) ?>
                </div>
              <?php endif; ?>
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

<section class="card" style="margin-top:16px;">
  <div class="card-body">
    <h2 style="margin:0 0 8px 0;">Ethics note</h2>
    <p class="muted" style="margin:0;">
      Some Nsịbịdị readings are institutionally mediated and context-bound. Where scholarship indicates restricted knowledge,
      we do not publish “decoded” content. We publish responsibly: concept frames, provenance, and limits.
    </p>
  </div>
</section>

<?php include APP_ROOT . '/private/shared/public_footer.php'; ?>
