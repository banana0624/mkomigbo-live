<?php
declare(strict_types=1);

/**
 * /public_html/app/mkomigbo/private/assets/initialize.php
 *
 * Central bootstrap:
 * - env + debug toggles
 * - structured JSONL logging with request correlation
 * - PHP error -> exception conversion (controlled)
 * - global exception + fatal shutdown handlers
 * - defines stable path constants (APP_ROOT / SITE_ROOT / PUBLIC_PATH / PUBLIC_SUBDIR / WWW_ROOT / PRIVATE_PATH)
 * - loads core helpers only (theme + public bootstrap + database)
 * - guarantees db():PDO exists and is stable (lazy connect fallback)
 */

/* -------------------------------------------------------------------------
 * 0) Idempotency: prevent double-init
 * ------------------------------------------------------------------------- */
if (defined('MK_APP_INITIALIZED') && MK_APP_INITIALIZED === true) {
  return;
}
define('MK_APP_INITIALIZED', true);

/* -------------------------------------------------------------------------
 * 1) Define APP_ROOT early and correctly
 *
 * initialize.php is: .../app/mkomigbo/private/assets/initialize.php
 * __DIR__                => .../app/mkomigbo/private/assets
 * dirname(__DIR__, 2)    => .../app/mkomigbo   (APP_ROOT)
 * ------------------------------------------------------------------------- */
if (!defined('APP_ROOT')) {
  $appRoot = dirname(__DIR__, 2);
  $rp = @realpath($appRoot);
  define('APP_ROOT', is_string($rp) && $rp !== '' ? $rp : $appRoot);
}

/* -------------------------------------------------------------------------
 * 1b) Paths (single source of truth)
 *
 * Layout:
 *   public_html/                (SITE_ROOT / docroot)
 *     lib/...
 *     public/subjects/...
 *     app/mkomigbo/private/...
 * ------------------------------------------------------------------------- */
if (!defined('PRIVATE_PATH')) {
  define('PRIVATE_PATH', APP_ROOT . '/private');
}

/* SITE_ROOT = public_html */
if (!defined('SITE_ROOT')) {
  $siteRoot = dirname(APP_ROOT, 2);
  $rp = @realpath($siteRoot);
  define('SITE_ROOT', is_string($rp) && $rp !== '' ? $rp : $siteRoot);
}

/**
 * PUBLIC_PATH = docroot on disk (public_html)
 * This is where /lib/... lives.
 */
if (!defined('PUBLIC_PATH')) {
  define('PUBLIC_PATH', SITE_ROOT);
}

/**
 * PUBLIC_SUBDIR = physical folder on disk (public_html/public)
 * This is where modules live on disk, e.g. /subjects/, /platforms/, /contributors/
 */
if (!defined('PUBLIC_SUBDIR')) {
  define('PUBLIC_SUBDIR', SITE_ROOT . '/public');
}

/**
 * URL base. If hosted at domain root keep ''.
 * If hosted under a subfolder (example.com/mkomigbo) set '/mkomigbo'.
 * Prefer env WWW_ROOT if present.
 */
if (!defined('WWW_ROOT')) {
  $wr = (string)(getenv('WWW_ROOT') ?: '');
  $wr = trim($wr);

  if ($wr !== '' && $wr !== '/') {
    if ($wr[0] !== '/') { $wr = '/' . $wr; }
    $wr = rtrim($wr, '/');
  } else {
    $wr = '';
  }
  define('WWW_ROOT', $wr);
}

/* -------------------------------------------------------------------------
 * 2) Environment toggles
 * ------------------------------------------------------------------------- */
if (!defined('APP_ENV')) {
  define('APP_ENV', getenv('APP_ENV') ?: 'prod');
}
if (!defined('APP_DEBUG')) {
  define('APP_DEBUG', APP_ENV !== 'prod');
}

/* Optional runtime debug override (temporary, for diag) */
if (!defined('APP_DEBUG_OVERRIDE')) {
  $dbg = isset($_GET['__debug']) && (string)$_GET['__debug'] === '1';
  define('APP_DEBUG_OVERRIDE', $dbg);
}

