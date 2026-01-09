$pdo = db();
$rows = $pdo->query("SELECT id, bio FROM contributors WHERE (bio_html IS NULL OR bio_html = '')")->fetchAll(PDO::FETCH_ASSOC);

$st = $pdo->prepare("UPDATE contributors SET bio_raw = :raw, bio_html = :html WHERE id = :id");
foreach ($rows as $r) {
  $raw = (string)($r['bio'] ?? '');
  $html = mk_sanitize_allowlist_html($raw);
  $st->execute([':raw'=>$raw, ':html'=>$html, ':id'=>(int)$r['id']]);
}
echo "Done: " . count($rows);
