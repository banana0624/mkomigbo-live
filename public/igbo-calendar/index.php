<?php
declare(strict_types=1);

/**
 * /public_html/public/igbo-calendar/index.php
 * Public: Igbo Calendar (PWA-ready)
 *
 * Source of truth:
 * - Uses /private/functions/igbo_calendar_functions.php (igbo_calendar_render_page)
 *
 * Rules enforced here:
 * - Leap year = Gregorian leap year of selected year
 * - Marketday checkpoint: 2026-01-07 = Nkwo
 * - Preferred new-year marketday for 2026: Orie
 */

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

/* ---------------------------------------------------------
   Locate APP_ROOT robustly
--------------------------------------------------------- */
$searched = [];
$candidates = [
  dirname(__DIR__, 3) . '/app/mkomigbo',
  dirname(__DIR__, 4) . '/public_html/app/mkomigbo',
  '/home/mkomigbo/public_html/app/mkomigbo',
];

$APP_ROOT = null;
foreach ($candidates as $cand) {
  $searched[] = $cand;
  if (is_file($cand . '/private/assets/initialize.php')) {
    $APP_ROOT = $cand;
    break;
  }
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

/* ---------------------------------------------------------
   Page vars
--------------------------------------------------------- */
$page_title = 'Igbo Calendar — Mkomigbo';
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

$cal_fn = APP_ROOT . '/private/functions/igbo_calendar_functions.php';
if (!is_file($cal_fn)) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Igbo calendar functions missing\nExpected: {$cal_fn}\n";
  exit;
}
require_once $cal_fn;

$context_fn = APP_ROOT . '/private/functions/igbo_context.php';
if (is_file($context_fn)) {
    require_once $context_fn;
}

if (!function_exists('igbo_calendar_render_page')) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Renderer missing: igbo_calendar_render_page() not found in {$cal_fn}\n";
  exit;
}

/* ---------------------------------------------------------
   FINAL micro-adjustment (LOCK IT PERMANENTLY)
   - Negative shifts slightly "earlier" toward full moon,
     raising illumination on waning gibbous.
--------------------------------------------------------- */
$GLOBALS['mk_moon_calibration_days'] = -0.45;

/* Optional: if your sprite’s first tile isn’t “New Moon”,
   set this later (0..27) to rotate frame mapping.
   Leave at 0 unless you want to shift frames.
*/
$GLOBALS['mk_moon_sprite_offset'] = 0;

/* ---------------------------------------------------------
   Year navigation (?y=YYYY) + compat (?year=YYYY)
--------------------------------------------------------- */
$year = (int)gmdate('Y');

if (isset($_GET['y']) && is_scalar($_GET['y'])) {
  $tmp = (int)$_GET['y'];
  if ($tmp !== 0) { $year = $tmp; }
} elseif (isset($_GET['year']) && is_scalar($_GET['year'])) {
  $tmp = (int)$_GET['year'];
  if ($tmp !== 0) { $year = $tmp; }
}
$year = max(-9999, min(9999, $year));

/* ---------------------------------------------------------
   Leap rule (Gregorian leap year)
--------------------------------------------------------- */
$isLeap = function_exists('igbo_is_gregorian_leap_year')
  ? igbo_is_gregorian_leap_year($year)
  : (($year % 400 === 0) || (($year % 4 === 0) && ($year % 100 !== 0)));

/* Month lengths per your spec */
$monthDays = array_fill(1, 13, 28);
$monthDays[13] = 29;
if ($isLeap) { $monthDays[1] = 29; }

/* Globals (optional) */
$GLOBALS['mk_igbo_year']       = $year;
$GLOBALS['mk_igbo_is_leap']    = $isLeap;
$GLOBALS['mk_igbo_month_days'] = $monthDays;

/* Today (UTC) for consistent "TODAY" marker */
$GLOBALS['mk_igbo_today_iso'] = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');

$todayCtx = function_exists('igbo_context_for_date')
    ? igbo_context_for_date(
        new DateTimeImmutable($GLOBALS['mk_igbo_today_iso'], new DateTimeZone('UTC'))
      )
    : null;

/* Nav URLs */
$prevHref = url_for('/igbo-calendar/?y=' . ($year - 1));
$nextHref = url_for('/igbo-calendar/?y=' . ($year + 1));
$gotoHref = url_for('/igbo-calendar/');

