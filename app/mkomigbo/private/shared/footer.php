<?php
declare(strict_types=1);

/**
 * /private/shared/footer.php
 * Shared footer for both public + staff pages.
 *
 * Safe to include multiple times.
 */

if (!function_exists('h')) {
  function h(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
  }
}

$year = gmdate('Y');
$site_name = 'Mkomigbo';

// If your project has a config/env helper, use it (optional)
if (function_exists('env')) {
  $n = (string) env('SITE_NAME', '');
  if (trim($n) !== '') $site_name = trim($n);
}
?>
<footer class="site-footer" style="margin-top:28px;">
  <div class="container" style="padding:18px 14px; border-top:1px solid rgba(0,0,0,.08); opacity:.9;">
    <div style="display:flex; flex-wrap:wrap; gap:10px; justify-content:space-between; align-items:center;">
      <div>&copy; <?= h($year) ?> <?= h($site_name) ?></div>
      <div style="font-size:13px; opacity:.8;">
        Built with care.
      </div>
    </div>
  </div>
</footer>

</body>
</html>
