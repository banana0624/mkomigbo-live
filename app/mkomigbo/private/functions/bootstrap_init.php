<?php
declare(strict_types=1);

/**
 * /private/functions/bootstrap_init.php
 *
 * PURPOSE (in THIS codebase):
 * - Provide mk_initialize() as a stable, idempotent initializer that can be invoked
 *   by initialize.php (or legacy code) without duplicating responsibilities.
 *
 * CONTRACT:
 * - initialize.php owns: APP_ROOT/SITE_ROOT/PUBLIC_PATH/WWW_ROOT, logging, handlers, env parsing.
 * - bootstrap_init.php owns: loading "feature modules" once (contain.php + optional route modules),
 *   plus compatibility shims and schema helpers that depend on db().
 *
 * IMPORTANT:
 * - This file must NOT send headers or change error_reporting aggressively.
 * - This file must NOT compete with /private/assets/database.php.
 */

if (defined('MK_BOOTSTRAP_INIT_LOADED') && MK_BOOTSTRAP_INIT_LOADED === true) {
  return;
}
define('MK_BOOTSTRAP_INIT_LOADED', true);

/* ---------------------------------------------------------
 * Resolve core paths from the constants you actually use now
 * --------------------------------------------------------- */
if (!defined('APP_ROOT')) {
  // initialize.php should define this. If not, do best-effort.
  define('APP_ROOT', dirname(__DIR__, 2)); // .../app/mkomigbo
}
if (!defined('SITE_ROOT')) {
  define('SITE_ROOT', dirname(APP_ROOT, 2)); // .../public_html
}
if (!defined('PUBLIC_PATH')) {
  define('PUBLIC_PATH', SITE_ROOT);
}
if (!defined('PUBLIC_SUBDIR')) {
  define('PUBLIC_SUBDIR', SITE_ROOT . '/public');
}

if (!defined('FUNCTIONS_PATH')) {
  define('FUNCTIONS_PATH', APP_ROOT . '/private/functions');
}
if (!defined('ASSETS_PATH')) {
  define('ASSETS_PATH', APP_ROOT . '/private/assets');
}
if (!defined('SHARED_PATH')) {
  define('SHARED_PATH', APP_ROOT . '/private/shared');
}

/* ---------------------------------------------------------
 * Minimal require helper (use initialize.php helper if present)
 * --------------------------------------------------------- */
if (!function_exists('mk__require_or_fail')) {
  function mk__require_or_fail(string $file, string $label): void
  {
    if (function_exists('mk_require_or_fail')) {
      mk_require_or_fail($file, $label);
      return;
    }
    if (!is_file($file)) {
      http_response_code(500);
      header('Content-Type: text/plain; charset=utf-8');
      echo "Bootstrap failed: {$label}\nMissing: {$file}\n";
      exit;
    }
    require_once $file;
  }
}

/* ---------------------------------------------------------
 * Public API: mk_initialize()
 * - Keep the name for backward compatibility
 * - Make it idempotent and “safe to call”
 * --------------------------------------------------------- */
if (!function_exists('mk_initialize')) {
  function mk_initialize(): void
  {
    static $ran = false;
    if ($ran) return;
    $ran = true;

    // 1) Ensure core helpers are present (loaded by initialize.php in your current chain)
    // If initialize.php did not load them, load them here as fallback.
    if (!function_exists('mk_public_bootstrap')) {
      mk__require_or_fail(FUNCTIONS_PATH . '/public_bootstrap.php', 'public_bootstrap');
    }
    if (!function_exists('pf__accent_for')) {
      // theme helpers
      mk__require_or_fail(FUNCTIONS_PATH . '/theme_functions.php', 'theme_functions');
    }

    // 2) Ensure DB layer exists (PDO db()) – DO NOT re-implement db() here
    if (!function_exists('db')) {
      mk__require_or_fail(ASSETS_PATH . '/database.php', 'database');
    }

    // 3) Ensure shared include helper exists
    mk_init_shared_include_helpers();

    // 4) Schema helpers (depends on db())
    mk_init_schema_helpers();

    // 5) Legacy compat shims (optional, safe)
    mk_init_legacy_compat();

    // 6) Feature hub (contain.php) exactly once
    mk_init_contain();

    // 7) Route-scoped feature requirements (e.g. igbo calendar)
    mk_init_required_features();
  }
}

/* ---------------------------------------------------------
 * Shared include helper
 * --------------------------------------------------------- */
if (!function_exists('mk_init_shared_include_helpers')) {
  function mk_init_shared_include_helpers(): void
  {
    if (!function_exists('mk_require_shared')) {
      function mk_require_shared(string $file): void
      {
        $path = rtrim((string)SHARED_PATH, '/') . '/' . ltrim($file, '/');
        if (!is_file($path)) {
          // Silent fail here is dangerous. Fail clearly.
          throw new RuntimeException('Shared include not found: ' . $path);
        }
        require_once $path;
      }
    }
  }
}

