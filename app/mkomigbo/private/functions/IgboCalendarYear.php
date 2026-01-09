<?php
declare(strict_types=1);

/**
 * /private/functions/IgboCalendarYear.php
 *
 * Data-model for an Igbo Calendar year.
 *
 * Rules:
 * - 4 market days: Eke, Orie, Afo, Nkwo (cycle every 4 days)
 * - Months 1..12 = 28 days
 * - Month 13 = 29 days (365)
 * - Gregorian leap year: Month 1 = 29 and Month 13 = 29 (366)
 * - Igbo day number resets each month (1..28/29)
 *
 * Marketday checkpoint anchor (confirmed truth):
 * - 2026-01-07 = Nkwo
 *
 * Notes:
 * - New-year selection (3rd week of Feb / first new moon) is not decided here.
 *   You pass the chosen start date as $approxStart.
 */

final class IgboCalendarYear
{
  private DateTimeImmutable $start;
  private int $yearIndex;
  private bool $isLeap;

  /** @var array<int,array<string,mixed>> */
  private array $months = [];

  public function __construct(DateTimeImmutable $approxStart, int $igboYearIndex, string $anchorMarketDay = 'Nkwo')
  {
    // $anchorMarketDay kept only for signature compatibility
    unset($anchorMarketDay);

    $this->start = $approxStart->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0, 0);
    $this->yearIndex = $igboYearIndex;

    // Leap year is determined by the Gregorian year of the start date
    $gY = (int)$this->start->format('Y');
    $this->isLeap = self::isGregorianLeapYear($gY);

    $this->buildMonths();
  }

  public function isLeapYear(): bool
  {
    return $this->isLeap;
  }

  public function getYearLabel(): string
  {
    // You can adjust label semantics later if you introduce an independent Igbo year index.
    $gY = (int)$this->start->format('Y');
    return "Igbo Calendar · {$gY}";
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  public function getMonths(): array
  {
    return $this->months;
  }

  /* -----------------------------
     Core helpers
  ----------------------------- */

  private static function isGregorianLeapYear(int $y): bool
  {
    if ($y % 400 === 0) return true;
    if ($y % 100 === 0) return false;
    return ($y % 4 === 0);
  }

  private static function marketNameFromIndex(int $idx): string
  {
    $idx = ($idx % 4 + 4) % 4;
    return ['Eke','Orie','Afo','Nkwo'][$idx];
  }

  private static function marketIndexForDate(DateTimeImmutable $date): int
  {
    // Fixed checkpoint: 2026-01-07 = Nkwo
    $tz = new DateTimeZone('UTC');
    $anchorDate = new DateTimeImmutable('2026-01-07 00:00:00', $tz);
    $anchorIdx  = 3;

    $d0 = new DateTimeImmutable($anchorDate->format('Y-m-d') . ' 00:00:00', $tz);
    $d1 = new DateTimeImmutable($date->format('Y-m-d') . ' 00:00:00', $tz);

    $diff = (int)(($d1->getTimestamp() - $d0->getTimestamp()) / 86400);
    $idx = ($anchorIdx + ($diff % 4)) % 4;
    if ($idx < 0) $idx += 4;
    return $idx;
  }

  /**
   * Moon metrics: simple 8-phase icon + stage using synodic month approximation.
   * Returns: [ageDays, pct, stage, icon]
   */
  private static function moonMetrics(DateTimeImmutable $date): array
  {
    $synodic = 29.53058867;

    $tz = new DateTimeZone('UTC');
    $utc = $date->setTimezone($tz)->setTime(12, 0, 0);

    $epoch = new DateTimeImmutable('2000-01-06 18:14:00', $tz);

    $days = ($utc->getTimestamp() - $epoch->getTimestamp()) / 86400.0;
    $age = fmod($days, $synodic);
    if ($age < 0) $age += $synodic;

    $pct = (int)round(($age / $synodic) * 100);

    $phaseIndex = (int)floor(($age / $synodic) * 8);
    if ($phaseIndex < 0) $phaseIndex = 0;
    if ($phaseIndex > 7) $phaseIndex = 7;

    $stages = [
      'New Moon',
      'Waxing Crescent',
      'First Quarter',
      'Waxing Gibbous',
      'Full Moon',
      'Waning Gibbous',
      'Last Quarter',
      'Waning Crescent',
    ];
    $icons = ['🌑','🌒','🌓','🌔','🌕','🌖','🌗','🌘'];

    return [$age, $pct, $stages[$phaseIndex], $icons[$phaseIndex]];
  }

  private function buildMonths(): void
  {
    $monthDays = array_fill(1, 13, 28);
    $monthDays[13] = 29;
    if ($this->isLeap) $monthDays[1] = 29;

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

    $tz = new DateTimeZone('UTC');
    $todayIso = (new DateTimeImmutable('now', $tz))->format('Y-m-d');

    $cursor = $this->start;
    $this->months = [];

    for ($m = 1; $m <= 13; $m++) {
      $daysInMonth = (int)$monthDays[$m];
      $days = [];
      $monthStart = $cursor;

      for ($d = 1; $d <= $daysInMonth; $d++) {
        $greg = $cursor->format('Y-m-d');

        $marketIdx = self::marketIndexForDate($cursor);
        $marketDay = self::marketNameFromIndex($marketIdx);

        [, , $moonStage, $moonSymbol] = self::moonMetrics($cursor);

        $days[] = [
          'igboDay'     => (string)$d,
          'gregorian'   => $greg,
          'weekday'     => $cursor->format('l'),
          'marketDay'   => $marketDay,
          'moonSymbol'  => $moonSymbol,
          'moonStage'   => $moonStage,
          'isToday'     => ($greg === $todayIso),
        ];

        $cursor = $cursor->modify('+1 day');
      }

      $monthEnd = $cursor->modify('-1 day');

      $this->months[] = [
        'monthNo' => $m,
        'name' => $monthNames[$m] ?? ('Month ' . $m),
        'gloss' => '',
        'theme' => '',
        'gregorianRef' => [
          'start' => $monthStart->format('Y-m-d'),
          'end'   => $monthEnd->format('Y-m-d'),
        ],
        'days' => $days,
      ];
    }
  }
}
