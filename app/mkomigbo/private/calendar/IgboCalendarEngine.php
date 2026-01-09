<?php
declare(strict_types=1);

/**
 * IgboCalendarEngine
 *
 * HYBRID LUNISOLAR AUTHORITY
 * -------------------------
 * - Ọnwa Mbụ (Month 1) begins on the observed New Moon.
 * - Months 1–12 have 28 days.
 * - Month 13 has 29 days; every 3rd Igbo year it has 30 days.
 * - Gregorian calendar is used ONLY as a reference overlay.
 * - Gregorian leap years have NO influence on Igbo leap correction.
 *
 * This engine is logic-only and UI-agnostic.
 */

final class IgboCalendarEngine
{
    /** Astronomical constants */
    private const SYNODIC_MONTH = 29.53058867;

    /** Reference new moon: 2000-01-06 18:14 UTC */
    private const REF_NEW_MOON = '2000-01-06 18:14:00';

    /** Leap correction cycle (years) */
    private const LEAP_CYCLE = 3;

    /** Anchor (first implemented year) */
    private const ANCHOR_YEAR = 2025;

    /** Month structure */
    private const MONTHS_IN_YEAR = 13;
    private const STANDARD_DAYS  = 28;
    private const LAST_MONTH_DAYS = 29;

    /**
     * Determine if an Igbo year is a leap year.
     * Every 3rd year adds one extra day to Month 13.
     */
    public static function isLeapYear(int $igboYearIndex): bool
    {
        return (($igboYearIndex - self::ANCHOR_YEAR) % self::LEAP_CYCLE) === 0;
    }

    /**
     * Find the observed New Moon nearest a target date.
     * Used ONLY to anchor Ọnwa Mbụ.
     */
    public static function alignToNewMoon(
        DateTimeImmutable $target,
        int $windowDays = 5
    ): DateTimeImmutable {
        $bestDate  = $target;
        $bestPhase = 1.0;

        for ($i = -$windowDays; $i <= $windowDays; $i++) {
            $candidate = $target->modify(($i >= 0 ? '+' : '') . $i . ' days');
            $phase = self::moonPhaseFraction($candidate);

            if ($phase < $bestPhase) {
                $bestPhase = $phase;
                $bestDate  = $candidate;
            }
        }

        return $bestDate;
    }

    /**
     * Moon phase fraction (0 = New Moon, 0.5 = Full Moon).
     */
    public static function moonPhaseFraction(DateTimeImmutable $date): float
    {
        $ref = new DateTimeImmutable(self::REF_NEW_MOON, new DateTimeZone('UTC'));
        $dt  = $date->setTime(12, 0)->setTimezone(new DateTimeZone('UTC'));

        $days = ($dt->getTimestamp() - $ref->getTimestamp()) / 86400.0;
        $age  = fmod($days, self::SYNODIC_MONTH);
        if ($age < 0) {
            $age += self::SYNODIC_MONTH;
        }

        return $age / self::SYNODIC_MONTH;
    }

    /**
     * Get moon phase metadata for display/reference.
     */
    public static function moonPhaseInfo(DateTimeImmutable $date): array
    {
        $phase = self::moonPhaseFraction($date);
        $illum = 0.5 * (1 - cos(2 * M_PI * $phase));

        $buckets = [
            ['🌑', 'New Moon'],
            ['🌒', 'Waxing Crescent'],
            ['🌓', 'First Quarter'],
            ['🌔', 'Waxing Gibbous'],
            ['🌕', 'Full Moon'],
            ['🌖', 'Waning Gibbous'],
            ['🌗', 'Last Quarter'],
            ['🌘', 'Waning Crescent'],
        ];

        $index = (int)floor($phase * 8);
        if ($index > 7) {
            $index = 7;
        }

        return [
            'symbol' => $buckets[$index][0],
            'stage'  => $buckets[$index][1],
            'illum'  => (int)round($illum * 100),
            'phase'  => $phase,
        ];
    }

    /**
     * Get the number of days in a given Igbo month.
     */
    public static function daysInMonth(int $monthIndex, bool $isLeapYear): int
    {
        if ($monthIndex < 13) {
            return self::STANDARD_DAYS;
        }

        return self::LAST_MONTH_DAYS + ($isLeapYear ? 1 : 0);
    }

    /**
     * Build the Gregorian reference span for a month.
     */
    public static function gregorianSpan(
        DateTimeImmutable $start,
        int $daysInMonth
    ): array {
        $end = $start->modify('+' . ($daysInMonth - 1) . ' days');

        return [
            'start' => $start->format('M d, Y'),
            'end'   => $end->format('M d, Y'),
        ];
    }

    /**
     * Format Igbo year display name.
     */
    public static function igboYearLabel(DateTimeImmutable $yearStart): string
    {
        $startYear = (int)$yearStart->format('Y');
        $endYear   = (int)$yearStart->modify('+11 months')->format('Y');

        return "Igbo Year {$startYear}/{$endYear}";
    }
}