/* -------------------------------------------------------------------------
 * 3) Basic PHP error settings
 * ------------------------------------------------------------------------- */
error_reporting(E_ALL);
ini_set('display_errors', (APP_DEBUG || APP_DEBUG_OVERRIDE) ? '1' : '0');
ini_set('display_startup_errors', (APP_DEBUG || APP_DEBUG_OVERRIDE) ? '1' : '0');
ini_set('log_errors', '1');

/* Stabilize timezone (avoid PHP warnings in logs) */
if (!ini_get('date.timezone')) {
  @date_default_timezone_set('UTC');
}

/* -------------------------------------------------------------------------
 * 4) Logging paths (robust fallback if perms fail)
 * ------------------------------------------------------------------------- */
if (!defined('LOG_DIR')) {
  define('LOG_DIR', APP_ROOT . '/logs');
}

$__logDir = LOG_DIR;
if (!is_dir($__logDir)) {
  @mkdir($__logDir, 0775, true);
}
if (!is_dir($__logDir) || !is_writable($__logDir)) {
  $__logDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'mkomigbo-logs';
  @mkdir($__logDir, 0775, true);
}

if (!defined('APP_LOG_FILE')) {
  define('APP_LOG_FILE', rtrim($__logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'app.log.jsonl');
}

/* -------------------------------------------------------------------------
 * 5) Request correlation
 * ------------------------------------------------------------------------- */
if (!defined('REQUEST_ID')) {
  $rid = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
  if (!$rid) {
    try {
      $rid = bin2hex(random_bytes(8)) . '-' . bin2hex(random_bytes(4));
    } catch (Throwable $e) {
      $rid = uniqid('rid_', true);
    }
  }
  define('REQUEST_ID', (string)$rid);
}

/* -------------------------------------------------------------------------
 * 6) Mask sensitive keys recursively
 * ------------------------------------------------------------------------- */
if (!function_exists('mask_sensitive')) {
  function mask_sensitive($value) {
    $sensitiveKeys = [
      'password', 'pass', 'pwd',
      'token', 'access_token', 'refresh_token',
      'authorization', 'api_key', 'apikey',
      'secret', 'session', 'cookie',
      'db_pass', 'db_password',
    ];

    if (is_array($value)) {
      $out = [];
      foreach ($value as $k => $v) {
        $lk = is_string($k) ? strtolower($k) : $k;
        if (is_string($lk) && in_array($lk, $sensitiveKeys, true)) {
          $out[$k] = '[REDACTED]';
        } else {
          $out[$k] = mask_sensitive($v);
        }
      }
      return $out;
    }

    if (is_string($value)) {
      if (strlen($value) > 5000) {
        return substr($value, 0, 5000) . '...[TRUNCATED]';
      }
      return $value;
    }

    return $value;
  }
}

/* -------------------------------------------------------------------------
 * 7) Structured JSONL logger (best-effort + PHP error_log fallback)
 * ------------------------------------------------------------------------- */
if (!function_exists('app_log')) {
  function app_log(string $level, string $message, array $context = []): void
  {
    $event = [
      'ts'         => gmdate('c'),
      'level'      => strtoupper($level),
      'message'    => $message,
      'request_id' => defined('REQUEST_ID') ? REQUEST_ID : null,
      'context'    => function_exists('mask_sensitive') ? mask_sensitive($context) : $context,
      'http'       => [
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'uri'    => $_SERVER['REQUEST_URI'] ?? null,
        'ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
        'ua'     => $_SERVER['HTTP_USER_AGENT'] ?? null,
      ],
    ];

    try {
      $line = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      if ($line === false) {
        $line = '{"ts":"' . gmdate('c') . '","level":"ERROR","message":"json_encode_failed","request_id":"' .
          (defined('REQUEST_ID') ? REQUEST_ID : '') . '"}';
      }

      $ok = @file_put_contents(APP_LOG_FILE, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
      if ($ok === false) {
        error_log('[APP_LOG_FALLBACK] ' . $line);
      }
    } catch (Throwable $e) {
      error_log('[APP_LOG_EXCEPTION] ' . $message . ' rid=' . (defined('REQUEST_ID') ? REQUEST_ID : 'n/a'));
    }
  }
}

/* -------------------------------------------------------------------------
 * 7b) REQUIRED: safe require helper with “searched” logging
 * ------------------------------------------------------------------------- */
if (!function_exists('mk_require_or_fail')) {
  function mk_require_or_fail(string $file, string $label = 'required file'): void
  {
    $searched = [$file];

    if (is_file($file)) {
      require_once $file;
      return;
    }

    if (function_exists('app_log')) {
      app_log('critical', 'Bootstrap require failed', [
        'label'    => $label,
        'missing'  => $file,
        'searched' => $searched,
        'app_root' => defined('APP_ROOT') ? APP_ROOT : null,
      ]);
    }

    if (!headers_sent()) {
      http_response_code(500);
      header('Content-Type: text/plain; charset=utf-8');
    }

    $debug = defined('APP_DEBUG') && (APP_DEBUG || (defined('APP_DEBUG_OVERRIDE') && APP_DEBUG_OVERRIDE));
    echo $debug
      ? "Bootstrap failed: {$label}\nMissing: {$file}\nSearched:\n - " . implode("\n - ", $searched) . "\nRID: " . (defined('REQUEST_ID') ? REQUEST_ID : 'n/a')
      : "Bootstrap failed. Reference: " . (defined('REQUEST_ID') ? REQUEST_ID : 'n/a');

    exit;
  }
}

/* -------------------------------------------------------------------------
 * 8) Load .env (best-effort)
 * ------------------------------------------------------------------------- */
if (!function_exists('mk_load_env_file')) {
  function mk_load_env_file(string $path): void
  {
    if (!is_file($path) || !is_readable($path)) { return; }

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) { return; }

    foreach ($lines as $line) {
      $line = trim($line);
      if ($line === '' || $line[0] === '#') { continue; }

      $pos = strpos($line, '=');
      if ($pos === false) { continue; }

      $key = trim(substr($line, 0, $pos));
      $val = trim(substr($line, $pos + 1));
      if ($key === '') { continue; }

      if (strlen($val) >= 2) {
        $first = $val[0];
        $last  = $val[strlen($val) - 1];
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
          $val = substr($val, 1, -1);
        }
      }

      if (getenv($key) === false) {
        @putenv($key . '=' . $val);
        $_ENV[$key] = $val;
      }
    }
  }
}
mk_load_env_file(APP_ROOT . '/.env');

