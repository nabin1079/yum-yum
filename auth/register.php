<?php

require_once __DIR__ . '/../config/db.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to their dashboard if already logged in
if (!empty($_SESSION['user_id'])) {
    $redirect = $_SESSION['role'] === 'seller'
        ? BASE_PATH . 'seller/dashboard.php'
        : BASE_PATH . 'buyer/menu.php';
    header('Location: ' . $redirect);
    exit;
}

//  Initialise error and field arrays 
$errors = [];
$fields = [
    'first_name' => '',
    'last_name'  => '',
    'email'      => '',
    'role'       => 'buyer',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Sanitise inputs
    $fields['first_name'] = trim($_POST['first_name'] ?? '');
    $fields['last_name']  = trim($_POST['last_name']  ?? '');
    $fields['email']      = trim($_POST['email']      ?? '');
    $fields['role']       = trim($_POST['role']       ?? 'buyer');
    $password             = $_POST['password']         ?? '';
    $confirm              = $_POST['confirm_password'] ?? '';

    // 2. Validate
    if (empty($fields['first_name'])) {
        $errors['first_name'] = 'First name is required.';
    } elseif (strlen($fields['first_name']) > 100) {
        $errors['first_name'] = 'First name is too long.';
    }

    if (empty($fields['last_name'])) {
        $errors['last_name'] = 'Last name is required.';
    } elseif (strlen($fields['last_name']) > 100) {
        $errors['last_name'] = 'Last name is too long.';
    }

    if (empty($fields['email'])) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }

    if (empty($confirm)) {
        $errors['confirm_password'] = 'Please confirm your password.';
    } elseif ($password !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (!in_array($fields['role'], ['buyer', 'seller'])) {
        $errors['role'] = 'Please select a valid role.';
    }

    // 3. Check email is not already registered
    if (empty($errors['email'])) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$fields['email']]);
        if ($stmt->fetch()) {
            $errors['email'] = 'This email is already registered. Please log in.';
        }
    }

    // 4. If no errors — insert user
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('
            INSERT INTO users (first_name, last_name, email, password_hash, role)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $fields['first_name'],
            $fields['last_name'],
            $fields['email'],
            $hash,
            $fields['role'],
        ]);

        $new_id = $pdo->lastInsertId();

        // 5. Log them in immediately after registration
        $_SESSION['user_id']    = $new_id;
        $_SESSION['first_name'] = $fields['first_name'];
        $_SESSION['last_name']  = $fields['last_name'];
        $_SESSION['role']       = $fields['role'];

        // 6. Redirect to their dashboard
        $redirect = $fields['role'] === 'seller'
            ? BASE_PATH . 'seller/dashboard.php'
            : BASE_PATH . 'buyer/menu.php';

        header('Location: ' . $redirect);
        exit;
    }
}

// Helper: returns 'error' class if that field has an error
function field_class(string $field, array $errors): string {
    return isset($errors[$field]) ? 'auth-input error' : 'auth-input';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Create Account | Yum-Yum Tiffin Services</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet" />

    <link rel="stylesheet" href="<?= BASE_PATH ?>css/styles.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>css/login.css" />
    <style>
        body { padding-top: 0 !important; }

        /* Role selector cards */
        .role-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .role-card {
            position: relative;
            cursor: pointer;
        }

        .role-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .role-card__label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            border: 1.5px solid var(--border-light);
            border-radius: var(--radius-xl);
            background: var(--white);
            cursor: pointer;
            transition: border-color var(--transition-fast), background var(--transition-fast), box-shadow var(--transition-fast);
            text-align: center;
        }

        .role-card input[type="radio"]:checked + .role-card__label {
            border-color: var(--primary);
            background: rgba(244, 209, 37, 0.08);
            box-shadow: 0 0 0 3px rgba(244, 209, 37, 0.2);
        }

        .role-card__icon {
            font-size: 1.75rem;
            color: var(--sage);
            font-variation-settings: 'FILL' 0;
            transition: color var(--transition-fast);
        }

        .role-card input[type="radio"]:checked + .role-card__label .role-card__icon {
            color: var(--earth);
            font-variation-settings: 'FILL' 1;
        }

        .role-card__title {
            font-size: 0.9375rem;
            font-weight: 700;
            color: var(--earth);
        }

        .role-card__desc {
            font-size: 0.75rem;
            color: var(--sage);
            line-height: 1.4;
        }

        /* General error banner */
        .auth-banner {
            padding: 0.875rem 1rem;
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
        }

        .auth-banner--error {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #dc2626;
        }
    </style>
