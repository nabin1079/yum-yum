<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-guard.php';
require_role('buyer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . 'buyer/cart.php');
    exit;
}

$cart_id  = (int)($_POST['cart_id'] ?? 0);
$buyer_id = $_SESSION['user_id'];

if ($cart_id === 0) {
    header('Location: ' . BASE_PATH . 'buyer/cart.php');
    exit;
}

//  Delete only if it belongs to this buyer 
$stmt = $pdo->prepare('DELETE FROM cart WHERE id = ? AND buyer_id = ?');
$stmt->execute([$cart_id, $buyer_id]);

$_SESSION['flash'] = [
    'type'    => 'success',
    'message' => 'Item removed from your cart.',
];

header('Location: ' . BASE_PATH . 'buyer/cart.php');
exit;