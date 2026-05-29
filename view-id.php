<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';
session_start_once();

// Must be logged in
require_login(BASE_URL . '/login.php');

$currentUser = current_user();
$role        = $currentUser['role'] ?? '';
$userId      = (int)($currentUser['id'] ?? 0);

// Sanitize filename 
$file = isset($_GET['file']) ? basename(trim($_GET['file'])) : '';

if ($file === '') {
    http_response_code(400);
    exit('Bad request: no file specified.');
}

// Allow only safe filename characters
if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $file)) {
    http_response_code(400);
    exit('Bad request: invalid filename.');
}

if ($role === 'admin') {

} elseif ($role === 'adopter') {
    // Verify the file belongs to this adopter
    $stmt = db()->prepare("SELECT id_document FROM adopter_profiles WHERE user_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row || $row['id_document'] !== $file) {
        http_response_code(403);
        exit('Access denied: this file does not belong to your account.');
    }
} else {
    http_response_code(403);
    exit('Access denied.');
}

// Build the upload directory path (Windows-safe) 
$projectRoot = realpath(__DIR__);
$uploadDir   = $projectRoot . DIRECTORY_SEPARATOR . 'public'  . DIRECTORY_SEPARATOR
                . 'uploads'    . DIRECTORY_SEPARATOR . 'ids'     . DIRECTORY_SEPARATOR;
$filePath    = $uploadDir . $file;

// Check file exists 
if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    exit('File not found.');
}

$realFile = realpath($filePath);
if ($realFile === false || strpos($realFile, $projectRoot) !== 0) {
    http_response_code(403);
    exit('Access denied.');
}

// Map extension → MIME type
$ext  = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'pdf'         => 'application/pdf',
    'jpg', 'jpeg' => 'image/jpeg',
    'png'         => 'image/png',
    'webp'        => 'image/webp',
    default       => 'application/octet-stream',
};

// Stream file to browser 
header('Content-Type: '        . $mime);
header('Content-Length: '      . filesize($realFile));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . addslashes(basename($realFile)) . '"');

readfile($realFile);
exit;