<?php
declare(strict_types=1);

/**
 * /public/staff/subjects/pgs/files/delete.php
 * Staff: Delete an attachment DB row + (best-effort) delete the stored file.
 *
 * Supports POST-only entry to confirmation screen.
 * Final delete is always POST.
 */

$init = dirname(__DIR__, 5) . '/private/assets/initialize.php';
if (!is_file($init)) {
  header('Content-Type: text/plain; charset=utf-8');
  echo "Init not found\nExpected: {$init}\n";
  exit;
}
require_once $init;

/* Safety: ensure h() exists (fallback) */
if (!function_exists('h')) {
  function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
  }
}

/* Safety: ensure redirect_to() exists (fallback) */
if (!function_exists('redirect_to')) {
  function redirect_to(string $url): void {
    $url = str_replace(["\r", "\n"], '', $url);
    header('Location: ' . $url, true, 302);
    exit;
  }
}

/* Flash helpers */
if (!function_exists('pf__flash_set')) {
  function pf__flash_set(string $key, string $msg): void {
    if (function_exists('mk_flash_set')) {
      mk_flash_set($key, $msg);
      return;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $_SESSION[$key] = $msg;
  }
}

/* Auth guard */
if (function_exists('require_staff')) {
  require_staff();
} elseif (function_exists('require_login')) {
  require_login();
}

/* Shared header helpers */
$staff_header = APP_ROOT . '/private/shared/staff_header.php';
if (!is_file($staff_header)) {
  header('Content-Type: text/plain; charset=utf-8');
  echo "staff_header.php not found\nExpected: {$staff_header}\n";
  exit;
}
require_once $staff_header;

/* DB */
try {
  $pdo = function_exists('db') ? db() : null;
  if (!$pdo instanceof PDO) {
    throw new RuntimeException('db() did not return a PDO instance.');
  }
} catch (Throwable $e) {
  staff_render_header('Staff • Delete Attachment — Mkomigbo', 'pages');
  echo '<main class="container" style="padding:24px 0;">';
  echo '<div class="notice error"><strong>DB Error:</strong> ' . h($e->getMessage()) . '</div>';
  echo '</main>';
  staff_render_footer();
  exit;
}

/* Inputs: accept from POST or GET */
$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');

$file_id    = (int)(($_POST['id'] ?? 0) ?: ($_GET['id'] ?? 0));
$page_id    = (int)(($_POST['page_id'] ?? 0) ?: ($_GET['page_id'] ?? 0));
$subject_id = (int)(($_POST['subject_id'] ?? 0) ?: ($_GET['subject_id'] ?? 0));

if ($file_id <= 0 || $page_id <= 0) {
  if ($subject_id > 0) {
    redirect_to(url_for('/staff/subjects/pgs/?subject_id=' . $subject_id));
  }
  redirect_to(url_for('/staff/subjects/'));
}

/* Back link (edit page) */
$back_to_edit = url_for('/staff/subjects/pgs/edit.php?id=' . $page_id . ($subject_id > 0 ? '&subject_id=' . $subject_id : ''));

/* POST-only self URL (no query string needed) */
$self_url = url_for('/staff/subjects/pgs/files/delete.php');

/* Controlled download URL */
$download_url = url_for('/staff/subjects/pgs/files/download.php?id=' . $file_id . '&page_id=' . $page_id . ($subject_id > 0 ? '&subject_id=' . $subject_id : ''));

/* Ensure table exists / accessible */
try {
  $pdo->query("SELECT 1 FROM `page_files` LIMIT 1");
} catch (Throwable $e) {
  pf__flash_set('flash_error', 'Attachments are not enabled yet (page_files table not accessible).');
  redirect_to($back_to_edit);
}

/* Load attachment row */
$row = null;
try {
  $st = $pdo->prepare("
    SELECT id, page_id, original_name, stored_name, file_path
    FROM page_files
    WHERE id = ? AND page_id = ?
    LIMIT 1
  ");
  $st->execute([$file_id, $page_id]);
  $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
  $row = null;
}

if (!$row) {
  pf__flash_set('flash_error', 'Attachment not found.');
  redirect_to($back_to_edit);
}

$original_name = (string)($row['original_name'] ?? '');
$file_path     = (string)($row['file_path'] ?? '');

/* Only allow deleting files inside this uploads folder */
$uploads_rel_dir = '/lib/uploads/page_files/';

/* Resolve filesystem path from web path (best-effort, safe) */
$project_root = dirname(__DIR__, 5);
$public_root  = realpath($project_root . '/public');

$fs_path = null;

if ($public_root && $file_path !== '') {
  if ($file_path[0] === '/' && strpos($file_path, $uploads_rel_dir) === 0) {
    $candidate = $public_root . str_replace('/', DIRECTORY_SEPARATOR, $file_path);

    $real_candidate = is_file($candidate) ? realpath($candidate) : null;
    if ($real_candidate && strpos($real_candidate, $public_root . DIRECTORY_SEPARATOR) === 0) {
      $fs_path = $real_candidate;
    }
  }
}

$errors = [];

/* Handle POST:
   - initial POST (intent=confirm) shows UI only
   - confirm_delete=yes performs deletion
*/
if ($method === 'POST') {
  if (function_exists('csrf_require')) {
    csrf_require();
  } else {
    $sent = (string)($_POST['csrf_token'] ?? '');
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $sess = (string)($_SESSION['csrf_token'] ?? '');
    if ($sent === '' || $sess === '' || !hash_equals($sess, $sent)) {
      $errors[] = 'Security check failed (CSRF). Please reload and try again.';
    }
  }

  $confirm = (string)($_POST['confirm_delete'] ?? '');
  if ($confirm === 'yes' && !$errors) {
    try {
      $stD = $pdo->prepare("DELETE FROM page_files WHERE id = ? AND page_id = ? LIMIT 1");
      $stD->execute([$file_id, $page_id]);

      if ($fs_path && is_file($fs_path)) {
        @unlink($fs_path);
      }

      pf__flash_set('flash_success', 'Attachment deleted: ' . ($original_name !== '' ? $original_name : 'attachment') . '.');
      redirect_to($back_to_edit);
    } catch (Throwable $e) {
      $errors[] = 'Delete failed: ' . $e->getMessage();
    }
  } elseif (isset($_POST['confirm_delete'])) {
    if ($confirm !== 'yes') {
      $errors[] = 'Please confirm deletion.';
    }
  }
}

/* Render confirm UI */
staff_render_header('Staff • Delete Attachment — Mkomigbo', 'pages');
?>
<main class="container" style="padding:24px 0;">

  <section class="hero">
    <div class="hero-bar"></div>
    <div class="hero-inner">
      <h1>Delete Attachment</h1>
      <p class="muted" style="margin:6px 0 0;">
        You are about to delete: <span class="pill"><?= h($original_name !== '' ? $original_name : '(Attachment)') ?></span>
      </p>

      <div class="actions" style="margin-top:14px;">
        <a class="btn" href="<?= h($back_to_edit) ?>">← Back to Edit</a>
        <?php if ($file_path !== ''): ?>
          <a class="btn" href="<?= h($download_url) ?>">Download</a>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <?php if ($errors): ?>
    <div class="notice error" style="margin-top:14px;">
      <strong>Cannot delete:</strong>
      <ul class="small" style="margin:8px 0 0 18px;">
        <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <section class="card form-card" style="margin-top:14px;">
    <form method="post" action="<?= h($self_url) ?>">
      <?php if (function_exists('csrf_field')): ?>
        <?= csrf_field() ?>
      <?php else: ?>
        <?php
          if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
          if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
          }
        ?>
        <input type="hidden" name="csrf_token" value="<?= h((string)$_SESSION['csrf_token']) ?>">
      <?php endif; ?>

      <input type="hidden" name="id" value="<?= h((string)$file_id) ?>">
      <input type="hidden" name="page_id" value="<?= h((string)$page_id) ?>">
      <input type="hidden" name="subject_id" value="<?= h((string)$subject_id) ?>">

      <div class="notice error" style="margin:0 0 12px;">
        <strong>This action cannot be undone.</strong>
        <div class="muted small">
          This removes the DB record and deletes the file (only if it lives under <span class="pill"><?= h($uploads_rel_dir) ?></span>).
        </div>
      </div>

      <label class="checkbox" style="display:flex;gap:10px;align-items:flex-start;margin:0 0 12px;">
        <input type="checkbox" name="confirm_delete" value="yes" style="margin-top:4px;">
        <span>
          <strong>I understand.</strong>
          <span class="muted small">Permanently delete this attachment.</span>
        </span>
      </label>

      <div class="actions">
        <button class="btn btn-primary" type="submit">Yes, Delete Attachment</button>
        <a class="btn" href="<?= h($back_to_edit) ?>">Cancel</a>
      </div>
    </form>
  </section>

</main>
<?php staff_render_footer(); ?>
