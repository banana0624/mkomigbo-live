<?php
declare(strict_types=1);

require_once __DIR__ . '/IgboCalendarEngine.php';

/**
 * IgboCalendarYear
 *
 * Builds a complete Igbo year using the Hybrid Lunisolar model.
 * Produces structured, visitor-friendly data for rendering or apps.
 */

final class IgboCalendarYear
{
    private DateTimeImmutable $yearStart;
    private int $igboYearIndex;
    private bool $isLeapYear;
    private array $months = [];

    /** Market days in canonical order */
    private array $marketCycle = ['Eke', 'Orie', 'Afo', 'Nkwo'];

    /** Month registry with refined naming */
    private array $monthRegistry = [
        1  => ['name' => 'Ọnwa Mbụ',        'gloss' => 'First Moon',        'theme' => 'New beginnings'],
        2  => ['name' => 'Ọnwa Abụọ',      'gloss' => 'Second Moon',       'theme' => 'Stability'],
        3  => ['name' => 'Ọnwa Ife Eke',   'gloss' => 'Light of Eke',       'theme' => 'Awakening'],
        4  => ['name' => 'Ọnwa Anọ',       'gloss' => 'Fourth Moon',       'theme' => 'Growth'],
        5  => ['name' => 'Ọnwa Agwụ',      'gloss' => 'Moon of Agwụ',      'theme' => 'Spiritual insight'],
        6  => ['name' => 'Ọnwa Ifejiọkụ',  'gloss' => 'Yam Deity Moon',     'theme' => 'Agriculture'],
        7  => ['name' => 'Ọnwa Alọm Chi',  'gloss' => 'Personal Spirit',   'theme' => 'Reflection'],
        8  => ['name' => 'Ọnwa Ilo Mmụọ',  'gloss' => 'Spirits Retreat',   'theme' => 'Cleansing'],
        9  => ['name' => 'Ọnwa Ana',       'gloss' => 'Earth Moon',        'theme' => 'Grounding'],
        10 => ['name' => 'Ọnwa Okike',     'gloss' => 'Creation',          'theme' => 'Renewal'],
        11 => ['name' => 'Ọnwa Ajana',     'gloss' => 'Harvest Cleansing', 'theme' => 'Harvest'],
        12 => ['name' => 'Ọnwa Ede Ajana', 'gloss' => 'End of Ajana',      'theme' => 'Completion'],
        13 => ['name' => 'Ọnwa Ụzọ Alụsị', 'gloss' => 'Path of Deities',   'theme' => 'Transition'],
    ];

    /**
     * Constructor
     *
     * @param DateTimeImmutable $approxStart  Approximate Gregorian start (Feb)
     * @param int $igboYearIndex              Logical Igbo year index (e.g. 2025)
     * @param string $anchorMarketDay         Market day on Ọnwa Mbụ Day 1
     */
    public function __construct(
        DateTimeImmutable $approxStart,
        int $igboYearIndex,
        string $anchorMarketDay = 'Afo'
    ) {
        $this->igboYearIndex = $igboYearIndex;
        $this->isLeapYear    = IgboCalendarEngine::isLeapYear($igboYearIndex);

        // Align Ọnwa Mbụ to observed New Moon
        $this->yearStart = IgboCalendarEngine::alignToNewMoon($approxStart);

        $this->buildYear($anchorMarketDay);
    }

    /**
     * Build all months and days.
     */
    private function buildYear(string $anchorMarket): void
    {
        $currentDate = $this->yearStart;
        $marketIndex = array_search($anchorMarket, $this->marketCycle, true);
        if ($marketIndex === false) {
            $marketIndex = 0;
        }

        for ($m = 1; $m <= 13; $m++) {
            $daysInMonth = IgboCalendarEngine::daysInMonth($m, $this->isLeapYear);
            $monthMeta   = $this->monthRegistry[$m];

            $days = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $moon = IgboCalendarEngine::moonPhaseInfo($currentDate);

                $days[] = [
                    'igboDay'     => $d,
                    'gregorian'  => $currentDate->format('Y-m-d'),
                    'weekday'    => $currentDate->format('l'),
                    'marketDay'  => $this->marketCycle[$marketIndex],
                    'moonSymbol' => $moon['symbol'],
                    'moonStage'  => $moon['stage'],
                    'illum'      => $moon['illum'],
                ];

                // Advance cycles
                $currentDate = $currentDate->modify('+1 day');
                $marketIndex = ($marketIndex + 1) % 4;
            }

            $gregSpan = IgboCalendarEngine::gregorianSpan(
                $currentDate->modify('-' . $daysInMonth . ' days'),
                $daysInMonth
            );

            $this->months[] = [
                'index'        => $m,
                'name'         => $monthMeta['name'],
                'gloss'        => $monthMeta['gloss'],
                'theme'        => $monthMeta['theme'],
                'daysInMonth'  => $daysInMonth,
                'gregorianRef' => $gregSpan,
                'days'         => $days,
            ];
        }
    }

    /**
     * Public accessors
     */
    public function getYearLabel(): string
    {
        return IgboCalendarEngine::igboYearLabel($this->yearStart);
    }

    public function isLeapYear(): bool
    {
        return $this->isLeapYear;
    }

    public function getMonths(): array
    {
        return $this->months;
    }

    public function getYearStart(): DateTimeImmutable
    {
        return $this->yearStart;
    }
}
