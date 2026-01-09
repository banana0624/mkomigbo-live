<?php
declare(strict_types=1);

/**
 * /private/functions/slug.php
 * Canonical slug policy:
 * - kebab-case only
 * - ASCII only (best effort)
 * - no underscores
 * - no empty slugs
 */

function mk_slugify(string $s, int $maxLen = 191): string {
  $s = trim($s);
  if ($s === '') return '';

  // best-effort transliteration if ext-intl exists
  if (function_exists('transliterator_transliterate')) {
    $s = transliterator_transliterate('Any-Latin; Latin-ASCII; NFKD; [:Nonspacing Mark:] Remove; NFC;', $s);
  }

  $s = strtolower($s);
  $s = str_replace('_', '-', $s);

  // keep alnum + hyphen only
  $s = preg_replace('/[^a-z0-9\-]+/', '-', $s) ?? $s;

  // collapse and trim hyphens
  $s = preg_replace('/\-{2,}/', '-', $s) ?? $s;
  $s = trim($s, '-');

  if ($s === '') return '';
  if (strlen($s) > $maxLen) $s = substr($s, 0, $maxLen);
  $s = rtrim($s, '-');

  return $s;
}

/**
 * Ensure uniqueness in a table by adding "-2", "-3", ...
 * $whereSql is optional extra condition like "subject_id = :sid"
 */
function mk_slug_unique(PDO $pdo, string $table, string $slug, string $column = 'slug', string $whereSql = '', array $params = []): string {
  $base = $slug;
  $i = 1;

  while (true) {
    $candidate = ($i === 1) ? $base : ($base . '-' . $i);

    $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = :slug";
    if ($whereSql !== '') $sql .= " AND ({$whereSql})";

    $st = $pdo->prepare($sql);
    $st->execute(array_merge([':slug' => $candidate], $params));
    $count = (int)$st->fetchColumn();

    if ($count === 0) return $candidate;
    $i++;
    if ($i > 2000) {
      throw new RuntimeException("Unable to generate unique slug for {$table}.{$column}");
    }
  }
}
