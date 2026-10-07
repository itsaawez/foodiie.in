<?php
/** FOODIIE admin — serve a stored media file (storage/ is not web-accessible). */
require_once __DIR__ . '/includes/guard.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found.';
    exit;
}

$stmt = db()->prepare('SELECT * FROM media WHERE id = :id');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();

$path = $row ? storage_path('uploads/' . $row['filename']) : '';
$real = $path !== '' ? realpath($path) : false;
$base = realpath(storage_path('uploads'));

if (!$row || !$real || !$base || strpos($real, $base) !== 0 || !is_file($real)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found.';
    exit;
}

header('Content-Type: ' . ($row['mime'] !== '' ? $row['mime'] : 'application/octet-stream'));
header('Content-Length: ' . filesize($real));
header('Cache-Control: public, max-age=86400');
readfile($real);
exit;
