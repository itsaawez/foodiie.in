<?php
/** FOODIIE admin — download an existing backup (strict filename validation, no traversal). */
require_once __DIR__ . '/includes/guard.php';

$f = $_GET['f'] ?? '';
if (!is_string($f) || !preg_match('/^backup-\d{8}-\d{6}\.zip$/', $f)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found.';
    exit;
}

$dir = realpath(storage_path('backups'));
$path = $dir ? realpath($dir . '/' . $f) : false;

if (!$path || strpos($path, $dir) !== 0 || !is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found.';
    exit;
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $f . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
