<?php
require_once 'config/db.php';

$file_id = (int) ($_GET['id'] ?? 0);
if ($file_id <= 0 || !table_exists($conn, 'activity_files')) {
    http_response_code(404);
    exit('File not found.');
}

$stmt = $conn->prepare(
    'SELECT original_name, file_path, mime_type, file_size FROM activity_files WHERE file_id = ? LIMIT 1'
);
$stmt->bind_param('i', $file_id);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$file) {
    http_response_code(404);
    exit('File not found.');
}

$full_path = __DIR__ . '/' . $file['file_path'];
if (!is_file($full_path)) {
    http_response_code(404);
    exit('File not found.');
}

header('Content-Type: ' . $file['mime_type']);
header('Content-Length: ' . (string) filesize($full_path));
header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
header('Cache-Control: private, max-age=3600');
readfile($full_path);
exit;
