<?php

require_once __DIR__ . '/../config/db.php';

// Logout is a POST with the CSRF token (checked in bootstrap), so another
// site cannot log users out by linking here.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

// Clear all session variables
$_SESSION = [];

// Expire the session cookie in the browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $params['path'],
        'domain'   => $params['domain'],
        'secure'   => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

// Destroy the server-side session
session_destroy();

header('Location: ' . BASE_PATH . 'auth/login.php?logged_out=1');
exit;
