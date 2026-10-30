<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-guard.php';

$page_title = 'Product';
$active_nav = 'menu';
$extra_css  = ['pages.css'];

$item_id = (int)($_GET['id'] ?? 0);

if ($item_id === 0) {
    header('Location: ' . BASE_PATH . 'buyer/menu.php');
    exit;
}

//  Fetch item 
$stmt = $pdo->prepare('
    SELECT
        fi.id,
        fi.name,
        fi.description,
        fi.price,
        fi.image_path,
        fi.is_available,
        c.name       AS category,
        u.id         AS seller_id,
        u.first_name AS seller_first,
        u.last_name  AS seller_last
    FROM food_items fi
    JOIN categories c ON fi.category_id = c.id
    JOIN users      u ON fi.seller_id   = u.id
    WHERE fi.id = ? AND fi.is_available = 1
');
$stmt->execute([$item_id]);
$item = $stmt->fetch();

// Not found or unavailable
if (!$item) {
    header('Location: ' . BASE_PATH . 'buyer/menu.php');
    exit;
}

$page_title = $item['name'];

//  Flash message (e.g. added to cart)
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

//  Fetch more items from same category (related)
$stmt = $pdo->prepare('
    SELECT
        fi.id,
        fi.name,
        fi.price,
        fi.image_path,
        c.name AS category
    FROM food_items fi
    JOIN categories c ON fi.category_id = c.id
    WHERE fi.category_id = (
        SELECT category_id FROM food_items WHERE id = ?
    )
    AND fi.id != ?
    AND fi.is_available = 1
    LIMIT 3
');
$stmt->execute([$item_id, $item_id]);
$related_items = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:1100px;margin:0 auto;padding:2rem 1.5rem 4rem;">

    <!-- Breadcrumb -->
    <nav class="breadcrumb" style="margin-bottom:2rem;">
        <a href="<?= BASE_PATH ?>index.php">Home</a>
        <span class="material-symbols-outlined">chevron_right</span>
        <a href="<?= BASE_PATH ?>buyer/menu.php">Menu</a>
        <span class="material-symbols-outlined">chevron_right</span>
        <span><?= htmlspecialchars($item['name']) ?></span>
    </nav>

    <!-- Flash -->
    <?php if ($flash): ?>
        <div style="
            padding:0.875rem 1.25rem;
            border-radius:var(--radius-lg);
            font-size:0.9375rem;
            font-weight:600;
            margin-bottom:1.5rem;
            display:flex;
            align-items:center;
            gap:0.625rem;
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

    <!-- Product detail grid -->
    <div class="detail-grid">

        <!-- Left: Image -->
        <div class="detail-image-col">
            <?php if ($item['image_path']): ?>
                <img
                    class="detail-image"
                    src="<?= BASE_PATH ?>uploads/food/<?= htmlspecialchars($item['image_path']) ?>"
                    alt="<?= htmlspecialchars($item['name']) ?>"
                />
            <?php else: ?>
                <div class="detail-image"
                     style="background:var(--primary-dim);display:flex;align-items:center;justify-content:center;min-height:320px;">
                    <span class="material-symbols-outlined"
                          style="font-size:5rem;color:var(--sage);opacity:0.3;">
                        restaurant
                    </span>
                </div>
            <?php endif; ?>

            <!-- Category + Seller pill -->
            <div class="detail-tag-pills" style="margin-top:1.25rem;">
                <span class="detail-tag-pill">
                    <span class="material-symbols-outlined" style="font-size:0.9rem;font-variation-settings:'FILL' 1;">category</span>
                    <?= htmlspecialchars($item['category']) ?>
                </span>
                <span class="detail-tag-pill">
                    <span class="material-symbols-outlined" style="font-size:0.9rem;font-variation-settings:'FILL' 1;">storefront</span>
                    <?= htmlspecialchars($item['seller_first'] . ' ' . $item['seller_last']) ?>
                </span>
            </div>
        </div>

        <!-- Right: Info + Add to cart -->
        <div style="display:flex;flex-direction:column;gap:1.5rem;">

            <!-- Name + price -->
            <div>
                <h1 style="font-size:clamp(1.75rem,3vw,2.5rem);font-weight:800;color:var(--earth);letter-spacing:-0.03em;margin-bottom:0.5rem;">
                    <?= htmlspecialchars($item['name']) ?>
                </h1>
                <div style="font-size:2rem;font-weight:800;color:var(--earth);">
                    $<?= number_format($item['price'], 2) ?>
                </div>
            </div>

            <!-- Description -->
            <p style="font-size:1rem;color:var(--sage);line-height:1.75;font-weight:500;">
                <?= nl2br(htmlspecialchars($item['description'])) ?>
            </p>

            <!-- Add to cart section -->
            <?php if (is_logged_in() && $_SESSION['role'] === 'buyer'): ?>

                <!-- Check if already in cart -->
                <?php
                $in_cart = false;
                $cart_qty = 0;
                $stmt = $pdo->prepare('SELECT quantity FROM cart WHERE buyer_id = ? AND food_item_id = ?');
                $stmt->execute([$_SESSION['user_id'], $item_id]);
                $cart_row = $stmt->fetch();
                if ($cart_row) {
                    $in_cart  = true;
                    $cart_qty = $cart_row['quantity'];
                }
                ?>

                <div style="background:var(--white);border:1px solid var(--border-light);border-radius:var(--radius-2xl);padding:1.5rem;box-shadow:var(--shadow-sm);">

                    <?php if ($in_cart): ?>
                        <!-- Already in cart -->
                        <p style="font-size:0.875rem;font-weight:700;color:#15803d;margin-bottom:1rem;display:flex;align-items:center;gap:0.375rem;">
                            <span class="material-symbols-outlined" style="font-size:1rem;font-variation-settings:'FILL' 1;">check_circle</span>
                            Already in your cart (qty: <?= $cart_qty ?>)
                        </p>
                        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                            <a href="<?= BASE_PATH ?>buyer/cart.php"
                               style="flex:1;display:inline-flex;align-items:center;justify-content:center;gap:0.5rem;background:var(--primary);color:var(--earth);padding:0.875rem 1.5rem;border-radius:var(--radius-xl);font-weight:800;font-size:0.9375rem;text-decoration:none;">
                                <span class="material-symbols-outlined">shopping_cart</span>
                                View Cart
                            </a>
                            <form method="POST" action="<?= BASE_PATH ?>buyer/cart_add.php" style="flex:1;margin:0;">
                                <input type="hidden" name="food_item_id" value="<?= $item_id ?>" />
                                <input type="hidden" name="redirect" value="product" />
                                <button type="submit"
                                        style="width:100%;display:inline-flex;align-items:center;justify-content:center;gap:0.5rem;background:none;border:1.5px solid var(--border-light);color:var(--sage);padding:0.875rem 1.5rem;border-radius:var(--radius-xl);font-family:var(--font-body);font-weight:800;font-size:0.9375rem;cursor:pointer;">
                                    <span class="material-symbols-outlined">add</span>
                                    Add Another
                                </button>
                            </form>
                        </div>

                    <?php else: ?>
                        <!-- Add to cart form -->
                        <form method="POST" action="<?= BASE_PATH ?>buyer/cart_add.php" style="margin:0;">
                            <input type="hidden" name="food_item_id" value="<?= $item_id ?>" />
                            <input type="hidden" name="redirect" value="product" />

                            <!-- Quantity picker -->
                            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;">
                                <label style="font-size:0.875rem;font-weight:700;color:var(--earth);">Quantity</label>
                                <div style="display:flex;align-items:center;border:1.5px solid var(--border-light);border-radius:var(--radius-lg);overflow:hidden;">
                                    <button type="button" onclick="adjustQty(-1)"
                                            style="width:2.5rem;height:2.5rem;background:var(--bg-light);border:none;font-size:1.25rem;font-weight:700;color:var(--earth);cursor:pointer;">
                                        −
                                    </button>
                                    <input type="number" id="qty" name="quantity"
                                           value="1" min="1" max="20"
                                           style="width:3rem;text-align:center;border:none;font-family:var(--font-body);font-size:0.9375rem;font-weight:700;color:var(--earth);background:var(--white);outline:none;" />
                                    <button type="button" onclick="adjustQty(1)"
                                            style="width:2.5rem;height:2.5rem;background:var(--bg-light);border:none;font-size:1.25rem;font-weight:700;color:var(--earth);cursor:pointer;">
                                        +
                                    </button>
                                </div>
                            </div>

                            <button type="submit"
                                    style="width:100%;display:inline-flex;align-items:center;justify-content:center;gap:0.625rem;background:var(--primary);color:var(--earth);padding:1rem;border-radius:var(--radius-xl);border:none;font-family:var(--font-body);font-size:1rem;font-weight:800;cursor:pointer;box-shadow:0 4px 14px rgba(244,209,37,0.35);transition:box-shadow 0.25s ease,transform 0.15s ease;">
                                <span class="material-symbols-outlined">add_shopping_cart</span>
                                Add to Cart
                            </button>
                        </form>
                    <?php endif; ?>

                </div>

            <?php elseif (is_logged_in() && $_SESSION['role'] === 'seller'): ?>
                <!-- Seller viewing — no add to cart -->
                <div style="background:var(--bg-light);border:1px solid var(--border-light);border-radius:var(--radius-xl);padding:1.25rem;text-align:center;color:var(--sage);font-weight:600;font-size:0.9375rem;">
                    <span class="material-symbols-outlined" style="display:block;font-size:1.5rem;margin-bottom:0.375rem;opacity:0.5;">info</span>
                    Seller accounts cannot place orders.
                </div>

            <?php else: ?>
                <!-- Guest — prompt to log in -->
                <div style="background:var(--bg-light);border:1px solid var(--border-light);border-radius:var(--radius-2xl);padding:1.5rem;text-align:center;">
                    <p style="font-size:0.9375rem;font-weight:600;color:var(--sage);margin-bottom:1rem;">
                        Sign in to add this item to your cart.
                    </p>
                    <div style="display:flex;gap:0.75rem;justify-content:center;flex-wrap:wrap;">
                        <a href="<?= BASE_PATH ?>auth/login.php"
                           style="display:inline-flex;align-items:center;gap:0.5rem;background:var(--primary);color:var(--earth);padding:0.75rem 1.5rem;border-radius:var(--radius-xl);font-weight:800;text-decoration:none;font-size:0.9375rem;">
                            Sign In
                        </a>
                        <a href="<?= BASE_PATH ?>auth/register.php"
                           style="display:inline-flex;align-items:center;gap:0.5rem;background:none;border:1.5px solid var(--border-light);color:var(--earth);padding:0.75rem 1.5rem;border-radius:var(--radius-xl);font-weight:800;text-decoration:none;font-size:0.9375rem;">
                            Create Account
                        </a>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div><!-- /detail-grid -->

    <!-- Related items -->
    <?php if (!empty($related_items)): ?>
        <div style="margin-top:4rem;">
            <h2 style="font-size:1.375rem;font-weight:800;color:var(--earth);letter-spacing:-0.02em;margin-bottom:1.5rem;">
                More from this category
            </h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1.25rem;">
                <?php foreach ($related_items as $rel): ?>
                    <a href="<?= BASE_PATH ?>buyer/product.php?id=<?= $rel['id'] ?>"
                       class="menu-card menu-card--link" style="text-decoration:none;">
                        <div class="menu-card__image-wrapper">
                            <?php if ($rel['image_path']): ?>
                                <img class="menu-card__image"
                                     src="<?= BASE_PATH ?>uploads/food/<?= htmlspecialchars($rel['image_path']) ?>"
                                     alt="<?= htmlspecialchars($rel['name']) ?>" />
                            <?php else: ?>
                                <div class="menu-card__image"
                                     style="background:var(--primary-dim);display:flex;align-items:center;justify-content:center;">
                                    <span class="material-symbols-outlined" style="font-size:2.5rem;color:var(--sage);opacity:0.4;">restaurant</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="menu-card__body">
                            <div class="menu-card__top">
                                <h3 class="menu-card__name"><?= htmlspecialchars($rel['name']) ?></h3>
                                <span class="menu-card__price">$<?= number_format($rel['price'], 2) ?></span>
                            </div>
                            <span class="menu-card__btn" style="margin-top:0.5rem;display:inline-block;">View →</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
function adjustQty(delta) {
    const input = document.getElementById('qty');
    if (!input) return;
    let val = parseInt(input.value) + delta;
    if (val < 1)  val = 1;
    if (val > 20) val = 20;
    input.value = val;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>