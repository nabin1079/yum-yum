<?php

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth-guard.php';

$page_title = 'Fresh Home-Style Tiffins';
$active_nav = 'home';
$extra_css  = ['hero.css', 'pages.css'];

require_once __DIR__ . '/includes/header.php';

$stmt = $pdo->prepare('
    SELECT
        fi.id,
        fi.name,
        fi.description,
        fi.price,
        fi.image_path,
        c.name  AS category,
        u.first_name AS seller_first,
        u.last_name  AS seller_last
    FROM food_items fi
    JOIN categories c ON fi.category_id = c.id
    JOIN users      u ON fi.seller_id   = u.id
    WHERE fi.is_available = 1
    ORDER BY fi.created_at DESC
    LIMIT 6
');
$stmt->execute();
$menu_items = $stmt->fetchAll();
?>

<section class="hero">
    <div class="hero__inner">

        <!-- Left: copy -->
        <div class="hero__content" data-reveal>
            <span class="hero__badge">
                <span class="material-symbols-outlined">local_fire_department</span>
                2,000+ students eating well
            </span>

            <h1 class="hero__heading">
                Fuel your studies with a
                <span class="hero__heading-accent">taste of home.</span>
            </h1>

            <p class="hero__description">
                Fresh, home-style tiffins delivered directly to your campus or housing —
                made with love by local cooks who know what comfort food really means.
            </p>

            <div class="hero__actions">
                <a href="<?= BASE_PATH ?>buyer/menu.php" class="hero__btn-primary">
                    View Today's Menu
                    <span class="material-symbols-outlined">arrow_forward</span>
                </a>
                <?php if (!is_logged_in()): ?>
                    <a href="<?= BASE_PATH ?>auth/register.php" class="hero__btn-secondary">
                        Get Started Free
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: image -->
        <div class="hero__image-wrapper" data-reveal>
            <div class="hero__image-glow"></div>
            <img
                class="hero__image"
                src="https://lh3.googleusercontent.com/aida-public/AB6AXuAdNGg3c-UnjVHb5oLDyxSLtOVeh0GTOb7KUH6H8UjV4_MIGW8gM4SgvfmmKYcgP-Ti8OqQEFOAjZt9Xfm0fgt84TWOqbJ-XMLaiRmcr3hk9QYUMvnl-2RMdpWO6nH2-1PorcbbK1fesEcLn19l4SJYfs_cteV_Bh7WNIsTKC2k3olvOceSAohH8qbJPM67uf9zKvs5cLfLdk31EPTB35CsmFoJ-9bZd6Ed-fwp1IVGEC03LDpy-l6uQpeVXfckN9cNN_RikiRU4tcc"
                alt="Fresh home-style tiffin meal"
            />
            <div class="hero__float-badge">
                <div class="hero__float-badge-icon">
                    <span class="material-symbols-outlined">verified</span>
                </div>
                <div>
                    <div class="hero__float-badge-title">Fresh every day</div>
                    <div class="hero__float-badge-sub">Cooked by local sellers</div>
                </div>
            </div>
        </div>

    </div>
</section>

<section class="how-it-works" data-reveal>
    <div class="how-it-works__inner">
        <div class="how-it-works__header">
            <h2 class="how-it-works__title">How It Works</h2>
            <p class="how-it-works__subtitle">
                Getting your daily tiffin is as simple as three steps.
            </p>
        </div>
        <div class="how-it-works__grid">
            <div class="step-card">
                <div class="step-card__icon step-card__icon--primary">
                    <span class="material-symbols-outlined">search</span>
                </div>
                <span class="step-card__number">01</span>
                <h3 class="step-card__title">Browse the Menu</h3>
                <p class="step-card__description">
                    Explore fresh meals added daily by local tiffin sellers near your campus.
                </p>
            </div>
            <div class="step-card">
                <div class="step-card__icon step-card__icon--sage">
                    <span class="material-symbols-outlined">shopping_cart</span>
                </div>
                <span class="step-card__number">02</span>
                <h3 class="step-card__title">Add to Cart</h3>
                <p class="step-card__description">
                    Pick your favourites, adjust quantities, and head to checkout in seconds.
                </p>
            </div>
            <div class="step-card">
                <div class="step-card__icon step-card__icon--earth">
                    <span class="material-symbols-outlined">delivery_dining</span>
                </div>
                <span class="step-card__number">03</span>
                <h3 class="step-card__title">Get It Delivered</h3>
                <p class="step-card__description">
                    Sit back and enjoy a home-cooked meal delivered straight to your door.
                </p>
            </div>
        </div>
    </div>
</section>

<section class="menu-section" data-reveal>
    <div class="menu-section__inner" style="max-width:1280px;margin:0 auto;padding:3rem 1.5rem;">

        <div class="menu-section__header">
            <div class="menu-section__header-text">
                <h2 class="menu-section__title">Today's Menu</h2>
                <p class="menu-section__subtitle">
                    Freshly listed by our sellers — updated every day.
                </p>
            </div>
            <a href="<?= BASE_PATH ?>buyer/menu.php" class="menu-section__view-all">
                View Full Menu →
            </a>
        </div>

        <?php if (empty($menu_items)): ?>
            <!-- Empty state -->
            <div style="text-align:center;padding:4rem 1rem;color:var(--sage);">
                <span class="material-symbols-outlined" style="font-size:3rem;display:block;margin-bottom:1rem;opacity:0.4;">
                    restaurant_menu
                </span>
                <p style="font-size:1.125rem;font-weight:600;">No meals listed yet.</p>
                <p style="font-size:0.9375rem;margin-top:0.5rem;">Check back soon — sellers are getting ready!</p>
                <?php if (!is_logged_in()): ?>
                    <a href="<?= BASE_PATH ?>auth/register.php?role=seller"
                       style="display:inline-block;margin-top:1.5rem;background:var(--primary);color:var(--earth);padding:0.75rem 2rem;border-radius:var(--radius-xl);font-weight:800;text-decoration:none;">
                        Become a Seller
                    </a>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="menu-section__scroll" id="menu-scroll">
                <?php foreach ($menu_items as $item): ?>
                    <a href="<?= BASE_PATH ?>buyer/product.php?id=<?= $item['id'] ?>"
                       class="dish-card dish-card--link">

                        <!-- Image -->
                        <div class="dish-card__image-wrapper">
                            <?php if ($item['image_path']): ?>
                                <img
                                    class="dish-card__image"
                                    src="<?= BASE_PATH ?>uploads/food/<?= htmlspecialchars($item['image_path']) ?>"
                                    alt="<?= htmlspecialchars($item['name']) ?>"
                                />
                            <?php else: ?>
                                <div class="dish-card__image"
                                     style="background:var(--primary-dim);display:flex;align-items:center;justify-content:center;">
                                    <span class="material-symbols-outlined"
                                          style="font-size:3rem;color:var(--sage);opacity:0.5;">
                                        restaurant
                                    </span>
                                </div>
                            <?php endif; ?>
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

            <!-- Scroll nav buttons -->
            <div class="menu-section__nav" style="margin-top:1.5rem;">
                <button class="menu-section__nav-btn" id="scroll-left" aria-label="Scroll left">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
                <button class="menu-section__nav-btn" id="scroll-right" aria-label="Scroll right">
                    <span class="material-symbols-outlined">chevron_right</span>
                </button>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php if (!is_logged_in()): ?>
<section data-reveal style="background:var(--bg-dark);padding:5rem 1.5rem;text-align:center;">
    <div style="max-width:600px;margin:0 auto;">
        <h2 style="font-size:clamp(1.75rem,3vw,2.5rem);font-weight:800;color:var(--white);margin-bottom:1rem;letter-spacing:-0.03em;">
            Ready to eat well every day?
        </h2>
        <p style="color:rgba(255,255,255,0.7);font-size:1.0625rem;line-height:1.7;margin-bottom:2rem;">
            Join 2,000+ students already enjoying fresh home-cooked tiffins delivered to their door.
        </p>
        <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
            <a href="<?= BASE_PATH ?>auth/register.php"
               style="display:inline-flex;align-items:center;gap:0.5rem;background:var(--primary);color:var(--earth);padding:1rem 2rem;border-radius:var(--radius-xl);font-weight:800;font-size:1rem;text-decoration:none;transition:box-shadow 0.25s ease;">
                Create Free Account
                <span class="material-symbols-outlined">arrow_forward</span>
            </a>
            <a href="<?= BASE_PATH ?>buyer/menu.php"
               style="display:inline-flex;align-items:center;gap:0.5rem;background:transparent;border:2px solid rgba(255,255,255,0.2);color:var(--white);padding:1rem 2rem;border-radius:var(--radius-xl);font-weight:800;font-size:1rem;text-decoration:none;">
                Browse Menu
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<script>
// Carousel scroll
(function () {
    const track = document.getElementById('menu-scroll');
    if (!track) return;
    const scrollAmount = 320;
    document.getElementById('scroll-left')?.addEventListener('click',  () => track.scrollBy({ left: -scrollAmount, behavior: 'smooth' }));
    document.getElementById('scroll-right')?.addEventListener('click', () => track.scrollBy({ left:  scrollAmount, behavior: 'smooth' }));
})();

// Scroll reveal
(function () {
    const els = document.querySelectorAll('[data-reveal]');
    if (!els.length) return;

    const reveal = () => {
        els.forEach(el => {
            el.style.opacity    = '0';
            el.style.transform  = 'translateY(24px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        });

        const io = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    e.target.classList.add('revealed');
                    e.target.style.opacity   = '1';
                    e.target.style.transform = 'translateY(0)';
                    io.unobserve(e.target);
                }
            });
        }, { threshold: 0.1 });

        els.forEach(el => io.observe(el));

        // Immediately reveal elements already in viewport
        requestAnimationFrame(() => {
            els.forEach(el => {
                const rect = el.getBoundingClientRect();
                if (rect.top < window.innerHeight) {
                    el.classList.add('revealed');
                    el.style.opacity   = '1';
                    el.style.transform = 'translateY(0)';
                }
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', reveal);
    } else {
        reveal();
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>