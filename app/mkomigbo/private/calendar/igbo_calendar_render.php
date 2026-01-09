<?php
declare(strict_types=1);

require_once __DIR__ . '/IgboCalendarYear.php';

/**
 * Igbo Calendar Renderer
 *
 * Features:
 * - Igbo ↔ Gregorian focus toggle
 * - Search / jump to date
 * - Market-day rhythm visualization
 * - Client-side sorting by Igbo/Gregorian
 * - Highlight today's date automatically
 * - Sticky month headers
 * - Mobile-first responsive layout
 * - Accessible, semantic HTML
 *
 * Rendering ONLY. No business logic.
 */

function render_igbo_calendar(
    DateTimeImmutable $approxStart,
    int $igboYearIndex,
    string $anchorMarketDay = 'Afo'
): string {
    $year = new IgboCalendarYear($approxStart, $igboYearIndex, $anchorMarketDay);

    ob_start();
    ?>
<section class="igbo-calendar-app view-igbo" aria-label="Igbo Calendar">

    <!-- HEADER -->
    <header class="igbo-calendar-header">
        <h1><?= htmlspecialchars($year->getYearLabel(), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="subtitle">
            Hybrid Igbo Lunisolar Calendar · <?= $year->isLeapYear() ? 'Leap Year' : 'Common Year' ?>
        </p>
    </header>

    <!-- CONTROLS -->
    <nav class="calendar-controls" aria-label="Calendar Controls">
        <div class="view-toggle" role="group" aria-label="Toggle view">
            <button type="button" data-view="igbo" class="active" aria-pressed="true">Igbo Focus</button>
            <button type="button" data-view="gregorian" aria-pressed="false">Gregorian Focus</button>
        </div>

        <input
            type="search"
            id="calendar-search"
            placeholder="Search Igbo day (e.g. 12) or Gregorian date (YYYY-MM-DD)"
            aria-label="Search calendar"
        />

        <div class="sort-controls" role="group" aria-label="Sort calendar">
            <button type="button" data-sort="igboDay">Sort by Igbo Day</button>
            <button type="button" data-sort="gregorian">Sort by Gregorian Date</button>
        </div>
    </nav>

    <!-- MONTHS -->
    <div class="months-container">
        <?php foreach ($year->getMonths() as $month): ?>
        <article class="igbo-month-card">

            <header class="month-header">
                <h2><?= htmlspecialchars($month['name'], ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="month-meta">
                    <span class="gloss"><?= htmlspecialchars($month['gloss'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="theme"><?= htmlspecialchars($month['theme'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="gregorian-ref">
                    Gregorian: <?= htmlspecialchars($month['gregorianRef']['start']) ?> – <?= htmlspecialchars($month['gregorianRef']['end']) ?>
                </div>
            </header>

            <table class="month-grid" aria-label="<?= htmlspecialchars($month['name']) ?> month">
                <thead>
                    <tr>
                        <th scope="col">Igbo Day</th>
                        <th scope="col">Gregorian</th>
                        <th scope="col">Weekday</th>
                        <th scope="col">Market</th>
                        <th scope="col">Moon</th>
                        <th scope="col">Phase</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($month['days'] as $day):
                        $isToday = (new DateTimeImmutable())->format('Y-m-d') === $day['gregorian'];
                    ?>
                    <tr
                        class="market-<?= strtolower($day['marketDay']) ?><?= $isToday ? ' today' : '' ?>"
                        data-igbo-day="<?= htmlspecialchars($day['igboDay']) ?>"
                        data-gregorian="<?= htmlspecialchars($day['gregorian']) ?>"
                    >
                        <td><?= htmlspecialchars($day['igboDay']) ?></td>
                        <td><?= htmlspecialchars($day['gregorian']) ?></td>
                        <td><?= htmlspecialchars($day['weekday']) ?></td>
                        <td class="market"><?= htmlspecialchars($day['marketDay']) ?></td>
                        <td><?= htmlspecialchars($day['moonSymbol']) ?></td>
                        <td><?= htmlspecialchars($day['moonStage']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        </article>
        <?php endforeach; ?>
    </div>

</section>

<!-- STYLES -->
<style>
:root {
    --market-eke: #fcfcff;
    --market-orie: #fdfcf9;
    --market-afo: #f9fcf9;
    --market-nkwo: #fcf9f9;
    --highlight-today: #fff3b0;
}

/* BASE STYLES */
.igbo-calendar-app {
    max-width: 1100px;
    margin: auto;
    padding: 16px;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
    background: #fdfdfc;
    color: #222;
}

.igbo-calendar-header {
    text-align: center;
    margin-bottom: 28px;
}

.igbo-calendar-header h1 { margin: 0; font-size: 2rem; }
.subtitle { opacity: 0.75; font-size: 0.95rem; }

/* CONTROLS */
.calendar-controls { display:flex; gap:12px; flex-wrap:wrap; justify-content:center; margin-bottom:32px; }
.view-toggle button, .sort-controls button {
    padding: 6px 16px;
    border-radius: 20px;
    border: 1px solid #ccc;
    background: #fff;
    cursor: pointer;
    font-size: 0.85rem;
    transition: 0.2s;
}
.view-toggle button.active, .sort-controls button.active,
.view-toggle button:focus, .sort-controls button:focus {
    background: #222;
    color: #fff;
    border-color: #222;
    outline: none;
}

.calendar-controls input {
    padding: 7px 12px;
    border-radius: 8px;
    border: 1px solid #ccc;
    min-width: 260px;
    font-size: 0.85rem;
}

/* MONTH CARD */
.igbo-month-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.05);
    margin-bottom: 36px;
    overflow: hidden;
}

.month-header {
    background: #f3f6f8;
    padding: 16px 20px;
    position: sticky;
    top: 0;
    z-index: 2;
}

.month-header h2 { margin: 0; font-size: 1.4rem; }

.month-meta { display:flex; gap:12px; flex-wrap:wrap; font-size:0.85rem; opacity:0.85; }
.gregorian-ref { margin-top:10px; font-size:0.85rem; background:#eef2f4; padding:6px 10px; border-radius:8px; display:inline-block; }

/* TABLE */
.month-grid { width:100%; border-collapse:collapse; font-size:0.85rem; }
.month-grid th, .month-grid td { padding:8px 6px; border-bottom:1px solid #eee; text-align:left; white-space:nowrap; }
.month-grid th { background:#fafafa; font-weight:600; }
.market { font-weight:600; }
.market-eke { background: var(--market-eke); }
.market-orie { background: var(--market-orie); }
.market-afo { background: var(--market-afo); }
.market-nkwo { background: var(--market-nkwo); }
.today { background: var(--highlight-today); font-weight:700; }

/* VIEW MODES */
.view-gregorian .month-grid th:first-child,
.view-gregorian .month-grid td:first-child { display: none; }
.view-igbo .month-grid th:nth-child(2),
.view-igbo .month-grid td:nth-child(2) { display: none; }

/* MOBILE */
@media (max-width:768px){
    .month-grid th:nth-child(3), .month-grid td:nth-child(3),
    .month-grid th:nth-child(6), .month-grid td:nth-child(6) { display:none; }
}
</style>

<!-- INTERACTION -->
<script>
(() => {
    const app = document.querySelector('.igbo-calendar-app');
    const viewButtons = app.querySelectorAll('.view-toggle button');
    const searchInput = document.getElementById('calendar-search');
    const sortButtons = app.querySelectorAll('.sort-controls button');
    const monthGrids = app.querySelectorAll('.month-grid');

    // Toggle View
    viewButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            viewButtons.forEach(b => { b.classList.remove('active'); b.setAttribute('aria-pressed','false'); });
            btn.classList.add('active'); btn.setAttribute('aria-pressed','true');
            app.classList.remove('view-igbo','view-gregorian');
            app.classList.add('view-'+btn.dataset.view);
        });
    });

    // Search Filter
    searchInput.addEventListener('input', () => {
        const q = searchInput.value.trim().toLowerCase();
        monthGrids.forEach(grid => {
            grid.querySelectorAll('tbody tr').forEach(row => {
                const igbo = row.dataset.igboDay.toLowerCase();
                const greg = row.dataset.gregorian.toLowerCase();
                row.style.display = (!q || igbo===q || greg.includes(q)) ? '' : 'none';
            });
        });
    });

    // Sort by Igbo or Gregorian
    sortButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            sortButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const key = btn.dataset.sort;
            monthGrids.forEach(grid => {
                const tbody = grid.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));
                rows.sort((a,b) => {
                    const valA = a.dataset[key].toLowerCase();
                    const valB = b.dataset[key].toLowerCase();
                    return valA.localeCompare(valB, undefined, {numeric:true});
                });
                rows.forEach(r => tbody.appendChild(r));
            });
        });
    });

})();
</script>
<?php
    return ob_get_clean();
}
