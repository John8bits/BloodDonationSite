<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/location_picker.php';
require_once __DIR__ . '/../includes/blood_donation_map.php';
require_login('../login.php');
if (is_admin()) {
    header('Location: ../admin/dashboard.php');
    exit;
}
if (!is_clinic()) {
    header('Location: ../dashboard/dashboard.php');
    exit;
}
if (!isset($_SESSION['csrf_token']))
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

function clinic_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function clinic_sidebar_navigation(array $groups, string $activePage): void
{
    $icons = ['overview' => 'fa-gauge-high', 'requests' => 'fa-droplet', 'donation-offers' => 'fa-hand-holding-droplet', 'blood-map' => 'fa-map-location-dot', 'events' => 'fa-calendar-days', 'responses' => 'fa-handshake', 'donation-records' => 'fa-clipboard-check', 'messages' => 'fa-comments', 'notifications' => 'fa-bell', 'profile' => 'fa-hospital', 'settings' => 'fa-gear'];
    ?>
    <nav aria-label="Clinic navigation">
        <?php foreach ($groups as $groupLabel => $links): ?>
            <div class="clinic-nav-group">
                <p class="clinic-sidebar-section-label"><?= clinic_h($groupLabel) ?></p>
                <?php foreach ($links as $key => $label): ?>
                    <a href="dashboard.php?page=<?= clinic_h($key) ?>" class="<?= $activePage === $key ? 'active' : '' ?>"
                        <?= $activePage === $key ? 'aria-current="page"' : '' ?>><i
                            class="fa-solid <?= clinic_h($icons[$key] ?? 'fa-circle') ?>"
                            aria-hidden="true"></i><span><?= clinic_h($label) ?></span></a>
                <?php endforeach; ?>
                <?php if ($groupLabel === 'Account'): ?><a href="dashboard.php?page=settings"
                        class="<?= $activePage === 'settings' ? 'active' : '' ?>" <?= $activePage === 'settings' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-gear" aria-hidden="true"></i><span>Settings</span></a><a
                        href="../logout.php"><i class="fa-solid fa-right-from-bracket"
                            aria-hidden="true"></i><span>Logout</span></a><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </nav>
    <?php
}
$pdo = (new Database())->getConnection();
$userId = (int) current_user_id();
$accountStmt = $pdo->prepare("SELECT u.account_status,u.must_change_password,cp.* FROM uinfo u JOIN clinic_profiles cp ON cp.user_id=u.id WHERE u.id=:id AND u.role='clinic' LIMIT 1");
$accountStmt->execute([':id' => $userId]);
$clinic = $accountStmt->fetch();
if (!$clinic || $clinic['account_status'] !== 'active') {
    session_destroy();
    header('Location: ../login.php?suspended=1');
    exit;
}
$philippineProvinces = require __DIR__ . '/../config/philippine_provinces.php';
$columnStmt = $pdo->prepare("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('blood_requests','community_posts','donation_offer_messages')");
$columnStmt->execute();
$featureColumns = [];
foreach ($columnStmt->fetchAll(PDO::FETCH_NUM) as [$tableName, $columnName])
    $featureColumns[$tableName][$columnName] = true;
$representationAvailable = !empty($featureColumns['blood_requests']['representation_authorized_at']) && !empty($featureColumns['community_posts']['representation_authorized_at']);
$offerFieldsAvailable = !empty($featureColumns['community_posts']['donation_blood_type']) && !empty($featureColumns['community_posts']['donation_location']) && !empty($featureColumns['community_posts']['donation_available_date']);
$postLocationAvailable = !empty($featureColumns['blood_requests']['city']) && !empty($featureColumns['blood_requests']['latitude']) && !empty($featureColumns['community_posts']['donation_latitude']) && !empty($featureColumns['community_posts']['donation_province']);
$offerStatusAvailable = !empty($featureColumns['community_posts']['donation_status']);
$offerTimeAvailable = !empty($featureColumns['community_posts']['donation_available_time']);
$offerCompletionAvailable = !empty($featureColumns['community_posts']['donation_completed_at']);
$eventFields = ['event_title', 'event_time', 'organizer', 'venue', 'event_city', 'public_contact', 'event_status'];
$eventFieldsAvailable = !empty($featureColumns['community_posts']['event_date']);
foreach ($eventFields as $eventColumn) $eventFieldsAvailable = $eventFieldsAvailable && !empty($featureColumns['community_posts'][$eventColumn]);
$offerMessagesAvailable = !empty($featureColumns['donation_offer_messages']['id']) && !empty($featureColumns['donation_offer_messages']['post_id']) && !empty($featureColumns['donation_offer_messages']['seeker_id']) && !empty($featureColumns['donation_offer_messages']['sender_id']) && !empty($featureColumns['donation_offer_messages']['message']) && !empty($featureColumns['donation_offer_messages']['urgency']);
$offerMessageReadStateAvailable = !empty($featureColumns['donation_offer_messages']['read_at']);
$page = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : 'overview';
$allowedPages = ['overview', 'requests', 'donation-offers', 'blood-map', 'events', 'responses', 'donation-records', 'messages', 'notifications', 'profile', 'settings'];
if (!in_array($page, $allowedPages, true))
    $page = 'overview';
$mustChange = (int) $clinic['must_change_password'] === 1;
if ($mustChange && $page !== 'settings')
    $page = 'settings';
