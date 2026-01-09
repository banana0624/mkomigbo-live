<?php
declare(strict_types=1);

/**
 * /private/registry/subjects_register.php
 *
 * Static seed/registry for Subjects (fallback/reference only).
 * Used for:
 * - SEO fallbacks (when DB is unavailable / before DB is ready)
 * - Routing fallbacks (subject slug → id/name/meta)
 *
 * CRITICAL:
 * - subjects_all_registry(): array MUST ALWAYS return an array (never null),
 *   because it is typed ": array" and is used by SEO fallback + view routing.
 *
 * SAFETY:
 * - This file MUST NOT collide with DB/bootstrap helpers.
 * - Keep functions guarded with function_exists().
 */

if (defined('MK_REGISTRY_SUBJECTS_LOADED')) {
  return; // idempotent include
}
define('MK_REGISTRY_SUBJECTS_LOADED', true);

/**
 * Registry data (keyed by numeric id).
 *
 * NOTE:
 * - Keep slugs stable; they are part of your public URLs.
 * - Icons are optional; if a referenced icon doesn't exist, UI should fallback.
 *
 * @var array<int,array<string,mixed>>
 */
$SUBJECTS_REGISTRY = [
  1  => ['id'=>1,  'name'=>'History',      'slug'=>'history',      'nav_order'=>1,  'meta_description'=>'Pages related to history.',         'meta_keywords'=>'history, past, heritage, records',        'icon'=>'/lib/images/subjects/history.svg'],
  2  => ['id'=>2,  'name'=>'Slavery',      'slug'=>'slavery',      'nav_order'=>2,  'meta_description'=>'Pages related to slavery.',         'meta_keywords'=>'slavery, trade, bondage, history',        'icon'=>'/lib/images/subjects/slavery.svg'],
  3  => ['id'=>3,  'name'=>'People',       'slug'=>'people',       'nav_order'=>3,  'meta_description'=>'Pages related to people.',          'meta_keywords'=>'people, community, individuals',          'icon'=>'/lib/images/subjects/people.svg'],
  4  => ['id'=>4,  'name'=>'Persons',      'slug'=>'persons',      'nav_order'=>4,  'meta_description'=>'Pages related to persons.',         'meta_keywords'=>'persons, individuals, biographies',       'icon'=>'/lib/images/subjects/persons.svg'],
  5  => ['id'=>5,  'name'=>'Culture',      'slug'=>'culture',      'nav_order'=>5,  'meta_description'=>'Pages related to culture.',         'meta_keywords'=>'culture, lifestyle, arts, heritage',      'icon'=>'/lib/images/subjects/culture.svg'],
  6  => ['id'=>6,  'name'=>'Religion',     'slug'=>'religion',     'nav_order'=>6,  'meta_description'=>'Pages related to religion.',        'meta_keywords'=>'religion, faith, worship, belief',        'icon'=>'/lib/images/subjects/religion.svg'],
  7  => ['id'=>7,  'name'=>'Spirituality', 'slug'=>'spirituality', 'nav_order'=>7,  'meta_description'=>'Pages related to spirituality.',    'meta_keywords'=>'spirituality, meditation, faith, soul',   'icon'=>'/lib/images/subjects/spirituality.svg'],
  8  => ['id'=>8,  'name'=>'Tradition',    'slug'=>'tradition',    'nav_order'=>8,  'meta_description'=>'Pages related to tradition.',       'meta_keywords'=>'tradition, customs, practices, heritage', 'icon'=>'/lib/images/subjects/tradition.svg'],
  9  => ['id'=>9,  'name'=>'Language1',    'slug'=>'language1',    'nav_order'=>9,  'meta_description'=>'Pages related to first language.',  'meta_keywords'=>'language, communication, dialect',        'icon'=>'/lib/images/subjects/language1.svg'],
  10 => ['id'=>10, 'name'=>'Language2',    'slug'=>'language2',    'nav_order'=>10, 'meta_description'=>'Pages related to second language.', 'meta_keywords'=>'language, communication, dialect',        'icon'=>'/lib/images/subjects/language2.svg'],
  11 => ['id'=>11, 'name'=>'Struggles',    'slug'=>'struggles',    'nav_order'=>11, 'meta_description'=>'Pages related to struggles.',       'meta_keywords'=>'struggles, resistance, survival',         'icon'=>'/lib/images/subjects/struggles.svg'],
  12 => ['id'=>12, 'name'=>'Biafra',       'slug'=>'biafra',       'nav_order'=>12, 'meta_description'=>'Pages related to Biafra.',          'meta_keywords'=>'biafra, war, independence, nigeria',      'icon'=>'/lib/images/subjects/biafra.svg'],
  13 => ['id'=>13, 'name'=>'Nigeria',      'slug'=>'nigeria',      'nav_order'=>13, 'meta_description'=>'Pages related to Nigeria.',         'meta_keywords'=>'nigeria, nation, politics, history',      'icon'=>'/lib/images/subjects/nigeria.svg'],
  14 => ['id'=>14, 'name'=>'Resistance',   'slug'=>'resistance',   'nav_order'=>14, 'meta_description'=>'Pages related to IPOB.',            'meta_keywords'=>'ipob, biafra, movement, nigeria',         'icon'=>'/lib/images/subjects/ipob.svg'],
  15 => ['id'=>15, 'name'=>'Africa',       'slug'=>'africa',       'nav_order'=>15, 'meta_description'=>'Pages related to Africa.',          'meta_keywords'=>'africa, continent, heritage, nations',    'icon'=>'/lib/images/subjects/africa.svg'],
  16 => ['id'=>16, 'name'=>'UK',           'slug'=>'uk',           'nav_order'=>16, 'meta_description'=>'Pages related to the UK.',          'meta_keywords'=>'uk, britain, england, london',            'icon'=>'/lib/images/subjects/uk.svg'],
  17 => ['id'=>17, 'name'=>'Europe',       'slug'=>'europe',       'nav_order'=>17, 'meta_description'=>'Pages related to Europe.',          'meta_keywords'=>'europe, continent, nations, history',     'icon'=>'/lib/images/subjects/europe.svg'],
  18 => ['id'=>18, 'name'=>'Arabs',        'slug'=>'arabs',        'nav_order'=>18, 'meta_description'=>'Pages related to Arabs.',           'meta_keywords'=>'arabs, middle east, culture, history',    'icon'=>'/lib/images/subjects/arabs.svg'],
  19 => ['id'=>19, 'name'=>'About',        'slug'=>'about',        'nav_order'=>19, 'meta_description'=>'About this website.',               'meta_keywords'=>'about, information, project, overview',   'icon'=>'/lib/images/subjects/about.svg'],
];

