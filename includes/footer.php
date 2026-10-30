</main>
<footer style="background:var(--bg-light);border-top:1px solid var(--border-light);padding:2.5rem 1.5rem;">
    <div style="max-width:1280px;margin:0 auto;">

        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1.5rem;margin-bottom:2rem;">

            <div style="display:flex;flex-direction:column;gap:0.375rem;">
                <a href="<?= BASE_PATH ?>index.php"
                   style="display:flex;align-items:center;gap:0.5rem;text-decoration:none;">
                    <div style="background:var(--primary);padding:0.375rem;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;">
                        <span class="material-symbols-outlined"
                              style="font-size:1.25rem;color:var(--earth);font-variation-settings:'FILL' 1;">
                            restaurant_menu
                        </span>
                    </div>
                    <span style="font-size:1.125rem;font-weight:800;color:var(--earth);letter-spacing:-0.02em;">
                        Yum-Yum
                    </span>
                </a>
                <p style="font-size:0.8125rem;color:var(--sage);font-weight:500;max-width:220px;line-height:1.5;">
                    Fresh home-style tiffins delivered to your campus every day.
                </p>
            </div>

            <nav style="display:flex;flex-wrap:wrap;gap:0.25rem 0.125rem;align-items:center;">
                <?php
                $footer_links = [
                    ['label' => 'Home',   'href' => BASE_PATH . 'index.php'],
                    ['label' => 'Menu',   'href' => BASE_PATH . 'buyer/menu.php'],
                    ['label' => 'About',  'href' => BASE_PATH . 'about.php'],
                    ['label' => 'Contact','href' => BASE_PATH . 'contact.php'],
                ];

                if (isset($_SESSION['user_id'])) {
                    if ($_SESSION['role'] === 'seller') {
                        $footer_links[] = ['label' => 'Dashboard', 'href' => BASE_PATH . 'seller/dashboard.php'];
                        $footer_links[] = ['label' => 'Orders',    'href' => BASE_PATH . 'seller/orders.php'];
                    } else {
                        $footer_links[] = ['label' => 'My Cart',   'href' => BASE_PATH . 'buyer/cart.php'];
                        $footer_links[] = ['label' => 'My Orders', 'href' => BASE_PATH . 'buyer/orders.php'];
                    }
                } else {
                    $footer_links[] = ['label' => 'Login',   'href' => BASE_PATH . 'auth/login.php'];
                    $footer_links[] = ['label' => 'Sign Up', 'href' => BASE_PATH . 'auth/register.php'];
                }

                foreach ($footer_links as $i => $link):
                ?>
                    <a href="<?= $link['href'] ?>"
                       style="font-size:0.875rem;font-weight:600;color:var(--sage);text-decoration:none;padding:0.375rem 0.75rem;border-radius:var(--radius-full);transition:color var(--transition-fast),background var(--transition-fast);"
                       onmouseover="this.style.color='var(--earth)';this.style.background='var(--sage-light)'"
                       onmouseout="this.style.color='var(--sage)';this.style.background='transparent'">
                        <?= $link['label'] ?>
                    </a>
                    <?php if ($i < count($footer_links) - 1): ?>
                        <span style="color:var(--border-light);font-size:0.75rem;user-select:none;">·</span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>

        </div>

        <!-- Bottom row: copyright + legal -->
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;padding-top:1.25rem;border-top:1px solid var(--border-light);">
            <p style="font-size:0.8125rem;color:var(--sage);font-weight:500;">
                &copy; <?= date('Y') ?> Yum-Yum Tiffin Services. All rights reserved.
            </p>
            <div style="display:flex;gap:1.25rem;">
                <a href="#" style="font-size:0.8125rem;font-weight:600;color:var(--sage);text-decoration:none;transition:color 0.15s ease;"
                   onmouseover="this.style.color='var(--earth)'" onmouseout="this.style.color='var(--sage)'">Privacy Policy</a>
                <a href="#" style="font-size:0.8125rem;font-weight:600;color:var(--sage);text-decoration:none;transition:color 0.15s ease;"
                   onmouseover="this.style.color='var(--earth)'" onmouseout="this.style.color='var(--sage)'">Terms of Service</a>
                <a href="#" style="font-size:0.8125rem;font-weight:600;color:var(--sage);text-decoration:none;transition:color 0.15s ease;"
                   onmouseover="this.style.color='var(--earth)'" onmouseout="this.style.color='var(--sage)'">Help Center</a>
            </div>
        </div>

    </div>
</footer>
<!-- /FOOTER -->

<!-- Navbar scroll hide/show script -->
<script>
(function () {
    const navbar    = document.getElementById('navbar');
    const hamburger = document.getElementById('navbar-hamburger');
    const mobileMenu= document.getElementById('navbar-mobile-menu');

    // ---- Hide on scroll down, show on scroll up ----
    let lastScroll  = 0;
    let ticking     = false;

    window.addEventListener('scroll', () => {
        if (!ticking) {
            requestAnimationFrame(() => {
                const current = window.scrollY;

                if (current <= 80) {
                    navbar.classList.remove('navbar--hidden');
                    navbar.classList.remove('navbar--scrolled');
                } else if (current > lastScroll) {
                    // Scrolling down
                    navbar.classList.add('navbar--hidden');
                    mobileMenu.classList.remove('navbar__mobile-menu--open');
                    hamburger.classList.remove('navbar__hamburger--open');
                } else {
                    // Scrolling up
                    navbar.classList.remove('navbar--hidden');
                    navbar.classList.add('navbar--scrolled');
                }

                lastScroll = current;
                ticking    = false;
            });
            ticking = true;
        }
    });

    // ---- Mobile hamburger toggle ----
    hamburger.addEventListener('click', () => {
        hamburger.classList.toggle('navbar__hamburger--open');
        mobileMenu.classList.toggle('navbar__mobile-menu--open');
    });

    // ---- Close mobile menu on link click ----
    mobileMenu.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            mobileMenu.classList.remove('navbar__mobile-menu--open');
            hamburger.classList.remove('navbar__hamburger--open');
        });
    });
})();
</script>

<?php if (isset($extra_js)): ?>
    <?php foreach ((array)$extra_js as $js): ?>
        <script src="<?= BASE_PATH ?>js/<?= htmlspecialchars($js) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
