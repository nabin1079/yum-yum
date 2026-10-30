<?php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirects to login page if user is not logged in.
// Call this on any page that needs authentication.
function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . get_login_path());
        exit;
    }
}

// Redirects if user is not logged in OR is the wrong role.
// $role is either 'buyer' or 'seller'.
function require_role(string $role) {
    require_login();
    if ($_SESSION['role'] !== $role) {
        // Logged in but wrong role — send them to their own dashboard
        if ($_SESSION['role'] === 'seller') {
            header('Location: ' . BASE_PATH . 'seller/dashboard.php');
        } else {
            header('Location: ' . BASE_PATH . 'buyer/menu.php');
        }
        exit;
    }
}

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    return [
        'id'         => $_SESSION['user_id'],
        'first_name' => $_SESSION['first_name'],
        'last_name'  => $_SESSION['last_name'],
        'role'       => $_SESSION['role'],
    ];
}

// Works out the correct relative path to login page regardless of which subfolder the current file is in.
function get_login_path(): string {
    return BASE_PATH . 'auth/login.php';
}