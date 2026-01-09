<?php
declare(strict_types=1);

/**
 * /public/staff/platforms/index.php
 * Staff: Platforms list (premium scaffold)
 *
 * Matches Subjects layout exactly.
 */

require_once __DIR__ . '/../_init.php';

/* ---------------------------------------------------------
   Safety helpers (fallbacks)
--------------------------------------------------------- */
if (!function_exists('h')) {
  function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
  }
}

/* ---------------------------------------------------------
   Schema helpers (shared pattern)
--------------------------------------------------------- */
if (!function_exists('pf__table_exists')) {
  function pf__table_exists(PDO $pdo, string $table): bool
  {
    $table = trim($table);
    if ($table === '') return false;

    try {
      $sql = "SELECT 1
              FROM information_schema.tables
              WHERE table_schema = DATABASE()
                AND table_name = :t
              LIMIT 1";
      $st = $pdo->prepare($sql);
      $st->execute([':t' => $table]);
      return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
      try {
        $st = $pdo->prepare("SHOW TABLES LIKE :t");
        $st->execute([':t' => $table]);
        return (bool)$st->fetchColumn();
      } catch (Throwable $e2) {
        return false;
      }
    }
  }
}

if (!function_exists('pf__columns')) {
  function pf__columns(PDO $pdo, string $table): array
  {
    try {
      $st = $pdo->query("DESCRIBE `{$table}`");
      $cols = [];
      foreach (($st ? $st->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
        if (!empty($row['Field'])) $cols[] = (string)$row['Field'];
      }
      return $cols;
    } catch (Throwable $e) {
      return [];
    }
  }
}

if (!function_exists('pf__pick_first')) {
  function pf__pick_first(array $cols, array $candidates): ?string
  {
    $map = [];
    foreach ($cols as $c) {
      $map[strtolower((string)$c)] = (string)$c;
    }
    foreach ($candidates as $cand) {
      $k = strtolower((string)$cand);
      if (isset($map[$k])) return $map[$k];
    }
    return null;
  }
}

/* ---------------------------------------------------------
   DB (PDO) — canonical staff accessor
--------------------------------------------------------- */
$pdo = function_exists('staff_pdo') ? staff_pdo() : null;

/* ---------------------------------------------------------
   Filters (match Subjects UX)
--------------------------------------------------------- */
$q = trim((string)($_GET['q'] ?? ''));

$return_params = [];
if ($q !== '') { $return_params['q'] = $q; }
$return_qs = $return_params ? ('?' . http_build_query($return_params, '', '&', PHP_QUERY_RFC3986)) : '';
$return_path = '/staff/platforms/index.php' . $return_qs;

/* ---------------------------------------------------------
   Header (shared)
--------------------------------------------------------- */
$active_nav = 'platforms';
$page_title = 'Manage Platforms • Staff';
$page_desc  = 'Manage platforms (modules, sections, content hubs).';

$staff_subnav = [
  ['label' => 'Dashboard', 'href' => url_for('/staff/'),           'active' => false],
  ['label' => 'Platforms', 'href' => url_for('/staff/platforms/'), 'active' => true],
  ['label' => 'Public',    'href' => url_for('/platforms/'),       'active' => false],
];

require_once APP_ROOT . '/private/shared/staff_header.php';

/* ---------------------------------------------------------
   Fetch list (schema-tolerant)
--------------------------------------------------------- */
$rows = [];
$warn = '';

if (!$pdo instanceof PDO) {
  $warn = 'Database connection is not available in this request context.';
} else {
  $table = 'platforms';

  if (!pf__table_exists($pdo, $table)) {
    $warn = 'Table "platforms" not found yet. This page is ready; add the platforms table when you implement Platforms CRUD.';
  } else {
    $cols = pf__columns($pdo, $table);

    $c_id   = pf__pick_first($cols, ['id', 'platform_id']);
    $c_name = pf__pick_first($cols, ['name', 'title', 'label']);
    $c_slug = pf__pick_first($cols, ['slug', 'key', 'handle']);
    $c_desc = pf__pick_first($cols, ['description', 'summary', 'body']);
    $c_pub  = pf__pick_first($cols, ['is_public', 'published', 'is_active', 'active']);

    $select = [];
    if ($c_id)   $select[] = "`{$c_id}` AS id";
    if ($c_name) $select[] = "`{$c_name}` AS name";
    if ($c_slug) $select[] = "`{$c_slug}` AS slug";
    if ($c_desc) $select[] = "`{$c_desc}` AS description";
    if ($c_pub)  $select[] = "`{$c_pub}` AS is_public";

    if (!$select) {
      $warn = 'Platforms table exists, but no recognized columns were found (expected at least id + name/title).';
    } else {
      $sql = "SELECT " . implode(', ', $select) . " FROM `{$table}` WHERE 1=1";
      $params = [];

      if ($q !== '') {
        $parts = [];
        if ($c_name) $parts[] = "`{$c_name}` LIKE :q";
        if ($c_slug) $parts[] = "`{$c_slug}` LIKE :q";
        if ($c_desc) $parts[] = "`{$c_desc}` LIKE :q";
        if (!$parts && $c_id) $parts[] = "CAST(`{$c_id}` AS CHAR) LIKE :q";
        if ($parts) {
          $sql .= " AND (" . implode(' OR ', $parts) . ")";
          $params[':q'] = '%' . $q . '%';
        }
      }

      if ($c_name) $sql .= " ORDER BY `{$c_name}` ASC";
      elseif ($c_id) $sql .= " ORDER BY `{$c_id}` DESC";
      $sql .= " LIMIT 200";

      try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
      } catch (Throwable $e) {
        $warn = 'Failed to query platforms table safely (schema or permission issue).';
      }
    }
  }
}

