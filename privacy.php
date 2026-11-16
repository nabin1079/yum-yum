<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth-guard.php';

$page_title = 'Privacy Policy';
$active_nav = '';
$extra_css  = ['pages.css'];
require_once __DIR__ . '/includes/header.php';
?>
<article class="legal-page">
    <h1>Privacy Policy</h1>
    <p class="legal-page__updated">Last updated <?= e(date('j F Y', strtotime('2026-11-15'))) ?></p>

    <h2>What we collect</h2>
    <p>When you create an account we store your name, email address, your role (buyer or seller) and a securely hashed version of your password. We never store your password itself. When you order, we store your delivery name, phone number, address and any note you add, together with the meals, prices and order status. Sellers may add a kitchen name, delivery area, phone number and a short bio to their profile.</p>

    <h2>How we use it</h2>
    <ul>
        <li>To let you log in and keep your account secure.</li>
        <li>To pass your delivery details to the seller who is cooking and delivering your order.</li>
        <li>To email you about your account (for example password reset links) and your orders.</li>
        <li>To answer messages you send through the contact form.</li>
    </ul>

    <h2>Who can see it</h2>
    <p>A seller sees the delivery details and email address only for orders placed with them. Buyers see a seller's public profile and, while an order is active, the seller's phone number. We do not sell or share your information with anyone else.</p>

    <h2>Payments</h2>
    <p>Yum-Yum uses cash on delivery. We do not collect or store card or bank details.</p>

    <h2>Cookies</h2>
    <p>We use one essential session cookie to keep you logged in. It is deleted when you log out or close your browser. We do not use advertising or tracking cookies.</p>

    <h2>Your choices</h2>
    <p>You can update your details at any time, or ask us to delete your account and data through the <a href="<?= BASE_PATH ?>contact.php">contact page</a>. Order records may be kept where needed for the seller's records.</p>
</article>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
