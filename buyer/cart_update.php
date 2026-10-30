<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-guard.php';
require_role('buyer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . 'buyer/cart.php');
    exit;
}

$cart_id  = (int)($_POST['cart_id'] ?? 0);
$action   = $_POST['action'] ?? '';   // 'increase' | 'decrease'
$buyer_id = $_SESSION['user_id'];

if ($cart_id === 0 || !in_array($action, ['increase', 'decrease'])) {
    header('Location: ' . BASE_PATH . 'buyer/cart.php');
    exit;
}

//  Verify this cart row belongs to the buyer 
$stmt = $pdo->prepare('SELECT id, quantity FROM cart WHERE id = ? AND buyer_id = ?');
$stmt->execute([$cart_id, $buyer_id]);
$row = $stmt->fetch();

if (!$row) {
    header('Location: ' . BASE_PATH . 'buyer/cart.php');
    exit;
}

$new_qty = $action === 'increase'
    ? min(20, $row['quantity'] + 1)
    : $row['quantity'] - 1;

if ($new_qty <= 0) {
    // Remove the item entirely
    $stmt = $pdo->prepare('DELETE FROM cart WHERE id = ? AND buyer_id = ?');
    $stmt->execute([$cart_id, $buyer_id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Item removed from cart.'];
} else {
    $stmt = $pdo->prepare('UPDATE cart SET quantity = ? WHERE id = ? AND buyer_id = ?');
    $stmt->execute([$new_qty, $cart_id, $buyer_id]);
}

header('Location: ' . BASE_PATH . 'buyer/cart.php');
exit;