<?php
declare(strict_types=1);

/**
 * project-root/public/platforms/igbo-calendar.php
 * Public: Igbo Calendar page
 *
 * Requires:
 *   private/functions/igbo_calendar_functions.php
 *   function igbo_calendar_render_page()
 */

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

/*
|--------------------------------------------------------------------------
| Locate initialize.php (walk upward, bounded)
|--------------------------------------------------------------------------
*/
$baseDir = __DIR__;
$initFile = null;
$scanDir = $baseDir;

for ($i = 0; $i < 12; $i++) {
    $candidate = $scanDir . '/private/assets/initialize.php';
    if (is_file($candidate)) {
        $initFile = $candidate;
        break;
    }

    $parent = dirname($scanDir);
    if ($parent === $scanDir) {
        break;
    }
    $scanDir = $parent;
}

if ($initFile) {
    require_once $initFile;
}

/*
|--------------------------------------------------------------------------
| Safe helper fallbacks
|--------------------------------------------------------------------------
*/
if (!function_exists('h')) {
    function h(string $s = ''): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url_for')) {
    function url_for(string $path): string {
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        $root = defined('WWW_ROOT') ? rtrim((string) WWW_ROOT, '/') : '';
        return $root . $path;
    }
}

/*
|--------------------------------------------------------------------------
| Load Igbo calendar functions
|--------------------------------------------------------------------------
*/
$calendarFunctions = null;

if (defined('PRIVATE_PATH')) {
    $candidate = rtrim((string) PRIVATE_PATH, DIRECTORY_SEPARATOR)
        . '/functions/igbo_calendar_functions.php';
    if (is_file($candidate)) {
        $calendarFunctions = $candidate;
    }
}

if (!$calendarFunctions) {
    $candidate = $baseDir . '/private/functions/igbo_calendar_functions.php';
    if (is_file($candidate)) {
        $calendarFunctions = $candidate;
    }
}

if ($calendarFunctions) {
    require_once $calendarFunctions;
}

$title = 'Igbo Calendar';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> • Mkomigbo</title>

    <link rel="stylesheet" href="<?= h(url_for('/lib/css/ui.css')) ?>">
    <link rel="stylesheet" href="<?= h(url_for('/lib/css/subjects.css')) ?>">
</head>
<body>

<?php
/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/
$usedHeader = false;

if (defined('SHARED_PATH')) {
    $header = rtrim((string) SHARED_PATH, DIRECTORY_SEPARATOR) . '/header.php';
    if (is_file($header)) {
        include $header;
        $usedHeader = true;
    }
}

if (!$usedHeader):
?>
<div style="max-width:1200px;margin:0 auto;padding:16px">
    <strong>Mkomigbo</strong> · <a href="<?= h(url_for('/')) ?>">Home</a>
</div>
<?php endif; ?>

<main style="max-width:1200px;margin:0 auto;padding:16px">
    <h1><?= h($title) ?></h1>

<?php
/*
|--------------------------------------------------------------------------
| Calendar Rendering
|--------------------------------------------------------------------------
*/
if (!function_exists('igbo_calendar_render_page')) {
    ?>
    <div style="padding:12px;border:1px solid #e07a7a;background:#ffecec;border-radius:12px">
        <strong>Calendar renderer missing.</strong><br>
        The function <code>igbo_calendar_render_page()</code> was not found.
        Confirm that <code>private/functions/igbo_calendar_functions.php</code>
        has been replaced with the correct full version.
    </div>
    <?php
} else {
    try {
        echo igbo_calendar_render_page();
    } catch (Throwable $e) {
        ?>
        <div style="padding:12px;border:1px solid #e07a7a;background:#ffecec;border-radius:12px">
            <strong>Calendar error:</strong>
            <pre style="white-space:pre-wrap;margin:8px 0 0;"><?= h($e->getMessage()) ?></pre>
        </div>
        <?php
    }
}
?>
</main>

<?php
/*
|--------------------------------------------------------------------------
| Footer
|--------------------------------------------------------------------------
*/
$usedFooter = false;

if (defined('SHARED_PATH')) {
    $footer = rtrim((string) SHARED_PATH, DIRECTORY_SEPARATOR) . '/footer.php';
    if (is_file($footer)) {
        include $footer;
        $usedFooter = true;
    }
}

if (!$usedFooter):
?>
<div style="max-width:1200px;margin:0 auto;padding:16px;opacity:.75">
    <hr>
    <small>&copy; <?= date('Y') ?> Mkomigbo</small>
</div>
<?php endif; ?>

</body>
</html>
