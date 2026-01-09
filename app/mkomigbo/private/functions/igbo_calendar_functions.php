<?php
declare(strict_types=1);

/**
 * /private/functions/igbo_calendar_functions.php
 *
 * Igbo Calendar renderer (4 columns) — hardened:
 * - Leap year = Gregorian leap year
 * - Year start = 3rd week Feb new moon window (approx)
 * - 13 months, 28-day months, Month 13 = 29, Leap year Month 1 = 29
 * - Marketday anchor checkpoint: 2026-01-07 = Nkwo
 * - Moon: calibrated illumination + stage + detailed sprite frame (28-frame sheet)
 * - TODAY marked with .mk-cal-cell--today + badge
 */

if (!function_exists('igbo_calendar_render_page')) {

  /* ---------- Public entry ---------- */
  function igbo_calendar_render_page(int $gregYear, array $opts = []): string {
    $todayIso = igbo_get_today_iso($opts);

    // Leap year (Gregorian)
    $isLeap = igbo_is_gregorian_leap_year($gregYear);

    // Month days
    $monthDays = igbo_resolve_month_days($gregYear, $opts, $isLeap);

    // Month names
    $monthNames = [
      1  => 'Ọnwa Mbụ',
      2  => 'Ọnwa Abụọ',
      3  => 'Ọnwa Ife Eke',
      4  => 'Ọnwa Anọ',
      5  => 'Ọnwa Agwụ',
      6  => 'Ọnwa Ifejiọkụ',
      7  => 'Ọnwa Alọm Chi',
      8  => 'Ọnwa Ilo Mmụọ',
      9  => 'Ọnwa Ana',
      10 => 'Ọnwa Okike',
      11 => 'Ọnwa Ajana',
      12 => 'Ọnwa Ede Ajana',
      13 => 'Ọnwa Ụzọ Alụsị',
    ];

    // Market-day anchor
    $anchor = igbo_get_market_anchor($opts);
    $anchor = igbo_anchor_with_epoch($anchor);

    // Preferred new-year marketday
    $preferredStartDay = null;
    if ($gregYear === 2026) {
      $preferredStartDay = 'Orie';
    } elseif (isset($opts['preferred_new_year_marketday']) && is_string($opts['preferred_new_year_marketday']) && trim($opts['preferred_new_year_marketday']) !== '') {
      $preferredStartDay = $opts['preferred_new_year_marketday'];
    }

    // Find year start
    $yearStart = igbo_find_new_year_start($gregYear, $anchor, $preferredStartDay);

    // Build months
    $months = igbo_build_months($yearStart, $monthDays, $monthNames, $todayIso, $anchor);

    // Render
    $out  = '<div class="mk-cal">';
    $out .= '<div class="mk-cal__note muted">'
          . '4-day week: Eke / Orie / Afo / Nkwo. Month start aligns to prior month; blanks are intentional.'
          . '</div>';

    foreach ($months as $m) {
      $out .= igbo_render_month_grid_with_offsets($m, $anchor, $todayIso);
    }

    $out .= '</div>';
    return $out;
  }

  /* ---------- Today ISO ---------- */
  function igbo_get_today_iso(array $opts): string {
    static $tz = null;
    if ($tz === null) $tz = new DateTimeZone('UTC');

    if (isset($opts['today_iso']) && is_string($opts['today_iso']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $opts['today_iso'])) {
      return $opts['today_iso'];
    }

    $g = $GLOBALS['mk_igbo_today_iso'] ?? '';
    if (is_string($g) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $g)) {
      return $g;
    }

    return (new DateTimeImmutable('now', $tz))->format('Y-m-d');
  }

  /* ---------- Month days resolver ---------- */
  function igbo_resolve_month_days(int $gregYear, array $opts, bool $isLeap): array {
    if (isset($opts['month_days']) && is_array($opts['month_days'])) {
      $md = $opts['month_days'];

      // 1-based [1..13]
      $ok1 = true;
      $normalized = [];
      for ($m = 1; $m <= 13; $m++) {
        if (!isset($md[$m])) { $ok1 = false; break; }
        $v = (int)$md[$m];
        if ($v <= 0 || $v > 40) { $ok1 = false; break; }
        $normalized[$m] = $v;
      }
      if ($ok1) return $normalized;

      // 0-based [0..12]
      $ok0 = true;
      $normalized = [];
      for ($i = 0; $i <= 12; $i++) {
        if (!isset($md[$i])) { $ok0 = false; break; }
        $v = (int)$md[$i];
        if ($v <= 0 || $v > 40) { $ok0 = false; break; }
        $normalized[$i + 1] = $v;
      }
      if ($ok0) return $normalized;
    }

    $monthDays = array_fill(1, 13, 28);
    $monthDays[13] = 29;
    if ($isLeap) { $monthDays[1] = 29; }
    return $monthDays;
  }

  /* ---------- Leap year (Gregorian) ---------- */
  function igbo_is_gregorian_leap_year(int $y): bool {
    if ($y % 400 === 0) return true;
    if ($y % 100 === 0) return false;
    return ($y % 4 === 0);
  }

  /* ---------- String lower ---------- */
  function igbo_strlower(string $s): string {
    if (function_exists('mb_strtolower')) return mb_strtolower($s);
    return strtolower($s);
  }

  /* ---------- Marketday helpers ---------- */
  function igbo_marketday_index_from_name(string $name): int {
    $n = igbo_strlower(trim($name));
    if ($n === 'eke') return 0;
    if ($n === 'orie' || $n === 'ọrie' || $n === 'orié') return 1;
    if ($n === 'afo' || $n === 'afọ' || $n === 'afó' || $n === 'afọ') return 2;
    if ($n === 'nkwo' || $n === 'ńkwọ' || $n === 'nkwọ' || $n === 'kwọ') return 3;
    return 0;
  }

  function igbo_marketday_name_from_index(int $idx): string {
    $idx = ($idx % 4 + 4) % 4;
    return ['Eke','Orie','Afo','Nkwo'][$idx];
  }

  function igbo_get_market_anchor(array $opts): array {
    static $tz = null;
    if ($tz === null) $tz = new DateTimeZone('UTC');

    $anchorDate = (string)($opts['market_anchor_date'] ?? ($GLOBALS['mk_market_anchor_date'] ?? ''));
    $anchorDay  = (string)($opts['market_anchor_day']  ?? ($GLOBALS['mk_market_anchor_day']  ?? ''));

    if ($anchorDate !== '' && $anchorDay !== '') {
      try {
        return [
          'date' => new DateTimeImmutable($anchorDate . ' 00:00:00', $tz),
          'day'  => igbo_marketday_index_from_name($anchorDay),
        ];
      } catch (Throwable $e) { /* fall through */ }
    }

    // Fixed checkpoint: 2026-01-07 is Nkwo
    return [
      'date' => new DateTimeImmutable('2026-01-07 00:00:00', $tz),
      'day'  => 3,
    ];
  }

  function igbo_anchor_with_epoch(array $anchor): array {
    $d = $anchor['date'] ?? null;
    if (!($d instanceof DateTimeImmutable)) return $anchor;
    $anchor['epochDays'] = (int)floor($d->getTimestamp() / 86400);
    return $anchor;
  }

  function igbo_marketday_index_for_date(DateTimeImmutable $date, array $anchor): int {
    if (isset($anchor['epochDays'])) {
      $dEpoch = (int)floor($date->getTimestamp() / 86400);
      $diff = $dEpoch - (int)$anchor['epochDays'];
      $idx = ((int)$anchor['day'] + ($diff % 4)) % 4;
      if ($idx < 0) $idx += 4;
      return $idx;
    }

    static $tz = null;
    if ($tz === null) $tz = new DateTimeZone('UTC');

    $aDate = $anchor['date'];
    $aIdx  = (int)$anchor['day'];

    $d0 = new DateTimeImmutable($aDate->format('Y-m-d') . ' 00:00:00', $tz);
    $d1 = new DateTimeImmutable($date->format('Y-m-d') . ' 00:00:00', $tz);

    $diff = (int)(($d1->getTimestamp() - $d0->getTimestamp()) / 86400);
    $idx = ($aIdx + ($diff % 4)) % 4;
    if ($idx < 0) $idx += 4;
    return $idx;
  }

  /* ---------- New year start (3rd week Feb new moon window) ---------- */
  function igbo_find_new_year_start(int $gregYear, array $anchor, ?string $preferredMarketday = null): DateTimeImmutable {
    static $tz = null;
    if ($tz === null) $tz = new DateTimeZone('UTC');

    $start = new DateTimeImmutable(sprintf('%04d-02-15 12:00:00', $gregYear), $tz);
    $end   = new DateTimeImmutable(sprintf('%04d-02-28 12:00:00', $gregYear), $tz);

    $candidates = [];
    for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
      [$ageDays] = igbo_moon_metrics($d);
      if ($ageDays <= 1.25 || $ageDays >= 28.25) {
        $candidates[] = new DateTimeImmutable($d->format('Y-m-d') . ' 00:00:00', $tz);
      }
    }

    if (!empty($candidates) && $preferredMarketday !== null && trim($preferredMarketday) !== '') {
      $want = igbo_marketday_index_from_name($preferredMarketday);
      foreach ($candidates as $cand) {
        if (igbo_marketday_index_for_date($cand, $anchor) === $want) return $cand;
      }
      return $candidates[0];
    }

    if (!empty($candidates)) return $candidates[0];

    return new DateTimeImmutable(sprintf('%04d-02-18 00:00:00', $gregYear), $tz);
  }

  /* ---------- Build months ---------- */
  function igbo_build_months(DateTimeImmutable $yearStart, array $monthDays, array $monthNames, string $todayIso, array $anchor): array {
    $months = [];
    $cursor = $yearStart;

    for ($m = 1; $m <= 13; $m++) {
      $daysInMonth = (int)($monthDays[$m] ?? 28);
      $days = [];

      $monthStart = $cursor;
      for ($dayNo = 1; $dayNo <= $daysInMonth; $dayNo++) {
        $days[] = igbo_day_payload($dayNo, $cursor, $todayIso, $anchor);
        $cursor = $cursor->modify('+1 day');
      }
      $monthEnd = $cursor->modify('-1 day');

      $months[] = [
        'month_no'  => $m,
        'name'      => (string)($monthNames[$m] ?? ('Month ' . $m)),
        'days'      => $daysInMonth,
        'start'     => $monthStart,
        'end'       => $monthEnd,
        'days_data' => $days,
      ];
    }

    return $months;
  }

  /* ---------- Render month grid (offset blanks) ---------- */
  function igbo_render_month_grid_with_offsets(array $m, array $anchor, string $todayIso): string {
    $cols = ['Eke', 'Orie', 'Afo', 'Nkwo'];

    $title = sprintf(
      '%s (Month %d) — %s to %s',
      $m['name'],
      (int)$m['month_no'],
      $m['start']->format('M j, Y'),
      $m['end']->format('M j, Y')
    );

    $days = $m['days_data'];
    $daysInMonth = count($days);

    $startCol = igbo_marketday_index_for_date($m['start'], $anchor);
    $totalCells = $startCol + $daysInMonth;
    $rows = (int)ceil($totalCells / 4);

    $html  = '<section class="mk-cal-month">';
    $html .= '  <div class="mk-cal-month__head">';
    $html .= '    <h2 class="mk-cal-month__title">' . h($title) . '</h2>';
    $html .= '    <div class="muted mk-cal-month__meta">Days: ' . (int)$m['days'] . '</div>';

    $html .= '    <div class="mk-cal-legend" aria-hidden="true">';
    $html .= '      <span class="mk-cal-legend__item"><strong>Eke</strong> = Fire</span>';
    $html .= '      <span class="mk-cal-legend__dot">•</span>';
    $html .= '      <span class="mk-cal-legend__item"><strong>Orie</strong> = Water</span>';
    $html .= '      <span class="mk-cal-legend__dot">•</span>';
    $html .= '      <span class="mk-cal-legend__item"><strong>Afo</strong> = Earth</span>';
    $html .= '      <span class="mk-cal-legend__dot">•</span>';
    $html .= '      <span class="mk-cal-legend__item"><strong>Nkwo</strong> = Air</span>';
    $html .= '    </div>';

    $html .= '  </div>';

    $html .= '  <div class="mk-cal-table-wrap">';
    $html .= '  <table class="mk-cal-grid" role="table" aria-label="Igbo month grid">';
    $html .= '    <thead><tr>';
    foreach ($cols as $c) { $html .= '<th scope="col">' . h($c) . '</th>'; }
    $html .= '</tr></thead><tbody>';

    for ($r = 0; $r < $rows; $r++) {
      $html .= '<tr>';
      for ($c = 0; $c < 4; $c++) {
        $cellNumber = ($r * 4) + $c;

        if ($cellNumber < $startCol) {
          $html .= '<td class="mk-cal-empty">&nbsp;</td>';
          continue;
        }

        $effective = $cellNumber - $startCol;
        if ($effective >= 0 && $effective < $daysInMonth) {
          $d = $days[$effective];
          $iso = (string)($d['date_iso'] ?? '');
          $isToday = ($iso !== '' && $iso === $todayIso);

          $tdClass = $isToday ? ' class="mk-cal-cell--today"' : '';

          $html .= '<td' . $tdClass
                . ' data-gregorian="' . h($iso) . '"'
                . ' data-igbo-day="' . h((string)($d['day_no'] ?? '')) . '">'
                . igbo_render_cell($d, $isToday)
                . '</td>';
        } else {
          $html .= '<td class="mk-cal-empty">&nbsp;</td>';
        }
      }
      $html .= '</tr>';
    }

    $html .= '</tbody></table></div></section>';
    return $html;
  }

  /* ---------- Day payload ---------- */
  function igbo_day_payload(int $dayNo, DateTimeImmutable $date, string $todayIso, array $anchor): array {
    $weekday = $date->format('l');
    [$ageDays, $pct, $stage, $icon, $phaseFrac, $waxing] = igbo_moon_metrics($date);

    $marketIdx = igbo_marketday_index_for_date($date, $anchor);
    $marketName = igbo_marketday_name_from_index($marketIdx);

    $iso = $date->format('Y-m-d');

    // 28-frame sprite index (0..27)
    $frame = igbo_moon_sprite_frame_from_phase($phaseFrac);

    return [
      'day_no'       => $dayNo,
      'date_iso'     => $iso,
      'date_label'   => $date->format('M j'),
      'weekday'      => $weekday,
      'market_day'   => $marketName,

      'moon_pct'     => $pct,
      'moon_stage'   => $stage,
      'moon_icon'    => $icon,
      'moon_age'     => $ageDays,

      'moon_phase_frac' => $phaseFrac,
      'moon_waxing'     => $waxing,
      'moon_frame'      => $frame,

      'is_today'     => ($iso === $todayIso),
    ];
  }

  /* ---------- Render cell ---------- */
  function igbo_render_cell(array $d, bool $isToday): string {
    $dayNo     = (int)($d['day_no'] ?? 0);
    $dateIso   = (string)($d['date_iso'] ?? '');
    $weekday   = (string)($d['weekday'] ?? '');
    $marketDay = (string)($d['market_day'] ?? '');

    $moonPct   = (int)($d['moon_pct'] ?? 0);
    $moonStage = (string)($d['moon_stage'] ?? '');
    $moonIcon  = (string)($d['moon_icon'] ?? '◑');

    $frame     = (int)($d['moon_frame'] ?? 0);
    $frame     = max(0, min(27, $frame));

    $moonPct = max(0, min(100, $moonPct));
    $cellClass = 'mk-cal-cell' . ($isToday ? ' mk-cal-cell--today' : '');

    $html  = '<div class="' . h($cellClass) . '">';

    $html .= '  <div class="mk-cal-cell__top">';
    $html .= '    <div class="mk-cal-cell__day">Day ' . $dayNo . '</div>';
    $html .= '    <div class="mk-cal-cell__date muted">' . h($dateIso) . '</div>';
    $html .= '  </div>';

    if ($isToday) {
      $html .= '  <div class="mk-cal-today-badge" aria-label="Today">TODAY</div>';
    }

    // Moon sprite (detailed), with emoji fallback if CSS/image unavailable
    $sprite = '<span class="mk-moon-sprite mk-moon-f' . $frame . '" aria-hidden="true"></span>';
    $fallback = '<span class="mk-moon" aria-hidden="true">' . h($moonIcon) . '</span>';

    $html .= '  <div class="mk-cal-kv">';
    $html .= '    <div><strong>Weekday:</strong> ' . h($weekday) . '</div>';
    $html .= '    <div><strong>Market:</strong> ' . h($marketDay) . '</div>';
    $html .= '    <div><strong>Moon:</strong> ' . $sprite . ' <span class="mk-moon">' . $fallback . '</span> ' . $moonPct . '%</div>';
    $html .= '    <div><strong>Stage:</strong> ' . h($moonStage) . '</div>';
    $html .= '  </div>';

    $html .= '  <div class="mk-moonbar" aria-hidden="true"><span style="width:' . $moonPct . '%"></span></div>';
    $html .= '</div>';

    return $html;
  }

  /* ---------- Moon metrics (calibrated; single knob) ---------- */
  function igbo_moon_metrics(DateTimeImmutable $date): array {
    // Returns:
    // [ageDays, illumPct, stageLabel, icon, phaseFrac(0..1), waxing(bool)]
    $tz = new DateTimeZone('UTC');
    $dt = $date->setTimezone($tz)->setTime(12, 0, 0);

    $jd = igbo_julian_day($dt);

    // The only permanent knob:
    $cal = 0.0;
    if (isset($GLOBALS['mk_moon_calibration_days']) && is_numeric($GLOBALS['mk_moon_calibration_days'])) {
      $cal = (float)$GLOBALS['mk_moon_calibration_days'];
    }
    $jd += $cal;

    $d = $jd - 2451545.0; // days since J2000.0

    // Sun (approx)
    $g = deg2rad(igbo_norm_deg(357.529 + 0.98560028 * $d));
    $q = deg2rad(igbo_norm_deg(280.459 + 0.98564736 * $d));
    $Lsun = $q + deg2rad(1.915) * sin($g) + deg2rad(0.020) * sin(2 * $g);

    // Moon (approx)
    $L0 = deg2rad(igbo_norm_deg(218.316 + 13.176396 * $d));
    $Mm = deg2rad(igbo_norm_deg(134.963 + 13.064993 * $d));

    $Lmoon = $L0
      + deg2rad(6.289) * sin($Mm)
      + deg2rad(1.274) * sin(2 * ($L0 - $Lsun) - $Mm)
      + deg2rad(0.658) * sin(2 * ($L0 - $Lsun))
      + deg2rad(0.214) * sin(2 * $Mm)
      + deg2rad(0.110) * sin($L0 - $Lsun);

    // Elongation
    $D = igbo_wrap_pi($Lmoon - $Lsun);

    // Illumination fraction k = (1 - cos(D))/2
    $k = (1 - cos($D)) / 2;
    $illumPct = (int)round($k * 100);

    // Waxing/waning by one-day lookahead (stable enough for labels)
    $jd2 = $jd + 1.0;
    $d2  = ($jd2 - 2451545.0);

    $g2 = deg2rad(igbo_norm_deg(357.529 + 0.98560028 * $d2));
    $q2 = deg2rad(igbo_norm_deg(280.459 + 0.98564736 * $d2));
    $Lsun2 = $q2 + deg2rad(1.915) * sin($g2) + deg2rad(0.020) * sin(2 * $g2);

    $L02 = deg2rad(igbo_norm_deg(218.316 + 13.176396 * $d2));
    $Mm2 = deg2rad(igbo_norm_deg(134.963 + 13.064993 * $d2));

    $Lmoon2 = $L02
      + deg2rad(6.289) * sin($Mm2)
      + deg2rad(1.274) * sin(2 * ($L02 - $Lsun2) - $Mm2)
      + deg2rad(0.658) * sin(2 * ($L02 - $Lsun2))
      + deg2rad(0.214) * sin(2 * $Mm2)
      + deg2rad(0.110) * sin($L02 - $Lsun2);

    $D2 = igbo_wrap_pi($Lmoon2 - $Lsun2);

    // If abs elongation is increasing, we consider waxing; otherwise waning.
    $waxing = (abs($D2) > abs($D));

    $stage = igbo_moon_stage_from_illum($illumPct, $waxing);
    $icon  = igbo_moon_icon_from_stage($stage);

    // Phase fraction 0..1 mapped from elongation:
    // 0 ~ new, 0.5 ~ full, 1 ~ back to new
    $phaseFrac = igbo_norm_rad($D) / (2 * M_PI);
    if ($phaseFrac < 0) $phaseFrac += 1;

    // Display age days (approx)
    $ageDays = $phaseFrac * 29.53058867;

    return [$ageDays, $illumPct, $stage, $icon, $phaseFrac, $waxing];
  }

  /* ---------- 28-frame sprite mapping (0..27) ---------- */
  function igbo_moon_sprite_frame_from_phase(float $phaseFrac): int {
    $phaseFrac = max(0.0, min(1.0, $phaseFrac));
    $frame = (int)round($phaseFrac * 27.0);

    // Optional offset (only if your sprite starts at a different phase tile)
    $off = 0;
    if (isset($GLOBALS['mk_moon_sprite_offset']) && is_numeric($GLOBALS['mk_moon_sprite_offset'])) {
      $off = (int)$GLOBALS['mk_moon_sprite_offset'];
    }
    $frame = ($frame + $off) % 28;
    if ($frame < 0) $frame += 28;

    return $frame;
  }

  /* --- helpers for moon metrics --- */
  function igbo_julian_day(DateTimeImmutable $dt): float {
    $y = (int)$dt->format('Y');
    $m = (int)$dt->format('m');
    $D = (int)$dt->format('d');

    $h = (int)$dt->format('H');
    $i = (int)$dt->format('i');
    $s = (int)$dt->format('s');

    $dayFrac = ($h + ($i / 60) + ($s / 3600)) / 24;

    if ($m <= 2) { $y -= 1; $m += 12; }
    $A = intdiv($y, 100);
    $B = 2 - $A + intdiv($A, 4);

    $jd = floor(365.25 * ($y + 4716))
        + floor(30.6001 * ($m + 1))
        + $D + $B - 1524.5 + $dayFrac;

    return (float)$jd;
  }

  function igbo_norm_deg(float $deg): float {
    $x = fmod($deg, 360.0);
    if ($x < 0) $x += 360.0;
    return $x;
  }

  function igbo_norm_rad(float $rad): float {
    $x = fmod($rad, 2 * M_PI);
    if ($x < 0) $x += 2 * M_PI;
    return $x;
  }

  function igbo_wrap_pi(float $rad): float {
    $x = fmod($rad + M_PI, 2 * M_PI);
    if ($x < 0) $x += 2 * M_PI;
    return $x - M_PI;
  }

  function igbo_moon_stage_from_illum(int $pct, bool $waxing): string {
    if ($pct <= 3)  return 'New Moon';
    if ($pct < 50)  return $waxing ? 'Waxing Crescent' : 'Waning Crescent';
    if ($pct === 50) return $waxing ? 'First Quarter' : 'Last Quarter';
    if ($pct < 97)  return $waxing ? 'Waxing Gibbous' : 'Waning Gibbous';
    return 'Full Moon';
  }

  function igbo_moon_icon_from_stage(string $stage): string {
    switch ($stage) {
      case 'New Moon': return '🌑';
      case 'Waxing Crescent': return '🌒';
      case 'First Quarter': return '🌓';
      case 'Waxing Gibbous': return '🌔';
      case 'Full Moon': return '🌕';
      case 'Waning Gibbous': return '🌖';
      case 'Last Quarter': return '🌗';
      case 'Waning Crescent': return '🌘';
      default: return '🌙';
    }
  }

  /* ---------- Escape helper ---------- */
  if (!function_exists('h')) {
    function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
  }
}
