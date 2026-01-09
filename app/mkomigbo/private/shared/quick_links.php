<?php
declare(strict_types=1);

/**
 * /app/mkomigbo/private/shared/quick_links.php
 * Shared: CTA row + optional tip + "Quick links" cards (self-styling fallback)
 *
 * Primary goal:
 * - Render nicely even if ui.css/theme CSS did not load (scoped fallback styles).
 *
 * Optional inputs (set BEFORE require/include):
 * - $ql_title (string) default "Quick links"
 * - $ql_include_staff (bool) default false (recommended for public pages)
 * - $ql_tip (string|null) optional tip line shown under the CTA row
 */

if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

$ql_title = (isset($ql_title) && is_string($ql_title) && trim($ql_title) !== '')
  ? trim($ql_title)
  : 'Quick links';

$ql_include_staff = isset($ql_include_staff) ? (bool)$ql_include_staff : false;

$ql_tip = (isset($ql_tip) && is_string($ql_tip) && trim($ql_tip) !== '')
  ? trim($ql_tip)
  : null;

/* URLs */
$subjects_url      = function_exists('url_for') ? url_for('/subjects/')      : '/subjects/';
$platforms_url     = function_exists('url_for') ? url_for('/platforms/')     : '/platforms/';
$contributors_url  = function_exists('url_for') ? url_for('/contributors/')  : '/contributors/';
$igbo_calendar_url = function_exists('url_for') ? url_for('/igbo-calendar/') : '/igbo-calendar/';
$staff_url         = function_exists('url_for') ? url_for('/staff/')         : '/staff/';

?>
<style>
  .mk-quicklinks { margin-top: 14px; }

  .mk-quicklinks .mk-cta-row {
    display:flex; flex-wrap:wrap; gap:10px;
    margin: 0 0 12px 0;
  }
  .mk-quicklinks .mk-cta-row a { text-decoration:none; }

  /* Buttons fallback */
  .mk-quicklinks .mk-btn,
  .mk-quicklinks a.mk-btn {
    display:inline-flex; align-items:center; justify-content:center;
    padding:10px 14px; border-radius:12px;
    border:1px solid rgba(255,255,255,.10);
    background: rgba(255,255,255,.04);
    color: inherit;
    line-height: 1;
    transition: transform .05s ease, background .15s ease, border-color .15s ease;
  }
  .mk-quicklinks .mk-btn:hover,
  .mk-quicklinks a.mk-btn:hover { transform: translateY(-1px); }

  .mk-quicklinks .mk-btn--primary,
  .mk-quicklinks a.mk-btn--primary {
    border-color: rgba(37,99,235,.45);
    background: rgba(37,99,235,.15);
  }

  /* Tip fallback */
  .mk-quicklinks .mk-tip {
    margin: 0 0 14px 0;
    opacity: .85;
  }
  .mk-quicklinks .mk-tip code { opacity: .95; }

  /* Grid fallback */
  .mk-quicklinks .mk-ql-grid {
    display:grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
    margin-top: 10px;
  }

  /* Card fallback */
  .mk-quicklinks .mk-ql-card {
    position: relative;
    border-radius: 16px;
    border: 1px solid rgba(255,255,255,.08);
    background: rgba(255,255,255,.03);
    overflow:hidden;
  }
  .mk-quicklinks .mk-ql-card::before {
    content:"";
    position:absolute; inset:0 auto 0 0;
    width: 5px;
    background: var(--accent, #2563eb);
    opacity: .85;
  }
  .mk-quicklinks .mk-ql-link {
    display:block;
    padding: 14px 14px 14px 16px;
    color: inherit;
    text-decoration: none;
  }
  .mk-quicklinks .mk-ql-top { display:flex; gap:12px; align-items:flex-start; }
  .mk-quicklinks .mk-ql-badge {
    width:42px; height:42px; border-radius:999px;
    display:grid; place-items:center;
    font-weight:700;
    border:1px solid rgba(255,255,255,.10);
    background: rgba(255,255,255,.04);
    flex: 0 0 auto;
  }
  .mk-quicklinks .mk-ql-title { margin:0; font-size:1rem; }
  .mk-quicklinks .mk-ql-desc { margin:6px 0 0 0; opacity:.85; line-height:1.35; }
</style>

<div class="mk-quicklinks">

  <!-- CTA row -->
  <div class="cta-row mk-cta-row">
    <a class="mk-btn mk-btn--primary" href="<?= h($subjects_url) ?>">Explore Subjects</a>
    <a class="mk-btn" href="<?= h($platforms_url) ?>">Browse Platforms</a>
    <a class="mk-btn" href="<?= h($contributors_url) ?>">Meet Contributors</a>
    <a class="mk-btn" href="<?= h($igbo_calendar_url) ?>">Download Igbo Calendar</a>
  </div>

  <?php if ($ql_tip !== null): ?>
    <p class="muted mk-tip"><?= h($ql_tip) ?></p>
  <?php endif; ?>

  <!-- Quick links heading -->
  <h2 style="margin:18px 0 10px 0; font-size:1.1rem; letter-spacing:.2px;"><?= h($ql_title) ?></h2>

  <?php
    $items = [
      [
        'href'   => $subjects_url,
        'badge'  => 'S',
        'title'  => 'Subjects',
        'desc'   => 'Browse all topics and dive into pages.',
        'accent' => '#2563eb',
      ],
      [
        'href'   => $platforms_url,
        'badge'  => 'P',
        'title'  => 'Platforms',
        'desc'   => 'Explore tools and sections like calendars, posts, and more.',
        'accent' => '#7c3aed',
      ],
      [
        'href'   => $contributors_url,
        'badge'  => 'C',
        'title'  => 'Contributors',
        'desc'   => 'Meet authors, editors, and collaborators.',
        'accent' => '#059669',
      ],
    ];

    if ($ql_include_staff) {
      $items[] = [
        'href'   => $staff_url,
        'badge'  => 'A',
        'title'  => 'Staff',
        'desc'   => 'Admin dashboard for managing subjects and pages.',
        'accent' => '#0ea5e9',
      ];
    }

    $items[] = [
      'href'   => $igbo_calendar_url,
      'badge'  => 'I',
      'title'  => 'Igbo Calendar',
      'desc'   => 'Download the Igbo Calendar for reference.',
      'accent' => '#f59e0b',
    ];
  ?>

  <div class="mk-ql-grid" aria-label="<?= h($ql_title) ?>">
    <?php foreach ($items as $it): ?>
      <article class="card mk-card mk-ql-card" style="--accent: <?= h($it['accent']) ?>;">
        <a class="stretch mk-card__link mk-ql-link" href="<?= h($it['href']) ?>">
          <div class="mk-ql-top">
            <div class="logo mk-ql-badge" aria-hidden="true"><?= h($it['badge']) ?></div>
            <div style="min-width:0;">
              <h3 class="mk-ql-title"><?= h($it['title']) ?></h3>
              <p class="muted mk-muted mk-ql-desc"><?= h($it['desc']) ?></p>
            </div>
          </div>
        </a>
      </article>
    <?php endforeach; ?>
  </div>

</div>
