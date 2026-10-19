<?php

session_start();
require_once __DIR__ . "/config/auth.php";

$isLoggedIn = is_logged_in();
$isAdmin = is_admin();
$homeLink = role_home_path();

$firstName = $_SESSION['first_name'] ?? '';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="BloodLife connects people who want to donate blood with a community dedicated to helping people in need."
    >

    <title>BloodLife | Donate Blood. Save Lives.</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="assets/landing.css"
    >

    <link rel="icon" href="images/blood-drop.png" type="image/png">

</head>

<body>

    <!-- =========================
         HEADER
    ========================== -->

    <header class="site-header" id="siteHeader">

        <div class="container nav-container">

            <a href="index.php" class="brand" aria-label="BloodLife Home">

                <!-- <span class="brand-icon" aria-hidden="true">
                    ♥
                </span> -->

                <span class="brand-name">
                    BloodLife
                </span>

            </a>

            <button
                class="menu-toggle"
                id="menuToggle"
                type="button"
                aria-label="Open navigation menu"
                aria-expanded="false"
                aria-controls="mainNavigation"
            >
                <span></span>
                <span></span>
                <span></span>
            </button>

            <nav
                class="main-nav"
                id="mainNavigation"
                aria-label="Main navigation"
            >

                <a href="#home" class="nav-link active">
                    Home
                </a>

                <a href="#about" class="nav-link">
                    About
                </a>

                <a href="#how-it-works" class="nav-link">
                    How It Works
                </a>

                <a href="#why-donate" class="nav-link">
                    Why Donate
                </a>

                <a href="#contact" class="nav-link">
                    Contact
                </a>

                <div class="nav-actions">

                    <?php if ($isLoggedIn): ?>

                        <a
                            href="<?= htmlspecialchars($homeLink, ENT_QUOTES, 'UTF-8') ?>"
                            class="btn btn-outline"
                        >
                            <?= $isAdmin ? 'Admin Panel' : 'Dashboard' ?>
                        </a>

                    <?php else: ?>

                        <a
                            href="login.php"
                            class="btn btn-outline"
                        >
                            Login
                        </a>

                        <a
                            href="register.php"
                            class="btn btn-primary"
                        >
                            Become a Donor
                        </a>

                    <?php endif; ?>

                </div>

            </nav>

        </div>

    </header>


    <main>

        <!-- =========================
             HERO
        ========================== -->

        <section
            class="hero section"
            id="home"
        >

            <div class="container hero-grid">

                <div class="hero-content reveal">

                    <!-- <span class="eyebrow">

                        <span class="eyebrow-dot"></span>

                        Every donation matters

                    </span> -->

                    <h1>

                        Donate Blood.
                        <span>Save Lives.</span>

                    </h1>

                    <p class="hero-text">

                        Your donation can give someone another chance
                        at life. Join BloodLife and become part of a
                        community dedicated to helping people in need.

                    </p>

                    <div class="hero-actions">

                        <?php if ($isLoggedIn): ?>

                            <a
                                href="<?= htmlspecialchars($homeLink, ENT_QUOTES, 'UTF-8') ?>"
                                class="btn btn-primary btn-large"
                            >
                                <?= $isAdmin ? 'Go to Admin Panel' : 'Go to Dashboard' ?>
                            </a>

                        <?php else: ?>

                            <a
                                href="register.php"
                                class="btn btn-primary btn-large"
                            >
                                Become a Donor
                            </a>

                        <?php endif; ?>

                        <a
                            href="#about"
                            class="btn btn-secondary btn-large"
                        >
                            Learn More
                        </a>

                    </div>

                    <div class="hero-trust">

                        <span class="trust-icon">
                            ♥
                        </span>

                        <span>
                            Every donation can make a difference.
                        </span>

                    </div>

                </div>

