<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-guard.php';
require_role('buyer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . 'buyer/menu.php');
    exit;
}

$food_item_id = (int)($_POST['food_item_id'] ?? 0);
$quantity     = max(1, min(20, (int)($_POST['quantity'] ?? 1)));
$redirect     = $_POST['redirect'] ?? 'menu'; // 'menu' | 'product' | 'cart'
$buyer_id     = $_SESSION['user_id'];

if ($food_item_id === 0) {
    header('Location: ' . BASE_PATH . 'buyer/menu.php');
    exit;
}

//  Verify the food item exists and is available 
$stmt = $pdo->prepare('SELECT id, name FROM food_items WHERE id = ? AND is_available = 1');
$stmt->execute([$food_item_id]);
$item = $stmt->fetch();

if (!$item) {
    $_SESSION['flash'] = [
        'type'    => 'error',
        'message' => 'This item is no longer available.',
    ];
    header('Location: ' . BASE_PATH . 'buyer/menu.php');
    exit;
}

//  Insert or increment quantity ON DUPLICATE KEY UPDATE handles the case where item is already in cart
$stmt = $pdo->prepare('
    INSERT INTO cart (buyer_id, food_item_id, quantity)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
');
$stmt->execute([$buyer_id, $food_item_id, $quantity]);

$_SESSION['flash'] = [
    'type'    => 'success',
    'message' => '"' . $item['name'] . '" has been added to your cart.',
];

//  Redirect based on where the request came from 
$back = match($redirect) {
    'product' => BASE_PATH . 'buyer/product.php?id=' . $food_item_id,
    'cart'    => BASE_PATH . 'buyer/cart.php',
    default   => BASE_PATH . 'buyer/menu.php',
};

header('Location: ' . $back);
exit;