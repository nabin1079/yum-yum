<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-guard.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/mailer.php';
require_role('buyer');

$page_title = 'My Orders';
$active_nav = 'orders';
$extra_css  = ['pages.css'];

$buyer_id = (int)$_SESSION['user_id'];

// ── Actions (POST): cancel a pending order, confirm the food was received ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)($_POST['order_id'] ?? 0);

    $stmt = $pdo->prepare('
        SELECT o.*, s.email AS seller_email, s.first_name AS seller_first
        FROM orders o JOIN users s ON s.id = o.seller_id
        WHERE o.id = ? AND o.buyer_id = ?
    ');
    $stmt->execute([$order_id, $buyer_id]);
    $order = $stmt->fetch();

    if (!$order) {
        flash('error', 'Order not found.');
        redirect('buyer/orders.php');
    }

    if (isset($_POST['cancel_order'])) {
        if (!buyer_can_cancel($order)) {
            flash('error', 'Order #' . $order_id . ' can no longer be cancelled because the seller has already accepted it. Please contact the seller.');
            redirect('buyer/orders.php');
        }
        $stmt = $pdo->prepare("
            UPDATE orders SET status = 'cancelled', cancelled_by = 'buyer'
            WHERE id = ? AND buyer_id = ? AND status = 'pending'
        ");
        $stmt->execute([$order_id, $buyer_id]);
        if ($stmt->rowCount() === 1) {
            send_mail(
                $order['seller_email'],
                'Order #' . $order_id . ' was cancelled by the buyer',
                "Hi {$order['seller_first']},\n\nThe buyer cancelled order #$order_id before you accepted it. No action is needed.\n\nYum-Yum"
            );
            flash('success', 'Order #' . $order_id . ' has been cancelled.');
        } else {
            flash('error', 'The seller accepted order #' . $order_id . ' just now, so it can no longer be cancelled.');
        }
        redirect('buyer/orders.php');
    }

    if (isset($_POST['confirm_received'])) {
        if (!buyer_can_confirm_received($order)) {
            flash('error', 'You can confirm delivery once the order is out for delivery.');
            redirect('buyer/orders.php');
        }
        $pdo->prepare("
            UPDATE orders SET status = 'delivered', received_at = NOW()
            WHERE id = ? AND buyer_id = ? AND status IN ('out_for_delivery', 'delivered') AND received_at IS NULL
        ")->execute([$order_id, $buyer_id]);
        flash('success', 'Thanks! Order #' . $order_id . ' is marked as received.'
            . ($order['payment_status'] === 'unpaid' ? ' Please make sure you have paid the seller in cash.' : ''));
        redirect('buyer/orders.php');
    }

    redirect('buyer/orders.php');
}

// ── List ────────────────────────────────────────────────────────────────
$flash = take_flash();

$filter = (string)($_GET['status'] ?? 'all');
if ($filter !== 'all' && !isset(ORDER_STATUSES[$filter])) {
    $filter = 'all';
}

$where  = 'WHERE o.buyer_id = ?';
$params = [$buyer_id];
if ($filter !== 'all') {
    $where   .= ' AND o.status = ?';
    $params[] = $filter;
}

$stmt = $pdo->prepare("
    SELECT o.*,
           s.id AS seller_id, s.first_name AS seller_first, s.last_name AS seller_last,
           s.kitchen_name AS seller_kitchen, s.phone AS seller_phone
    FROM orders o
    JOIN users s ON s.id = o.seller_id
    $where
    ORDER BY o.created_at DESC
    LIMIT 200
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$order_items_map = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT oi.order_id, oi.quantity, oi.price_at_purchase,
               fi.id AS food_item_id, fi.name AS food_name, fi.image_path AS food_image,
               (fi.is_available = 1 AND fi.deleted_at IS NULL) AS can_reorder
        FROM order_items oi
        JOIN food_items fi ON fi.id = oi.food_item_id
        WHERE oi.order_id IN ($in)
        ORDER BY oi.id
    ");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $order_items_map[$row['order_id']][] = $row;
    }
}

$tabs = ['all' => 'All'] + ORDER_STATUSES;

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:920px;margin:0 auto;padding:2.5rem 1.5rem 4rem;">

    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:2rem;">
        <div>
            <h1 style="font-size:1.875rem;font-weight:800;color:var(--earth);letter-spacing:-0.03em;margin-bottom:0.25rem;">My Orders</h1>
            <p style="color:var(--sage);font-size:0.9375rem;font-weight:500;">
                <?= count($orders) ?> order<?= count($orders) !== 1 ? 's' : '' ?>
                <?= $filter !== 'all' ? '· ' . e(ORDER_STATUSES[$filter]) : '' ?>
                · All orders are cash on delivery
            </p>
        </div>
        <a href="<?= BASE_PATH ?>buyer/menu.php" class="order-cta">
            <span class="material-symbols-outlined" style="font-size:1rem;">add</span>
            Order Again
        </a>
    </div>

    <?php if ($flash): ?>
        <div class="page-alert page-alert--<?= e($flash['type']) ?>">
            <span class="material-symbols-outlined"><?= $flash['type'] === 'success' ? 'check_circle' : 'error' ?></span>
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="order-tabs">
        <?php foreach ($tabs as $val => $label): ?>
            <a href="?status=<?= e($val) ?>" class="order-tab <?= $filter === $val ? 'order-tab--active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (!$orders): ?>
        <div style="text-align:center;padding:5rem 1rem;color:var(--sage);">
            <span class="material-symbols-outlined" style="font-size:3.5rem;display:block;margin-bottom:1rem;opacity:0.3;">receipt_long</span>
            <p style="font-size:1.125rem;font-weight:700;color:var(--earth);margin-bottom:0.5rem;">
                <?= $filter !== 'all' ? 'No ' . e($tabs[$filter]) . ' orders' : 'No orders yet' ?>
            </p>
            <p style="font-size:0.9375rem;margin-bottom:2rem;">
                <?= $filter !== 'all' ? 'Try a different filter above.' : 'Browse the menu and place your first order!' ?>
            </p>
            <?php if ($filter === 'all'): ?>
                <a href="<?= BASE_PATH ?>buyer/menu.php" class="order-cta">
                    <span class="material-symbols-outlined">restaurant_menu</span>
                    Browse Menu
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>

        <div class="order-list">
            <?php foreach ($orders as $order):
                $items = $order_items_map[$order['id']] ?? [];
                $seller_name = seller_display_name($order);
            ?>
                <article class="order-card" id="order-<?= (int)$order['id'] ?>">
                    <header class="order-card__head">
                        <div class="order-card__meta">
                            <div><span class="order-card__label">Order</span><strong>#<?= (int)$order['id'] ?></strong></div>
                            <div><span class="order-card__label">Placed</span><?= e(date('M j, Y · g:i A', strtotime($order['created_at']))) ?></div>
                            <div><span class="order-card__label">Total (cash)</span><strong>$<?= number_format((float)$order['total_amount'], 2) ?></strong></div>
                            <div>
                                <span class="order-card__label">Seller</span>
                                <a href="<?= BASE_PATH ?>seller.php?id=<?= (int)$order['seller_id'] ?>" class="order-card__seller"><?= e($seller_name) ?></a>
                            </div>
                        </div>
                        <div class="order-card__badges">
                            <?= status_badge($order['status']) ?>
                            <?= payment_badge($order) ?>
                        </div>
                    </header>

                    <div class="order-card__body">
                        <div class="order-card__items">
                            <?php foreach ($items as $oi): ?>
                                <div class="order-line">
                                    <?php if ($oi['food_image']): ?>
                                        <img class="order-line__img" src="<?= BASE_PATH ?>uploads/food/<?= e($oi['food_image']) ?>" alt="" />
                                    <?php else: ?>
                                        <div class="order-line__img order-line__img--empty"><span class="material-symbols-outlined">restaurant</span></div>
                                    <?php endif; ?>
                                    <div class="order-line__name">
                                        <?= e($oi['food_name']) ?>
                                        <span>× <?= (int)$oi['quantity'] ?> · $<?= number_format((float)$oi['price_at_purchase'], 2) ?> each</span>
                                    </div>
                                    <div class="order-line__price">$<?= number_format($oi['quantity'] * $oi['price_at_purchase'], 2) ?></div>
                                    <?php if ($oi['can_reorder']): ?>
                                        <form method="POST" action="<?= BASE_PATH ?>buyer/cart_add.php" class="order-inline-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="food_item_id" value="<?= (int)$oi['food_item_id'] ?>" />
                                            <input type="hidden" name="quantity" value="<?= (int)$oi['quantity'] ?>" />
                                            <input type="hidden" name="redirect" value="cart" />
                                            <button type="submit" class="order-btn order-btn--ghost">
                                                <span class="material-symbols-outlined">replay</span> Reorder
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($order['delivery_address']): ?>
                            <div class="order-card__delivery">
                                <span class="order-card__label">Delivering to</span>
                                <strong><?= e($order['delivery_name']) ?></strong>
                                <span><?= e($order['delivery_phone']) ?></span>
                                <span><?= e($order['delivery_address']) ?></span>
                                <?php if ($order['delivery_note']): ?><em>Note: <?= e($order['delivery_note']) ?></em><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <footer class="order-card__foot">
                        <div class="order-card__status-text">
                            <?php if (order_is_complete($order) && $order['received_at']): ?>
                                <span class="order-ok"><span class="material-symbols-outlined">task_alt</span> Completed: received and paid</span>
                            <?php elseif ($order['status'] === 'cancelled'): ?>
                                <span class="order-bad"><span class="material-symbols-outlined">cancel</span> Cancelled<?= $order['cancelled_by'] === 'buyer' ? ' by you' : ' by the seller' ?></span>
                            <?php elseif ($order['status'] === 'pending'): ?>
                                <span>Waiting for the seller to accept your order.</span>
                            <?php elseif ($order['status'] === 'out_for_delivery'): ?>
                                <span>On its way. Please have $<?= number_format((float)$order['total_amount'], 2) ?> ready in cash.</span>
                            <?php elseif ($order['status'] === 'delivered' && !$order['received_at']): ?>
                                <span>The seller marked this as delivered. Please confirm you received it.</span>
                            <?php elseif ($order['received_at'] && $order['payment_status'] === 'unpaid'): ?>
                                <span>Received. Waiting for the seller to confirm your cash payment.</span>
                            <?php else: ?>
                                <span>Your seller is preparing this order.</span>
                            <?php endif; ?>
                            <?php if ($order['seller_phone'] && !in_array($order['status'], ['cancelled'], true) && !order_is_complete($order)): ?>
                                <span class="order-card__muted">Seller phone: <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $order['seller_phone'])) ?>"><?= e($order['seller_phone']) ?></a></span>
                            <?php endif; ?>
                        </div>

                        <div class="order-card__actions">
                            <?php if (buyer_can_confirm_received($order)): ?>
                                <form method="POST" action="" class="order-inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>" />
                                    <button type="submit" name="confirm_received" value="1" class="order-btn order-btn--paid">
                                        <span class="material-symbols-outlined">inventory</span> I received my food
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if (buyer_can_cancel($order)): ?>
                                <form method="POST" action="" class="order-inline-form"
                                      data-confirm="Cancel order #<?= (int)$order['id'] ?>?" onsubmit="return confirm(this.dataset.confirm);">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>" />
                                    <button type="submit" name="cancel_order" value="1" class="order-btn order-btn--danger">
                                        <span class="material-symbols-outlined">close</span> Cancel order
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
