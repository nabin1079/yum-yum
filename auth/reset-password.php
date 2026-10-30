<?php
require_once __DIR__ . '/../config/db.php';

$error = '';
$message = '';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$uid = (int)($_GET['uid'] ?? $_POST['uid'] ?? 0);

if (!$token || !$uid) {
    die('Invalid reset link.');
}

$stmt = $pdo->prepare("
    SELECT id, token_hash, expires_at, used_at
    FROM password_resets
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$uid]);
$reset = $stmt->fetch();

if (!$reset) {
    die('Reset request not found.');
}

if ($reset['used_at'] !== null) {
    die('This reset link has already been used.');
}

if (strtotime($reset['expires_at']) < time()) {
    die('This reset link has expired.');
}

if (!password_verify($token, $reset['token_hash'])) {
    die('Invalid reset token.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->beginTransaction();
        try {
            $upUser = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $upUser->execute([$newHash, $uid]);
            $upReset = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
            $upReset->execute([$reset['id']]);
            $pdo->commit();
            $message = 'Password updated successfully. You can now log in.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Something went wrong. Please try again.';
        }
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
                <p class="auth-visual__subtext">Make it at least 6 characters and something only you would know.</p>
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
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>" />
                    <input type="hidden" name="uid"   value="<?= $uid ?>" />

                    <div class="auth-form__group">
                        <label class="auth-label" for="password">New Password</label>
                        <div class="auth-input-wrapper">
                            <span class="material-symbols-outlined">lock</span>
                            <input
                                class="auth-input auth-input--has-toggle"
                                type="password"
                                id="password"
                                name="password"
                                placeholder="At least 6 characters"
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
            <a href="#">Help Center</a>
            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Service</a>
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