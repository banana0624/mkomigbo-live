<?php
declare(strict_types=1);

/**
 * scripts_repository.php
 * Read-only repository for Scripts / Concepts / Mappings with provenance.
 *
 * Requires:
 * - db connection available as $db OR db_connect() helper
 * - h(), url_for() helpers exist (per your codebase)
 */

function mk_db(): mysqli {
  if (function_exists('db_connect')) { return db_connect(); }
  global $db;
  if ($db instanceof mysqli) { return $db; }
  throw new RuntimeException('Database connection not available.');
}

function mk_scripts_all(): array {
  $db = mk_db();
  $sql = "SELECT id, slug, name, script_type, era, lens
          FROM scripts
          ORDER BY sort_order ASC, id ASC";
  $res = $db->query($sql);
  return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function mk_script_by_slug(string $slug): ?array {
  $db = mk_db();
  $stmt = $db->prepare("SELECT * FROM scripts WHERE slug=? LIMIT 1");
  $stmt->bind_param("s", $slug);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  return $row ?: null;
}

function mk_concepts_for_script(string $script_slug, array $opts = []): array {
  $db = mk_db();

  $domain = $opts['domain'] ?? null; // optional
  $sensitivity_max = $opts['sensitivity_max'] ?? 'public'; // public|limited|restricted
  $limit = (int)($opts['limit'] ?? 200);

  // sensitivity ordering
  $sensRank = ['public' => 1, 'limited' => 2, 'restricted' => 3];
  $maxRank = $sensRank[$sensitivity_max] ?? 1;

  $sql = "SELECT
            c.id AS concept_id,
            c.domain,
            c.igbo_term,
            c.english_gloss,
            c.description,
            m.sensitivity,
            m.confidence,
            m.context_tag,
            m.remarks,
            s.citation_key,
            s.authors,
            s.year,
            s.title AS source_title
          FROM scripts sc
          JOIN script_concept_map m ON m.script_id = sc.id
          JOIN concepts c ON c.id = m.concept_id
          LEFT JOIN sources s ON s.id = m.source_id
          WHERE sc.slug = ?
          " . ($domain ? " AND c.domain = ? " : "") . "
          ORDER BY c.domain ASC, c.igbo_term ASC, c.id ASC
          LIMIT {$limit}";

  $stmt = $db->prepare($sql);
  if ($domain) {
    $stmt->bind_param("ss", $script_slug, $domain);
  } else {
    $stmt->bind_param("s", $script_slug);
  }
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

  // enforce sensitivity ceiling defensively
  $out = [];
  foreach ($rows as $r) {
    $rank = $sensRank[$r['sensitivity']] ?? 1;
    if ($rank <= $maxRank) { $out[] = $r; }
  }
  return $out;
}

function mk_domains_for_script(string $script_slug, string $sensitivity_max='public'): array {
  $db = mk_db();
  $rows = mk_concepts_for_script($script_slug, ['sensitivity_max' => $sensitivity_max, 'limit' => 5000]);
  $domains = [];
  foreach ($rows as $r) { $domains[$r['domain']] = true; }
  $keys = array_keys($domains);
  sort($keys);
  return $keys;
}
