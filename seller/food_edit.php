<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-guard.php';
require_once __DIR__ . '/../includes/upload.php';
require_role('seller');

$page_title = 'Edit Food Item';
$active_nav = 'dashboard';
$extra_css  = ['seller.css'];

$seller_id = (int)$_SESSION['user_id'];
$item_id   = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

// Load the item — it must belong to this seller and not be archived
$stmt = $pdo->prepare('
    SELECT fi.*, c.name AS category_name
    FROM food_items fi
    JOIN categories c ON fi.category_id = c.id
    WHERE fi.id = ? AND fi.seller_id = ? AND fi.deleted_at IS NULL
');
$stmt->execute([$item_id, $seller_id]);
$item = $stmt->fetch();

if (!$item) {
    flash('error', 'Food item not found or you do not have permission to edit it.');
    redirect('seller/dashboard.php');
}

//  Fetch categories for dropdown
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();

//  Initialise fields from existing item
$errors = [];
$fields = [
    'name'        => $item['name'],
    'description' => $item['description'],
    'price'       => $item['price'],
    'category_id' => $item['category_id'],
    'is_available'=> $item['is_available'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Read and validate
    $fields['name']         = trim($_POST['name']        ?? '');
    $fields['description']  = trim($_POST['description'] ?? '');
    $fields['price']        = trim($_POST['price']       ?? '');
    $fields['category_id']  = (int)($_POST['category_id']  ?? 0);
    $fields['is_available'] = isset($_POST['is_available']) ? 1 : 0;
    $errors = validate_food_fields($pdo, $fields);

    $image = check_food_image($_FILES['image'] ?? null);
    if (isset($image['error'])) {
        $errors['image'] = $image['error'];
    }

    // 2. Work out the new image, without touching the old file yet
    $old_image  = $item['image_path'];
    $image_path = $old_image;
    $new_file   = null;

    if (empty($errors) && $image) {
        $new_file = store_food_image($image);
        if ($new_file === null) {
            $errors['image'] = 'Failed to save image. Please try again.';
        } else {
            $image_path = $new_file;
        }
    } elseif (empty($errors) && isset($_POST['remove_image'])) {
        $image_path = null;
    }

    // 3. Update, then remove the old file only once the database has changed
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('
                UPDATE food_items
                SET name = ?, description = ?, price = ?, category_id = ?,
                    image_path = ?, is_available = ?
                WHERE id = ? AND seller_id = ?
            ');
            $stmt->execute([
                $fields['name'],
                $fields['description'],
                $fields['price'],
                $fields['category_id'],
                $image_path,
                $fields['is_available'],
                $item_id,
                $seller_id,
            ]);
        } catch (Throwable $e) {
            delete_food_image($new_file);
            throw $e;
        }

        if ($old_image && $old_image !== $image_path) {
            delete_food_image($old_image);
        }

        flash('success', '"' . $fields['name'] . '" has been updated successfully.');
        redirect('seller/dashboard.php');
    }
}

function field_class(string $field, array $errors): string {
    return isset($errors[$field]) ? 'seller-input error' : 'seller-input';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="seller-page" style="max-width:760px;">

    <!-- Page header -->
    <div class="seller-page__header">
        <div>
            <h1 class="seller-page__title">Edit Food Item</h1>
            <p class="seller-page__subtitle">Update the details for "<?= htmlspecialchars($item['name']) ?>".</p>
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
            Please fix the errors below before saving.
        </div>
    <?php endif; ?>

    <!-- Edit Form -->
    <form
        method="POST"
        action=""
        enctype="multipart/form-data"
        novalidate
        style="display:flex;flex-direction:column;gap:1.5rem;"
    >
        <?= csrf_field() ?>
        <!-- Hidden item id -->
        <input type="hidden" name="id" value="<?= $item_id ?>" />

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

            <!-- Price + Category -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">

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

            <!-- Availability toggle -->
            <div style="display:flex;align-items:center;gap:0.75rem;padding:1rem;background:var(--bg-light);border-radius:var(--radius-lg);">
                <input
                    type="checkbox"
                    id="is_available"
                    name="is_available"
                    value="1"
                    <?= $fields['is_available'] ? 'checked' : '' ?>
                    style="width:1.125rem;height:1.125rem;accent-color:var(--primary);cursor:pointer;"
                />
                <label for="is_available" style="font-size:0.9375rem;font-weight:700;color:var(--earth);cursor:pointer;">
                    List this item as available on the menu
                </label>
            </div>

        </div>

        <!-- Card: Image -->
        <div class="seller-form-card">
            <h3 class="seller-form-card__title">
                Food Image
                <span style="font-size:0.8125rem;font-weight:600;color:var(--sage);">(optional)</span>
            </h3>

            <?php if ($item['image_path']): ?>
                <!-- Existing image preview -->
                <div style="margin-bottom:1rem;">
                    <p style="font-size:0.875rem;font-weight:600;color:var(--sage);margin-bottom:0.5rem;">
                        Current image:
                    </p>
                    <div style="position:relative;display:inline-block;">
                        <img
                            src="<?= BASE_PATH ?>uploads/food/<?= htmlspecialchars($item['image_path']) ?>"
                            alt="Current image"
                            style="width:160px;height:120px;object-fit:cover;border-radius:var(--radius-lg);border:1px solid var(--border-light);"
                        />
                    </div>
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-top:0.75rem;">
                        <input
                            type="checkbox"
                            id="remove_image"
                            name="remove_image"
                            value="1"
                            style="width:1rem;height:1rem;accent-color:#ef4444;cursor:pointer;"
                        />
                        <label for="remove_image"
                               style="font-size:0.875rem;font-weight:600;color:#ef4444;cursor:pointer;">
                            Remove current image
                        </label>
                    </div>
                </div>
                <p style="font-size:0.875rem;color:var(--sage);margin-bottom:0.75rem;">
                    Upload a new image to replace it, or leave empty to keep the current one.
                </p>
            <?php endif; ?>

            <!-- Upload zone -->
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
                    <p class="seller-upload-zone__text">
                        <?= $item['image_path'] ? 'Upload replacement image' : 'Click to upload or drag & drop' ?>
                    </p>
                    <p class="seller-upload-zone__sub">JPG, PNG, WebP up to 3MB</p>
                </div>
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
                <span class="material-symbols-outlined">save</span>
                Save Changes
            </button>
        </div>

    </form>

</div>

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
            preview.src           = e.target.result;
            preview.style.display = 'block';
            content.style.display = 'none';
        };
        reader.readAsDataURL(file);
    }

    input.addEventListener('change', () => {
        if (input.files[0]) showPreview(input.files[0]);
    });

    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('dragover');
        const file = e.dataTransfer.files[0];
        if (file) {
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            showPreview(file);
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>