</head>
<body>

<div class="auth-page">
    <aside class="auth-visual" aria-hidden="true">
        <div class="auth-visual__bg" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuAdNGg3c-UnjVHb5oLDyxSLtOVeh0GTOb7KUH6H8UjV4_MIGW8gM4SgvfmmKYcgP-Ti8OqQEFOAjZt9Xfm0fgt84TWOqbJ-XMLaiRmcr3hk9QYUMvnl-2RMdpWO6nH2-1PorcbbK1fesEcLn19l4SJYfs_cteV_Bh7WNIsTKC2k3olvOceSAohH8qbJPM67uf9zKvs5cLfLdk31EPTB35CsmFoJ-9bZd6Ed-fwp1IVGEC03LDpy-l6uQpeVXfckN9cNN_RikiRU4tcc');"></div>
        <div class="auth-visual__overlay"></div>
        <div class="auth-visual__content">
            <a href="<?= BASE_PATH ?>index.php" class="auth-visual__logo">
                <div class="auth-visual__logo-icon">
                    <span class="material-symbols-outlined" style="font-size:1.75rem;font-variation-settings:'FILL' 1;">restaurant_menu</span>
                </div>
                <span class="auth-visual__logo-text">Yum-Yum</span>
            </a>
            <div class="auth-visual__hero">
                <h1 class="auth-visual__heading">
                    Join thousands of students eating <em>well every day.</em>
                </h1>
                <p class="auth-visual__subtext">
                    Create your account in under a minute and start ordering home-style tiffins — or list your own meals as a seller.
                </p>
            </div>
            <div class="auth-visual__social-proof">
                <div class="auth-visual__avatars">
                    <div class="auth-visual__avatar"><img src="https://lh3.googleusercontent.com/aida-public/AB6AXuCfwnuECSXGi7Hxt1_ffTyNlNXd4RrbThVGpNCa3FdtsTAbleU_KZfeLxCp9sHSqdDun8LBJUQiQXyB-TINML5yiUoBaDND1OgkcWWEq2_oOSQRvwNxNLfqdywGEcEXForQRYmQN1ZiQ5ReDaaqSnHtSe2RDSeHK5pNfOEVFvWh_PnvQcIr-Bz__m6_hSZ6GIys0no3wtIkKZK94uvuDFnpt-o6-unsD10tU1e7iilIyc-c2tu1u0x3U-dhWdvtQpVBMsTH8bGoAK7I" alt="" /></div>
                    <div class="auth-visual__avatar"><img src="https://lh3.googleusercontent.com/aida-public/AB6AXuA-3RZlmPUdzfXizaUTk4NXE__aYUsLPl4pPtcy-TxnHsbJAfu4SoFuKq3tZqI6zIJtD1vwJGBe32_RFGVbzWZA-U3K6PHOvWHZf16YA-dQWAFHs3x5RNxH1LF2N9E2_YPDoOgOVRhbX1Vr8yGQFnFqXw522fu55lB8fKt2UfHpYBkv4IlxPIqZG2V4dVf_2S4oD3YSOZcSUJYBCUPwkw8UgMkWBVez6BzndQg0_kWR81-6OvA2RlPz_x6eomNnn2rPciv-pPhRhgaL" alt="" /></div>
                    <div class="auth-visual__avatar"><img src="https://lh3.googleusercontent.com/aida-public/AB6AXuB4AqRVKn3AEcj5xQo5oaFgKpvGtjBAzMhMQGlwVCMTlcIfTsLU95sX-gVVXBsBid9sthT-Zbs8PalEU1VMCcZZEI9GrEVHl3CmiEd68I0FNQvOXkfmqC2mshbmQIB8bxS8KVALPsCwQ-pS8r6_TG_TEsm7G13sHd5zi-vvNw8i4b2bpe6GHG9J03Ayzx5PaD-8agdCyKbAaOroJIWqWbUBoqBxyHmdpX4unj5ZwC86ObQv_dqdsTKQfr-wYC9IDiMaFqAB44CcBYQH" alt="" /></div>
                </div>
                <span class="auth-visual__proof-text">2,000+ students joined</span>
            </div>
        </div>
    </aside>

    <main class="auth-form-panel">

        <!-- Mobile logo -->
        <a href="<?= BASE_PATH ?>index.php" class="auth-mobile-logo">
            <div class="auth-mobile-logo__icon">
                <span class="material-symbols-outlined" style="font-size:1.25rem;font-variation-settings:'FILL' 1;">restaurant_menu</span>
            </div>
            <span class="auth-mobile-logo__text">Yum-Yum</span>
        </a>

        <div class="auth-form-inner">

            <!-- Tab bar -->
            <div class="auth-tabs">
                <a href="<?= BASE_PATH ?>auth/login.php" class="auth-tab" style="text-decoration:none;">Login</a>
                <span class="auth-tab active">Sign Up</span>
            </div>

            <h2 class="auth-heading">Create Account</h2>
            <p class="auth-subheading">Join thousands of students enjoying home-cooked tiffins.</p>

            <!-- General error banner (shown if any server errors) -->
            <?php if (!empty($errors)): ?>
                <div class="auth-banner auth-banner--error">
                    Please fix the errors below and try again.
                </div>
            <?php endif; ?>

            <!-- Registration Form -->
            <form class="auth-form" method="POST" action="" novalidate>

                <!-- First + Last Name -->
                <div class="auth-form__row">
                    <div class="auth-form__group">
                        <label class="auth-label" for="first_name">First Name</label>
                        <div class="auth-input-wrapper">
                            <span class="material-symbols-outlined">person</span>
                            <input
                                class="<?= field_class('first_name', $errors) ?>"
                                type="text"
                                id="first_name"
                                name="first_name"
                                value="<?= htmlspecialchars($fields['first_name']) ?>"
                                placeholder="Ananya"
                                autocomplete="given-name"
                            />
                        </div>
                        <?php if (isset($errors['first_name'])): ?>
                            <span class="auth-field-error visible"><?= htmlspecialchars($errors['first_name']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="auth-form__group">
                        <label class="auth-label" for="last_name">Last Name</label>
                        <div class="auth-input-wrapper">
                            <span class="material-symbols-outlined">person</span>
                            <input
                                class="<?= field_class('last_name', $errors) ?>"
                                type="text"
                                id="last_name"
                                name="last_name"
                                value="<?= htmlspecialchars($fields['last_name']) ?>"
                                placeholder="Sharma"
                                autocomplete="family-name"
                            />
                        </div>
                        <?php if (isset($errors['last_name'])): ?>
                            <span class="auth-field-error visible"><?= htmlspecialchars($errors['last_name']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Email -->
                <div class="auth-form__group">
                    <label class="auth-label" for="email">Email Address</label>
                    <div class="auth-input-wrapper">
                        <span class="material-symbols-outlined">mail</span>
                        <input
                            class="<?= field_class('email', $errors) ?>"
                            type="email"
                            id="email"
                            name="email"
                            value="<?= htmlspecialchars($fields['email']) ?>"
                            placeholder="johnsmith@gmail.com"
                            autocomplete="email"
                        />
                    </div>
                    <?php if (isset($errors['email'])): ?>
                        <span class="auth-field-error visible"><?= htmlspecialchars($errors['email']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Password -->
                <div class="auth-form__group">
                    <label class="auth-label" for="password">Password</label>
                    <div class="auth-input-wrapper">
                        <span class="material-symbols-outlined">lock</span>
                        <input
                            class="<?= field_class('password', $errors) ?> auth-input--has-toggle"
                            type="password"
                            id="password"
                            name="password"
                            placeholder="At least 8 characters"
                            autocomplete="new-password"
                        />
                        <button type="button" class="auth-pw-toggle" data-target="password" aria-label="Toggle password">
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                    </div>
                    <?php if (isset($errors['password'])): ?>
                        <span class="auth-field-error visible"><?= htmlspecialchars($errors['password']) ?></span>
                    <?php endif; ?>
                    <!-- Live strength meter (JS) -->
                    <div class="auth-pw-strength" id="pw-strength-bar" style="display:none;">
                        <div class="auth-pw-strength__bar">
                            <div class="auth-pw-strength__fill" id="pw-strength-fill"></div>
                        </div>
                        <span class="auth-pw-strength__label" id="pw-strength-label"></span>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="auth-form__group">
                    <label class="auth-label" for="confirm_password">Confirm Password</label>
                    <div class="auth-input-wrapper">
                        <span class="material-symbols-outlined">lock</span>
                        <input
                            class="<?= field_class('confirm_password', $errors) ?> auth-input--has-toggle"
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Re-enter your password"
                            autocomplete="new-password"
                        />
                        <button type="button" class="auth-pw-toggle" data-target="confirm_password" aria-label="Toggle confirm password">
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                    </div>
                    <?php if (isset($errors['confirm_password'])): ?>
                        <span class="auth-field-error visible"><?= htmlspecialchars($errors['confirm_password']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Role Selection -->
                <div class="auth-form__group">
                    <label class="auth-label">I am a</label>
                    <div class="role-selector">
                        <label class="role-card">
                            <input
                                type="radio"
                                name="role"
                                value="buyer"
                                <?= $fields['role'] === 'buyer' ? 'checked' : '' ?>
                            />
                            <span class="role-card__label">
                                <span class="material-symbols-outlined role-card__icon">shopping_bag</span>
                                <span class="role-card__title">Buyer</span>
                                <span class="role-card__desc">I want to order tiffins</span>
                            </span>
                        </label>
                        <label class="role-card">
                            <input
                                type="radio"
                                name="role"
                                value="seller"
                                <?= $fields['role'] === 'seller' ? 'checked' : '' ?>
                            />
                            <span class="role-card__label">
                                <span class="material-symbols-outlined role-card__icon">storefront</span>
                                <span class="role-card__title">Seller</span>
                                <span class="role-card__desc">I want to sell my meals</span>
                            </span>
                        </label>
                    </div>
                    <?php if (isset($errors['role'])): ?>
                        <span class="auth-field-error visible"><?= htmlspecialchars($errors['role']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Submit -->
                <button class="auth-submit" type="submit">
                    <span>Create My Account</span>
                    <span class="material-symbols-outlined">arrow_forward</span>
                </button>

            </form>

            <!-- Switch to login -->
            <p class="auth-switch">
                Already have an account?
                <a href="<?= BASE_PATH ?>auth/login.php">Sign in instead</a>
            </p>

        </div><!-- /auth-form-inner -->

        <footer class="auth-footer">
            <a href="#">Help Center</a>
            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Service</a>
        </footer>

    </main>

</div><!-- /auth-page -->

<script>
(function () {
    // Password visibility toggles
    document.querySelectorAll('.auth-pw-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.target);
            const icon  = btn.querySelector('.material-symbols-outlined');
            input.type  = input.type === 'password' ? 'text' : 'password';
            icon.textContent = input.type === 'password' ? 'visibility' : 'visibility_off';
        });
    });

    // Password strength meter
    const pwInput       = document.getElementById('password');
    const strengthBar   = document.getElementById('pw-strength-bar');
    const strengthFill  = document.getElementById('pw-strength-fill');
    const strengthLabel = document.getElementById('pw-strength-label');

    const levels = [
        { max: 1,  label: 'Too short', color: '#ef4444', pct: '15%'  },
        { max: 2,  label: 'Weak',      color: '#f97316', pct: '35%'  },
        { max: 3,  label: 'Fair',      color: '#eab308', pct: '60%'  },
        { max: 4,  label: 'Good',      color: '#84cc16', pct: '80%'  },
        { max: 99, label: 'Strong',    color: '#22c55e', pct: '100%' },
    ];

    function scorePassword(pw) {
        let s = 0;
        if (pw.length >= 8)          s++;
        if (pw.length >= 12)         s++;
        if (/[A-Z]/.test(pw))        s++;
        if (/[0-9]/.test(pw))        s++;
        if (/[^A-Za-z0-9]/.test(pw)) s++;
        return s;
    }

    pwInput.addEventListener('input', () => {
        const pw = pwInput.value;
        if (!pw) { strengthBar.style.display = 'none'; return; }
        strengthBar.style.display = 'block';
        const score = scorePassword(pw);
        const level = levels.find(l => score < l.max) || levels[4];
        strengthFill.style.width           = level.pct;
        strengthFill.style.backgroundColor = level.color;
        strengthLabel.textContent          = level.label;
        strengthLabel.style.color          = level.color;
    });
})();
</script>

</body>
</html>