/* ---------------------------------------------------------
 * Schema helpers (DB-safe, cached)
 * --------------------------------------------------------- */
if (!function_exists('mk_init_schema_helpers')) {
  function mk_init_schema_helpers(): void
  {
    if (!function_exists('mk_table_columns')) {
      function mk_table_columns(PDO $pdo, string $table): array
      {
        static $cache = [];
        $key = strtolower($table);
        if (isset($cache[$key])) return $cache[$key];

        try {
          $st = $pdo->query("DESCRIBE `{$table}`");
          $cols = [];
          foreach (($st ? $st->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
            if (!empty($row['Field'])) $cols[strtolower((string)$row['Field'])] = true;
          }
          return $cache[$key] = $cols;
        } catch (Throwable $e) {
          return $cache[$key] = [];
        }
      }
    }

    if (!function_exists('mk_has_column')) {
      function mk_has_column(PDO $pdo, string $table, string $column): bool
      {
        $cols = mk_table_columns($pdo, $table);
        return isset($cols[strtolower($column)]);
      }
    }

    if (!function_exists('mk_has_table')) {
      function mk_has_table(PDO $pdo, string $table): bool
      {
        try {
          $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
          return true;
        } catch (Throwable $e) {
          return false;
        }
      }
    }

    // Keep your existing pf__* aliases (your code relies on them)
    if (!function_exists('pf__table_exists_hard')) {
      function pf__table_exists_hard(PDO $pdo, string $table): bool { return mk_has_table($pdo, $table); }
    }
    if (!function_exists('pf__column_exists_hard')) {
      function pf__column_exists_hard(PDO $pdo, string $table, string $column): bool
      {
        try { $pdo->query("SELECT `{$column}` FROM `{$table}` LIMIT 1"); return true; }
        catch (Throwable $e) { return false; }
      }
    }
    if (!function_exists('pf__column_exists')) {
      function pf__column_exists(PDO $pdo, string $table, string $column): bool { return mk_has_column($pdo, $table, $column); }
    }
  }
}

/* ---------------------------------------------------------
 * Legacy compatibility
 * --------------------------------------------------------- */
if (!function_exists('mk_init_legacy_compat')) {
  function mk_init_legacy_compat(): void
  {
    // Provide global $db alias for older code
    global $db;

    try {
      if ($db instanceof PDO) {
        $GLOBALS['pdo'] = $db;
        return;
      }
      if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $db = $GLOBALS['pdo'];
        return;
      }
      if (function_exists('db')) {
        $pdo = db();
        if ($pdo instanceof PDO) {
          $GLOBALS['pdo'] = $pdo;
          $db = $pdo;
        }
      }
    } catch (Throwable $e) {
      // Never hard-fail legacy shim
      $db = $db ?? null;
    }
  }
}

/* ---------------------------------------------------------
 * Contain (dependency hub) – load once
 * --------------------------------------------------------- */
if (!function_exists('mk_init_contain')) {
  function mk_init_contain(): void
  {
    if (defined('MK_CONTAIN_LOADED') && MK_CONTAIN_LOADED === true) return;

    $contain = FUNCTIONS_PATH . '/contain.php';
    if (!is_file($contain)) {
      // contain is important in your stack, but keep failure explicit
      throw new RuntimeException('contain.php missing at: ' . $contain);
    }
    require_once $contain;

    // If contain.php did not define the flag, define it for safety
    if (!defined('MK_CONTAIN_LOADED')) define('MK_CONTAIN_LOADED', true);
  }
}

/* ---------------------------------------------------------
 * Route-scoped requirements (keep this idea, but do it safely)
 * --------------------------------------------------------- */
if (!function_exists('mk_request_path')) {
  function mk_request_path(): string
  {
    $uri  = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string)parse_url($uri, PHP_URL_PATH);
    return ($path !== '') ? $path : '/';
  }
}

if (!function_exists('mk_init_required_features')) {
  function mk_init_required_features(): void
  {
    $path = mk_request_path();

    // Igbo calendar route(s)
    $needs_calendar = (bool)preg_match('~^/(?:platforms/)?igbo-calendar(?:/|$)~', $path);

    if ($needs_calendar && !function_exists('igbo_calendar_render_page')) {
      $cal = FUNCTIONS_PATH . '/igbo_calendar_functions.php';
      if (is_file($cal)) {
        require_once $cal;
      }
    }

    if ($needs_calendar && !function_exists('igbo_calendar_render_page')) {
      throw new RuntimeException('Igbo calendar renderer missing (expected igbo_calendar_functions.php).');
    }
  }
}
