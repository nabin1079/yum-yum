<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-guard.php';
require_role('seller');

$page_title = 'Seller Dashboard';
$active_nav = 'dashboard';
$extra_css  = ['seller.css'];

//  Flash message from redirects 
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

//  Stats 
$seller_id = $_SESSION['user_id'];

// Total food items
$stmt = $pdo->prepare('SELECT COUNT(*) FROM food_items WHERE seller_id = ?');
$stmt->execute([$seller_id]);
$total_items = (int)$stmt->fetchColumn();

// Available items
$stmt = $pdo->prepare('SELECT COUNT(*) FROM food_items WHERE seller_id = ? AND is_available = 1');
$stmt->execute([$seller_id]);
$available_items = (int)$stmt->fetchColumn();

// Total orders containing seller's items
$stmt = $pdo->prepare('
    SELECT COUNT(DISTINCT o.id)
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    JOIN food_items  fi ON fi.id = oi.food_item_id
    WHERE fi.seller_id = ?
');
$stmt->execute([$seller_id]);
$total_orders = (int)$stmt->fetchColumn();

// Total revenue
$stmt = $pdo->prepare('
    SELECT COALESCE(SUM(oi.quantity * oi.price_at_purchase), 0)
    FROM order_items oi
    JOIN food_items  fi ON fi.id = oi.food_item_id
    WHERE fi.seller_id = ?
');
$stmt->execute([$seller_id]);
$total_revenue = (float)$stmt->fetchColumn();

//  Fetch seller's food items 
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
    WHERE fi.seller_id = ?
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
                <?= htmlspecialchars($_SESSION['first_name']) ?> 👋
            </h1>
            <p class="seller-page__subtitle">Here's what's happening with your tiffin shop today.</p>
        </div>
        <a href="<?= BASE_PATH ?>seller/food_add.php" class="seller-btn-primary">
            <span class="material-symbols-outlined">add</span>
            Add New Food
        </a>
    </div>

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
            <div class="seller-stat-card__label">Total Revenue</div>
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
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>" />
                                        <input type="hidden" name="current" value="<?= $item['is_available'] ?>" />
                                        <button type="submit" class="seller-btn-secondary" title="<?= $item['is_available'] ? 'Hide from menu' : 'Make available' ?>">
                                            <span class="material-symbols-outlined">
                                                <?= $item['is_available'] ? 'visibility_off' : 'visibility' ?>
                                            </span>
                                        </button>
                                    </form>

                                    <!-- Delete -->
                                    <form method="POST" action="<?= BASE_PATH ?>seller/food_delete.php"
                                          style="margin:0;"
                                          onsubmit="return confirm('Delete \'<?= htmlspecialchars(addslashes($item['name'])) ?>\'? This cannot be undone.');">
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