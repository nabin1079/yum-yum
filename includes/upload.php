<?php
/*
 * Meal image uploads, shared by seller/food_add.php and seller/food_edit.php.
 * The file type is decided by the file's real content, and the extension
 * comes from that type, never from the name the user uploaded.
 */

const FOOD_IMAGE_DIR       = __DIR__ . '/../uploads/food/';
const FOOD_IMAGE_MAX_BYTES = 3 * 1024 * 1024;
const FOOD_IMAGE_TYPES     = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];

/*
 * Returns null when no file was chosen, ['error' => msg] on a problem,
 * or ['tmp' => path, 'ext' => ext] for a valid image that is not saved yet.
 */
function check_food_image(?array $file): ?array {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['error' => 'Image must be smaller than 3MB.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'Image upload failed. Please try again.'];
    }
    if ($file['size'] > FOOD_IMAGE_MAX_BYTES) {
        return ['error' => 'Image must be smaller than 3MB.'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!isset(FOOD_IMAGE_TYPES[$mime]) || @getimagesize($file['tmp_name']) === false) {
        return ['error' => 'Only JPG, PNG, WebP, or GIF images are allowed.'];
    }
    return ['tmp' => $file['tmp_name'], 'ext' => FOOD_IMAGE_TYPES[$mime]];
}

/* Moves a checked image into uploads/food/ and returns the new file name. */
function store_food_image(array $checked): ?string {
    if (!is_dir(FOOD_IMAGE_DIR)) {
        mkdir(FOOD_IMAGE_DIR, 0755, true);
    }
    $name = 'food_' . bin2hex(random_bytes(12)) . '.' . $checked['ext'];
    return move_uploaded_file($checked['tmp'], FOOD_IMAGE_DIR . $name) ? $name : null;
}

function delete_food_image(?string $name): void {
    if ($name && basename($name) === $name && is_file(FOOD_IMAGE_DIR . $name)) {
        @unlink(FOOD_IMAGE_DIR . $name);
    }
}

/* Shared validation for the add and edit forms. */
function validate_food_fields(PDO $pdo, array &$fields): array {
    $errors = [];
    if ($fields['name'] === '') {
        $errors['name'] = 'Food name is required.';
    } elseif (mb_strlen($fields['name']) > 200) {
        $errors['name'] = 'Name must be under 200 characters.';
    }
    if ($fields['description'] === '') {
        $errors['description'] = 'Description is required.';
    } elseif (mb_strlen($fields['description']) > 2000) {
        $errors['description'] = 'Description must be under 2000 characters.';
    }
    if ($fields['price'] === '') {
        $errors['price'] = 'Price is required.';
    } elseif (!is_numeric($fields['price']) || (float)$fields['price'] <= 0) {
        $errors['price'] = 'Please enter a valid price greater than 0.';
    } elseif ((float)$fields['price'] > 1000) {
        $errors['price'] = 'Price must be 1000 or less.';
    } else {
        $fields['price'] = number_format((float)$fields['price'], 2, '.', '');
    }
    if ($fields['category_id'] === 0) {
        $errors['category_id'] = 'Please select a category.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
        $stmt->execute([$fields['category_id']]);
        if (!$stmt->fetch()) {
            $errors['category_id'] = 'Invalid category selected.';
        }
    }
    return $errors;
}
