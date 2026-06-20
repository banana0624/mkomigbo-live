<?php
declare(strict_types=1);

/**
 * /private/registry/subjects_register.php
 *
 * Canonical static registry for Subjects (source of truth for the public list order).
 *
 * Used for:
 * - Public index fallback (guarantee 19 subjects exist even if DB is empty)
 * - Routing fallbacks (slug → id/name/meta/icon)
 * - SEO meta fallbacks per subject
 *
 * HARD RULE:
 * - MUST NOT invent subjects outside this registry.
 * - Order is by id/nav_order (1..19) and is stable.
 */

if (defined('MK_REGISTRY_SUBJECTS_LOADED')) { return; }
define('MK_REGISTRY_SUBJECTS_LOADED', true);

/* ---------------------------------------------------------
   Helpers (internal)
--------------------------------------------------------- */
if (!function_exists('mk__subjects_registry_slug_ok')) {
  function mk__subjects_registry_slug_ok(string $slug): bool
  {
    $slug = strtolower(trim($slug));
    return $slug !== '' && (bool)preg_match('/^[a-z0-9][a-z0-9_-]{0,190}$/', $slug);
  }
}

if (!function_exists('mk__subjects_registry_normalize')) {
  /**
   * Normalizes and validates rows.
   * @param array<int,array<string,mixed>> $rows
   * @return array<int,array<string,mixed>> keyed by id
   */
  function mk__subjects_registry_normalize(array $rows): array
  {
    $out = [];
    $seenSlug = [];
    $seenOrder = [];

    foreach ($rows as $k => $row) {
      if (!is_array($row)) continue;

      $id = (int)($row['id'] ?? $k);
      if ($id <= 0) continue;

      $slug = strtolower(trim((string)($row['slug'] ?? '')));
      if (!mk__subjects_registry_slug_ok($slug))) continue;

      if (isset($seenSlug[$slug])) continue;
      $seenSlug[$slug] = true;

      $name = trim((string)($row['name'] ?? ''));
      if ($name === '') $name = ucfirst(str_replace(['-','_'], ' ', $slug));

      $nav = (int)($row['nav_order'] ?? $id);
      if ($nav <= 0) $nav = $id;

      // Keep nav_order unique if possible; if duplicate, fall back to id for sorting.
      if (isset($seenOrder[$nav])) {
        $nav = $id;
      }
      $seenOrder[$nav] = true;

      $desc = '';
      if (isset($row['description']) && is_string($row['description'])) $desc = trim($row['description']);
      elseif (isset($row['meta_description']) && is_string($row['meta_description'])) $desc = trim($row['meta_description']);

      $keys = isset($row['meta_keywords']) && is_string($row['meta_keywords']) ? trim($row['meta_keywords']) : '';
      $icon = isset($row['icon']) && is_string($row['icon']) ? trim($row['icon']) : '';
      $status = isset($row['status']) && is_string($row['status']) ? trim($row['status']) : 'active';
      if ($status === '') $status = 'active';

      $out[$id] = [
        'id'            => $id,
        'slug'          => $slug,
        'name'          => $name,
        'nav_order'     => $nav,
        'description'   => $desc,
        'meta_keywords' => $keys,
        'icon'          => $icon,
        'status'        => $status,
      ];
    }

    // Ensure stable ordering keys are consistent
    ksort($out, SORT_NUMERIC);

    return $out;
  }
}

/* ---------------------------------------------------------
   Registry data (keyed by numeric id)
   NOTE: This is your canonical set. If you later change slugs/names,
         do it here and in DB seed, but keep IDs stable.
--------------------------------------------------------- */

/**
 * @var array<int,array<string,mixed>>
 */
