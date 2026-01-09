<?php
declare(strict_types=1);

/**
 * /public/staff/subjects/pgs/new.php
 * Staff: Create a new page.
 */

/* ---------------------------------------------------------
   Locate initialize.php (bounded upward scan)
--------------------------------------------------------- */
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

$init = mk_find_init(__DIR__);
if (!$init) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Init not found.\n";
  echo "Start: " . __DIR__ . "\n";
  echo "Expected one of:\n";
  echo " - {dir}/private/assets/initialize.php\n";
  echo " - {dir}/app/mkomigbo/private/assets/initialize.php\n";
  echo " - {dir}/app/private/assets/initialize.php\n";
  exit;
}
require_once $init;

if (function_exists('require_staff')) {
  require_staff();
} elseif (function_exists('require_login')) {
  require_login();
}

if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('redirect_to')) {
  function redirect_to(string $location): void {
    $location = str_replace(["\r", "\n"], '', $location);
    header('Location: ' . $location, true, 302);
    exit;
  }
}

/* CSRF */
if (!function_exists('csrf_token')) {
  function csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
  }
}
if (!function_exists('csrf_field')) {
  function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
  }
}
if (!function_exists('csrf_verify')) {
  function csrf_verify(?string $token): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $sess = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sess) || $sess === '' || !is_string($token) || $token === '') return false;
    return hash_equals($sess, $token);
  }
}

/* Flash */
if (!function_exists('pf__flash_set')) {
  function pf__flash_set(string $key, string $msg): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) $_SESSION['flash'] = [];
    $_SESSION['flash'][$key] = $msg;
  }
}
if (!function_exists('pf__flash_get')) {
  function pf__flash_get(string $key): string {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $msg = '';
    if (isset($_SESSION['flash'][$key])) {
      $msg = (string)$_SESSION['flash'][$key];
      unset($_SESSION['flash'][$key]);
    }
    return $msg;
  }
}

/* Safe return */
if (!function_exists('pf__safe_return_url')) {
  function pf__safe_return_url(string $raw, string $default): string {
    $raw = trim($raw);
    if ($raw === '') return $default;
    $raw = rawurldecode($raw);
    if ($raw === '' || $raw[0] !== '/') return $default;
    if (preg_match('~^//~', $raw)) return $default;
    if (preg_match('~^[a-z]+:~i', $raw)) return $default;
    if (!preg_match('~^/staff/~', $raw)) return $default;
    return $raw;
  }
}

/* Column inspector */
if (!function_exists('pf__column_exists')) {
  function pf__column_exists(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $key = strtolower($table . '.' . $column);
    if (array_key_exists($key, $cache)) return (bool)$cache[$key];

    $st = $pdo->prepare("
      SELECT COUNT(*)
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
      LIMIT 1
    ");
    $st->execute([$table, $column]);
    $cache[$key] = ((int)$st->fetchColumn() > 0);
    return (bool)$cache[$key];
  }
}

/* DB */
$pdo = function_exists('db') ? db() : null;
if (!$pdo instanceof PDO) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Database handle db() not available.\n";
  exit;
}

/* Return */
$return = pf__safe_return_url((string)($_GET['return'] ?? ''), '/staff/subjects/pgs/index.php');

$notice = pf__flash_get('notice');
$error  = pf__flash_get('error');

/* Schema */
$has_title     = pf__column_exists($pdo, 'pages', 'title');
$has_menu_name = pf__column_exists($pdo, 'pages', 'menu_name');
$has_name      = pf__column_exists($pdo, 'pages', 'name');

$has_slug      = pf__column_exists($pdo, 'pages', 'slug');
$has_subject   = pf__column_exists($pdo, 'pages', 'subject_id');

$has_content   = pf__column_exists($pdo, 'pages', 'content');
$has_body      = pf__column_exists($pdo, 'pages', 'body');

