<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-guard.php';
require_once __DIR__ . '/../includes/upload.php';
require_role('seller');

// POST only — reject any GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('seller/dashboard.php');
}

$item_id   = (int)($_POST['id'] ?? 0);
$seller_id = (int)$_SESSION['user_id'];

// The item must exist, belong to this seller and not be archived already
$stmt = $pdo->prepare('
    SELECT id, name, image_path
    FROM food_items
    WHERE id = ? AND seller_id = ? AND deleted_at IS NULL
');
$stmt->execute([$item_id, $seller_id]);
$item = $stmt->fetch();

if (!$item) {
    flash('error', 'Item not found or you do not have permission to delete it.');
    redirect('seller/dashboard.php');
}

// Remove it from every buyer's cart in both cases
$pdo->prepare('DELETE FROM cart WHERE food_item_id = ?')->execute([$item_id]);

$stmt = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE food_item_id = ?');
$stmt->execute([$item_id]);
$has_orders = (int)$stmt->fetchColumn() > 0;

if ($has_orders) {
    // Past orders need this meal's name and picture, so archive it instead.
    $pdo->prepare('
        UPDATE food_items SET deleted_at = NOW(), is_available = 0
        WHERE id = ? AND seller_id = ?
    ')->execute([$item_id, $seller_id]);

    flash('success', '"' . $item['name'] . '" has been removed from your menu. It stays in past orders so your order history is kept.');
    redirect('seller/dashboard.php');
}

try {
    $pdo->prepare('DELETE FROM food_items WHERE id = ? AND seller_id = ?')
        ->execute([$item_id, $seller_id]);
} catch (PDOException $e) {
    // An order was placed for it a moment ago — archive instead.
    error_log('food_delete fell back to archive: ' . $e->getMessage());
    $pdo->prepare('UPDATE food_items SET deleted_at = NOW(), is_available = 0 WHERE id = ? AND seller_id = ?')
        ->execute([$item_id, $seller_id]);
    flash('success', '"' . $item['name'] . '" has been removed from your menu.');
    redirect('seller/dashboard.php');
}

// The row is gone, so the image can be deleted safely now.
delete_food_image($item['image_path']);

flash('success', '"' . $item['name'] . '" has been deleted from your menu.');
redirect('seller/dashboard.php');
