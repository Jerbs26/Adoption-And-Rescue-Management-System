<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';
session_start_once();

$rememberedEmail = '';
if (isset($_COOKIE['remember_token'])) {
    // Delete the auth token from DB
    $token_hash = hash('sha256', $_COOKIE['remember_token']);
    try {
        // Get the user email before deleting
        $stmt = db()->prepare(
            "SELECT u.email FROM remember_tokens rt
            JOIN users u ON u.id = rt.user_id
            WHERE rt.token_hash = ? LIMIT 1"
        );
        $stmt->execute([$token_hash]);
        $row = $stmt->fetch();
        if ($row) {
            $rememberedEmail = $row['email'];
        }
        db()->prepare("DELETE FROM remember_tokens WHERE token_hash = ?")
            ->execute([$token_hash]);
    } catch (PDOException $e) {
        // ignore
    }

    // Clear the auth token cookie
    setcookie('remember_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => isset($_SERVER['HTTPS']),
    ]);
}

if ($rememberedEmail) {
    setcookie('remembered_email', $rememberedEmail, [
        'expires'  => time() + (30 * 24 * 60 * 60), // 30 days
        'path'     => '/',
        'httponly' => false, // readable by PHP on next request
        'samesite' => 'Lax',
        'secure'   => isset($_SERVER['HTTPS']),
    ]);
}

logout_user();
redirect(BASE_URL . '/index.php');