<?php
declare(strict_types=1);

/**
 * /public_html/app/mkomigbo/private/functions/duplicate_report.php
 *
 * Duplicate report generator (robust, portable):
 * - Works as a callable library function OR as a script.
 * - CLI color output (auto-disabled for web).
 * - CSV + JSON exports with Timestamp + Run ID.
 * - Local execution log with rotation + gzip + retention (keep last 50).
 * - Central structured logging via app_log() from initialize.php.
 *
 * INPUT SHAPE:
 *   $duplicatesBySubject = [
 *     'subject_slug_or_id' => [
 *       ['id' => '123', 'path' => '/subjects/x/pages/y', ...],
 *       ['id' => '123', 'path' => '/subjects/x/pages/z', ...],
 *     ],
 *   ];
 */

require_once __DIR__ . '/../assets/initialize.php';

/* -------------------------------------------------------------------------
 * CLI / Color handling
 * ------------------------------------------------------------------------- */
const COLOR_GREEN  = "\033[32m";
const COLOR_YELLOW = "\033[33m";
const COLOR_RED    = "\033[31m";
const COLOR_RESET  = "\033[0m";
const COLOR_CYAN   = "\033[36m";
const COLOR_WHITE  = "\033[37m";

function dr_is_cli(): bool
{
    return PHP_SAPI === 'cli' || defined('STDIN');
}

function dr_colorize(string $text, string $color): string
{
    return dr_is_cli() ? ($color . $text . COLOR_RESET) : $text;
}

/* -------------------------------------------------------------------------
 * Local file logging: rotate + gzip + retention
 * ------------------------------------------------------------------------- */
function dr_compress_gzip(string $path): void
{
    $gzPath = $path . '.gz';
    $in  = @fopen($path, 'rb');
    $out = @gzopen($gzPath, 'wb9');

    if (!$in || !$out) {
        if (is_resource($in)) fclose($in);
        if (is_resource($out)) gzclose($out);
        return;
    }

    while (!feof($in)) {
        $chunk = fread($in, 1024 * 64);
        if ($chunk === false) break;
        gzwrite($out, $chunk);
    }

    fclose($in);
    gzclose($out);
    @unlink($path);
}

function dr_retention_sweep(string $logDir, int $keep = 50): void
{
    $files = glob($logDir . "/duplicate_report_*.log.gz") ?: [];
    if (count($files) <= $keep) return;

    usort($files, static function ($a, $b) {
        return (filemtime($a) ?: 0) <=> (filemtime($b) ?: 0);
    });

    foreach (array_slice($files, 0, count($files) - $keep) as $file) {
        @unlink($file);
    }
}

function dr_log_status(string $message, string $logFile): void
{
    $maxSizeBytes = 5 * 1024 * 1024; // 5MB
    $dateTag      = date('Ymd_His');
    $logDir       = dirname($logFile);

    if (file_exists($logFile) && filesize($logFile) > $maxSizeBytes) {
        $archiveFile = $logDir . "/duplicate_report_{$dateTag}.log";
        if (@rename($logFile, $archiveFile)) {
            dr_compress_gzip($archiveFile);
            dr_retention_sweep($logDir, 50);
        }
    }

    $entry = "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);

    if (dr_is_cli()) {
        echo dr_colorize($message, COLOR_CYAN) . PHP_EOL;
    }
}

/* -------------------------------------------------------------------------
 * Core generator (callable)
 * ------------------------------------------------------------------------- */