<!-- 
                <div class="hero-visual reveal">

                    <div class="hero-image-wrapper">

                        <img
                            src="images/side_login2.png"
                            alt="Hands offering a drop of blood, representing donation"
                            class="hero-image"
                        >

                        <div class="image-gradient"></div>

                        <div class="floating-card">

                            <div class="floating-icon">
                                ♥
                            </div>

                            <div>

                                <strong>
                                    One donation
                                </strong>

                                <span>
                                    can help save lives.
                                </span>

                            </div>

                        </div>

                    </div>

                    <div class="hero-decoration decoration-one"></div>
                    <div class="hero-decoration decoration-two"></div>

                </div> -->

            </div>

        </section>


        <!-- =========================
             STATISTICS
        ========================== -->

        <section class="stats-section">

            <div class="container">

                <div class="stats-grid">

                    <article class="stat-card reveal">

                        <div class="stat-icon">
                            ♥
                        </div>

                        <div>

                            <h3>
                                1 Donation
                            </h3>

                            <p>
                                Can help multiple patients
                            </p>

                        </div>

                    </article>


                    <article class="stat-card reveal">

                        <div class="stat-icon">
                            +
                        </div>

                        <div>

                            <h3>
                                8 Blood Types
                            </h3>

                            <p>
                                Every blood type matters
                            </p>

                        </div>

                    </article>


                    <article class="stat-card reveal">

                        <div class="stat-icon">
                            24
                        </div>

                        <div>

                            <h3>
                                24/7 Need
                            </h3>

                            <p>
                                Hospitals constantly need blood
                            </p>

                        </div>

                    </article>


                    <article class="stat-card reveal">

                        <div class="stat-icon">
                            ♡
                        </div>

                        <div>

                            <h3>
                                1 Community
                            </h3>

                            <p>
                                People helping people
                            </p>

                        </div>

                    </article>

                </div>

            </div>

        </section>


        <!-- =========================
             ABOUT
        ========================== -->

        <section
            class="section about-section"
            id="about"
        >

            <div class="container about-grid">

                <div class="about-visual reveal">

                    <div class="about-image-wrapper">

                        <img
                            src="images/donation_illustration.png"
                            alt="A hand offering a heart, representing the gift of blood donation"
                            class="about-image"
                        >

                        <div class="about-badge">

                            <span class="about-badge-icon">
                                ♥
                            </span>

                            <div>

                                <strong>
                                    Give with purpose
                                </strong>

                                <span>
                                    Help your community
                                </span>

                            </div>

                        </div>

                    </div>

                </div>


                <div class="about-content reveal">

                    <span class="section-label">
                        About BloodLife
                    </span>

                    <h2>
                        A Simple Way to
                        <span>Make a Difference.</span>
                    </h2>

                    <p>

                        BloodLife makes it easier for people to become
                        blood donors and contribute to a community where
                        every donation matters.

                    </p>

                    <p>

                        Our goal is to encourage more people to donate,
                        raise awareness about blood donation, and help
                        build a stronger network of donors.

                    </p>

                    <!-- <div class="about-highlight">

                        <span class="highlight-line"></span>

                        <p>
                            Together, small acts of generosity can
                            create meaningful change.
                        </p>

                    </div> -->

                    <?php if (!$isLoggedIn): ?>

                        <a
                            href="register.php"
                            class="text-link"
                        >
                            Join Now
                            <span aria-hidden="true">→</span>
                        </a>

                    <?php endif; ?>

                </div>

            </div>

        </section>


        <!-- =========================
             HOW IT WORKS
        ========================== -->

        <section
            class="section process-section"
            id="how-it-works"
        >

            <div class="container">

                <div class="section-heading reveal">

                    <span class="section-label">
                        Simple process
                    </span>

                    <h2>
                        How BloodLife Works
                    </h2>

                    <p>

                        Getting involved is simple. Start your journey
                        and become part of a community that cares.

                    </p>

                </div>


                <div class="process-grid">

                    <article class="process-card reveal">

                        <div class="step-number">
                            01
                        </div>

                        <div class="process-icon">
                            ♡
                        </div>

                        <h3>
                            Create Your Account
                        </h3>

                        <p>

                            Register with your basic information
                            and blood type.

                        </p>

                    </article>


                    <article class="process-card reveal">

                        <div class="step-number">
                            02
                        </div>

                        <div class="process-icon">
                            ♥
                        </div>

                        <h3>
                            Become a Donor
                        </h3>

                        <p>

                            Join the BloodLife community and be ready
                            to help when your blood type is needed.

                        </p>

                    </article>


                    <article class="process-card reveal">

                        <div class="step-number">
                            03
                        </div>

                        <div class="process-icon">
                            ✓
                        </div>

                        <h3>
                            Help Save Lives
                        </h3>

                        <p>

                            Your willingness to donate can make a
                            meaningful difference to someone in need.

                        </p>

                    </article>

                </div>

            </div>

        </section>


        <!-- =========================
             WHY DONATE
        ========================== -->

        <section
            class="section why-section"
            id="why-donate"
        >

            <div class="container">

                <div class="section-heading reveal">

                    <span class="section-label">
                        Why donate?
                    </span>

                    <h2>
                        Why Donate Blood?
                    </h2>

                    <p>

                        Your decision to donate can be a simple
                        act of kindness with a meaningful impact.

                    </p>

                </div>


                <div class="benefits-grid">

                    <article class="benefit-card reveal">

                        <!-- <div class="benefit-icon">
                            ♥
                        </div> -->

                        <div>

                            <h3>
                                Help Someone in Need
                            </h3>

                            <p>

                                Your blood may be needed during
                                emergencies, surgeries, and
                                medical treatments.

                            </p>

                        </div>

                    </article>


                    <article class="benefit-card reveal">

                        <!-- <div class="benefit-icon">
                            ♡
                        </div> -->

                        <div>

                            <h3>
                                Support Your Community
                            </h3>

                            <p>

                                Become part of a community where
                                people help one another.

                            </p>

                        </div>

                    </article>


                    <article class="benefit-card reveal">

                        <!-- <div class="benefit-icon">
                            +
                        </div> -->

                        <div>

                            <h3>
                                Make a Difference
                            </h3>

                            <p>

                                A small act of generosity can have
                                a significant impact.

                            </p>

                        </div>

                    </article>


                    <article class="benefit-card reveal">

                        <!-- <div class="benefit-icon">
                            ✓
                        </div> -->

                        <div>

                            <h3>
                                Be There When It Matters
                            </h3>

                            <p>

                                Blood cannot always be manufactured
                                or replaced. Donors are essential.

                            </p>

                        </div>

                    </article>

                </div>

            </div>

        </section>


        <!-- =========================
             BLOOD TYPES
        ========================== -->

        <section class="blood-types-section">

            <div class="container blood-types-content">

                <div class="blood-types-copy reveal">

                    <span class="section-label light">
                        Every type matters
                    </span>

                    <h2>
                        Every Blood Type Matters.
                    </h2>

                    <p>

                        Whatever your blood type, your willingness
                        to donate can help someone in need.

                    </p>

                </div>


                <div class="blood-types-list reveal">

                    <span class="blood-type-badge">
                        A+
                    </span>

                    <span class="blood-type-badge">
                        A-
                    </span>

                    <span class="blood-type-badge">
                        B+
                    </span>

                    <span class="blood-type-badge">
                        B-
                    </span>

                    <span class="blood-type-badge">
                        AB+
                    </span>

                    <span class="blood-type-badge">
                        AB-
                    </span>

                    <span class="blood-type-badge">
                        O+
                    </span>

                    <span class="blood-type-badge">
                        O-
                    </span>

                </div>

            </div>

        </section>


        <!-- =========================
             CTA
        ========================== -->

        <section class="cta-section">

            <div class="cta-decoration cta-decoration-one">
                ♥
            </div>

            <div class="cta-decoration cta-decoration-two">
                +
            </div>

            <div class="container cta-content reveal">

                <span class="section-label light">
                    Make a difference
                </span>

                <h2>
                    Ready to Make a Difference?
                </h2>

                <p>

                    Join BloodLife today and become part of a
                    community that believes every life is worth helping.

                </p>

                <div class="cta-actions">

                    <?php if ($isLoggedIn): ?>

                        <a
                            href="<?= htmlspecialchars($homeLink, ENT_QUOTES, 'UTF-8') ?>"
                            class="btn btn-white btn-large"
                        >
                            <?= $isAdmin ? 'Go to Admin Panel' : 'Go to Dashboard' ?>
                        </a>

                    <?php else: ?>

                        <a
                            href="register.php"
                            class="btn btn-white btn-large"
                        >
                            Become a Donor
                        </a>

                        <a
                            href="login.php"
                            class="cta-login-link"
                        >
                            Already have an account? Login
                        </a>

                    <?php endif; ?>

                </div>

            </div>

        </section>


        <!-- =========================
             CONTACT
        ========================== -->

        <section
            class="contact-section"
            id="contact"
        >

            <div class="container contact-content reveal">

                <span class="section-label">
                    Contact
                </span>

                <h2>
                    Have Questions About BloodLife?
                </h2>

                <p>

                    We believe that making blood donation easier
                    starts with keeping our community informed.

                </p>

                <a
                    href="mailto:contact@bloodlife.example"
                    class="text-link"
                >
                    Get in touch
                    <span aria-hidden="true">→</span>
                </a>

            </div>

        </section>

    </main>


    <!-- =========================
         FOOTER
    ========================== -->

    <footer class="site-footer">

        <div class="container footer-grid">

            <div class="footer-brand">

                <a
                    href="index.php"
                    class="brand footer-logo"
                >

                    <span class="brand-icon">
                        ♥
                    </span>

                    <span class="brand-name">
                        BloodLife
                    </span>

                </a>

                <p>

                    Connecting people through the simple act
                    of giving blood.

                </p>

            </div>


            <div class="footer-column">

                <h3>
                    Explore
                </h3>

                <a href="#home">
                    Home
                </a>

                <a href="#about">
                    About
                </a>

                <a href="#how-it-works">
                    How It Works
                </a>

                <a href="#why-donate">
                    Why Donate
                </a>

                <a href="#contact">
                    Contact
                </a>

            </div>


            <div class="footer-column">

                <h3>
                    Account
                </h3>

                <?php if ($isLoggedIn): ?>

                    <a href="<?= htmlspecialchars($homeLink, ENT_QUOTES, 'UTF-8') ?>">
                        <?= $isAdmin ? 'Admin Panel' : 'Dashboard' ?>
                    </a>

                <?php else: ?>

                    <a href="login.php">
                        Login
                    </a>

                    <a href="register.php">
                        Become a Donor
                    </a>

                <?php endif; ?>

            </div>


            <div class="footer-column">

                <h3>
                    Legal
                </h3>

                <a href="#">
                    Privacy Policy
                </a>

                <a href="#">
                    Terms &amp; Conditions
                </a>

            </div>

        </div>


        <div class="container footer-bottom">

            <p>
                © <?= date('Y') ?> BloodLife. All rights reserved.
            </p>

            <p>
                Donate Blood. Save Lives.
            </p>

        </div>

    </footer>


    <script>

        /*
        |--------------------------------------------------------------------------
        | MOBILE NAVIGATION
        |--------------------------------------------------------------------------
        */

        const menuToggle = document.getElementById("menuToggle");
        const mainNavigation = document.getElementById("mainNavigation");

        if (menuToggle && mainNavigation) {

            menuToggle.addEventListener("click", function () {

                const isOpen =
                    mainNavigation.classList.toggle("open");

                menuToggle.classList.toggle("active", isOpen);

                menuToggle.setAttribute(
                    "aria-expanded",
                    isOpen ? "true" : "false"
                );

            });


            document
                .querySelectorAll(".nav-link, .nav-actions a")
                .forEach(function (link) {

                    link.addEventListener("click", function () {

                        mainNavigation.classList.remove("open");

                        menuToggle.classList.remove("active");

                        menuToggle.setAttribute(
                            "aria-expanded",
                            "false"
                        );

                    });

                });

        }


        /*
        |--------------------------------------------------------------------------
        | HEADER SCROLL EFFECT
        |--------------------------------------------------------------------------
        */

        const siteHeader =
            document.getElementById("siteHeader");

        window.addEventListener("scroll", function () {

            if (!siteHeader) {
                return;
            }

            if (window.scrollY > 20) {

                siteHeader.classList.add("scrolled");

            } else {

                siteHeader.classList.remove("scrolled");

            }

        });


        /*
        |--------------------------------------------------------------------------
        | SCROLL REVEAL
        |--------------------------------------------------------------------------
        */

        const revealElements =
            document.querySelectorAll(".reveal");

        const revealObserver =
            new IntersectionObserver(

                function (entries, observer) {

                    entries.forEach(function (entry) {

                        if (entry.isIntersecting) {

                            entry.target.classList.add("visible");

                            observer.unobserve(entry.target);

                        }

                    });

                },

                {
                    threshold: 0.12
                }

            );


        revealElements.forEach(function (element) {

            revealObserver.observe(element);

        });


        /*
        |--------------------------------------------------------------------------
        | ACTIVE NAVIGATION
        |--------------------------------------------------------------------------
        */

        const sections =
            document.querySelectorAll("section[id]");

        const navLinks =
            document.querySelectorAll(".nav-link");

        const sectionObserver =
            new IntersectionObserver(

                function (entries) {

                    entries.forEach(function (entry) {

                        if (entry.isIntersecting) {

                            navLinks.forEach(function (link) {

                                link.classList.remove("active");

                            });

                            const activeLink =
                                document.querySelector(
                                    '.nav-link[href="#' +
                                    entry.target.id +
                                    '"]'
                                );

                            if (activeLink) {

                                activeLink.classList.add("active");

                            }

                        }

                    });

                },

                {
                    rootMargin: "-30% 0px -60% 0px"
                }

            );


        sections.forEach(function (section) {

            sectionObserver.observe(section);

        });

    </script>

</body>

</html>
