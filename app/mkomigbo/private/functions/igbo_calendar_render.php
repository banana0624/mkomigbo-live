<?php
declare(strict_types=1);

/**
 * /private/functions/igbo_calendar_render.php
 *
 * Compact 4-column month grid (Eke/Orie/Afo/Nkwo) renderer.
 *
 * Marketday checkpoint anchor (confirmed truth):
 * - 2026-01-07 = Nkwo
 *
 * IMPORTANT:
 * - We recompute marketday for each date using the checkpoint.
 * - This prevents "Jan 7, 2026 shows Orie" type of drift.
 */

require_once __DIR__ . '/IgboCalendarYear.php';

if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

function mk_market_anchor(): array {
  return [
    'date' => new DateTimeImmutable('2026-01-07 00:00:00', new DateTimeZone('UTC')),
    'idx'  => 3, // 0=Eke,1=Orie,2=Afo,3=Nkwo
  ];
}

function mk_marketday_idx_from_iso(string $isoYmd): int {
  $tz = new DateTimeZone('UTC');
  $anchor = mk_market_anchor();

  $d0 = new DateTimeImmutable($anchor['date']->format('Y-m-d') . ' 00:00:00', $tz);
  $d1 = new DateTimeImmutable($isoYmd . ' 00:00:00', $tz);

  $diff = (int)(($d1->getTimestamp() - $d0->getTimestamp()) / 86400);
  $idx = ($anchor['idx'] + ($diff % 4)) % 4;
  if ($idx < 0) $idx += 4;
  return $idx;
}

function mk_marketday_name(int $idx): string {
  $idx = ($idx % 4 + 4) % 4;
  return ['Eke', 'Orie', 'Afo', 'Nkwo'][$idx];
}

function mk_marketday_css(string $name): string {
  $n = mb_strtolower(trim($name));
  if ($n === 'eke') return 'eke';
  if ($n === 'orie' || $n === 'ọrie') return 'orie';
  if ($n === 'afo' || $n === 'afọ') return 'afo';
  if ($n === 'nkwo' || $n === 'ńkwọ' || $n === 'kwọ') return 'nkwo';
  return 'eke';
}

/**
 * Render a full year (months) from an approx start date.
 */