$SUBJECTS_REGISTRY = [
  1  => ['id'=>1,  'name'=>'History',      'slug'=>'history',      'nav_order'=>1,  'description'=>'Pages related to history.',         'meta_keywords'=>'history, past, heritage, records',        'icon'=>'/lib/images/subjects/history.svg',      'status'=>'active'],
  2  => ['id'=>2,  'name'=>'Slavery',      'slug'=>'slavery',      'nav_order'=>2,  'description'=>'Pages related to slavery.',         'meta_keywords'=>'slavery, trade, bondage, history',        'icon'=>'/lib/images/subjects/slavery.svg',      'status'=>'active'],
  3  => ['id'=>3,  'name'=>'People',       'slug'=>'people',       'nav_order'=>3,  'description'=>'Pages related to people.',          'meta_keywords'=>'people, community, individuals',          'icon'=>'/lib/images/subjects/people.svg',       'status'=>'active'],
  4  => ['id'=>4,  'name'=>'Persons',      'slug'=>'persons',      'nav_order'=>4,  'description'=>'Pages related to persons.',         'meta_keywords'=>'persons, individuals, biographies',       'icon'=>'/lib/images/subjects/persons.svg',      'status'=>'active'],
  5  => ['id'=>5,  'name'=>'Culture',      'slug'=>'culture',      'nav_order'=>5,  'description'=>'Pages related to culture.',         'meta_keywords'=>'culture, lifestyle, arts, heritage',      'icon'=>'/lib/images/subjects/culture.svg',      'status'=>'active'],
  6  => ['id'=>6,  'name'=>'Religion',     'slug'=>'religion',     'nav_order'=>6,  'description'=>'Pages related to religion.',        'meta_keywords'=>'religion, faith, worship, belief',        'icon'=>'/lib/images/subjects/religion.svg',     'status'=>'active'],
  7  => ['id'=>7,  'name'=>'Esoterism', 'slug'=>'esoterism', 'nav_order'=>7,  'description'=>'Esoteric traditions — inner teachings, hidden knowledge, and mystical philosophy.',    'meta_keywords'=>'spirituality, meditation, faith, soul',   'icon'=>'/lib/images/subjects/esoterism.svg', 'status'=>'active'],
  8  => ['id'=>8,  'name'=>'Tradition',    'slug'=>'tradition',    'nav_order'=>8,  'description'=>'Pages related to tradition.',       'meta_keywords'=>'tradition, customs, practices, heritage', 'icon'=>'/lib/images/subjects/tradition.svg',    'status'=>'active'],
  9  => ['id'=>9,  'name'=>'Language1',    'slug'=>'language1',    'nav_order'=>9,  'description'=>'Pages related to first language.',  'meta_keywords'=>'language, communication, dialect',        'icon'=>'/lib/images/subjects/language1.svg',    'status'=>'active'],
  10 => ['id'=>10, 'name'=>'Language2',    'slug'=>'language2',    'nav_order'=>10, 'description'=>'Pages related to second language.', 'meta_keywords'=>'language, communication, dialect',        'icon'=>'/lib/images/subjects/language2.svg',    'status'=>'active'],
  11 => ['id'=>11, 'name'=>'Struggles',    'slug'=>'struggles',    'nav_order'=>11, 'description'=>'Pages related to struggles.',       'meta_keywords'=>'struggles, resistance, survival',         'icon'=>'/lib/images/subjects/struggles.svg',    'status'=>'active'],
  12 => ['id'=>12, 'name'=>'Biafra',       'slug'=>'biafra',       'nav_order'=>12, 'description'=>'Pages related to Biafra.',          'meta_keywords'=>'biafra, war, independence, nigeria',      'icon'=>'/lib/images/subjects/biafra.svg',       'status'=>'active'],
  13 => ['id'=>13, 'name'=>'Nigeria',      'slug'=>'nigeria',      'nav_order'=>13, 'description'=>'Pages related to Nigeria.',         'meta_keywords'=>'nigeria, nation, politics, history',      'icon'=>'/lib/images/subjects/nigeria.svg',      'status'=>'active'],
  14 => ['id'=>14, 'name'=>'Resistance',   'slug'=>'resistance',   'nav_order'=>14, 'description'=>'Pages related to resistance.',      'meta_keywords'=>'resistance, movement, activism',          'icon'=>'/lib/images/subjects/ipob.svg',         'status'=>'active'],
  15 => ['id'=>15, 'name'=>'Africa',       'slug'=>'africa',       'nav_order'=>15, 'description'=>'Pages related to Africa.',          'meta_keywords'=>'africa, continent, heritage, nations',    'icon'=>'/lib/images/subjects/africa.svg',       'status'=>'active'],
  16 => ['id'=>16, 'name'=>'UK',           'slug'=>'uk',           'nav_order'=>16, 'description'=>'Pages related to the UK.',          'meta_keywords'=>'uk, britain, england, london',            'icon'=>'/lib/images/subjects/uk.svg',           'status'=>'active'],
  17 => ['id'=>17, 'name'=>'Europe',       'slug'=>'europe',       'nav_order'=>17, 'description'=>'Pages related to Europe.',          'meta_keywords'=>'europe, continent, nations, history',     'icon'=>'/lib/images/subjects/europe.svg',       'status'=>'active'],
  18 => ['id'=>18, 'name'=>'Arabs',        'slug'=>'arabs',        'nav_order'=>18, 'description'=>'Pages related to Arabs.',           'meta_keywords'=>'arabs, middle east, culture, history',    'icon'=>'/lib/images/subjects/arabs.svg',        'status'=>'active'],
  19 => ['id'=>19, 'name'=>'About',        'slug'=>'about',        'nav_order'=>19, 'description'=>'About this website.',               'meta_keywords'=>'about, information, project, overview',   'icon'=>'/lib/images/subjects/about.svg',        'status'=>'active'],
  20 => ['id'=>20, 'name'=>'Pogrom',       'slug'=>'pogrom',       'nav_order'=>20, 'description'=>'The Igbo pogroms — mass killings of 1966 and their historical context.', 'meta_keywords'=>'pogrom, massacre, 1966, Igbo, genocide', 'icon'=>'/lib/images/subjects/biafra.svg', 'status'=>'active'],
];