/* URLs */
$u = static function(string $path): string {
  return function_exists('url_for') ? url_for($path) : $path;
};

?>
<div class="container">

  <div class="hero">
    <div class="hero__row">
      <div>
        <h1 class="hero__title">Platforms</h1>
        <p class="hero__sub">Manage platforms (modules, sections, and content hubs).</p>
      </div>
      <div class="hero__actions">
        <span class="btn btn--primary btn--disabled" aria-disabled="true">+ New Platform (soon)</span>
      </div>
    </div>
  </div>

  <?php if ($warn !== ''): ?>
    <div class="alert alert--warning"><?php echo h($warn); ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card__body">

      <form class="stack" method="get" action="<?php echo h($u('/staff/platforms/index.php')); ?>">
        <div class="row row--wrap row--gap">
          <div class="field">
            <label class="label" for="q">Search</label>
            <input class="input" id="q" name="q" value="<?php echo h($q); ?>" placeholder="name / slug / description / id">
          </div>

          <div class="field" style="align-self:flex-end;">
            <button class="btn" type="submit">Apply</button>
            <a class="btn btn--ghost" href="<?php echo h($u('/staff/platforms/index.php')); ?>">Clear</a>
          </div>

          <div class="muted" style="align-self:flex-end;">
            <?php echo (int)count($rows); ?> platform(s)
          </div>
        </div>
      </form>

      <hr class="sep">

      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th style="width:90px;">ID</th>
              <th>Name</th>
              <th style="width:220px;">Slug</th>
              <th style="width:110px;">Status</th>
              <th style="width:240px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr>
                <td colspan="5" class="muted">No platforms found (or table not ready).</td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <?php
                  $id   = (string)($r['id'] ?? '');
                  $name = (string)($r['name'] ?? '(Unnamed)');
                  $slug = (string)($r['slug'] ?? '—');
                  $pub  = $r['is_public'] ?? null;

                  $is_public = null;
                  if (is_numeric($pub)) $is_public = ((int)$pub === 1);
                  elseif (is_bool($pub)) $is_public = $pub;
                ?>
                <tr>
                  <td class="mono"><?php echo h($id); ?></td>
                  <td>
                    <div class="strong"><?php echo h($name); ?></div>
                    <?php if (!empty($r['description'])): ?>
                      <div class="muted"><?php echo h((string)$r['description']); ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="mono"><?php echo h($slug); ?></td>
                  <td>
                    <?php if ($is_public === true): ?>
                      <span class="pill pill--success">Public</span>
                    <?php elseif ($is_public === false): ?>
                      <span class="pill pill--muted">Draft</span>
                    <?php else: ?>
                      <span class="muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="row row--gap">
                    <span class="btn btn--sm btn--disabled" aria-disabled="true">View</span>
                    <span class="btn btn--sm btn--disabled" aria-disabled="true">Edit</span>
                    <span class="btn btn--sm btn--disabled" aria-disabled="true">Delete</span>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</div>

<?php
require APP_ROOT . '/private/shared/staff_footer.php';
