<?php
declare(strict_types=1);

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

require_once __DIR__ . '/../../_init.php';

if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

$page_title  = 'Culture · Indigenous Scripts (Nsịbịdị & Ndebe)';
$page_desc   = 'Indigenous scripts: Nsịbịdị and Ndebe — scholarship, ethics, and learning paths.';
$active_nav  = 'subjects';
$extra_css   = ['/lib/css/subjects-public.css'];

if (function_exists('mk_require_shared')) {
  // Prefer subjects header if you have one; else public header.
  if (defined('PRIVATE_PATH') && is_file(PRIVATE_PATH . '/shared/subjects_header.php')) {
    mk_require_shared('subjects_header.php');
  } else {
    mk_require_shared('public_header.php');
  }
} else {
  // last resort
  if (defined('APP_ROOT') && is_file(APP_ROOT . '/private/shared/public_header.php')) {
    require APP_ROOT . '/private/shared/public_header.php';
  }
}

?>
<section class="mk-hero" style="margin-top:14px;">
  <div class="mk-hero__bar" aria-hidden="true"></div>
  <div class="mk-hero__inner">
    <h1 class="mk-hero__title">Indigenous Scripts: Nsịbịdị and Ndebe</h1>
    <p class="mk-hero__subtitle">
      This page treats writing as a cultural practice: symbols, meaning, institutions, media, and ethics.
      For a literacy/linguistics treatment of writing Igbo, use the Language lens.
    </p>
  </div>
</section>

<?php
$lens_context = 'culture';

// scripts_block.php should be included via shared resolver too (avoid APP_ROOT coupling)
if (function_exists('mk_require_shared') && defined('PRIVATE_PATH') && is_file(PRIVATE_PATH . '/shared/scripts_block.php')) {
  mk_require_shared('scripts_block.php');
} elseif (defined('APP_ROOT') && is_file(APP_ROOT . '/private/shared/scripts_block.php')) {
  require APP_ROOT . '/private/shared/scripts_block.php';
}
?>

<section class="mk-card" style="margin-top:16px;">
  <div class="mk-card__body">
    <h2 style="margin:0 0 10px 0;">What we mean by “script” here</h2>
    <p style="margin:0 0 10px 0;">
      In African intellectual history, writing is not limited to alphabets. Some systems are
      <strong>phonographic</strong> (built to encode speech sounds), while others are
      <strong>semasiographic</strong> (built to encode ideas and social meaning).
    </p>
    <p class="mk-muted" style="margin:0;">
      Nsịbịdị belongs primarily to the second frame: meaning emerges through social context, performance,
      and institutional knowledge networks. Ndebe belongs primarily to the first: designed to write spoken Ìgbò.
    </p>
  </div>
</section>

<section class="mk-card" style="margin-top:16px;">
  <div class="mk-card__body">
    <h2 style="margin:0 0 10px 0;">Cultural safety policy (public scholarship)</h2>
    <ul style="margin:0; padding-left:18px;">
      <li>We publish concept-level mappings with provenance (sources and confidence), not private initiatory knowledge.</li>
      <li>We label entries by sensitivity: <strong>public</strong>, <strong>limited</strong>, <strong>restricted</strong>.</li>
      <li>Where a symbol’s reading is context-bound or guarded, we state that clearly instead of forcing a single “translation.”</li>
    </ul>
  </div>
</section>

<section class="mk-card" style="margin-top:16px;">
  <div class="mk-card__body">
    <h2 style="margin:0 0 12px 0;">Go deeper</h2>
    <div class="cta-row" style="display:flex; gap:10px; flex-wrap:wrap;">
      <a class="mk-btn" href="/subjects/_scripts/nsibidi.php">Nsịbịdị: Heritage & Meaning</a>
      <a class="mk-btn" href="/subjects/_scripts/ndebe.php">Ndebe: Writing Ìgbò</a>
      <a class="mk-btn" href="/subjects/_scripts/writing.php#compare">Compare</a>
    </div>
  </div>
</section>

<?php
if (function_exists('mk_require_shared')) {
  if (defined('PRIVATE_PATH') && is_file(PRIVATE_PATH . '/shared/subjects_footer.php')) {
    mk_require_shared('subjects_footer.php');
  } else {
    mk_require_shared('public_footer.php');
  }
} else {
  if (defined('APP_ROOT') && is_file(APP_ROOT . '/private/shared/public_footer.php')) {
    require APP_ROOT . '/private/shared/public_footer.php';
  }
}
