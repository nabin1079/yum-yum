<?php
/*
 * Public seller page: who the seller is and everything they currently sell.
 * Linked from the product page, the menu cards and the buyer's orders.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth-guard.php';
require_once __DIR__ . '/includes/orders.php';

$seller_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, kitchen_name, area, bio, created_at
    FROM users
    WHERE id = ? AND role = 'seller'
");
$stmt->execute([$seller_id]);
$seller = $stmt->fetch();

if (!$seller) {
    http_response_code(404);
    render_error_page(404, 'Seller not found', 'This seller page does not exist or is no longer available.');
}

$display_name = seller_display_name([
    'seller_kitchen' => $seller['kitchen_name'],
    'seller_first'   => $seller['first_name'],
    'seller_last'    => $seller['last_name'],
]);

$page_title = $display_name;
$active_nav = 'menu';
$extra_css  = ['pages.css'];

// Meals on sale now, grouped by category
$stmt = $pdo->prepare('
    SELECT fi.id, fi.name, fi.description, fi.price, fi.image_path, c.name AS category
    FROM food_items fi
    JOIN categories c ON c.id = fi.category_id
    WHERE fi.seller_id = ? AND fi.is_available = 1 AND fi.deleted_at IS NULL
    ORDER BY c.name, fi.name
');
$stmt->execute([$seller_id]);
$meals = $stmt->fetchAll();

// A little trust information: completed orders
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM orders
    WHERE seller_id = ? AND status = 'delivered' AND payment_status = 'paid'
");
$stmt->execute([$seller_id]);
$completed = (int)$stmt->fetchColumn();

$categories = array_values(array_unique(array_column($meals, 'category')));
$initials   = mb_strtoupper(mb_substr($seller['first_name'], 0, 1) . mb_substr($seller['last_name'], 0, 1));

require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width:1100px;margin:0 auto;padding:2rem 1.5rem 4rem;">

    <nav class="breadcrumb" style="margin-bottom:1.5rem;">
        <a href="<?= BASE_PATH ?>index.php">Home</a>
        <span class="material-symbols-outlined">chevron_right</span>
        <a href="<?= BASE_PATH ?>buyer/menu.php">Menu</a>
        <span class="material-symbols-outlined">chevron_right</span>
        <span><?= e($display_name) ?></span>
    </nav>

    <section class="seller-hero">
        <div class="seller-hero__avatar" aria-hidden="true"><?= e($initials) ?></div>
        <div class="seller-hero__info">
            <h1 class="seller-hero__name"><?= e($display_name) ?></h1>
            <?php if ($seller['kitchen_name']): ?>
                <p class="seller-hero__cook">Cooked by <?= e($seller['first_name'] . ' ' . $seller['last_name']) ?></p>
            <?php endif; ?>
            <div class="seller-hero__facts">
                <?php if ($seller['area']): ?>
                    <span><span class="material-symbols-outlined">location_on</span><?= e($seller['area']) ?></span>
                <?php endif; ?>
                <span><span class="material-symbols-outlined">restaurant_menu</span><?= count($meals) ?> meal<?= count($meals) !== 1 ? 's' : '' ?> on the menu</span>
                <span><span class="material-symbols-outlined">task_alt</span><?= $completed ?> completed order<?= $completed !== 1 ? 's' : '' ?></span>
                <span><span class="material-symbols-outlined">calendar_month</span>Selling since <?= e(date('F Y', strtotime($seller['created_at']))) ?></span>
                <span><span class="material-symbols-outlined">payments</span>Cash on delivery</span>
            </div>
            <?php if ($seller['bio']): ?>
                <p class="seller-hero__bio"><?= nl2br(e($seller['bio'])) ?></p>
            <?php endif; ?>
        </div>
    </section>

    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin:2.5rem 0 1.25rem;">
        <h2 style="font-size:1.375rem;font-weight:800;color:var(--earth);letter-spacing:-0.02em;">
            Everything from <?= e($display_name) ?>
        </h2>
        <?php if (count($categories) > 1): ?>
            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                <?php foreach ($categories as $cat): ?>
                    <span class="detail-tag-pill"><?= e($cat) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$meals): ?>
        <div style="text-align:center;padding:3rem 1rem;color:var(--sage);background:var(--white);border:1px solid var(--border-light);border-radius:var(--radius-2xl);">
            <span class="material-symbols-outlined" style="font-size:3rem;opacity:0.3;display:block;margin-bottom:0.75rem;">no_meals</span>
            <p style="font-weight:700;color:var(--earth);">No meals on the menu right now.</p>
            <p>Check back soon, or <a href="<?= BASE_PATH ?>buyer/menu.php" style="color:var(--earth);font-weight:700;">browse other sellers</a>.</p>
        </div>
    <?php else: ?>
        <div class="menu-grid">
            <?php foreach ($meals as $item): ?>
                <a href="<?= BASE_PATH ?>buyer/product.php?id=<?= (int)$item['id'] ?>" class="menu-card menu-card--link">
                    <div class="menu-card__image-wrapper">
                        <?php if ($item['image_path']): ?>
                            <img class="menu-card__image" src="<?= BASE_PATH ?>uploads/food/<?= e($item['image_path']) ?>" alt="<?= e($item['name']) ?>" loading="lazy" />
                        <?php else: ?>
                            <div class="menu-card__image" style="background:var(--primary-dim);display:flex;align-items:center;justify-content:center;">
                                <span class="material-symbols-outlined" style="font-size:3rem;color:var(--sage);opacity:0.4;">restaurant</span>
                            </div>
                        <?php endif; ?>
                        <span class="dish-card__badge"><?= e($item['category']) ?></span>
                    </div>
                    <div class="menu-card__body">
                        <div class="menu-card__top">
                            <h3 class="menu-card__name"><?= e($item['name']) ?></h3>
                            <span class="menu-card__price">$<?= number_format((float)$item['price'], 2) ?></span>
                        </div>
                        <p class="menu-card__desc"><?= e(mb_strimwidth($item['description'], 0, 90, '…')) ?></p>
                        <span class="menu-card__btn" style="margin-top:auto;padding-top:0.75rem;display:inline-block;">View →</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