$has_nav_order = pf__column_exists($pdo, 'pages', 'nav_order');
$has_position  = pf__column_exists($pdo, 'pages', 'position');
$order_col     = $has_nav_order ? 'nav_order' : ($has_position ? 'position' : null);

$has_is_public = pf__column_exists($pdo, 'pages', 'is_public');
$has_visible   = pf__column_exists($pdo, 'pages', 'visible');
$pub_col       = $has_is_public ? 'is_public' : ($has_visible ? 'visible' : null);

/* Subjects list for dropdown (if subject_id exists) */
$subjects = [];
if ($has_subject) {
  $sub_has_menu = pf__column_exists($pdo, 'subjects', 'menu_name');
  $sub_has_name = pf__column_exists($pdo, 'subjects', 'name');
  $sub_title = $sub_has_menu ? 'menu_name' : ($sub_has_name ? 'name' : null);

  if ($sub_title) {
    $st = $pdo->query("SELECT id, {$sub_title} AS title FROM subjects ORDER BY {$sub_title} ASC, id ASC");
    $subjects = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } else {
    $st = $pdo->query("SELECT id FROM subjects ORDER BY id ASC");
    $subjects = array_map(fn($r) => ['id'=>$r['id'], 'title'=>'Subject #'.$r['id']], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
  }
}

/* Defaults */
$form = [
  'subject_id' => '',
  'title'      => '',
  'slug'       => '',
  'content'    => '',
  'order'      => '',
  'is_public'  => '0',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
    pf__flash_set('error', 'Security check failed (CSRF). Please retry.');
    redirect_to('/staff/subjects/pgs/new.php?return=' . rawurlencode($return));
  }

  $form['subject_id'] = trim((string)($_POST['subject_id'] ?? ''));
  $form['title']      = trim((string)($_POST['title'] ?? ''));
  $form['slug']       = trim((string)($_POST['slug'] ?? ''));
  $form['content']    = (string)($_POST['content'] ?? '');
  $form['order']      = trim((string)($_POST['order'] ?? ''));
  $form['is_public']  = ((string)($_POST['is_public'] ?? '0') === '1') ? '1' : '0';

  if ($has_subject && $form['subject_id'] === '') {
    $error = 'Please choose a subject.';
  } elseif ($form['title'] === '') {
    $error = 'Please enter a title.';
  }

  if ($error === '') {
    try {
      $fields = [];
      $vals   = [];
      $bind   = [];

      if ($has_subject) {
        $fields[] = 'subject_id';
        $vals[] = ':subject_id';
        $bind[':subject_id'] = (int)$form['subject_id'];
      }

      // choose best title column
      if ($has_title) {
        $fields[] = 'title'; $vals[] = ':title'; $bind[':title'] = $form['title'];
      } elseif ($has_menu_name) {
        $fields[] = 'menu_name'; $vals[] = ':title'; $bind[':title'] = $form['title'];
      } elseif ($has_name) {
        $fields[] = 'name'; $vals[] = ':title'; $bind[':title'] = $form['title'];
      }

      if ($has_slug) {
        $fields[] = 'slug'; $vals[] = ':slug'; $bind[':slug'] = $form['slug'];
      }

      $content_col = $has_content ? 'content' : ($has_body ? 'body' : null);
      if ($content_col) {
        $fields[] = $content_col; $vals[] = ':content'; $bind[':content'] = $form['content'];
      }

      if ($order_col) {
        $fields[] = $order_col; $vals[] = ':ord';
        $bind[':ord'] = ($form['order'] === '') ? null : (int)$form['order'];
      }

      if ($pub_col) {
        $fields[] = $pub_col; $vals[] = ':pub';
        $bind[':pub'] = (int)$form['is_public'];
      }

      if (!$fields) {
        throw new RuntimeException('No insertable columns found in pages table.');
      }

      $sql = "INSERT INTO pages (" . implode(',', $fields) . ") VALUES (" . implode(',', $vals) . ")";
      $st = $pdo->prepare($sql);

      foreach ($bind as $k => $v) {
        if ($v === null) $st->bindValue($k, null, PDO::PARAM_NULL);
        elseif (is_int($v)) $st->bindValue($k, $v, PDO::PARAM_INT);
        else $st->bindValue($k, (string)$v, PDO::PARAM_STR);
      }

      $st->execute();
      pf__flash_set('notice', 'Page created.');
      redirect_to($return);

    } catch (Throwable $e) {
      $error = 'Create failed: ' . $e->getMessage();
    }
  }
}

