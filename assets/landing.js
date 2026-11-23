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
    let pendingSectionId = null;

    function setActiveLink(sectionId) {
        navLinks.forEach(function (link) {
            const active = link.getAttribute('href') === '#' + sectionId;
            link.classList.toggle('active', active);
            if (active) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        });
    }

    navLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            const target = document.querySelector(link.getAttribute('href'));
            if (target) {
                pendingSectionId = target.id;
                setActiveLink(target.id);
            }
        });
    });

    function updateActiveSection() {
        if (!observedSections.length) return;
        const activationLine = (header ? header.getBoundingClientRect().height : 0) + 80;
        if (pendingSectionId) {
            const pendingSection = document.getElementById(pendingSectionId);
            if (pendingSection && pendingSection.getBoundingClientRect().top > activationLine) {
                setActiveLink(pendingSectionId);
                return;
            }
            pendingSectionId = null;
        }
        let current = observedSections[0];
        observedSections.forEach(function (section) {
            if (section.getBoundingClientRect().top <= activationLine) current = section;
        });
        if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2) {
            current = observedSections[observedSections.length - 1];
        }
        setActiveLink(current.id);
    }

    updateActiveSection();
    window.addEventListener('scroll', updateActiveSection, { passive: true });
})();
