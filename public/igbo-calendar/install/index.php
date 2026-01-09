<?php
declare(strict_types=1);

/**
 * /public/igbo-calendar/install/index.php
 * PWA Install Landing (Premium)
 *
 * Route: /igbo-calendar/install/
 * Purpose:
 * - Human-friendly install guidance (Android / iOS / Desktop)
 * - "Install" button when beforeinstallprompt is available
 * - Deep links: Open App, Refresh, Troubleshoot
 *
 * Notes:
 * - This page assumes your canonical app lives at /igbo-calendar/
 * - It registers the SW (safe) and loads /igbo-calendar/install/install.js
 */

/* ---------------------------------------------------------
   Locate APP_ROOT robustly
--------------------------------------------------------- */
$searched = [];
$candidates = [
  dirname(__DIR__, 4) . '/app/mkomigbo',
  dirname(__DIR__, 5) . '/public_html/app/mkomigbo',
  '/home/mkomigbo/public_html/app/mkomigbo',
];

$APP_ROOT = null;
foreach ($candidates as $cand) {
  $searched[] = $cand;
  if (is_file($cand . '/private/assets/initialize.php')) { $APP_ROOT = $cand; break; }
}

if ($APP_ROOT === null) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Init not found\nTried:\n - " . implode("\n - ", $searched) . "\n";
  exit;
}

define('APP_ROOT', $APP_ROOT);
require_once APP_ROOT . '/private/assets/initialize.php';

if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

$page_title = 'Install Igbo Calendar — Mkomigbo';
$nav_active = 'igbo-calendar';

/* ---------------------------------------------------------
   Includes
--------------------------------------------------------- */
$public_nav = APP_ROOT . '/private/shared/public_nav.php';
if (!is_file($public_nav)) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Public nav include missing\nExpected: {$public_nav}\n";
  exit;
}

$app_url    = url_for('/igbo-calendar/');
$install_url= url_for('/igbo-calendar/install/');
$manifest   = url_for('/igbo-calendar/manifest.json');
$sw_url     = url_for('/igbo-calendar/service-worker.js');
$js_url     = url_for('/igbo-calendar/install/install.js');

/* ---------------------------------------------------------
   Basic UA hints (guidance only, never security)
--------------------------------------------------------- */
$ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
$is_ios = (strpos($ua, 'iphone') !== false) || (strpos($ua, 'ipad') !== false) || (strpos($ua, 'ipod') !== false);
$is_android = (strpos($ua, 'android') !== false);
$is_edge = (strpos($ua, 'edg') !== false);
$is_chrome = (strpos($ua, 'chrome') !== false) && !$is_edge;
$is_safari = (strpos($ua, 'safari') !== false) && !$is_chrome && !$is_edge;

