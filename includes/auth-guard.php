<?php
/*
 * Authentication helpers. The session itself is started in bootstrap.php.
 */

// Redirects to the login page if the user is not logged in.
function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . get_login_path());
        exit;
    }
}

// Redirects if the user is not logged in OR has the wrong role.
// $role is either 'buyer' or 'seller'.
function require_role(string $role) {
    require_login();
    if ($_SESSION['role'] !== $role) {
        // Logged in but wrong role — send them to their own area
        header('Location: ' . home_path_for($_SESSION['role']));
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

function get_login_path(): string {
    return BASE_PATH . 'auth/login.php';
}

function home_path_for(string $role): string {
    return BASE_PATH . ($role === 'seller' ? 'seller/dashboard.php' : 'buyer/menu.php');
}

// Starts a fresh session for the user (prevents session fixation).
function log_user_in(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name']  = $user['last_name'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ── Rate limiting ────────────────────────────────────────────────────────
 * Each failed login and each reset request is recorded. Too many within
 * the window blocks further attempts for that email address or IP address.
 */
const RATE_LIMITS = [
    //          per email, per IP, window in minutes
    'login' => [5, 20, 15],
    'reset'   => [3, 10, 15],
    'contact' => [3, 10, 60],
];

function too_many_attempts(PDO $pdo, string $action, string $identifier): bool {
    [$per_id, $per_ip, $minutes] = RATE_LIMITS[$action];

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM auth_attempts
        WHERE action = ? AND identifier = ? AND created_at > NOW() - INTERVAL $minutes MINUTE
    ");
    $stmt->execute([$action, strtolower($identifier)]);
    $for_identifier = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM auth_attempts
        WHERE action = ? AND ip = ? AND created_at > NOW() - INTERVAL $minutes MINUTE
    ");
    $stmt->execute([$action, client_ip()]);
    $for_ip = (int)$stmt->fetchColumn();

    return $for_identifier >= $per_id || $for_ip >= $per_ip;
}

function record_attempt(PDO $pdo, string $action, string $identifier): void {
    $pdo->prepare('INSERT INTO auth_attempts (action, identifier, ip) VALUES (?, ?, ?)')
        ->execute([$action, strtolower($identifier), client_ip()]);
    // Keep the table small.
    if (random_int(1, 50) === 1) {
        $pdo->exec('DELETE FROM auth_attempts WHERE created_at < NOW() - INTERVAL 1 DAY');
    }
}

function clear_attempts(PDO $pdo, string $action, string $identifier): void {
    $pdo->prepare('DELETE FROM auth_attempts WHERE action = ? AND identifier = ?')
        ->execute([$action, strtolower($identifier)]);
}
