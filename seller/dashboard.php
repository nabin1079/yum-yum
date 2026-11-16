<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-guard.php';
require_role('seller');

$page_title = 'Seller Dashboard';
$active_nav = 'dashboard';
$extra_css  = ['seller.css'];

$flash     = take_flash();
$seller_id = (int)$_SESSION['user_id'];

//  Stats (archived meals are not counted)
$stmt = $pdo->prepare('
    SELECT COUNT(*) AS total, COALESCE(SUM(is_available), 0) AS available
    FROM food_items
    WHERE seller_id = ? AND deleted_at IS NULL
');
$stmt->execute([$seller_id]);
$counts          = $stmt->fetch();
$total_items     = (int)$counts['total'];
$available_items = (int)$counts['available'];

// Orders and cash on delivery: collected = paid, to collect = active and unpaid
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount END), 0) AS collected,
        COALESCE(SUM(CASE WHEN payment_status = 'unpaid' AND status <> 'cancelled' THEN total_amount END), 0) AS to_collect,
        SUM(status = 'pending') AS pending
    FROM orders
    WHERE seller_id = ?
");
$stmt->execute([$seller_id]);
$order_stats   = $stmt->fetch();
$total_orders  = (int)$order_stats['total_orders'];
$total_revenue = (float)$order_stats['collected'];
$to_collect    = (float)$order_stats['to_collect'];
$pending       = (int)$order_stats['pending'];

// Profile completeness reminder
$stmt = $pdo->prepare('SELECT kitchen_name, bio FROM users WHERE id = ?');
$stmt->execute([$seller_id]);
$profile = $stmt->fetch();
$profile_incomplete = trim((string)$profile['kitchen_name']) === '' || trim((string)$profile['bio']) === '';

