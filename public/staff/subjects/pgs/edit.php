<?php
declare(strict_types=1);

/**
 * /public/staff/subjects/pgs/edit.php
 * Staff: Edit a page + manage attachments (Phase 3).
 *
 * Uses centralized staff bootstrap: /public/staff/_init.php
 * - staff_pdo()
 * - staff_safe_return_url()
 * - staff_csrf_verify()
 * - staff_csrf_field()
 */

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

require_once __DIR__ . '/../../_init.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

/* ---------------------------------------------------------
   Minimal fallbacks (only if _init.php did not provide them)
--------------------------------------------------------- */
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
    if (isset($_SESSION['flash']) && is_array($_SESSION['flash']) && array_key_exists($key, $_SESSION['flash'])) {
      $msg = (string)$_SESSION['flash'][$key];
      unset($_SESSION['flash'][$key]);
    }
    return $msg;
  }
}
if (!function_exists('staff_safe_return_url')) {
  function staff_safe_return_url(string $raw, string $default): string {
    $raw = trim($raw);
    if ($raw === '') return $default;
    $raw = rawurldecode($raw);
    if ($raw === '' || $raw[0] !== '/') return $default;
    if (preg_match('~^//~', $raw)) return $default;
    if (preg_match('~^[a-z]+:~i', $raw)) return $default;
    if (strpos($raw, '/staff/') !== 0) return $default;
    return $raw;
  }
}
if (!function_exists('staff_csrf_verify')) {
  function staff_csrf_verify(string $token): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $sess = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sess) || $sess === '' || $token === '') return false;
    return hash_equals($sess, $token);
  }
}
if (!function_exists('staff_csrf_field')) {
  function staff_csrf_field(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf_token" value="' . h((string)$_SESSION['csrf_token']) . '">';
  }
}
if (!function_exists('staff_pdo')) {
  function staff_pdo(): ?PDO {
    return (function_exists('db') && db() instanceof PDO) ? db() : null;
  }
}

/* ---------------------------------------------------------
   Auth (defense-in-depth)
--------------------------------------------------------- */
if (function_exists('require_staff')) {
  require_staff();
} elseif (function_exists('require_staff_login')) {
  require_staff_login();
} elseif (function_exists('mk_require_staff_login')) {
  mk_require_staff_login();
}

/* DB */
$pdo = staff_pdo();
if (!$pdo instanceof PDO) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Database handle not available.\n";
  exit;
}

/* Optional slug helpers */
$slugFn = (defined('PRIVATE_PATH') ? (PRIVATE_PATH . '/functions/slug.php') : '');
if ($slugFn !== '' && is_file($slugFn)) require_once $slugFn;

/* Inputs */
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect_to('/staff/subjects/pgs/index.php');

$return = staff_safe_return_url((string)($_GET['return'] ?? ($_POST['return'] ?? '')), '/staff/subjects/pgs/index.php');

$notice = pf__flash_get('notice');
$error  = pf__flash_get('error');

/* Attachment notices from upload/delete controllers */
$attachNotice = strtolower(trim((string)($_GET['attach'] ?? '')));
$attachMsg = '';
if ($attachNotice === 'sent')    $attachMsg = 'Attachment uploaded.';
if ($attachNotice === 'partial') $attachMsg = 'Some files uploaded; some failed.';
if ($attachNotice === 'error')   $attachMsg = 'Attachment upload failed.';
if ($attachNotice === 'deleted') $attachMsg = 'Attachment deleted.';
if ($attachNotice === 'missing') $attachMsg = 'Attachment missing (already removed or file not found).';
if ($attachNotice === 'denied')  $attachMsg = 'Action denied.';
if ($attachNotice === 'csrf')    $attachMsg = 'Security check failed. Please retry.';
if ($attachNotice === 'invalid') $attachMsg = 'Invalid attachment request.';

/* Fetch current page */
$st = $pdo->prepare("SELECT id, subject_id, title, slug, body, nav_order, is_public FROM pages WHERE id = :id LIMIT 1");
$st->execute([':id' => $id]);
$page = $st->fetch(PDO::FETCH_ASSOC);

if (!$page) {
  pf__flash_set('error', 'Page not found.');
  redirect_to($return);
}