$hint = 'Install is supported on most modern browsers.';
if ($is_ios && $is_safari) $hint = 'iPhone/iPad: install via Share → Add to Home Screen.';
elseif ($is_android) $hint = 'Android: use the Install button below or the browser menu → Install app.';
elseif ($is_chrome || $is_edge) $hint = 'Desktop: use the Install button or the install icon in the address bar.';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($page_title) ?></title>

  <link rel="stylesheet" href="<?= h(url_for('/lib/css/ui.css')) ?>">
  <link rel="stylesheet" href="<?= h(url_for('/lib/css/subjects.css')) ?>">

  <!-- PWA metadata -->
  <link rel="manifest" href="<?= h($manifest) ?>">
  <meta name="theme-color" content="#111111">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="apple-touch-icon" href="<?= h(url_for('/igbo-calendar/icons/icon-192.png')) ?>">

  <style>
    .mk-wrap{ padding:24px 0; }
    .mk-hero{
      border:1px solid rgba(0,0,0,.10);
      border-radius:22px;
      background:linear-gradient(180deg, rgba(0,0,0,0.02), #fff);
      box-shadow:0 20px 55px rgba(0,0,0,0.10);
      overflow:hidden;
    }
    .mk-hero__bar{ height:8px; background:#111; opacity:.88; }
    .mk-hero__inner{ padding:22px 18px 18px; }
    .mk-grid{
      display:grid;
      grid-template-columns: 1.3fr .9fr;
      gap:14px;
      margin-top:14px;
    }
    @media (max-width: 980px){
      .mk-grid{ grid-template-columns:1fr; }
    }
    .mk-panel{
      border:1px solid rgba(0,0,0,.08);
      border-radius:18px;
      background:#fff;
      box-shadow:0 10px 28px rgba(0,0,0,.06);
      padding:16px;
    }
    .mk-kbd{
      display:inline-block;
      border:1px solid rgba(0,0,0,.18);
      border-bottom-width:2px;
      border-radius:10px;
      padding:2px 8px;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
      font-size:.9em;
      background:rgba(0,0,0,.03);
    }
    .mk-list{ margin:10px 0 0; padding-left:18px; line-height:1.75; }
    .mk-badges{ display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
    .mk-badge{
      border:1px solid rgba(0,0,0,.14);
      border-radius:999px;
      padding:6px 10px;
      font-size:.9rem;
      background:rgba(0,0,0,.02);
    }
    .mk-actions{ display:flex; flex-wrap:wrap; gap:10px; margin-top:12px; align-items:center; }
    .btn[disabled]{ opacity:.55; cursor:not-allowed; }
    .mk-alert{
      margin-top:12px;
      border:1px solid rgba(0,0,0,.12);
      border-radius:14px;
      padding:12px 12px;
      background:rgba(0,0,0,.02);
    }
    .mk-alert--danger{
      border-color: rgba(160,0,0,.25);
      background: rgba(160,0,0,.04);
    }
    .mk-alert--ok{
      border-color: rgba(0,120,60,.25);
      background: rgba(0,120,60,.04);
    }
    .mk-small{ font-size:.92rem; line-height:1.7; }
    .mk-mono{
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
      font-size:.92rem;
    }
    .mk-hr{ height:1px; background:rgba(0,0,0,.08); margin:14px 0; }
    .mk-row{
      display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between;
    }
    .mk-right{ display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
  </style>
</head>

<body>
  <?php include_once $public_nav; ?>

  <main class="container mk-wrap">
    <section class="mk-hero">
      <div class="mk-hero__bar"></div>
      <div class="mk-hero__inner">
        <h1 style="margin:0 0 10px; font-size:clamp(1.75rem, 3.2vw, 2.75rem); line-height:1.06; letter-spacing:-0.02em;">
          Install Igbo Calendar
        </h1>
        <p class="muted" style="margin:0; max-width:92ch; line-height:1.75;">
          This installs the Igbo Calendar as an app on your phone or computer. It opens in its own window, works faster, and can keep core assets available offline.
        </p>

        <div class="mk-badges">
          <span class="mk-badge">Start URL: <span class="mk-mono"><?= h($app_url) ?></span></span>
          <span class="mk-badge">Scope: <span class="mk-mono">/igbo-calendar/</span></span>
          <span class="mk-badge"><?= h($hint) ?></span>
        </div>

        <div class="mk-actions">
          <a class="btn" href="<?= h($app_url) ?>">Open the App</a>

          <!-- Install button: enabled by install.js when available -->
          <button class="btn" id="btnInstall" type="button" disabled
            aria-disabled="true" title="Install will be enabled when your browser allows it.">
            Install App
          </button>

          <button class="btn" id="btnCheck" type="button">Run Install Check</button>
        </div>

        <div id="installStatus" class="mk-alert mk-small" role="status" aria-live="polite">
          Checking install support…
        </div>
      </div>
    </section>

    <section class="mk-grid">
      <!-- LEFT: Steps -->
      <div class="mk-panel">
        <div class="mk-row">
          <h2 style="margin:0;">Install steps</h2>
          <div class="mk-right">
            <a class="btn" href="<?= h($app_url) ?>?source=install">Open App</a>
          </div>
        </div>

        <div class="mk-hr"></div>

        <h3 style="margin:0 0 6px;">Android (Chrome/Edge)</h3>
        <ul class="mk-list">
          <li>Tap <strong>Install App</strong> (if available), or open the browser menu and choose <strong>Install app</strong>.</li>
          <li>Confirm. The app will appear on your home screen and in your app drawer.</li>
        </ul>

        <h3 style="margin:14px 0 6px;">iPhone / iPad (Safari)</h3>
        <ul class="mk-list">
          <li>Open this page in <strong>Safari</strong> (not in an in-app browser).</li>
          <li>Tap the <strong>Share</strong> icon.</li>
          <li>Select <strong>Add to Home Screen</strong>, then confirm.</li>
        </ul>

        <h3 style="margin:14px 0 6px;">Windows / macOS / Linux (Chrome/Edge)</h3>
        <ul class="mk-list">
          <li>Click <strong>Install App</strong> (if available), or click the <strong>install icon</strong> in the address bar.</li>
          <li>Confirm. It will open like a normal desktop app.</li>
        </ul>

        <div class="mk-hr"></div>

        <h3 style="margin:0 0 8px;">If install is not showing</h3>
        <ul class="mk-list">
          <li>Ensure you are on <strong>HTTPS</strong> (secure URL).</li>
          <li>Open the app route once: <span class="mk-kbd">/igbo-calendar/</span>, then come back here.</li>
          <li>Hard refresh: <span class="mk-kbd">Ctrl</span> + <span class="mk-kbd">F5</span> (desktop).</li>
          <li>If you already installed earlier, uninstall and reinstall after updates.</li>
        </ul>
      </div>

      <!-- RIGHT: Diagnostics -->
      <div class="mk-panel">
        <h2 style="margin:0;">Diagnostics</h2>
        <p class="muted" style="margin:8px 0 0; line-height:1.7;">
          These checks confirm the manifest and service worker are reachable. If they fail, install will not appear.
        </p>

        <div class="mk-hr"></div>

        <div class="mk-small">
          <div><strong>Manifest:</strong> <a class="mk-mono" href="<?= h($manifest) ?>"><?= h($manifest) ?></a></div>
          <div style="margin-top:6px;"><strong>Service worker:</strong> <a class="mk-mono" href="<?= h($sw_url) ?>"><?= h($sw_url) ?></a></div>
          <div style="margin-top:6px;"><strong>Install script:</strong> <a class="mk-mono" href="<?= h($js_url) ?>"><?= h($js_url) ?></a></div>
        </div>

        <div class="mk-hr"></div>

        <div id="diagBox" class="mk-alert mk-small" style="display:none;"></div>

        <div class="mk-actions">
          <button class="btn" id="btnCopyDebug" type="button">Copy Debug Info</button>
          <a class="btn" href="<?= h($install_url) ?>" title="Reload this page">Reload</a>
        </div>

        <p class="muted mk-small" style="margin-top:10px;">
          Tip: If Firefox shows “redirecting in a way that will never complete”, check for forced redirects between <span class="mk-kbd">/igbo-calendar</span> and <span class="mk-kbd">/igbo-calendar/</span> or conflicting rewrite rules.
        </p>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container">© <?= date('Y') ?> Mkomigbo</div>
  </footer>
  
  <script src="<?= h($js_url) ?>?v=20260103-0048" defer></script>

</body>
</html>
