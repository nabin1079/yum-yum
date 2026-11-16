<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-guard.php';
require_role('seller');

$page_title = 'My Seller Profile';
$active_nav = 'profile';
$extra_css  = ['seller.css'];

$seller_id = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT first_name, last_name, email, phone, kitchen_name, area, bio FROM users WHERE id = ?');
$stmt->execute([$seller_id]);
$account = $stmt->fetch();

$fields = [
    'first_name'   => $account['first_name'],
    'last_name'    => $account['last_name'],
    'kitchen_name' => (string)$account['kitchen_name'],
    'area'         => (string)$account['area'],
    'phone'        => (string)$account['phone'],
    'bio'          => (string)$account['bio'],
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $k => $_) {
        $fields[$k] = trim((string)($_POST[$k] ?? ''));
    }

    if ($fields['first_name'] === '' || mb_strlen($fields['first_name']) > 100) {
        $errors['first_name'] = 'First name is required (up to 100 characters).';
    }
    if ($fields['last_name'] === '' || mb_strlen($fields['last_name']) > 100) {
        $errors['last_name'] = 'Last name is required (up to 100 characters).';
    }
    if (mb_strlen($fields['kitchen_name']) > 120) {
        $errors['kitchen_name'] = 'Kitchen name must be under 120 characters.';
    }
    if (mb_strlen($fields['area']) > 120) {
        $errors['area'] = 'Area must be under 120 characters.';
    }
    if ($fields['phone'] !== '' && !preg_match('/^\+?[0-9 ()-]{8,20}$/', $fields['phone'])) {
        $errors['phone'] = 'Please enter a valid phone number, or leave it empty.';
    }
    if (mb_strlen($fields['bio']) > 1000) {
        $errors['bio'] = 'Bio must be under 1000 characters.';
    }

    if (!$errors) {
        $pdo->prepare('
            UPDATE users
            SET first_name = ?, last_name = ?, kitchen_name = ?, area = ?, phone = ?, bio = ?
            WHERE id = ?
        ')->execute([
            $fields['first_name'], $fields['last_name'],
            $fields['kitchen_name'] ?: null, $fields['area'] ?: null,
            $fields['phone'] ?: null, $fields['bio'] ?: null,
            $seller_id,
        ]);
        $_SESSION['first_name'] = $fields['first_name'];
        $_SESSION['last_name']  = $fields['last_name'];
        flash('success', 'Your profile has been saved.');
        redirect('seller/profile.php');
    }
}

$flash = take_flash();

function pf_class(string $field, array $errors): string {
    return isset($errors[$field]) ? 'seller-input error' : 'seller-input';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="seller-page" style="max-width:760px;">

    <div class="seller-page__header">
        <div>
            <h1 class="seller-page__title">My Seller Profile</h1>
            <p class="seller-page__subtitle">This is what buyers see on your seller page.</p>
        </div>
        <a href="<?= BASE_PATH ?>seller.php?id=<?= $seller_id ?>" class="seller-btn-secondary">
            <span class="material-symbols-outlined">visibility</span>
            View My Public Page
        </a>
    </div>

    <?php if ($flash): ?>
        <div class="seller-alert seller-alert--<?= e($flash['type']) ?>">
            <span class="material-symbols-outlined"><?= $flash['type'] === 'success' ? 'check_circle' : 'error' ?></span>
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="seller-alert seller-alert--error">
            <span class="material-symbols-outlined">error</span>
            Please fix the errors below before saving.
        </div>
    <?php endif; ?>

    <form method="POST" action="" novalidate style="display:flex;flex-direction:column;gap:1.5rem;">
        <?= csrf_field() ?>

        <div class="seller-form-card">
            <h3 class="seller-form-card__title">About You</h3>

            <div class="seller-form__row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;">
                <div class="seller-form__group">
                    <label class="seller-label" for="first_name">First Name <span class="req">*</span></label>
                    <input class="<?= pf_class('first_name', $errors) ?>" type="text" id="first_name" name="first_name" maxlength="100" value="<?= e($fields['first_name']) ?>" />
                    <?php if (isset($errors['first_name'])): ?><span class="seller-field-error"><?= e($errors['first_name']) ?></span><?php endif; ?>
                </div>
                <div class="seller-form__group">
                    <label class="seller-label" for="last_name">Last Name <span class="req">*</span></label>
                    <input class="<?= pf_class('last_name', $errors) ?>" type="text" id="last_name" name="last_name" maxlength="100" value="<?= e($fields['last_name']) ?>" />
                    <?php if (isset($errors['last_name'])): ?><span class="seller-field-error"><?= e($errors['last_name']) ?></span><?php endif; ?>
                </div>
            </div>

            <div class="seller-form__group">
                <label class="seller-label" for="kitchen_name">Kitchen Name</label>
                <input class="<?= pf_class('kitchen_name', $errors) ?>" type="text" id="kitchen_name" name="kitchen_name" maxlength="120"
                       value="<?= e($fields['kitchen_name']) ?>" placeholder="e.g. Dhruba's Home Kitchen" />
                <?php if (isset($errors['kitchen_name'])): ?><span class="seller-field-error"><?= e($errors['kitchen_name']) ?></span><?php endif; ?>
                <span class="seller-hint">Shown instead of your name on the menu and your seller page.</span>
            </div>

            <div class="seller-form__row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;">
                <div class="seller-form__group">
                    <label class="seller-label" for="area">Area You Deliver To</label>
                    <input class="<?= pf_class('area', $errors) ?>" type="text" id="area" name="area" maxlength="120"
                           value="<?= e($fields['area']) ?>" placeholder="e.g. Townsville campus and Douglas" />
                    <?php if (isset($errors['area'])): ?><span class="seller-field-error"><?= e($errors['area']) ?></span><?php endif; ?>
                </div>
                <div class="seller-form__group">
                    <label class="seller-label" for="phone">Phone (shown to buyers with active orders)</label>
                    <input class="<?= pf_class('phone', $errors) ?>" type="tel" id="phone" name="phone" maxlength="20"
                           value="<?= e($fields['phone']) ?>" placeholder="04xx xxx xxx" />
                    <?php if (isset($errors['phone'])): ?><span class="seller-field-error"><?= e($errors['phone']) ?></span><?php endif; ?>
                </div>
            </div>

            <div class="seller-form__group">
                <label class="seller-label" for="bio">About Your Cooking</label>
                <textarea class="<?= pf_class('bio', $errors) ?>" id="bio" name="bio" rows="5" maxlength="1000"
                          placeholder="Tell buyers who you are, what you cook and what makes it special."><?= e($fields['bio']) ?></textarea>
                <?php if (isset($errors['bio'])): ?><span class="seller-field-error"><?= e($errors['bio']) ?></span><?php endif; ?>
            </div>

            <p class="seller-hint">Email: <?= e($account['email']) ?> (not shown publicly)</p>
        </div>

        <div style="display:flex;gap:0.75rem;justify-content:flex-end;flex-wrap:wrap;">
            <a href="<?= BASE_PATH ?>seller/dashboard.php" class="seller-btn-secondary">Cancel</a>
            <button type="submit" class="seller-btn-primary">
                <span class="material-symbols-outlined">save</span>
                Save Profile
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