/* Render */
$active_nav = 'staff';
$page_title = 'New Page • Staff';
require_once APP_ROOT . '/private/shared/staff_header.php';

$action = function_exists('url_for') ? url_for('/staff/subjects/pgs/new.php?return=' . rawurlencode($return)) : '/staff/subjects/pgs/new.php?return=' . rawurlencode($return);

?>
<div class="container">

  <div class="hero">
    <div class="hero__row">
      <div>
        <h1 class="hero__title">New Page</h1>
        <p class="hero__sub">Create a new public page.</p>
      </div>
      <div class="hero__actions">
        <a class="btn btn--ghost" href="<?php echo h($return); ?>">← Back</a>
      </div>
    </div>
  </div>

  <?php if ($notice !== ''): ?><div class="alert alert--success"><?php echo h($notice); ?></div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="alert alert--danger"><?php echo h($error); ?></div><?php endif; ?>

  <div class="card">
    <div class="card__body">
      <form method="post" action="<?php echo h($action); ?>" class="stack">
        <?php echo csrf_field(); ?>

        <?php if ($has_subject): ?>
          <div class="field">
            <label class="label" for="subject_id">Subject</label>
            <select class="input" id="subject_id" name="subject_id" required>
              <option value="">— Choose —</option>
              <?php foreach ($subjects as $s): ?>
                <?php $sid = (int)($s['id'] ?? 0); $stitle = (string)($s['title'] ?? ('Subject #'.$sid)); ?>
                <option value="<?php echo h((string)$sid); ?>" <?php echo ((string)$sid === $form['subject_id']) ? 'selected' : ''; ?>>
                  <?php echo h($stitle); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="field">
          <label class="label" for="title">Title</label>
          <input class="input" id="title" name="title" value="<?php echo h($form['title']); ?>" required>
        </div>

        <?php if ($has_slug): ?>
          <div class="field">
            <label class="label" for="slug">Slug</label>
            <input class="input mono" id="slug" name="slug" value="<?php echo h($form['slug']); ?>" placeholder="e.g. overview">
          </div>
        <?php endif; ?>

        <?php if ($has_content || $has_body): ?>
          <div class="field">
            <label class="label" for="content">Content</label>
            <textarea class="input" id="content" name="content" rows="10"><?php echo h($form['content']); ?></textarea>
          </div>
        <?php endif; ?>

        <?php if ($order_col): ?>
          <div class="field">
            <label class="label" for="order">Order</label>
            <input class="input mono" id="order" name="order" type="number" value="<?php echo h($form['order']); ?>" placeholder="10, 20, 30...">
          </div>
        <?php endif; ?>

        <?php if ($pub_col): ?>
          <div class="field">
            <label class="check">
              <input type="checkbox" name="is_public" value="1" <?php echo ($form['is_public'] === '1') ? 'checked' : ''; ?>>
              <span>Public (published)</span>
            </label>
          </div>
        <?php endif; ?>

        <div class="row row--gap">
          <button class="btn btn--primary" type="submit">Create page</button>
          <a class="btn btn--ghost" href="<?php echo h($return); ?>">Cancel</a>
        </div>
      </form>
    </div>
  </div>

</div>

<?php
$staff_footer = APP_ROOT . '/private/shared/staff_footer.php';
if (is_file($staff_footer)) require $staff_footer;
else echo "</body></html>";
