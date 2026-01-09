<?php
declare(strict_types=1);

/**
 * /private/functions/registry_loader.php
 * On-demand loaders for registries (subjects, later: platforms, contributors).
 *
 * Goal:
 * - Do NOT load registry unless needed
 * - Provide stable accessors for ordering + SEO fallback
 */

if (!function_exists('mk_registry_subjects')) {

  /**
   * Loads subjects registry once and returns sorted registry rows (by nav_order).
   * Returns: array<int,array> keyed by id (as in subjects_register.php), sorted by nav_order.
   */
  function mk_registry_subjects(): array
  {
    static $loaded = false;
    static $sorted = null;

    if ($loaded && is_array($sorted)) {
      return $sorted;
    }

    $loaded = true;

    $path = defined('PRIVATE_PATH')
      ? (rtrim((string)PRIVATE_PATH, '/') . '/registry/subjects_register.php')
      : (dirname(__DIR__) . '/registry/subjects_register.php');

    if (!is_file($path)) {
      $sorted = [];
      return $sorted;
    }

    require_once $path;

    if (function_exists('subjects_sorted_registry')) {
      $sorted = subjects_sorted_registry();
      return $sorted;
    }

    // If registry file exists but helpers not defined, fall back to global array
    if (isset($GLOBALS['SUBJECTS_REGISTRY']) && is_array($GLOBALS['SUBJECTS_REGISTRY'])) {
      $tmp = $GLOBALS['SUBJECTS_REGISTRY'];

      uasort($tmp, function ($a, $b) {
        $na = (int)($a['nav_order'] ?? PHP_INT_MAX);
        $nb = (int)($b['nav_order'] ?? PHP_INT_MAX);
        return ($na === $nb)
          ? strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''))
          : ($na <=> $nb);
      });

      $sorted = $tmp;
      return $sorted;
    }

    $sorted = [];
    return $sorted;
  }

  /**
   * Map slug => registry row (fast lookup).
   */
  function mk_registry_subjects_by_slug(): array
  {
    static $map = null;
    if (is_array($map)) return $map;

    $map = [];
    foreach (mk_registry_subjects() as $row) {
      $slug = (string)($row['slug'] ?? '');
      if ($slug !== '') $map[$slug] = $row;
    }
    return $map;
  }

  /**
   * Apply registry nav_order to an array of DB subject rows.
   * Unknown slugs go last, tie-break by name then id.
   */
  function mk_subjects_apply_registry_order(array $subjects): array
  {
    $bySlug = mk_registry_subjects_by_slug();
    if (!$bySlug) return $subjects;

    usort($subjects, function ($a, $b) use ($bySlug) {
      $sa = (string)($a['slug'] ?? '');
      $sb = (string)($b['slug'] ?? '');

      $oa = isset($bySlug[$sa]) ? (int)($bySlug[$sa]['nav_order'] ?? 999999) : 999999;
      $ob = isset($bySlug[$sb]) ? (int)($bySlug[$sb]['nav_order'] ?? 999999) : 999999;

      if ($oa === $ob) {
        $na = (string)($a['name'] ?? $sa);
        $nb = (string)($b['name'] ?? $sb);
        $c = strcasecmp($na, $nb);
        if ($c !== 0) return $c;
        return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
      }
      return $oa <=> $ob;
    });

    return $subjects;
  }
}
