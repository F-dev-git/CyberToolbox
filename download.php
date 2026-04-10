<?php
require_once __DIR__ . '/auth.php';
require_auth();
require_once __DIR__ . '/etc/config.php';

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Method not allowed');
}

// Which directory: 'transferts' (default) or 'notices'
$dir = $_GET['dir'] ?? 'transferts';
$f = $_GET['f'] ?? '';
$f = basename($f);

if ($dir === 'notices') {
    $baseDir = defined('NOTICES_DIRECTORY') ? NOTICES_DIRECTORY : 'notices/';
} else {
    $baseDir = PRIVATE_DIRECTORY;
}

// Support qr codes directory
if ($dir === 'qr' || $dir === 'qr-codes-wifi') {
    $baseDir = defined('QR_DIRECTORY') ? QR_DIRECTORY : 'ressources/qr-codes-wifi/';
}

$base = realpath(__DIR__ . '/' . rtrim($baseDir, '/'));
$path = $base ? realpath($base . DIRECTORY_SEPARATOR . $f) : false;

if (!$path || !$base || strpos($path, $base) !== 0 || !is_file($path)) {
    http_response_code(404);
    exit('Fichier introuvable');
}

$mime = mime_content_type($path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($path) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
