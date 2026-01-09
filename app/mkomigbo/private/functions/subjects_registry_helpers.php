<?php
declare(strict_types=1);

/**
 * /private/functions/subjects_registry_helpers.php
 *
 * Registry-backed helpers for Subjects:
 * - Apply registry nav_order to a DB list of subjects (fallback ordering)
 * - Provide SEO fallback meta per subject slug
 *
 * Important:
 * - This file loads /private/registry/subjects_register.php on-demand only.
 * - It must not collide with DB helpers.
 */

if (defined('MK_SUBJECTS_REGISTRY_HELPERS_LOADED')) {
  return;
}
define('MK_SUBJECTS_REGISTRY_HELPERS_LOADED', true);

/* ---------------------------------------------------------
 * Internal: load registry once
 * --------------------------------------------------------- */
function mk_require_subjects_registry(): void
{
  if (defined('MK_REGISTRY_SUBJECTS_LOADED')) {
    return;
  }
  if (!defined('PRIVATE_PATH')) {
    return;
  }
  $reg = PRIVATE_PATH . '/registry/subjects_register.php';
  if (is_file($reg)) {
    require_once $reg;
  }
}

/* ---------------------------------------------------------
 * Public: slug => registry row map
 * --------------------------------------------------------- */
function mk_registry_subjects_by_slug(): array
{
  mk_require_subjects_registry();

  if (!function_exists('subjects_all_registry')) {
    return [];
  }

  $by = [];
  foreach (subjects_all_registry() as $row) {
    $slug = (string)($row['slug'] ?? '');
    if ($slug !== '') {
      $by[$slug] = $row;
    }
  }
  return $by;
}

/* ---------------------------------------------------------
 * Public: apply registry nav_order to a DB subject list
 * --------------------------------------------------------- */
/**
 * @param array<int,array<string,mixed>> $subjects DB rows
 * @return array<int,array<string,mixed>> reordered
 */
function mk_subjects_apply_registry_order(array $subjects): array
{
  if (!$subjects) return $subjects;

  $regBySlug = mk_registry_subjects_by_slug();
  if (!$regBySlug) return $subjects;

  // Stable sort: registry nav_order first, unknowns at the end, then name/slug/id
  usort($subjects, function ($a, $b) use ($regBySlug) {
    $sa = (string)($a['slug'] ?? '');
    $sb = (string)($b['slug'] ?? '');

    $ra = $sa !== '' ? ($regBySlug[$sa] ?? null) : null;
    $rb = $sb !== '' ? ($regBySlug[$sb] ?? null) : null;

    $oa = is_array($ra) ? (int)($ra['nav_order'] ?? PHP_INT_MAX) : PHP_INT_MAX;
    $ob = is_array($rb) ? (int)($rb['nav_order'] ?? PHP_INT_MAX) : PHP_INT_MAX;

    if ($oa !== $ob) return $oa <=> $ob;

    $na = (string)($a['name'] ?? $sa);
    $nb = (string)($b['name'] ?? $sb);
    $c = strcasecmp($na, $nb);
    if ($c !== 0) return $c;

    $ia = (int)($a['id'] ?? 0);
    $ib = (int)($b['id'] ?? 0);
    return $ia <=> $ib;
  });

  return $subjects;
}

/* ---------------------------------------------------------
 * Public: decide whether to trust DB nav_order or fallback to registry
 * --------------------------------------------------------- */
/**
 * Heuristic:
 * - If subjects table has nav_order column AND at least 60% rows have nav_order > 0,
 *   treat DB ordering as meaningful.
 * - Else, apply registry ordering.
 *
 * @param PDO   $pdo
 * @param array<int,array<string,mixed>> $subjects
 * @return array<int,array<string,mixed>>
 */
function mk_subjects_maybe_apply_registry_order(PDO $pdo, array $subjects): array
{
  if (!$subjects) return $subjects;

  if (!function_exists('mk_has_column') || !mk_has_column($pdo, 'subjects', 'nav_order')) {
    return function_exists('mk_subjects_apply_registry_order')
      ? mk_subjects_apply_registry_order($subjects)
      : $subjects;
  }

  $nonZero = 0;
  $total   = 0;

  foreach ($subjects as $s) {
    $total++;
    $v = $s['nav_order'] ?? null;
    if ($v !== null && (int)$v > 0) $nonZero++;
  }

  if ($total > 0 && ($nonZero / $total) >= 0.6) {
    return $subjects; // trust DB
  }

  return mk_subjects_apply_registry_order($subjects);
}

/* ---------------------------------------------------------
 * Public: SEO fallback meta for a subject slug
 * --------------------------------------------------------- */
function mk_subject_meta_fallback(string $slug): array
{
  $reg = mk_registry_subjects_by_slug();
  $row = $reg[$slug] ?? [];

  return [
    'meta_description' => (string)($row['meta_description'] ?? ''),
    'meta_keywords'    => (string)($row['meta_keywords'] ?? ''),
    'icon'             => (string)($row['icon'] ?? ''),
  ];
}
