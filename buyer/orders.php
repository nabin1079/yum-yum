<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-guard.php';
require_role('buyer');

$page_title = 'My Orders';
$active_nav = 'orders';
$extra_css  = ['pages.css'];

$buyer_id = $_SESSION['user_id'];

//  Flash 
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

//  Filter by status 
$filter    = $_GET['status'] ?? 'all';
$allowed   = ['all','pending','confirmed','preparing','out_for_delivery','delivered','cancelled'];
if (!in_array($filter, $allowed)) $filter = 'all';

//  Fetch orders 
$where  = $filter !== 'all' ? 'AND o.status = ?' : '';
$params = [$buyer_id];
if ($filter !== 'all') $params[] = $filter;

$stmt = $pdo->prepare("
    SELECT
        o.id,
        o.total_amount,
        o.status,
        o.created_at,
        COUNT(oi.id)       AS item_count,
        SUM(oi.quantity)   AS total_qty
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    WHERE o.buyer_id = ?
    $where
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

//  Fetch all order items for these orders 
$order_items_map = [];
if (!empty($orders)) {
    $order_ids    = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));

    $stmt = $pdo->prepare("
        SELECT
            oi.order_id,
            oi.quantity,
            oi.price_at_purchase,
            fi.id         AS food_item_id,
            fi.name       AS food_name,
            fi.image_path AS food_image,
            fi.is_available,
            u.first_name  AS seller_first,
            u.last_name   AS seller_last
        FROM order_items oi
        JOIN food_items fi ON fi.id = oi.food_item_id
        JOIN users      u  ON u.id  = fi.seller_id
        WHERE oi.order_id IN ($placeholders)
        ORDER BY oi.id ASC
    ");
    $stmt->execute($order_ids);

    foreach ($stmt->fetchAll() as $row) {
        $order_items_map[$row['order_id']][] = $row;
    }
}

