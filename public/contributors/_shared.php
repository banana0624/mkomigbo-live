<?php
declare(strict_types=1);

/**
 * /public/contributors/_shared.php
 * Shared helpers for public contributors pages (index/view).
 */

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (!function_exists('mk_find_init')) {
  function mk_find_init(string $startDir, int $maxDepth = 14): ?string {
    $dir = $startDir;
    for ($i = 0; $i <= $maxDepth; $i++) {
      $candidates = [
        $dir . '/private/assets/initialize.php',
        $dir . '/app/mkomigbo/private/assets/initialize.php',
        $dir . '/app/private/assets/initialize.php',
      ];
      foreach ($candidates as $candidate) {
        if (is_file($candidate)) return $candidate;
      }
      $parent = dirname($dir);
      if ($parent === $dir) break;
      $dir = $parent;
    }
    return null;
  }
}

if (!defined('APP_ROOT') || !is_string(APP_ROOT) || APP_ROOT === '') {
  // If initialize.php defines APP_ROOT, this is overridden after require_once.
  define('APP_ROOT', dirname(__DIR__, 2) . '/app/mkomigbo');
}

$init = mk_find_init(__DIR__);
if (!$init) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Init not found.\n";
  exit;
}
require_once $init;

if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('pf__column_exists')) {
  function pf__column_exists(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $key = strtolower($table . '.' . $column);
    if (array_key_exists($key, $cache)) return (bool)$cache[$key];

    try {
      $st = $pdo->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
      ");
      $st->execute([$table, $column]);
      $cache[$key] = (bool)$st->fetchColumn();
      return (bool)$cache[$key];
    } catch (Throwable $e) {
      $cache[$key] = false;
      return false;
    }
  }
}

if (!function_exists('pf__table_exists')) {
  function pf__table_exists(PDO $pdo, string $table): bool {
    try {
      $st = $pdo->prepare("
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
        LIMIT 1
      ");
      $st->execute([$table]);
      return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
      return false;
    }
  }
}

if (!function_exists('pf__parse_key')) {
  /**
   * Accepts slug-like strings OR numeric ids.
   * Returns: ['id'=>int|null,'slug'=>string|null,'key'=>string]
   */
  function pf__parse_key(string $raw): array {
    $raw = trim($raw);
    $raw = preg_replace('~[^\w\-]+~u', '', $raw) ?? $raw;
    $raw = trim($raw, '-_');
    if ($raw === '') return ['id'=>null,'slug'=>null,'key'=>''];

    if (ctype_digit($raw)) {
      return ['id'=>(int)$raw,'slug'=>null,'key'=>$raw];
    }

    $slug = strtolower($raw);
    $slug = preg_replace('~[^a-z0-9\-]+~', '-', $slug) ?? $slug;
    $slug = preg_replace('~\-{2,}~', '-', $slug) ?? $slug;
    $slug = trim($slug, '-');
    return ['id'=>null,'slug'=>$slug,'key'=>$slug];
  }
}

if (!function_exists('pf__contributors_header')) {
  function pf__contributors_header(): void {
    $hdr = null;

    if (defined('PRIVATE_PATH') && is_string(PRIVATE_PATH) && PRIVATE_PATH !== '') {
      $cand = rtrim(PRIVATE_PATH, '/\\') . '/shared/contributor_header.php';
      if (is_file($cand)) $hdr = $cand;
      if (!$hdr) {
        $cand = rtrim(PRIVATE_PATH, '/\\') . '/shared/public_header.php';
        if (is_file($cand)) $hdr = $cand;
      }
    }

    if (!$hdr && defined('APP_ROOT') && is_string(APP_ROOT) && APP_ROOT !== '') {
      $cand = rtrim(APP_ROOT, '/\\') . '/private/shared/contributor_header.php';
      if (is_file($cand)) $hdr = $cand;
      if (!$hdr) {
        $cand = rtrim(APP_ROOT, '/\\') . '/private/shared/public_header.php';
        if (is_file($cand)) $hdr = $cand;
      }
    }

    if ($hdr && is_file($hdr)) {
      require_once $hdr;
      return;
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo "Header include missing.\n";
    exit;
  }
}

if (!function_exists('pf__contributors_footer')) {
  function pf__contributors_footer(): void {
    $ftr = null;

    if (defined('PRIVATE_PATH') && is_string(PRIVATE_PATH) && PRIVATE_PATH !== '') {
      $cand = rtrim(PRIVATE_PATH, '/\\') . '/shared/contributor_footer.php';
      if (is_file($cand)) $ftr = $cand;
      if (!$ftr) {
        $cand = rtrim(PRIVATE_PATH, '/\\') . '/shared/public_footer.php';
        if (is_file($cand)) $ftr = $cand;
      }
    }

    if (!$ftr && defined('APP_ROOT') && is_string(APP_ROOT) && APP_ROOT !== '') {
      $cand = rtrim(APP_ROOT, '/\\') . '/private/shared/contributor_footer.php';
      if (is_file($cand)) $ftr = $cand;
      if (!$ftr) {
        $cand = rtrim(APP_ROOT, '/\\') . '/private/shared/public_footer.php';
        if (is_file($cand)) $ftr = $cand;
      }
    }

    if ($ftr && is_file($ftr)) {
      require_once $ftr;
      return;
    }

    if (!empty($GLOBALS['mk__main_open'])) echo "</main>";
    echo "</body></html>";
  }
}