$flash = $_SESSION['clinic_flash'] ?? null;
unset($_SESSION['clinic_flash']);
$csrfToken = $_SESSION['csrf_token'];
$requestStmt = $pdo->prepare("SELECT br.*,(SELECT COUNT(*) FROM request_responses rr WHERE rr.request_id=br.id) AS response_count,(SELECT COUNT(*) FROM request_responses rr WHERE rr.request_id=br.id AND rr.status='pending') AS pending_count FROM blood_requests br WHERE br.requester_id=:id ORDER BY FIELD(br.urgency,'emergency','urgent','routine'),br.needed_by ASC,br.created_at DESC");
$requestStmt->execute([':id' => $userId]);
$requests = $requestStmt->fetchAll();
$offers = [];
$completedOffers = [];
if ($offerFieldsAvailable) {
    $offerSelect = 'id,content,donation_blood_type,donation_location,donation_available_date,created_at';
    if ($offerTimeAvailable)
        $offerSelect .= ',donation_available_time';
    if ($postLocationAvailable)
        $offerSelect .= ',donation_province,donation_city,donation_barangay,donation_latitude,donation_longitude';
    if ($offerStatusAvailable)
        $offerSelect .= ',donation_status';
    if ($offerCompletionAvailable)
        $offerSelect .= ',donation_completed_at';
    $offerSelect .= $offerMessagesAvailable ? ',(SELECT COUNT(*) FROM donation_offer_messages om WHERE om.post_id=community_posts.id) AS message_count' : ',0 AS message_count';
    $offerStmt = $pdo->prepare("SELECT {$offerSelect} FROM community_posts WHERE user_id=:id AND post_type='donation' ORDER BY created_at DESC");
    $offerStmt->execute([':id' => $userId]);
    $offers = $offerStmt->fetchAll();
    if ($offerStatusAvailable)
        $completedOffers = array_values(array_filter($offers, static fn(array $offer): bool => ($offer['donation_status'] ?? '') === 'completed'));
}
$events = [];
if ($eventFieldsAvailable) {
    $eventStmt = $pdo->prepare("SELECT id,event_title,content,event_date,event_time,organizer,venue,event_city,public_contact,event_status,created_at FROM community_posts WHERE user_id=:owner AND post_type='event' ORDER BY event_date ASC,created_at DESC");
    $eventStmt->execute([':owner' => $userId]);
    $events = $eventStmt->fetchAll();
}
$statsStmt = $pdo->prepare("SELECT COUNT(*) total,SUM(status='open' AND needed_by>=CURDATE()) active,SUM(status='fulfilled') fulfilled,(SELECT COUNT(*) FROM request_responses rr JOIN blood_requests br2 ON br2.id=rr.request_id WHERE br2.requester_id=:pending_owner AND rr.status='pending') pending FROM blood_requests br WHERE requester_id=:owner");
$statsStmt->execute([':pending_owner' => $userId, ':owner' => $userId]);
$stats = $statsStmt->fetch() ?: [];
$responseStmt = $pdo->prepare("SELECT rr.id response_id,rr.request_id,rr.message,rr.status approval_status,rr.created_at,br.blood_type,br.units,br.hospital,br.location,br.urgency,br.status request_status,br.needed_by,u.fname,u.lname FROM request_responses rr JOIN blood_requests br ON br.id=rr.request_id JOIN uinfo u ON u.id=rr.user_id WHERE br.requester_id=:id ORDER BY FIELD(br.urgency,'emergency','urgent','routine'),rr.created_at DESC");
$responseStmt->execute([':id' => $userId]);
$responses = $responseStmt->fetchAll();
$messageStmt = $pdo->prepare("SELECT rr.id response_id,br.id request_id,br.blood_type,br.hospital,br.urgency,peer.fname,peer.lname,lm.message latest_message,lm.created_at latest_at,(SELECT COUNT(*) FROM request_response_messages unread WHERE unread.response_id=rr.id AND unread.sender_id<>:viewer AND unread.read_at IS NULL) unread_count FROM request_responses rr JOIN blood_requests br ON br.id=rr.request_id JOIN uinfo peer ON peer.id=rr.user_id LEFT JOIN request_response_messages lm ON lm.id=(SELECT MAX(m.id) FROM request_response_messages m WHERE m.response_id=rr.id) WHERE br.requester_id=:owner AND rr.status='accepted' ORDER BY lm.created_at DESC");
$messageStmt->execute([':viewer' => $userId, ':owner' => $userId]);
$conversations = $messageStmt->fetchAll();
$offerConversations = [];
if ($offerMessagesAvailable) {
    $offerUnreadSql = $offerMessageReadStateAvailable ? '(SELECT COUNT(*) FROM donation_offer_messages unread WHERE unread.post_id=threads.post_id AND unread.seeker_id=threads.seeker_id AND unread.sender_id<>:viewer AND unread.read_at IS NULL) unread_count' : '0 AS unread_count';
    $offerInboxSql = "SELECT threads.post_id,threads.seeker_id,peer.fname,peer.lname,latest.message latest_message,latest.created_at latest_at,{$offerUnreadSql} FROM (SELECT post_id,seeker_id,MAX(id) latest_id FROM donation_offer_messages GROUP BY post_id,seeker_id) threads JOIN donation_offer_messages latest ON latest.id=threads.latest_id JOIN community_posts cp ON cp.id=threads.post_id JOIN uinfo peer ON peer.id=threads.seeker_id WHERE cp.user_id=:owner AND cp.post_type='donation' ORDER BY latest.created_at DESC";
    $offerInboxStmt = $pdo->prepare($offerInboxSql);
    $offerInboxValues = [':owner' => $userId];
    if ($offerMessageReadStateAvailable)
        $offerInboxValues[':viewer'] = $userId;
    $offerInboxStmt->execute($offerInboxValues);
    $offerConversations = $offerInboxStmt->fetchAll();
}
$selectedThread = filter_var($_GET['thread'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$selectedOfferPost = 0;
$selectedOfferSeeker = 0;
if (isset($_GET['thread']) && is_string($_GET['thread']) && preg_match('/^offer-(\d+)-(\d+)$/', $_GET['thread'], $threadParts)) {
    $selectedOfferPost = (int) $threadParts[1];
    $selectedOfferSeeker = (int) $threadParts[2];
}
$selectedConversation = null;
$selectedOfferConversation = null;
$offerMessages = [];
$messages = [];
foreach ($conversations as $conversation)
    if ((int) $conversation['response_id'] === $selectedThread) {
        $selectedConversation = $conversation;
        break;
    }
if ($selectedConversation) {
    $history = $pdo->prepare('SELECT m.*,u.fname,u.lname FROM request_response_messages m JOIN uinfo u ON u.id=m.sender_id WHERE m.response_id=:id ORDER BY m.created_at,m.id');
    $history->execute([':id' => $selectedThread]);
    $messages = $history->fetchAll();
    $pdo->prepare('UPDATE request_response_messages SET read_at=NOW() WHERE response_id=:id AND sender_id<>:viewer AND read_at IS NULL')->execute([':id' => $selectedThread, ':viewer' => $userId]);
}
if ($selectedOfferPost && $selectedOfferSeeker && $offerMessagesAvailable) {
    $selectedOfferStmt = $pdo->prepare("SELECT cp.id post_id,cp.content,cp.donation_blood_type,cp.donation_location,u.fname,u.lname FROM community_posts cp JOIN uinfo u ON u.id=:peer_id WHERE cp.id=:post_id AND cp.user_id=:owner_id AND cp.post_type='donation' AND EXISTS (SELECT 1 FROM donation_offer_messages existing WHERE existing.post_id=cp.id AND existing.seeker_id=:thread_peer_id)");
    $selectedOfferStmt->execute([':peer_id' => $selectedOfferSeeker, ':thread_peer_id' => $selectedOfferSeeker, ':post_id' => $selectedOfferPost, ':owner_id' => $userId]);
    $selectedOfferConversation = $selectedOfferStmt->fetch() ?: null;
    if ($selectedOfferConversation) {
        $offerHistoryStmt = $pdo->prepare('SELECT m.*,sender.fname,sender.lname FROM donation_offer_messages m JOIN uinfo sender ON sender.id=m.sender_id WHERE m.post_id=:post_id AND m.seeker_id=:seeker_id ORDER BY m.created_at,m.id');
        $offerHistoryStmt->execute([':post_id' => $selectedOfferPost, ':seeker_id' => $selectedOfferSeeker]);
        $offerMessages = $offerHistoryStmt->fetchAll();
        if ($offerMessageReadStateAvailable)
            $pdo->prepare('UPDATE donation_offer_messages SET read_at=NOW() WHERE post_id=:post_id AND seeker_id=:seeker_id AND sender_id<>:viewer AND read_at IS NULL')->execute([':post_id' => $selectedOfferPost, ':seeker_id' => $selectedOfferSeeker, ':viewer' => $userId]);
    }
}
$noticeStmt = $pdo->prepare('SELECT n.*,a.fname actor_first,a.lname actor_last FROM member_notifications n LEFT JOIN uinfo a ON a.id=n.actor_id WHERE n.user_id=:id ORDER BY n.created_at DESC LIMIT 100');
$noticeStmt->execute([':id' => $userId]);
$notifications = $noticeStmt->fetchAll();
$nav = ['overview' => 'Dashboard', 'requests' => 'Blood Requests', 'donation-offers' => 'Donation Offers', 'blood-map' => 'Blood Donation Map', 'events' => 'Donation Events', 'responses' => 'Request Responses', 'donation-records' => 'Donation Records', 'messages' => 'Messages', 'notifications' => 'Notifications', 'profile' => 'Facility Profile', 'settings' => 'Settings'];
$sidebarGroups = ['Overview' => ['overview' => 'Dashboard'], 'Blood Services' => ['requests' => 'Blood Requests', 'donation-offers' => 'Donation Offers', 'blood-map' => 'Blood Donation Map', 'events' => 'Donation Events', 'responses' => 'Request Responses', 'donation-records' => 'Donation Records'], 'Communication' => ['messages' => 'Messages', 'notifications' => 'Notifications'], 'Facility Management' => ['profile' => 'Facility Profile'], 'Account' => []];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= clinic_h($nav[$page]) ?> | BLife PH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/user-dashboard.css">
    <link rel="stylesheet" href="../assets/clinic-dashboard.css">
    <link rel="stylesheet" href="../assets/location-picker.css">
    <link rel="stylesheet" href="../assets/blood-donation-map.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
</head>

<body>
    <div class="clinic-shell">
        <aside class="clinic-sidebar"><a class="clinic-brand" href="dashboard.php"><img
                    src="../images/BlifePH_no_bg.png" alt="BLife PH"><span>BLife PH</span></a>
            <div class="clinic-name">
                <?= clinic_h($clinic['facility_name']) ?><small><?= clinic_h(ucwords(str_replace('_', ' ', $clinic['facility_type']))) ?>
                    · <?php if ((int) $clinic['is_verified'] === 1): ?><b>Verified Institution</b><?php else: ?>Not
                        verified<?php endif; ?></small></div><?php clinic_sidebar_navigation($sidebarGroups, $page); ?>
        </aside>
        <main class="clinic-main">
            <header class="clinic-header">
                <div>
                    <p class="eyebrow">CLINIC / BLOOD BANK PORTAL</p>
                    <h1><?= clinic_h($nav[$page]) ?></h1>
                </div><?php if ($page === 'requests' && $representationAvailable): ?><button
                        class="button button-primary" type="button" data-modal-open="clinicRequestDialog"><i
                            class="fa-solid fa-plus" aria-hidden="true"></i> Create Blood
                        Request</button><?php elseif ($page === 'donation-offers' && $representationAvailable && $offerFieldsAvailable): ?><button
                        class="button button-primary" type="button" data-modal-open="clinicOfferDialog"><i
                            class="fa-solid fa-plus" aria-hidden="true"></i> Offer Donation</button><?php elseif ($page === 'events' && $eventFieldsAvailable): ?><button
                        class="button button-primary" type="button" data-modal-open="clinicEventDialog"><i
                            class="fa-solid fa-plus" aria-hidden="true"></i> Create Donation Event</button><?php endif; ?>
            </header>
            <?php if ($flash): ?>
                <div class="clinic-flash <?= clinic_h($flash['type']) ?>" role="status"><?= clinic_h($flash['message']) ?>
                </div><?php endif; ?>
            <?php if ($mustChange): ?>
                <p class="clinic-flash" role="status">Change your temporary password before using clinic functions.</p>
            <?php endif; ?>
            <?php if ($page === 'overview' && !$mustChange): ?>
                <section class="clinic-stats">
                    <article><span>Total requests</span><strong><?= (int) ($stats['total'] ?? 0) ?></strong></article>
                    <article><span>Active requests</span><strong><?= (int) ($stats['active'] ?? 0) ?></strong></article>
                    <article><span>Fulfilled</span><strong><?= (int) ($stats['fulfilled'] ?? 0) ?></strong></article>
                    <article><span>Pending responses</span><strong><?= (int) ($stats['pending'] ?? 0) ?></strong></article>
                </section>
                <section class="clinic-panel">
                    <div class="clinic-section-title">
                        <h2>Recent requests</h2><a href="dashboard.php?page=requests">Manage requests</a>
                    </div><?php foreach (array_slice($requests, 0, 5) as $request): ?>
                        <article class="clinic-request"><strong><?= clinic_h($request['blood_type']) ?> ·
                                <?= (int) $request['units'] ?> units at <?= clinic_h($request['hospital']) ?></strong><span
                                class="urgency-badge urgency-<?= clinic_h($request['urgency']) ?>"><?= clinic_h(ucfirst($request['urgency'])) ?></span><small><?= clinic_h(trim(($request['city'] ?? '') . ', ' . $request['location'], ', ')) ?>
                                · needed <?= clinic_h($request['needed_by']) ?> · <?= (int) $request['response_count'] ?>
                                response(s)</small></article><?php endforeach; ?><?php if (!$requests): ?>
                        <p>No blood requests yet.</p><?php endif; ?>
                </section>
            <?php elseif ($page === 'blood-map' && !$mustChange): ?>
                <?php blife_render_blood_donation_map('../api/map_data.php', 'dashboard.php?page=requests#request-', 'dashboard.php?page=donation-offers#post-', true); ?>
            <?php elseif ($page === 'requests' && !$mustChange): ?>
                <?php if (!$representationAvailable): ?>
                    <div class="notice notice-error" role="alert">Clinic representation records require the database migration
                        <code>database_sql/clinic_represented_posts_migration.sql</code>.</div><?php endif; ?>
                <?php if (!$requests): ?>
                    <div class="empty-state panel clinic-empty"><strong>No blood requests yet.</strong><span>Requests published
                            for patients will appear here.</span></div><?php else: ?>
                    <section class="clinic-feed" aria-label="Facility blood requests">
                        <?php foreach ($requests as $request): ?>
                            <article class="request-card panel donation-offer-card request-card-own clinic-member-card" id="request-<?= (int) $request['id'] ?>">
                                <details class="content-management-menu request-management-menu clinic-request-menu">
                                    <summary aria-label="Manage this blood request">&hellip;</summary>
                                    <div class="content-management-panel">
                                        <?php if ($request['status'] === 'open'): ?><button class="content-menu-action"
                                                type="button" data-modal-open="clinicEditRequest<?= (int) $request['id'] ?>">Edit
                                                request</button>
                                            <form class="inline-actions" method="post" action="action.php"><input type="hidden"
                                                    name="csrf_token" value="<?= clinic_h($csrfToken) ?>"><input type="hidden"
                                                    name="action" value="clinic_request_status"><input type="hidden" name="request_id"
                                                    value="<?= (int) $request['id'] ?>"><label>Request status<select name="status">
                                                        <option value="" disabled selected>Open</option>
                                                        <option value="fulfilled">Fulfilled</option>
                                                        <option value="cancelled">Cancelled</option>
                                                    </select></label><button class="text-button" type="submit">Update status</button>
                                            </form><?php endif; ?>
                                        <form class="confirm-delete-form" method="post" action="action.php"><input type="hidden"
                                                name="csrf_token" value="<?= clinic_h($csrfToken) ?>"><input type="hidden"
                                                name="action" value="clinic_request_delete"><input type="hidden" name="request_id"
                                                value="<?= (int) $request['id'] ?>"><button class="delete-post-button"
                                                type="submit">Delete</button></form>
                                    </div>
                                </details>
                                <div class="request-heading"><span class="blood-mark"><?= clinic_h($request['blood_type']) ?></span>
                                    <div class="post-author">
                                        <div class="post-author-heading">
                                            <strong><?= clinic_h($clinic['facility_name']) ?></strong><?php if ((int) $clinic['is_verified'] === 1): ?><span
                                                    class="verified-donor">Verified facility</span><?php endif; ?><span
                                                class="post-author-you">You</span></div><span>Blood Request</span>
                                    </div>
                                    <div class="request-heading-badges"><span
                                            class="urgency-badge urgency-<?= clinic_h($request['urgency']) ?>"><?= clinic_h(ucfirst($request['urgency'])) ?></span><span
                                            class="status-badge status-<?= clinic_h($request['status']) ?>"><?= clinic_h(ucfirst($request['status'])) ?></span>
                                    </div>
                                </div>
                                <div class="donation-offer-details request-offer-details">
                                    <p><strong>Units:</strong> <?= (int) $request['units'] ?></p>
                                    <p><strong>Facility:</strong> <?= clinic_h($request['hospital']) ?></p>
                                    <p><strong>Location:</strong> <?= clinic_h(trim(($request['city'] ?? '') . ', ' . $request['location'], ', ')) ?></p>
                                    <p><strong>Needed by:</strong> <?= clinic_h(date('M j, Y', strtotime($request['needed_by']))) ?>
                                    </p>
                                </div>
                                <?php if ($request['details'] !== ''): ?>
                                    <p class="request-details"><?= nl2br(clinic_h($request['details'])) ?></p><?php endif; ?>
                                <p class="request-date"><?= (int) $request['response_count'] ?>
                                    response<?= (int) $request['response_count'] === 1 ? '' : 's' ?> received · Published
                                    <?= clinic_h(date('M j, Y', strtotime($request['created_at']))) ?></p>
                            </article>
                            <?php if ($request['status'] === 'open'): ?>
                                <dialog class="dashboard-modal" id="clinicEditRequest<?= (int) $request['id'] ?>"
                                    aria-labelledby="clinicEditRequestTitle<?= (int) $request['id'] ?>">
                                    <div class="modal-heading">
                                        <div>
                                            <p class="eyebrow">BLOOD REQUEST</p>
                                            <h2 id="clinicEditRequestTitle<?= (int) $request['id'] ?>">Edit your request</h2>
                                        </div><button class="modal-close" type="button" data-modal-close
                                            aria-label="Close edit request form">&times;</button>
                                    </div>
                                    <form class="stacked-form modal-form" method="post" action="action.php"><input type="hidden"
                                            name="csrf_token" value="<?= clinic_h($csrfToken) ?>"><input type="hidden" name="action"
                                            value="clinic_request_edit"><input type="hidden" name="request_id"
                                            value="<?= (int) $request['id'] ?>"><?php clinic_request_fields($request, false, $philippineProvinces); ?><button
                                            class="button button-primary" type="submit">Save request</button></form>
                                </dialog><?php endif; ?>
                        <?php endforeach; ?>
                    </section><?php endif; ?>
                <dialog class="dashboard-modal" id="clinicRequestDialog" aria-labelledby="clinicRequestTitle">
                    <div class="modal-heading">
                        <div>
                            <p class="eyebrow">BLOOD REQUEST</p>
                            <h2 id="clinicRequestTitle">Request blood for a patient</h2>
                        </div><button class="modal-close" type="button" data-modal-close
                            aria-label="Close request form">&times;</button>
                    </div>
                    <form class="stacked-form modal-form" method="post" action="action.php"><input type="hidden"
                            name="csrf_token" value="<?= clinic_h($csrfToken) ?>"><input type="hidden" name="action"
                            value="clinic_request_create">
                        <!-- <p class="form-help">The facility will appear as the requester. Do not enter the patient's name,
                            contact details, or medical record number.</p> -->
                        <?php clinic_request_fields([], true, $philippineProvinces); ?><button class="button button-primary"
                            type="submit">Publish blood request</button>
                    </form>
                </dialog>
            <?php elseif ($page === 'donation-offers' && !$mustChange): ?>
                <?php if (!$representationAvailable || !$offerFieldsAvailable): ?>
                    <div class="notice notice-error" role="alert">Clinic donation offers require
                        <code>database_sql/donation_offers_migration.sql</code> and
                        <code>database_sql/clinic_represented_posts_migration.sql</code>.</div><?php endif; ?>
                <?php if (!$offers): ?>
                    <div class="empty-state panel clinic-empty"><strong>No donation offers yet.</strong><span>Offers published
                            on behalf of potential donors will appear here.</span></div><?php else: ?>
                    <section class="clinic-feed" aria-label="Donation offers published by your facility">
                        <?php foreach ($offers as $offer): ?>
                            <article class="post-card panel donation-offer-card clinic-member-card" id="post-<?= (int) $offer['id'] ?>">
                                <details class="content-management-menu">
                                    <summary aria-label="Manage this donation offer">&hellip;</summary>
                                    <div class="content-management-panel">
                                        <?php if (($offer['donation_status'] ?? 'available') !== 'completed'): ?><button
                                                class="content-menu-action" type="button"
                                                data-modal-open="clinicEditOffer<?= (int) $offer['id'] ?>">Edit
                                                offer</button><?php endif; ?>
                                        <?php if ($offerStatusAvailable): ?>
                                            <form class="content-status-form" method="post" action="action.php"><input type="hidden"
                                                    name="csrf_token" value="<?= clinic_h($csrfToken) ?>"><input type="hidden"
                                                    name="action" value="clinic_offer_status"><input type="hidden" name="post_id"
                                                    value="<?= (int) $offer['id'] ?>"><label>Offer status<select
                                                        name="status"><?php foreach (['available' => 'Available', 'unavailable' => 'Unavailable', 'completed' => 'Completed'] as $value => $label): ?>
                                                            <option value="<?= clinic_h($value) ?>" <?= ($offer['donation_status'] ?? 'available') === $value ? 'selected' : '' ?>><?= clinic_h($label) ?></option>
                                                        <?php endforeach; ?>
                                                    </select></label><button class="text-button" type="submit">Update status</button>
                                            </form><?php endif; ?>
                                        <?php if ((int) $offer['message_count'] === 0 && ($offer['donation_status'] ?? 'available') !== 'completed'): ?>
                                            <form class="confirm-delete-form" method="post" action="action.php"><input type="hidden"
                                                    name="csrf_token" value="<?= clinic_h($csrfToken) ?>"><input type="hidden"
                                                    name="action" value="clinic_offer_delete"><input type="hidden" name="post_id"
                                                    value="<?= (int) $offer['id'] ?>"><button class="delete-post-button"
                                                    type="submit">Delete offer</button></form><?php else: ?>
                                            <p class="clinic-menu-note">Offers with conversations or completed records cannot be
                                                deleted.</p><?php endif; ?>
                                    </div>
                                </details>
                                <div class="post-meta"><span
                                        class="avatar avatar-small clinic-avatar"><?= clinic_h(strtoupper(substr($clinic['facility_name'], 0, 2))) ?></span>
                                    <div class="post-author">
                                        <div class="post-author-heading">
                                            <strong><?= clinic_h($clinic['facility_name']) ?></strong><?php if ((int) $clinic['is_verified'] === 1): ?><span
                                                    class="verified-donor">Verified facility</span><?php endif; ?><span
                                                class="post-author-you">You</span></div><span>Donation offer</span>
                                    </div><time><?= clinic_h(date('M j, Y', strtotime($offer['created_at']))) ?></time>
                                </div>
                                <div class="clinic-offer-status-row"><span
                                        class="status-badge status-<?= clinic_h($offer['donation_status'] ?? 'available') ?>"><?= clinic_h(ucfirst($offer['donation_status'] ?? 'available')) ?></span>
                                </div>
                                <div class="donation-offer-details">
                                    <p><strong>Blood type:</strong> <?= clinic_h($offer['donation_blood_type']) ?></p>
                                    <p><strong>Location:</strong> <?= clinic_h($offer['donation_location']) ?></p>
                                    <?php if (!empty($offer['donation_available_date'])): ?>
                                        <p><strong>Available:</strong>
                                            <?= clinic_h(date('M j, Y', strtotime($offer['donation_available_date']))) ?>                <?php if (!empty($offer['donation_available_time'])): ?>
                                                ·
                                                <?= clinic_h(date('g:i a', strtotime($offer['donation_available_time']))) ?>                <?php endif; ?>
                                        </p><?php endif; ?>
                                </div>
                                <p class="post-content clinic-offer-copy"><?= nl2br(clinic_h($offer['content'])) ?></p>
                                <p class="request-date"><?= (int) $offer['message_count'] ?>
                                    conversation<?= (int) $offer['message_count'] === 1 ? '' : 's' ?></p>
                            </article>
                            <?php if (($offer['donation_status'] ?? 'available') !== 'completed'): ?>
                                <dialog class="dashboard-modal" id="clinicEditOffer<?= (int) $offer['id'] ?>"
                                    aria-labelledby="clinicEditOfferTitle<?= (int) $offer['id'] ?>">
                                    <div class="modal-heading">
                                        <div>
                                            <p class="eyebrow">DONATION OFFER</p>
                                            <h2 id="clinicEditOfferTitle<?= (int) $offer['id'] ?>">Edit your offer</h2>
                                        </div><button class="modal-close" type="button" data-modal-close
                                            aria-label="Close edit offer form">&times;</button>
                                    </div>
                                    <form class="stacked-form modal-form" method="post" action="action.php"><input type="hidden"
                                            name="csrf_token" value="<?= clinic_h($csrfToken) ?>"><input type="hidden" name="action"
                                            value="clinic_offer_edit"><input type="hidden" name="post_id"
                                            value="<?= (int) $offer['id'] ?>"><label>Offer details<textarea name="content"
                                                maxlength="1000" rows="3" required><?= clinic_h($offer['content']) ?></textarea></label>
                                        <div class="form-row"><label>Blood type<select name="donation_blood_type"
                                                    required><?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $type): ?>
                                                        <option value="<?= clinic_h($type) ?>"
                                                            <?= $offer['donation_blood_type'] === $type ? 'selected' : '' ?>><?= clinic_h($type) ?>
                                                        </option><?php endforeach; ?>
                                                </select></label></div>
                                        <?php blife_location_picker($philippineProvinces, 'donation', 'clinicDonationEditMap' . (int) $offer['id'], ['province' => $offer['donation_province'] ?? (in_array($offer['donation_location'], $philippineProvinces, true) ? $offer['donation_location'] : ''), 'city' => $offer['donation_city'] ?? (in_array($offer['donation_location'], $philippineProvinces, true) ? '' : $offer['donation_location']), 'barangay' => $offer['donation_barangay'] ?? '', 'latitude' => $offer['donation_latitude'] ?? '', 'longitude' => $offer['donation_longitude'] ?? '']); ?>
                                        <div class="form-row"><label>Available date<input type="date" name="donation_available_date"
                                                    min="<?= clinic_h(date('Y-m-d')) ?>"
                                                    value="<?= clinic_h($offer['donation_available_date'] ?? '') ?>"></label><?php if ($offerTimeAvailable): ?><label>Available
                                                    time<input type="time" name="donation_available_time"
                                                        value="<?= clinic_h(isset($offer['donation_available_time']) ? substr($offer['donation_available_time'], 0, 5) : '') ?>"></label><?php endif; ?>
                                        </div><button class="button button-primary" type="submit">Save changes</button>
                                    </form>
                                </dialog><?php endif; ?>
                        <?php endforeach; ?>
                    </section><?php endif; ?>
                <?php if ($representationAvailable && $offerFieldsAvailable): ?>
                    <dialog class="dashboard-modal" id="clinicOfferDialog" aria-labelledby="clinicOfferTitle">
                        <div class="modal-heading">
                            <div>
                                <p class="eyebrow">DONATION OFFER</p>
                                <h2 id="clinicOfferTitle">Offer to donate on behalf of a potential donor</h2>
                            </div><button class="modal-close" type="button" data-modal-close
                                aria-label="Close donation offer form">&times;</button>
                        </div>
                        <form class="stacked-form modal-form" method="post" action="action.php"><input type="hidden"
                                name="csrf_token" value="<?= clinic_h($csrfToken) ?>"><input type="hidden" name="action"
                                value="clinic_offer_create"><label>Offer details<textarea name="content" maxlength="1000"
                                    rows="3" required
                                    placeholder="Describe the donor's stated availability..."></textarea></label>
                            <div class="form-row"><label>Blood type<select name="donation_blood_type" required>
                                        <option value="">Select blood type</option>
                                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $type): ?>
                                            <option value="<?= clinic_h($type) ?>"><?= clinic_h($type) ?></option>
                                        <?php endforeach; ?>
                                    </select></label></div>
                            <?php blife_location_picker($philippineProvinces, 'donation', 'clinicDonationCreateMap', []); ?>
                            <div class="form-row"><label>Available date (optional)<input type="date"
                                        name="donation_available_date"
                                        min="<?= clinic_h(date('Y-m-d')) ?>"></label><?php if ($offerTimeAvailable): ?><label>Available
                                        time (optional)<input type="time" name="donation_available_time"></label><?php endif; ?>
                            </div>
                            <p class="form-help">The facility is shown as the offer contact. Do not enter the person's name,
                                contact details, or medical record number. Availability does not confirm eligibility.</p><label
                                class="clinic-permission-check"><input type="checkbox" name="represented_person_permission"
                                    value="1" required><span>I confirm the potential donor gave permission for this offer to be
                                    published.</span></label><button class="button button-primary" type="submit">Publish
                                donation offer</button>
                        </form>
                    </dialog><?php endif; ?>
            <?php elseif ($page === 'events' && !$mustChange): ?>
                <?php if (!$eventFieldsAvailable): ?>
                    <div class="notice notice-error" role="alert">Donation event publishing needs the shared event fields. Apply <code>database_sql/blife_admin_modules_migration.sql</code> after the existing admin and member panel migrations.</div>
                <?php else: ?>
                    <section class="clinic-panel clinic-event-list">
                        <div class="clinic-section-title"><h2>Events published by your facility</h2><span><?= count($events) ?> event<?= count($events) === 1 ? '' : 's' ?></span></div>
                        <?php if (!$events): ?><div class="empty-state"><strong>No donation events yet.</strong><span>Events published by your facility will appear in the member Events section.</span></div><?php endif; ?>
                        <?php foreach ($events as $event): ?>
                            <article class="clinic-event-card">
                                <details class="content-management-menu clinic-event-menu"><summary aria-label="Manage donation event">&hellip;</summary><div class="content-management-panel">
                                    <button class="content-menu-action" type="button" data-modal-open="clinicEditEvent<?= (int)$event['id'] ?>">Edit event</button>
                                    <form class="content-status-form" method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= clinic_h($csrfToken) ?>"><input type="hidden" name="action" value="clinic_event_status"><input type="hidden" name="post_id" value="<?= (int)$event['id'] ?>"><label>Event status<select name="status"><?php foreach (['published','draft','cancelled','completed'] as $status): ?><option value="<?= clinic_h($status) ?>" <?= $event['event_status'] === $status ? 'selected' : '' ?>><?= clinic_h(ucfirst($status)) ?></option><?php endforeach; ?></select></label><button class="text-button" type="submit">Update status</button></form>
                                </div></details>
                                <div class="clinic-event-heading"><div><p class="eyebrow">DONATION EVENT</p><h3><?= clinic_h($event['event_title']) ?></h3></div><span class="status-badge status-<?= clinic_h($event['event_status']) ?>"><?= clinic_h(ucfirst($event['event_status'])) ?></span></div>
                                <p class="clinic-event-meta"><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?= clinic_h(date('M j, Y', strtotime($event['event_date']))) ?><?php if (!empty($event['event_time'])): ?> · <?= clinic_h(date('g:i a', strtotime($event['event_time']))) ?><?php endif; ?></p>
                                <?php if (!empty($event['venue']) || !empty($event['event_city'])): ?><p class="clinic-event-meta"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= clinic_h(trim($event['venue'] . ($event['event_city'] !== '' ? ', ' . $event['event_city'] : ''))) ?></p><?php endif; ?>
                                <?php if ($event['organizer'] !== ''): ?><p class="clinic-event-meta"><i class="fa-solid fa-people-group" aria-hidden="true"></i> <?= clinic_h($event['organizer']) ?></p><?php endif; ?>
                                <p class="clinic-event-description"><?= nl2br(clinic_h($event['content'])) ?></p>
                            </article>
                            <dialog class="dashboard-modal" id="clinicEditEvent<?= (int)$event['id'] ?>" aria-labelledby="clinicEditEventTitle<?= (int)$event['id'] ?>"><div class="modal-heading"><div><p class="eyebrow">DONATION EVENT</p><h2 id="clinicEditEventTitle<?= (int)$event['id'] ?>">Edit event</h2></div><button class="modal-close" type="button" data-modal-close aria-label="Close event form">&times;</button></div><form class="stacked-form modal-form clinic-event-form" method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= clinic_h($csrfToken) ?>"><input type="hidden" name="action" value="clinic_event_edit"><input type="hidden" name="post_id" value="<?= (int)$event['id'] ?>"><?php clinic_event_fields($event); ?><button class="button button-primary" type="submit">Save event</button></form></dialog>
                        <?php endforeach; ?>
                    </section>
                    <dialog class="dashboard-modal" id="clinicEventDialog" aria-labelledby="clinicEventTitle"><div class="modal-heading"><div><p class="eyebrow">DONATION EVENT</p><h2 id="clinicEventTitle">Create donation event</h2></div><button class="modal-close" type="button" data-modal-close aria-label="Close event form">&times;</button></div><form class="stacked-form modal-form clinic-event-form" method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= clinic_h($csrfToken) ?>"><input type="hidden" name="action" value="clinic_event_create"><?php clinic_event_fields([]); ?><button class="button button-primary" type="submit">Publish event</button></form></dialog>
                <?php endif; ?>
            <?php elseif ($page === 'donation-records' && !$mustChange): ?>
                <section class="clinic-panel">
                    <h2>Completed donation offers</h2>
                    <p class="form-help">These are offers your facility marked as completed. BLife PH does not independently
                        verify a donation or transfusion.</p><?php if (!$offerStatusAvailable): ?>
                        <div class="notice notice-info">Run <code>database_sql/donation_offer_status_migration.sql</code> to
                            track completed offers.</div><?php elseif (!$completedOffers): ?>
                        <div class="empty-state"><strong>No completed offers recorded yet.</strong><span>Mark an offer Completed
                                after your facility confirms its outcome.</span></div>
                    <?php else: ?>        <?php foreach ($completedOffers as $record): ?>
                            <article class="clinic-request">
                                <header><strong><?= clinic_h($record['donation_blood_type']) ?> ·
                                        <?= clinic_h($record['donation_location']) ?></strong><span
                                        class="status-badge status-completed">Completed</span></header>
                                <p><?= nl2br(clinic_h($record['content'])) ?></p><small>Offer published
                                    <?= clinic_h(date('M j, Y', strtotime($record['created_at']))) ?>            <?php if (!empty($record['donation_completed_at'])): ?>
                                        · Marked completed
                                        <?= clinic_h(date('M j, Y', strtotime($record['donation_completed_at']))) ?>            <?php endif; ?></small>
                            </article><?php endforeach; ?><?php endif; ?>
                </section>
            <?php elseif ($page === 'responses' && !$mustChange): ?>
                <section class="clinic-panel">
                    <h2>Member responses to your requests</h2><?php foreach ($responses as $response): ?>
                        <article class="clinic-request">
                            <header>
                                <strong><?= clinic_h(trim($response['fname'] . ' ' . $response['lname']) ?: 'BLife PH member') ?></strong><span
                                    class="urgency-badge urgency-<?= clinic_h($response['urgency']) ?>"><?= clinic_h(ucfirst($response['urgency'])) ?>
                                    request</span></header>
                            <p><?= clinic_h($response['blood_type']) ?> · <?= (int) $response['units'] ?> units ·
                                <?= clinic_h($response['hospital']) ?> ·
                                <?= nl2br(clinic_h($response['message'] ?: 'Offered to help without a message.')) ?></p>
                            <?php if ($response['approval_status'] === 'pending' && $response['request_status'] === 'open'): ?>
                                <form method="post" action="../dashboard/action.php"><input type="hidden" name="csrf_token"
                                        value="<?= clinic_h($csrfToken) ?>"><input type="hidden" name="action"
                                        value="accept_request_response"><input type="hidden" name="response_id"
                                        value="<?= (int) $response['response_id'] ?>"><button class="clinic-primary">Accept
                                        offer</button></form><?php elseif ($response['approval_status'] === 'accepted'): ?><a
                                    href="dashboard.php?page=messages&amp;thread=<?= (int) $response['response_id'] ?>">Open
                                    conversation</a><?php else: ?><small>Request is closed.</small><?php endif; ?>
                        </article><?php endforeach; ?><?php if (!$responses): ?>
                        <p>No responses received yet.</p><?php endif; ?>
                </section>
            <?php elseif ($page === 'messages' && !$mustChange): ?>
                <div class="clinic-messages">
                    <section class="clinic-panel">
                        <h2>Active conversations</h2>
                        <?php foreach ($conversations as $conversation): ?><a class="clinic-conversation"
                                href="dashboard.php?page=messages&amp;thread=<?= (int) $conversation['response_id'] ?>"><strong><?= clinic_h(trim($conversation['fname'] . ' ' . $conversation['lname']) ?: 'Member') ?></strong><small><?= clinic_h($conversation['hospital']) ?>
                                    ·
                                    <?= clinic_h($conversation['latest_message'] ?: 'Conversation started') ?></small><?php if ((int) $conversation['unread_count']): ?><b><?= (int) $conversation['unread_count'] ?></b><?php endif; ?></a><?php endforeach; ?>
                        <?php foreach ($offerConversations as $conversation): ?><a class="clinic-conversation"
                                href="dashboard.php?page=messages&amp;thread=offer-<?= (int) $conversation['post_id'] ?>-<?= (int) $conversation['seeker_id'] ?>"><strong><?= clinic_h(trim($conversation['fname'] . ' ' . $conversation['lname']) ?: 'Member') ?></strong><small>Donation
                                    offer ·
                                    <?= clinic_h($conversation['latest_message'] ?: 'Conversation started') ?></small><?php if ((int) $conversation['unread_count']): ?><b><?= (int) $conversation['unread_count'] ?></b><?php endif; ?></a><?php endforeach; ?>
                        <?php if (!$conversations && !$offerConversations): ?>
                            <p>No active conversations yet.</p><?php endif; ?>
                    </section>
                    <section class="clinic-panel">
                        <?php if ($selectedConversation): ?>
                            <h2><?= clinic_h(trim($selectedConversation['fname'] . ' ' . $selectedConversation['lname']) ?: 'Member') ?>
                            </h2>
                            <p><?= clinic_h($selectedConversation['hospital']) ?> ·
                                <?= clinic_h(ucfirst($selectedConversation['urgency'])) ?> urgency</p>
                            <ol class="clinic-history"><?php foreach ($messages as $message): ?>
                                    <li class="<?= (int) $message['sender_id'] === $userId ? 'own' : '' ?>">
                                        <strong><?= (int) $message['sender_id'] === $userId ? 'Facility' : clinic_h(trim($message['fname'] . ' ' . $message['lname'])) ?></strong><time><?= clinic_h(date('M j, Y g:i a', strtotime($message['created_at']))) ?></time>
                                        <p><?= nl2br(clinic_h($message['message'])) ?></p>
                                    </li><?php endforeach; ?>
                            </ol>
                            <form class="clinic-form" method="post" action="../dashboard/action.php"><input type="hidden"
                                    name="csrf_token" value="<?= clinic_h($csrfToken) ?>"><input type="hidden" name="action"
                                    value="request_message"><input type="hidden" name="response_id"
                                    value="<?= (int) $selectedThread ?>"><input type="hidden" name="return_page"
                                    value="messages"><label>Reply<textarea name="message" maxlength="1000"
                                        required></textarea></label><button class="button button-primary">Send message</button>
                            </form>
                        <?php elseif ($selectedOfferConversation): ?>
                            <h2><?= clinic_h(trim($selectedOfferConversation['fname'] . ' ' . $selectedOfferConversation['lname']) ?: 'Member') ?>
                            </h2>
                            <p>Donation offer ·
                                <?= clinic_h($selectedOfferConversation['donation_blood_type'] ?: 'Blood type not specified') ?>
                                · <?= clinic_h($selectedOfferConversation['donation_location'] ?: 'Location not specified') ?>
                            </p>
                            <p><?= nl2br(clinic_h($selectedOfferConversation['content'])) ?></p>
                            <ol class="clinic-history"><?php foreach ($offerMessages as $message): ?>
                                    <li class="<?= (int) $message['sender_id'] === $userId ? 'own' : '' ?>">
                                        <strong><?= (int) $message['sender_id'] === $userId ? 'Facility' : clinic_h(trim($message['fname'] . ' ' . $message['lname'])) ?></strong><time><?= clinic_h(date('M j, Y g:i a', strtotime($message['created_at']))) ?></time>
                                        <p><?= nl2br(clinic_h($message['message'])) ?></p>
                                    </li><?php endforeach; ?>
                            </ol>
                            <form class="clinic-form" method="post" action="../dashboard/action.php"><input type="hidden"
                                    name="csrf_token" value="<?= clinic_h($csrfToken) ?>"><input type="hidden" name="action"
                                    value="donation_offer_message"><input type="hidden" name="post_id"
                                    value="<?= (int) $selectedOfferPost ?>"><input type="hidden" name="seeker_id"
                                    value="<?= (int) $selectedOfferSeeker ?>"><input type="hidden" name="return_page"
                                    value="messages"><label>Reply<textarea name="message" maxlength="1000"
                                        required></textarea></label><button class="button button-primary">Send message</button>
                            </form>
                        <?php else: ?>
                            <p>Select a conversation.</p><?php endif; ?>
                    </section>
                </div>
            <?php elseif ($page === 'notifications' && !$mustChange): ?>
                <section class="clinic-panel">
                    <h2>Notifications</h2><?php foreach ($notifications as $notice): ?>
                        <article class="clinic-notification">
                            <strong><?= clinic_h(trim(($notice['actor_first'] ?? '') . ' ' . ($notice['actor_last'] ?? '')) ?: 'BLife PH') ?></strong>
                            <?= clinic_h($notice['message']) ?><small><?= clinic_h(date('M j, Y g:i a', strtotime($notice['created_at']))) ?></small>
                        </article><?php endforeach; ?><?php if (!$notifications): ?>
                        <p>No notifications yet.</p><?php endif; ?>
                </section>
            <?php elseif ($page === 'profile' && !$mustChange): ?>
                <section class="clinic-panel">
                    <h2>Administrator-managed identity</h2>
                    <dl class="clinic-profile">
                        <dt>Facility</dt>
                        <dd><?= clinic_h($clinic['facility_name']) ?></dd>
                        <dt>Type</dt>
                        <dd><?= clinic_h(ucwords(str_replace('_', ' ', $clinic['facility_type']))) ?></dd>
                        <dt>Address</dt>
                        <dd><?= clinic_h($clinic['facility_address']) ?></dd>
                        <dt>City / province</dt>
                        <dd><?= clinic_h($clinic['city'] . ', ' . $clinic['province']) ?></dd>
                        <dt>Account status</dt>
                        <dd><?= clinic_h(ucfirst($clinic['account_status'])) ?></dd>
                    </dl>
                    <h3>Update contact information</h3>
                    <form class="clinic-form" method="post" action="action.php"><input type="hidden" name="csrf_token"
                            value="<?= clinic_h($csrfToken) ?>"><input type="hidden" name="action"
                            value="clinic_profile_update"><label>Contact number<input name="contact_number" maxlength="30"
                                required value="<?= clinic_h($clinic['contact_number']) ?>"></label><label>Official
                            email<input type="email" name="official_email" maxlength="255" required
                                value="<?= clinic_h($clinic['official_email']) ?>"></label><label>Authorized
                            representative<input name="authorized_representative" maxlength="160" required
                                value="<?= clinic_h($clinic['authorized_representative']) ?>"></label><label>Description<textarea
                                name="description" maxlength="1000"
                                rows="4"><?= clinic_h($clinic['description']) ?></textarea></label><button
                            class="clinic-primary">Save contact information</button></form>
                </section>
            <?php elseif ($page === 'settings'): ?>
                <section class="clinic-panel">
                    <h2><?= $mustChange ? 'Change temporary password' : 'Account settings' ?></h2><?php if (!$mustChange): ?>
                        <p>Change your account password here.</p><?php endif; ?>
                    <form class="clinic-form" method="post" action="action.php"><input type="hidden" name="csrf_token"
                            value="<?= clinic_h($csrfToken) ?>"><input type="hidden" name="action"
                            value="clinic_password_change"><label>Current password<input type="password"
                                name="current_password" autocomplete="current-password" required></label><label>New
                            password<input type="password" name="new_password" minlength="12" autocomplete="new-password"
                                required></label><label>Confirm new password<input type="password" name="confirm_password"
                                minlength="12" autocomplete="new-password" required></label><button
                            class="clinic-primary">Update password</button></form>
                </section><?php endif; ?>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../validation/auth-notices.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script src="../validation/location-picker.js"></script>
    <script src="../validation/blood-donation-map.js"></script>
    <script src="../validation/clinic-dashboard.js"></script><?php if ($flash): ?>
        <script>if (window.Swal) Swal.fire({ icon: <?= json_encode($flash['type'] === 'error' ? 'error' : 'success') ?>, text: <?= json_encode($flash['message']) ?>, timer: 2600, showConfirmButton: false });</script>
    <?php endif; ?>
