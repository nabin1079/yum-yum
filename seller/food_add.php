<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-guard.php';
require_once __DIR__ . '/../includes/upload.php';
require_role('seller');

$page_title = 'Add New Food';
$active_nav = 'food_add';
$extra_css  = ['seller.css'];

$errors = [];
$fields = [
    'name'        => '',
    'description' => '',
    'price'       => '',
    'category_id' => '',
];

//  Fetch categories for dropdown
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();

//  POST — process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Read and validate the text fields
    $fields['name']        = trim($_POST['name']        ?? '');
    $fields['description'] = trim($_POST['description'] ?? '');
    $fields['price']       = trim($_POST['price']       ?? '');
    $fields['category_id'] = (int)($_POST['category_id'] ?? 0);
    $errors = validate_food_fields($pdo, $fields);

    // 2. Check the image (saved only if everything else is valid)
    $image = check_food_image($_FILES['image'] ?? null);
    if (isset($image['error'])) {
        $errors['image'] = $image['error'];
    }

    // 3. Save
    if (empty($errors)) {
        $image_path = null;
        if ($image) {
            $image_path = store_food_image($image);
            if ($image_path === null) {
                $errors['image'] = 'Failed to save image. Please try again.';
            }
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('
                INSERT INTO food_items
                    (seller_id, category_id, name, description, price, image_path, is_available)
                VALUES
                    (?, ?, ?, ?, ?, ?, 1)
            ');
            $stmt->execute([
                $_SESSION['user_id'],
                $fields['category_id'],
                $fields['name'],
                $fields['description'],
                $fields['price'],
                $image_path,
            ]);
        } catch (Throwable $e) {
            delete_food_image($image_path);   // do not leave an orphan file
            throw $e;
        }

        flash('success', '"' . $fields['name'] . '" has been added to your menu.');
        redirect('seller/dashboard.php');
    }
}

