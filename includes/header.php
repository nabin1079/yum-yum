<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = $page_title ?? 'Yum-Yum Tiffin Services';
$active_nav = $active_nav ?? '';
$user       = current_user();
$role       = $user['role']  ?? null;

// Cart item count — only query if buyer is logged in
$cart_count = 0;
if ($role === 'buyer' && isset($pdo)) {
    $stmt = $pdo->prepare('SELECT SUM(quantity) as total FROM cart WHERE buyer_id = ?');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();
    $cart_count = (int)($row['total'] ?? 0);
}

// Helper — adds 'active' class to correct nav link
function nav_class(string $link, string $active): string {
    return $link === $active ? 'navbar__link navbar__link--active' : 'navbar__link';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Yum-Yum — Fresh home-style tiffins delivered to your campus." />
    <title><?= htmlspecialchars($page_title) ?> | Yum-Yum</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet" />

    <!-- Global CSS -->
    <link rel="stylesheet" href="<?= BASE_PATH ?>css/styles.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>css/navbar.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>css/components.css" />

    <?php if (isset($extra_css)): ?>
        <!-- Page-specific CSS -->
        <?php foreach ((array)$extra_css as $css): ?>
            <link rel="stylesheet" href="<?= BASE_PATH ?>css/<?= htmlspecialchars($css) ?>" />
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>

<nav class="navbar" id="navbar">
    <div class="navbar__inner">

        <!-- Logo -->
        <a href="<?= BASE_PATH ?>index.php" class="navbar__logo">
            <div class="navbar__logo-icon">
                <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">restaurant_menu</span>
            </div>
            <span class="navbar__logo-text">Yum-Yum</span>
        </a>

        <!-- Desktop nav links -->
        <ul class="navbar__links" id="navbar-links">

            <?php if ($role === 'seller'): ?>
                <!-- Seller navigation -->
                <li>
                    <a href="<?= BASE_PATH ?>seller/dashboard.php"
                       class="<?= nav_class('dashboard', $active_nav) ?>">
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_PATH ?>seller/food_add.php"
                       class="<?= nav_class('food_add', $active_nav) ?>">
                        Add Food
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_PATH ?>seller/orders.php"
                       class="<?= nav_class('orders', $active_nav) ?>">
                        Orders
                    </a>
                </li> 
                <li>
                    <a href="<?= BASE_PATH ?>about.php"
                       class="<?= nav_class('about', $active_nav) ?>">
                        About
                    </a>
                </li>

            <?php elseif ($role === 'buyer'): ?>
                <!-- Buyer navigation -->
                <li>
                    <a href="<?= BASE_PATH ?>index.php"
                       class="<?= nav_class('home', $active_nav) ?>">
                        Home
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_PATH ?>buyer/menu.php"
                       class="<?= nav_class('menu', $active_nav) ?>">
                        Menu
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_PATH ?>buyer/orders.php"
                       class="<?= nav_class('orders', $active_nav) ?>">
                        My Orders
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_PATH ?>about.php"
                       class="<?= nav_class('about', $active_nav) ?>">
                        About
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_PATH ?>contact.php"
                       class="<?= nav_class('contact', $active_nav) ?>">
                        Contact
                    </a>
                </li>

            <?php else: ?>
                <li>
                    <a href="<?= BASE_PATH ?>index.php"
                       class="<?= nav_class('home', $active_nav) ?>">
                        Home
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_PATH ?>buyer/menu.php"
                       class="<?= nav_class('menu', $active_nav) ?>">
                        Menu
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_PATH ?>about.php"
                       class="<?= nav_class('about', $active_nav) ?>">
                        About
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_PATH ?>contact.php"
                       class="<?= nav_class('contact', $active_nav) ?>">
                        Contact
                    </a>
                </li>
            <?php endif; ?>

        </ul>

        <!-- Right side actions -->
        <div class="navbar__actions">

            <?php if ($role === 'buyer'): ?>
                <!-- Cart icon with item count badge -->
                <a href="<?= BASE_PATH ?>buyer/cart.php"
                   class="navbar__cart-btn <?= $active_nav === 'cart' ? 'active' : '' ?>"
                   aria-label="View cart">
                    <span class="material-symbols-outlined" style="font-variation-settings:'FILL' <?= $cart_count > 0 ? '1' : '0' ?>;">
                        shopping_cart
                    </span>
                    <?php if ($cart_count > 0): ?>
                        <span class="navbar__cart-badge"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <?php if ($user): ?>
                <!-- Logged in — show user name + logout -->
                <div class="navbar__user">
                    <span class="navbar__user-name">
                        <?= htmlspecialchars($user['first_name']) ?>
                    </span>
                    <form method="POST" action="<?= BASE_PATH ?>auth/logout.php" style="margin:0;">
                        <button type="submit" class="navbar__btn-logout">
                            <span class="material-symbols-outlined">logout</span>
                            Logout
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <!-- Guest — show login + register -->
                <a href="<?= BASE_PATH ?>auth/login.php"    class="navbar__btn-login">Login</a>
                <a href="<?= BASE_PATH ?>auth/register.php" class="navbar__btn-order">Sign Up</a>
            <?php endif; ?>

            <!-- Mobile hamburger -->
            <button class="navbar__hamburger" id="navbar-hamburger" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>

        </div>
    </div>

    <div class="navbar__mobile-menu" id="navbar-mobile-menu">
        <ul class="navbar__mobile-links">

            <?php if ($role === 'seller'): ?>
                <li><a href="<?= BASE_PATH ?>seller/dashboard.php">Dashboard</a></li>
                <li><a href="<?= BASE_PATH ?>seller/food_add.php">Add Food</a></li>
                <li><a href="<?= BASE_PATH ?>seller/orders.php">Orders</a></li>

            <?php elseif ($role === 'buyer'): ?>
                <li><a href="<?= BASE_PATH ?>index.php">Home</a></li>
                <li><a href="<?= BASE_PATH ?>buyer/menu.php">Menu</a></li>
                <li><a href="<?= BASE_PATH ?>buyer/cart.php">Cart <?= $cart_count > 0 ? "($cart_count)" : '' ?></a></li>
                <li><a href="<?= BASE_PATH ?>buyer/orders.php">My Orders</a></li>
                <li><a href="<?= BASE_PATH ?>about.php">About</a></li>
                <li><a href="<?= BASE_PATH ?>contact.php">Contact</a></li>

            <?php else: ?>
                <li><a href="<?= BASE_PATH ?>index.php">Home</a></li>
                <li><a href="<?= BASE_PATH ?>buyer/menu.php">Menu</a></li>
                <li><a href="<?= BASE_PATH ?>about.php">About</a></li>
                <li><a href="<?= BASE_PATH ?>contact.php">Contact</a></li>
            <?php endif; ?>

            <?php if ($user): ?>
                <li>
                    <form method="POST" action="<?= BASE_PATH ?>auth/logout.php" style="margin:0;">
                        <button type="submit" class="navbar__mobile-logout">Logout</button>
                    </form>
                </li>
            <?php else: ?>
                <li><a href="<?= BASE_PATH ?>auth/login.php">Login</a></li>
                <li><a href="<?= BASE_PATH ?>auth/register.php">Sign Up</a></li>
            <?php endif; ?>

        </ul>
    </div>
</nav>
<main class="page-main">