/* ---------------------------------------------------------
   Public accessors (canonical contract)
--------------------------------------------------------- */
if (!function_exists('subjects_all_registry')) {
  /**
   * @return array<int,array<string,mixed>> keyed by id
   */
  function subjects_all_registry(): array
  {
    global $SUBJECTS_REGISTRY;
    $rows = (isset($SUBJECTS_REGISTRY) && is_array($SUBJECTS_REGISTRY)) ? $SUBJECTS_REGISTRY : [];
    $norm = mk__subjects_registry_normalize($rows);
    return is_array($norm) ? $norm : [];
  }
}

if (!function_exists('subjects_sorted_registry')) {
  /**
   * @return array<int,array<string,mixed>> list, ordered by nav_order then id
   */
  function subjects_sorted_registry(): array
  {
    $all = subjects_all_registry();

    uasort($all, static function ($a, $b): int {
      $oa = is_array($a) ? (int)($a['nav_order'] ?? $a['id'] ?? PHP_INT_MAX) : PHP_INT_MAX;
      $ob = is_array($b) ? (int)($b['nav_order'] ?? $b['id'] ?? PHP_INT_MAX) : PHP_INT_MAX;
      if ($oa !== $ob) return $oa <=> $ob;

      $ia = is_array($a) ? (int)($a['id'] ?? PHP_INT_MAX) : PHP_INT_MAX;
      $ib = is_array($b) ? (int)($b['id'] ?? PHP_INT_MAX) : PHP_INT_MAX;
      return $ia <=> $ib;
    });

    return array_values($all);
  }
}

if (!function_exists('subject_by_id_registry')) {
  function subject_by_id_registry(int $id): ?array
  {
    $all = subjects_all_registry();
    return $all[$id] ?? null;
  }
}

if (!function_exists('subject_by_slug_registry')) {
  function subject_by_slug_registry(string $slug): ?array
  {
    $slug = strtolower(trim($slug));
    if (!mk__subjects_registry_slug_ok($slug)) return null;

    foreach (subjects_all_registry() as $row) {
      if (!is_array($row)) continue;
      if (isset($row['slug']) && strtolower((string)$row['slug']) === $slug) return $row;
    }
    return null;
  }
}

/* ---------------------------------------------------------
   Compat shim: unified name expected by your public code
   (Your subjects index already calls mk_subjects_registry_sorted())
--------------------------------------------------------- */
if (!function_exists('mk_subjects_registry_sorted')) {
  /**
   * @return array<int,array<string,mixed>> list in canonical order (id 1..19)
   */
  function mk_subjects_registry_sorted(): array
  {
    return subjects_sorted_registry();
  }
}

/* ---------------------------------------------------------
   SEO helper (compat-friendly)
--------------------------------------------------------- */
if (!function_exists('mk_subject_meta_fallback')) {
  /**
   * @return array<string,string>
   */
  function mk_subject_meta_fallback(string $slug): array
  {
    $slug = strtolower(trim($slug));
    $row = subject_by_slug_registry($slug);

    $name = is_array($row) ? trim((string)($row['name'] ?? '')) : '';
    $desc = is_array($row) ? trim((string)($row['description'] ?? '')) : '';
    $keys = is_array($row) ? trim((string)($row['meta_keywords'] ?? '')) : '';
    $icon = is_array($row) ? trim((string)($row['icon'] ?? '')) : '';

    $brand = defined('MK_BRAND_NAME') ? trim((string)MK_BRAND_NAME) : 'Mkomigbo';
    if ($brand === '') $brand = 'Mkomigbo';

    $title = ($name !== '') ? ($name . ' • ' . $brand) : '';

    return [
      // legacy-ish
      'title'            => $title,
      'description'      => $desc,
      'keywords'         => $keys,
      // meta keys some code uses
      'meta_description' => $desc,
      'meta_keywords'    => $keys,
      'icon'             => $icon,
      'name'             => $name,
    ];
  }
}

/* ---------------------------------------------------------
   URL helper
--------------------------------------------------------- */
if (!function_exists('subject_url_registry')) {
  function subject_url_registry($subject, bool $staff = false): string
  {
    $slug = is_array($subject) ? (string)($subject['slug'] ?? '') : (string)$subject;
    $slug = strtolower(trim($slug));
    if (!mk__subjects_registry_slug_ok($slug)) $slug = trim((string)$slug);

    $base = $staff ? '/staff/subjects/' : '/subjects/';
    $path = $base . $slug . '/';

    return function_exists('url_for') ? (string)url_for($path) : $path;
  }
}