/* -------------------------------------------------------------------------
 * 9) Define DB constants from env (define even if empty to avoid notices)
 * ------------------------------------------------------------------------- */
if (!defined('DB_DSN')) {
  $dsn = (string)(getenv('DB_DSN') ?: '');

  $host    = (string)(getenv('DB_HOST') ?: '');
  $port    = (string)(getenv('DB_PORT') ?: '3306');
  $name    = (string)(getenv('DB_NAME') ?: '');
  $charset = (string)(getenv('DB_CHARSET') ?: 'utf8mb4');

  if ($dsn === '' && $host !== '' && $name !== '') {
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
  }

  define('DB_DSN', $dsn);
}
if (!defined('DB_USER')) {
  define('DB_USER', (string)(getenv('DB_USER') ?: ''));
}
if (!defined('DB_PASS')) {
  define('DB_PASS', (string)(getenv('DB_PASS') ?: ''));
}

/* -------------------------------------------------------------------------
 * 10) Convert PHP errors to exceptions (controlled)
 * ------------------------------------------------------------------------- */
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
  if (!(error_reporting() & $severity)) {
    return true; // Respect @
  }

  if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
    if (function_exists('app_log')) {
      app_log('warning', 'PHP deprecated', ['message' => $message, 'file' => $file, 'line' => $line]);
    }
    return true;
  }

  if (defined('APP_DEBUG') && !APP_DEBUG && ($severity === E_NOTICE || $severity === E_USER_NOTICE)) {
    if (function_exists('app_log')) {
      app_log('notice', 'PHP notice', ['message' => $message, 'file' => $file, 'line' => $line]);
    }
    return true;
  }

  throw new ErrorException($message, 0, $severity, $file, $line);
});

