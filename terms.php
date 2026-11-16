<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth-guard.php';

$page_title = 'Terms of Service';
$active_nav = '';
$extra_css  = ['pages.css'];
require_once __DIR__ . '/includes/header.php';
?>
<article class="legal-page">
    <h1>Terms of Service</h1>
    <p class="legal-page__updated">Last updated <?= e(date('j F Y', strtotime('2026-11-15'))) ?></p>

    <h2>The service</h2>
    <p>Yum-Yum connects home cooks (sellers) with people who want home-style meals (buyers). Each seller prepares, delivers and is responsible for their own food. Yum-Yum provides the website that lists meals and records orders.</p>

    <h2>Orders and cash on delivery</h2>
    <ul>
        <li>Prices are shown on each meal and are fixed when you place your order.</li>
        <li>If your cart has meals from more than one seller, a separate order is created for each seller.</li>
        <li>You pay the seller in cash when your food arrives. Please confirm in My Orders once you receive it.</li>
        <li>You can cancel an order while it is still pending. After the seller accepts it, please contact the seller.</li>
        <li>Sellers mark an order as paid once they have received the cash.</li>
    </ul>

    <h2>Sellers</h2>
    <p>Sellers must describe their meals honestly, including ingredients that may cause allergies, follow local food safety rules, and deliver orders they accept. Sellers keep their profile and menu up to date and may hide or remove meals at any time.</p>

    <h2>Accounts</h2>
    <p>Keep your password private. You are responsible for activity on your account. We may suspend accounts that misuse the service, for example by placing fake orders.</p>

    <h2>Contact</h2>
    <p>Questions or problems with an order? Use the <a href="<?= BASE_PATH ?>contact.php">contact page</a> and we will get back to you.</p>
</article>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
