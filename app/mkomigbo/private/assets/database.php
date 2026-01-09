<?php
declare(strict_types=1);

/**
 * /private/assets/database.php
 *
 * Database layer (single responsibility):
 * - Provide a cached PDO handle via db(): PDO
 * - Optionally provide a cached mysqli handle via db_mysqli(): mysqli (legacy support)
 *
 * Depends on:
 * - DB_DSN, DB_USER, DB_PASS from initialize.php (or env)
 * - app_log() if available for structured logs
 */

if (defined('MK_DATABASE_LOADED') && MK_DATABASE_LOADED === true) {
  return;
}
define('MK_DATABASE_LOADED', true);

/* ---------------------------------------------------------
 * Helpers
 * --------------------------------------------------------- */
if (!function_exists('mk_db_log')) {
  function mk_db_log(string $level, string $message, array $context = []): void {
    if (function_exists('app_log')) {
      app_log($level, $message, $context);
      return;
    }
    error_log('[DB][' . strtoupper($level) . '] ' . $message);
  }
}

/* ---------------------------------------------------------
 * PDO (primary)
 * --------------------------------------------------------- */
if (!function_exists('db')) {
  function db(): PDO
  {
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
      return $GLOBALS['pdo'];
    }

    $dsn  = defined('DB_DSN') ? (string)DB_DSN : (string)(getenv('DB_DSN') ?: '');
    $user = defined('DB_USER') ? (string)DB_USER : (string)(getenv('DB_USER') ?: '');
    $pass = defined('DB_PASS') ? (string)DB_PASS : (string)(getenv('DB_PASS') ?: '');

    if ($dsn === '') {
      mk_db_log('critical', 'DB_DSN is empty (cannot connect)');
      throw new RuntimeException('Database DSN is not configured.');
    }

    try {
      $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
      ]);

      // Ensure UTF-8 for MySQL/MariaDB
      try {
        $pdo->exec("SET NAMES utf8mb4");
      } catch (Throwable $e) {
        // Non-fatal
        mk_db_log('warning', 'SET NAMES utf8mb4 failed', ['message' => $e->getMessage()]);
      }

      $GLOBALS['pdo'] = $pdo;
      return $pdo;
    } catch (Throwable $e) {
      mk_db_log('critical', 'PDO connection failed', [
        'message' => $e->getMessage(),
        'dsn'     => preg_replace('/password=([^;]+)/i', 'password=[REDACTED]', $dsn),
      ]);
      throw new RuntimeException('Database connection failed.');
    }
  }
}

/* ---------------------------------------------------------
 * mysqli (optional legacy support)
 * Only create if requested by code.
 * --------------------------------------------------------- */
if (!function_exists('db_mysqli')) {
  function db_mysqli(): mysqli
  {
    if (isset($GLOBALS['mysqli']) && $GLOBALS['mysqli'] instanceof mysqli) {
      return $GLOBALS['mysqli'];
    }

    // Prefer explicit env vars for mysqli
    $host = (string)(getenv('DB_HOST') ?: '');
    $port = (int)(getenv('DB_PORT') ?: 3306);
    $name = (string)(getenv('DB_NAME') ?: '');
    $user = defined('DB_USER') ? (string)DB_USER : (string)(getenv('DB_USER') ?: '');
    $pass = defined('DB_PASS') ? (string)DB_PASS : (string)(getenv('DB_PASS') ?: '');

    if ($host === '' || $name === '' || $user === '') {
      mk_db_log('critical', 'mysqli config missing (DB_HOST/DB_NAME/DB_USER required)');
      throw new RuntimeException('mysqli database config is not complete.');
    }

    mysqli_report(MYSQLI_REPORT_OFF);

    $mysqli = @new mysqli($host, $user, $pass, $name, $port);
    if ($mysqli->connect_error) {
      mk_db_log('critical', 'mysqli connection failed', [
        'error' => $mysqli->connect_error,
        'host'  => $host,
        'port'  => $port,
        'db'    => $name,
        'user'  => $user,
      ]);
      throw new RuntimeException('mysqli connection failed.');
    }

    $mysqli->set_charset('utf8mb4');
    $GLOBALS['mysqli'] = $mysqli;
    return $mysqli;
  }
}