function render_igbo_calendar(DateTimeImmutable $approxStart, int $igboYearIndex, string $anchorMarketDay = 'Nkwo'): string {
  $year = new IgboCalendarYear($approxStart, $igboYearIndex, $anchorMarketDay);

  $tz = new DateTimeZone('UTC');
  $todayIso = (new DateTimeImmutable('now', $tz))->format('Y-m-d');

  $cols = ['Eke', 'Orie', 'Afo', 'Nkwo'];

  ob_start();
  ?>
<section class="igbo-calendar-app" aria-label="Igbo Calendar" data-app="igbo-calendar">

  <header class="igbo-calendar-header">
    <h1><?= h($year->getYearLabel()) ?></h1>
    <p class="subtitle">
      Igbo Calendar · <?= $year->isLeapYear() ? 'Gregorian Leap Year' : 'Gregorian Standard Year' ?>
    </p>
    <p class="subtitle small">
      Checkpoint: <strong>2026-01-07 = Nkwo</strong>
    </p>
  </header>

  <div class="months-container">
    <?php
      $monthNo = 0;
      foreach ($year->getMonths() as $month):
        $monthNo++;

        // Normalize month day data with corrected marketdays (checkpoint-based).
        $days = [];
        foreach (($month['days'] ?? []) as $day) {
          $greg = (string)($day['gregorian'] ?? '');
          if ($greg === '') continue;

          $idx = mk_marketday_idx_from_iso($greg);
          $market = mk_marketday_name($idx);

          $days[] = [
            'igboDay'     => (string)($day['igboDay'] ?? ''),
            'gregorian'   => $greg,
            'weekday'     => (string)($day['weekday'] ?? ''),
            'marketDay'   => $market,
            'moonSymbol'  => (string)($day['moonSymbol'] ?? ''),
            'moonStage'   => (string)($day['moonStage'] ?? ''),
          ];
        }

        $daysInMonth = count($days);
        $startCol = ($daysInMonth > 0) ? mk_marketday_idx_from_iso($days[0]['gregorian']) : 0;

        $totalCells = $startCol + $daysInMonth;
        $rows = (int)ceil($totalCells / 4);

        $monthName = (string)($month['name'] ?? ('Month ' . $monthNo));
        $gregStart = (string)($month['gregorianRef']['start'] ?? '');
        $gregEnd   = (string)($month['gregorianRef']['end'] ?? '');
    ?>
      <article class="igbo-month-card">

        <header class="month-header">
          <div class="month-title-row">
            <h2><?= h($monthName) ?></h2>
            <div class="month-sub">
              <span class="pill">Month <?= (int)$monthNo ?></span>
              <span class="pill"><?= (int)$daysInMonth ?> days</span>
            </div>
          </div>

          <div class="gregorian-ref">
            Gregorian: <?= h($gregStart) ?> – <?= h($gregEnd) ?>
          </div>

          <div class="mk-cal-legend" aria-hidden="true">
            <span class="mk-cal-legend__item"><strong>Eke</strong> = Fire</span>
            <span class="mk-cal-legend__dot">•</span>
            <span class="mk-cal-legend__item"><strong>Orie</strong> = Water</span>
            <span class="mk-cal-legend__dot">•</span>
            <span class="mk-cal-legend__item"><strong>Afo</strong> = Earth</span>
            <span class="mk-cal-legend__dot">•</span>
            <span class="mk-cal-legend__item"><strong>Nkwo</strong> = Air</span>
          </div>
        </header>

        <div class="month-table-wrap">
          <div class="month-table-scroll">
            <table class="month-grid-4" aria-label="<?= h($monthName) ?> month">
              <thead>
                <tr>
                  <?php foreach ($cols as $c): ?>
                    <th scope="col"><?= h($c) ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php for ($r = 0; $r < $rows; $r++): ?>
                  <tr>
                    <?php for ($c = 0; $c < 4; $c++):
                      $cellNumber = ($r * 4) + $c;

                      if ($cellNumber < $startCol) {
                        echo '<td class="mk-cal-empty">&nbsp;</td>';
                        continue;
                      }

                      $effective = $cellNumber - $startCol;

                      if ($effective >= 0 && $effective < $daysInMonth) {
                        $d = $days[$effective];
                        $isToday = ($todayIso === $d['gregorian']);
                        $marketCss = mk_marketday_css($d['marketDay']);

                        echo '<td class="mk-cell market-' . h($marketCss) . ($isToday ? ' mk-cal-cell--today' : '') . '"'
                           . ' data-igbo-day="' . h($d['igboDay']) . '"'
                           . ' data-gregorian="' . h($d['gregorian']) . '">';

                        echo '  <div class="mk-cal-cell">';
                        echo '    <div class="mk-cal-cell__top">';
                        echo '      <div class="mk-cal-cell__day">Day ' . h($d['igboDay']) . '</div>';
                        echo '      <div class="mk-cal-cell__date muted">' . h($d['gregorian']) . '</div>';
                        echo '    </div>';

                        if ($isToday) {
                          echo '    <div class="mk-cal-today-badge" aria-label="Today">TODAY</div>';
                        }

                        echo '    <div class="mk-cal-kv">';
                        echo '      <div><strong>Weekday:</strong> ' . h($d['weekday']) . '</div>';
                        echo '      <div><strong>Moon:</strong> ' . h($d['moonSymbol']) . '</div>';
                        echo '      <div><strong>Stage:</strong> ' . h($d['moonStage']) . '</div>';
                        echo '    </div>';
                        echo '  </div>';

                        echo '</td>';
                      } else {
                        echo '<td class="mk-cal-empty">&nbsp;</td>';
                      }
                    endfor; ?>
                  </tr>
                <?php endfor; ?>
              </tbody>
            </table>
          </div>
        </div>

      </article>
    <?php endforeach; ?>
  </div>
</section>

<style>
:root{
  --market-eke:#fcfcff;
  --market-orie:#fdfcf9;
  --market-afo:#f9fcf9;
  --market-nkwo:#fcf9f9;

  --highlight-today:#fff3b0;
  --border: rgba(0,0,0,.14);
  --muted: rgba(0,0,0,.65);
}

.muted{ color: var(--muted); }

/* BASE */
.igbo-calendar-app{
  max-width:1100px;
  margin:0 auto;
  padding:16px 12px 40px;
  font-family:system-ui,-apple-system,BlinkMacSystemFont,sans-serif;
  background:#fdfdfc; color:#222;
}
.igbo-calendar-header{ text-align:center; margin:10px 0 24px; }
.igbo-calendar-header h1{ margin:0; font-size:2rem; letter-spacing:-0.02em; }
.subtitle{ opacity:.78; font-size:.95rem; margin:6px 0 0; }
.subtitle.small{ font-size:.88rem; }

/* MONTH CARD */
.igbo-month-card{
  background:#fff;
  border-radius:16px;
  box-shadow:0 10px 26px rgba(0,0,0,.06);
  margin:0 0 34px;
  overflow:hidden;
  border:1px solid rgba(0,0,0,.08);
}
.month-header{
  background:linear-gradient(180deg,#f3f6f8,#ffffff);
  padding:16px 18px 14px;
  border-bottom:1px solid rgba(0,0,0,.08);
}
.month-title-row{
  display:flex; gap:12px; align-items:flex-start; justify-content:space-between;
  flex-wrap:wrap;
}
.month-header h2{ margin:0; font-size:1.35rem; letter-spacing:-0.01em; }
.month-sub{ display:flex; gap:8px; flex-wrap:wrap; }
.pill{
  display:inline-flex;
  padding:5px 10px;
  border-radius:999px;
  border:1px solid rgba(0,0,0,.12);
  background:#fff;
  font-size:.82rem;
  font-weight:700;
}
.gregorian-ref{
  margin-top:10px;
  font-size:.88rem;
  background:#eef2f4;
  padding:6px 10px;
  border-radius:10px;
  display:inline-block;
}

/* LEGEND (desktop only) */
.mk-cal-legend{
  margin-top:10px;
  font-size:.88rem;
  color: rgba(0,0,0,.70);
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  align-items:center;
}
.mk-cal-legend__dot{ opacity:.55; }
@media (max-width: 900px){ .mk-cal-legend{ display:none; } }

/* CENTERED TABLE WRAPPER (70% desktop) */
.month-table-wrap{
  width:100%;
  display:flex;
  justify-content:center;
  padding:14px 12px 18px;
}
.month-table-scroll{
  width:min(70vw, 860px);
  max-width:860px;
  overflow:auto;
  border-radius:14px;
  border:1px solid var(--border);
  background:#fff;
}
@media (max-width: 900px){
  .month-table-scroll{ width:100%; max-width:100%; }
}

/* TABLE: clear borders */
.month-grid-4{
  width:100%;
  border-collapse:collapse;
  table-layout:fixed;
  font-size:.9rem;
}
.month-grid-4 th, .month-grid-4 td{
  border:1px solid rgba(0,0,0,.12);
  vertical-align:top;
  padding:10px;
}
.month-grid-4 th{
  background:#fafafa;
  font-weight:900;
  text-align:center;
}
.mk-cal-empty{
  background:#fbfbfb;
}

/* CELL */
.mk-cal-cell{
  display:flex;
  flex-direction:column;
  gap:8px;
  min-height:122px;
}
.mk-cal-cell__top{
  display:flex;
  justify-content:space-between;
  gap:10px;
  align-items:baseline;
}
.mk-cal-cell__day{ font-weight:900; font-size:.92rem; }
.mk-cal-cell__date{ font-size:.85rem; }
.mk-cal-kv{ font-size:.86rem; line-height:1.5; }
.mk-cal-kv strong{ font-weight:900; }

/* Market backgrounds */
.market-eke{ background:var(--market-eke); }
.market-orie{ background:var(--market-orie); }
.market-afo{ background:var(--market-afo); }
.market-nkwo{ background:var(--market-nkwo); }

/* Today indicator (always visible) */
.mk-cal-cell--today{
  background:linear-gradient(180deg, rgba(255, 239, 179, .70), rgba(255,255,255,1)) !important;
  outline:2px solid rgba(17,17,17,.45);
  outline-offset:-2px;
}
.mk-cal-today-badge{
  align-self:flex-start;
  font-size:.72rem;
  font-weight:900;
  letter-spacing:.06em;
  padding:4px 8px;
  border-radius:999px;
  border:1px solid rgba(17,17,17,.22);
  background:rgba(255, 239, 179, .95);
}

/* MOBILE */
@media (max-width: 768px){
  .month-grid-4 th, .month-grid-4 td{ padding:8px; }
  .mk-cal-cell{ min-height:118px; }
}
</style>

<script>
(function(){
  var el = document.querySelector('.mk-cal-cell--today');
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
})();
</script>
<?php
  return ob_get_clean();
}
