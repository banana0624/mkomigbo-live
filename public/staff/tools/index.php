<?php
declare(strict_types=1);

/**
 * /public/staff/tools/index.php
 * Staff-only Tools Hub
 * - Diagnostics (Health)
 * - Audit
 * - Scan
 * - Error Log (tail viewer)
 */

require_once __DIR__ . '/../../_init.php';

if (function_exists('require_staff')) {
  require_staff();
} elseif (function_exists('require_login')) {
  require_login();
}

if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

$staff_header = APP_ROOT . '/private/shared/staff_header.php';
if (is_file($staff_header)) {
  require_once $staff_header;
  if (function_exists('staff_render_header')) {
    staff_render_header('Tools', 'dashboard');
  }
} else {
  echo "<!doctype html><meta charset='utf-8'><title>Tools</title><body>";
}

/* ---- tools list ---- */
$tools = [
  [
    'title' => 'Diagnostics (Linking + Capabilities)',
    'desc'  => 'Checks core files, HTTP asset serving, permissions, and scans key templates for broken CSS/JS/img links.',
    'href'  => (function_exists('url_for') ? url_for('/staff/tools/diagnostics.php') : '/staff/tools/diagnostics.php'),
    'badge' => 'Health',
  ],
  [
    'title' => 'Audit (Security + Performance)',
    'desc'  => 'Heuristic scan for common weaknesses: CSRF gaps, raw input, unsafe echoes, risky SQL patterns, etc.',
    'href'  => (function_exists('url_for') ? url_for('/staff/tools/audit.php') : '/staff/tools/audit.php'),
    'badge' => 'Audit',
  ],
  [
    'title' => 'Scan (Assets + Includes)',
    'desc'  => 'Finds relative asset paths that break CSS at deeper URLs, missing /public assets, missing includes, optional lint.',
    'href'  => (function_exists('url_for') ? url_for('/staff/tools/scan.php') : '/staff/tools/scan.php'),
    'badge' => 'Scan',
  ],
  [
    'title' => 'Error Log (Tail Viewer)',
    'desc'  => 'View last lines of the PHP error log (staff-only). Helps debug 500 errors quickly.',
    'href'  => (function_exists('url_for') ? url_for('/staff/tools/error_log.php') : '/staff/tools/error_log.php'),
    'badge' => 'Logs',
  ],
];

?>
<div class="container" style="max-width:1100px; margin:0 auto; padding:18px;">
  <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div>
      <h1 style="margin:0; font:800 22px system-ui;">Staff Tools</h1>
      <div style="margin-top:6px; color:#6b7280; font:400 14px system-ui;">
        Diagnostics, audits, scans, and log tools (restricted).
      </div>
    </div>
    <div>
      <a href="<?= h(function_exists('url_for') ? url_for('/staff/') : '/staff/') ?>"
         style="text-decoration:none; padding:9px 12px; border:1px solid #e5e7eb; border-radius:12px; background:#fff; color:#111827; font:700 13px system-ui;">
        ← Back to Staff
      </a>
    </div>
  </div>

  <div style="margin-top:16px; display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:12px;">
    <?php foreach ($tools as $t): ?>
      <a href="<?= h((string)$t['href']) ?>"
         style="display:block; text-decoration:none; color:inherit; border:1px solid #e5e7eb; border-radius:16px; background:#fff; padding:14px;">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
          <div style="font:800 15px system-ui;"><?= h((string)$t['title']) ?></div>
          <div style="font:700 12px system-ui; color:#111827; border:1px solid #e5e7eb; background:#f9fafb; padding:4px 8px; border-radius:999px;">
            <?= h((string)$t['badge']) ?>
          </div>
        </div>
        <div style="margin-top:8px; color:#6b7280; font:400 13px system-ui; line-height:1.5;">
          <?= h((string)$t['desc']) ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <div style="margin-top:16px; color:#6b7280; font:13px system-ui;">
    Tip: bookmark <code>/staff/tools/</code> during development.
  </div>
</div>

<?php
if (function_exists('staff_render_footer')) {
  staff_render_footer();
} else {
  echo "</body>";
}