function generate_duplicate_report(array $duplicatesBySubject, array $opts = []): array
{
    // Options
    $logDir = $opts['log_dir'] ?? (realpath(__DIR__ . '/../logs') ?: (__DIR__ . '/../logs'));
    $keep   = isset($opts['retention_keep']) ? (int)$opts['retention_keep'] : 50;

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    $timestamp = date('Ymd_His');
    $runId     = uniqid('run_', true);

    $csvFile = $logDir . "/duplicate_report_{$timestamp}_{$runId}.csv";
    $jsonFile = $logDir . "/duplicate_report_{$timestamp}_{$runId}.json";
    $logFile = $logDir . "/duplicate_report.log";

    app_log('info', 'duplicate_report start', [
        'run_id' => $runId,
        'timestamp' => $timestamp,
        'sapi' => PHP_SAPI,
        'subjects_count' => count($duplicatesBySubject),
    ]);

    dr_log_status("Run started (Timestamp: {$timestamp}, Run ID: {$runId})", $logFile);

    $csvHandle = null;

    try {
        $csvHandle = fopen($csvFile, 'wb');
        if (!$csvHandle) {
            throw new RuntimeException("Failed to open CSV file for writing: {$csvFile}");
        }

        // CSV header
        fputcsv($csvHandle, ['timestamp', 'run_id', 'subject', 'page_id', 'path', 'recommendation']);

        $jsonData = [];

        $totalSubjects   = 0;
        $totalDuplicates = 0;
        $recommendationCounts = [
            'Potential Merge' => 0,
            'Minor Conflict (Auto-rename)' => 0,
            'Potential Reject' => 0,
        ];

        foreach ($duplicatesBySubject as $subject => $duplicates) {
            if (!is_array($duplicates) || $duplicates === []) {
                continue;
            }

            $totalSubjects++;
            $totalDuplicates += count($duplicates);

            if (dr_is_cli()) {
                echo dr_colorize("Subject: {$subject}", COLOR_CYAN) . PHP_EOL;
                echo dr_colorize("Duplicate Pages:", COLOR_WHITE) . PHP_EOL;
                foreach ($duplicates as $page) {
                    $pid  = is_array($page) ? (string)($page['id'] ?? '') : '';
                    $path = is_array($page) ? (string)($page['path'] ?? '') : '';
                    echo "  - Page ID: {$pid}, Path: {$path}" . PHP_EOL;
                }
            }

            // Collect ids + paths
            $allPaths = [];
            $allIds   = [];
            foreach ($duplicates as $page) {
                if (!is_array($page)) continue;
                if (array_key_exists('path', $page)) $allPaths[] = (string)$page['path'];
                if (array_key_exists('id', $page))   $allIds[]   = (string)$page['id'];
            }

            $pathsNonEmpty = array_values(array_filter($allPaths, static fn($p) => trim((string)$p) !== ''));
            $idsNonEmpty   = array_values(array_filter($allIds, static fn($i) => trim((string)$i) !== ''));

            // Heuristics:
            // - Potential Merge: all paths identical (strong signal same thing stored twice)
            // - Minor Conflict: duplicate IDs appear (ID collision) -> auto-rename
            // - Else: Potential Reject
            $uniquePath = (count($pathsNonEmpty) > 0) && (count(array_unique($pathsNonEmpty)) === 1);
            $dupIds     = (count($idsNonEmpty) > 0) && (count($idsNonEmpty) !== count(array_unique($idsNonEmpty)));

            if ($uniquePath) {
                $recommendation = 'Potential Merge';
                if (dr_is_cli()) {
                    echo dr_colorize("  - Potential Merge: identical paths, consider consolidation.", COLOR_YELLOW) . PHP_EOL;
                }
            } elseif ($dupIds) {
                $recommendation = 'Minor Conflict (Auto-rename)';
                if (dr_is_cli()) {
                    echo dr_colorize("  - Minor Conflict: duplicate IDs, auto-rename recommended.", COLOR_GREEN) . PHP_EOL;
                }
            } else {
                $recommendation = 'Potential Reject';
                if (dr_is_cli()) {
                    echo dr_colorize("  - Potential Reject: ambiguous, manual review required.", COLOR_RED) . PHP_EOL;
                }
            }

            $recommendationCounts[$recommendation]++;

            $proposals = [
                'Auto-renaming' => 'Append suffix or sequence to conflicting IDs/paths',
                'Merging'       => 'Consolidate duplicate content into a single canonical page',
                'Rejecting'     => 'Block migration until duplicates are manually resolved',
            ];

            if (dr_is_cli()) {
                echo dr_colorize("  - Auto-renaming: {$proposals['Auto-renaming']}", COLOR_WHITE) . PHP_EOL;
                echo dr_colorize("  - Merging: {$proposals['Merging']}", COLOR_WHITE) . PHP_EOL;
                echo dr_colorize("  - Rejecting: {$proposals['Rejecting']}", COLOR_WHITE) . PHP_EOL;
                echo PHP_EOL;
            }

            // CSV rows
            foreach ($duplicates as $page) {
                $pid  = is_array($page) ? (string)($page['id'] ?? '') : '';
                $path = is_array($page) ? (string)($page['path'] ?? '') : '';
                fputcsv($csvHandle, [$timestamp, $runId, (string)$subject, $pid, $path, $recommendation]);
            }

            // JSON record
            $jsonData[] = [
                'timestamp'      => $timestamp,
                'run_id'         => $runId,
                'subject'        => (string)$subject,
                'duplicates'     => $duplicates,
                'recommendation' => $recommendation,
                'proposals'      => $proposals,
            ];
        }

        fclose($csvHandle);
        $csvHandle = null;

        $jsonOk = @file_put_contents(
            $jsonFile,
            json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
        if ($jsonOk === false) {
            throw new RuntimeException("Failed to write JSON file: {$jsonFile}");
        }

        dr_log_status("Files written: CSV ({$csvFile}), JSON ({$jsonFile})", $logFile);

        app_log('info', 'duplicate_report success', [
            'run_id' => $runId,
            'csvFile' => $csvFile,
            'jsonFile' => $jsonFile,
            'totalSubjects' => $totalSubjects,
            'totalDuplicates' => $totalDuplicates,
            'recommendations' => $recommendationCounts,
        ]);

        // Optional retention sweep for rotated logs
        dr_retention_sweep($logDir, $keep);

        return [
            'ok' => true,
            'run_id' => $runId,
            'timestamp' => $timestamp,
            'files' => [
                'csv' => $csvFile,
                'json' => $jsonFile,
                'log' => $logFile,
            ],
            'summary' => [
                'subjects' => $totalSubjects,
                'duplicates' => $totalDuplicates,
                'recommendations' => $recommendationCounts,
            ],
        ];

    } catch (\Throwable $e) {
        if (is_resource($csvHandle)) {
            fclose($csvHandle);
        }

        app_log('error', 'duplicate_report failed', [
            'run_id' => $runId,
            'type' => get_class($e),
            'code' => $e->getCode(),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        dr_log_status("ERROR: {$e->getMessage()} in {$e->getFile()} on line {$e->getLine()}", $logFile);

        return [
            'ok' => false,
            'run_id' => $runId,
            'timestamp' => $timestamp,
            'error' => APP_DEBUG ? $e->getMessage() : 'internal_error',
            'reference' => defined('REQUEST_ID') ? REQUEST_ID : null,
        ];
    }
}

/* -------------------------------------------------------------------------
 * Script mode (CLI/web): run only if explicitly invoked
 * ------------------------------------------------------------------------- */
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {

    // In script mode, we DO NOT assume $duplicatesBySubject exists.
    // We either accept JSON input (CLI) or return a safe error.
    $duplicatesBySubject = $duplicatesBySubject ?? null;

    // CLI: allow passing a JSON file: php duplicate_report.php /path/to/dupes.json
    if (dr_is_cli() && $duplicatesBySubject === null) {
        $arg = $argv[1] ?? null;
        if ($arg && is_file($arg)) {
            $raw = file_get_contents($arg);
            $decoded = json_decode((string)$raw, true);
            if (is_array($decoded)) {
                $duplicatesBySubject = $decoded;
            }
        }
    }

    if (!is_array($duplicatesBySubject)) {
        app_log('warning', 'duplicate_report invoked without duplicatesBySubject', [
            'sapi' => PHP_SAPI,
            'uri'  => $_SERVER['REQUEST_URI'] ?? null,
        ]);

        if (dr_is_cli()) {
            fwrite(STDERR, "duplicate_report: missing duplicatesBySubject (pass JSON file as argument)\n");
            exit(2);
        }

        http_response_code(400);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => false,
            'error' => 'missing_input',
            'reference' => defined('REQUEST_ID') ? REQUEST_ID : null,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result = generate_duplicate_report($duplicatesBySubject);

    if (dr_is_cli()) {
        echo dr_colorize("Done. Run ID: " . ($result['run_id'] ?? 'n/a'), COLOR_CYAN) . PHP_EOL;
        exit(($result['ok'] ?? false) ? 0 : 1);
    }

    http_response_code(($result['ok'] ?? false) ? 200 : 500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
