<?php
session_start();
require_once __DIR__ . '/config/auth.php';

$isLoggedIn = is_logged_in();
$isAdmin = is_admin();
$isClinic = is_clinic();
$homeLink = role_home_path();
$memberHome = 'dashboard/dashboard.php';
$clinicHome = 'clinic/dashboard.php';
$adminHome = 'admin/dashboard.php';
$requestHref = !$isLoggedIn
    ? 'register.php'
    : ($isAdmin ? $adminHome . '?page=requests' : ($isClinic ? $clinicHome . '?page=requests' : $memberHome . '?page=blood-requests'));
$offerHref = !$isLoggedIn
    ? 'register.php'
    : ($isAdmin ? $homeLink : ($isClinic ? $clinicHome . '?page=donation-offers' : $memberHome . '?page=donation-offers'));
$mapHref = !$isLoggedIn
    ? 'login.php'
    : ($isAdmin ? $homeLink : ($isClinic ? $clinicHome . '?page=blood-map' : $memberHome . '?page=blood-map'));
$dashboardLabel = $isAdmin ? 'Admin Panel' : ($isClinic ? 'Facility Panel' : 'Dashboard');

function landing_icon(string $name, string $class = ''): string
{
    $icons = [
        'drop' => '<path d="M12 3.2c-2.7 3.6-6.6 7.6-6.6 11.8a6.6 6.6 0 0 0 13.2 0c0-4.2-3.9-8.2-6.6-11.8Z"/><path d="M9.1 15.3a3.2 3.2 0 0 0 3.2 3.2"/>',
        'offer' => '<path d="M7.2 14.8c1.1-.9 2.1-1.4 3.2-1.4h3.1a1.7 1.7 0 0 1 0 3.4h-3"/><path d="m7.1 14.9 2.1 2.3c.8.9 1.9 1.3 3.1 1.3h4.5a2.1 2.1 0 0 0 1.4-.5l3-2.5a1.8 1.8 0 0 0-2.2-2.8l-2.2 1.5"/><path d="M2.8 13.4h4.1v7H2.8zM14 3.2a4 4 0 0 0-5.7 0L7.2 4.3 6.1 3.2a4 4 0 0 0-5.7 5.7l1.1 1.1 5.7 5.7 5.7-5.7 1.1-1.1A4 4 0 0 0 14 3.2Z" transform="translate(5 0) scale(.8)"/>',
        'map' => '<path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z"/><path d="M9 3v15m6-12v15"/><path d="M12 8.5a2.4 2.4 0 1 0 0 4.8c1.3 0 2.4-1.1 2.4-2.4S13.3 8.5 12 8.5Z"/>',
        'community' => '<path d="M4 5.5h16v11H9l-5 3v-14Z"/><path d="M8 9h8m-8 4h5"/>',
        'message' => '<path d="M4 5.5h16v11H9l-5 3v-14Z"/><path d="M8 9h8m-8 4h5"/><path d="M7 2.5h13"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18m-13 4h2m4 0h2m-8 3h2"/>',
        'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/><path d="M19 5v6m-3-3h6"/>',
        'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2.8h6V4m-7 6h8m-8 4h8m-8 4h5"/>',
        'shield' => '<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/>',
        'arrow' => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'plus' => '<path d="M12 5v14m-7-7h14"/>',
        'heart' => '<path d="M20.8 8.8c0 5.1-8.8 10-8.8 10s-8.8-4.9-8.8-10a4.6 4.6 0 0 1 8.8-1.9 4.6 4.6 0 0 1 8.8 1.9Z"/>',
        'hospital' => '<path d="M3 21h18M5 21V7h14v14M9 7V3h6v4m-7 5h2m4 0h2m-8 4h2m4 0h2m-4-5v4m-2-2h4"/>',
    ];
    $body = $icons[$name] ?? $icons['heart'];
    $classAttr = $class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' : '';
    return '<svg' . $classAttr . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $body . '</svg>';
}

