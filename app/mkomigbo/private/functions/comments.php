<?php
// mkomigbo/private/functions/comments.php
// Versioned comment storage: create, edit (version), list, moderate

require_once __DIR__ . '/security.php';

function comments_storage_dir() {
    $dir = __DIR__ . '/../registry/comments';
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    return $dir;
}

// Create new comment (pending moderation)
function comment_create(array $data) {
    // Expected: subject_id, page_id, author_id (nullable), body
    $runId = generate_run_id();
    $record = [
        'id'         => bin2hex(random_bytes(8)),
        'subject_id' => (string)($data['subject_id'] ?? ''),
        'page_id'    => (string)($data['page_id'] ?? ''),
        'author_id'  => $data['author_id'] ?? null,
        'body'       => (string)($data['body'] ?? ''),
        'status'     => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'run_id'     => $runId,
        'versions'   => [], // will hold edits
    ];
    // basic validation
    if ($record['subject_id'] === '' || $record['page_id'] === '' || trim($record['body']) === '') {
        throw new RuntimeException('Invalid comment fields.');
    }
    // file-based storage
    $file = comments_storage_dir() . '/' . $record['id'] . '.json';
    file_put_contents($file, json_encode($record, JSON_PRETTY_PRINT));
    log_moderation_event($record['author_id'] ?? 'visitor', 'create', $record['id'], "status=pending run=$runId");
    return $record;
}

// Edit comment (append version, keep audit trail)
function comment_edit(string $id, string $actorId, string $newBody) {
    $file = comments_storage_dir() . '/' . $id . '.json';
    if (!is_file($file)) {
        throw new RuntimeException('Comment not found.');
    }
    $data = json_decode(file_get_contents($file), true);
    $version = [
        'edited_at' => date('Y-m-d H:i:s'),
        'actor_id'  => $actorId,
        'old_body'  => $data['body'],
        'new_body'  => $newBody,
    ];
    $data['versions'][] = $version;
    $data['body'] = $newBody;
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    log_moderation_event($actorId, 'edit', $id, 'versioned');
    return $data;
}

// Moderate (approve/reject)
function comment_moderate(string $id, string $actorId, string $decision, string $note = '') {
    $file = comments_storage_dir() . '/' . $id . '.json';
    if (!is_file($file)) {
        throw new RuntimeException('Comment not found.');
    }
    $data = json_decode(file_get_contents($file), true);
    if (!in_array($decision, ['approved', 'rejected'], true)) {
        throw new RuntimeException('Invalid decision.');
    }
    $data['status']      = $decision;
    $data['moderated_at'] = date('Y-m-d H:i:s');
    $data['moderator_id'] = $actorId;
    $data['moderation_note'] = $note;
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    log_moderation_event($actorId, $decision, $id, $note);
    return $data;
}

// List comments for a page (optionally filter by status)
function comments_list(string $subjectId, string $pageId, ?string $status = 'approved') {
    $dir = comments_storage_dir();
    $out = [];
    foreach (glob($dir . '/*.json') as $file) {
        $c = json_decode(file_get_contents($file), true);
        if ($c['subject_id'] === $subjectId && $c['page_id'] === $pageId) {
            if ($status === null || $c['status'] === $status) {
                $out[] = $c;
            }
        }
    }
    // sort by created_at asc
    usort($out, fn($a, $b) => strcmp($a['created_at'], $b['created_at']));
    return $out;
}
