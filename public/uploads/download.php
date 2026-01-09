<?php
// public/uploads/download.php
// Validate auth/authorization as needed
$filename = $_GET['f'] ?? '';
if (!preg_match('/^[a-f0-9]{32}\.(png|jpg|pdf|mp3)$/', $filename)) {
    http_response_code(400);
    exit('Invalid file.');
}
$path = __DIR__ . '/../private/assets/uploads/' . $filename;
if (!is_file($path)) {
    http_response_code(404);
    exit('Not found.');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $filename . '"');
readfile($path);
