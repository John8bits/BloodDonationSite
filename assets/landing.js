(function () {
    'use strict';

    const body = document.body;
    const header = document.getElementById('siteHeader');
    const menuButton = document.getElementById('menuToggle');
    const navigation = document.getElementById('mainNavigation');
    const navLinks = Array.from(document.querySelectorAll('.nav-link[href^="#"]'));

    body.classList.add('js-ready');

    function setMenuOpen(open) {
        if (!menuButton || !navigation) return;
        navigation.classList.toggle('open', open);
        menuButton.classList.toggle('active', open);
        menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        menuButton.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
        body.classList.toggle('menu-open', open && window.matchMedia('(max-width: 800px)').matches);
    }

    if (menuButton && navigation) {
        menuButton.addEventListener('click', function () {
            setMenuOpen(menuButton.getAttribute('aria-expanded') !== 'true');
        });

        navigation.addEventListener('click', function (event) {
            if (event.target.closest('a')) setMenuOpen(false);
        });

        document.addEventListener('click', function (event) {
            if (!navigation.classList.contains('open')) return;
            if (navigation.contains(event.target) || menuButton.contains(event.target)) return;
            setMenuOpen(false);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && navigation.classList.contains('open')) {
                setMenuOpen(false);
                menuButton.focus();
            }
        });

        window.addEventListener('resize', function () {
            if (!window.matchMedia('(max-width: 800px)').matches) setMenuOpen(false);
        }, { passive: true });
    }

    function updateHeader() {
        if (header) header.classList.toggle('scrolled', window.scrollY > 12);
    }
    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });

    const revealItems = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver(function (entries, observer) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -24px 0px' });
        revealItems.forEach(function (item) { revealObserver.observe(item); });
    } else {
        revealItems.forEach(function (item) { item.classList.add('visible'); });
    }

    const observedSections = navLinks
        .map(function (link) { return document.querySelector(link.getAttribute('href')); })
        .filter(Boolean);

    function setActiveLink(sectionId) {
        navLinks.forEach(function (link) {
            const active = link.getAttribute('href') === '#' + sectionId;
            link.classList.toggle('active', active);
            if (active) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        });
    }

    if ('IntersectionObserver' in window && observedSections.length) {
        const sectionObserver = new IntersectionObserver(function (entries) {
            const visible = entries
                .filter(function (entry) { return entry.isIntersecting; })
                .sort(function (a, b) { return b.intersectionRatio - a.intersectionRatio; });
            if (visible.length) setActiveLink(visible[0].target.id);
        }, { rootMargin: '-22% 0px -65% 0px', threshold: [0, .15, .35, .6] });
        observedSections.forEach(function (section) { sectionObserver.observe(section); });
    }
})();
