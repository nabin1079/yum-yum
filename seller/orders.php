<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-guard.php';
require_role('seller');

$page_title = 'My Orders';
$active_nav = 'orders';
$extra_css  = ['seller.css'];

$seller_id = $_SESSION['user_id'];

//  Flash message 
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Handle status update (POST) 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id   = (int)($_POST['order_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';

    $allowed_statuses = ['pending','confirmed','preparing','out_for_delivery','delivered','cancelled'];

    if ($order_id > 0 && in_array($new_status, $allowed_statuses)) {
        // Verify this order actually contains this seller's items
        $stmt = $pdo->prepare('
            SELECT COUNT(*) FROM order_items oi
            JOIN food_items fi ON fi.id = oi.food_item_id
            WHERE oi.order_id = ? AND fi.seller_id = ?
        ');
        $stmt->execute([$order_id, $seller_id]);

        if ((int)$stmt->fetchColumn() > 0) {
            $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
            $stmt->execute([$new_status, $order_id]);

            $_SESSION['flash'] = [
                'type'    => 'success',
                'message' => 'Order #' . $order_id . ' status updated to ' . ucfirst(str_replace('_', ' ', $new_status)) . '.',
            ];
        }
    }

    header('Location: ' . BASE_PATH . 'seller/orders.php');
    exit;
}

//  Filter by status 
$filter_status = $_GET['status'] ?? 'all';
$allowed_statuses = ['all','pending','confirmed','preparing','out_for_delivery','delivered','cancelled'];
if (!in_array($filter_status, $allowed_statuses)) {
    $filter_status = 'all';
}

//  Fetch orders containing seller's items 
// Each order row includes buyer info, order totals, and status.
$where_clause = $filter_status !== 'all' ? 'AND o.status = ?' : '';

$sql = "
    SELECT DISTINCT
        o.id,
        o.status,
        o.total_amount,
        o.created_at,
        u.first_name AS buyer_first,
        u.last_name  AS buyer_last,
        u.email      AS buyer_email
    FROM orders o
    JOIN users       u  ON u.id  = o.buyer_id
    JOIN order_items oi ON oi.order_id = o.id
    JOIN food_items  fi ON fi.id = oi.food_item_id
    WHERE fi.seller_id = ?
    $where_clause
    ORDER BY o.created_at DESC
";

$params = [$seller_id];
if ($filter_status !== 'all') $params[] = $filter_status;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

//  For each order, fetch only THIS seller's items 
$order_items_map = [];
if (!empty($orders)) {
    $order_ids   = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));

    $stmt = $pdo->prepare("
        SELECT
            oi.order_id,
            oi.quantity,
            oi.price_at_purchase,
            fi.name       AS food_name,
            fi.image_path AS food_image
        FROM order_items oi
        JOIN food_items fi ON fi.id = oi.food_item_id
        WHERE oi.order_id IN ($placeholders)
          AND fi.seller_id = ?
        ORDER BY oi.id ASC
    ");
    $stmt->execute([...$order_ids, $seller_id]);

    foreach ($stmt->fetchAll() as $row) {
        $order_items_map[$row['order_id']][] = $row;
    }
}

//  Stats 
$stmt = $pdo->prepare("
    SELECT
        COUNT(DISTINCT o.id)                                        AS total,
        SUM(CASE WHEN o.status = 'pending'           THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN o.status = 'delivered'         THEN 1 ELSE 0 END) AS delivered,
        SUM(CASE WHEN o.status = 'cancelled'         THEN 1 ELSE 0 END) AS cancelled
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    JOIN food_items  fi ON fi.id = oi.food_item_id
    WHERE fi.seller_id = ?
");
$stmt->execute([$seller_id]);
$stats = $stmt->fetch();

//  Status helpers 
function status_badge(string $status): string {
    $map = [
        'pending'          => ['label' => 'Pending',          'color' => '#f97316', 'bg' => 'rgba(249,115,22,0.1)'],
        'confirmed'        => ['label' => 'Confirmed',        'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,0.1)'],
        'preparing'        => ['label' => 'Preparing',        'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,0.1)'],
        'out_for_delivery' => ['label' => 'Out for Delivery', 'color' => '#0ea5e9', 'bg' => 'rgba(14,165,233,0.1)'],
        'delivered'        => ['label' => 'Delivered',        'color' => '#22c55e', 'bg' => 'rgba(34,197,94,0.1)'],
        'cancelled'        => ['label' => 'Cancelled',        'color' => '#ef4444', 'bg' => 'rgba(239,68,68,0.1)'],
    ];
    $s = $map[$status] ?? ['label' => ucfirst($status), 'color' => '#6b7280', 'bg' => 'rgba(107,114,128,0.1)'];
    return sprintf(
        '<span style="display:inline-flex;align-items:center;gap:0.3rem;padding:0.25rem 0.75rem;border-radius:9999px;font-size:0.75rem;font-weight:700;background:%s;color:%s;">● %s</span>',
        $s['bg'], $s['color'], $s['label']
    );
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="seller-page">

    <!-- Page header -->
    <div class="seller-page__header">
        <div>
            <h1 class="seller-page__title">Orders</h1>
            <p class="seller-page__subtitle">Orders containing your food items.</p>
        </div>
        <a href="<?= BASE_PATH ?>seller/dashboard.php" class="seller-btn-secondary">
            <span class="material-symbols-outlined">arrow_back</span>
            Dashboard
        </a>
    </div>

    <!-- Flash -->
    <?php if ($flash): ?>
        <div class="seller-alert seller-alert--<?= $flash['type'] ?>">
            <span class="material-symbols-outlined">
                <?= $flash['type'] === 'success' ? 'check_circle' : 'error' ?>
            </span>
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="seller-stats" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr));margin-bottom:2rem;">
        <div class="seller-stat-card">
            <div class="seller-stat-card__icon seller-stat-card__icon--blue">
                <span class="material-symbols-outlined">receipt_long</span>
            </div>
            <div class="seller-stat-card__value"><?= (int)$stats['total'] ?></div>
            <div class="seller-stat-card__label">Total Orders</div>
        </div>
        <div class="seller-stat-card">
            <div class="seller-stat-card__icon seller-stat-card__icon--yellow">
                <span class="material-symbols-outlined">pending</span>
            </div>
            <div class="seller-stat-card__value"><?= (int)$stats['pending'] ?></div>
            <div class="seller-stat-card__label">Pending</div>
        </div>
        <div class="seller-stat-card">
            <div class="seller-stat-card__icon seller-stat-card__icon--green">
                <span class="material-symbols-outlined">check_circle</span>
            </div>
            <div class="seller-stat-card__value"><?= (int)$stats['delivered'] ?></div>
            <div class="seller-stat-card__label">Delivered</div>
        </div>
        <div class="seller-stat-card">
            <div class="seller-stat-card__icon seller-stat-card__icon--red">
                <span class="material-symbols-outlined">cancel</span>
            </div>
            <div class="seller-stat-card__value"><?= (int)$stats['cancelled'] ?></div>
            <div class="seller-stat-card__label">Cancelled</div>
        </div>
    </div>

    <!-- Status filter tabs -->
    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1.5rem;">
        <?php
        $tab_labels = [
            'all'              => 'All',
            'pending'          => 'Pending',
            'confirmed'        => 'Confirmed',
            'preparing'        => 'Preparing',
            'out_for_delivery' => 'Out for Delivery',
            'delivered'        => 'Delivered',
            'cancelled'        => 'Cancelled',
        ];
        foreach ($tab_labels as $val => $label):
            $is_active = $filter_status === $val;
        ?>
            <a
                href="?status=<?= $val ?>"
                style="
                    display:inline-block;
                    padding:0.4rem 1rem;
                    border-radius:9999px;
                    font-size:0.8125rem;
                    font-weight:700;
                    text-decoration:none;
                    border: 1.5px solid <?= $is_active ? 'var(--primary)' : 'var(--border-light)' ?>;
                    background: <?= $is_active ? 'var(--primary)' : 'var(--white)' ?>;
                    color: <?= $is_active ? 'var(--earth)' : 'var(--sage)' ?>;
                    transition: all 0.15s ease;
                "
            ><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Orders list -->
    <?php if (empty($orders)): ?>
        <div class="seller-table-wrapper">
            <div class="seller-empty">
                <span class="material-symbols-outlined">receipt_long</span>
                <p class="seller-empty__title">
                    <?= $filter_status !== 'all' ? 'No ' . $tab_labels[$filter_status] . ' orders' : 'No orders yet' ?>
                </p>
                <p class="seller-empty__sub">
                    <?= $filter_status !== 'all'
                        ? 'Try a different filter above.'
                        : 'Orders will appear here once buyers start purchasing your items.' ?>
                </p>
            </div>
        </div>

    <?php else: ?>

        <div style="display:flex;flex-direction:column;gap:1rem;">
            <?php foreach ($orders as $order):
                $items = $order_items_map[$order['id']] ?? [];
                $seller_subtotal = array_sum(array_map(fn($i) => $i['quantity'] * $i['price_at_purchase'], $items));
            ?>
                <div style="background:var(--white);border:1px solid var(--border-light);border-radius:var(--radius-2xl);overflow:hidden;box-shadow:var(--shadow-sm);">

                    <!-- Order header -->
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;padding:1.25rem 1.5rem;border-bottom:1px solid var(--border-light);background:var(--bg-light);">
                        <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;">
                            <div>
                                <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--sage);">Order</div>
                                <div style="font-size:1rem;font-weight:800;color:var(--earth);">#<?= $order['id'] ?></div>
                            </div>
                            <div>
                                <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--sage);">Buyer</div>
                                <div style="font-size:0.9375rem;font-weight:700;color:var(--earth);">
                                    <?= htmlspecialchars($order['buyer_first'] . ' ' . $order['buyer_last']) ?>
                                </div>
                                <div style="font-size:0.8125rem;color:var(--sage);">
                                    <?= htmlspecialchars($order['buyer_email']) ?>
                                </div>
                            </div>
                            <div>
                                <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--sage);">Date</div>
                                <div style="font-size:0.875rem;color:var(--earth);font-weight:600;">
                                    <?= date('M j, Y · g:i A', strtotime($order['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                            <?= status_badge($order['status']) ?>
                        </div>
                    </div>

                    <!-- Order items (seller's items only) -->
                    <div style="padding:1.25rem 1.5rem;">
                        <p style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--sage);margin-bottom:0.875rem;">
                            Your Items in This Order
                        </p>
                        <div style="display:flex;flex-direction:column;gap:0.625rem;">
                            <?php foreach ($items as $oi): ?>
                                <div style="display:flex;align-items:center;gap:0.875rem;">
                                    <!-- Thumbnail -->
                                    <?php if ($oi['food_image']): ?>
                                        <img
                                            src="<?= BASE_PATH ?>uploads/food/<?= htmlspecialchars($oi['food_image']) ?>"
                                            alt=""
                                            style="width:2.75rem;height:2.75rem;border-radius:var(--radius-lg);object-fit:cover;border:1px solid var(--border-light);flex-shrink:0;"
                                        />
                                    <?php else: ?>
                                        <div style="width:2.75rem;height:2.75rem;border-radius:var(--radius-lg);background:var(--primary-dim);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                            <span class="material-symbols-outlined" style="font-size:1.1rem;color:var(--sage);opacity:0.6;">restaurant</span>
                                        </div>
                                    <?php endif; ?>

                                    <div style="flex:1;">
                                        <span style="font-size:0.9375rem;font-weight:700;color:var(--earth);">
                                            <?= htmlspecialchars($oi['food_name']) ?>
                                        </span>
                                        <span style="font-size:0.875rem;color:var(--sage);margin-left:0.5rem;">
                                            × <?= $oi['quantity'] ?>
                                        </span>
                                    </div>
                                    <span style="font-size:0.9375rem;font-weight:800;color:var(--earth);">
                                        $<?= number_format($oi['quantity'] * $oi['price_at_purchase'], 2) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Subtotal + status update -->
                        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--border-light);">
                            <div style="font-size:0.9375rem;font-weight:700;color:var(--sage);">
                                Your subtotal:
                                <span style="color:var(--earth);font-size:1.0625rem;margin-left:0.375rem;">
                                    $<?= number_format($seller_subtotal, 2) ?>
                                </span>
                            </div>

                            <!-- Update status form -->
                            <form method="POST" action="" style="display:flex;align-items:center;gap:0.625rem;margin:0;">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>" />
                                <select
                                    name="new_status"
                                    style="padding:0.5rem 0.75rem;border:1.5px solid var(--border-light);border-radius:var(--radius-lg);font-family:var(--font-body);font-size:0.875rem;font-weight:600;color:var(--earth);background:var(--bg-light);outline:none;cursor:pointer;"
                                >
                                    <?php
                                    $statuses = ['pending','confirmed','preparing','out_for_delivery','delivered','cancelled'];
                                    foreach ($statuses as $s):
                                    ?>
                                        <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>>
                                            <?= ucfirst(str_replace('_', ' ', $s)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="update_status" class="seller-btn-primary" style="padding:0.5rem 1rem;font-size:0.875rem;">
                                    Update
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>