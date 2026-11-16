<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-guard.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/mailer.php';
require_role('seller');

$page_title = 'My Orders';
$active_nav = 'orders';
$extra_css  = ['seller.css', 'pages.css'];

$seller_id = (int)$_SESSION['user_id'];

// ── Actions (POST) ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $back     = 'seller/orders.php' . (isset($_POST['filter']) && isset(ORDER_STATUSES[$_POST['filter']]) ? '?status=' . $_POST['filter'] : '');

    // The order must belong to this seller
    $stmt = $pdo->prepare('
        SELECT o.*, u.email AS buyer_email, u.first_name AS buyer_first
        FROM orders o JOIN users u ON u.id = o.buyer_id
        WHERE o.id = ? AND o.seller_id = ?
    ');
    $stmt->execute([$order_id, $seller_id]);
    $order = $stmt->fetch();

    if (!$order) {
        flash('error', 'Order not found.');
        redirect($back);
    }

    if (isset($_POST['update_status'])) {
        $new_status = (string)($_POST['new_status'] ?? '');
        if (!seller_can_move($order['status'], $new_status)) {
            flash('error', 'Order #' . $order_id . ' cannot move from ' . ORDER_STATUSES[$order['status']] . ' to ' . (ORDER_STATUSES[$new_status] ?? 'that status') . '.');
            redirect($back);
        }
        // Conditional update so two clicks at once cannot skip a step
        $stmt = $pdo->prepare('
            UPDATE orders
            SET status = ?, cancelled_by = IF(? = \'cancelled\', \'seller\', cancelled_by)
            WHERE id = ? AND seller_id = ? AND status = ?
        ');
        $stmt->execute([$new_status, $new_status, $order_id, $seller_id, $order['status']]);

        if ($stmt->rowCount() === 1) {
            send_mail(
                $order['buyer_email'],
                'Your Yum-Yum order #' . $order_id . ' is ' . strtolower(ORDER_STATUSES[$new_status]),
                "Hi {$order['buyer_first']},\n\nYour order #$order_id is now: " . ORDER_STATUSES[$new_status] . ".\n\n"
                . ($new_status === 'out_for_delivery'
                    ? "Please have $" . number_format((float)$order['total_amount'], 2) . " ready in cash, and confirm in My Orders once you receive the food.\n\n"
                    : '')
                . "View your orders: " . APP_URL . "/buyer/orders.php\n\nYum-Yum"
            );
            flash('success', 'Order #' . $order_id . ' is now ' . ORDER_STATUSES[$new_status] . '.');
        } else {
            flash('error', 'Order #' . $order_id . ' was changed by someone else. Please check it again.');
        }
        redirect($back);
    }

    if (isset($_POST['mark_paid'])) {
        if (!seller_can_mark_paid($order)) {
            flash('error', 'Cash can be marked as received once the order is out for delivery.');
            redirect($back);
        }
        $pdo->prepare("
            UPDATE orders SET payment_status = 'paid', paid_at = NOW()
            WHERE id = ? AND seller_id = ? AND payment_status = 'unpaid'
        ")->execute([$order_id, $seller_id]);
        flash('success', 'Cash received for order #' . $order_id . ' ($' . number_format((float)$order['total_amount'], 2) . ').');
        redirect($back);
    }

    redirect($back);
}

// ── List ────────────────────────────────────────────────────────────────
$flash = take_flash();

$filter_status = (string)($_GET['status'] ?? 'all');
if ($filter_status !== 'all' && $filter_status !== 'unpaid' && !isset(ORDER_STATUSES[$filter_status])) {
    $filter_status = 'all';
}

$where  = 'WHERE o.seller_id = ?';
$params = [$seller_id];
if ($filter_status === 'unpaid') {
    $where .= " AND o.payment_status = 'unpaid' AND o.status <> 'cancelled'";
} elseif ($filter_status !== 'all') {
    $where   .= ' AND o.status = ?';
    $params[] = $filter_status;
}

$stmt = $pdo->prepare("
    SELECT o.*, u.first_name AS buyer_first, u.last_name AS buyer_last, u.email AS buyer_email
    FROM orders o
    JOIN users u ON u.id = o.buyer_id
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
               fi.name AS food_name, fi.image_path AS food_image
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

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'pending'), 0) AS pending,
        COALESCE(SUM(status = 'delivered' AND payment_status = 'paid'), 0) AS completed,
        COALESCE(SUM(CASE WHEN payment_status = 'unpaid' AND status <> 'cancelled' THEN total_amount END), 0) AS to_collect
    FROM orders WHERE seller_id = ?
");
$stmt->execute([$seller_id]);
$stats = $stmt->fetch();

$tab_labels = ['all' => 'All'] + ORDER_STATUSES + ['unpaid' => 'Cash to collect'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="seller-page">

    <div class="seller-page__header">
        <div>
            <h1 class="seller-page__title">Orders</h1>
            <p class="seller-page__subtitle">Cash on delivery. Move each order forward, and mark the cash as received when the buyer pays.</p>
        </div>
        <a href="<?= BASE_PATH ?>seller/dashboard.php" class="seller-btn-secondary">
            <span class="material-symbols-outlined">arrow_back</span>
            Dashboard
        </a>
    </div>

    <?php if ($flash): ?>
        <div class="seller-alert seller-alert--<?= e($flash['type']) ?>">
            <span class="material-symbols-outlined"><?= $flash['type'] === 'success' ? 'check_circle' : 'error' ?></span>
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="seller-stats" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:2rem;">
        <div class="seller-stat-card">
            <div class="seller-stat-card__icon seller-stat-card__icon--blue"><span class="material-symbols-outlined">receipt_long</span></div>
            <div class="seller-stat-card__value"><?= (int)$stats['total'] ?></div>
            <div class="seller-stat-card__label">Total Orders</div>
        </div>
        <div class="seller-stat-card">
            <div class="seller-stat-card__icon seller-stat-card__icon--yellow"><span class="material-symbols-outlined">pending</span></div>
            <div class="seller-stat-card__value"><?= (int)$stats['pending'] ?></div>
            <div class="seller-stat-card__label">New (Pending)</div>
        </div>
        <div class="seller-stat-card">
            <div class="seller-stat-card__icon seller-stat-card__icon--green"><span class="material-symbols-outlined">task_alt</span></div>
            <div class="seller-stat-card__value"><?= (int)$stats['completed'] ?></div>
            <div class="seller-stat-card__label">Completed</div>
        </div>
        <div class="seller-stat-card">
            <div class="seller-stat-card__icon seller-stat-card__icon--red"><span class="material-symbols-outlined">payments</span></div>
            <div class="seller-stat-card__value">$<?= number_format((float)$stats['to_collect'], 2) ?></div>
            <div class="seller-stat-card__label">Cash to Collect</div>
        </div>
    </div>

    <div class="order-tabs">
        <?php foreach ($tab_labels as $val => $label): ?>
            <a href="?status=<?= e($val) ?>" class="order-tab <?= $filter_status === $val ? 'order-tab--active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (!$orders): ?>
        <div class="seller-table-wrapper">
            <div class="seller-empty">
                <span class="material-symbols-outlined">receipt_long</span>
                <p class="seller-empty__title"><?= $filter_status !== 'all' ? 'No orders here' : 'No orders yet' ?></p>
                <p class="seller-empty__sub"><?= $filter_status !== 'all' ? 'Try a different filter above.' : 'Orders will appear here once buyers start ordering your food.' ?></p>
            </div>
        </div>
    <?php else: ?>

        <div class="order-list">
            <?php foreach ($orders as $order):
                $items = $order_items_map[$order['id']] ?? [];
                $next  = SELLER_TRANSITIONS[$order['status']];
            ?>
                <article class="order-card" id="order-<?= (int)$order['id'] ?>">
                    <header class="order-card__head">
                        <div class="order-card__meta">
                            <div><span class="order-card__label">Order</span><strong>#<?= (int)$order['id'] ?></strong></div>
                            <div><span class="order-card__label">Placed</span><?= e(date('M j, Y · g:i A', strtotime($order['created_at']))) ?></div>
                            <div><span class="order-card__label">Total (cash)</span><strong>$<?= number_format((float)$order['total_amount'], 2) ?></strong></div>
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
                                    <div class="order-line__name"><?= e($oi['food_name']) ?> <span>× <?= (int)$oi['quantity'] ?></span></div>
                                    <div class="order-line__price">$<?= number_format($oi['quantity'] * $oi['price_at_purchase'], 2) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="order-card__delivery">
                            <span class="order-card__label">Deliver to</span>
                            <strong><?= e($order['delivery_name'] ?: $order['buyer_first'] . ' ' . $order['buyer_last']) ?></strong>
                            <?php if ($order['delivery_phone']): ?>
                                <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $order['delivery_phone'])) ?>"><?= e($order['delivery_phone']) ?></a>
                            <?php endif; ?>
                            <?php if ($order['delivery_address']): ?><span><?= e($order['delivery_address']) ?></span><?php endif; ?>
                            <?php if ($order['delivery_note']): ?><em>Note: <?= e($order['delivery_note']) ?></em><?php endif; ?>
                            <span class="order-card__muted"><?= e($order['buyer_email']) ?></span>
                        </div>
                    </div>

                    <footer class="order-card__foot">
                        <div class="order-card__status-text">
                            <?php if (order_is_complete($order)): ?>
                                <span class="order-ok"><span class="material-symbols-outlined">task_alt</span> Completed: delivered and paid</span>
                            <?php elseif ($order['status'] === 'cancelled'): ?>
                                <span class="order-bad"><span class="material-symbols-outlined">cancel</span> Cancelled by <?= e($order['cancelled_by'] ?? 'seller') ?></span>
                            <?php else: ?>
                                <?php if ($order['received_at']): ?>
                                    <span class="order-ok"><span class="material-symbols-outlined">inventory</span> Buyer confirmed received</span>
                                <?php endif; ?>
                                <?php if ($order['payment_status'] === 'paid'): ?>
                                    <span class="order-ok"><span class="material-symbols-outlined">payments</span> Cash received <?= e(date('M j, g:i A', strtotime($order['paid_at']))) ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <div class="order-card__actions">
                            <?php if ($next): ?>
                                <form method="POST" action="" class="order-inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>" />
                                    <input type="hidden" name="filter" value="<?= e($filter_status) ?>" />
                                    <select name="new_status" class="order-select" aria-label="Next status for order <?= (int)$order['id'] ?>">
                                        <?php foreach ($next as $s): ?>
                                            <option value="<?= e($s) ?>"><?= $s === 'cancelled' ? 'Cancel order' : 'Mark as ' . e(ORDER_STATUSES[$s]) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="update_status" value="1" class="seller-btn-primary order-btn">Update</button>
                                </form>
                            <?php endif; ?>

                            <?php if (seller_can_mark_paid($order)): ?>
                                <form method="POST" action="" class="order-inline-form"
                                      data-confirm="Confirm you received $<?= number_format((float)$order['total_amount'], 2) ?> in cash for order #<?= (int)$order['id'] ?>?"
                                      onsubmit="return confirm(this.dataset.confirm);">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>" />
                                    <input type="hidden" name="filter" value="<?= e($filter_status) ?>" />
                                    <button type="submit" name="mark_paid" value="1" class="order-btn order-btn--paid">
                                        <span class="material-symbols-outlined">payments</span> Cash received
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