//  Seller's food items
$stmt = $pdo->prepare('
    SELECT
        fi.id,
        fi.name,
        fi.description,
        fi.price,
        fi.image_path,
        fi.is_available,
        fi.created_at,
        c.name AS category
    FROM food_items fi
    JOIN categories c ON fi.category_id = c.id
    WHERE fi.seller_id = ? AND fi.deleted_at IS NULL
    ORDER BY fi.created_at DESC
');
$stmt->execute([$seller_id]);
$food_items = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="seller-page">

    <!-- Page header -->
    <div class="seller-page__header">
        <div>
            <h1 class="seller-page__title">
                Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>,
                <?= e($_SESSION['first_name']) ?>
            </h1>
            <p class="seller-page__subtitle">Here's what's happening with your tiffin shop today.</p>
        </div>
        <div style="display:flex;gap:0.625rem;flex-wrap:wrap;">
            <a href="<?= BASE_PATH ?>seller.php?id=<?= $seller_id ?>" class="seller-btn-secondary">
                <span class="material-symbols-outlined">storefront</span>
                My Public Page
            </a>
            <a href="<?= BASE_PATH ?>seller/food_add.php" class="seller-btn-primary">
                <span class="material-symbols-outlined">add</span>
                Add New Food
            </a>
        </div>
    </div>

    <?php if ($profile_incomplete): ?>
        <div class="seller-alert seller-alert--info">
            <span class="material-symbols-outlined">badge</span>
            <span>Buyers can visit your seller page. <a href="<?= BASE_PATH ?>seller/profile.php" style="font-weight:800;color:inherit;">Add your kitchen name and a short bio</a> so they know who is cooking.</span>
        </div>
    <?php endif; ?>

    <?php if ($pending > 0): ?>
        <div class="seller-alert seller-alert--info">
            <span class="material-symbols-outlined">notifications_active</span>
            <span>You have <?= $pending ?> new order<?= $pending !== 1 ? 's' : '' ?> waiting. <a href="<?= BASE_PATH ?>seller/orders.php?status=pending" style="font-weight:800;color:inherit;">Review now</a></span>
        </div>
    <?php endif; ?>

    <!-- Flash message -->
    <?php if ($flash): ?>
        <div class="seller-alert seller-alert--<?= $flash['type'] ?>">
            <span class="material-symbols-outlined">
                <?= $flash['type'] === 'success' ? 'check_circle' : 'error' ?>
            </span>
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- Stats row -->
    <div class="seller-stats">

        <div class="seller-stat-card">
            <div class="seller-stat-card__icon seller-stat-card__icon--yellow">
                <span class="material-symbols-outlined">restaurant_menu</span>
            </div>
            <div class="seller-stat-card__value"><?= $total_items ?></div>
            <div class="seller-stat-card__label">Total Items Listed</div>
        </div>

        <div class="seller-stat-card">
            <div class="seller-stat-card__icon seller-stat-card__icon--green">
                <span class="material-symbols-outlined">visibility</span>
            </div>
            <div class="seller-stat-card__value"><?= $available_items ?></div>
            <div class="seller-stat-card__label">Available Now</div>
        </div>

        <div class="seller-stat-card">
            <div class="seller-stat-card__icon seller-stat-card__icon--blue">
                <span class="material-symbols-outlined">receipt_long</span>
            </div>
            <div class="seller-stat-card__value"><?= $total_orders ?></div>
            <div class="seller-stat-card__label">Total Orders</div>
        </div>

        <div class="seller-stat-card">
            <div class="seller-stat-card__icon seller-stat-card__icon--red">
                <span class="material-symbols-outlined">payments</span>
            </div>
            <div class="seller-stat-card__value">$<?= number_format($total_revenue, 2) ?></div>
            <div class="seller-stat-card__label">Cash Collected</div>
            <?php if ($to_collect > 0): ?>
                <div style="font-size:0.75rem;font-weight:700;color:var(--sage);margin-top:0.25rem;">$<?= number_format($to_collect, 2) ?> still to collect</div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Food items table -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
        <h2 style="font-size:1.125rem;font-weight:800;color:var(--earth);">Your Food Items</h2>
        <?php if ($total_items > 0): ?>
            <span style="font-size:0.875rem;color:var(--sage);font-weight:600;">
                <?= $total_items ?> item<?= $total_items !== 1 ? 's' : '' ?> listed
            </span>
        <?php endif; ?>
    </div>

    <?php if (empty($food_items)): ?>
        <!-- Empty state -->
        <div class="seller-table-wrapper">
            <div class="seller-empty">
                <span class="material-symbols-outlined">restaurant</span>
                <p class="seller-empty__title">No food items yet</p>
                <p class="seller-empty__sub">Start by adding your first meal to the menu.</p>
                <a href="<?= BASE_PATH ?>seller/food_add.php" class="seller-btn-primary">
                    <span class="material-symbols-outlined">add</span>
                    Add Your First Item
                </a>
            </div>
        </div>

    <?php else: ?>
        <div class="seller-table-wrapper">
            <table class="seller-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($food_items as $item): ?>
                        <tr>
                            <!-- Item name + image -->
                            <td>
                                <div class="seller-table__item-cell">
                                    <?php if ($item['image_path']): ?>
                                        <img
                                            class="seller-table__thumb"
                                            src="<?= BASE_PATH ?>uploads/food/<?= htmlspecialchars($item['image_path']) ?>"
                                            alt="<?= htmlspecialchars($item['name']) ?>"
                                        />
                                    <?php else: ?>
                                        <div class="seller-table__thumb-placeholder">
                                            <span class="material-symbols-outlined">restaurant</span>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="seller-table__item-name">
                                            <?= htmlspecialchars($item['name']) ?>
                                        </div>
                                        <div class="seller-table__item-desc">
                                            <?= htmlspecialchars(mb_strimwidth($item['description'] ?? '', 0, 50, '…')) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Category -->
                            <td>
                                <span class="cat-badge"><?= htmlspecialchars($item['category']) ?></span>
                            </td>

                            <!-- Price -->
                            <td style="font-weight:800;">
                                $<?= number_format($item['price'], 2) ?>
                            </td>

                            <!-- Availability status -->
                            <td>
                                <?php if ($item['is_available']): ?>
                                    <span class="status-badge status-badge--available">
                                        ● Available
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-badge--hidden">
                                        ● Hidden
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Date added -->
                            <td style="color:var(--sage);font-size:0.875rem;">
                                <?= date('M j, Y', strtotime($item['created_at'])) ?>
                            </td>

                            <!-- Actions -->
                            <td>
                                <div class="seller-table__actions">
                                    <!-- Edit -->
                                    <a href="<?= BASE_PATH ?>seller/food_edit.php?id=<?= $item['id'] ?>"
                                       class="seller-btn-secondary">
                                        <span class="material-symbols-outlined">edit</span>
                                        Edit
                                    </a>

                                    <!-- Toggle availability -->
                                    <form method="POST" action="<?= BASE_PATH ?>seller/food_toggle.php" style="margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>" />
                                        <button type="submit" class="seller-btn-secondary" title="<?= $item['is_available'] ? 'Hide from menu' : 'Make available' ?>">
                                            <span class="material-symbols-outlined">
                                                <?= $item['is_available'] ? 'visibility_off' : 'visibility' ?>
                                            </span>
                                        </button>
                                    </form>

                                    <!-- Delete -->
                                    <form method="POST" action="<?= BASE_PATH ?>seller/food_delete.php"
                                          style="margin:0;"
                                          data-confirm="Remove &quot;<?= e($item['name']) ?>&quot; from your menu?" onsubmit="return confirm(this.dataset.confirm);">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>" />
                                        <button type="submit" class="seller-btn-danger">
                                            <span class="material-symbols-outlined">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Quick link to orders -->
        <div style="margin-top:1.5rem;text-align:right;">
            <a href="<?= BASE_PATH ?>seller/orders.php" class="seller-btn-secondary">
                <span class="material-symbols-outlined">receipt_long</span>
                View All Orders
            </a>
        </div>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>