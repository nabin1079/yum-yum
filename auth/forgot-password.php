<?php
require_once __DIR__ . '/../config/db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'Please enter your email address.';
    } else {
        $stmt = $pdo->prepare("SELECT id, first_name, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = password_hash($rawToken, PASSWORD_DEFAULT);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $del = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL");
            $del->execute([$user['id']]);

            $ins = $pdo->prepare("
                INSERT INTO password_resets (user_id, token_hash, expires_at)
                VALUES (?, ?, ?)
            ");
            $ins->execute([$user['id'], $tokenHash, $expiresAt]);

            $resetLink = BASE_PATH . "auth/reset-password.php?token=" . urlencode($rawToken) . "&uid=" . $user['id'];

            $message = "reset_link:" . $resetLink;
        } else {
            $message = 'If the email exists in our system, a password reset link has been generated.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Forgot Password | Yum-Yum</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>css/styles.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>css/login.css" />
    <style>body { padding-top: 0 !important; }</style>
</head>
<body>
<div class="auth-page">

    <!-- Left visual panel -->
    <aside class="auth-visual" aria-hidden="true">
        <div class="auth-visual__bg" style="background-image:url('https://lh3.googleusercontent.com/aida-public/AB6AXuAdNGg3c-UnjVHb5oLDyxSLtOVeh0GTOb7KUH6H8UjV4_MIGW8gM4SgvfmmKYcgP-Ti8OqQEFOAjZt9Xfm0fgt84TWOqbJ-XMLaiRmcr3hk9QYUMvnl-2RMdpWO6nH2-1PorcbbK1fesEcLn19l4SJYfs_cteV_Bh7WNIsTKC2k3olvOceSAohH8qbJPM67uf9zKvs5cLfLdk31EPTB35CsmFoJ-9bZd6Ed-fwp1IVGEC03LDpy-l6uQpeVXfckN9cNN_RikiRU4tcc');"></div>
        <div class="auth-visual__overlay"></div>
        <div class="auth-visual__content">
            <a href="<?= BASE_PATH ?>index.php" class="auth-visual__logo">
                <div class="auth-visual__logo-icon">
                    <span class="material-symbols-outlined" style="font-size:1.75rem;font-variation-settings:'FILL' 1;">restaurant_menu</span>
                </div>
                <span class="auth-visual__logo-text">Yum-Yum</span>
            </a>
            <div class="auth-visual__hero">
                <h1 class="auth-visual__heading">Forgot your password? <em>No worries.</em></h1>
                <p class="auth-visual__subtext">Enter your email and we'll send you a secure link to get back in.</p>
            </div>
            <div></div>
        </div>
    </aside>

    <!-- Right form panel -->
    <main class="auth-form-panel">

        <a href="<?= BASE_PATH ?>index.php" class="auth-mobile-logo">
            <div class="auth-mobile-logo__icon">
                <span class="material-symbols-outlined" style="font-size:1.25rem;font-variation-settings:'FILL' 1;">restaurant_menu</span>
            </div>
            <span class="auth-mobile-logo__text">Yum-Yum</span>
        </a>

        <div class="auth-form-inner">

            <h2 class="auth-heading">Forgot Password</h2>
            <p class="auth-subheading">Enter your registered email and we'll generate a reset link.</p>

            <?php if ($error): ?>
                <div style="display:flex;align-items:center;gap:0.5rem;padding:0.875rem 1rem;border-radius:var(--radius-lg);background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);color:#dc2626;font-size:0.875rem;font-weight:600;margin-bottom:1.25rem;">
                    <span class="material-symbols-outlined" style="font-size:1.1rem;font-variation-settings:'FILL' 1;">error</span>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($message): ?>
                <?php if (str_starts_with($message, 'reset_link:')): ?>
                    <?php $link = substr($message, strlen('reset_link:')); ?>
                    <!-- Reset link generated -->
                    <div style="background:rgba(244,209,37,0.1);border:1.5px dashed var(--primary);border-radius:var(--radius-xl);padding:1.25rem;margin-bottom:1.5rem;">
                        <p style="font-size:0.75rem;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:var(--earth);margin-bottom:0.5rem;">
                            ⚠ Dev Mode — Click the link below to reset your password
                        </p>
                        <a href="<?= htmlspecialchars($link) ?>"
                           style="font-size:0.875rem;font-weight:700;color:var(--earth);word-break:break-all;text-decoration:underline;">
                            <?= htmlspecialchars($link) ?>
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Safe fallback message (email not found) -->
                    <div style="display:flex;align-items:center;gap:0.5rem;padding:0.875rem 1rem;border-radius:var(--radius-lg);background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.25);color:#15803d;font-size:0.875rem;font-weight:600;margin-bottom:1.25rem;">
                        <span class="material-symbols-outlined" style="font-size:1.1rem;font-variation-settings:'FILL' 1;">check_circle</span>
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <form class="auth-form" method="POST" action="">

                <div class="auth-form__group">
                    <label class="auth-label" for="email">Email Address</label>
                    <div class="auth-input-wrapper">
                        <span class="material-symbols-outlined">mail</span>
                        <input
                            class="auth-input"
                            type="email"
                            id="email"
                            name="email"
                            placeholder="johnsmith@gmail.com"
                            autocomplete="email"
                            required
                        />
                    </div>
                </div>

                <button class="auth-submit" type="submit">
                    <span>Send Reset Link</span>
                    <span class="material-symbols-outlined">send</span>
                </button>

            </form>

            <p class="auth-switch" style="margin-top:1.5rem;">
                Remember your password?
                <a href="<?= BASE_PATH ?>auth/login.php">Sign in instead</a>
            </p>

        </div>

        <footer class="auth-footer">
            <a href="#">Help Center</a>
            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Service</a>
        </footer>

    </main>
</div>
</body>
</html>