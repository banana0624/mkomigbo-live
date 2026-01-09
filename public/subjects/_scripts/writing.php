<?php
declare(strict_types=1);

// /home/mkomigbo/public_html/public/subjects/_scripts/writing.php

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

require_once __DIR__ . '/../../_init.php';

if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

$page_title = 'Language · Writing Ìgbò (Systems & Orthography)';
$page_desc  = 'Writing systems for Ìgbò: phonographic vs semasiographic, and ethics in publishing.';
$active_nav = 'subjects';
$extra_css  = ['/lib/css/subjects-public.css'];

if (function_exists('mk_require_shared')) {
  if (defined('PRIVATE_PATH') && is_file(PRIVATE_PATH . '/shared/subjects_header.php')) {
    mk_require_shared('subjects_header.php');
  } else {
    mk_require_shared('public_header.php');
  }
} else {
  if (defined('APP_ROOT') && is_file(APP_ROOT . '/private/shared/public_header.php')) {
    require APP_ROOT . '/private/shared/public_header.php';
  }
}
?>
<section class="mk-hero" style="margin-top:14px;">
  <div class="mk-hero__bar" aria-hidden="true"></div>
  <div class="mk-hero__inner">
    <h1 class="mk-hero__title">Writing Ìgbò: Systems, Literacy, and Meaning</h1>
    <p class="mk-hero__subtitle">
      This is the language lens: what a writing system must do to represent speech (sounds, syllables, tone),
      and how Ndebe positions itself as a modern script for Ìgbò. Nsịbịdị is discussed accurately as cultural semantics,
      not as a full phonographic encoding of spoken Igbo.
    </p>
  </div>
</section>

<?php
$lens_context = 'language';

if (function_exists('mk_require_shared') && defined('PRIVATE_PATH') && is_file(PRIVATE_PATH . '/shared/scripts_block.php')) {
  mk_require_shared('scripts_block.php');
} elseif (defined('APP_ROOT') && is_file(APP_ROOT . '/private/shared/scripts_block.php')) {
  require APP_ROOT . '/private/shared/scripts_block.php';
}
?>

<section class="mk-card" style="margin-top:16px;">
  <div class="mk-card__body">
    <h2 style="margin:0 0 10px 0;">Why “alphabet” is not the only standard</h2>
    <p style="margin:0 0 10px 0;">
      A phonographic script is built to represent speech; a semasiographic system represents meaning directly.
      In practice, societies may maintain multiple inscription systems for different functions—heritage, ritual,
      diplomacy, identity, and modern literacy.
    </p>
    <p class="mk-muted" style="margin:0;">
      Our project keeps these functions distinct so the scholarship stays clean and respectful.
    </p>
  </div>
</section>

<section class="mk-card" id="compare" style="margin-top:16px;">
  <div class="mk-card__body">
    <h2 style="margin:0 0 10px 0;">Comparison: Nsịbịdị vs Ndebe</h2>
    <div style="overflow:auto;">
      <table class="mk-table" style="width:100%; border-collapse:collapse;">
        <thead>
          <tr>
            <th style="text-align:left; border-bottom:1px solid var(--border); padding:8px;">Aspect</th>
            <th style="text-align:left; border-bottom:1px solid var(--border); padding:8px;">Nsịbịdị</th>
            <th style="text-align:left; border-bottom:1px solid var(--border); padding:8px;">Ndebe</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="padding:8px; border-bottom:1px solid var(--border);">Primary function</td>
            <td style="padding:8px; border-bottom:1px solid var(--border);">Cultural semantics; meaning in context</td>
            <td style="padding:8px; border-bottom:1px solid var(--border);">Designed to write Ìgbò (modern literacy)</td>
          </tr>
          <tr>
            <td style="padding:8px; border-bottom:1px solid var(--border);">Type</td>
            <td style="padding:8px; border-bottom:1px solid var(--border);">Ideographic / semasiographic</td>
            <td style="padding:8px; border-bottom:1px solid var(--border);">Phonographic (script for language)</td>
          </tr>
          <tr>
            <td style="padding:8px; border-bottom:1px solid var(--border);">Access model</td>
            <td style="padding:8px; border-bottom:1px solid var(--border);">Historically institutionally mediated</td>
            <td style="padding:8px; border-bottom:1px solid var(--border);">Open learning and public usage</td>
          </tr>
          <tr>
            <td style="padding:8px; border-bottom:1px solid var(--border);">Publishing ethics</td>
            <td style="padding:8px; border-bottom:1px solid var(--border);">Avoid restricted readings; publish concept layer</td>
            <td style="padding:8px; border-bottom:1px solid var(--border);">Publish learning materials; fonts/typing guides</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<section class="mk-card" style="margin-top:16px;">
  <div class="mk-card__body">
    <h2 style="margin:0 0 10px 0;">Continue</h2>
    <div class="cta-row" style="display:flex; gap:10px; flex-wrap:wrap;">
      <a class="mk-btn" href="/subjects/_scripts/ndebe.php">Learn Ndebe</a>
      <a class="mk-btn" href="/subjects/_scripts/nsibidi.php">Nsịbịdị (Culture lens)</a>
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
