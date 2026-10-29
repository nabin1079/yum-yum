<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-guard.php';
require_role('buyer');

$page_title = 'My Cart';
$active_nav = 'cart';
$extra_css  = ['pages.css'];

$buyer_id = $_SESSION['user_id'];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$stmt = $pdo->prepare('
    SELECT
        c.id          AS cart_id,
        c.quantity,
        fi.id         AS food_item_id,
        fi.name,
        fi.description,
        fi.price,
        fi.image_path,
        fi.is_available,
        cat.name      AS category,
        u.first_name  AS seller_first,
        u.last_name   AS seller_last
    FROM cart c
    JOIN food_items  fi  ON fi.id  = c.food_item_id
    JOIN categories  cat ON cat.id = fi.category_id
    JOIN users       u   ON u.id   = fi.seller_id
    WHERE c.buyer_id = ?
    ORDER BY c.id ASC
');
$stmt->execute([$buyer_id]);
$cart_items = $stmt->fetchAll();

//  Calculate totals
$subtotal    = 0;
$item_count  = 0;
$unavailable = [];

foreach ($cart_items as $ci) {
    if ($ci['is_available']) {
        $subtotal   += $ci['price'] * $ci['quantity'];
        $item_count += $ci['quantity'];
    } else {
        $unavailable[] = $ci['name'];
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:1100px;margin:0 auto;padding:2.5rem 1.5rem 4rem;">

    <!-- Page heading -->
    <div style="margin-bottom:2rem;">
        <h1 style="font-size:1.875rem;font-weight:800;color:var(--earth);letter-spacing:-0.03em;margin-bottom:0.25rem;">
            My Cart
        </h1>
        <p style="color:var(--sage);font-size:0.9375rem;font-weight:500;">
            <?= $item_count ?> item<?= $item_count !== 1 ? 's' : '' ?> in your cart
        </p>
    </div>

    <!-- Flash -->
    <?php if ($flash): ?>
        <div style="
            padding:0.875rem 1.25rem;
            border-radius:var(--radius-lg);
            font-size:0.9375rem;
            font-weight:600;
            margin-bottom:1.5rem;
            display:flex;align-items:center;gap:0.625rem;
            background:<?= $flash['type'] === 'success' ? 'rgba(34,197,94,0.08)' : 'rgba(239,68,68,0.08)' ?>;
            border:1px solid <?= $flash['type'] === 'success' ? 'rgba(34,197,94,0.25)' : 'rgba(239,68,68,0.25)' ?>;
            color:<?= $flash['type'] === 'success' ? '#15803d' : '#dc2626' ?>;
        ">
            <span class="material-symbols-outlined" style="font-size:1.1rem;font-variation-settings:'FILL' 1;">
                <?= $flash['type'] === 'success' ? 'check_circle' : 'error' ?>
            </span>
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- Unavailable items warning -->
    <?php if (!empty($unavailable)): ?>
        <div style="padding:0.875rem 1.25rem;border-radius:var(--radius-lg);background:rgba(249,115,22,0.08);border:1px solid rgba(249,115,22,0.25);color:#c2410c;font-size:0.9375rem;font-weight:600;margin-bottom:1.5rem;display:flex;align-items:flex-start;gap:0.625rem;">
            <span class="material-symbols-outlined" style="font-size:1.1rem;font-variation-settings:'FILL' 1;flex-shrink:0;margin-top:1px;">warning</span>
            <span>
                The following items are no longer available and will be excluded from checkout:
                <strong><?= htmlspecialchars(implode(', ', $unavailable)) ?></strong>.
                Please remove them from your cart.
            </span>
        </div>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>

        <!-- Empty cart -->
        <div style="text-align:center;padding:5rem 1rem;color:var(--sage);">
            <span class="material-symbols-outlined" style="font-size:4rem;display:block;margin-bottom:1rem;opacity:0.3;">
                shopping_cart
            </span>
            <p style="font-size:1.25rem;font-weight:700;color:var(--earth);margin-bottom:0.5rem;">Your cart is empty</p>
            <p style="font-size:0.9375rem;margin-bottom:2rem;">Browse the menu and add something delicious.</p>
            <a href="<?= BASE_PATH ?>buyer/menu.php"
               style="display:inline-flex;align-items:center;gap:0.5rem;background:var(--primary);color:var(--earth);padding:0.875rem 2rem;border-radius:var(--radius-xl);font-weight:800;text-decoration:none;">
                <span class="material-symbols-outlined">restaurant_menu</span>
                Browse Menu
            </a>
        </div>

    <?php else: ?>

        <div style="display:grid;grid-template-columns:1fr;gap:1.5rem;">
            <div style="display:grid;grid-template-columns:1fr 340px;gap:1.5rem;align-items:start;">

                <!-- Left: cart items -->
                <div style="display:flex;flex-direction:column;gap:1rem;">
                    <?php foreach ($cart_items as $ci): ?>
                        <div style="
                            background:var(--white);
                            border:1px solid var(--border-light);
                            border-radius:var(--radius-2xl);
                            padding:1.25rem;
                            box-shadow:var(--shadow-sm);
                            display:flex;
                            gap:1.25rem;
                            align-items:flex-start;
                            <?= !$ci['is_available'] ? 'opacity:0.6;' : '' ?>
                        ">

                            <!-- Image -->
                            <a href="<?= BASE_PATH ?>buyer/product.php?id=<?= $ci['food_item_id'] ?>"
                               style="flex-shrink:0;text-decoration:none;">
                                <?php if ($ci['image_path']): ?>
                                    <img
                                        src="<?= BASE_PATH ?>uploads/food/<?= htmlspecialchars($ci['image_path']) ?>"
                                        alt="<?= htmlspecialchars($ci['name']) ?>"
                                        style="width:5rem;height:5rem;object-fit:cover;border-radius:var(--radius-xl);border:1px solid var(--border-light);"
                                    />
                                <?php else: ?>
                                    <div style="width:5rem;height:5rem;border-radius:var(--radius-xl);background:var(--primary-dim);display:flex;align-items:center;justify-content:center;">
                                        <span class="material-symbols-outlined" style="font-size:1.75rem;color:var(--sage);opacity:0.5;">restaurant</span>
                                    </div>
                                <?php endif; ?>
                            </a>

                            <!-- Details -->
                            <div style="flex:1;min-width:0;">
                                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;">
                                    <div>
                                        <a href="<?= BASE_PATH ?>buyer/product.php?id=<?= $ci['food_item_id'] ?>"
                                           style="font-size:1rem;font-weight:800;color:var(--earth);text-decoration:none;display:block;margin-bottom:0.125rem;">
                                            <?= htmlspecialchars($ci['name']) ?>
                                        </a>
                                        <span style="font-size:0.8125rem;color:var(--sage);font-weight:600;">
                                            <?= htmlspecialchars($ci['category']) ?> ·
                                            <?= htmlspecialchars($ci['seller_first'] . ' ' . $ci['seller_last']) ?>
                                        </span>
                                        <?php if (!$ci['is_available']): ?>
                                            <span style="display:inline-block;margin-top:0.25rem;font-size:0.75rem;font-weight:700;color:#c2410c;background:rgba(249,115,22,0.1);padding:0.125rem 0.5rem;border-radius:var(--radius-full);">
                                                Unavailable
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <span style="font-size:1.125rem;font-weight:800;color:var(--earth);white-space:nowrap;">
                                        $<?= number_format($ci['price'] * $ci['quantity'], 2) ?>
                                    </span>
                                </div>

                                <!-- Qty + Remove row -->
                                <div style="display:flex;align-items:center;gap:1rem;margin-top:1rem;flex-wrap:wrap;">

                                    <?php if ($ci['is_available']): ?>
                                        <!-- Quantity update form -->
                                        <form method="POST" action="<?= BASE_PATH ?>buyer/cart_update.php"
                                              style="display:flex;align-items:center;gap:0.5rem;margin:0;">
                                            <input type="hidden" name="cart_id" value="<?= $ci['cart_id'] ?>" />
                                            <div style="display:flex;align-items:center;border:1.5px solid var(--border-light);border-radius:var(--radius-lg);overflow:hidden;">
                                                <button type="submit" name="action" value="decrease"
                                                        style="width:2.25rem;height:2.25rem;background:var(--bg-light);border:none;font-size:1.1rem;font-weight:700;color:var(--earth);cursor:pointer;">
                                                    −
                                                </button>
                                                <span style="width:2.5rem;text-align:center;font-size:0.9375rem;font-weight:700;color:var(--earth);">
                                                    <?= $ci['quantity'] ?>
                                                </span>
                                                <button type="submit" name="action" value="increase"
                                                        style="width:2.25rem;height:2.25rem;background:var(--bg-light);border:none;font-size:1.1rem;font-weight:700;color:var(--earth);cursor:pointer;">
                                                    +
                                                </button>
                                            </div>
                                            <span style="font-size:0.8125rem;color:var(--sage);font-weight:600;">
                                                × $<?= number_format($ci['price'], 2) ?> each
                                            </span>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Remove form -->
                                    <form method="POST" action="<?= BASE_PATH ?>buyer/cart_remove.php"
                                          style="margin:0;">
                                        <input type="hidden" name="cart_id" value="<?= $ci['cart_id'] ?>" />
                                        <button type="submit"
                                                style="display:inline-flex;align-items:center;gap:0.25rem;background:none;border:none;font-family:var(--font-body);font-size:0.8125rem;font-weight:700;color:#ef4444;cursor:pointer;padding:0.25rem 0;">
                                            <span class="material-symbols-outlined" style="font-size:1rem;">delete</span>
                                            Remove
                                        </button>
                                    </form>

                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="background:var(--white);border:1px solid var(--border-light);border-radius:var(--radius-2xl);padding:1.5rem;box-shadow:var(--shadow-sm);position:sticky;top:88px;">

                    <h2 style="font-size:1.125rem;font-weight:800;color:var(--earth);margin-bottom:1.25rem;padding-bottom:1rem;border-bottom:1px solid var(--border-light);">
                        Order Summary
                    </h2>

                    <div style="display:flex;flex-direction:column;gap:0.75rem;margin-bottom:1.25rem;">
                        <div style="display:flex;justify-content:space-between;font-size:0.9375rem;color:var(--sage);font-weight:600;">
                            <span>Subtotal (<?= $item_count ?> item<?= $item_count !== 1 ? 's' : '' ?>)</span>
                            <span>$<?= number_format($subtotal, 2) ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:0.9375rem;color:var(--sage);font-weight:600;">
                            <span>Delivery</span>
                            <span style="color:#16a34a;font-weight:700;">Free</span>
                        </div>
                    </div>

                    <div style="display:flex;justify-content:space-between;font-size:1.125rem;font-weight:800;color:var(--earth);padding-top:1rem;border-top:1px solid var(--border-light);margin-bottom:1.5rem;">
                        <span>Total</span>
                        <span>$<?= number_format($subtotal, 2) ?></span>
                    </div>

                    <?php if ($item_count > 0): ?>
                        <a href="<?= BASE_PATH ?>buyer/checkout.php"
                           style="display:flex;align-items:center;justify-content:center;gap:0.5rem;background:var(--primary);color:var(--earth);padding:1rem;border-radius:var(--radius-xl);font-weight:800;font-size:1rem;text-decoration:none;box-shadow:0 4px 14px rgba(244,209,37,0.3);text-align:center;">
                            <span class="material-symbols-outlined">payment</span>
                            Proceed to Checkout
                        </a>
                    <?php else: ?>
                        <div style="text-align:center;padding:0.75rem;background:var(--bg-light);border-radius:var(--radius-xl);font-size:0.875rem;color:var(--sage);font-weight:600;">
                            Remove unavailable items to checkout
                        </div>
                    <?php endif; ?>

                    <a href="<?= BASE_PATH ?>buyer/menu.php"
                       style="display:flex;align-items:center;justify-content:center;gap:0.375rem;margin-top:0.875rem;font-size:0.875rem;font-weight:700;color:var(--sage);text-decoration:none;">
                        <span class="material-symbols-outlined" style="font-size:1rem;">arrow_back</span>
                        Continue Shopping
                    </a>

                </div>

            </div>
        </div>

    <?php endif; ?>

</div>

<style>
@media (max-width: 768px) {
    div[style*="grid-template-columns:1fr 340px"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>