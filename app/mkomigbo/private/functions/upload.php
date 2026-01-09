<?php
// private/functions/upload.php
function safe_upload($file) {
    $quarantineDir = __DIR__ . '/../tmp';
    $approvedDir   = __DIR__ . '/../assets/uploads';

    if (!is_dir($quarantineDir)) mkdir($quarantineDir, 0700, true);
    if (!is_dir($approvedDir))   mkdir($approvedDir,   0750, true);

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload error.');
    }

    // Size limit (example: 50 MB)
    if (($file['size'] ?? 0) > 50 * 1024 * 1024) {
        throw new RuntimeException('File too large.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);

    $allowed = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'application/pdf' => 'pdf',
        'audio/mpeg' => 'mp3',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Unsupported file type.');
    }

    // Generate safe server-side name
    $safeName = sprintf('%s.%s', bin2hex(random_bytes(16)), $allowed[$mime]);
    $quarantinePath = $quarantineDir . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $quarantinePath)) {
        throw new RuntimeException('Failed to move to quarantine.');
    }

    // TODO: Virus/malware scan here (external tool or service)
    // TODO: Re-encode images to strip metadata if image/*

    // Approve (publish)
    $approvedPath = $approvedDir . '/' . $safeName;
    if (!rename($quarantinePath, $approvedPath)) {
        throw new RuntimeException('Failed to publish file.');
    }

    // Return metadata (store in DB/registry)
    return [
        'filename' => $safeName,
        'mime'     => $mime,
        'size'     => filesize($approvedPath),
        'path'     => $approvedPath
    ];
}
