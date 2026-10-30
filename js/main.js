(function () {
    const navbar     = document.getElementById('navbar');
    const hamburger  = document.getElementById('navbar-hamburger');
    const mobileMenu = document.getElementById('navbar-mobile-menu');

    // Hide navbar on scroll down, show on scroll up
    if (navbar) {
        let lastScroll = 0;
        let ticking    = false;

        window.addEventListener('scroll', () => {
            if (ticking) return;
            requestAnimationFrame(() => {
                const current = window.scrollY;

                if (current <= 80) {
                    navbar.classList.remove('navbar--hidden');
                    navbar.classList.remove('navbar--scrolled');
                } else if (current > lastScroll) {
                    navbar.classList.add('navbar--hidden');
                    mobileMenu.classList.remove('navbar__mobile-menu--open');
                    hamburger.classList.remove('navbar__hamburger--open');
                } else {
                    navbar.classList.remove('navbar--hidden');
                    navbar.classList.add('navbar--scrolled');
                }

                lastScroll = current;
                ticking    = false;
            });
            ticking = true;
        });
    }

    // Mobile menu
    if (hamburger && mobileMenu) {
        hamburger.addEventListener('click', () => {
            hamburger.classList.toggle('navbar__hamburger--open');
            mobileMenu.classList.toggle('navbar__mobile-menu--open');
        });

        mobileMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                mobileMenu.classList.remove('navbar__mobile-menu--open');
                hamburger.classList.remove('navbar__hamburger--open');
            });
        });
    }

    /* =====================================================================
       REFACTORING: Duplicated filter logic -> one shared function

       BEFORE: the menu filter and the order filter each had their own
       copy of the same 4 steps:
         1. read the clicked tab's name
         2. highlight the clicked tab
         3. read each card's label
         4. show the card if it matches (or "All"), otherwise hide it

       AFTER: the 4 steps live once in setupFilter(). Each filter only
       passes in what is different about it.
       ===================================================================== */

    // Shared filter function (the 4 steps, written once)
    function setupFilter(tabs, getCards, getCardLabel, markActive) {
        tabs.forEach(tab => {
            tab.addEventListener('click', e => {
                e.preventDefault();
                const label = tab.textContent.trim();          // step 1

                tabs.forEach(t => markActive(t, t === tab));   // step 2

                getCards().forEach(card => {                   // step 3
                    const show = label === 'All' || getCardLabel(card) === label;
                    card.style.display = show ? '' : 'none';   // step 4
                });
            });
        });
    }

    // Menu category filter (buyer/menu.html)

    /* PREVIOUS CODE (replaced by setupFilter):

    const catButtons = document.querySelectorAll('a.filter-btn');
    if (catButtons.length) {
        catButtons.forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                const cat = btn.textContent.trim();

                catButtons.forEach(b => {
                    b.classList.toggle('filter-btn--active', b === btn);
                    b.classList.toggle('filter-btn--inactive', b !== btn);
                });

                document.querySelectorAll('.menu-card').forEach(card => {
                    const badge = card.querySelector('.dish-card__badge');
                    const show  = cat === 'All' || (badge && badge.textContent.trim() === cat);
                    card.style.display = show ? '' : 'none';
                });
            });
        });
    }
    */

    const catButtons = document.querySelectorAll('a.filter-btn');
    if (catButtons.length) {
        setupFilter(
            catButtons,
            () => document.querySelectorAll('.menu-card'),            // which cards to filter
            card => {                                                 // how to read a card's label
                const badge = card.querySelector('.dish-card__badge');
                return badge ? badge.textContent.trim() : '';
            },
            (btn, active) => {                                        // how to highlight a tab
                btn.classList.toggle('filter-btn--active', active);
                btn.classList.toggle('filter-btn--inactive', !active);
            }
        );
    }

    // Order status filter (buyer/orders.html, seller/orders.html)
    const statusTabs = document.querySelectorAll('a[href^="?status="]');
    if (statusTabs.length) {
        const activeStyle   = statusTabs[0].getAttribute('style');
        const inactiveStyle = statusTabs[1].getAttribute('style');
        const list = statusTabs[0].parentElement.nextElementSibling;

        /* PREVIOUS CODE (replaced by setupFilter):

        statusTabs.forEach(tab => {
            tab.addEventListener('click', e => {
                e.preventDefault();
                const label = tab.textContent.trim();

                statusTabs.forEach(t => t.setAttribute('style', t === tab ? activeStyle : inactiveStyle));

                Array.from(list.children).forEach(card => {
                    const badge  = Array.from(card.querySelectorAll('span'))
                        .find(s => s.textContent.trim().startsWith('●'));
                    const status = badge ? badge.textContent.replace('●', '').trim() : '';
                    card.style.display = (label === 'All' || status === label) ? '' : 'none';
                });
            });
        });
        */

        setupFilter(
            statusTabs,
            () => Array.from(list.children),                          // which cards to filter
            card => {                                                 // how to read a card's label
                const badge = Array.from(card.querySelectorAll('span'))
                    .find(s => s.textContent.trim().startsWith('●'));
                return badge ? badge.textContent.replace('●', '').trim() : '';
            },
            (tab, active) => tab.setAttribute('style', active ? activeStyle : inactiveStyle) // how to highlight
        );
    }

    // No backend yet - forms just move to the next page
    document.querySelectorAll('form[action="#"]').forEach(form => {
        form.addEventListener('submit', e => {
            e.preventDefault();

            const role = form.querySelector('input[name="role"]:checked');
            if (role && role.value === 'seller') {
                window.location.href = '../seller/dashboard.html';
                return;
            }

            if (form.dataset.next) window.location.href = form.dataset.next;
        });
    });
})();