/* ---------------------------------------------------------
   Render calendar (opts enforce checkpoint + preference)
--------------------------------------------------------- */
$render_html = '';
$render_error = null;

try {
  $opts = [
    'market_anchor_date' => '2026-01-07',
    'market_anchor_day'  => 'Nkwo',
    'preferred_new_year_marketday' => ($year === 2026 ? 'Orie' : null),
    'month_days' => $monthDays,
    'layout' => 'market_grid_4',
  ];

  $render_html = igbo_calendar_render_page($year, $opts);
} catch (Throwable $e) {
  $render_error = $e;
}

/* ---------------------------------------------------------
   PWA assets
--------------------------------------------------------- */
$manifest_url = url_for('/igbo-calendar/manifest.json');
$sw_url       = url_for('/igbo-calendar/service-worker.js');
$install_url  = url_for('/igbo-calendar/install/');
$cal_css      = url_for('/igbo-calendar/igbo-calendar.css');

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($page_title) ?></title>

  <link rel="stylesheet" href="<?= h(url_for('/lib/css/ui.css')) ?>">
  <link rel="stylesheet" href="<?= h(url_for('/lib/css/subjects.css')) ?>">
  <link rel="stylesheet" href="<?= h($cal_css) ?>">

  <!-- PWA -->
  <link rel="manifest" href="<?= h($manifest_url) ?>">
  <meta name="theme-color" content="#111111">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Igbo Calendar">

  <!-- Icons (install surfaces) -->
  <link rel="icon" sizes="192x192" href="<?= h(url_for('/igbo-calendar/icons/icon-192.png')) ?>">
  <link rel="icon" sizes="512x512" href="<?= h(url_for('/igbo-calendar/icons/icon-512.png')) ?>">
  <link rel="apple-touch-icon" sizes="48x48" href="<?= h(url_for('/igbo-calendar/icons/icon-48.png')) ?>">
  <link rel="apple-touch-icon" sizes="72x72" href="<?= h(url_for('/igbo-calendar/icons/icon-72.png')) ?>">
  <link rel="apple-touch-icon" sizes="192x192" href="<?= h(url_for('/igbo-calendar/icons/icon-192.png')) ?>">
  <link rel="apple-touch-icon" sizes="512x512" href="<?= h(url_for('/igbo-calendar/icons/icon-512.png')) ?>">
</head>