/* --------------------------------------------------------------------------
 * Internal: normalize rows (defensive)
 * -------------------------------------------------------------------------- */

if (!function_exists('mk__subjects_registry_normalize')) {
  /**
   * @param array<int,array<string,mixed>> $rows
   * @return array<int,array<string,mixed>>
   */
  function mk__subjects_registry_normalize(array $rows): array {
    $out = [];

    foreach ($rows as $k => $row) {
      if (!is_array($row)) {
        continue;
      }

      $id   = isset($row['id']) ? (int)$row['id'] : (int)$k;
      $slug = isset($row['slug']) ? (string)$row['slug'] : '';
      $name = isset($row['name']) ? (string)$row['name'] : '';

      // Skip unusable entries (but never break return type)
      if ($id <= 0 || $slug === '' || $name === '') {
        continue;
      }

      $out[$id] = [
        'id'               => $id,
        'slug'             => $slug,
        'name'             => $name,
        'nav_order'        => isset($row['nav_order']) ? (int)$row['nav_order'] : PHP_INT_MAX,
        'meta_description' => isset($row['meta_description']) ? (string)$row['meta_description'] : '',
        'meta_keywords'    => isset($row['meta_keywords']) ? (string)$row['meta_keywords'] : '',
        'icon'             => isset($row['icon']) ? (string)$row['icon'] : '',
      ];
    }

    return $out;
  }
}

/* --------------------------------------------------------------------------
 * Public Accessors (guarded)
 * -------------------------------------------------------------------------- */

/**
 * Return all subjects in the registry (keyed by id).
 * MUST always return an array (never null).
 */
if (!function_exists('subjects_all_registry')) {
  function subjects_all_registry(): array {
    global $SUBJECTS_REGISTRY;

    // Safety: ensure array even if something overwrote it
    $rows = is_array($SUBJECTS_REGISTRY ?? null) ? $SUBJECTS_REGISTRY : [];

    // Normalize to protect callers (SEO/routing) from bad shapes
    return mk__subjects_registry_normalize($rows);
  }
}

/** Get by id */
if (!function_exists('subject_by_id_registry')) {
  function subject_by_id_registry(int $id): ?array {
    $all = subjects_all_registry();
    return $all[$id] ?? null;
  }
}

/** Get by slug */
if (!function_exists('subject_by_slug_registry')) {
  function subject_by_slug_registry(string $slug): ?array {
    $slug = trim($slug);
    if ($slug === '') return null;

    foreach (subjects_all_registry() as $row) {
      if (($row['slug'] ?? '') === $slug) return $row;
    }
    return null;
  }
}

/**
 * Sorted list (nav_order asc, then name asc).
 * Returns the same array shape (keyed by id).
 */
if (!function_exists('subjects_sorted_registry')) {
  function subjects_sorted_registry(): array {
    $all = subjects_all_registry();

    uasort($all, static function ($a, $b): int {
      $na = is_array($a) ? (int)($a['nav_order'] ?? PHP_INT_MAX) : PHP_INT_MAX;
      $nb = is_array($b) ? (int)($b['nav_order'] ?? PHP_INT_MAX) : PHP_INT_MAX;

      if ($na === $nb) {
        $an = is_array($a) ? (string)($a['name'] ?? '') : '';
        $bn = is_array($b) ? (string)($b['name'] ?? '') : '';
        return strcmp($an, $bn);
      }
      return $na <=> $nb;
    });

    return $all;
  }
}

/**
 * URL helper (staff-aware by default).
 * - staff=true  => /staff/subjects/{slug}/
 * - staff=false => /subjects/{slug}/
 */
if (!function_exists('subject_url_registry')) {
  function subject_url_registry(array|string $subject, bool $staff = true): string {
    $slug = is_array($subject) ? (string)($subject['slug'] ?? '') : (string)$subject;
    $slug = trim($slug);

    $base = $staff ? '/staff/subjects/' : '/subjects/';
    $path = $base . $slug . '/';

    if (function_exists('url_for')) {
      return url_for($path);
    }

    $site = defined('SITE_URL') ? rtrim((string)SITE_URL, '/') : '';
    return $site . $path;
  }
}