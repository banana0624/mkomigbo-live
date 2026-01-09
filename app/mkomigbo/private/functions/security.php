<?php
// mkomigbo/private/functions/security.php
// Centralized security helpers: CSRF, safe output, rate limiting, file uploads, moderation/alerts, run IDs

// --------------------
// CSRF Protection
// --------------------
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_verify($token) {
    return hash_equals($_SESSION['csrf'] ?? '', $token ?? '');
}

// --------------------
// Safe Output
// --------------------
function e($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

// --------------------
// Rate Limiting (APCu)
// --------------------
function rate_limit($key, $max, $windowSeconds) {
    $now = time();
    $bucket = apcu_fetch($key);
    if (!$bucket || $bucket['reset'] <= $now) {
        $bucket = ['count' => 0, 'reset' => $now + $windowSeconds];
    }
    if ($bucket['count'] >= $max) {
        return false;
    }
    $bucket['count']++;
    apcu_store($key, $bucket, $windowSeconds);
    return true;
}

// --------------------
// File Upload Validation Helpers
// --------------------
function upload_allowed_mimes() {
    return [
        'image/png'           => 'png',
        'image/jpeg'          => 'jpg',
        'application/pdf'     => 'pdf',
        'audio/mpeg'          => 'mp3',
        'application/zip'     => 'zip', // optional
    ];
}
function upload_size_limits() {
    return [
        'image/png'       => 10 * 1024 * 1024,
        'image/jpeg'      => 10 * 1024 * 1024,
        'application/pdf' => 20 * 1024 * 1024,
        'audio/mpeg'      => 50 * 1024 * 1024,
        'application/zip' => 100 * 1024 * 1024,
    ];
}
function upload_paths() {
    return [
        'quarantine' => realpath(__DIR__ . '/../tmp') ?: (__DIR__ . '/../tmp'),
        'approved'   => realpath(__DIR__ . '/../assets/uploads') ?: (__DIR__ . '/../assets/uploads'),
    ];
}
function ensure_upload_dirs() {
    $p = upload_paths();
    if (!is_dir($p['quarantine'])) mkdir($p['quarantine'], 0700, true);
    if (!is_dir($p['approved']))   mkdir($p['approved'],   0750, true);
}
function detect_mime($tmpFile) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    return $finfo->file($tmpFile);
}
function generate_safe_filename($ext) {
    return sprintf('%s.%s', bin2hex(random_bytes(16)), $ext);
}
// Optional image re-encode to strip metadata
function reencode_image_if_needed($path, $mime) {
    if ($mime === 'image/jpeg') {
        $img = @imagecreatefromjpeg($path);
        if ($img) {
            @imagejpeg($img, $path, 90); // re-save JPEG
            imagedestroy($img);
        }
    } elseif ($mime === 'image/png') {
        $img = @imagecreatefrompng($path);
        if ($img) {
            @imagepng($img, $path, 6); // re-save PNG
            imagedestroy($img);
        }
    }
}
function safe_upload(array $file) {
    ensure_upload_dirs();
    $paths   = upload_paths();
    $allowed = upload_allowed_mimes();
    $limits  = upload_size_limits();

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload error.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid upload source.');
    }
    $mime = detect_mime($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Unsupported file type.');
    }
    $maxSize = $limits[$mime] ?? (10 * 1024 * 1024);
    if (($file['size'] ?? 0) > $maxSize) {
        throw new RuntimeException('File too large for type.');
    }

    $safeName = generate_safe_filename($allowed[$mime]);
    $qPath    = rtrim($paths['quarantine'], '/\\') . DIRECTORY_SEPARATOR . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $qPath)) {
        throw new RuntimeException('Failed to move to quarantine.');
    }

    // TODO: Integrate AV scan here (clamav/third-party). If fail → unlink & throw.
    reencode_image_if_needed($qPath, $mime);

    $aPath = rtrim($paths['approved'], '/\\') . DIRECTORY_SEPARATOR . $safeName;
    if (!rename($qPath, $aPath)) {
        @unlink($qPath);
        throw new RuntimeException('Failed to publish file.');
    }

    return [
        'filename' => $safeName,
        'mime'     => $mime,
        'size'     => filesize($aPath),
        'path'     => $aPath,
    ];
}

// --------------------
// Operational Safeguards (Moderation/Alerts/Run IDs)
// --------------------
function log_moderation_event($actorId, $action, $targetId, $details = '') {
    $logFile = __DIR__ . '/../logs/moderation.log';
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] Actor:$actorId Action:$action Target:$targetId Details:$details" . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND);
}
function generate_run_id() {
    return date('Ymd_His') . '_' . bin2hex(random_bytes(4));
}
function log_alert($message) {
    $logFile = __DIR__ . '/../logs/alerts.log';
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] WARNING: $message" . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND);
}