/* -------------------------------------------------------------------------
 * 11) Global exception handler
 * ------------------------------------------------------------------------- */
set_exception_handler(function (Throwable $e): void {
  if (function_exists('app_log')) {
    app_log('error', 'Unhandled exception', [
      'type'    => get_class($e),
      'code'    => $e->getCode(),
      'message' => $e->getMessage(),
      'file'    => $e->getFile(),
      'line'    => $e->getLine(),
      'trace'   => explode("\n", $e->getTraceAsString()),
    ]);
  }

  error_log('[UNHANDLED_EXCEPTION] rid=' . (defined('REQUEST_ID') ? REQUEST_ID : 'n/a') . ' ' . $e->getMessage());

  $accept   = $_SERVER['HTTP_ACCEPT'] ?? '';
  $wantJson = stripos($accept, 'application/json') !== false;

  if (!headers_sent()) {
    http_response_code(500);
    header($wantJson
      ? 'Content-Type: application/json; charset=UTF-8'
      : 'Content-Type: text/plain; charset=UTF-8'
    );
  }

  $isDebug = defined('APP_DEBUG') && (APP_DEBUG || (defined('APP_DEBUG_OVERRIDE') && APP_DEBUG_OVERRIDE));
  if ($isDebug) {
    echo "Server error (debug)\n";
    echo get_class($e) . ": " . $e->getMessage() . "\n";
    echo $e->getFile() . ":" . $e->getLine() . "\n\n";
    echo $e->getTraceAsString();
    return;
  }

  if ($wantJson) {
    echo json_encode([
      'ok'        => false,
      'error'     => 'internal_error',
      'reference' => defined('REQUEST_ID') ? REQUEST_ID : null,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  } else {
    echo "An internal error occurred. Reference: " . (defined('REQUEST_ID') ? REQUEST_ID : 'n/a');
  }
});

/* -------------------------------------------------------------------------
 * 12) Fatal shutdown handler
 * ------------------------------------------------------------------------- */
register_shutdown_function(function (): void {
  $err = error_get_last();
  if (!$err) { return; }

  $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
  if (!in_array((int)$err['type'], $fatalTypes, true)) { return; }

  if (function_exists('app_log')) {
    app_log('critical', 'Fatal shutdown error', [
      'type'    => $err['type'],
      'message' => $err['message'],
      'file'    => $err['file'],
      'line'    => $err['line'],
    ]);
  }

  error_log('[FATAL] rid=' . (defined('REQUEST_ID') ? REQUEST_ID : 'n/a') . ' ' . ($err['message'] ?? ''));

  if (!headers_sent()) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
  }

  $isDebug = defined('APP_DEBUG') && (APP_DEBUG || (defined('APP_DEBUG_OVERRIDE') && APP_DEBUG_OVERRIDE));
  echo $isDebug
    ? "Fatal error (debug). Reference: " . (defined('REQUEST_ID') ? REQUEST_ID : 'n/a')
    : "A fatal error occurred. Reference: " . (defined('REQUEST_ID') ? REQUEST_ID : 'n/a');
});

/* -------------------------------------------------------------------------
 * 13) Core bootstraps (order matters)
 * ------------------------------------------------------------------------- */
$__boot_files = [
  'theme_functions'  => APP_ROOT . '/private/functions/theme_functions.php',
  'public_bootstrap' => APP_ROOT . '/private/functions/public_bootstrap.php',
  'database'         => APP_ROOT . '/private/assets/database.php',
];

foreach ($__boot_files as $__label => $__file) {
  if (is_file($__file)) {
    require_once $__file;
  } else {
    mk_require_or_fail($__file, $__label);
  }
}

/* -------------------------------------------------------------------------
 * 13b) Guarantee a stable PDO + db() (lazy connect fallback)
 * ------------------------------------------------------------------------- */
if (!function_exists('mk_pdo_connect')) {
  function mk_pdo_connect(): PDO {
    $dsn  = defined('DB_DSN')  ? (string)DB_DSN  : '';
    $user = defined('DB_USER') ? (string)DB_USER : '';
    $pass = defined('DB_PASS') ? (string)DB_PASS : '';

    if ($dsn === '') {
      throw new RuntimeException('DB_DSN is empty. Check APP_ROOT/.env (DB_DSN or DB_HOST/DB_NAME).');
    }

    $opts = [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    return new PDO($dsn, $user, $pass, $opts);
  }
}

try {
  // Prefer existing db() if database.php provided it.
  if ((!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) && function_exists('db')) {
    try {
      $maybe = db();
      if ($maybe instanceof PDO) {
        $GLOBALS['pdo'] = $maybe;
      }
    } catch (Throwable $e) {
      // ignore; we'll attempt lazy connect below
    }
  }

  // Some legacy code stores it here.
  if ((!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) &&
      isset($GLOBALS['db']) && $GLOBALS['db'] instanceof PDO) {
    $GLOBALS['pdo'] = $GLOBALS['db'];
  }

  // Lazy connect if still missing.
  if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
    $GLOBALS['pdo'] = mk_pdo_connect();
  }

} catch (Throwable $e) {
  if (function_exists('app_log')) {
    app_log('critical', 'DB init failed', [
      'message' => $e->getMessage(),
      'dsn_set' => (defined('DB_DSN') && DB_DSN !== '') ? 1 : 0,
    ]);
  }
  throw $e; // allow global handler to emit RID
}

/* Guarantee db() exists and returns PDO */
if (!function_exists('db')) {
  function db(): PDO {
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) { return $GLOBALS['pdo']; }
    if (isset($GLOBALS['db'])  && $GLOBALS['db']  instanceof PDO) { return $GLOBALS['db']; }
    throw new RuntimeException('DB handle not initialized.');
  }
}