//  Status badge helper 
function status_badge(string $status): string {
    $map = [
        'pending'          => ['Pending',          '#f97316', 'rgba(249,115,22,0.1)'],
        'confirmed'        => ['Confirmed',        '#3b82f6', 'rgba(59,130,246,0.1)'],
        'preparing'        => ['Preparing',        '#8b5cf6', 'rgba(139,92,246,0.1)'],
        'out_for_delivery' => ['Out for Delivery', '#0ea5e9', 'rgba(14,165,233,0.1)'],
        'delivered'        => ['Delivered',        '#22c55e', 'rgba(34,197,94,0.1)'],
        'cancelled'        => ['Cancelled',        '#ef4444', 'rgba(239,68,68,0.1)'],
    ];
    [$label, $color, $bg] = $map[$status] ?? [ucfirst($status), '#6b7280', 'rgba(107,114,128,0.1)'];
    return "<span style='display:inline-flex;align-items:center;gap:0.3rem;padding:0.25rem 0.75rem;border-radius:9999px;font-size:0.75rem;font-weight:700;background:$bg;color:$color;'>● $label</span>";
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:900px;margin:0 auto;padding:2.5rem 1.5rem 4rem;">

    <!-- Heading -->
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:2rem;">
        <div>
            <h1 style="font-size:1.875rem;font-weight:800;color:var(--earth);letter-spacing:-0.03em;margin-bottom:0.25rem;">
                My Orders
            </h1>
            <p style="color:var(--sage);font-size:0.9375rem;font-weight:500;">
                <?= count($orders) ?> order<?= count($orders) !== 1 ? 's' : '' ?>
                <?= $filter !== 'all' ? '· ' . ucfirst(str_replace('_', ' ', $filter)) : '' ?>
            </p>
        </div>
        <a href="<?= BASE_PATH ?>buyer/menu.php"
           style="display:inline-flex;align-items:center;gap:0.5rem;background:var(--primary);color:var(--earth);padding:0.75rem 1.25rem;border-radius:var(--radius-xl);font-weight:800;font-size:0.875rem;text-decoration:none;box-shadow:0 4px 12px rgba(244,209,37,0.3);">
            <span class="material-symbols-outlined" style="font-size:1rem;">add</span>
            Order Again
        </a>
    </div>

    <!-- Flash -->
    <?php if ($flash): ?>
        <div style="
            padding:0.875rem 1.25rem;border-radius:var(--radius-lg);
            font-size:0.9375rem;font-weight:600;margin-bottom:1.5rem;
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

    <!-- Filter tabs -->
    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1.75rem;">
        <?php
        $tabs = [
            'all'              => 'All',
            'pending'          => 'Pending',
            'confirmed'        => 'Confirmed',
            'preparing'        => 'Preparing',
            'out_for_delivery' => 'Out for Delivery',
            'delivered'        => 'Delivered',
            'cancelled'        => 'Cancelled',
        ];
        foreach ($tabs as $val => $label):
            $active = $filter === $val;
        ?>
            <a href="?status=<?= $val ?>"
               style="display:inline-block;padding:0.4rem 1rem;border-radius:9999px;font-size:0.8125rem;font-weight:700;text-decoration:none;border:1.5px solid <?= $active ? 'var(--primary)' : 'var(--border-light)' ?>;background:<?= $active ? 'var(--primary)' : 'var(--white)' ?>;color:<?= $active ? 'var(--earth)' : 'var(--sage)' ?>;">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Orders -->
    <?php if (empty($orders)): ?>
        <div style="text-align:center;padding:5rem 1rem;color:var(--sage);">
            <span class="material-symbols-outlined" style="font-size:3.5rem;display:block;margin-bottom:1rem;opacity:0.3;">receipt_long</span>
            <p style="font-size:1.125rem;font-weight:700;color:var(--earth);margin-bottom:0.5rem;">
                <?= $filter !== 'all' ? 'No ' . $tabs[$filter] . ' orders' : 'No orders yet' ?>
            </p>
            <p style="font-size:0.9375rem;margin-bottom:2rem;">
                <?= $filter !== 'all' ? 'Try a different filter above.' : 'Browse the menu and place your first order!' ?>
            </p>
            <?php if ($filter === 'all'): ?>
                <a href="<?= BASE_PATH ?>buyer/menu.php"
                   style="display:inline-flex;align-items:center;gap:0.5rem;background:var(--primary);color:var(--earth);padding:0.875rem 2rem;border-radius:var(--radius-xl);font-weight:800;text-decoration:none;">
                    <span class="material-symbols-outlined">restaurant_menu</span>
                    Browse Menu
                </a>
            <?php endif; ?>
        </div>

    <?php else: ?>

        <div style="display:flex;flex-direction:column;gap:1.25rem;">
            <?php foreach ($orders as $order):
                $items = $order_items_map[$order['id']] ?? [];
            ?>
                <div style="background:var(--white);border:1px solid var(--border-light);border-radius:var(--radius-2xl);overflow:hidden;box-shadow:var(--shadow-sm);">

                    <!-- Order header -->
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;padding:1.1rem 1.5rem;border-bottom:1px solid var(--border-light);background:var(--bg-light);">
                        <div style="display:flex;align-items:center;gap:2rem;flex-wrap:wrap;">
                            <div>
                                <div style="font-size:0.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--sage);">Order</div>
                                <div style="font-size:1rem;font-weight:800;color:var(--earth);">#<?= $order['id'] ?></div>
                            </div>
                            <div>
                                <div style="font-size:0.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--sage);">Date</div>
                                <div style="font-size:0.875rem;font-weight:700;color:var(--earth);">
                                    <?= date('M j, Y · g:i A', strtotime($order['created_at'])) ?>
                                </div>
                            </div>
                            <div>
                                <div style="font-size:0.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--sage);">Items</div>
                                <div style="font-size:0.875rem;font-weight:700;color:var(--earth);">
                                    <?= $order['total_qty'] ?> item<?= $order['total_qty'] != 1 ? 's' : '' ?>
                                </div>
                            </div>
                            <div>
                                <div style="font-size:0.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--sage);">Total</div>
                                <div style="font-size:1rem;font-weight:800;color:var(--earth);">
                                    $<?= number_format($order['total_amount'], 2) ?>
                                </div>
                            </div>
                        </div>
                        <?= status_badge($order['status']) ?>
                    </div>

                    <!-- Order items breakdown -->
                    <div style="padding:1.1rem 1.5rem;display:flex;flex-direction:column;gap:0.875rem;">
                        <?php foreach ($items as $oi): ?>
                            <div style="display:flex;align-items:center;gap:0.875rem;">
                                <!-- Thumbnail -->
                                <?php if ($oi['food_image']): ?>
                                    <img
                                        src="<?= BASE_PATH ?>uploads/food/<?= htmlspecialchars($oi['food_image']) ?>"
                                        alt=""
                                        style="width:3rem;height:3rem;border-radius:var(--radius-lg);object-fit:cover;border:1px solid var(--border-light);flex-shrink:0;"
                                    />
                                <?php else: ?>
                                    <div style="width:3rem;height:3rem;border-radius:var(--radius-lg);background:var(--primary-dim);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <span class="material-symbols-outlined" style="font-size:1.1rem;color:var(--sage);opacity:0.5;">restaurant</span>
                                    </div>
                                <?php endif; ?>

                                <!-- Name + seller -->
                                <div style="flex:1;min-width:0;">
                                    <div style="font-size:0.9375rem;font-weight:700;color:var(--earth);">
                                        <?= htmlspecialchars($oi['food_name']) ?>
                                    </div>
                                    <div style="font-size:0.8125rem;color:var(--sage);font-weight:600;">
                                        by <?= htmlspecialchars($oi['seller_first'] . ' ' . $oi['seller_last']) ?>
                                        · <?= $oi['quantity'] ?> × $<?= number_format($oi['price_at_purchase'], 2) ?>
                                    </div>
                                </div>

                                <!-- Line total -->
                                <div style="font-size:0.9375rem;font-weight:800;color:var(--earth);flex-shrink:0;">
                                    $<?= number_format($oi['quantity'] * $oi['price_at_purchase'], 2) ?>
                                </div>

                                <!-- Reorder button — only if item still available -->
                                <?php if ($oi['is_available']): ?>
                                    <form method="POST" action="<?= BASE_PATH ?>buyer/cart_add.php" style="margin:0;flex-shrink:0;">
                                        <input type="hidden" name="food_item_id" value="<?= $oi['food_item_id'] ?>" />
                                        <input type="hidden" name="quantity" value="<?= $oi['quantity'] ?>" />
                                        <input type="hidden" name="redirect" value="cart" />
                                        <button type="submit"
                                                style="display:inline-flex;align-items:center;gap:0.25rem;padding:0.375rem 0.75rem;border-radius:var(--radius-lg);background:none;border:1.5px solid var(--border-light);font-family:var(--font-body);font-size:0.75rem;font-weight:700;color:var(--sage);cursor:pointer;white-space:nowrap;transition:border-color 0.15s ease,color 0.15s ease;"
                                                onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--earth)'"
                                                onmouseout="this.style.borderColor='var(--border-light)';this.style.color='var(--sage)'"
                                        >
                                            <span class="material-symbols-outlined" style="font-size:0.875rem;">replay</span>
                                            Reorder
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Order footer -->
                    <div style="padding:0.875rem 1.5rem;border-top:1px solid var(--border-light);background:var(--bg-light);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;">
                        <span style="font-size:0.8125rem;color:var(--sage);font-weight:600;">
                            Placed <?= date('M j, Y \a\t g:i A', strtotime($order['created_at'])) ?>
                        </span>
                        <?php if ($order['status'] === 'delivered'): ?>
                            <span style="font-size:0.8125rem;color:#16a34a;font-weight:700;display:flex;align-items:center;gap:0.25rem;">
                                <span class="material-symbols-outlined" style="font-size:0.875rem;font-variation-settings:'FILL' 1;">check_circle</span>
                                Order completed
                            </span>
                        <?php elseif ($order['status'] === 'cancelled'): ?>
                            <span style="font-size:0.8125rem;color:#dc2626;font-weight:700;display:flex;align-items:center;gap:0.25rem;">
                                <span class="material-symbols-outlined" style="font-size:0.875rem;font-variation-settings:'FILL' 1;">cancel</span>
                                Order cancelled
                            </span>
                        <?php else: ?>
                            <span style="font-size:0.8125rem;color:var(--sage);font-weight:600;">
                                Your seller is handling this order
                            </span>
                        <?php endif; ?>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>