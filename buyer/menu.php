<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-guard.php';

$page_title = 'Menu';
$active_nav = 'menu';
$extra_css  = ['pages.css'];

//  Filters & sorting from GET 
$selected_category = (int)($_GET['category'] ?? 0);
$sort              = $_GET['sort'] ?? 'newest';
$allowed_sorts     = ['newest', 'oldest', 'price_asc', 'price_desc', 'name_asc'];
if (!in_array($sort, $allowed_sorts)) $sort = 'newest';

$order_by = match($sort) {
    'oldest'     => 'fi.created_at ASC',
    'price_asc'  => 'fi.price ASC',
    'price_desc' => 'fi.price DESC',
    'name_asc'   => 'fi.name ASC',
    default      => 'fi.created_at DESC',
};

//  Fetch all categories for filter bar 
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();

//  Fetch food items (filtered + sorted) 
$where = 'WHERE fi.is_available = 1';
$params = [];

if ($selected_category > 0) {
    $where   .= ' AND fi.category_id = ?';
    $params[] = $selected_category;
}

$stmt = $pdo->prepare("
    SELECT
        fi.id,
        fi.name,
        fi.description,
        fi.price,
        fi.image_path,
        c.id   AS category_id,
        c.name AS category,
        u.first_name AS seller_first,
        u.last_name  AS seller_last
    FROM food_items fi
    JOIN categories c ON fi.category_id = c.id
    JOIN users      u ON fi.seller_id   = u.id
    $where
    ORDER BY $order_by
");
$stmt->execute($params);
$food_items = $stmt->fetchAll();

$total_count = count($food_items);

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:1280px;margin:0 auto;padding:2.5rem 1.5rem 4rem;">

    <!-- Page heading -->
    <div style="margin-bottom:2rem;">
        <h1 style="font-size:clamp(1.75rem,3vw,2.5rem);font-weight:800;color:var(--earth);letter-spacing:-0.03em;margin-bottom:0.375rem;">
            Today's Menu
        </h1>
        <p style="color:var(--sage);font-size:0.9375rem;font-weight:500;">
            <?= $total_count ?> meal<?= $total_count !== 1 ? 's' : '' ?> available
            <?= $selected_category > 0 ? '· filtered by <strong>' . htmlspecialchars(
                array_values(array_filter($categories, fn($c) => $c['id'] == $selected_category))[0]['name'] ?? ''
            ) . '</strong>' : '' ?>
        </p>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar" style="margin-bottom:2rem;">

        <!-- Category buttons -->
        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;flex:1;">

            <!-- All -->
            <a href="?sort=<?= urlencode($sort) ?>"
               class="filter-btn <?= $selected_category === 0 ? 'filter-btn--active' : 'filter-btn--inactive' ?>"
               style="text-decoration:none;">
                All
            </a>

            <?php foreach ($categories as $cat): ?>
                <a href="?category=<?= $cat['id'] ?>&sort=<?= urlencode($sort) ?>"
                   class="filter-btn <?= $selected_category === (int)$cat['id'] ? 'filter-btn--active' : 'filter-btn--inactive' ?>"
                   style="text-decoration:none;">
                    <?= htmlspecialchars($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Sort dropdown -->
        <div class="filter-bar__sort">
            <form method="GET" action="" id="sort-form" style="margin:0;">
                <?php if ($selected_category > 0): ?>
                    <input type="hidden" name="category" value="<?= $selected_category ?>" />
                <?php endif; ?>
                <select name="sort" id="sort-select">
                    <option value="newest"     <?= $sort === 'newest'     ? 'selected' : '' ?>>Newest First</option>
                    <option value="oldest"     <?= $sort === 'oldest'     ? 'selected' : '' ?>>Oldest First</option>
                    <option value="price_asc"  <?= $sort === 'price_asc'  ? 'selected' : '' ?>>Price: Low → High</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High → Low</option>
                    <option value="name_asc"   <?= $sort === 'name_asc'   ? 'selected' : '' ?>>Name: A → Z</option>
                </select>
            </form>
        </div>

    </div>

    <!-- Food grid -->
    <?php if (empty($food_items)): ?>

        <!-- Empty state -->
        <div style="text-align:center;padding:5rem 1rem;color:var(--sage);">
            <span class="material-symbols-outlined"
                  style="font-size:3.5rem;display:block;margin-bottom:1rem;opacity:0.35;">
                restaurant_menu
            </span>
            <p style="font-size:1.125rem;font-weight:700;color:var(--earth);margin-bottom:0.5rem;">
                No meals found
            </p>
            <p style="font-size:0.9375rem;margin-bottom:1.5rem;">
                <?= $selected_category > 0
                    ? 'No items in this category yet. Try a different filter.'
                    : 'No meals have been listed yet. Check back soon!' ?>
            </p>
            <?php if ($selected_category > 0): ?>
                <a href="<?= BASE_PATH ?>buyer/menu.php"
                   style="display:inline-flex;align-items:center;gap:0.5rem;background:var(--primary);color:var(--earth);padding:0.75rem 1.5rem;border-radius:var(--radius-xl);font-weight:800;text-decoration:none;">
                    Clear Filter
                </a>
            <?php endif; ?>
        </div>

    <?php else: ?>

        <div class="menu-grid">
            <?php foreach ($food_items as $item): ?>
                <a href="<?= BASE_PATH ?>buyer/product.php?id=<?= $item['id'] ?>"
                   class="menu-card menu-card--link">

                    <!-- Image -->
                    <div class="menu-card__image-wrapper">
                        <?php if ($item['image_path']): ?>
                            <img
                                class="menu-card__image"
                                src="<?= BASE_PATH ?>uploads/food/<?= htmlspecialchars($item['image_path']) ?>"
                                alt="<?= htmlspecialchars($item['name']) ?>"
                            />
                        <?php else: ?>
                            <div class="menu-card__image"
                                 style="background:var(--primary-dim);display:flex;align-items:center;justify-content:center;">
                                <span class="material-symbols-outlined"
                                      style="font-size:3rem;color:var(--sage);opacity:0.4;">
                                    restaurant
                                </span>
                            </div>
                        <?php endif; ?>

                        <!-- Category badge -->
                        <span class="dish-card__badge"><?= htmlspecialchars($item['category']) ?></span>
                    </div>

                    <!-- Body -->
                    <div class="menu-card__body">
                        <div class="menu-card__top">
                            <h3 class="menu-card__name">
                                <?= htmlspecialchars($item['name']) ?>
                            </h3>
                            <span class="menu-card__price">
                                $<?= number_format($item['price'], 2) ?>
                            </span>
                        </div>

                        <p class="menu-card__desc">
                            <?= htmlspecialchars(mb_strimwidth($item['description'] ?? '', 0, 90, '…')) ?>
                        </p>

                        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:auto;padding-top:0.75rem;">
                            <span style="font-size:0.8125rem;font-weight:600;color:var(--sage);">
                                <span class="material-symbols-outlined"
                                      style="font-size:0.875rem;vertical-align:middle;font-variation-settings:'FILL' 1;">
                                    storefront
                                </span>
                                <?= htmlspecialchars($item['seller_first'] . ' ' . $item['seller_last']) ?>
                            </span>
                            <span class="menu-card__btn">View →</span>
                        </div>
                    </div>

                </a>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</div>

<script>
document.getElementById('sort-select').addEventListener('change', function () {
    document.getElementById('sort-form').submit();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>