/* Subjects dropdown */
$subjects = [];
try {
  $sub_has_menu = ((int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='subjects' AND COLUMN_NAME='menu_name'")->fetchColumn() > 0);
  $sub_has_name = ((int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='subjects' AND COLUMN_NAME='name'")->fetchColumn() > 0);

  $sub_title_col = $sub_has_menu ? 'menu_name' : ($sub_has_name ? 'name' : 'id');
  $subjects = $pdo->query("SELECT id, {$sub_title_col} AS title, slug FROM subjects ORDER BY {$sub_title_col} ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $subjects = [];
}

/* Form state */
$form = [
  'subject_id' => (string)($page['subject_id'] ?? ''),
  'title'      => (string)($page['title'] ?? ''),
  'slug'       => (string)($page['slug'] ?? ''),
  'body'       => (string)($page['body'] ?? ''),
  'nav_order'  => (string)($page['nav_order'] ?? ''),
  'is_public'  => ((int)($page['is_public'] ?? 0) === 1) ? '1' : '0',
];

/* Handle POST update */
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
  $token = (string)($_POST['csrf_token'] ?? '');
  if (!staff_csrf_verify($token)) {
    pf__flash_set('error', 'Security check failed (CSRF). Please retry.');
    redirect_to('/staff/subjects/pgs/edit.php?id=' . rawurlencode((string)$id) . '&return=' . rawurlencode($return));
  }

  $form['subject_id'] = trim((string)($_POST['subject_id'] ?? ''));
  $form['title']      = trim((string)($_POST['title'] ?? ''));
  $form['slug']       = trim((string)($_POST['slug'] ?? ''));
  $form['body']       = (string)($_POST['body'] ?? '');
  $form['nav_order']  = trim((string)($_POST['nav_order'] ?? ''));
  $form['is_public']  = ((string)($_POST['is_public'] ?? '0') === '1') ? '1' : '0';

  if ($form['subject_id'] === '') $error = 'Please choose a subject.';
  elseif ($form['title'] === '')  $error = 'Please enter a title.';

  if ($error === '' && $form['slug'] === '' && function_exists('mk_slugify')) {
    $form['slug'] = (string)mk_slugify($form['title']);
  }
  if ($error === '' && $form['slug'] === '') $error = 'Please enter a slug.';

  if ($error === '' && function_exists('mk_slug_unique')) {
    $sid = (int)$form['subject_id'];
    $form['slug'] = (string)mk_slug_unique(
      $pdo,
      'pages',
      $form['slug'],
      'slug',
      'subject_id = :sid AND id <> :id',
      [':sid' => $sid, ':id' => $id]
    );
  }

  if ($error === '') {
    try {
      $sql = "UPDATE pages
              SET subject_id = :sid,
                  title      = :title,
                  slug       = :slug,
                  body       = :body,
                  nav_order  = :nav_order,
                  is_public  = :is_public
              WHERE id = :id
              LIMIT 1";
      $st = $pdo->prepare($sql);
      $st->bindValue(':id', $id, PDO::PARAM_INT);
      $st->bindValue(':sid', (int)$form['subject_id'], PDO::PARAM_INT);
      $st->bindValue(':title', $form['title'], PDO::PARAM_STR);
      $st->bindValue(':slug', $form['slug'], PDO::PARAM_STR);
      $st->bindValue(':body', $form['body'], PDO::PARAM_STR);
      if ($form['nav_order'] === '') $st->bindValue(':nav_order', null, PDO::PARAM_NULL);
      else $st->bindValue(':nav_order', (int)$form['nav_order'], PDO::PARAM_INT);
      $st->bindValue(':is_public', (int)$form['is_public'], PDO::PARAM_INT);
      $st->execute();

      pf__flash_set('notice', 'Page updated.');
      redirect_to($return);
    } catch (Throwable $e) {
      $error = 'Update failed: ' . $e->getMessage();
    }
  }
}

/* Load attachments (schema tolerant) */
$attachments = [];
$pageFilesExists = false;

try {
  $pageFilesExists = ((int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'page_files'")->fetchColumn() > 0);

  if ($pageFilesExists) {
    $have = [];
    $stc = $pdo->query("SHOW COLUMNS FROM page_files");
    $rows = $stc ? ($stc->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    foreach ($rows as $r) {
      $f = (string)($r['Field'] ?? '');
      if ($f !== '') $have[$f] = true;
    }

    $select = ['id','page_id'];
    foreach (['filename','stored_name','mime','filesize','is_external','external_url','created_at'] as $c) {
      if (isset($have[$c])) $select[] = $c;
    }

    $sql = "SELECT " . implode(', ', array_unique($select)) . " FROM page_files WHERE page_id = :pid ORDER BY id DESC";
    $st = $pdo->prepare($sql);
    $st->execute([':pid' => $id]);
    $attachments = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
} catch (Throwable $e) {
  $attachments = [];
}

/* Header */
$active_nav = 'pgs';
$page_title = 'Edit Page • Staff';
require_once APP_ROOT . '/private/shared/staff_header.php';

/* URLs */
$action = (function_exists('url_for') ? (string)url_for('/staff/subjects/pgs/edit.php') : '/staff/subjects/pgs/edit.php')
  . '?id=' . rawurlencode((string)$id) . '&return=' . rawurlencode($return);

$show_url = (function_exists('url_for') ? (string)url_for('/staff/subjects/pgs/show.php') : '/staff/subjects/pgs/show.php')
  . '?id=' . rawurlencode((string)$id) . '&return=' . rawurlencode($return);

$upload_action = function_exists('url_for') ? (string)url_for('/staff/pages/attachments_upload.php') : '/staff/pages/attachments_upload.php';
$delete_action = function_exists('url_for') ? (string)url_for('/staff/pages/attachments_delete.php') : '/staff/pages/attachments_delete.php';

$current_uri = (string)($_SERVER['REQUEST_URI'] ?? ('/staff/subjects/pgs/edit.php?id=' . $id));
$current_uri = staff_safe_return_url($current_uri, '/staff/subjects/pgs/edit.php?id=' . $id);

?>
<div class="container">

  <div class="hero">
    <div class="hero__row">
      <div>
        <h1 class="hero__title">Edit Page</h1>
        <p class="hero__sub"><?php echo h($form['title']); ?></p>
      </div>
      <div class="hero__actions">
        <a class="btn btn--ghost" href="<?php echo h($return); ?>">← Back</a>
        <a class="btn" href="<?php echo h($show_url); ?>">View</a>
      </div>
    </div>
  </div>

  <?php if ($notice !== ''): ?><div class="alert alert--success"><?php echo h($notice); ?></div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="alert alert--danger"><?php echo h($error); ?></div><?php endif; ?>
  <?php if ($attachMsg !== ''): ?><div class="alert alert--info"><?php echo h($attachMsg); ?></div><?php endif; ?>

  <div class="card">
    <div class="card__body">
      <form method="post" action="<?php echo h($action); ?>" class="stack">
        <?php echo staff_csrf_field(); ?>
        <input type="hidden" name="return" value="<?php echo h($return); ?>">

        <div class="field">
          <label class="label" for="subject_id">Subject</label>
          <select class="input" id="subject_id" name="subject_id" required>
            <option value="">— Choose —</option>
            <?php foreach ($subjects as $s): ?>
              <?php
                $sid = (int)($s['id'] ?? 0);
                $stitle = (string)($s['title'] ?? ('Subject #' . $sid));
              ?>
              <option value="<?php echo h((string)$sid); ?>" <?php echo ((string)$sid === $form['subject_id']) ? 'selected' : ''; ?>>
                <?php echo h($stitle); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label class="label" for="title">Title</label>
          <input class="input" id="title" name="title" value="<?php echo h($form['title']); ?>" required>
        </div>

        <div class="field">
          <label class="label" for="slug">Slug</label>
          <input class="input mono" id="slug" name="slug" value="<?php echo h($form['slug']); ?>" placeholder="auto if blank">
        </div>

        <div class="field">
          <label class="label" for="nav_order">Nav order</label>
          <input class="input mono" id="nav_order" name="nav_order" type="number" value="<?php echo h($form['nav_order']); ?>" placeholder="10, 20, 30...">
        </div>

        <div class="field">
          <label class="check">
            <input type="checkbox" name="is_public" value="1" <?php echo ($form['is_public'] === '1') ? 'checked' : ''; ?>>
            <span>Public (published)</span>
          </label>
        </div>

        <div class="field">
          <label class="label" for="body">Body</label>
          <textarea class="input" id="body" name="body" rows="12"><?php echo h($form['body']); ?></textarea>
        </div>

        <div class="row row--gap">
          <button class="btn btn--primary" type="submit">Save changes</button>
          <a class="btn btn--ghost" href="<?php echo h($return); ?>">Cancel</a>
        </div>

      </form>
    </div>
  </div>

  <!-- Attachments -->
  <div class="card" style="margin-top:14px;">
    <div class="card__body">

      <div class="row row--gap" style="align-items:center; justify-content:space-between;">
        <h2 style="margin:0;">Attachments</h2>
      </div>

      <?php if (!$pageFilesExists): ?>
        <div class="alert alert--warning" style="margin-top:10px;">
          The <code>page_files</code> table is missing, so attachments are disabled.
        </div>
      <?php else: ?>

        <form method="post" action="<?php echo h($upload_action); ?>" enctype="multipart/form-data" class="stack" style="margin-top:12px;">
          <?php echo staff_csrf_field(); ?>
          <input type="hidden" name="page_id" value="<?php echo (int)$id; ?>">
          <input type="hidden" name="return" value="<?php echo h($current_uri); ?>">

          <div class="field">
            <label class="label" for="attachments">Add attachments</label>
            <input class="input" id="attachments" type="file" name="attachments[]" multiple>
            <div class="muted" style="margin-top:6px;">
              Allowed types and max size are enforced by the upload handler.
            </div>
          </div>

          <div class="row row--gap">
            <button class="btn btn--primary" type="submit">Upload</button>
          </div>
        </form>

        <?php if (empty($attachments)): ?>
          <p class="muted" style="margin-top:12px;"><em>No attachments yet.</em></p>
        <?php else: ?>
          <div style="margin-top:14px; overflow:auto;">
            <table class="table" style="width:100%; min-width:720px;">
              <thead>
                <tr>
                  <th style="text-align:left;">Filename</th>
                  <th style="text-align:left;">Type</th>
                  <th style="text-align:right;">Size</th>
                  <th style="text-align:left;">Added</th>
                  <th style="text-align:right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($attachments as $a): ?>
                  <?php
                    $aid   = (int)($a['id'] ?? 0);
                    $name  = trim((string)($a['filename'] ?? '')) ?: ('Attachment #' . $aid);
                    $mime  = (string)($a['mime'] ?? '');
                    $bytes = isset($a['filesize']) ? (int)$a['filesize'] : 0;
                    $when  = (string)($a['created_at'] ?? '');
                    $isExt = !empty($a['is_external']);

                    $publicHref = '/attachments/file.php?id=' . $aid;

                    $sizeLabel = '—';
                    if ($bytes > 0) {
                      $kb = $bytes / 1024;
                      $mb = $kb / 1024;
                      $sizeLabel = ($mb >= 1) ? (number_format($mb, 2) . ' MB') : (number_format($kb, 0) . ' KB');
                    }
                  ?>
                  <tr>
                    <td>
                      <a href="<?php echo h($publicHref); ?>" target="_blank" rel="noopener">
                        <?php echo h($name); ?>
                      </a>
                      <?php if ($isExt && !empty($a['external_url'])): ?>
                        <div class="muted" style="margin-top:4px; font-size:.9rem;">
                          External: <?php echo h((string)$a['external_url']); ?>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td><?php echo h($mime !== '' ? $mime : '—'); ?></td>
                    <td style="text-align:right;"><?php echo h($sizeLabel); ?></td>
                    <td><?php echo h($when !== '' ? $when : '—'); ?></td>
                    <td style="text-align:right;">
                      <form method="post" action="<?php echo h($delete_action); ?>" style="display:inline;">
                        <?php echo staff_csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int)$aid; ?>">
                        <input type="hidden" name="page_id" value="<?php echo (int)$id; ?>">
                        <input type="hidden" name="return" value="<?php echo h($current_uri); ?>">
                        <button class="btn btn--ghost" type="submit" onclick="return confirm('Delete this attachment?');">Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

      <?php endif; ?>
    </div>
  </div>

</div>

<?php
$staff_footer = APP_ROOT . '/private/shared/staff_footer.php';
if (is_file($staff_footer)) require $staff_footer;
else echo "</body></html>";
