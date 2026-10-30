<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-guard.php';
require_role('seller');

// POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . 'seller/dashboard.php');
    exit;
}

$item_id    = (int)($_POST['id']      ?? 0);
$current    = (int)($_POST['current'] ?? 0);
$seller_id  = $_SESSION['user_id'];
$new_status = $current ? 0 : 1;

if ($item_id > 0) {
    // Only update if this item belongs to the logged-in seller
    $stmt = $pdo->prepare('
        UPDATE food_items
        SET is_available = ?
        WHERE id = ? AND seller_id = ?
    ');
    $stmt->execute([$new_status, $item_id, $seller_id]);

    $_SESSION['flash'] = [
        'type'    => 'success',
        'message' => $new_status
            ? 'Item is now visible on the menu.'
            : 'Item has been hidden from the menu.',
    ];
}

header('Location: ' . BASE_PATH . 'seller/dashboard.php');
exit;