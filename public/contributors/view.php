<?php
declare(strict_types=1);

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

require_once __DIR__ . '/../_init.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

if (function_exists('require_staff_login')) { require_staff_login(); }

$pdo = db();

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? '')); // active|draft|all

$where = [];
$params = [];

if ($q !== '') {
  $where[] = "(display_name LIKE :q OR email LIKE :q OR slug LIKE :q)";
  $params[':q'] = '%' . $q . '%';
}

if ($status === 'active' || $status === 'draft') {
  if (db_column_exists('contributors','status')) {
    $where[] = "status = :st";
    $params[':st'] = $status;
  }
}

$sql = "SELECT id, display_name, email, slug, status, created_at
        FROM contributors";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY id DESC LIMIT 500";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Contributors';
$breadcrumbs = [
  ['label' => 'Staff', 'href' => url_for('/staff/')],
  ['label' => 'Contributors', 'href' => null],
];

require_once PRIVATE_PATH . '/shared/staff_header.php';
?>
<section class="section">
  <div class="container">
    <div class="toolbar">
      <form class="toolbar__search" method="get" action="">
        <input class="input" type="search" name="q" value="<?php echo h($q); ?>" placeholder="Search name, email, slug">
        <select class="select" name="status">
          <option value="" <?php echo $status===''?'selected':''; ?>>All statuses</option>
          <option value="active" <?php echo $status==='active'?'selected':''; ?>>Active</option>
          <option value="draft" <?php echo $status==='draft'?'selected':''; ?>>Draft</option>
        </select>
        <button class="btn" type="submit">Filter</button>
      </form>

      <div class="toolbar__actions">
        <a class="btn btn--ghost" href="<?php echo url_for('/contributors/'); ?>" target="_blank" rel="noopener">View public</a>
      </div>
    </div>

    <form method="post" action="<?php echo url_for('/staff/contributors/bulk.php'); ?>" class="card">
      <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">

      <div class="card__body">
        <div class="bulkbar">
          <select class="select" name="action" required>
            <option value="">Bulk action…</option>
            <option value="set_active">Set Active</option>
            <option value="set_draft">Set Draft</option>
          </select>
          <button class="btn" type="submit">Apply</button>
        </div>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th style="width:36px;"><input type="checkbox" onclick="document.querySelectorAll('input[name=&quot;ids[]&quot;]').forEach(cb=>cb.checked=this.checked)"></th>
                <th>Name</th>
                <th>Email</th>
                <th>Slug</th>
                <th>Status</th>
                <th>ID</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr><td colspan="6" class="muted">No contributors found.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td><input type="checkbox" name="ids[]" value="<?php echo (int)$r['id']; ?>"></td>
                    <td><?php echo h((string)$r['display_name']); ?></td>
                    <td><?php echo h((string)$r['email']); ?></td>
                    <td><?php echo h((string)$r['slug']); ?></td>
                    <td><?php echo h((string)($r['status'] ?? '')); ?></td>
                    <td><?php echo (int)$r['id']; ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <p class="muted" style="margin-top:12px;">
          Bulk status control is enforced server-side and protected by CSRF.
        </p>
      </div>
    </form>
  </div>
</section>
<?php require_once PRIVATE_PATH . '/shared/staff_footer.php'; ?>