</body>

</html>
<?php
function clinic_request_fields(array $request = [], bool $requiresPermission = false, array $provinces = []): void
{ ?>
    <div class="form-row"><label>Blood type<select name="blood_type"
                required><?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $type): ?>
                    <option value="<?= clinic_h($type) ?>" <?= ($request['blood_type'] ?? '') === $type ? 'selected' : '' ?>>
                        <?= clinic_h($type) ?></option><?php endforeach; ?>
            </select></label><label>Units<input type="number" name="units" min="1" max="20" required
                value="<?= (int) ($request['units'] ?? 1) ?>"></label></div><label>Facility / hospital<input name="hospital"
            maxlength="160" required value="<?= clinic_h($request['hospital'] ?? '') ?>"></label>
    <?php blife_location_picker($provinces, 'request', 'clinicRequestMap' . (int) ($request['id'] ?? 0), ['province' => $request['location'] ?? '', 'city' => $request['city'] ?? '', 'barangay' => $request['barangay'] ?? '', 'latitude' => $request['latitude'] ?? '', 'longitude' => $request['longitude'] ?? ''], 'location'); ?>
    <div class="form-row"><label>Needed by<input type="date" name="needed_by" min="<?= clinic_h(date('Y-m-d')) ?>" required
                value="<?= clinic_h($request['needed_by'] ?? '') ?>"></label><label>Urgency<select name="urgency"
                required><?php foreach (['routine' => 'Routine', 'urgent' => 'Urgent', 'emergency' => 'Emergency'] as $value => $label): ?>
                    <option value="<?= clinic_h($value) ?>" <?= ($request['urgency'] ?? 'routine') === $value ? 'selected' : '' ?>>
                        <?= clinic_h($label) ?></option><?php endforeach; ?>
            </select></label></div>
    <label>Additional information <span class="optional">(optional)</span><textarea name="details" maxlength="1000" rows="3"><?= clinic_h($request['details'] ?? '') ?></textarea></label>
    <?php if ($requiresPermission): ?><label
            class="clinic-permission-check">
            <input type="checkbox" name="represented_person_permission" value="1"
                required><span>I confirm the patient or their authorized representative gave permission for this request. I have
                not included their name, phone number, or other unnecessary identifying details.</span></label><?php endif; ?>
