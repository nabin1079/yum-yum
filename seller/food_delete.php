<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-guard.php';
require_role('seller');

// POST only — reject any GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . 'seller/dashboard.php');
    exit;
}

$item_id   = (int)($_POST['id'] ?? 0);
$seller_id = $_SESSION['user_id'];

if ($item_id === 0) {
    header('Location: ' . BASE_PATH . 'seller/dashboard.php');
    exit;
}

//  Fetch the item to verify ownership & get image path 
$stmt = $pdo->prepare('
    SELECT id, name, image_path
    FROM food_items
    WHERE id = ? AND seller_id = ?
');
$stmt->execute([$item_id, $seller_id]);
$item = $stmt->fetch();

if (!$item) {
    // Item not found or doesn't belong to this seller
    $_SESSION['flash'] = [
        'type'    => 'error',
        'message' => 'Item not found or you do not have permission to delete it.',
    ];
    header('Location: ' . BASE_PATH . 'seller/dashboard.php');
    exit;
}

//  Delete the image file from disk 
if ($item['image_path']) {
    $image_file = __DIR__ . '/../uploads/food/' . $item['image_path'];
    if (file_exists($image_file)) {
        unlink($image_file);
    }
}

// Delete from database 
// order_items rows referencing this food_item will be kept
// (ON DELETE RESTRICT) so historical orders stay intact.
// We just delete the food_items row itself.
$stmt = $pdo->prepare('
    DELETE FROM food_items
    WHERE id = ? AND seller_id = ?
');
$stmt->execute([$item_id, $seller_id]);

//  Flash and redirect 
$_SESSION['flash'] = [
    'type'    => 'success',
    'message' => '"' . $item['name'] . '" has been deleted from your menu.',
];

header('Location: ' . BASE_PATH . 'seller/dashboard.php');
exit;