/* -------------------------------------------------------------------------
 * 14) Initialize public helpers
 * ------------------------------------------------------------------------- */
$plain = isset($_GET['__plain']) && (string)$_GET['__plain'] === '1';
$needTheme = !$plain;

if (function_exists('app_log')) {
  app_log('info', 'Bootstrap options', [
    'plain'      => $plain ? 1 : 0,
    'need_theme' => $needTheme ? 1 : 0,
  ]);
}

if (function_exists('mk_public_bootstrap')) {
  mk_public_bootstrap(['cache' => 0, 'need_theme' => $needTheme]);
}

/* -------------------------------------------------------------------------
 * 15) Validate core function availability (fail fast)
 * ------------------------------------------------------------------------- */
$__missing = [];
foreach (['h', 'url_for', 'db'] as $__fn) {
  if (!function_exists($__fn)) { $__missing[] = $__fn; }
}

if ($__missing) {
  if (function_exists('app_log')) {
    app_log('critical', 'Core functions missing after initialize', [
      'missing'  => $__missing,
      'app_root' => APP_ROOT,
    ]);
  }
  throw new RuntimeException('Application bootstrap incomplete: missing ' . implode(', ', $__missing));
}

if (function_exists('app_log')) {
  app_log('info', 'Application initialized', [
    'app_env'    => defined('APP_ENV') ? APP_ENV : null,
    'app_debug'  => defined('APP_DEBUG') ? (APP_DEBUG ? 1 : 0) : null,
    'app_root'   => APP_ROOT,
    'site_root'  => defined('SITE_ROOT') ? SITE_ROOT : null,
    'public_path'=> defined('PUBLIC_PATH') ? PUBLIC_PATH : null,
    'public_subdir'=> defined('PUBLIC_SUBDIR') ? PUBLIC_SUBDIR : null,
    'www_root'   => defined('WWW_ROOT') ? WWW_ROOT : null,
    'has_db_dsn' => (defined('DB_DSN') && DB_DSN !== '') ? 1 : 0,
    'has_theme_helpers' => (function_exists('pf__seg') && function_exists('pf__accent_for') && function_exists('pf__subject_logo_url')) ? 1 : 0,
  ]);
}
