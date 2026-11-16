<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-guard.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/mailer.php';
require_role('buyer');

$page_title = 'Checkout';
$active_nav = 'cart';
$extra_css  = ['pages.css'];

$buyer_id = (int)$_SESSION['user_id'];

// Available cart items, with their seller
function load_cart(PDO $pdo, int $buyer_id, bool $lock = false): array {
    $stmt = $pdo->prepare('
        SELECT
            c.id           AS cart_id,
            c.quantity,
            fi.id          AS food_item_id,
            fi.name,
            fi.price,
            fi.image_path,
            cat.name       AS category,
            u.id           AS seller_id,
            u.email        AS seller_email,
            u.first_name   AS seller_first,
            u.last_name    AS seller_last,
            u.kitchen_name AS seller_kitchen
        FROM cart c
        JOIN food_items  fi  ON fi.id  = c.food_item_id AND fi.is_available = 1 AND fi.deleted_at IS NULL
        JOIN categories  cat ON cat.id = fi.category_id
        JOIN users       u   ON u.id   = fi.seller_id
        WHERE c.buyer_id = ?
        ORDER BY u.id, c.id
    ' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$buyer_id]);
    return $stmt->fetchAll();
}

$cart_items = load_cart($pdo, $buyer_id);

if (empty($cart_items)) {
    flash('error', 'Your cart is empty or contains only unavailable items.');
    redirect('buyer/cart.php');
}

$total   = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cart_items));
$sellers = [];
foreach ($cart_items as $ci) {
    $sellers[$ci['seller_id']] = seller_display_name($ci);
}

