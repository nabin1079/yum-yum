<?php
/*
 * Loaded first by every page through config/db.php.
 * Sets up error handling, the session, security headers, CSRF protection
 * and a few small helpers shared by all pages.
 */

date_default_timezone_set(APP_TIMEZONE);

// ── Errors: log everything, show details only in development ─────────────
error_reporting(E_ALL);
ini_set('display_errors', APP_ENV === 'production' ? '0' : '1');
ini_set('log_errors', '1');
if (!is_dir(STORAGE_PATH . '/logs')) {
    @mkdir(STORAGE_PATH . '/logs', 0755, true);
}
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

set_exception_handler(function (Throwable $e) {
    error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $detail = APP_ENV === 'production' ? '' : $e->getMessage();
    render_error_page(500, 'Something went wrong', 'Sorry, we could not complete that request. Please try again in a moment.', $detail);
});

// ── Session ──────────────────────────────────────────────────────────────
function is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('yumyum_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_PATH,
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── Security headers ─────────────────────────────────────────────────────
if (!headers_sent() && PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

// ── Helpers ──────────────────────────────────────────────────────────────
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void {
    header('Location: ' . BASE_PATH . ltrim($path, '/'));
    exit;
}

function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function client_ip(): string {
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

/* One password rule for registration and reset. Returns an error or null. */
const PASSWORD_MIN_LENGTH = 8;
function password_problem(string $password): ?string {
    if ($password === '') return 'Password is required.';
    if (strlen($password) < PASSWORD_MIN_LENGTH) return 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    if (strlen($password) > 72) return 'Password must be 72 characters or fewer.';
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password must contain at least one letter and one number.';
    }
    return null;
}

// ── CSRF protection ──────────────────────────────────────────────────────
// Every POST must carry the session's token. Forms add it with csrf_field().
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '" />';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        http_response_code(403);
        $too_big = empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
        render_error_page(
            403,
            $too_big ? 'Upload too large' : 'Page expired',
            $too_big
                ? 'The file you sent is larger than the server allows. Please choose a smaller image (under 3 MB).'
                : 'Your session expired or the form was opened in another tab. Please go back, refresh the page and try again.'
        );
    }
}

// ── Simple error page used for 403, 404 and 500 ──────────────────────────
function render_error_page(int $code, string $title, string $message, string $detail = ''): void {
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: text/html; charset=UTF-8');
    }
    $home = BASE_PATH . 'index.php';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />'
       . '<title>' . e($title) . ' | Yum-Yum</title>'
       . '<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet" />'
       . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#fcfbf8;font-family:"Plus Jakarta Sans",sans-serif;color:#3d3522;padding:1.5rem}'
       . '.box{max-width:440px;text-align:center}.code{font-size:4rem;font-weight:800;color:#f4d125;margin:0}'
       . 'h1{font-size:1.5rem;margin:.5rem 0}p{color:#6b705c;line-height:1.7}a{display:inline-block;margin-top:1rem;background:#f4d125;color:#3d3522;padding:.8rem 1.6rem;border-radius:12px;font-weight:800;text-decoration:none}'
       . 'pre{text-align:left;background:#f3f0e6;padding:1rem;border-radius:8px;white-space:pre-wrap;font-size:.8rem}</style></head><body><div class="box">'
       . '<p class="code">' . $code . '</p><h1>' . e($title) . '</h1><p>' . e($message) . '</p>'
       . ($detail !== '' ? '<pre>' . e($detail) . '</pre>' : '')
       . '<a href="' . e($home) . '">Back to home</a></div></body></html>';
    exit;
}