//  Helper
function field_class(string $field, array $errors): string {
    return isset($errors[$field]) ? 'seller-input error' : 'seller-input';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="seller-page" style="max-width:760px;">

    <!-- Page header -->
    <div class="seller-page__header">
        <div>
            <h1 class="seller-page__title">Add New Food Item</h1>
            <p class="seller-page__subtitle">Fill in the details below to list a new meal on the menu.</p>
        </div>
        <a href="<?= BASE_PATH ?>seller/dashboard.php" class="seller-btn-secondary">
            <span class="material-symbols-outlined">arrow_back</span>
            Back to Dashboard
        </a>
    </div>

    <!-- Error banner -->
    <?php if (!empty($errors)): ?>
        <div class="seller-alert seller-alert--error">
            <span class="material-symbols-outlined">error</span>
            Please fix the errors below before submitting.
        </div>
    <?php endif; ?>

    <!-- Form -->
    <form
        method="POST"
        action=""
        enctype="multipart/form-data"
        novalidate
        style="display:flex;flex-direction:column;gap:1.5rem;"
    >
        <?= csrf_field() ?>

        <!-- Card: Basic Info -->
        <div class="seller-form-card">
            <h3 class="seller-form-card__title">Basic Information</h3>

            <!-- Name -->
            <div class="seller-form__group">
                <label class="seller-label" for="name">Food Name <span class="req">*</span></label>
                <input
                    class="<?= field_class('name', $errors) ?>"
                    type="text"
                    id="name"
                    name="name"
                    value="<?= htmlspecialchars($fields['name']) ?>"
                    placeholder="e.g. Grandma's Dal Tadka"
                    maxlength="200"
                />
                <?php if (isset($errors['name'])): ?>
                    <span class="seller-field-error"><?= htmlspecialchars($errors['name']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <div class="seller-form__group">
                <label class="seller-label" for="description">Description <span class="req">*</span></label>
                <textarea
                    class="<?= field_class('description', $errors) ?>"
                    id="description"
                    name="description"
                    rows="4"
                    placeholder="Describe your dish — ingredients, flavour, portion size…"
                ><?= htmlspecialchars($fields['description']) ?></textarea>
                <?php if (isset($errors['description'])): ?>
                    <span class="seller-field-error"><?= htmlspecialchars($errors['description']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Price + Category row -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">

                <!-- Price -->
                <div class="seller-form__group">
                    <label class="seller-label" for="price">Price ($) <span class="req">*</span></label>
                    <div class="seller-input-wrapper">
                        <span class="seller-input-prefix">$</span>
                        <input
                            class="<?= field_class('price', $errors) ?> seller-input--prefixed"
                            type="number"
                            id="price"
                            name="price"
                            value="<?= htmlspecialchars($fields['price']) ?>"
                            placeholder="0.00"
                            step="0.01"
                            min="0.01"
                        />
                    </div>
                    <?php if (isset($errors['price'])): ?>
                        <span class="seller-field-error"><?= htmlspecialchars($errors['price']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Category -->
                <div class="seller-form__group">
                    <label class="seller-label" for="category_id">Category <span class="req">*</span></label>
                    <select
                        class="<?= field_class('category_id', $errors) ?>"
                        id="category_id"
                        name="category_id"
                    >
                        <option value="">— Select category —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option
                                value="<?= $cat['id'] ?>"
                                <?= (int)$fields['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['category_id'])): ?>
                        <span class="seller-field-error"><?= htmlspecialchars($errors['category_id']) ?></span>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!-- Card: Image Upload -->
        <div class="seller-form-card">
            <h3 class="seller-form-card__title">Food Image <span style="font-size:0.8125rem;font-weight:600;color:var(--sage);">(optional)</span></h3>
            <p style="font-size:0.875rem;color:var(--sage);margin-bottom:1rem;">
                Upload a clear photo of your dish. JPG, PNG or WebP, max 3MB.
            </p>

            <!-- Drop zone -->
            <div class="seller-upload-zone" id="upload-zone">
                <input
                    type="file"
                    id="image"
                    name="image"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    class="seller-upload-zone__input"
                />
                <div class="seller-upload-zone__content" id="upload-content">
                    <span class="material-symbols-outlined seller-upload-zone__icon">add_photo_alternate</span>
                    <p class="seller-upload-zone__text">Click to upload or drag & drop</p>
                    <p class="seller-upload-zone__sub">JPG, PNG, WebP up to 3MB</p>
                </div>
                <!-- Preview shown after selection -->
                <img id="upload-preview" class="seller-upload-zone__preview" alt="Preview" style="display:none;" />
            </div>

            <?php if (isset($errors['image'])): ?>
                <span class="seller-field-error"><?= htmlspecialchars($errors['image']) ?></span>
            <?php endif; ?>
        </div>

        <!-- Submit row -->
        <div style="display:flex;gap:1rem;justify-content:flex-end;align-items:center;">
            <a href="<?= BASE_PATH ?>seller/dashboard.php" class="seller-btn-secondary">
                Cancel
            </a>
            <button type="submit" class="seller-btn-primary">
                <span class="material-symbols-outlined">check</span>
                Add to Menu
            </button>
        </div>

    </form>

</div><!-- /seller-page -->

<script>
(function () {
    const zone    = document.getElementById('upload-zone');
    const input   = document.getElementById('image');
    const content = document.getElementById('upload-content');
    const preview = document.getElementById('upload-preview');

    function showPreview(file) {
        if (!file || !file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = e => {
            preview.src          = e.target.result;
            preview.style.display = 'block';
            content.style.display = 'none';
        };
        reader.readAsDataURL(file);
    }

    // File selected via click
    input.addEventListener('change', () => {
        if (input.files[0]) showPreview(input.files[0]);
    });

    // Drag and drop
    zone.addEventListener('dragover', e => {
        e.preventDefault();
        zone.classList.add('dragover');
    });

    zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));

    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('dragover');
        const file = e.dataTransfer.files[0];
        if (file) {
            // Transfer to the actual input for form submission
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            showPreview(file);
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>