// Delivery details: prefill from the last order, or from the account
$stmt = $pdo->prepare('
    SELECT delivery_name, delivery_phone, delivery_address
    FROM orders WHERE buyer_id = ? AND delivery_address IS NOT NULL
    ORDER BY id DESC LIMIT 1
');
$stmt->execute([$buyer_id]);
$last = $stmt->fetch() ?: [];
$delivery = [
    'name'    => $last['delivery_name']    ?? ($_SESSION['first_name'] . ' ' . $_SESSION['last_name']),
    'phone'   => $last['delivery_phone']   ?? '',
    'address' => $last['delivery_address'] ?? '',
    'note'    => '',
];
$errors = [];

//  POST — place the order(s)
if (isset($_POST['confirm_order'])) {
    $delivery = [
        'name'    => trim($_POST['delivery_name']    ?? ''),
        'phone'   => trim($_POST['delivery_phone']   ?? ''),
        'address' => trim($_POST['delivery_address'] ?? ''),
        'note'    => trim($_POST['delivery_note']    ?? ''),
    ];
    if ($delivery['name'] === '' || mb_strlen($delivery['name']) > 200) {
        $errors['delivery_name'] = 'Please enter the name for delivery.';
    }
    if (!preg_match('/^\+?[0-9 ()-]{8,20}$/', $delivery['phone'])) {
        $errors['delivery_phone'] = 'Please enter a valid phone number so the seller can call you.';
    }
    if (mb_strlen($delivery['address']) < 8 || mb_strlen($delivery['address']) > 255) {
        $errors['delivery_address'] = 'Please enter the full delivery address (building, room or street).';
    }
    if (mb_strlen($delivery['note']) > 255) {
        $errors['delivery_note'] = 'Note must be under 255 characters.';
    }

    if (empty($errors)) {
        $placed = [];
        $pdo->beginTransaction();
        try {
            // Read the cart again inside the transaction, so the prices and
            // availability saved are exactly what is in the database now.
            $items = load_cart($pdo, $buyer_id, true);
            if (!$items) {
                throw new RuntimeException('empty cart');
            }
            $by_seller = [];
            foreach ($items as $ci) {
                $by_seller[$ci['seller_id']][] = $ci;
            }

            $insert_order = $pdo->prepare("
                INSERT INTO orders (buyer_id, seller_id, total_amount, status, payment_method,
                                    delivery_name, delivery_phone, delivery_address, delivery_note)
                VALUES (?, ?, ?, 'pending', 'cod', ?, ?, ?, ?)
            ");
            $insert_item = $pdo->prepare('
                INSERT INTO order_items (order_id, food_item_id, quantity, price_at_purchase)
                VALUES (?, ?, ?, ?)
            ');

            // One order per seller: each seller delivers and collects the cash for their own food.
            foreach ($by_seller as $seller_id => $lines) {
                $subtotal = array_sum(array_map(fn($l) => $l['price'] * $l['quantity'], $lines));
                $insert_order->execute([
                    $buyer_id, $seller_id, number_format($subtotal, 2, '.', ''),
                    $delivery['name'], $delivery['phone'], $delivery['address'],
                    $delivery['note'] !== '' ? $delivery['note'] : null,
                ]);
                $order_id = (int)$pdo->lastInsertId();
                foreach ($lines as $l) {
                    $insert_item->execute([$order_id, $l['food_item_id'], $l['quantity'], $l['price']]);
                }
                $placed[] = ['id' => $order_id, 'total' => $subtotal, 'lines' => $lines];
            }

            $pdo->prepare('DELETE FROM cart WHERE buyer_id = ?')->execute([$buyer_id]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('checkout failed: ' . $e->getMessage());
            $placed = [];
            $errors['general'] = 'Something went wrong placing your order. Please try again.';
        }

        if ($placed) {
            // Tell each seller about their new order (a mail failure is only logged).
            foreach ($placed as $p) {
                $first = $p['lines'][0];
                $list  = implode("\n", array_map(fn($l) => "  {$l['quantity']} x {$l['name']}", $p['lines']));
                send_mail(
                    $first['seller_email'],
                    'New Yum-Yum order #' . $p['id'],
                    "Hi {$first['seller_first']},\n\nYou have a new cash-on-delivery order #{$p['id']}:\n$list\n\n"
                    . 'Cash to collect: $' . number_format($p['total'], 2) . "\n"
                    . "Deliver to: {$delivery['name']}, {$delivery['address']} (phone {$delivery['phone']})\n\n"
                    . 'Manage it here: ' . APP_URL . "/seller/orders.php\n\nYum-Yum"
                );
            }
            $ids = implode(', ', array_map(fn($p) => '#' . $p['id'], $placed));
            flash('success', count($placed) > 1
                ? "Your orders $ids have been placed, one for each seller. Please pay each seller in cash when your food arrives."
                : "Your order $ids has been placed. Please pay in cash when your food arrives.");
            redirect('buyer/orders.php');
        }
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
        Check your order and delivery details. You pay in cash when the food arrives.
    </p>

    <?php if (!empty($errors)): ?>
        <div style="padding:0.875rem 1.25rem;border-radius:var(--radius-lg);background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);color:#dc2626;font-size:0.9375rem;font-weight:600;margin-bottom:1.5rem;display:flex;align-items:center;gap:0.625rem;">
            <span class="material-symbols-outlined" style="font-size:1.1rem;font-variation-settings:'FILL' 1;">error</span>
            <?= e($errors['general'] ?? 'Please check your delivery details below.') ?>
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
                                    <?= e(seller_display_name($ci)) ?>
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

            <!-- Delivery details (fields belong to the checkout form) -->
            <div class="checkout-box">
                <h3 class="checkout-box__title">Delivery Details</h3>
                <div class="checkout-fields">
                    <label class="checkout-field">
                        <span>Name for delivery *</span>
                        <input form="checkout-form" type="text" name="delivery_name" maxlength="200" required
                               value="<?= e($delivery['name']) ?>" autocomplete="name"
                               class="<?= isset($errors['delivery_name']) ? 'has-error' : '' ?>" />
                        <?php if (isset($errors['delivery_name'])): ?><small><?= e($errors['delivery_name']) ?></small><?php endif; ?>
                    </label>
                    <label class="checkout-field">
                        <span>Phone number *</span>
                        <input form="checkout-form" type="tel" name="delivery_phone" maxlength="20" required
                               value="<?= e($delivery['phone']) ?>" autocomplete="tel" placeholder="04xx xxx xxx"
                               class="<?= isset($errors['delivery_phone']) ? 'has-error' : '' ?>" />
                        <?php if (isset($errors['delivery_phone'])): ?><small><?= e($errors['delivery_phone']) ?></small><?php endif; ?>
                    </label>
                    <label class="checkout-field checkout-field--wide">
                        <span>Delivery address *</span>
                        <input form="checkout-form" type="text" name="delivery_address" maxlength="255" required
                               value="<?= e($delivery['address']) ?>" autocomplete="street-address"
                               placeholder="e.g. Room 214, Block C, University Halls"
                               class="<?= isset($errors['delivery_address']) ? 'has-error' : '' ?>" />
                        <?php if (isset($errors['delivery_address'])): ?><small><?= e($errors['delivery_address']) ?></small><?php endif; ?>
                    </label>
                    <label class="checkout-field checkout-field--wide">
                        <span>Note for the seller (optional)</span>
                        <input form="checkout-form" type="text" name="delivery_note" maxlength="255"
                               value="<?= e($delivery['note']) ?>" placeholder="e.g. less spicy, call when outside" />
                        <?php if (isset($errors['delivery_note'])): ?><small><?= e($errors['delivery_note']) ?></small><?php endif; ?>
                    </label>
                </div>
                <?php if (count($sellers) > 1): ?>
                    <p class="checkout-hint">
                        <span class="material-symbols-outlined">info</span>
                        Your cart has food from <?= count($sellers) ?> sellers (<?= e(implode(', ', $sellers)) ?>).
                        Each seller gets a separate order, delivers their own food and collects their own cash.
                    </p>
                <?php endif; ?>
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

                <div style="display:flex;justify-content:space-between;font-size:0.875rem;color:var(--sage);font-weight:700;margin:-0.75rem 0 1.25rem;">
                    <span>Payment</span>
                    <span style="color:var(--earth);">Cash on delivery</span>
                </div>

                <!-- Confirm order form -->
                <form method="POST" action="" style="margin:0;" id="checkout-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="confirm_order" value="1" />
                    <button
                        type="submit"
                        id="checkout-btn"
                        style="width:100%;display:flex;align-items:center;justify-content:center;gap:0.625rem;background:var(--primary);color:var(--earth);padding:1rem;border-radius:var(--radius-xl);border:none;font-family:var(--font-body);font-size:1rem;font-weight:800;cursor:pointer;box-shadow:0 4px 14px rgba(244,209,37,0.35);transition:box-shadow 0.25s ease,transform 0.15s ease;"
                    >
                        <span class="material-symbols-outlined">check_circle</span>
                        Place Order
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
                    <span class="material-symbols-outlined" style="font-size:1rem;font-variation-settings:'FILL' 1;color:#16a34a;">payments</span>
                    Pay in cash when your food arrives
                </div>
                <div style="display:flex;align-items:center;gap:0.625rem;font-size:0.8125rem;font-weight:600;color:var(--sage);">
                    <span class="material-symbols-outlined" style="font-size:1rem;font-variation-settings:'FILL' 1;color:#16a34a;">replay</span>
                    Cancel free while your order is pending
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