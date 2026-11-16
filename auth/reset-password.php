<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-guard.php';
require_once __DIR__ . '/../includes/mailer.php';

$error   = '';
$message = '';
$invalid = '';

$selector = (string)($_GET['selector'] ?? $_POST['selector'] ?? '');
$token    = (string)($_GET['token']    ?? $_POST['token']    ?? '');

// Look up the request by its selector, then compare the secret token
// in constant time. Expiry is checked by the database clock.
$reset = null;
if (preg_match('/^[a-f0-9]{16}$/', $selector) && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $stmt = $pdo->prepare('
        SELECT pr.id, pr.user_id, pr.token_hash, pr.used_at,
               pr.expires_at <= NOW() AS expired,
               u.email, u.first_name
        FROM password_resets pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.selector = ?
        LIMIT 1
    ');
    $stmt->execute([$selector]);
    $reset = $stmt->fetch() ?: null;
}

if (!$reset || !hash_equals($reset['token_hash'], hash('sha256', $token))) {
    $invalid = 'This reset link is not valid. Please request a new one.';
} elseif ($reset['used_at'] !== null) {
    $invalid = 'This reset link has already been used. Please request a new one if you still need it.';
} elseif ((int)$reset['expired'] === 1) {
    $invalid = 'This reset link has expired. Reset links work for 1 hour.';
}

if (!$invalid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password        = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($problem = password_problem($password)) {
        $error = $problem;
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);
            $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')
                ->execute([$reset['id']]);
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND id <> ?')
                ->execute([$reset['user_id'], $reset['id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        clear_attempts($pdo, 'login', $reset['email']);
        send_mail(
            $reset['email'],
            'Your Yum-Yum password was changed',
            "Hi {$reset['first_name']},\n\nThe password for your Yum-Yum account was just changed.\n\n"
            . "If this was you, there is nothing else to do.\n"
            . "If it was not you, reset your password straight away at " . APP_URL . "/auth/forgot-password.php and contact us.\n\nYum-Yum"
        );
        $message = 'Password updated successfully. You can now log in with your new password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reset Password | Yum-Yum</title>
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
                <h1 class="auth-visual__heading">Set a new <em>strong password.</em></h1>
                <p class="auth-visual__subtext">Use at least 8 characters with letters and a number.</p>
            </div>
            <div></div>
        </div>
    </aside>

    <main class="auth-form-panel">

        <a href="<?= BASE_PATH ?>index.php" class="auth-mobile-logo">
            <div class="auth-mobile-logo__icon">
                <span class="material-symbols-outlined" style="font-size:1.25rem;font-variation-settings:'FILL' 1;">restaurant_menu</span>
            </div>
            <span class="auth-mobile-logo__text">Yum-Yum</span>
        </a>

        <div class="auth-form-inner">

            <?php if ($message): ?>
                <div style="text-align:center;padding:1rem 0;">
                    <div style="width:5rem;height:5rem;background:rgba(34,197,94,0.1);border-radius:9999px;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;">
                        <span class="material-symbols-outlined" style="font-size:2.5rem;color:#16a34a;font-variation-settings:'FILL' 1;">lock_reset</span>
                    </div>
                    <h2 class="auth-heading" style="text-align:center;">Password Updated!</h2>
                    <p style="color:var(--sage);font-size:0.9375rem;line-height:1.7;margin-bottom:2rem;">
                        <?= htmlspecialchars($message) ?>
                    </p>
                    <a href="<?= BASE_PATH ?>auth/login.php"
                       style="display:inline-flex;align-items:center;gap:0.5rem;background:var(--primary);color:var(--earth);padding:0.875rem 2rem;border-radius:var(--radius-xl);font-weight:800;text-decoration:none;">
                        <span class="material-symbols-outlined">login</span>
                        Go to Login
                    </a>
                </div>

            <?php elseif ($invalid): ?>
                <div style="text-align:center;padding:1rem 0;">
                    <div style="width:5rem;height:5rem;background:rgba(239,68,68,0.08);border-radius:9999px;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;">
                        <span class="material-symbols-outlined" style="font-size:2.5rem;color:#dc2626;">link_off</span>
                    </div>
                    <h2 class="auth-heading" style="text-align:center;">Link not valid</h2>
                    <p style="color:var(--sage);font-size:0.9375rem;line-height:1.7;margin-bottom:2rem;"><?= e($invalid) ?></p>
                    <a href="<?= BASE_PATH ?>auth/forgot-password.php"
                       style="display:inline-flex;align-items:center;gap:0.5rem;background:var(--primary);color:var(--earth);padding:0.875rem 2rem;border-radius:var(--radius-xl);font-weight:800;text-decoration:none;">
                        <span class="material-symbols-outlined">mail</span>
                        Request a new link
                    </a>
                </div>

            <?php else: ?>

                <h2 class="auth-heading">Reset Password</h2>
                <p class="auth-subheading">Enter and confirm your new password below.</p>

                <?php if ($error): ?>
                    <div style="display:flex;align-items:center;gap:0.5rem;padding:0.875rem 1rem;border-radius:var(--radius-lg);background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);color:#dc2626;font-size:0.875rem;font-weight:600;margin-bottom:1.25rem;">
                        <span class="material-symbols-outlined" style="font-size:1.1rem;font-variation-settings:'FILL' 1;">error</span>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form class="auth-form" method="POST" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="selector" value="<?= e($selector) ?>" />
                    <input type="hidden" name="token"    value="<?= e($token) ?>" />

                    <div class="auth-form__group">
                        <label class="auth-label" for="password">New Password</label>
                        <div class="auth-input-wrapper">
                            <span class="material-symbols-outlined">lock</span>
                            <input
                                class="auth-input auth-input--has-toggle"
                                type="password"
                                id="password"
                                name="password"
                                placeholder="8+ characters, letters and a number"
                                autocomplete="new-password"
                                required
                            />
                            <button type="button" class="auth-pw-toggle" data-target="password" aria-label="Toggle password">
                                <span class="material-symbols-outlined">visibility</span>
                            </button>
                        </div>
                    </div>

                    <div class="auth-form__group">
                        <label class="auth-label" for="confirm_password">Confirm New Password</label>
                        <div class="auth-input-wrapper">
                            <span class="material-symbols-outlined">lock</span>
                            <input
                                class="auth-input auth-input--has-toggle"
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Re-enter your new password"
                                autocomplete="new-password"
                                required
                            />
                            <button type="button" class="auth-pw-toggle" data-target="confirm_password" aria-label="Toggle confirm">
                                <span class="material-symbols-outlined">visibility</span>
                            </button>
                        </div>
                    </div>

                    <button class="auth-submit" type="submit">
                        <span>Reset Password</span>
                        <span class="material-symbols-outlined">lock_reset</span>
                    </button>

                </form>

                <p class="auth-switch" style="margin-top:1.5rem;">
                    Remembered your password?
                    <a href="<?= BASE_PATH ?>auth/login.php">Sign in instead</a>
                </p>

            <?php endif; ?>

        </div>

        <footer class="auth-footer">
            <a href="<?= BASE_PATH ?>contact.php">Help Center</a>
            <a href="<?= BASE_PATH ?>privacy.php">Privacy Policy</a>
            <a href="<?= BASE_PATH ?>terms.php">Terms of Service</a>
        </footer>

    </main>
</div>

<script>
(function () {
    document.querySelectorAll('.auth-pw-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const input      = document.getElementById(btn.dataset.target);
            const icon       = btn.querySelector('.material-symbols-outlined');
            input.type       = input.type === 'password' ? 'text' : 'password';
            icon.textContent = input.type === 'password' ? 'visibility' : 'visibility_off';
        });
    });
})();
</script>

</body>
</html>