$features = [
    ['drop', 'Blood Requests', 'Publish a request or browse active requests by blood type, urgency, and location.'],
    ['offer', 'Donation Offers', 'Share your willingness and availability to help someone in the community.'],
    ['map', 'Blood Donation Map', 'Explore active requests and available offers through the location-based map.'],
    ['community', 'Community Feed', 'Share updates, awareness posts, and donation event announcements.'],
    ['message', 'Private Messaging', 'Continue conversations connected to accepted requests and offers.'],
    ['calendar', 'Donation Events', 'Discover event announcements shared by members and participating facilities.'],
];
$steps = [
    ['user', 'Create an account', 'Register as a member, then complete the profile details needed to participate.'],
    ['drop', 'Request or offer blood', 'Publish a blood request when help is needed, or share a donation offer.'],
    ['community', 'Connect with the community', 'Explore relevant posts and continue eligible conversations through Messages.'],
    ['shield', 'Coordinate responsibly', 'Confirm availability and next steps with the requester, donor, and qualified healthcare professionals.'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="BLife PH connects people who need blood with community members willing to help and participating healthcare institutions.">
    <meta name="theme-color" content="#dc2626">
    <title>BLife PH | Connecting Donors. Saving Lives.</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="images/blood-drop.png" type="image/png">
    <link rel="stylesheet" href="assets/landing.css">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="site-header" id="siteHeader">
    <div class="container nav-shell">
        <a class="brand" href="index.php" aria-label="BLife PH home"><img class="brand-logo" src="images/BlifePH_no_bg.png" alt="BLife PH" width="70" height="70"></a>
        <button class="menu-toggle" id="menuToggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="mainNavigation">
            <span></span><span></span><span></span>
        </button>
        <nav class="main-nav" id="mainNavigation" aria-label="Main navigation">
            <div class="nav-links">
                <a class="nav-link active" href="#home">Home</a>
                <a class="nav-link" href="#features">Features</a>
                <a class="nav-link" href="#how-it-works">How It Works</a>
                <a class="nav-link" href="#about">About Us</a>
                <a class="nav-link" href="#contact">Contact</a>
            </div>
            <div class="nav-actions">
                <?php if ($isLoggedIn): ?>
                    <a class="btn btn-quiet" href="<?= htmlspecialchars($homeLink, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($dashboardLabel, ENT_QUOTES, 'UTF-8') ?></a>
                <?php else: ?>
                    <a class="btn btn-quiet" href="login.php">Sign In</a>
                    <a class="btn btn-primary btn-small" href="register.php">Get Started <?= landing_icon('arrow') ?></a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>

<main id="main-content">
    <section class="hero" id="home" aria-labelledby="hero-title">
        <div class="hero-glow hero-glow-one" aria-hidden="true"></div>
        <div class="hero-glow hero-glow-two" aria-hidden="true"></div>
        <div class="container hero-grid">
            <div class="hero-copy reveal">
                <h1 id="hero-title">Connecting Donors.<br><span>Saving Lives.</span></h1>
                <p class="hero-lead">BLife PH connects people requesting blood with community members ready to help.</p>
                <div class="hero-actions">
                    <?php if ($isAdmin): ?>
                        <a class="btn btn-primary btn-large" href="<?= htmlspecialchars($requestHref, ENT_QUOTES, 'UTF-8') ?>">Manage Blood Requests <?= landing_icon('arrow') ?></a>
                        <a class="btn btn-secondary btn-large" href="<?= htmlspecialchars($homeLink, ENT_QUOTES, 'UTF-8') ?>">Open Admin Panel</a>
                    <?php else: ?>
                        <a class="btn btn-primary btn-large" href="<?= htmlspecialchars($requestHref, ENT_QUOTES, 'UTF-8') ?>">Request Blood <?= landing_icon('arrow') ?></a>
                        <a class="btn btn-secondary btn-large" href="<?= htmlspecialchars($offerHref, ENT_QUOTES, 'UTF-8') ?>">Offer to Donate</a>
                    <?php endif; ?>
                </div>
                <p class="hero-note"><?= landing_icon('shield') ?> BLife PH helps people connect; care decisions remain with qualified healthcare professionals.</p>
            </div>

        </div>
    </section>

    <section class="section features-section" id="features" aria-labelledby="features-title">
        <div class="container">
            <div class="section-heading reveal"><span class="section-kicker">One platform, shared purpose</span><h2 id="features-title">Connect and Help</h2></div>
            <div class="feature-grid">
                <?php foreach ($features as [$icon, $title, $description]): ?>
                    <article class="feature-card reveal"><span class="feature-icon"><?= landing_icon($icon) ?></span><h3><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p></article>
                <?php endforeach; ?>
            </div>
            <p class="feature-footnote">Some features require sign-in. Events appear when announced by members or participating facilities.</p>
        </div>
    </section>

    <section class="section process-section" id="how-it-works" aria-labelledby="process-title">
        <div class="container">
            <div class="section-heading reveal"><span class="section-kicker">Getting started</span><h2 id="process-title">How BLife PH Works</h2></div>
            <ol class="steps-grid">
                <?php foreach ($steps as $index => [$icon, $title, $description]): ?>
                    <li class="step-card reveal"><span class="step-count">0<?= $index + 1 ?></span><span class="step-icon"><?= landing_icon($icon) ?></span><h3><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p></li>
                <?php endforeach; ?>
            </ol>
            <p class="process-note"><?= landing_icon('shield') ?> New members are asked to complete their required profile details after signing in. Donor availability is optional.</p>
        </div>
    </section>

    <section class="section map-section" id="blood-map-preview" aria-labelledby="map-title">
        <div class="container map-grid">
            <div class="map-copy reveal"><span class="section-kicker">Location-based discovery</span><h2 id="map-title">Explore Blood Posts <span>on the Map</span></h2><p>View active requests and available offers by location. Sign in to search and filter mapped posts.</p><a class="btn btn-primary" href="<?= htmlspecialchars($mapHref, ENT_QUOTES, 'UTF-8') ?>"><?= $isLoggedIn && !$isAdmin ? 'Explore Blood Map' : ($isAdmin ? 'Open Admin Panel' : 'Sign in to Explore') ?> <?= landing_icon('arrow') ?></a><p class="map-privacy-note">Map locations are approximate. The preview shows no live posts or personal information.</p></div>
            <div class="map-preview-card reveal" aria-label="Illustrative preview of the BLife PH location map">
                <div class="map-preview-toolbar"><span class="preview-brand-dot"></span><span>BLife PH <small>Blood Donation Map</small></span><span class="preview-label">Illustration</span></div>
                <div class="map-art" aria-hidden="true"><div class="map-grid-lines"></div><svg viewBox="0 0 620 360" preserveAspectRatio="xMidYMid slice"><path class="map-waterway" d="M-20 275c85-78 120-45 191-118s118-16 174-72 116 1 168-52 85-36 128-67"/><path class="map-road road-one" d="M-20 70c85 44 128 18 205 82s118 76 184 38 135 25 271 92"/><path class="map-road road-two" d="M92-20c-7 76 24 111 6 167s27 111 87 155 65 48 79 78"/><path class="map-road road-three" d="M414-20c-28 73-14 119 42 162s-6 106 54 153 82 42 117 69"/><path class="map-district" d="M82 36c36-28 68-24 92 6l-4 45c-25 32-62 40-95 21l-19-34 26-38Z"/><path class="map-district" d="M240 146c38-24 73-18 91 14l-7 47c-31 27-68 22-95-7l11-54Z"/><path class="map-district" d="M421 214c35-25 65-18 84 12l-4 42c-29 22-60 19-82-9l2-45Z"/></svg><div class="map-preview-callout"><span><?= landing_icon('map') ?></span><strong>Explore mapped posts</strong><small>Sign in to view current requests and offers</small></div><div class="map-legend-preview"><span><i class="legend-dot request-dot"></i> Blood request</span><span><i class="legend-dot offer-dot"></i> Donation offer</span></div></div>
                <div class="map-preview-footer"><span><?= landing_icon('shield') ?> Private details stay in authenticated features</span><span class="map-zoom-mark">+</span></div>
            </div>
        </div>
    </section>

    <section class="section about-section" id="about" aria-labelledby="about-title">
        <div class="container about-grid">
            <div class="about-visual reveal"><div class="about-emblem"><div class="emblem-ring"></div><div class="emblem-drop"><?= landing_icon('drop') ?></div><span class="emblem-node node-one"><?= landing_icon('user') ?></span><span class="emblem-node node-two"><?= landing_icon('hospital') ?></span><span class="emblem-node node-three"><?= landing_icon('heart') ?></span><div class="emblem-caption"><strong>Every drop matters</strong><span>Connection begins with community</span></div></div></div>
            <div class="about-copy reveal"><span class="section-kicker">About BLife PH</span><h2 id="about-title">A Community Where <span>Every Drop Matters</span></h2><p>BLife PH helps people and authorized healthcare facilities share blood requests, offer donations, and coordinate with their communities.</p></div>
        </div>
    </section>

    <section class="section why-section" id="why-donate" aria-labelledby="why-title">
        <div class="container why-grid"><div class="why-copy reveal"><span class="section-kicker">Responsible participation</span><h2 id="why-title">Why Blood Donation Matters</h2><p>Voluntary donation supports the blood supply healthcare teams may need. Blood type labels use the ABO system and Rh factor, but medical professionals must assess eligibility and compatibility.</p></div><div class="blood-type-panel reveal"><div class="blood-type-panel-head"><span class="type-icon"><?= landing_icon('drop') ?></span><div><strong>Blood types</strong><small>ABO and Rh</small></div></div><div class="blood-types-list" aria-label="Common blood type labels"><span>A+</span><span>A−</span><span>B+</span><span>B−</span><span>AB+</span><span>AB−</span><span>O+</span><span>O−</span></div><p>Eligibility and compatibility are assessed by healthcare professionals.</p></div></div>
    </section>

    <section class="section faq-section" id="faq" aria-labelledby="faq-title">
        <div class="container faq-layout"><div class="faq-intro reveal"><span class="section-kicker">Need to know</span><h2 id="faq-title">Frequently Asked Questions</h2></div><div class="faq-list reveal">
            <details><summary>What is BLife PH?</summary><p>A community platform for blood requests, donation offers, mapped posts, and supported member conversations. Authorized facilities also have a dedicated panel.</p></details>
            <details><summary>How do I request or offer blood?</summary><p>Register, sign in, and complete the required profile details. Then publish a request or donation offer from your dashboard. Healthcare professionals assess donation eligibility.</p></details>
            <details><summary>Can I find posts near me?</summary><p>Yes. The Blood Donation Map lets signed-in users search locations and filter active mapped requests and available offers.</p></details>
            <details><summary>How do I contact another member?</summary><p>Use Messages or the conversation link for a supported request or offer.</p></details>
            <details><summary>Does BLife PH collect or distribute blood?</summary><p>No. BLife PH helps people connect; it does not collect or distribute blood and does not replace medical evaluation or emergency care.</p></details>
        </div></div>
    </section>

    <section class="contact-strip" id="contact" aria-labelledby="contact-title"><div class="container contact-inner"><div><span class="section-kicker">Contact</span><h2 id="contact-title">Need help?</h2><p>For clinical questions, contact your healthcare facility. A public BLife PH support contact is not currently listed.</p></div></div></section>
</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand"><a class="brand" href="index.php" aria-label="BLife PH home"><img class="brand-logo" src="images/BlifePH_no_bg.png" alt="BLife PH" width="70" height="70"></a><p>A community platform for blood requests, donation offers, and connection.</p><small class="footer-disclaimer">Not a substitute for medical evaluation or emergency care.</small></div>
        <div class="footer-column"><h2>Explore</h2><a href="#features">Features</a><a href="#how-it-works">How It Works</a><a href="#about">About Us</a><a href="#healthcare-partners">For Facilities</a><a href="#contact">Contact</a></div>
        <div class="footer-column"><h2>Platform</h2><a href="<?= htmlspecialchars($requestHref, ENT_QUOTES, 'UTF-8') ?>">Blood Requests</a><a href="<?= htmlspecialchars($offerHref, ENT_QUOTES, 'UTF-8') ?>">Donation Offers</a><a href="<?= htmlspecialchars($mapHref, ENT_QUOTES, 'UTF-8') ?>">Blood Donation Map</a><a href="#faq">FAQs</a></div>
        <div class="footer-column footer-legal"><h2>Policies</h2><span>Privacy Policy <small>Not published yet</small></span><span>Terms &amp; Conditions <small>Not published yet</small></span><a href="login.php">Account Sign In</a></div>
    </div>
    <div class="container footer-bottom"><span>© <?= date('Y') ?> BLife PH. All rights reserved.</span></div>
</footer>
<script src="assets/landing.js" defer></script>
</body>
</html>
