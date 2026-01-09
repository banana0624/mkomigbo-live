<?php
declare(strict_types=1);

/**
 * /public_html/public/platforms/index.php
 * Public Platforms landing (premium + robust).
 *
 * - Shows platforms that exist (file OR folder/index.php)
 * - Coming soon items link to real placeholder pages (folder routes)
 * - Each platform has its own accent color (like subjects)
 * - Uses shared quick_links.php for consistent CTA + Quick Links everywhere
 */

define('APP_ROOT', dirname(__DIR__, 2) . '/app/mkomigbo');

$init = APP_ROOT . '/private/assets/initialize.php';
if (!is_file($init)) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Init not found\nExpected: {$init}\n";
  exit;
}
require_once $init;

$page_title  = 'Platforms — Mkomigbo';
$nav_active  = 'platforms';

/* Shared public nav include */
$public_nav = APP_ROOT . '/private/shared/public_nav.php';
if (!is_file($public_nav)) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Public nav include missing\nExpected: {$public_nav}\n";
  exit;
}

/* Primary URLs */
$subjects_url     = url_for('/subjects/');
$contributors_url = url_for('/contributors/');

/* ---------------------------------------------------------
 * Existence checks (checks files inside /public_html/public)
 * --------------------------------------------------------- */
if (!function_exists('pf__platform_entry_exists')) {
  function pf__platform_entry_exists(string $public_path): bool {
    $public_path = '/' . ltrim($public_path, '/');

    // We are inside: /public_html/public/platforms/
    // So: dirname(__DIR__) == /public_html/public
    $abs = dirname(__DIR__) . $public_path;

    if (is_file($abs)) return true;

    if (is_dir($abs)) {
      $idx = rtrim($abs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.php';
      return is_file($idx);
    }

    return false;
  }
}

$platforms = [
  [
    'key'   => 'igbo-calendar',
    'title' => 'Igbo Calendar',
    'desc'  => 'Traditional timekeeping, market days, cultural calendar references, and educational notes.',
    'href'  => url_for('/platforms/igbo-calendar.php'),
    'icon'  => 'IC',
    'tag'   => 'Featured',
    'accent'=> '#111111',
    'exists'=> pf__platform_entry_exists('/platforms/igbo-calendar.php'),
  ],

  // Coming soon (clickable placeholders)
  [
    'key'   => 'blog',
    'title' => 'Blog',
    'desc'  => 'Long-form articles, editorials, and featured writings across subjects.',
    'href'  => url_for('/platforms/blog/'),
    'icon'  => 'B',
    'tag'   => 'Coming soon',
    'accent'=> '#2F3A4A',
    'exists'=> pf__platform_entry_exists('/platforms/blog/'),
  ],
  [
    'key'   => 'forum',
    'title' => 'Forum',
    'desc'  => 'Community discussions, Q&A, and collaborative learning.',
    'href'  => url_for('/platforms/forum/'),
    'icon'  => 'F',
    'tag'   => 'Coming soon',
    'accent'=> '#2F4A5A',
    'exists'=> pf__platform_entry_exists('/platforms/forum/'),
  ],
  [
    'key'   => 'podcast',
    'title' => 'Podcast',
    'desc'  => 'Audio episodes: conversations, history, interviews, and cultural insights.',
    'href'  => url_for('/platforms/podcast/'),
    'icon'  => 'PD',
    'tag'   => 'Coming soon',
    'accent'=> '#3C2F4A',
    'exists'=> pf__platform_entry_exists('/platforms/podcast/'),
  ],
  [
    'key'   => 'vlog',
    'title' => 'Vlog',
    'desc'  => 'Video stories, short explainers, interviews, and documentary-style content.',
    'href'  => url_for('/platforms/vlog/'),
    'icon'  => 'V',
    'tag'   => 'Coming soon',
    'accent'=> '#4A2F59',
    'exists'=> pf__platform_entry_exists('/platforms/vlog/'),
  ],
  [
    'key'   => 'gallery',
    'title' => 'Gallery',
    'desc'  => 'Curated photos, artefacts, maps, and historical visuals.',
    'href'  => url_for('/platforms/gallery/'),
    'icon'  => 'G',
    'tag'   => 'Coming soon',
    'accent'=> '#4A3F2F',
    'exists'=> pf__platform_entry_exists('/platforms/gallery/'),
  ],
  [
    'key'   => 'knowledge-index',
    'title' => 'Knowledge Index',
    'desc'  => 'Cross-subject discovery by tags, themes, timelines, and people.',
    'href'  => url_for('/platforms/knowledge-index/'),
    'icon'  => 'KI',
    'tag'   => 'Coming soon',
    'accent'=> '#2F4A43',
    'exists'=> pf__platform_entry_exists('/platforms/knowledge-index/'),
  ],
  [
    'key'   => 'media-library',
    'title' => 'Media Library',
    'desc'  => 'Curated videos, reels, photos, and documentary references.',
    'href'  => url_for('/platforms/media-library/'),
    'icon'  => 'M',
    'tag'   => 'Coming soon',
    'accent'=> '#59312F',
    'exists'=> pf__platform_entry_exists('/platforms/media-library/'),
  ],
];

$available = array_values(array_filter($platforms, fn($p) => !empty($p['exists'])));
$coming    = array_values(array_filter($platforms, fn($p) => empty($p['exists'])));

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($page_title) ?></title>

  <link rel="stylesheet" href="<?= h(url_for('/lib/css/ui.css')) ?>">
  <link rel="stylesheet" href="<?= h(url_for('/lib/css/subjects.css')) ?>">

  <style>
    :root{
      --ink:#111; --muted:#6c757d;
      --line:rgba(0,0,0,.10); --line-strong:rgba(0,0,0,.22);
      --card:#fff; --shadow:0 12px 28px rgba(0,0,0,.05); --shadow-hover:0 18px 36px rgba(0,0,0,.08);
      --radius-xl:20px; --radius-lg:18px; --radius-md:16px;
    }
    body{ background: rgba(0,0,0,.015); color:var(--ink); }
    .muted{ color:var(--muted); }

    .hero{
      border:1px solid var(--line);
      border-radius: var(--radius-xl);
      background: linear-gradient(180deg, rgba(0,0,0,0.02), #fff);
      box-shadow: 0 20px 55px rgba(0,0,0,0.10);
      overflow:hidden;
    }
    .hero-bar{ height:8px; background:#111; opacity:.88; }
    .hero-inner{ padding:22px 18px 18px; }
    .hero h1{ margin:0 0 10px; font-size: clamp(1.75rem, 3.2vw, 2.75rem); line-height:1.06; letter-spacing:-0.02em; }
    .hero p{ margin:0; max-width:88ch; line-height:1.75; }

    .section-title{ margin:24px 0 10px; display:flex; align-items:baseline; justify-content:space-between; gap:10px; flex-wrap:wrap; }
    .section-title h2{ margin:0; font-size:1.2rem; }

    .grid{
      margin-top:12px;
      display:grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap:14px;
    }
    .card{
      border:1px solid var(--line);
      border-radius: var(--radius-lg);
      background:var(--card);
      overflow:hidden;
      box-shadow: var(--shadow);
      transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
      min-height: 170px;
    }
    .card:hover{ transform: translateY(-1px); border-color: var(--line-strong); box-shadow: var(--shadow-hover); }
    .card-bar{ height:7px; background: var(--accent, #111); }
    .card-body{ padding:14px; display:flex; flex-direction:column; gap:10px; height:100%; }
    .top{ display:flex; gap:12px; align-items:flex-start; }

    .icon{
      width:56px; height:56px; border-radius:16px;
      border:1px solid var(--line);
      background: rgba(0,0,0,0.02);
      display:flex; align-items:center; justify-content:center;
      font-weight:900; letter-spacing:-0.02em;
      flex:0 0 auto;
    }
    .card h3{ margin:2px 0 6px; font-size:1.1rem; line-height:1.2; letter-spacing:-0.01em; }
    .card p{ margin:0; line-height:1.6; }

    .pill{
      display:inline-block; padding:4px 10px;
      border:1px solid rgba(0,0,0,0.12);
      border-radius:999px; background:#fff;
      font-size:.85rem; color:#495057;
    }
    .meta{ margin-top:auto; display:flex; gap:10px; flex-wrap:wrap; }
    .stretch{ text-decoration:none; color:inherit; display:block; height:100%; }
  </style>
</head>

<body>
  <?php include_once $public_nav; ?>

  <main class="container" style="padding:24px 0;">
    <section class="hero">
      <div class="hero-bar"></div>
      <div class="hero-inner">
        <h1>Platforms</h1>
        <p class="muted">
          Platforms are interactive sections of Mkomigbo — tools, indexes, and features that complement the Subjects library.
          This page shows what is available now and what is planned next.
        </p>

        <?php
          $ql_title = 'Quick links';
          $ql_tip = null;
          $ql_include_staff = false;
          require APP_ROOT . '/private/shared/quick_links.php';
        ?>
      </div>
    </section>

    <div class="section-title">
      <h2>Available now</h2>
    </div>

    <?php if (count($available) === 0): ?>
      <p class="muted">No platforms are enabled yet.</p>
    <?php else: ?>
      <section class="grid">
        <?php foreach ($available as $p): ?>
          <div class="card" style="--accent: <?= h((string)$p['accent']) ?>;">
            <div class="card-bar"></div>
            <a class="stretch" href="<?= h((string)$p['href']) ?>">
              <div class="card-body">
                <div class="top">
                  <div class="icon" aria-hidden="true"><?= h((string)$p['icon']) ?></div>
                  <div style="min-width:0;">
                    <h3><?= h((string)$p['title']) ?></h3>
                    <p class="muted"><?= h((string)$p['desc']) ?></p>
                  </div>
                </div>
                <div class="meta">
                  <span class="pill"><?= h((string)$p['tag']) ?></span>
                  <span class="pill">Open</span>
                </div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <div class="section-title" style="margin-top:26px;">
      <h2>Coming soon</h2>
    </div>

    <?php if (count($coming) === 0): ?>
      <p class="muted">Nothing planned yet.</p>
    <?php else: ?>
      <section class="grid">
        <?php foreach ($coming as $p): ?>
          <div class="card" style="--accent: <?= h((string)$p['accent']) ?>;">
            <div class="card-bar"></div>
            <a class="stretch" href="<?= h((string)$p['href']) ?>">
              <div class="card-body">
                <div class="top">
                  <div class="icon" aria-hidden="true"><?= h((string)$p['icon']) ?></div>
                  <div style="min-width:0;">
                    <h3><?= h((string)$p['title']) ?></h3>
                    <p class="muted"><?= h((string)$p['desc']) ?></p>
                  </div>
                </div>
                <div class="meta">
                  <span class="pill"><?= h((string)$p['tag']) ?></span>
                  <span class="pill">Preview</span>
                </div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
  </main>

  <footer class="site-footer">
    <div class="container">© <?= date('Y') ?> Mkomigbo</div>
  </footer>
</body>
</html>
