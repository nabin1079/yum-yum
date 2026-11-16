<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-guard.php';
require_role('seller');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('seller/dashboard.php');
}

$item_id   = (int)($_POST['id'] ?? 0);
$seller_id = (int)$_SESSION['user_id'];

// Flip the value stored in the database (not a value sent by the browser),
// and only for this seller's own, non-archived item.
$stmt = $pdo->prepare('
    UPDATE food_items
    SET is_available = 1 - is_available
    WHERE id = ? AND seller_id = ? AND deleted_at IS NULL
');
$stmt->execute([$item_id, $seller_id]);

if ($stmt->rowCount() === 1) {
    $stmt = $pdo->prepare('SELECT is_available FROM food_items WHERE id = ?');
    $stmt->execute([$item_id]);
    $now_available = (int)$stmt->fetchColumn() === 1;
    flash('success', $now_available ? 'Item is now visible on the menu.' : 'Item has been hidden from the menu.');
} else {
    flash('error', 'Item not found.');
}

redirect('seller/dashboard.php');
