<?php
declare(strict_types=1);

/**
 * /app/mkomigbo/private/functions/public_subjects_list.php
 *
 * Single source of truth for public Subjects list:
 * - Registry order is canonical (ID order wins)
 * - Optional DB overlay (name/desc/icon/visibility) if `db()` exists
 * - Never-empty fallback to canonical 20 subjects
 *
 * Returns rows:
 *   [
 *     'registry_id' => int,
 *     'db_id'       => int,
 *     'slug'        => string,
 *     'name'        => string,
 *     'description' => string,
 *     'icon_path'   => string,
 *   ]
 */

if (!function_exists('mk_public_subjects_list')) {

  function mk_public_subjects_list(): array {

    $valid_slug = static function (string $slug): bool {
      $slug = strtolower(trim($slug));
      return ($slug !== '') && (bool)preg_match('/^[a-z0-9][a-z0-9_-]{0,190}$/', $slug);
    };

    // Canonical 19 — never invent.
    $hardcoded_registry_19 = static function (): array {
      return [
        ['id'=>1,  'slug'=>'history',      'name'=>'History'],
        ['id'=>2,  'slug'=>'slavery',      'name'=>'Slavery'],
        ['id'=>3,  'slug'=>'people',       'name'=>'People'],
        ['id'=>4,  'slug'=>'persons',      'name'=>'Persons'],
        ['id'=>5,  'slug'=>'culture',      'name'=>'Culture'],
        ['id'=>6,  'slug'=>'religion',     'name'=>'Religion'],
        ['id'=>7,  'slug'=>'esoterism', 'name'=>'Esoterism'],
        ['id'=>8,  'slug'=>'tradition',    'name'=>'Tradition'],
        ['id'=>9,  'slug'=>'language1',    'name'=>'Language1'],
        ['id'=>10, 'slug'=>'language2',    'name'=>'Language2'],
        ['id'=>11, 'slug'=>'struggles',    'name'=>'Struggles'],
        ['id'=>12, 'slug'=>'biafra',       'name'=>'Biafra'],
        ['id'=>13, 'slug'=>'nigeria',      'name'=>'Nigeria'],
        ['id'=>14, 'slug'=>'resistance',   'name'=>'Resistance'],
        ['id'=>15, 'slug'=>'africa',       'name'=>'Africa'],
        ['id'=>16, 'slug'=>'uk',           'name'=>'UK'],
        ['id'=>17, 'slug'=>'europe',       'name'=>'Europe'],
        ['id'=>18, 'slug'=>'arabs',        'name'=>'Arabs'],
        ['id'=>19, 'slug'=>'about',        'name'=>'About'],
        ['id'=>20, 'slug'=>'pogrom',       'name'=>'Pogrom'],
      ];
    };

    /* ---------------------------------------------------------
       Load registry helpers + registry file (best-effort)
       IMPORTANT: your real file is subjects_register.php
    --------------------------------------------------------- */
    try {
      $helperCandidates = [];

      if (defined('APP_ROOT')) {
        $helperCandidates[] = rtrim((string)APP_ROOT, "/\\") . '/private/functions/subjects_registry_helpers.php';
      }
      if (defined('PRIVATE_PATH')) {
        $helperCandidates[] = rtrim((string)PRIVATE_PATH, "/\\") . '/functions/subjects_registry_helpers.php';
      }

      // Real deployment fallback:
      $helperCandidates[] = dirname(__DIR__) . '/functions/subjects_registry_helpers.php';

      foreach ($helperCandidates as $p) {
        if (is_string($p) && $p !== '' && is_file($p)) { require_once $p; break; }
      }
    } catch (Throwable $e) {}

    try {
      $regCandidates = [];

      if (defined('APP_ROOT')) {
        $regCandidates[] = rtrim((string)APP_ROOT, "/\\") . '/private/registry/subjects_register.php';
      }
      if (defined('PRIVATE_PATH')) {
        $regCandidates[] = rtrim((string)PRIVATE_PATH, "/\\") . '/registry/subjects_register.php';
      }

      // Real deployment fallback:
      $regCandidates[] = dirname(__DIR__) . '/registry/subjects_register.php';

      foreach ($regCandidates as $p) {
        if (is_string($p) && $p !== '' && is_file($p)) { require_once $p; break; }
      }
    } catch (Throwable $e) {}

    /* ---------------------------------------------------------
       Pull registry subjects (canonical order)
    --------------------------------------------------------- */
    $registry_subjects = [];
    try {
      if (function_exists('mk_subjects_registry_sorted')) {
        $tmp = mk_subjects_registry_sorted();
        if (is_array($tmp)) $registry_subjects = $tmp;
      } elseif (function_exists('subjects_sorted_registry')) {
        $tmp = subjects_sorted_registry();
        if (is_array($tmp)) $registry_subjects = $tmp;
      } elseif (function_exists('subjects_all_registry')) {
        $tmp = subjects_all_registry();
        if (is_array($tmp)) $registry_subjects = array_values($tmp);
      }
    } catch (Throwable $e) {
      $registry_subjects = [];
    }

    $registry_subjects = array_values(array_filter((array)$registry_subjects, static fn($r): bool => is_array($r)));
    if (!$registry_subjects) $registry_subjects = $hardcoded_registry_19();

    /* ---------------------------------------------------------
       Optional DB overlay (never blocks rendering)
    --------------------------------------------------------- */
    $db_by_slug = [];

    try {
      $pdo = function_exists('db') ? db() : null;
      if ($pdo instanceof PDO) {

        $st = $pdo->query("SELECT id, slug FROM subjects WHERE slug IS NOT NULL AND slug <> ''");
        $baseRows = $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

        $ids = [];
        foreach ($baseRows as $r) {
          $slug = strtolower(trim((string)($r['slug'] ?? '')));
          if (!$valid_slug($slug)) continue;
          $id = (int)($r['id'] ?? 0);
          if ($id <= 0) continue;
          $ids[] = $id;
          $db_by_slug[$slug] = ['id' => $id, 'slug' => $slug];
        }

        if ($ids) {
          $try_col = static function (PDO $pdo, array $ids, string $col): array {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT id, {$col} AS v FROM subjects WHERE id IN ({$ph})";
            $st = $pdo->prepare($sql);
            $st->execute($ids);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
          };

          // name
          $nameMap = [];
          foreach (['name','menu_name','subject_name'] as $c) {
            try {
              foreach ($try_col($pdo, $ids, $c) as $rr) {
                $id = (int)($rr['id'] ?? 0);
                $v  = trim((string)($rr['v'] ?? ''));
                if ($id > 0 && $v !== '') $nameMap[$id] = $v;
              }
              if ($nameMap) break;
            } catch (Throwable $e) {}
          }

          // description
          $descMap = [];
          foreach (['meta_description','short_desc','description','content'] as $c) {
            try {
              $any = false;
              foreach ($try_col($pdo, $ids, $c) as $rr) {
                $id = (int)($rr['id'] ?? 0);
                $v  = trim((string)($rr['v'] ?? ''));
                if ($id > 0 && $v !== '') { $descMap[$id] = $v; $any = true; }
              }
              if ($any) break;
            } catch (Throwable $e) {}
          }

          // icon
          $iconMap = [];
          foreach (['icon_path','icon','logo','logo_path'] as $c) {
            try {
              foreach ($try_col($pdo, $ids, $c) as $rr) {
                $id = (int)($rr['id'] ?? 0);
                $v  = trim((string)($rr['v'] ?? ''));
                if ($id > 0 && $v !== '') $iconMap[$id] = $v;
              }
              if ($iconMap) break;
            } catch (Throwable $e) {}
          }

          // visibility
          $visibleIds = array_fill_keys($ids, true);
          try {
            foreach ($try_col($pdo, $ids, 'status') as $rr) {
              $id = (int)($rr['id'] ?? 0);
              $v  = strtolower(trim((string)($rr['v'] ?? '')));
              if ($id > 0) $visibleIds[$id] = in_array($v, ['active','published','public'], true);
            }
          } catch (Throwable $e) {
            try {
              foreach ($try_col($pdo, $ids, 'is_public') as $rr) {
                $id = (int)($rr['id'] ?? 0);
                $v  = $rr['v'] ?? null;
                if ($id > 0) $visibleIds[$id] = ((int)$v === 1);
              }
            } catch (Throwable $e2) {}
          }

          foreach ($db_by_slug as $slug => $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) continue;
            $db_by_slug[$slug]['name']        = $nameMap[$id] ?? '';
            $db_by_slug[$slug]['description'] = $descMap[$id] ?? '';
            $db_by_slug[$slug]['icon_path']   = $iconMap[$id] ?? '';
            $db_by_slug[$slug]['is_visible']  = $visibleIds[$id] ?? true;
          }
        }
      }
    } catch (Throwable $e) {
      $db_by_slug = [];
    }

    /* ---------------------------------------------------------
       Build final list (registry order is FINAL)
    --------------------------------------------------------- */
    $final = [];

    foreach ($registry_subjects as $r) {
      if (!is_array($r)) continue;

      $slug = strtolower(trim((string)($r['slug'] ?? '')));
      if (!$valid_slug($slug)) continue;

      $registry_id = (int)($r['id'] ?? 0);

      $name = trim((string)($r['name'] ?? ''));
      if ($name === '') $name = $slug;

      $desc = '';
      if (isset($r['description']) && is_string($r['description'])) $desc = trim($r['description']);
      elseif (isset($r['meta_description']) && is_string($r['meta_description'])) $desc = trim($r['meta_description']);

      $row = [
        'registry_id' => $registry_id,
        'db_id'       => 0,
        'slug'        => $slug,
        'name'        => $name,
        'description' => $desc,
        'icon_path'   => trim((string)($r['icon'] ?? '')),
      ];

      if (isset($db_by_slug[$slug])) {
        $dbrow = $db_by_slug[$slug];

        if (array_key_exists('is_visible', $dbrow) && $dbrow['is_visible'] === false) continue;

        $dbId = (int)($dbrow['id'] ?? 0);
        if ($dbId > 0) $row['db_id'] = $dbId;

        $dbName = trim((string)($dbrow['name'] ?? ''));
        if ($dbName !== '') $row['name'] = $dbName;

        $dbDesc = trim((string)($dbrow['description'] ?? ''));
        if ($dbDesc !== '') $row['description'] = $dbDesc;

        $dbIcon = trim((string)($dbrow['icon_path'] ?? ''));
        if ($dbIcon !== '') $row['icon_path'] = $dbIcon;
      }

      $final[] = $row;
    }

    if (!$final) {
      foreach ($hardcoded_registry_19() as $x) {
        $final[] = [
          'registry_id' => (int)($x['id'] ?? 0),
          'db_id'       => 0,
          'slug'        => (string)($x['slug'] ?? ''),
          'name'        => (string)($x['name'] ?? ''),
          'description' => '',
          'icon_path'   => '',
        ];
      }
    }

    return $final;
  }
}
