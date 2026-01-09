<?php
declare(strict_types=1);

/**
 * /private/shared/public_footer.php
 * Shared footer wrapper.
 *
 * GLOBAL CONTRACT (standardized):
 * - public_header.php opens <main class="site-main" id="main"> once.
 * - This footer closes </main> once, then prints footer and closes body/html.
 *
 * Optional variables:
 * - $page_scripts (string) raw HTML scripts (set before including footer)
 * - $footer_variant (string) e.g. 'Public' (default) or 'Staff' or 'Subjects'
 */

if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

$year = (int)date('Y');

$footer_variant = (isset($footer_variant) && is_string($footer_variant) && trim($footer_variant) !== '')
  ? trim($footer_variant)
  : 'Public';

/* Close <main> once if it was opened */
if (isset($GLOBALS['mk__main_open']) && $GLOBALS['mk__main_open'] === true) {
  echo "</main>\n";
  $GLOBALS['mk__main_open'] = false;
}
?>

<footer class="site-footer">
  <div class="container footer-row">
    <div class="footer-left">
      <small>&copy; <?= $year ?> Mkomigbo</small>
    </div>
    <div class="footer-right">
      <small class="muted">Built with care • <?= h($footer_variant) ?></small>
    </div>
  </div>
</footer>

<?php
/* Optional page-level scripts (raw HTML, intentional) */
if (isset($page_scripts) && is_string($page_scripts) && trim($page_scripts) !== '') {
  echo $page_scripts;
}
?>

</body>
</html>