<body>
  <?php include_once $public_nav; ?>

  <main class="container" style="padding:24px 0;">
    <section class="hero mk-hero">
      <div class="hero-bar mk-hero__bar"></div>
      <div class="hero-inner mk-hero__inner">
        <h1 class="mk-hero__title">Igbo Calendar</h1>
        
        <?php if (is_array($todayCtx)): ?>
          <div class="mk-card mk-card--soft" style="margin-top:14px; max-width:420px;">
            <div class="muted" style="font-size:.9rem; margin-bottom:6px;">
              Today’s Context
            </div>
        
            <div style="display:flex; gap:12px; flex-wrap:wrap; font-size:.95rem;">
              <div><strong>Market:</strong> <?= h($todayCtx['market_day']) ?></div>
              <div><strong>Element:</strong> <?= h($todayCtx['element']) ?> <?= h($todayCtx['element_symbol']) ?></div>
              <div><strong>Moon:</strong> <?= (int)$todayCtx['moon_pct'] ?>%</div>
              <div><strong>Stage:</strong> <?= h($todayCtx['moon_stage']) ?></div>
            </div>
          </div>
        <?php endif; ?>


        <!-- 4 cowrie shells (traditional, textured SVG) -->
        <div class="mk-cowrie-emblem mk-cowrie-grid" aria-hidden="true">
          <!-- Define filters ONCE -->
          <svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
            <defs>
              <!-- Subtle speckle texture (lightweight) -->
              <filter id="mkCowrieSpeckle" x="-20%" y="-20%" width="140%" height="140%">
                <feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="2" seed="7" result="noise"/>
                <feColorMatrix in="noise" type="matrix"
                  values="
                    1 0 0 0 0
                    0 1 0 0 0
                    0 0 1 0 0
                    0 0 0 14 -8
                  " result="specks"/>
                <feColorMatrix in="specks" type="matrix"
                  values="
                    1 0 0 0 0.03
                    0 1 0 0 0.02
                    0 0 1 0 0.01
                    0 0 0 0.25 0
                  " result="tintedSpecks"/>
                <feBlend in="SourceGraphic" in2="tintedSpecks" mode="multiply"/>
              </filter>

              <!-- Gentle inner shadow -->
              <filter id="mkCowrieInnerShadow" x="-20%" y="-20%" width="140%" height="140%">
                <feOffset dx="0" dy="1" />
                <feGaussianBlur stdDeviation="1.1" result="blur"/>
                <feComposite in="blur" in2="SourceAlpha" operator="arithmetic" k2="-1" k3="1" result="innerShadow"/>
                <feColorMatrix in="innerShadow" type="matrix"
                  values="
                    0 0 0 0 0.16
                    0 0 0 0 0.12
                    0 0 0 0 0.08
                    0 0 0 0.55 0
                  " />
                <feComposite in2="SourceGraphic" operator="over"/>
              </filter>
            </defs>
          </svg>

          <?php for ($i=0; $i<4; $i++): ?>
            <span class="mk-cowrie" aria-hidden="true">
              <svg viewBox="0 0 64 64" class="mk-cowrie-svg" role="img" focusable="false" aria-hidden="true">
                <!-- Apply texture to the whole shell group -->
                <g filter="url(#mkCowrieSpeckle)">
                  <path class="shell-body" d="M32 6
                    C22.2 6.2, 14.2 14.1, 14 24.1
                    C13.8 41.2, 24.2 56.2, 32 58
                    C39.8 56.2, 50.2 41.2, 50 24.1
                    C49.8 14.1, 41.8 6.2, 32 6 Z"
                    filter="url(#mkCowrieInnerShadow)"/>

                  <path class="shell-ridge" d="M20.2 24.2
                    C20.6 36.4, 27.3 48.6, 32 50.4
                    C36.7 48.6, 43.4 36.4, 43.8 24.2
                    C44 17.9, 39 12.4, 32 12.1
                    C25 12.4, 20 17.9, 20.2 24.2 Z"/>

                  <path class="shell-slit" d="M23.3 31
                    C28.2 28.6, 35.8 28.6, 40.7 31
                    C39.2 39.5, 24.8 39.5, 23.3 31 Z"/>

                  <path class="shell-slit-edge" d="M24.6 31.1
                    C28.7 29.2, 35.3 29.2, 39.4 31.1
                    C38.6 33.2, 25.4 33.2, 24.6 31.1 Z"/>

                  <path class="shell-highlight" d="M21.6 18.6
                    C23.8 14.6, 27.6 12.4, 32 12.1
                    C29 14.3, 26.8 18.2, 26.6 22.3
                    C24.4 22.5, 22.3 21, 21.6 18.6 Z"/>

                  <path class="shell-highlight-2" d="M36.6 14.2
                    C40.2 15.2, 42.8 18.2, 43.6 21.8
                    C41.4 20.9, 39.2 19.1, 38 16.9
                    C37.4 15.8, 37 14.9, 36.6 14.2 Z"/>
                </g>
              </svg>
            </span>
          <?php endfor; ?>
        </div>

        <p class="muted mk-hero__desc" style="max-width:70ch;">
          4-day market week (Eke / Orie / Afo / Nkwo), 13-month structure, and daily moon phase.
          Checkpoint enforced: <strong>2026-01-07 = Nkwo</strong>.
        </p>

        <?php
          $ql_title = 'Quick links';
          $ql_tip = null;
          $ql_include_staff = false;
          require APP_ROOT . '/private/shared/quick_links.php';
        ?>

        <!-- Install UI -->
        <div class="cta-row mk-install">
          <a class="btn" href="<?= h($install_url) ?>">Install Help</a>
          <button class="btn" id="mkInstallBtn" type="button" style="display:none;">Install App</button>
          <span class="muted" id="mkInstallNote" style="font-size:.92rem; line-height:1.5;"></span>
        </div>

        <div class="mk-install-hint" id="mkIosHint" style="display:none; margin-top:10px;">
          <div class="mk-alert mk-alert--info" style="margin:0;">
            <strong>iPhone/iPad:</strong> Open in Safari → Share → <em>Add to Home Screen</em>.
          </div>
        </div>
      </div>
    </section>

    <!-- Year navigation -->
    <section style="margin-top:14px;">
      <div class="mk-card mk-card--soft">
        <div class="mk-yearbar">
          <div class="mk-yearbar__left">
            <a class="btn" href="<?= h($prevHref) ?>" aria-label="Previous year">← Previous</a>

            <div class="mk-yearbar__title">
              <div class="mk-yearbar__year">
                Year: <span><?= h((string)$year) ?></span>
              </div>
              <div class="muted mk-yearbar__meta">
                <?= $isLeap ? '(Gregorian leap year)' : '(Gregorian standard year)' ?>
              </div>
            </div>

            <a class="btn" href="<?= h($nextHref) ?>" aria-label="Next year">Next →</a>
          </div>

          <form action="<?= h($gotoHref) ?>" method="get" class="mk-yearbar__form">
            <label for="mkYearInput" class="muted" style="font-size:.9rem;">Go to:</label>
            <input
              id="mkYearInput"
              name="y"
              type="number"
              inputmode="numeric"
              value="<?= h((string)$year) ?>"
              min="-9999"
              max="9999"
              class="mk-yearbar__input"
            />
            <button class="btn" type="submit">Go</button>
          </form>
        </div>

        <div class="muted" style="margin-top:10px; font-size:.92rem; line-height:1.6;">
          Month lengths this year:
          <strong>1</strong>=<?= (int)$monthDays[1] ?> days,
          <strong>2–12</strong>=28 days,
          <strong>13</strong>=29 days.
        </div>
      </div>
    </section>

    <section style="margin-top:18px;">
      <?php if ($render_error instanceof Throwable): ?>
        <?php $msg = (defined('APP_DEBUG') && APP_DEBUG) ? $render_error->getMessage() : 'Calendar failed to render.'; ?>
        <div class="mk-alert mk-alert--danger" style="margin-top:12px;">
          <strong>Calendar error:</strong>
          <pre style="white-space:pre-wrap; margin:8px 0 0;"><?= h($msg) ?></pre>
        </div>
      <?php else: ?>
        <?= $render_html ?>
      <?php endif; ?>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container">© <?= (int)gmdate('Y') ?> Mkomigbo</div>
  </footer>

  <!-- PWA: register SW + surface install prompt -->
  <script>
  (function(){
    var swUrl = "<?= h($sw_url) ?>";
    var scope = "<?= h(url_for('/igbo-calendar/')) ?>";

    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register(swUrl, { scope: scope, updateViaCache: 'none' })
        .catch(function(){});
    }

    // iOS hint
    var ua = navigator.userAgent || '';
    var isIOS = /iphone|ipad|ipod/i.test(ua);
    var isStandaloneIOS = (window.navigator && window.navigator.standalone) ? true : false;
    var iosHint = document.getElementById('mkIosHint');
    if (isIOS && !isStandaloneIOS && iosHint) iosHint.style.display = 'block';

    // Install prompt (Chromium/Android/Desktop)
    var btn = document.getElementById('mkInstallBtn');
    var note = document.getElementById('mkInstallNote');
    if (!btn) return;

    var deferred = null;

    function setNote(html){ if (note) note.innerHTML = html; }
    function isStandalone(){
      try { return window.matchMedia && window.matchMedia('(display-mode: standalone)').matches; }
      catch(e){ return false; }
    }

    if (isStandalone()) {
      setNote('This app is already installed.');
      return;
    }

    setNote('If you do not see an install button, open the browser menu and choose <strong>Install app</strong> (or on iPhone: Share → <strong>Add to Home Screen</strong>).');

    window.addEventListener('beforeinstallprompt', function(e){
      e.preventDefault();
      deferred = e;
      btn.style.display = 'inline-flex';
      setNote('Install is available. Click <strong>Install App</strong>.');
    });

    window.addEventListener('appinstalled', function(){
      deferred = null;
      btn.style.display = 'none';
      setNote('Installed successfully.');
    });

    btn.addEventListener('click', function(){
      if (!deferred) {
        setNote('Install is not available on this device/browser right now. Use the browser menu → <strong>Install app</strong>.');
        return;
      }
      try { deferred.prompt(); } catch(e) {}
      deferred = null;
      btn.style.display = 'none';
      setNote('If installed, open it from your apps/home screen.');
    });
  })();
  </script>

  <!-- Auto-scroll to TODAY -->
  <script>
  (function(){
    var el = document.querySelector('.mk-cal-cell--today');
    if (el && el.scrollIntoView) {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  })();
  </script>

</body>
</html>