<?php }

function clinic_event_fields(array $event = []): void
{ ?>
    <label>Event title<input name="event_title" maxlength="160" required value="<?= clinic_h($event['event_title'] ?? '') ?>"></label>
    <label>Description<textarea name="content" maxlength="1000" rows="4" required><?= clinic_h($event['content'] ?? '') ?></textarea></label>
    <div class="form-row"><label>Organizer<input name="organizer" maxlength="160" value="<?= clinic_h($event['organizer'] ?? '') ?>"></label><label>Date<input name="event_date" type="date" required value="<?= clinic_h($event['event_date'] ?? '') ?>"></label></div>
    <div class="form-row"><label>Time<input name="event_time" type="time" value="<?= clinic_h(isset($event['event_time']) ? substr($event['event_time'], 0, 5) : '') ?>"></label><label>Venue<input name="venue" maxlength="180" value="<?= clinic_h($event['venue'] ?? '') ?>"></label></div>
    <div class="form-row"><label>City<input name="event_city" maxlength="120" value="<?= clinic_h($event['event_city'] ?? '') ?>"></label><label>Public contact<input name="public_contact" maxlength="180" value="<?= clinic_h($event['public_contact'] ?? '') ?>"></label></div>
    <label>Event status<select name="event_status" required><?php foreach (['published', 'draft', 'cancelled', 'completed'] as $status): ?><option value="<?= clinic_h($status) ?>" <?= ($event['event_status'] ?? 'published') === $status ? 'selected' : '' ?>><?= clinic_h(ucfirst($status)) ?></option><?php endforeach; ?></select></label>
    <p class="form-help">Published events are visible to members. Include only public event contact information.</p>
<?php }
?>
