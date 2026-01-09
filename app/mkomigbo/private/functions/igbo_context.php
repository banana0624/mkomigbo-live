<?php
declare(strict_types=1);

/**
 * /private/functions/igbo_context.php
 *
 * Igbo Calendar Context Engine
 * ---------------------------
 * Single responsibility:
 *   Given a Gregorian date, return the cultural / cosmological context.
 *
 * This file:
 * - DOES NOT render HTML
 * - DOES NOT mutate globals
 * - IS SAFE to call anywhere (public or staff)
 *
 * Depends on:
 * - igbo_calendar_functions.php (market day + moon engine)
 */

/**
 * Primary public API
 *
 * @return array{
 *   date_iso: string,
 *   market_day: string,
 *   market_index: int,
 *   element: string,
 *   element_symbol: string,
 *   moon_pct: int,
 *   moon_stage: string
 * }
 */
function igbo_context_for_date(DateTimeImmutable $date): array
{
    static $tz = null;
    if ($tz === null) {
        $tz = new DateTimeZone('UTC');
    }

    // Normalize to UTC midnight (calendar-safe)
    $d = $date->setTimezone($tz)->setTime(0, 0, 0);
    $iso = $d->format('Y-m-d');

    /* -----------------------------------------
       Market day (checkpoint-based)
       ----------------------------------------- */

    // Hard anchor (single source of truth)
    // 2026-01-07 = Nkwo
    $anchorDate = new DateTimeImmutable('2026-01-07 00:00:00', $tz);
    $anchorIdx  = 3; // Nkwo

    $epochAnchor = (int) floor($anchorDate->getTimestamp() / 86400);
    $epochNow    = (int) floor($d->getTimestamp() / 86400);

    $diffDays = $epochNow - $epochAnchor;
    $marketIdx = ($anchorIdx + ($diffDays % 4)) % 4;
    if ($marketIdx < 0) {
        $marketIdx += 4;
    }

    $marketNames = ['Eke', 'Orie', 'Afo', 'Nkwo'];
    $marketDay   = $marketNames[$marketIdx];

    /* -----------------------------------------
       Element mapping (Igbo cosmology)
       ----------------------------------------- */

    $elements = [
        0 => ['Fire',  '🔥'],
        1 => ['Water', '💧'],
        2 => ['Earth', '🌍'],
        3 => ['Air',   '🌬️'],
    ];

    [$elementName, $elementSymbol] = $elements[$marketIdx];

    /* -----------------------------------------
       Moon metrics (delegated to locked engine)
       ----------------------------------------- */

    if (!function_exists('igbo_moon_metrics')) {
        // Absolute safety fallback
        $moonPct   = 0;
        $moonStage = 'Unknown';
    } else {
        [, $moonPct, $moonStage] = igbo_moon_metrics($d);
        $moonPct = max(0, min(100, (int)$moonPct));
    }

    /* -----------------------------------------
       Final immutable context payload
       ----------------------------------------- */

    return [
        'date_iso'       => $iso,
        'market_day'     => $marketDay,
        'market_index'   => $marketIdx,
        'element'        => $elementName,
        'element_symbol' => $elementSymbol,
        'moon_pct'       => $moonPct,
        'moon_stage'     => $moonStage,
    ];
}
