<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-guard.php';
require_role('buyer');

$page_title = 'Checkout';
$active_nav = 'cart';
$extra_css  = ['pages.css'];

$buyer_id = $_SESSION['user_id'];

//  Fetch available cart items only 
$stmt = $pdo->prepare('
    SELECT
        c.id          AS cart_id,
        c.quantity,
        fi.id         AS food_item_id,
        fi.name,
        fi.price,
        fi.image_path,
        cat.name      AS category,
        u.first_name  AS seller_first,
        u.last_name   AS seller_last
    FROM cart c
    JOIN food_items  fi  ON fi.id  = c.food_item_id  AND fi.is_available = 1
    JOIN categories  cat ON cat.id = fi.category_id
    JOIN users       u   ON u.id   = fi.seller_id
    WHERE c.buyer_id = ?
    ORDER BY c.id ASC
');
$stmt->execute([$buyer_id]);
$cart_items = $stmt->fetchAll();

// Nothing to checkout
if (empty($cart_items)) {
    $_SESSION['flash'] = [
        'type'    => 'error',
        'message' => 'Your cart is empty or contains only unavailable items.',
    ];
    header('Location: ' . BASE_PATH . 'buyer/cart.php');
    exit;
}

//  Calculate total 
$total = array_sum(array_map(
    fn($i) => $i['price'] * $i['quantity'],
    $cart_items
));

//  POST — confirm order
if (isset($_POST['confirm_order'])) {
    try {
        $pdo->beginTransaction();

        // 1. Insert into orders
        $stmt = $pdo->prepare('
            INSERT INTO orders (buyer_id, total_amount, status)
            VALUES (?, ?, \'pending\')
        ');
        $stmt->execute([$buyer_id, $total]);
        $order_id = (int)$pdo->lastInsertId();

        // 2. Insert each cart item into order_items
        //    price_at_purchase locked at current price
        $stmt = $pdo->prepare('
            INSERT INTO order_items (order_id, food_item_id, quantity, price_at_purchase)
            VALUES (?, ?, ?, ?)
        ');
        foreach ($cart_items as $ci) {
            $stmt->execute([
                $order_id,
                $ci['food_item_id'],
                $ci['quantity'],
                $ci['price'],
            ]);
        }

        // 3. Clear the buyer's cart
        $stmt = $pdo->prepare('DELETE FROM cart WHERE buyer_id = ?');
        $stmt->execute([$buyer_id]);

        $pdo->commit();

        // 4. Redirect to order confirmation
        $_SESSION['flash'] = [
            'type'    => 'success',
            'message' => 'Your order #' . $order_id . ' has been placed successfully!',
        ];
        header('Location: ' . BASE_PATH . 'buyer/orders.php');
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Something went wrong placing your order. Please try again.';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:900px;margin:0 auto;padding:2.5rem 1.5rem 4rem;">

    <!-- Breadcrumb -->
    <nav class="breadcrumb" style="margin-bottom:2rem;">
        <a href="<?= BASE_PATH ?>buyer/menu.php">Menu</a>
        <span class="material-symbols-outlined">chevron_right</span>
        <a href="<?= BASE_PATH ?>buyer/cart.php">Cart</a>
        <span class="material-symbols-outlined">chevron_right</span>
        <span>Checkout</span>
    </nav>

    <!-- Heading -->
    <h1 style="font-size:1.875rem;font-weight:800;color:var(--earth);letter-spacing:-0.03em;margin-bottom:0.25rem;">
        Checkout
    </h1>
    <p style="color:var(--sage);font-size:0.9375rem;font-weight:500;margin-bottom:2rem;">
        Review your order before confirming.
    </p>

    <!-- Error -->
    <?php if (isset($error)): ?>
        <div style="padding:0.875rem 1.25rem;border-radius:var(--radius-lg);background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);color:#dc2626;font-size:0.9375rem;font-weight:600;margin-bottom:1.5rem;display:flex;align-items:center;gap:0.625rem;">
            <span class="material-symbols-outlined" style="font-size:1.1rem;font-variation-settings:'FILL' 1;">error</span>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:start;">

        <!-- Left: order items -->
        <div>
            <div style="background:var(--white);border:1px solid var(--border-light);border-radius:var(--radius-2xl);overflow:hidden;box-shadow:var(--shadow-sm);">

                <div style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--border-light);background:var(--bg-light);">
                    <h2 style="font-size:1rem;font-weight:800;color:var(--earth);">
                        Order Items (<?= count($cart_items) ?>)
                    </h2>
                </div>

                <div style="display:flex;flex-direction:column;">
                    <?php foreach ($cart_items as $index => $ci): ?>
                        <div style="
                            display:flex;
                            align-items:center;
                            gap:1rem;
                            padding:1.1rem 1.5rem;
                            <?= $index < count($cart_items) - 1 ? 'border-bottom:1px solid var(--border-light);' : '' ?>
                        ">
                            <!-- Thumbnail -->
                            <?php if ($ci['image_path']): ?>
                                <img
                                    src="<?= BASE_PATH ?>uploads/food/<?= htmlspecialchars($ci['image_path']) ?>"
                                    alt="<?= htmlspecialchars($ci['name']) ?>"
                                    style="width:3.5rem;height:3.5rem;border-radius:var(--radius-lg);object-fit:cover;border:1px solid var(--border-light);flex-shrink:0;"
                                />
                            <?php else: ?>
                                <div style="width:3.5rem;height:3.5rem;border-radius:var(--radius-lg);background:var(--primary-dim);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <span class="material-symbols-outlined" style="font-size:1.25rem;color:var(--sage);opacity:0.5;">restaurant</span>
                                </div>
                            <?php endif; ?>

                            <!-- Name + seller -->
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:0.9375rem;font-weight:700;color:var(--earth);margin-bottom:0.125rem;">
                                    <?= htmlspecialchars($ci['name']) ?>
                                </div>
                                <div style="font-size:0.8125rem;color:var(--sage);font-weight:600;">
                                    <?= htmlspecialchars($ci['seller_first'] . ' ' . $ci['seller_last']) ?>
                                    · <?= htmlspecialchars($ci['category']) ?>
                                </div>
                            </div>

                            <!-- Qty × price -->
                            <div style="text-align:right;flex-shrink:0;">
                                <div style="font-size:0.9375rem;font-weight:800;color:var(--earth);">
                                    $<?= number_format($ci['price'] * $ci['quantity'], 2) ?>
                                </div>
                                <div style="font-size:0.8125rem;color:var(--sage);font-weight:600;">
                                    <?= $ci['quantity'] ?> × $<?= number_format($ci['price'], 2) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>

            <!-- Delivery info -->
            <div style="margin-top:1rem;background:var(--white);border:1px solid var(--border-light);border-radius:var(--radius-2xl);padding:1.25rem 1.5rem;box-shadow:var(--shadow-sm);">
                <h3 style="font-size:1rem;font-weight:800;color:var(--earth);margin-bottom:1rem;">Delivery Details</h3>
                <div style="display:flex;flex-direction:column;gap:0.625rem;">
                    <div style="display:flex;align-items:center;gap:0.625rem;font-size:0.9375rem;color:var(--sage);font-weight:600;">
                        <span class="material-symbols-outlined" style="font-size:1.1rem;font-variation-settings:'FILL' 1;color:var(--earth);">person</span>
                        <?= htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?>
                    </div>
                    <div style="display:flex;align-items:center;gap:0.625rem;font-size:0.9375rem;color:var(--sage);font-weight:600;">
                        <span class="material-symbols-outlined" style="font-size:1.1rem;font-variation-settings:'FILL' 1;color:var(--earth);">local_shipping</span>
                        Standard delivery · Free
                    </div>
                    <div style="display:flex;align-items:center;gap:0.625rem;font-size:0.9375rem;color:var(--sage);font-weight:600;">
                        <span class="material-symbols-outlined" style="font-size:1.1rem;font-variation-settings:'FILL' 1;color:var(--earth);">schedule</span>
                        Estimated delivery: 30–45 minutes
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: summary + confirm -->
        <div style="position:sticky;top:5rem;display:flex;flex-direction:column;gap:1rem;">

            <!-- Price breakdown -->
            <div style="background:var(--white);border:1px solid var(--border-light);border-radius:var(--radius-2xl);padding:1.5rem;box-shadow:var(--shadow-sm);">
                <h2 style="font-size:1rem;font-weight:800;color:var(--earth);margin-bottom:1.25rem;padding-bottom:1rem;border-bottom:1px solid var(--border-light);">
                    Payment Summary
                </h2>

                <div style="display:flex;flex-direction:column;gap:0.625rem;margin-bottom:1rem;">
                    <?php foreach ($cart_items as $ci): ?>
                        <div style="display:flex;justify-content:space-between;font-size:0.875rem;color:var(--sage);font-weight:600;">
                            <span><?= htmlspecialchars(mb_strimwidth($ci['name'], 0, 22, '…')) ?> ×<?= $ci['quantity'] ?></span>
                            <span>$<?= number_format($ci['price'] * $ci['quantity'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div style="display:flex;justify-content:space-between;font-size:0.875rem;color:var(--sage);font-weight:600;padding-top:0.5rem;border-top:1px solid var(--border-light);">
                        <span>Delivery</span>
                        <span style="color:#16a34a;font-weight:700;">Free</span>
                    </div>
                </div>

                <div style="display:flex;justify-content:space-between;font-size:1.125rem;font-weight:800;color:var(--earth);padding-top:1rem;border-top:1px solid var(--border-light);margin-bottom:1.5rem;">
                    <span>Total</span>
                    <span>$<?= number_format($total, 2) ?></span>
                </div>

                <!-- Confirm order form -->
                <form method="POST" action="" style="margin:0;" id="checkout-form">
                    <input type="hidden" name="confirm_order" value="1" />
                    <button
                        type="submit"
                        id="checkout-btn"
                        style="width:100%;display:flex;align-items:center;justify-content:center;gap:0.625rem;background:var(--primary);color:var(--earth);padding:1rem;border-radius:var(--radius-xl);border:none;font-family:var(--font-body);font-size:1rem;font-weight:800;cursor:pointer;box-shadow:0 4px 14px rgba(244,209,37,0.35);transition:box-shadow 0.25s ease,transform 0.15s ease;"
                    >
                        <span class="material-symbols-outlined">check_circle</span>
                        Confirm Order
                    </button>
                </form>

                <a href="<?= BASE_PATH ?>buyer/cart.php"
                   style="display:flex;align-items:center;justify-content:center;gap:0.375rem;margin-top:0.875rem;font-size:0.875rem;font-weight:700;color:var(--sage);text-decoration:none;">
                    <span class="material-symbols-outlined" style="font-size:1rem;">arrow_back</span>
                    Back to Cart
                </a>
            </div>

            <!-- Trust badges -->
            <div style="background:var(--white);border:1px solid var(--border-light);border-radius:var(--radius-xl);padding:1.1rem 1.25rem;box-shadow:var(--shadow-sm);display:flex;flex-direction:column;gap:0.625rem;">
                <div style="display:flex;align-items:center;gap:0.625rem;font-size:0.8125rem;font-weight:600;color:var(--sage);">
                    <span class="material-symbols-outlined" style="font-size:1rem;font-variation-settings:'FILL' 1;color:#16a34a;">verified</span>
                    Freshly made by local sellers
                </div>
                <div style="display:flex;align-items:center;gap:0.625rem;font-size:0.8125rem;font-weight:600;color:var(--sage);">
                    <span class="material-symbols-outlined" style="font-size:1rem;font-variation-settings:'FILL' 1;color:#16a34a;">lock</span>
                    Secure checkout
                </div>
                <div style="display:flex;align-items:center;gap:0.625rem;font-size:0.8125rem;font-weight:600;color:var(--sage);">
                    <span class="material-symbols-outlined" style="font-size:1rem;font-variation-settings:'FILL' 1;color:#16a34a;">replay</span>
                    Cancel anytime before delivery
                </div>
            </div>

        </div>
    </div>
</div>

<style>
@media (max-width: 768px) {
    div[style*="grid-template-columns:1fr 320px"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script>
// Disable button on form submit to prevent double-clicks
document.getElementById('checkout-form')?.addEventListener('submit', function(e) {
    const btn = document.getElementById('checkout-btn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span style="font-family:var(--font-body);font-weight:800;">Placing order...</span>';
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>