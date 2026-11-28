<?php

session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/member_session_security.php';
require_once __DIR__ . '/../includes/location_picker.php';
require_once __DIR__ . '/../includes/blood_donation_map.php';

require_login('../login.php');

if (is_admin() || is_clinic()) {
    header('Location: ' . role_home_path('../'));
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function member_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name), 2);
    $initials = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $initials .= function_exists('mb_substr') ? mb_substr($part, 0, 1) : substr($part, 0, 1);
        }
    }
    return strtoupper($initials ?: 'BL');
}

function post_label(string $type): string
{
    $labels = [
        'community' => 'Community update',
        'donation' => 'Donation offer',
        'event' => 'Donation event',
        'success_story' => 'Success story'
    ];
    return $labels[$type] ?? 'Community update';
}

function render_notification_items(array $notifications, string $csrfToken): void
{
    foreach ($notifications as $notification):
        $targetPage = in_array($notification['notification_type'], ['request_message', 'donation_offer_message'], true)
            ? 'messages'
            : (in_array($notification['notification_type'], ['request_response'], true) ? 'my-requests' : 'my-posts');
        ?>
        <form class="notification-item <?= !$notification['is_read'] ? 'unread' : '' ?>" method="post" action="action.php">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="notification_open"><input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>"><input type="hidden" name="target_page" value="<?= h($targetPage) ?>">
            <button type="submit"><span class="avatar avatar-small"><?= h(member_initials($notification['actor_name'] ?: 'BLife PH member')) ?></span><span><strong><?= h($notification['actor_name'] ?: 'A BLife PH member') ?> <?= h($notification['message']) ?></strong><small><?= h(date('M j, Y g:i a', strtotime($notification['created_at']))) ?></small></span><span class="notification-open">View</span></button>
        </form>
        <?php
    endforeach;
}

$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$philippineProvinces = require __DIR__ . '/../config/philippine_provinces.php';
$pages = [
    'home', 'find-blood', 'blood-requests', 'donation-offers', 'blood-map', 'events', 'activity',
    'my-posts', 'my-requests', 'my-community-posts', 'my-blood-requests', 'my-donation-offers',
    'responded', 'saved', 'messages', 'notifications',
    'profile', 'settings', 'help', 'search'
];
$requestedPage = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : 'home';
$aliases = [
    'overview' => 'home', 'donors' => 'find-blood', 'requests' => 'blood-requests',
    'community' => 'home', 'donations' => 'donation-offers',
    'my-community-posts' => 'my-posts', 'my-blood-requests' => 'my-posts',
    'my-donation-offers' => 'my-posts', 'my-requests' => 'my-posts'
];
$requestedPage = $aliases[$requestedPage] ?? $requestedPage;
$activePage = in_array($requestedPage, $pages, true) ? $requestedPage : 'home';
$searchQuery = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$donorBloodType = isset($_GET['btype']) && is_string($_GET['btype']) ? trim($_GET['btype']) : '';
$donorCity = isset($_GET['city']) && is_string($_GET['city']) ? trim($_GET['city']) : '';
$offerBloodType = isset($_GET['offer_btype']) && is_string($_GET['offer_btype']) ? trim($_GET['offer_btype']) : '';
$offerCity = isset($_GET['offer_city']) && is_string($_GET['offer_city']) ? trim($_GET['offer_city']) : '';
$requestUrgencyFilter = isset($_GET['urgency']) && is_string($_GET['urgency']) && in_array($_GET['urgency'], ['routine','urgent','emergency'], true) ? $_GET['urgency'] : '';
$userId = (int) current_user_id();
$csrfToken = $_SESSION['csrf_token'];
$flash = $_SESSION['member_flash'] ?? null;
unset($_SESSION['member_flash']);
$loginSuccess = isset($_GET['login']) && is_string($_GET['login']) && $_GET['login'] === 'success';
$profileWelcome = isset($_GET['welcome']) && $_GET['welcome'] === '1';

$profile = [
    'fname' => $_SESSION['first_name'] ?? '',
    'lname' => $_SESSION['last_name'] ?? '',
    'email' => $_SESSION['email'] ?? '',
    'btype' => $_SESSION['blood_type'] ?? '',
    'phone' => $_SESSION['phone'] ?? '',
    'city' => '',
    'municipality' => '',
    'barangay' => '',
    'donor_available' => 0,
    'notify_request_responses' => 1,
    'notify_post_comments' => 1,
    'show_first_name' => 0,
    'show_blood_type' => 0,
    'show_province' => 0,
    'location_discovery_enabled' => 0,
    'optional_privacy_consent' => 0
];
$activeSessions = [];
$pendingEmailChange = null;
$pendingDeletionRequest = null;
$privacySecurityAvailable = false;
$posts = $openRequests = $myRequests = $respondedRequests = $donors = $commentsByPost = [];
$notificationRows = $searchPosts = $searchRequests = [];
$messageConversations = $selectedThreadMessages = [];
$selectedConversation = null;
$newMessageOffer = null;
$selectedThread = '';
$activeMessageCategory = 'donors';
$unreadNotifications = 0;
$statistics = ['posts' => 0, 'offers' => 0, 'requests' => 0, 'responses' => 0, 'saved' => 0];
$schemaReady = false;
$schemaError = '';
$donationFieldsAvailable = false;
$offerMessagesAvailable = false;
$messageInboxAvailable = false;
$donorVerificationAvailable = false;
$donationTimeAvailable = false;
$donationStatusAvailable = false;
$donationLocationFieldsAvailable = false;

try {
    $pdo = (new Database())->getConnection();
    if (current_role() === 'user' && !member_session_is_active($pdo, $userId)) {
        $_SESSION = [];
        session_destroy();
        header('Location: ../login.php?session_revoked=1');
        exit;
    }
    $requiredTables = ['community_posts', 'blood_requests', 'request_responses', 'request_response_messages', 'post_comments', 'post_interactions', 'member_notifications'];
    $optionalTables = ['donation_offer_messages', 'member_message_reports', 'clinic_profiles', 'member_privacy_preferences', 'member_sessions', 'member_email_change_tokens', 'member_account_deletion_requests'];
    $tablesToCheck = array_merge($requiredTables, $optionalTables);
    $tableQuery = $pdo->prepare(
        'SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (' . implode(',', array_fill(0, count($tablesToCheck), '?')) . ')'
    );
    $tableQuery->execute($tablesToCheck);
    $availableTables = $tableQuery->fetchAll(PDO::FETCH_COLUMN);
    $offerMessagesAvailable = in_array('donation_offer_messages', $availableTables, true);
    $messageReportsAvailable = in_array('member_message_reports', $availableTables, true);
    $clinicProfilesAvailable = in_array('clinic_profiles', $availableTables, true);
    $privacySecurityAvailable = in_array('member_privacy_preferences', $availableTables, true)
        && in_array('member_sessions', $availableTables, true)
        && in_array('member_email_change_tokens', $availableTables, true)
        && in_array('member_account_deletion_requests', $availableTables, true);
    $requiredColumns = [
        'uinfo' => ['fname', 'lname', 'email', 'btype', 'phone', 'city', 'municipality', 'barangay', 'donor_available', 'notify_request_responses', 'notify_post_comments'],
        'community_posts' => ['id', 'user_id', 'post_type', 'content', 'event_date', 'created_at'],
        'blood_requests' => ['id', 'requester_id', 'blood_type', 'units', 'hospital', 'location', 'needed_by', 'urgency', 'details', 'status', 'created_at'],
        'request_responses' => ['id', 'request_id', 'user_id', 'message', 'status', 'created_at'],
        'request_response_messages' => ['id', 'response_id', 'sender_id', 'message', 'created_at'],
        'post_comments' => ['id', 'post_id', 'user_id', 'comment', 'created_at'],
        'post_interactions' => ['post_id', 'user_id', 'interaction', 'created_at'],
        'member_notifications' => ['id', 'user_id', 'actor_id', 'notification_type', 'target_id', 'message', 'is_read', 'created_at']
    ];
    $missingTables = array_diff($requiredTables, $availableTables);
    $missingColumns = [];
    $postTypeColumn = null;
    $eventStatusColumnAvailable = false;
    $donationFieldsAvailable = false;
    $columnQuery = $pdo->prepare(
        'SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    foreach ($requiredColumns as $table => $columns) {
        if (!in_array($table, $availableTables, true) && $table !== 'uinfo') {
            continue;
        }
        $columnQuery->execute([$table]);
        $columnInfo = $columnQuery->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($columns as $column) {
            if (!array_key_exists($column, $columnInfo)) {
                $missingColumns[] = $table . '.' . $column;
            }
        }
        if ($table === 'community_posts') {
            $postTypeColumn = $columnInfo['post_type'] ?? '';
            $eventStatusColumnAvailable = array_key_exists('event_status', $columnInfo);
            $donationTimeAvailable = array_key_exists('donation_available_time', $columnInfo);
            $donationStatusAvailable = array_key_exists('donation_status', $columnInfo);
            $donationFieldsAvailable = isset(
                $columnInfo['donation_blood_type'],
                $columnInfo['donation_location'],
                $columnInfo['donation_available_date']
            );
            $donationLocationFieldsAvailable = isset($columnInfo['donation_province'], $columnInfo['donation_city'], $columnInfo['donation_barangay'], $columnInfo['donation_latitude'], $columnInfo['donation_longitude']);
        }
        if ($table === 'uinfo') {
            $donorVerificationAvailable = array_key_exists('donor_verified', $columnInfo);
        }
    }
    if ($postTypeColumn !== null && strpos($postTypeColumn, 'success_story') === false) {
        $missingColumns[] = 'community_posts.post_type (success_story category)';
    }
    if ($offerMessagesAvailable) {
        $columnQuery->execute(['donation_offer_messages']);
        $offerMessageColumns = $columnQuery->fetchAll(PDO::FETCH_KEY_PAIR);
        $offerMessageRequiredColumns = ['id', 'post_id', 'seeker_id', 'sender_id', 'message', 'urgency', 'created_at'];
        $offerMessagesAvailable = !array_diff($offerMessageRequiredColumns, array_keys($offerMessageColumns));
        $columnQuery->execute(['member_notifications']);
        $notificationColumns = $columnQuery->fetchAll(PDO::FETCH_KEY_PAIR);
        $offerMessagesAvailable = $offerMessagesAvailable
            && strpos($notificationColumns['notification_type'] ?? '', 'donation_offer_message') !== false;
    }
    $requestMessageColumns = [];
    if (in_array('request_response_messages', $availableTables, true)) {
        $columnQuery->execute(['request_response_messages']);
        $requestMessageColumns = $columnQuery->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    $offerReadStateAvailable = true;
    if (in_array('donation_offer_messages', $availableTables, true)) {
        $columnQuery->execute(['donation_offer_messages']);
        $offerReadStateAvailable = array_key_exists('read_at', $columnQuery->fetchAll(PDO::FETCH_KEY_PAIR));
    }
    $messageInboxAvailable = $messageReportsAvailable
        && array_key_exists('read_at', $requestMessageColumns)
        && $offerReadStateAvailable;

    if ($missingTables || $missingColumns) {
        $schemaError = 'Some dashboard features need a database update before they can be used.';
        if ($missingTables) {
            $schemaError .= ' Missing tables: ' . implode(', ', $missingTables) . '.';
        }
        if ($missingColumns) {
            $schemaError .= ' Missing member fields: ' . implode(', ', $missingColumns) . '.';
        }
    } else {
        $schemaReady = true;
        $accountStatusCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'uinfo' AND COLUMN_NAME = 'account_status'"
        );
        $accountStatusCheck->execute();
        $accountStatusAvailable = (int) $accountStatusCheck->fetchColumn() > 0;
        $accountStatusSelect = $accountStatusAvailable ? ', account_status' : '';
        $profileStmt = $pdo->prepare('SELECT fname, lname, email, btype, phone, city, municipality, barangay, donor_available, notify_request_responses, notify_post_comments' . $accountStatusSelect . ' FROM uinfo WHERE id = :id LIMIT 1');
        $profileStmt->execute([':id' => $userId]);
        $storedProfile = $profileStmt->fetch();
        if (!$storedProfile) {
            session_destroy();
            header('Location: ../login.php');
            exit;
        }
        if (($storedProfile['account_status'] ?? 'active') !== 'active') {
            session_destroy();
            header('Location: ../login.php?suspended=1');
            exit;
        }
        $profile = array_merge($profile, $storedProfile);
        if (in_array('member_privacy_preferences', $availableTables, true)) {
            $pdo->prepare('INSERT IGNORE INTO member_privacy_preferences (user_id) VALUES (:user_id)')->execute([':user_id' => $userId]);
            $privacyStmt = $pdo->prepare('SELECT show_first_name, show_blood_type, show_province, location_discovery_enabled, optional_privacy_consent FROM member_privacy_preferences WHERE user_id = :user_id');
            $privacyStmt->execute([':user_id' => $userId]);
            $profile = array_merge($profile, $privacyStmt->fetch() ?: []);
        }
        if ($activePage === 'settings' && in_array('member_sessions', $availableTables, true)) {
            $sessionList = $pdo->prepare('SELECT id, user_agent, created_at, last_seen_at, session_hash = :current_hash AS is_current FROM member_sessions WHERE user_id = :user_id AND revoked_at IS NULL AND last_seen_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY is_current DESC, last_seen_at DESC');
            $sessionList->execute([':current_hash' => hash('sha256', session_id()), ':user_id' => $userId]);
            $activeSessions = $sessionList->fetchAll();
        }
        if ($activePage === 'settings' && in_array('member_email_change_tokens', $availableTables, true)) {
            $emailStmt = $pdo->prepare('SELECT new_email, expires_at FROM member_email_change_tokens WHERE user_id = :user_id AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1');
            $emailStmt->execute([':user_id' => $userId]);
            $pendingEmailChange = $emailStmt->fetch() ?: null;
        }
        if ($activePage === 'settings' && in_array('member_account_deletion_requests', $availableTables, true)) {
            $deletionStmt = $pdo->prepare("SELECT id, requested_at FROM member_account_deletion_requests WHERE user_id = :user_id AND status = 'pending' ORDER BY requested_at DESC LIMIT 1");
            $deletionStmt->execute([':user_id' => $userId]);
            $pendingDeletionRequest = $deletionStmt->fetch() ?: null;
        }
        $memberProfileComplete = trim((string) ($storedProfile['fname'] ?? '')) !== ''
            && trim((string) ($storedProfile['lname'] ?? '')) !== ''
            && trim((string) ($storedProfile['phone'] ?? '')) !== ''
            && !empty($storedProfile['btype'])
            && trim((string) ($storedProfile['city'] ?? '')) !== ''
            && trim((string) ($storedProfile['municipality'] ?? '')) !== '';
        if (!$memberProfileComplete && $activePage !== 'profile') {
            header('Location: dashboard.php?page=profile&welcome=1');
            exit;
        }

        $countStmt = $pdo->prepare(
            "SELECT
                (SELECT COUNT(*) FROM community_posts WHERE user_id = :posts_user AND post_type = 'community') AS posts,
                (SELECT COUNT(*) FROM community_posts WHERE user_id = :offers_user AND post_type = 'donation') AS offers,
                (SELECT COUNT(*) FROM blood_requests WHERE requester_id = :requests_user) AS requests,
                (SELECT COUNT(*) FROM request_responses WHERE user_id = :responses_user) AS responses,
                (SELECT COUNT(*) FROM post_interactions WHERE user_id = :saved_user AND interaction = 'save') AS saved"
        );
        $countStmt->execute([
            ':posts_user' => $userId,
            ':offers_user' => $userId,
            ':requests_user' => $userId,
            ':responses_user' => $userId,
            ':saved_user' => $userId
        ]);
        $statistics = $countStmt->fetch();

        $unreadStmt = $pdo->prepare('SELECT COUNT(*) FROM member_notifications WHERE user_id = :user_id AND is_read = 0');
        $unreadStmt->execute([':user_id' => $userId]);
        $unreadNotifications = (int) $unreadStmt->fetchColumn();

        $privacyJoin = in_array('member_privacy_preferences', $availableTables, true)
            ? ' LEFT JOIN member_privacy_preferences mp ON mp.user_id = u.id '
            : ' ';
        $donorConditions = ['u.donor_available = 1', 'u.id <> :member_id'];
        if (in_array('member_privacy_preferences', $availableTables, true)) {
            $donorConditions[] = 'mp.location_discovery_enabled = 1';
        }
        if ($accountStatusAvailable) {
            $donorConditions[] = "u.account_status = 'active'";
        }
        $donorParams = [':member_id' => $userId];
        if ($donorBloodType !== '' && in_array($donorBloodType, $bloodTypes, true)) {
            $donorConditions[] = 'u.btype = :blood_type';
            $donorParams[':blood_type'] = $donorBloodType;
        }
        if ($donorCity !== '') {
            $donorConditions[] = 'u.city LIKE :city';
            $donorParams[':city'] = '%' . $donorCity . '%';
        }
        $donorDisplayFields = in_array('member_privacy_preferences', $availableTables, true)
            ? "CASE WHEN COALESCE(mp.show_first_name, 0) = 1 THEN u.fname ELSE 'BLife PH member' END AS fname, CASE WHEN COALESCE(mp.show_blood_type, 0) = 1 THEN u.btype ELSE 'Not shared' END AS btype, CASE WHEN COALESCE(mp.show_province, 0) = 1 THEN u.city ELSE 'Location private' END AS city"
            : 'u.fname, u.btype, u.city';
        $donorStmt = $pdo->prepare('SELECT ' . $donorDisplayFields . ' FROM uinfo u' . $privacyJoin . 'WHERE ' . implode(' AND ', $donorConditions) . ' ORDER BY u.city, u.fname LIMIT 50');
        $donorStmt->execute($donorParams);
        $donors = $donorStmt->fetchAll();

        $myRequestsStmt = $pdo->prepare(
            "SELECT br.*, CASE WHEN br.status = 'open' AND br.needed_by < CURDATE() THEN 'expired' ELSE br.status END AS effective_status,
                (SELECT COUNT(*) FROM request_responses rr WHERE rr.request_id = br.id) AS response_count
             FROM blood_requests br WHERE br.requester_id = :user_id
             ORDER BY br.created_at DESC LIMIT 50"
        );
        $myRequestsStmt->execute([':user_id' => $userId]);
        $myRequests = $myRequestsStmt->fetchAll();

        $requestResponsesByRequest = [];
        $myRequestResponsesStmt = $pdo->prepare(
            'SELECT rr.id AS response_id, rr.request_id, rr.user_id AS responder_id, rr.message, rr.status AS approval_status, rr.created_at, responder.fname, responder.lname
             FROM request_responses rr
             JOIN blood_requests br ON br.id = rr.request_id
             JOIN uinfo responder ON responder.id = rr.user_id
             WHERE br.requester_id = :user_id
             ORDER BY rr.created_at DESC'
        );
        $myRequestResponsesStmt->execute([':user_id' => $userId]);
        foreach ($myRequestResponsesStmt->fetchAll() as $response) {
            $requestResponsesByRequest[(int) $response['request_id']][] = $response;
        }

        $respondedStmt = $pdo->prepare(
            'SELECT br.*, rr.id AS response_id, rr.message AS response_message, rr.status AS approval_status, rr.created_at AS responded_at, u.fname AS requester_name
             FROM request_responses rr
             JOIN blood_requests br ON br.id = rr.request_id
             JOIN uinfo u ON u.id = br.requester_id
             WHERE rr.user_id = :user_id
             ORDER BY rr.created_at DESC LIMIT 50'
        );
        $respondedStmt->execute([':user_id' => $userId]);
        $respondedRequests = $respondedStmt->fetchAll();

        foreach ($myRequests as &$request) {
            $request['responses'] = $requestResponsesByRequest[(int) $request['id']] ?? [];
        }
        unset($request);

        $requestConditions = ["br.status = 'open'", 'br.needed_by >= CURDATE()', "COALESCE(br.details, '') NOT LIKE '%map-api-test-%'"];
        $requestParams = [];
        if ($activePage === 'blood-requests' && $requestUrgencyFilter !== '') {
            $requestConditions[] = 'br.urgency = :urgency_filter';
            $requestParams[':urgency_filter'] = $requestUrgencyFilter;
        }
        if ($activePage === 'search' && $searchQuery !== '') {
            foreach (['hospital', 'location', 'type', 'details'] as $field) {
                $requestParams[':request_' . $field . '_search'] = '%' . $searchQuery . '%';
            }
            $requestConditions[] = '(br.hospital LIKE :request_hospital_search OR br.location LIKE :request_location_search OR br.blood_type LIKE :request_type_search OR br.details LIKE :request_details_search)';
        }
        $clinicRequestSelect = $clinicProfilesAvailable ? ', clinic_requester.facility_name AS requester_facility_name, clinic_requester.is_verified AS requester_facility_verified' : ', NULL AS requester_facility_name, 0 AS requester_facility_verified';
        $clinicRequestJoin = $clinicProfilesAvailable ? ' LEFT JOIN clinic_profiles clinic_requester ON clinic_requester.user_id = br.requester_id' : '';
        $communityRequestStmt = $pdo->prepare(
            "SELECT br.*, CASE WHEN br.status = 'open' AND br.needed_by < CURDATE() THEN 'expired' ELSE br.status END AS effective_status,
                u.fname AS requester_name, u.lname AS requester_last_name{$clinicRequestSelect},
                EXISTS (SELECT 1 FROM request_responses rr WHERE rr.request_id = br.id AND rr.user_id = :responder_id) AS already_responded,
                (SELECT rr.status FROM request_responses rr WHERE rr.request_id = br.id AND rr.user_id = :response_status_viewer_id) AS viewer_response_status,
                (SELECT COUNT(*) FROM request_responses rr WHERE rr.request_id = br.id) AS response_count
             FROM blood_requests br JOIN uinfo u ON u.id = br.requester_id{$clinicRequestJoin}
             WHERE " . implode(' AND ', $requestConditions) . '
             ORDER BY FIELD(br.urgency, \'emergency\', \'urgent\', \'routine\'), br.needed_by ASC, br.created_at DESC'
        );
        $communityRequestStmt->execute(array_merge([
            ':responder_id' => $userId,
            ':response_status_viewer_id' => $userId
        ], $requestParams));
        $openRequests = $communityRequestStmt->fetchAll();
        foreach ($openRequests as &$request) {
            if ((int) $request['requester_id'] === $userId) {
                $request['responses'] = $requestResponsesByRequest[(int) $request['id']] ?? [];
            }
        }
        unset($request);

        $postConditions = ["cp.content NOT LIKE '%map-api-test-%'"];
        if ($eventStatusColumnAvailable) {
            $postConditions[] = "(cp.post_type <> 'event' OR cp.event_status = 'published')";
        }
        $postParams = [
            ':like_viewer' => $userId,
            ':save_viewer' => $userId,
            ':report_viewer' => $userId
        ];
        if ($activePage === 'my-posts') {
            $postConditions[] = 'cp.user_id = :author_id';
            $postParams[':author_id'] = $userId;
            $postConditions[] = "cp.post_type IN ('community', 'donation')";
        } elseif ($activePage === 'saved') {
            $postConditions[] = "EXISTS (SELECT 1 FROM post_interactions saved WHERE saved.post_id = cp.id AND saved.user_id = :saved_viewer AND saved.interaction = 'save')";
            $postParams[':saved_viewer'] = $userId;
        } elseif ($activePage === 'events') {
            $postConditions[] = "cp.post_type = 'event'";
            $postConditions[] = '(cp.event_date IS NULL OR cp.event_date >= CURDATE())';
        } elseif ($activePage === 'donation-offers') {
            $postConditions[] = "cp.post_type = 'donation'";
            if ($donationStatusAvailable) $postConditions[] = "cp.donation_status = 'available'";
            if ($donationFieldsAvailable && in_array($offerBloodType, $bloodTypes, true)) {
                $postConditions[] = 'cp.donation_blood_type = :offer_blood_type';
                $postParams[':offer_blood_type'] = $offerBloodType;
            }
            if ($donationFieldsAvailable && $offerCity !== '') {
                $postConditions[] = 'cp.donation_location LIKE :offer_city';
                $postParams[':offer_city'] = '%' . $offerCity . '%';
            }
            if ($donationFieldsAvailable) {
                $postConditions[] = '(cp.donation_available_date IS NULL OR cp.donation_available_date >= CURDATE())';
            }
        } elseif ($activePage === 'home') {
            $postConditions[] = "cp.post_type = 'community'";
        } elseif ($activePage === 'search' && $searchQuery !== '') {
            $postConditions[] = '(cp.content LIKE :post_content_search OR u.fname LIKE :post_name_search OR u.city LIKE :post_city_search)';
            $postParams[':post_content_search'] = '%' . $searchQuery . '%';
            $postParams[':post_name_search'] = '%' . $searchQuery . '%';
            $postParams[':post_city_search'] = '%' . $searchQuery . '%';
        }
        $postWhere = $postConditions ? ('WHERE ' . implode(' AND ', $postConditions)) : '';
        $eventSort = $activePage === 'events';
        $postOrder = $eventSort
            ? 'COALESCE(cp.event_date, DATE(cp.created_at)) ASC, cp.created_at DESC'
            : 'cp.created_at DESC';
        $extraPostColumns = [];
        if ($eventStatusColumnAvailable) {
            $extraPostColumns[] = 'cp.event_title';
            $extraPostColumns[] = 'cp.event_time';
            $extraPostColumns[] = 'cp.organizer';
            $extraPostColumns[] = 'cp.venue';
            $extraPostColumns[] = 'cp.event_city';
            $extraPostColumns[] = 'cp.public_contact';
        }
        if ($donationFieldsAvailable) {
            $extraPostColumns[] = 'cp.donation_blood_type';
            $extraPostColumns[] = 'cp.donation_location';
            $extraPostColumns[] = 'cp.donation_available_date';
            if ($donationTimeAvailable) {
                $extraPostColumns[] = 'cp.donation_available_time';
            }
            if ($donationLocationFieldsAvailable) {
                foreach (['donation_province', 'donation_city', 'donation_barangay', 'donation_latitude', 'donation_longitude'] as $locationColumn) $extraPostColumns[] = 'cp.' . $locationColumn;
            }
        }
        if ($donationStatusAvailable) $extraPostColumns[] = 'cp.donation_status';
        if ($clinicProfilesAvailable) {
            $extraPostColumns[] = 'clinic_author.facility_name AS author_facility_name';
            $extraPostColumns[] = 'clinic_author.is_verified AS author_facility_verified';
        } else {
            $extraPostColumns[] = 'NULL AS author_facility_name';
            $extraPostColumns[] = '0 AS author_facility_verified';
        }
        if ($donorVerificationAvailable) {
            $extraPostColumns[] = 'u.donor_verified';
        }
        $extraPostSelect = $extraPostColumns ? ', ' . implode(', ', $extraPostColumns) : '';
        $offerContactCountSelect = $offerMessagesAvailable
            ? ', (SELECT COUNT(DISTINCT offer_message.seeker_id) FROM donation_offer_messages offer_message WHERE offer_message.post_id = cp.id) AS offer_contact_count'
            : ', 0 AS offer_contact_count';
        $clinicAuthorJoin = $clinicProfilesAvailable ? ' LEFT JOIN clinic_profiles clinic_author ON clinic_author.user_id = cp.user_id' : '';
        $postStmt = $pdo->prepare(
            "SELECT cp.id, cp.user_id, cp.post_type, cp.content, cp.event_date, cp.created_at{$extraPostSelect}{$offerContactCountSelect},
                    u.fname AS author_name, u.lname AS author_last_name, u.city AS author_city,
                    (SELECT COUNT(*) FROM post_interactions pi WHERE pi.post_id = cp.id AND pi.interaction = 'like') AS like_count,
                    (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = cp.id) AS comment_count,
                    EXISTS (SELECT 1 FROM post_interactions mine WHERE mine.post_id = cp.id AND mine.user_id = :like_viewer AND mine.interaction = 'like') AS liked_by_me,
                    EXISTS (SELECT 1 FROM post_interactions mine WHERE mine.post_id = cp.id AND mine.user_id = :save_viewer AND mine.interaction = 'save') AS saved_by_me,
                    EXISTS (SELECT 1 FROM post_interactions mine WHERE mine.post_id = cp.id AND mine.user_id = :report_viewer AND mine.interaction = 'report') AS reported_by_me
             FROM community_posts cp JOIN uinfo u ON u.id = cp.user_id{$clinicAuthorJoin}
             {$postWhere}
             ORDER BY {$postOrder} LIMIT 40"
        );
        $postStmt->execute($postParams);
        $posts = $postStmt->fetchAll();
        if ($activePage === 'search') {
            $searchPosts = $posts;
        }

        if ($posts && $activePage !== 'donation-offers') {
            $postIds = array_map(static function ($post) {
                return (int) $post['id'];
            }, $posts);
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));
            $commentStmt = $pdo->prepare(
                "SELECT pc.post_id, pc.user_id AS commenter_id, pc.comment, pc.created_at, u.fname AS author_name, u.lname AS author_last_name
                 FROM post_comments pc JOIN uinfo u ON u.id = pc.user_id
                 WHERE pc.post_id IN ({$placeholders}) ORDER BY pc.created_at ASC"
            );
            $commentStmt->execute($postIds);
            foreach ($commentStmt->fetchAll() as $comment) {
                $commentsByPost[$comment['post_id']][] = $comment;
            }
        }

        if ($activePage === 'search' && $searchQuery !== '') {
            $searchRequests = $openRequests;
        }

        $notificationStmt = $pdo->prepare(
            'SELECT n.id, n.notification_type, n.target_id, n.actor_id, n.message, n.is_read, n.created_at,
                    u.fname AS actor_name
             FROM member_notifications n LEFT JOIN uinfo u ON u.id = n.actor_id
             WHERE n.user_id = :user_id ORDER BY n.created_at DESC LIMIT 100'
        );
        $notificationStmt->execute([':user_id' => $userId]);
        $notificationRows = $notificationStmt->fetchAll();

        if ($activePage === 'messages' && $messageInboxAvailable) {
            if ($offerMessagesAvailable) {
                $offerInboxStmt = $pdo->prepare(
                    'SELECT threads.post_id, threads.seeker_id, cp.user_id AS donor_id, cp.content AS offer_content,
                            cp.donation_blood_type, cp.donation_location, cp.donation_available_date,
                            latest.message AS latest_message, latest.sender_id AS latest_sender_id, latest.created_at AS latest_at,
                            (SELECT first_message.urgency FROM donation_offer_messages first_message
                             WHERE first_message.post_id = threads.post_id AND first_message.seeker_id = threads.seeker_id
                               AND first_message.urgency IS NOT NULL
                             ORDER BY first_message.id LIMIT 1) AS urgency,
                            donor.fname AS donor_first, donor.lname AS donor_last,
                            seeker.fname AS seeker_first, seeker.lname AS seeker_last,
                            (SELECT COUNT(*) FROM donation_offer_messages unread
                             WHERE unread.post_id = threads.post_id AND unread.seeker_id = threads.seeker_id
                               AND unread.sender_id <> :unread_user AND unread.read_at IS NULL) AS unread_count
                     FROM (
                         SELECT post_id, seeker_id, MAX(id) AS latest_id
                         FROM donation_offer_messages GROUP BY post_id, seeker_id
                     ) threads
                     JOIN donation_offer_messages latest ON latest.id = threads.latest_id
                     JOIN community_posts cp ON cp.id = threads.post_id
                     JOIN uinfo donor ON donor.id = cp.user_id
                     JOIN uinfo seeker ON seeker.id = threads.seeker_id
                     WHERE cp.user_id = :offer_owner OR threads.seeker_id = :offer_seeker
                     ORDER BY latest.created_at DESC'
                );
                $offerInboxStmt->execute([
                    ':unread_user' => $userId,
                    ':offer_owner' => $userId,
                    ':offer_seeker' => $userId
                ]);
                foreach ($offerInboxStmt->fetchAll() as $conversation) {
                    $isOfferOwner = (int) $conversation['donor_id'] === $userId;
                    $peerName = $isOfferOwner
                        ? trim($conversation['seeker_first'] . ' ' . $conversation['seeker_last'])
                        : trim($conversation['donor_first'] . ' ' . $conversation['donor_last']);
                    $conversation['kind'] = 'donation_offer';
                    $conversation['category'] = $isOfferOwner ? 'requesters' : 'donors';
                    $conversation['conversation_status'] = 'accepted';
                    $conversation['thread_key'] = 'offer-' . (int) $conversation['post_id'] . '-' . (int) $conversation['seeker_id'];
                    $conversation['peer_name'] = $peerName !== '' ? $peerName : 'BLife PH member';
                    $conversation['peer_initials'] = member_initials($conversation['peer_name']);
                    $conversation['context'] = 'Donation offer · ' . ($conversation['donation_blood_type'] ?: 'Blood type not specified');
                    $conversation['unread_count'] = (int) $conversation['unread_count'];
                    $messageConversations[] = $conversation;
                }
            }

            $requestInboxStmt = $pdo->prepare(
                'SELECT rr.id AS response_id, rr.status AS approval_status, rr.created_at AS started_at, br.id AS request_id, br.requester_id, rr.user_id AS responder_id,
                        br.blood_type, br.hospital, br.location, br.urgency AS request_urgency, br.status AS request_status, br.needed_by,
                        latest.message AS latest_message, latest.sender_id AS latest_sender_id, latest.created_at AS latest_at,
                        requester.fname AS requester_first, requester.lname AS requester_last,
                        responder.fname AS responder_first, responder.lname AS responder_last,
                        (SELECT COUNT(*) FROM request_response_messages unread
                         WHERE unread.response_id = rr.id AND unread.sender_id <> :request_unread_user
                           AND unread.read_at IS NULL) AS unread_count
                 FROM request_responses rr
                 JOIN blood_requests br ON br.id = rr.request_id
                 LEFT JOIN request_response_messages latest ON latest.id = (
                     SELECT MAX(last_message.id) FROM request_response_messages last_message
                     WHERE last_message.response_id = rr.id
                 )
                 JOIN uinfo requester ON requester.id = br.requester_id
                 JOIN uinfo responder ON responder.id = rr.user_id
                 WHERE rr.status IN (\'pending\', \'accepted\')
                   AND (br.requester_id = :request_owner OR rr.user_id = :request_responder)
                 ORDER BY latest.created_at DESC'
            );
            $requestInboxStmt->execute([
                ':request_unread_user' => $userId,
                ':request_owner' => $userId,
                ':request_responder' => $userId
            ]);
            foreach ($requestInboxStmt->fetchAll() as $conversation) {
                $isRequester = (int) $conversation['requester_id'] === $userId;
                $peerName = $isRequester
                    ? trim($conversation['responder_first'] . ' ' . $conversation['responder_last'])
                    : trim($conversation['requester_first'] . ' ' . $conversation['requester_last']);
                $conversation['kind'] = 'request_response';
                $conversation['category'] = $isRequester ? 'donors' : 'requesters';
                $conversation['viewer_is_request_owner'] = $isRequester;
                $conversation['conversation_status'] = $conversation['approval_status'] ?? 'accepted';
                $conversation['thread_key'] = 'request-' . (int) $conversation['response_id'];
                $conversation['peer_name'] = $peerName !== '' ? $peerName : 'BLife PH member';
                $conversation['peer_initials'] = member_initials($conversation['peer_name']);
                $conversation['context'] = 'Blood request · ' . $conversation['blood_type'] . ' · ' . $conversation['hospital'];
                $conversation['latest_at'] = $conversation['latest_at'] ?: $conversation['started_at'];
                $conversation['latest_message'] = $conversation['latest_message'] ?: 'No messages yet';
                $conversation['unread_count'] = (int) $conversation['unread_count'];
                $messageConversations[] = $conversation;
            }

            usort($messageConversations, static function (array $first, array $second): int {
                return strcmp($second['latest_at'], $first['latest_at']);
            });

            $requestedThread = isset($_GET['thread']) && is_string($_GET['thread']) ? $_GET['thread'] : '';
            $requestedCategory = isset($_GET['category']) && is_string($_GET['category']) && in_array($_GET['category'], ['donors', 'requesters'], true)
                ? $_GET['category']
                : (isset($_GET['offer']) ? 'donors' : null);
            $activeMessageCategory = $requestedCategory ?? ($messageConversations[0]['category'] ?? 'donors');
            foreach ($messageConversations as $conversation) {
                if ($conversation['thread_key'] === $requestedThread) {
                    $selectedConversation = $conversation;
                    $selectedThread = $requestedThread;
                    $activeMessageCategory = $conversation['category'];
                    break;
                }
            }
            if ($selectedConversation === null && $requestedThread === '' && !isset($_GET['offer']) && $messageConversations) {
                foreach ($messageConversations as $conversation) {
                    if ($conversation['category'] === $activeMessageCategory) {
                        $selectedConversation = $conversation;
                        break;
                    }
                }
                if ($selectedConversation === null && $requestedCategory === null) {
                    $selectedConversation = $messageConversations[0];
                    $activeMessageCategory = $selectedConversation['category'];
                }
                if ($selectedConversation !== null) {
                    $selectedThread = $selectedConversation['thread_key'];
                }
            }

            $offerId = null;
            if (isset($_GET['offer']) && is_string($_GET['offer']) && ctype_digit($_GET['offer'])) {
                $offerId = (int) $_GET['offer'];
            } elseif (
                $selectedConversation === null
                && preg_match('/^offer-(\d+)-(\d+)$/', $requestedThread, $threadParts)
                && (int) $threadParts[2] === $userId
            ) {
                $offerId = (int) $threadParts[1];
            }
            if ($selectedConversation === null && $offerId !== null) {
                $offerSelect = 'cp.id, cp.user_id, cp.content, cp.donation_blood_type, cp.donation_location, cp.donation_available_date';
                if ($donationTimeAvailable) {
                    $offerSelect .= ', cp.donation_available_time';
                }
                if ($donorVerificationAvailable) {
                    $offerSelect .= ', donor.donor_verified';
                }
                $newOfferStmt = $pdo->prepare(
                    "SELECT {$offerSelect}, donor.fname, donor.lname
                     FROM community_posts cp JOIN uinfo donor ON donor.id = cp.user_id
                     WHERE cp.id = :post_id AND cp.post_type = 'donation'
                       AND cp.user_id <> :viewer_id
                       AND (cp.donation_available_date IS NULL OR cp.donation_available_date >= CURDATE())
                     LIMIT 1"
                );
                $newOfferStmt->execute([':post_id' => $offerId, ':viewer_id' => $userId]);
                $newMessageOffer = $newOfferStmt->fetch() ?: null;
                if ($newMessageOffer) {
                    $newThreadKey = 'offer-' . $offerId . '-' . $userId;
                    foreach ($messageConversations as $conversation) {
                        if ($conversation['thread_key'] === $newThreadKey) {
                            $selectedConversation = $conversation;
                            $selectedThread = $newThreadKey;
                            $activeMessageCategory = $conversation['category'];
                            $newMessageOffer = null;
                            break;
                        }
                    }
                }
            }

            if ($selectedConversation !== null) {
                if ($selectedConversation['kind'] === 'donation_offer') {
                    $postId = (int) $selectedConversation['post_id'];
                    $seekerId = (int) $selectedConversation['seeker_id'];
                    $markReadStmt = $pdo->prepare(
                        'UPDATE donation_offer_messages
                         SET read_at = NOW()
                         WHERE post_id = :post_id AND seeker_id = :seeker_id
                           AND sender_id <> :viewer_id AND read_at IS NULL'
                    );
                    $markReadStmt->execute([':post_id' => $postId, ':seeker_id' => $seekerId, ':viewer_id' => $userId]);
                    $peerId = (int) $selectedConversation['donor_id'] === $userId
                        ? $seekerId
                        : (int) $selectedConversation['donor_id'];
                    $markNotificationStmt = $pdo->prepare(
                        'UPDATE member_notifications SET is_read = 1
                         WHERE user_id = :viewer_id AND actor_id = :peer_id
                           AND notification_type = \'donation_offer_message\'
                           AND target_id = :post_id AND is_read = 0'
                    );
                    $markNotificationStmt->execute([
                        ':viewer_id' => $userId,
                        ':peer_id' => $peerId,
                        ':post_id' => $postId
                    ]);
                    $historyStmt = $pdo->prepare(
                        'SELECT m.id, m.sender_id, m.message, m.created_at,
                                sender.fname, sender.lname,
                                EXISTS (SELECT 1 FROM member_message_reports mr
                                        WHERE mr.reporter_id = :reporter_id
                                          AND mr.message_type = \'donation_offer\' AND mr.message_id = m.id) AS reported_by_me
                         FROM donation_offer_messages m JOIN uinfo sender ON sender.id = m.sender_id
                         WHERE m.post_id = :history_post AND m.seeker_id = :history_seeker
                         ORDER BY m.created_at, m.id'
                    );
                    $historyStmt->execute([
                        ':reporter_id' => $userId,
                        ':history_post' => $postId,
                        ':history_seeker' => $seekerId
                    ]);
                    $selectedThreadMessages = $historyStmt->fetchAll();
                } else {
                    $responseId = (int) $selectedConversation['response_id'];
                    $markReadStmt = $pdo->prepare(
                        'UPDATE request_response_messages
                         SET read_at = NOW()
                         WHERE response_id = :response_id AND sender_id <> :viewer_id AND read_at IS NULL
                           AND EXISTS (
                               SELECT 1 FROM request_responses rr JOIN blood_requests br ON br.id = rr.request_id
                               WHERE rr.id = :thread_response_id
                                 AND (br.requester_id = :requester_id OR rr.user_id = :responder_id)
                           )'
                    );
                    $markReadStmt->execute([
                        ':response_id' => $responseId,
                        ':viewer_id' => $userId,
                        ':thread_response_id' => $responseId,
                        ':requester_id' => $userId,
                        ':responder_id' => $userId
                    ]);
                    $peerId = (int) $selectedConversation['requester_id'] === $userId
                        ? (int) $selectedConversation['responder_id']
                        : (int) $selectedConversation['requester_id'];
                    $markNotificationStmt = $pdo->prepare(
                        'UPDATE member_notifications SET is_read = 1
                         WHERE user_id = :viewer_id AND actor_id = :peer_id
                           AND notification_type = \'request_message\'
                           AND target_id = :request_id AND is_read = 0'
                    );
                    $markNotificationStmt->execute([
                        ':viewer_id' => $userId,
                        ':peer_id' => $peerId,
                        ':request_id' => (int) $selectedConversation['request_id']
                    ]);
                    $historyStmt = $pdo->prepare(
                        'SELECT m.id, m.sender_id, m.message, m.created_at,
                                sender.fname, sender.lname,
                                EXISTS (SELECT 1 FROM member_message_reports mr
                                        WHERE mr.reporter_id = :reporter_id
                                          AND mr.message_type = \'request_response\' AND mr.message_id = m.id) AS reported_by_me
                         FROM request_response_messages m JOIN uinfo sender ON sender.id = m.sender_id
                         WHERE m.response_id = :response_id ORDER BY m.created_at, m.id'
                    );
                    $historyStmt->execute([':reporter_id' => $userId, ':response_id' => $responseId]);
                    $selectedThreadMessages = $historyStmt->fetchAll();
                }
                $selectedConversation['unread_count'] = 0;
                foreach ($messageConversations as &$inboxConversation) {
                    if ($inboxConversation['thread_key'] === $selectedThread) {
                        $inboxConversation['unread_count'] = 0;
                        break;
                    }
                }
                unset($inboxConversation);
                foreach ($notificationRows as &$notification) {
                    if (
                        $notification['notification_type'] === ($selectedConversation['kind'] === 'donation_offer' ? 'donation_offer_message' : 'request_message')
                        && (int) $notification['target_id'] === (int) ($selectedConversation['kind'] === 'donation_offer' ? $selectedConversation['post_id'] : $selectedConversation['request_id'])
                        && (int) $notification['actor_id'] === $peerId
                    ) {
                        $notification['is_read'] = 1;
                    }
                }
                unset($notification);
                $unreadStmt = $pdo->prepare('SELECT COUNT(*) FROM member_notifications WHERE user_id = :user_id AND is_read = 0');
                $unreadStmt->execute([':user_id' => $userId]);
                $unreadNotifications = (int) $unreadStmt->fetchColumn();
            }
        }
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    $schemaError = 'The dashboard could not load its data. Please check the database setup and try again.';
    $schemaReady = false;
}

$fullName = trim(($profile['fname'] ?? '') . ' ' . ($profile['lname'] ?? ''));
$displayName = $fullName !== '' ? $fullName : ($profile['email'] ?? 'Member');
$firstName = trim((string) ($profile['fname'] ?? ''));
$initials = member_initials($displayName);
$pageTitles = [
    'home' => 'Community Feed',
    'find-blood' => 'Find Blood',
    'blood-requests' => 'Blood Requests',
    'donation-offers' => 'Donation Offers',
    'blood-map' => 'Blood Donation Map',
    'events' => 'Donation Events',
    'activity' => 'My Activity',
    'my-posts' => 'My Post',
    'responded' => "My Responses",
    'saved' => 'Saved Posts',
    'messages' => 'Messages',
    'notifications' => 'Notifications',
    'profile' => 'My Profile',
    'settings' => 'Settings & Privacy',
    'help' => 'Help & Support',
    'search' => 'Search Results'
];

function render_post_composer(string $csrfToken, string $initials, bool $donationOnly = false, bool $asModal = false, bool $donationTimeAvailable = false, array $provinces = []): void
{
    $formId = $donationOnly ? 'donationPostComposer' : 'postComposer';
    $modalId = $donationOnly ? 'donationOfferModal' : 'createPostModal';
    $modalTitleId = $donationOnly ? 'donationOfferModalTitle' : 'createPostModalTitle';
    ?>
    <?php if ($donationOnly || $asModal): ?>
        <dialog class="dashboard-modal" id="<?= h($modalId) ?>" aria-labelledby="<?= h($modalTitleId) ?>">
            <div class="modal-heading">
                <div><p class="eyebrow"><?= $donationOnly ? 'POTENTIAL DONOR' : 'COMMUNITY' ?></p><h2 id="<?= h($modalTitleId) ?>"><?= $donationOnly ? 'Offer to donate blood' : 'Create a post' ?></h2></div>
                <button class="modal-close" type="button" data-modal-close aria-label="<?= $donationOnly ? 'Close donation offer form' : 'Close post form' ?>">×</button>
            </div>
    <?php endif; ?>
    <form class="post-composer panel" id="<?= h($formId) ?>" method="post" action="action.php" data-post-composer>
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <input type="hidden" name="action" value="post">
        <div class="composer-top">
            <span class="avatar"><?= h($initials) ?></span>
            <label class="visually-hidden" for="<?= h($formId) ?>Content"><?= $donationOnly ? 'Describe your donation offer' : 'Write a community post' ?></label>
            <textarea id="<?= h($formId) ?>Content" name="content" maxlength="1000" rows="2" required placeholder="<?= $donationOnly ? 'Share when and where you may be available to donate...' : 'Share an update about the BLife PH community' ?>"></textarea>
            <?php if (!$donationOnly): ?>
                <button class="button button-primary" type="submit">Post</button>
            <?php endif; ?>
        </div>
        <div class="composer-bottom">
            <?php if ($donationOnly): ?>
                <input type="hidden" name="post_type" value="donation">
                <span class="composer-category">Publishing as <strong>Donation offer</strong></span>
            <?php else: ?>
                <input type="hidden" name="post_type" value="community">
            <?php endif; ?>
            <?php if ($donationOnly): ?>
                <button class="button button-primary" type="submit">Publish Donation Offer</button>
            <?php endif; ?>
        </div>
        <?php if ($donationOnly): ?>
        <div class="donation-offer-fields" data-donation-fields>
            <!-- <p class="form-help">Let members know what type and area you may be able to help with. Your private phone number is not shown.</p> -->
            <div class="form-row">
                <label>Blood type
                    <select name="donation_blood_type" <?= $donationOnly ? 'required' : '' ?>>
                        <option value="">Select blood type</option>
                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $type): ?>
                            <option value="<?= h($type) ?>"><?= h($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <?php blife_location_picker($provinces, 'donation', 'donationCreateMap', []); ?>
                <div class="form-row">
                    <label>Available date <span class="optional">(optional)</span>
                        <input type="date" name="donation_available_date" min="<?= h(date('Y-m-d')) ?>">
                    </label>
                    <?php if ($donationTimeAvailable): ?>
                        <label>Available time <span class="optional">(optional)</span>
                            <input type="time" name="donation_available_time">
                        </label>
                    <?php endif; ?>
                </div>
        </div>
        <?php else: ?>
            <p class="form-help">Share questions, experiences, or updates related to blood donation and the BLife PH community. Keep posts respectful.</p>
        <?php endif; ?>
        <!-- <p class="form-help">An offer is not confirmed blood availability or medical eligibility. Coordinate screening and donation through an authorized hospital or blood bank.</p> -->
    </form>
    <?php if ($donationOnly || $asModal): ?></dialog><?php endif; ?>
<?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Connect with the BLife PH community, find potential donors, and manage blood requests.">
    <title><?= h($pageTitles[$activePage]) ?> | BLife PH</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/user-dashboard.css">
    <link rel="stylesheet" href="../assets/location-picker.css">
    <link rel="stylesheet" href="../assets/blood-donation-map.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    <header class="topbar member-topbar">
        <div class="member-topbar-primary">
            <button class="icon-button member-nav-toggle" id="memberNavToggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="memberMainNavigation">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
            <a class="brand" href="dashboard.php?page=home" aria-label="BLife PH home">
                <img src="../images/BlifePH_no_bg.png" alt="">
                <span>BLife PH</span>
            </a>
            <form class="global-search" method="get" action="dashboard.php" role="search">
                <input type="hidden" name="page" value="search">
                <label class="visually-hidden" for="globalSearch">Search posts and blood requests</label>
                <span aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input id="globalSearch" type="search" name="q" value="<?= $activePage === 'search' ? h($searchQuery) : '' ?>" placeholder="Search posts, blood requests, events..." maxlength="120">
                <button type="submit" aria-label="Search">Search</button>
            </form>
            <div class="topbar-actions">
                <details class="notification-menu header-dropdown">
                    <summary class="notification-link" aria-label="Notifications<?= $unreadNotifications ? ', ' . $unreadNotifications . ' unread' : '' ?>" aria-controls="notificationDropdown">
                        <i class="fa-regular fa-bell" aria-hidden="true"></i>
                        <?php if ($unreadNotifications > 0): ?><span class="notification-count"><?= $unreadNotifications > 99 ? '99+' : (int) $unreadNotifications ?></span><?php endif; ?>
                    </summary>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-dropdown-heading">
                            <strong>Notifications</strong>
                            <a href="dashboard.php?page=notifications">View all</a>
                        </div>
                        <?php if ($unreadNotifications > 0): ?>
                            <form class="notification-mark-all" method="post" action="action.php">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="action" value="notifications_read_all">
                                <button class="text-button" type="submit">Mark all as read</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!$notificationRows): ?>
                            <div class="empty-state notification-empty"><strong>You're all caught up.</strong><span>Replies to your requests and comments on your posts will appear here.</span></div>
                        <?php else: ?>
                            <div class="notification-list"><?php render_notification_items(array_slice($notificationRows, 0, 5), $csrfToken); ?></div>
                        <?php endif; ?>
                    </div>
                </details>
                <details class="profile-menu header-dropdown">
                    <summary aria-label="Open account menu">
                        <span class="avatar"><?= h($initials) ?></span>
                        <span class="profile-menu-name"><?= h($displayName) ?></span>
                        <i class="fa-solid fa-chevron-down account-chevron" aria-hidden="true"></i>
                    </summary>
                    <div class="profile-dropdown" aria-label="Account">
                        <a href="dashboard.php?page=profile"><i class="fa-regular fa-user" aria-hidden="true"></i><span>My Profile</span></a>
                        <a href="dashboard.php?page=settings"><i class="fa-solid fa-gear" aria-hidden="true"></i><span>Settings &amp; Privacy</span></a>
                        <a href="dashboard.php?page=help"><i class="fa-regular fa-circle-question" aria-hidden="true"></i><span>Help &amp; Support</span></a>
                        <a href="../logout.php"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i><span>Logout</span></a>
                    </div>
                </details>
            </div>
        </div>
        <nav class="member-main-navigation" id="memberMainNavigation" aria-label="Member navigation">
            <a class="member-nav-link <?= $activePage === 'home' ? 'active' : '' ?>" href="dashboard.php?page=home" <?= $activePage === 'home' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-house" aria-hidden="true"></i><span>Home</span></a>
            <a class="member-nav-link <?= $activePage === 'blood-requests' ? 'active' : '' ?>" href="dashboard.php?page=blood-requests" <?= $activePage === 'blood-requests' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-droplet" aria-hidden="true"></i><span>Blood Requests</span></a>
            <a class="member-nav-link <?= $activePage === 'donation-offers' ? 'active' : '' ?>" href="dashboard.php?page=donation-offers" <?= $activePage === 'donation-offers' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-hand-holding-droplet" aria-hidden="true"></i><span>Donation Offers</span></a>
            <a class="member-nav-link <?= $activePage === 'blood-map' ? 'active' : '' ?>" href="dashboard.php?page=blood-map" <?= $activePage === 'blood-map' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i><span>Blood Donation Map</span></a>
            <?php $activityPages = ['activity', 'messages', 'my-posts', 'responded', 'saved']; ?>
            <details class="member-activities-menu header-dropdown <?= in_array($activePage, $activityPages, true) ? 'has-active' : '' ?>">
                <summary class="member-nav-link <?= in_array($activePage, $activityPages, true) ? 'active' : '' ?>"><i class="fa-solid fa-layer-group" aria-hidden="true"></i><span>Activities</span><i class="fa-solid fa-chevron-down menu-chevron" aria-hidden="true"></i></summary>
                <div class="member-nav-dropdown" aria-label="Activities">
                    <a href="dashboard.php?page=activity" <?= $activePage === 'activity' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><span>My Activity</span></a>
                    <a href="dashboard.php?page=messages" <?= $activePage === 'messages' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-comments" aria-hidden="true"></i><span>Messages</span></a>
                    <a href="dashboard.php?page=my-posts" <?= $activePage === 'my-posts' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-file-lines" aria-hidden="true"></i><span>My Post</span></a>
                    <a href="dashboard.php?page=responded" <?= $activePage === 'responded' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-reply" aria-hidden="true"></i><span>My Responses</span></a>
                    <a href="dashboard.php?page=saved" <?= $activePage === 'saved' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-bookmark" aria-hidden="true"></i><span>Saved Posts</span></a>
                </div>
            </details>
        </nav>
    </header>

    <div class="dashboard-layout">
        <main class="dashboard-main<?= $activePage === 'home' ? ' member-feed-page' : '' ?>">
            <?php if ($profileWelcome): ?><div class="notice notice-info" role="status">Welcome to BLife PH! Please complete your profile before continuing.</div><?php endif; ?>
            <?php if ($loginSuccess): ?>
                <!-- <div class="notice notice-success" role="status">Welcome back to BloodLife.</div> -->
            <?php endif; ?>
            <?php if ($flash): ?>
                <div class="notice notice-<?= h($flash['type']) ?>" role="status"><?= h($flash['message']) ?></div>
            <?php endif; ?>
            <?php if (!$schemaReady): ?>
                <div class="notice notice-error schema-notice" role="alert">
                    <strong>Dashboard database update required.</strong>
                    <span><?= h($schemaError) ?></span>
                    <p>Run the migrations in <code>database_sql</code>, including <code>request_response_messages_migration.sql</code> and <code>request_response_acceptance_migration.sql</code>, against the configured database. Existing data is retained.</p>
                </div>
            <?php else: ?>
                <div class="page-heading">
                    <div>
                        <?php if ($activePage === 'home'): ?>
                            <p class="eyebrow">WELCOME BACK<?= $firstName !== '' ? ', ' . h($firstName) : '' ?></p>
                        <?php else: ?>
                            <!-- <p class="eyebrow">BLOODLIFE MEMBER SPACE</p> -->
                        <?php endif; ?>
                        <h1><?= h($pageTitles[$activePage]) ?></h1>

                    </div>
                    <?php if ($activePage === 'home'): ?>
                        <!-- <a class="button button-secondary" href="dashboard.php?page=donation-offers">♥ Donation Offers</a> -->
                        <button class="button button-primary" type="button" data-modal-open="createPostModal">＋ Create Post</button>
                    <?php elseif ($activePage === 'blood-requests'): ?>
                    <button class="button button-primary" type="button" data-modal-open="requestModal">＋ Post a Blood Request</button>
                    <?php elseif ($activePage === 'donation-offers'): ?>
                    <?php if ($donationFieldsAvailable): ?><button class="button button-primary" type="button" data-modal-open="donationOfferModal">Offer Donation</button><?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if ($fullName === '' || !$profile['btype']): ?>
                    <div class="notice notice-info">Complete your profile to help community members coordinate with you. Contact details stay private. <a href="dashboard.php?page=profile">Complete profile</a></div>
                <?php endif; ?>

                <?php if ($activePage === 'home'): ?>
                    <?php render_post_composer($csrfToken, $initials, false, true); ?>
                    <div class="dashboard-columns">
                        <section class="feed-column">
                            <?php if (!$posts): ?>
                                <div class="empty-state panel">
                                    <span class="empty-icon" aria-hidden="true">♥</span>
                                    <strong>Your community feed is ready.</strong>
                                    <span>There are no community posts yet. Be the first to share an update about blood donation or BLife PH.</span>
                                </div>
                            <?php else: ?>
                                <?php foreach ($posts as $post): ?>
                                    <?php render_post_card($post, $commentsByPost[$post['id']] ?? [], $csrfToken, $userId, false, false, $donationStatusAvailable, $donationTimeAvailable, $philippineProvinces); ?>
                                <?php endforeach; ?>
                            <?php endif; ?>

                        </section>

                        <!-- <aside class="right-rail">
                            <section class="panel quick-actions">   
                                <h2>Quick actions</h2>
                                <a href="dashboard.php?page=find-blood"><span aria-hidden="true">⌕</span> Find Blood</a>
                                <a href="dashboard.php?page=blood-requests"><span aria-hidden="true">＋</span> Post a Blood Request</a>
                                <a href="dashboard.php?page=home#postComposer"><span aria-hidden="true">▧</span> Create a Post</a>
                            </section>
                            <section class="panel safety-panel">
                                <p class="eyebrow">COMMUNITY SAFETY</p>
                                <h2>Help safely</h2>
                                <p>BloodLife connects people but does not test, store, transport, or confirm blood inventory.</p>
                                <p>Screening, availability, and transfusions must be handled by authorized hospitals or blood banks.</p>
                            </section>
                            <section class="panel mini-profile">
                                <span class="avatar avatar-large"><?= h($initials) ?></span>
                                <strong><?= h($displayName) ?></strong>
                                <span><?= h($profile['city'] ?: 'Add a city to your profile') ?></span>
                                <a href="dashboard.php?page=profile">View profile</a>
                            </section>
                        </aside> -->
                    </div>
                <?php elseif ($activePage === 'blood-map'): ?>
                    <?php blife_render_blood_donation_map('../api/map_data.php', 'dashboard.php?page=blood-requests#request-', 'dashboard.php?page=donation-offers#post-'); ?>
                <?php elseif ($activePage === 'donation-offers'): ?>

                    <?php if ($donationFieldsAvailable): ?>
                        <?php render_post_composer($csrfToken, $initials, true, false, $donationTimeAvailable, $philippineProvinces); ?>
                    <?php else: ?>
                        <div class="notice notice-error" role="alert">Donation offers need the database update in <code>database_sql/donation_offers_migration.sql</code>. Existing member accounts and blood requests remain unchanged.</div>
                    <?php endif; ?>
                    <?php if ($donationFieldsAvailable): ?>
                        <form class="filter-form donation-offer-filter" method="get" action="dashboard.php">
                            <input type="hidden" name="page" value="donation-offers">
                            <label>Blood type<select name="offer_btype"><option value="">All blood types</option><?php foreach ($bloodTypes as $type): ?><option value="<?= h($type) ?>" <?= $offerBloodType === $type ? 'selected' : '' ?>><?= h($type) ?></option><?php endforeach; ?></select></label>
                            <label>Province<select name="offer_city"><option value="">All provinces</option><?php foreach ($philippineProvinces as $province): ?><option value="<?= h($province) ?>" <?= $offerCity === $province ? 'selected' : '' ?>><?= h($province) ?></option><?php endforeach; ?></select></label>
                            <button class="button button-primary" type="submit">Search offers</button>
                            <?php if ($offerBloodType !== '' || $offerCity !== ''): ?><a class="clear-link" href="dashboard.php?page=donation-offers">Clear filters</a><?php endif; ?>
                        </form>
                    <?php endif; ?>
                    <section class="feed-column donation-offer-list">
                        <!-- <div class="section-heading"><div><p class="eyebrow">DONATION OFFERS</p><h2>Community offers</h2></div></div> -->
                        <?php if (!$offerMessagesAvailable): ?>
                            <div class="notice notice-info" role="status">Private donor conversations need a database update. Run <code>database_sql/donation_offer_messages_migration.sql</code> for a new installation, or <code>database_sql/donation_offer_urgency_migration.sql</code> if conversations are already enabled.</div>
                        <?php endif; ?>
                        <?php if (!$posts): ?>
                            <div class="empty-state panel"><strong>No donation offers have been published yet.</strong><span>You can still request blood or publish the first offer when the database update is applied.</span></div>
                        <?php else: ?>
                            <?php foreach ($posts as $post): ?>
                                <?php render_post_card($post, $commentsByPost[$post['id']] ?? [], $csrfToken, $userId, true, $offerMessagesAvailable, $donationStatusAvailable, $donationTimeAvailable, $philippineProvinces); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>
                <?php elseif ($activePage === 'find-blood'): ?>
                    <section class="panel">
                        <div class="section-heading"><div><p class="eyebrow">COMMUNITY DIRECTORY</p><h2>Find members willing to donate</h2></div></div>
                        <!-- <p class="form-help">Only members who opted in are listed. A member's willingness does not confirm eligibility or blood availability. Contact details and home addresses are not shown.</p> -->
                        <form class="filter-form" method="get" action="dashboard.php">
                            <input type="hidden" name="page" value="find-blood">
                            <label>Blood type<select name="btype"><option value="">Any type</option><?php foreach ($bloodTypes as $type): ?><option value="<?= h($type) ?>" <?= $donorBloodType === $type ? 'selected' : '' ?>><?= h($type) ?></option><?php endforeach; ?></select></label>
                            <label>City or province<input type="search" name="city" value="<?= h($donorCity) ?>" maxlength="120" placeholder="Search location"></label>
                            <button class="button button-primary" type="submit">Search members</button>
                            <?php if ($donorCity !== '' || $donorBloodType !== ''): ?><a class="clear-link" href="dashboard.php?page=find-blood">Clear filters</a><?php endif; ?>
                        </form>
                        <?php if (!$donors): ?>
                            <div class="empty-state"><strong>No opted-in members found.</strong><span>Try a different filter. Hospital and blood-bank directories are not connected yet.</span></div>
                        <?php else: ?>
                            <div class="donor-grid">
                                <?php foreach ($donors as $donor): $donorName = trim($donor['fname'] ?: 'BLife PH member'); ?>
                                    <article class="donor-card"><span class="avatar"><?= h(member_initials($donorName)) ?></span><span class="blood-mark"><?= h($donor['btype']) ?></span><div><h3><?= h($donorName) ?></h3><p><?= h($donor['city'] ?: 'Location not provided') ?></p><small>Willing to be contacted about donating</small></div></article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="notice notice-info facility-notice">No participating hospital or blood-bank directory is configured yet. Please contact an authorized facility directly to confirm availability.</div>
                    </section>
                <?php elseif ($activePage === 'blood-requests'): ?>
                    <div class="dashboard-columns">
                        <section class="feed-column">
                            <div class="section-heading"><div><p class="eyebrow"></p><h2>Need Blood? Request Now</h2></div></div>
                            <p class="form-help"> <a href="dashboard.php?page=donation-offers"> Browse or publish donation offers</a>.</p>
                            <form class="filter-form request-urgency-filter" method="get" action="dashboard.php"><input type="hidden" name="page" value="blood-requests"><label>Urgency<select name="urgency"><option value="">All urgency levels</option><option value="emergency" <?= $requestUrgencyFilter === 'emergency' ? 'selected' : '' ?>>Emergency</option><option value="urgent" <?= $requestUrgencyFilter === 'urgent' ? 'selected' : '' ?>>Urgent</option><option value="routine" <?= $requestUrgencyFilter === 'routine' ? 'selected' : '' ?>>Routine</option></select></label><button class="button button-secondary button-small" type="submit">Filter requests</button><?php if ($requestUrgencyFilter !== ''): ?><a class="clear-link" href="dashboard.php?page=blood-requests">Clear</a><?php endif; ?></form>
                            <p class="urgency-disclaimer">Urgency is reported by the requester and does not replace professional medical assessment. For emergencies, contact emergency services or an authorized hospital.</p>
                            <?php if (!$openRequests): ?><div class="empty-state panel"><strong>No open requests right now.</strong></div><?php else: ?>
                                <?php foreach ($openRequests as $request): ?><?php render_request_card($request, $csrfToken, (int) $request['requester_id'] === $userId, true, $philippineProvinces); ?><?php endforeach; ?>
                            <?php endif; ?>
                        </section>
                        <!-- <aside class="right-rail">
                            <section class="panel safety-panel">
                                <p class="eyebrow">NEED BLOOD?</p>
                                <h2>Post a blood request now</h2>
                                <button class="button button-primary" type="button" data-modal-open="requestModal">＋ Request Blood</button>
                            </section>
                        </aside> -->
                    </div>
                    <dialog class="dashboard-modal" id="requestModal" aria-labelledby="requestModalTitle">
                        <div class="modal-heading">
                            <div><p class="eyebrow">NEED BLOOD?</p><h2 id="requestModalTitle">Post a blood request</h2></div>
                            <button class="modal-close" type="button" data-modal-close aria-label="Close request form">×</button>
                        </div>

                        <form class="stacked-form modal-form" id="requestComposer" method="post" action="action.php">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="request">
                                <p class="form-help">Requests are for coordination only. Care and transfusions remain with authorized facilities.</p>
                                <div class="form-row">
                                    <label>Blood type<select name="blood_type" required><option value="">Select</option><?php foreach ($bloodTypes as $type): ?><option value="<?= h($type) ?>"><?= h($type) ?></option><?php endforeach; ?></select></label>
                                    <label>Units<input type="number" name="units" min="1" max="20" value="1" required></label>
                                </div>
                                <?php blife_location_picker($philippineProvinces, 'request', 'requestCreateMap', [], 'location'); ?>
                                <label>Urgency
                                    <select name="urgency" required>
                                        <option value="routine">Routine</option>
                                        <option value="urgent">Urgent</option>
                                        <option value="emergency">Emergency</option>
                                    </select>
                                </label>
                                <label>Needed by<input type="date" name="needed_by" min="<?= h(date('Y-m-d')) ?>" required></label>
                                <label><span class="label-heading-row"><span>Additional Details</span><span class="optional">Optional</span></span><textarea name="details" maxlength="1000" rows="3" placeholder="Add helpful context for potential donors."></textarea></label>
                                <button class="button button-primary" type="submit">Publish request</button>
                        </form>

                    </dialog>
                <?php elseif ($activePage === 'events'): ?>
                    <section class="panel">
                        <p class="form-help">Event details and dates are shared by members and are not independently verified by BLife PH. Contact the organizer or facility before attending.</p>
                        <?php if (!$posts): ?><div class="empty-state"><strong>No upcoming donation event announcements.</strong><span>Members can share an event from Home / Feed.</span><a class="button button-primary" href="dashboard.php?page=home">Go to community feed</a></div><?php else: ?>
                            <?php foreach ($posts as $post): ?><?php render_post_card($post, $commentsByPost[$post['id']] ?? [], $csrfToken, $userId, false, false, $donationStatusAvailable, $donationTimeAvailable, $philippineProvinces); ?><?php endforeach; ?>
                        <?php endif; ?>
                    </section>
                <?php elseif ($activePage === 'activity'): ?>
                    <section class="activity-grid">
                        <?php foreach ([
                            ['my-posts', 'My Post', (int) $statistics['posts'] + (int) $statistics['requests'] + (int) $statistics['offers'], 'Your community updates, blood requests, and donation offers.'],
                            ['responded', "My Responses", (int) $statistics['responses'], 'Requests where you offered to help.'],
                            ['saved', 'Saved Posts', (int) $statistics['saved'], 'Posts you saved for later.']
                        ] as $activity): ?>
                            <a class="panel activity-card" href="dashboard.php?page=<?= h($activity[0]) ?>"><span class="activity-number"><?= $activity[2] ?></span><strong><?= h($activity[1]) ?></strong><span><?= h($activity[3]) ?></span><span class="activity-link">View activity </span></a>
                        <?php endforeach; ?>
                    </section>
                <?php elseif ($activePage === 'my-posts' || $activePage === 'saved'): ?>
                    <?php if ($activePage === 'my-posts' && !$donationStatusAvailable): ?><div class="notice notice-info" role="status">Donation offer status management needs the database update in <code>database_sql/donation_offer_status_migration.sql</code>.</div><?php endif; ?>
                    <?php if ($activePage === 'my-posts'): ?>
                        <?php $communityPosts = array_filter($posts, static fn(array $post): bool => $post['post_type'] === 'community'); ?>
                        <div class="feed-column my-post-sections">
                            <nav class="my-post-category-nav" role="tablist" aria-label="My post categories">
                                <button id="my-community-tab" type="button" role="tab" aria-selected="true" aria-controls="my-community-posts" tabindex="0" data-my-post-tab="community">Community Post</button>
                                <button id="my-blood-tab" type="button" role="tab" aria-selected="false" aria-controls="my-blood-requests" tabindex="-1" data-my-post-tab="blood">Blood Request</button>
                                <button id="my-donation-tab" type="button" role="tab" aria-selected="false" aria-controls="my-donation-offers" tabindex="-1" data-my-post-tab="donation">Donation Offer</button>
                            </nav>
                            <section class="my-post-section" id="my-community-posts" role="tabpanel" aria-labelledby="my-community-tab" data-my-post-panel="community" tabindex="0">
                                <div class="section-heading"><h2>Community Post</h2></div>
                                <?php if (!$communityPosts): ?><div class="empty-state panel"><strong>No community updates yet.</strong><span>Updates you share will appear here.</span></div><?php else: ?>
                                    <div class="feed-column"><?php foreach ($communityPosts as $post): ?><?php render_post_card($post, $commentsByPost[$post['id']] ?? [], $csrfToken, $userId, false, false, $donationStatusAvailable, $donationTimeAvailable, $philippineProvinces); ?><?php endforeach; ?></div>
                                <?php endif; ?>
                            </section>
                            <section class="my-post-section" id="my-blood-requests" role="tabpanel" aria-labelledby="my-blood-tab" data-my-post-panel="blood" tabindex="0" hidden>
                                <div class="section-heading"><h2>Blood Request</h2></div>
                                <?php if (!$myRequests): ?><div class="empty-state panel"><strong>No blood requests yet.</strong><span>Requests you create will appear here.</span></div><?php else: ?>
                                    <div class="request-list"><?php foreach ($myRequests as $request): ?><?php render_request_card($request, $csrfToken, true, false, $philippineProvinces); ?><?php endforeach; ?></div>
                                <?php endif; ?>
                            </section>
                            <section class="my-post-section" id="my-donation-offers" role="tabpanel" aria-labelledby="my-donation-tab" data-my-post-panel="donation" tabindex="0" hidden>
                                <div class="section-heading"><h2>Donation Offer</h2></div>
                                <?php $donationPosts = array_filter($posts, static fn(array $post): bool => $post['post_type'] === 'donation'); ?>
                                <?php if (!$donationPosts): ?><div class="empty-state panel"><strong>No donation offers yet.</strong><span>Offers you publish will appear here.</span><a class="button button-primary" href="dashboard.php?page=donation-offers">Offer Donation</a></div><?php else: ?>
                                    <div class="feed-column"><?php foreach ($donationPosts as $post): ?><?php render_post_card($post, $commentsByPost[$post['id']] ?? [], $csrfToken, $userId, true, false, $donationStatusAvailable, $donationTimeAvailable, $philippineProvinces); ?><?php endforeach; ?></div>
                                <?php endif; ?>
                            </section>
                        </div>
                    <?php else: ?>
                        <section class="feed-column">
                            <?php if (!$posts): ?><div class="empty-state panel"><strong>No saved posts yet.</strong><span>Save posts from the community feed to find them here.</span></div><?php else: ?>
                                <?php foreach ($posts as $post): ?><?php render_post_card($post, $commentsByPost[$post['id']] ?? [], $csrfToken, $userId, false, false, $donationStatusAvailable, $donationTimeAvailable, $philippineProvinces); ?><?php endforeach; ?>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>
                <?php elseif ($activePage === 'my-requests' || $activePage === 'responded'): ?>
                    <?php $requestList = $activePage === 'my-requests' ? $myRequests : $respondedRequests; ?>
                    <?php if (!$requestList): ?><div class="empty-state panel"><strong><?= $activePage === 'my-requests' ? 'You have not posted a blood request.' : "You haven't responded to any requests yet." ?></strong><span>Browse requests to offer help, or post a request when you need one.</span><a class="button button-primary" href="dashboard.php?page=blood-requests">View Blood Requests</a></div><?php else: ?>
                        <section class="request-list">
                            <?php foreach ($requestList as $request): ?>
                                <?php if ($activePage === 'my-requests'): ?><?php render_request_card($request, $csrfToken, true, true, $philippineProvinces); ?>
                                <?php else: ?>
                                    <article class="request-card panel">
                                        <div class="request-heading"><span class="blood-mark"><?= h($request['blood_type']) ?></span><div><h3><?= (int) $request['units'] ?> unit<?= (int) $request['units'] === 1 ? '' : 's' ?> requested</h3><p>Requested by <?= h($request['requester_name'] ?: 'BLife PH member') ?></p></div><span class="status-badge status-<?= h($request['effective_status'] ?? $request['status']) ?>"><?= h(ucfirst($request['effective_status'] ?? $request['status'])) ?></span></div>
                                        <p><?= $request['hospital'] !== '' ? '<strong>' . h($request['hospital']) . '</strong> · ' : '' ?><?= h($request['location']) ?> · needed <?= h(date('M j, Y', strtotime($request['needed_by']))) ?></p>
                                        <div class="private-response"><strong>Your offer to help</strong><p><?= $request['response_message'] !== '' ? nl2br(h($request['response_message'])) : 'You offered to help without a message.' ?></p></div>
                                        <?php if ($request['approval_status'] === 'accepted'): ?>
                                            <a class="button button-secondary button-small" href="dashboard.php?page=messages&amp;thread=request-<?= (int) $request['response_id'] ?>">Open conversation in Messages</a>
                                        <?php else: ?>
                                            <p class="offer-pending-label">Your offer is waiting for the requester to accept it. Private messaging will open after acceptance.</p>
                                        <?php endif; ?>
                                    </article>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </section>
                    <?php endif; ?>
                <?php elseif ($activePage === 'notifications'): ?>
                    <section class="panel">
                        <div class="section-heading"><div><p class="eyebrow">ACCOUNT UPDATES</p><h2>Your notifications</h2></div>
                            <?php if ($unreadNotifications): ?><form method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="notifications_read_all"><button class="text-button" type="submit">Mark all as read</button></form><?php endif; ?>
                        </div>
                        <details class="notification-preferences"><summary><i class="fa-solid fa-sliders" aria-hidden="true"></i> Notification preferences</summary>
                            <form class="stacked-form" method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="settings">
                                <label class="checkbox-label"><input type="checkbox" name="notify_request_responses" value="1" <?= $profile['notify_request_responses'] ? 'checked' : '' ?>><span>Notify me about responses and messages related to my blood requests.</span></label>
                                <label class="checkbox-label"><input type="checkbox" name="notify_post_comments" value="1" <?= $profile['notify_post_comments'] ? 'checked' : '' ?>><span>Notify me about comments on my posts and messages about my donation offers.</span></label>
                                <button class="button button-secondary button-small" type="submit">Save notification preferences</button>
                            </form>
                        </details>
                        <?php if (!$notificationRows): ?><div class="empty-state"><strong>You're all caught up.</strong><span>Replies to your requests and comments on your posts will appear here.</span></div><?php else: ?>
                            <div class="notification-list"><?php render_notification_items($notificationRows, $csrfToken); ?></div>
                        <?php endif; ?>
                    </section>
                <?php elseif ($activePage === 'messages'): ?>
                    <?php if (!$messageInboxAvailable): ?>
                        <div class="notice notice-info" role="status">The private-message inbox needs the database update in <code>database_sql/messages_inbox_migration.sql</code>. Existing request and donation-offer conversations remain available from their current pages.</div>
                    <?php else: ?>
                        <div class="messages-layout">
                            <aside class="panel inbox-panel" aria-label="Conversations">
                                <div class="inbox-heading"><h2>Messages</h2><span><?= count($messageConversations) ?> conversation<?= count($messageConversations) === 1 ? '' : 's' ?></span></div>
                                <?php
                                $donorChatItems = array_values(array_filter($messageConversations, static fn(array $item): bool => ($item['category'] ?? 'donors') === 'donors'));
                                $requesterChatItems = array_values(array_filter($messageConversations, static fn(array $item): bool => ($item['category'] ?? 'donors') === 'requesters'));
                                $activeChatItems = $activeMessageCategory === 'requesters' ? $requesterChatItems : $donorChatItems;
                                ?>
                                <nav class="message-category-tabs" aria-label="Message categories">
                                    <a href="dashboard.php?page=messages&amp;category=donors" class="<?= $activeMessageCategory === 'donors' ? 'active' : '' ?>" <?= $activeMessageCategory === 'donors' ? 'aria-current="page"' : '' ?>>Donors Chat <span><?= count($donorChatItems) ?></span></a>
                                    <a href="dashboard.php?page=messages&amp;category=requesters" class="<?= $activeMessageCategory === 'requesters' ? 'active' : '' ?>" <?= $activeMessageCategory === 'requesters' ? 'aria-current="page"' : '' ?>>Requester Chat <span><?= count($requesterChatItems) ?></span></a>
                                </nav>
                                <?php if (!$activeChatItems): ?>
                                    <div class="empty-state inbox-empty"><strong>No <?= $activeMessageCategory === 'requesters' ? 'requester' : 'donor' ?> chats yet.</strong><span><?= $activeMessageCategory === 'requesters' ? 'Conversations with members who responded to your donation offers will appear here.' : 'Open a donation offer to message a donor, or review offers on your blood requests.' ?></span><?php if ($activeMessageCategory === 'donors'): ?><a class="button button-secondary button-small" href="dashboard.php?page=donation-offers">Browse donation offers</a><?php endif; ?></div>
                                <?php else: ?>
                                    <nav class="inbox-list" aria-label="Message threads">
                                        <?php foreach (['donors' => 'Donors Chat', 'requesters' => 'Requester Chat'] as $categoryKey => $categoryTitle):
                                            $categoryItems = array_values(array_filter($messageConversations, static fn(array $item): bool => ($item['category'] ?? 'donors') === $categoryKey));
                                            $pendingItems = array_values(array_filter($categoryItems, static fn(array $item): bool => ($item['conversation_status'] ?? 'accepted') === 'pending'));
                                            $activeItems = array_values(array_filter($categoryItems, static fn(array $item): bool => ($item['conversation_status'] ?? 'accepted') !== 'pending'));
                                            $categoryUnread = array_sum(array_column($categoryItems, 'unread_count'));
                                        ?>
                                            <?php if ($categoryItems && $categoryKey === $activeMessageCategory): ?><section class="inbox-category" aria-label="<?= h($categoryTitle) ?>">
                                                <?php if ($categoryUnread): ?><p class="inbox-category-unread"><?= $categoryUnread > 99 ? '99+' : $categoryUnread ?> unread</p><?php endif; ?>
                                                <?php foreach (['pending' => $pendingItems, 'active' => $activeItems] as $groupStatus => $group): if (!$group) continue; $statusTitle = $groupStatus === 'pending' ? (($categoryKey === 'requesters') ? 'Requests Sent' : 'Pending Offers') : 'Active Chats'; ?>
                                                    <h4 class="inbox-status-heading"><?= h($statusTitle) ?><span><?= count($group) ?></span></h4>
                                                    <?php foreach ($group as $conversation): $isPendingConversation = ($conversation['conversation_status'] ?? 'accepted') === 'pending'; ?>
                                                        <a class="inbox-conversation <?= $selectedThread === $conversation['thread_key'] ? 'selected' : '' ?>" href="dashboard.php?page=messages&amp;category=<?= h($categoryKey) ?>&amp;thread=<?= h($conversation['thread_key']) ?>" <?= $selectedThread === $conversation['thread_key'] ? 'aria-current="page"' : '' ?>>
                                                            <span class="avatar inbox-avatar"><?= h($conversation['peer_initials']) ?></span>
                                                            <span class="inbox-conversation-copy">
                                                                <span class="inbox-conversation-top"><strong><?= h($conversation['peer_name']) ?></strong><time datetime="<?= h($conversation['latest_at']) ?>"><?= h(date('M j', strtotime($conversation['latest_at']))) ?></time></span>
                                                                <small><?= h($conversation['context']) ?></small>
                                                                <span class="inbox-preview"><?= $isPendingConversation ? '<span class="inbox-sent-badge">' . (($conversation['viewer_is_request_owner'] ?? false) ? 'New offer' : 'Sent') . '</span> ' . h(($conversation['viewer_is_request_owner'] ?? false) ? 'Waiting for your response...' : 'Waiting for response...') : h($conversation['latest_message']) ?></span>
                                                            </span>
                                                            <?php if ($conversation['unread_count'] > 0): ?><span class="unread-indicator" aria-label="<?= (int) $conversation['unread_count'] ?> unread messages"><?= (int) $conversation['unread_count'] > 99 ? '99+' : (int) $conversation['unread_count'] ?></span><?php endif; ?><span class="inbox-chevron" aria-hidden="true">›</span>
                                                        </a>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                            </section><?php endif; ?>
                                        <?php endforeach; ?>
                                    </nav>
                                <?php endif; ?>
                            </aside>
                            <section class="panel message-thread-panel" aria-label="Private conversation">
                                <?php if ($selectedConversation !== null): ?>
                                    <header class="message-thread-heading">
                                        <span class="avatar avatar-large"><?= h($selectedConversation['peer_initials']) ?></span>
                                        <div><h2><?= h($selectedConversation['peer_name']) ?></h2><p><?= h($selectedConversation['context']) ?></p></div>
                                    </header>
                                    <?php if ($selectedConversation['kind'] === 'donation_offer'): ?>
                                        <div class="message-context-note">
                                            <strong>Donation offer urgency</strong>
                                            <span class="urgency-badge urgency-<?= h($selectedConversation['urgency'] ?: 'routine') ?>"><?= h(ucfirst($selectedConversation['urgency'] ?: 'Routine')) ?></span>
                                            <small>Self-reported by the member; not medically verified.</small>
                                        </div>
                                    <?php else: ?>
                                        <div class="message-context-note"><strong>Request urgency</strong><span class="urgency-badge urgency-<?= h($selectedConversation['request_urgency'] ?: 'routine') ?>"><?= h(ucfirst($selectedConversation['request_urgency'] ?: 'Routine')) ?></span><small>Self-reported; contact an authorized facility for medical guidance.</small></div>
                                    <?php endif; ?>
                                    <ol class="message-history">
                                        <?php foreach ($selectedThreadMessages as $message): $isOwnMessage = (int) $message['sender_id'] === $userId; ?>
                                            <li class="message-bubble <?= $isOwnMessage ? 'sent' : 'received' ?>">
                                                <div class="message-bubble-meta"><strong><?= $isOwnMessage ? 'You' : h(trim($message['fname'] . ' ' . $message['lname']) ?: $selectedConversation['peer_name']) ?></strong><time datetime="<?= h($message['created_at']) ?>"><?= h(date('M j, Y g:i a', strtotime($message['created_at']))) ?></time></div>
                                                <p><?= nl2br(h($message['message'])) ?></p>
                                                <?php if (!$isOwnMessage): ?>
                                                    <?php if ($message['reported_by_me']): ?><span class="message-reported">Reported for review</span><?php else: ?>
                                                        <details class="message-report"><summary>Report message</summary>
                                                            <form method="post" action="action.php">
                                                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                                <input type="hidden" name="action" value="message_report">
                                                                <input type="hidden" name="message_type" value="<?= h($selectedConversation['kind']) ?>">
                                                                <input type="hidden" name="message_id" value="<?= (int) $message['id'] ?>">
                                                                <input type="hidden" name="thread_key" value="<?= h($selectedThread) ?>">
                                                                <label class="visually-hidden" for="message-report-<?= (int) $message['id'] ?>">Reason for reporting this message</label>
                                                                <input id="message-report-<?= (int) $message['id'] ?>" type="text" name="reason" maxlength="255" required placeholder="Reason for report">
                                                                <button class="text-button" type="submit">Submit report</button>
                                                            </form>
                                                        </details>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ol>
                                    <?php if ($selectedConversation['kind'] === 'donation_offer'): ?>
                                        <?php render_offer_message_form((int) $selectedConversation['post_id'], (int) $selectedConversation['seeker_id'], $csrfToken, true, false, 'messages'); ?>
                                    <?php elseif (($selectedConversation['approval_status'] ?? 'accepted') === 'pending'): ?>
                                        <p class="conversation-closed">Offer sent. Private messaging becomes available after the requester accepts this response.</p>
                                    <?php elseif ($selectedConversation['request_status'] === 'open' && $selectedConversation['needed_by'] >= date('Y-m-d')): ?>
                                        <form class="conversation-reply-form inbox-reply-form" method="post" action="action.php">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="request_message"><input type="hidden" name="response_id" value="<?= (int) $selectedConversation['response_id'] ?>"><input type="hidden" name="return_page" value="messages">
                                            <label class="visually-hidden" for="requestMessageInput">Reply privately</label>
                                            <div class="message-composer-row">
                                                <input id="requestMessageInput" type="text" name="message" maxlength="1000" required placeholder="Write a message...">
                                                <button class="message-send-button" type="submit" aria-label="Send message"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.8 20.5 21.5 12 2.8 3.5 2.5 10l11 2-11 2 .3 6.5Z" /></svg></button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <p class="conversation-closed">This blood request is closed. The conversation remains available as history.</p>
                                    <?php endif; ?>
                                    <!-- <p class="conversation-privacy-note">Keep personal and medical details private. Donations and transfusions must be coordinated through an authorized facility.</p> -->
                                <?php elseif ($newMessageOffer): ?>
                                    <?php $newOfferDonor = trim($newMessageOffer['fname'] . ' ' . $newMessageOffer['lname']) ?: 'BLife PH member'; ?>
                                    <header class="message-thread-heading">
                                        <span class="avatar avatar-large"><?= h(member_initials($newOfferDonor)) ?></span>
                                        <div><h2><?= h($newOfferDonor) ?><?php if (!empty($newMessageOffer['donor_verified'])): ?> <span class="verified-donor">✓ Verified donor</span><?php endif; ?></h2><p>Donation offer · <?= h($newMessageOffer['donation_blood_type'] ?: 'Blood type not specified') ?> · <?= h($newMessageOffer['donation_location'] ?: 'Location not specified') ?></p></div>
                                    </header>
                                    <p class="message-start-copy"><?= nl2br(h($newMessageOffer['content'])) ?></p>
                                    <p class="form-help">Availability: <?php
                                        $newAvailability = [];
                                        if (!empty($newMessageOffer['donation_available_date'])) {
                                            $newAvailability[] = date('M j, Y', strtotime($newMessageOffer['donation_available_date']));
                                        }
                                        if (!empty($newMessageOffer['donation_available_time'])) {
                                            $newAvailability[] = date('g:i a', strtotime($newMessageOffer['donation_available_time']));
                                        }
                                        echo $newAvailability ? h(implode(' · ', $newAvailability)) : 'Not specified';
                                    ?></p>
                                    <!-- <div class="message-context-note"><strong>Urgency is self-reported</strong><small>Choose the level that best reflects your need. For emergencies, contact a hospital or emergency service immediately.</small></div> -->
                                    <?php render_offer_message_form((int) $newMessageOffer['id'], $userId, $csrfToken, false, true, 'messages'); ?>
                                    <!-- <p class="conversation-privacy-note">Your message is private to you and the donor. Do not share sensitive medical information.</p> -->
                                <?php else: ?>
                                    <div class="empty-state message-empty"><strong>Select a conversation</strong><span>Choose a message from your inbox or browse donation offers to contact a donor.</span><a class="button button-primary button-small" href="dashboard.php?page=donation-offers">Browse donation offers</a></div>
                                <?php endif; ?>
                            </section>
                        </div>
                    <?php endif; ?>
                <?php elseif ($activePage === 'profile'): ?>
                    <section class="panel profile-panel">
                        <p class="form-help">One member account can be used to request blood and offer to donate. Phone and email are never shown in the donor directory.</p>
                        <form class="stacked-form profile-form" method="post" action="action.php">
                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="profile">
                            <div class="form-row"><label>First name<input type="text" name="fname" maxlength="100" value="<?= h($profile['fname']) ?>" required></label><label>Last name<input type="text" name="lname" maxlength="100" value="<?= h($profile['lname']) ?>" required></label></div>
                            <label>Email<input type="email" value="<?= h($profile['email']) ?>" disabled></label>
                            <div class="form-row"><label>Phone number <span class="optional">(private)</span><input type="tel" name="phone" maxlength="20" value="<?= h($profile['phone']) ?>" required></label><label>Blood type<select name="btype" required><option value="">Select blood type</option><?php foreach ($bloodTypes as $type): ?><option value="<?= h($type) ?>" <?= $profile['btype'] === $type ? 'selected' : '' ?>><?= h($type) ?></option><?php endforeach; ?></select></label></div>
                            <div class="form-row profile-location-row" data-profile-location-form data-location-api="../api/philippine_locations.php" data-current-city="<?= h($profile['municipality'] ?? '') ?>" data-current-barangay="<?= h($profile['barangay'] ?? '') ?>">
                                <label>Province / Region<select name="province" data-profile-province required><option value="">Select province / region</option><?php foreach ($philippineProvinces as $province): ?><option value="<?= h($province) ?>" <?= $profile['city'] === $province ? 'selected' : '' ?>><?= h($province) ?></option><?php endforeach; ?><?php foreach (['Metro Manila (NCR)', 'Special Geographic Area', 'Independent Cities'] as $extraArea): ?><option value="<?= h($extraArea) ?>" <?= $profile['city'] === $extraArea ? 'selected' : '' ?>><?= h($extraArea) ?></option><?php endforeach; ?></select></label>
                                <label>City / Municipality<select name="municipality" data-profile-city required disabled><option value="">Select a province first</option></select></label>
                                <label>Barangay <span class="optional">(optional)</span><select name="barangay" data-profile-barangay disabled><option value="">Select a city / municipality first</option></select></label>
                                <p class="form-help profile-location-help" data-profile-location-status role="status">Choose your saved profile location. The map uses approximate public points and does not show your exact address.</p>
                            </div>
                            <button class="button button-primary" type="submit">Save Profile</button>
                        </form>
                    </section>
                <?php elseif ($activePage === 'settings'): ?>
                    <section class="panel settings-card" aria-label="Settings and privacy options">
                        <?php if (!$privacySecurityAvailable): ?><div class="notice notice-info" role="status">Account security and privacy controls need <code>database_sql/member_privacy_security_migration.sql</code> before they can be used.</div><?php endif; ?>
                        <section class="settings-section"><header><div><p class="settings-label">SECURITY</p><h2>Sign-in &amp; sessions</h2></div></header>
                            <div class="settings-action-row"><div><strong>Password</strong></div><button class="button button-secondary button-small" type="button" data-modal-open="changePasswordModal">Change</button></div>
                            <div class="settings-action-row"><div><strong>Email</strong><small><?= h($profile['email']) ?><?php if ($pendingEmailChange): ?> · Verification pending: <?= h($pendingEmailChange['new_email']) ?><?php endif; ?></small></div><button class="button button-secondary button-small" type="button" data-modal-open="changeEmailModal">Update</button></div>
                            <h3>Active sessions</h3>
                            <?php if (!$activeSessions): ?><p class="form-help">No active session records.</p><?php else: ?><ul class="session-list"><?php foreach ($activeSessions as $session): ?><li><div><strong><?= !empty($session['is_current']) ? 'This device' : 'Other device' ?></strong><small><?= h(substr((string)$session['user_agent'], 0, 180) ?: 'Browser session') ?> · <?= h(date('M j, Y g:i a', strtotime($session['last_seen_at']))) ?></small></div><?php if (empty($session['is_current'])): ?><form method="post" action="action.php" data-confirm-action="Sign out this session?"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="revoke_session"><input type="hidden" name="session_record_id" value="<?= (int)$session['id'] ?>"><button class="text-button" type="submit">Sign out</button></form><?php else: ?><span class="current-session-badge">Current</span><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?>
                        </section>
                        <section class="settings-section"><header><div><p class="settings-label">PRIVACY</p><h2>Profile visibility</h2></div></header>
                            <form class="settings-privacy-form" method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="privacy_settings">
                                <label class="settings-toggle"><span><strong>Appear in donor search</strong><small>Does not confirm donation availability.</small></span><input type="checkbox" name="donor_available" value="1" <?= !empty($profile['donor_available']) ? 'checked' : '' ?>></label>
                                <div class="settings-suboptions"><span class="form-help">Share these profile details</span><label class="checkbox-label"><input type="checkbox" name="show_first_name" value="1" <?= !empty($profile['show_first_name']) ? 'checked' : '' ?>><span>First name</span></label><label class="checkbox-label"><input type="checkbox" name="show_blood_type" value="1" <?= !empty($profile['show_blood_type']) ? 'checked' : '' ?>><span>Blood type</span></label><label class="checkbox-label"><input type="checkbox" name="show_province" value="1" <?= !empty($profile['show_province']) ? 'checked' : '' ?>><span>Province (approximate)</span></label></div>
                                <label class="settings-toggle"><span><strong>Location discovery</strong><small>Uses province-level location only.</small></span><input type="checkbox" name="location_discovery_enabled" value="1" <?= !empty($profile['location_discovery_enabled']) ? 'checked' : '' ?>></label>
                                <label class="checkbox-label"><input type="checkbox" name="optional_privacy_consent" value="1" <?= !empty($profile['optional_privacy_consent']) ? 'checked' : '' ?>><span>Optional privacy consent</span></label><button class="button button-primary button-small" type="submit">Save choices</button>
                            </form>
                        </section>
                        <section class="settings-section"><header><div><p class="settings-label">DATA</p><h2>Your information</h2></div></header>
                            <div class="settings-link-row"><div><strong>Account data</strong><small>Profile and activity export.</small></div><a class="button button-secondary button-small" href="data_export.php">Download</a></div>
                            <div class="settings-link-row"><div><strong>Privacy Policy</strong></div><a class="button button-secondary button-small" href="../privacy.php">View</a></div>
                            <p class="privacy-callout"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Existing requests and offers are unchanged.</p>
                        </section>
                        <section class="settings-section settings-danger"><header><div><p class="settings-label">ACCOUNT</p><h2>Account actions</h2></div></header>
                            <div class="settings-action-row"><div><strong>Deactivate account</strong><small>Sign out and hide your donor listing.</small></div><button class="button button-secondary button-small" type="button" data-modal-open="deactivateAccountModal">Deactivate</button></div>
                            <div class="settings-action-row"><div><strong>Delete account</strong><small>Requests are reviewed before records are removed.</small></div><?php if ($pendingDeletionRequest): ?><span class="current-session-badge">Pending</span><?php else: ?><button class="button button-danger-outline button-small" type="button" data-modal-open="deleteAccountModal">Request</button><?php endif; ?></div>
                        </section>
                    </section>
                    <dialog class="dashboard-modal settings-modal" id="changePasswordModal" aria-labelledby="changePasswordTitle"><div class="modal-heading"><div><h2 id="changePasswordTitle">Change password</h2><p>Verify your current password to continue.</p></div><button class="modal-close" type="button" data-modal-close aria-label="Close">×</button></div><form class="modal-form stacked-form" method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="change_password"><label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label><label>New password<input type="password" name="new_password" minlength="12" autocomplete="new-password" required><small>Use at least 12 characters.</small></label><label>Confirm new password<input type="password" name="confirm_password" minlength="12" autocomplete="new-password" required></label><div class="modal-actions"><button class="button button-secondary" type="button" data-modal-close>Cancel</button><button class="button button-primary" type="submit">Update password</button></div></form></dialog>
                    <dialog class="dashboard-modal settings-modal" id="changeEmailModal" aria-labelledby="changeEmailTitle"><div class="modal-heading"><div><h2 id="changeEmailTitle">Update email</h2><p>We will send a one-time verification link to the new address.</p></div><button class="modal-close" type="button" data-modal-close aria-label="Close">×</button></div><form class="modal-form stacked-form" method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="request_email_change"><label>New email address<input type="email" name="new_email" maxlength="255" autocomplete="email" required></label><label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label><div class="modal-actions"><button class="button button-secondary" type="button" data-modal-close>Cancel</button><button class="button button-primary" type="submit">Send verification</button></div></form></dialog>
                    <dialog class="dashboard-modal settings-modal" id="deactivateAccountModal" aria-labelledby="deactivateTitle"><div class="modal-heading"><div><h2 id="deactivateTitle">Deactivate account?</h2><p>Your donor listing will be hidden. Existing published requests and offers remain unchanged.</p></div><button class="modal-close" type="button" data-modal-close aria-label="Close">×</button></div><form class="modal-form stacked-form" method="post" action="action.php" data-confirm-action="Deactivate your account now?"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="deactivate_account"><input type="hidden" name="confirm_deactivation" value="1"><label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label><div class="modal-actions"><button class="button button-secondary" type="button" data-modal-close>Cancel</button><button class="button button-primary" type="submit">Deactivate account</button></div></form></dialog>
                    <dialog class="dashboard-modal settings-modal" id="deleteAccountModal" aria-labelledby="deleteTitle"><div class="modal-heading"><div><h2 id="deleteTitle">Request account deletion</h2><p>This submits a review request; it does not immediately erase conversations or related records.</p></div><button class="modal-close" type="button" data-modal-close aria-label="Close">×</button></div><form class="modal-form stacked-form" method="post" action="action.php" data-confirm-action="Send an account deletion request for review?"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="request_account_deletion"><input type="hidden" name="confirm_deletion" value="1"><label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label><div class="modal-actions"><button class="button button-secondary" type="button" data-modal-close>Cancel</button><button class="button button-danger" type="submit">Submit request</button></div></form></dialog>
                <?php elseif ($activePage === 'help'): ?>
                    <section class="help-grid">
                        <article class="panel"><h2>Using BLife PH safely</h2><p>BLife PH helps members find one another. It does not confirm medical eligibility, blood inventory, or a donor's ability to donate.</p><p>Screening, storage, transportation, and transfusion must be performed by an authorized hospital or blood bank.</p></article>
                        <article class="panel"><h2>Protect your information</h2><p>Do not post patient records, test results, private phone numbers, or home addresses. Contact facilities directly to confirm requirements and availability.</p><a href="dashboard.php?page=settings">Review privacy settings</a></article>
                        <article class="panel"><h2>Report a post</h2><p>Use the Report action on a community post to flag content for administrator review. Reports do not mean that a medical claim has been verified.</p><a href="dashboard.php?page=home">Return to the community feed</a></article>
                    </section>
                <?php elseif ($activePage === 'search'): ?>
                    <section class="panel search-results">
                        <?php if ($searchQuery === ''): ?><div class="empty-state"><strong>Search the community</strong><span>Enter a name, post topic, blood type, facility, or location in the search bar.</span></div><?php else: ?>
                            <p class="search-caption">Results for <strong><?= h($searchQuery) ?></strong></p>
                            <h2>Blood requests</h2>
                            <?php if (!$searchRequests): ?><p class="empty-inline">No matching open blood requests.</p><?php else: ?><?php foreach ($searchRequests as $request): ?><?php render_request_card($request, $csrfToken, (int) $request['requester_id'] === $userId, true, $philippineProvinces); ?><?php endforeach; ?><?php endif; ?>
                            <h2>Community posts and events</h2>
                            <?php if (!$searchPosts): ?><p class="empty-inline">No matching community posts or events.</p><?php else: ?><?php foreach ($searchPosts as $post): ?><?php render_post_card($post, $commentsByPost[$post['id']] ?? [], $csrfToken, $userId, false, false, $donationStatusAvailable, $donationTimeAvailable, $philippineProvinces); ?><?php endforeach; ?><?php endif; ?>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <!-- <p class="page-footnote">BLife PH connects people. Actual blood screening, inventory confirmation, and transfusion must be handled by authorized medical providers.</p> -->
            <?php endif; ?>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../validation/auth-notices.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script src="../validation/location-picker.js"></script>
    <script src="../validation/blood-donation-map.js"></script>
    <script src="../validation/user-dashboard.js"></script>
</body>
</html>
<?php

function render_request_card(array $request, string $csrfToken, bool $isOwn, bool $showResponses = true, array $philippineProvinces = []): void
{
    $requesterName = trim((string) ($request['requester_facility_name'] ?? ''));
    if ($requesterName === '') $requesterName = trim(($request['requester_name'] ?? '') . ' ' . ($request['requester_last_name'] ?? ''));
    if ($requesterName === '' && $isOwn) {
        $requesterName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
    }
    if ($requesterName === '') {
        $requesterName = 'BLife PH member';
    }
    ?>
    <article class="request-card panel donation-offer-card<?= $isOwn ? ' request-card-own' : '' ?>" id="request-<?= (int) $request['id'] ?>">
        <?php if ($isOwn): ?>
            <details class="content-management-menu request-management-menu">
                <summary aria-label="Manage this blood request">&hellip;</summary>
                <div class="content-management-panel">
                    <?php if (($request['effective_status'] ?? $request['status']) === 'open'): ?>
                        <button class="content-menu-action" type="button" data-modal-open="editRequest<?= (int) $request['id'] ?>">Edit request</button>
                        <form class="inline-actions" method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="request_status"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><label>Request status<select name="status"><option value="fulfilled">Fulfilled</option><option value="cancelled">Cancelled</option></select></label><button class="button button-secondary button-small" type="submit">Update status</button></form>
                    <?php endif; ?>
                    <form class="confirm-delete-form" method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="delete_request"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><button class="delete-post-button" type="submit">Delete request</button></form>
                </div>
            </details>
            <dialog class="dashboard-modal" id="editRequest<?= (int) $request['id'] ?>" aria-labelledby="editRequestTitle<?= (int) $request['id'] ?>">
                <div class="modal-heading"><div><p class="eyebrow">BLOOD REQUEST</p><h2 id="editRequestTitle<?= (int) $request['id'] ?>">Edit your request</h2></div><button class="modal-close" type="button" data-modal-close aria-label="Close edit request form">×</button></div>
                <form class="modal-form content-edit-form" method="post" action="action.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="edit_request"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                    <label>Blood type<select name="blood_type" required><?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $type): ?><option value="<?= h($type) ?>" <?= $request['blood_type'] === $type ? 'selected' : '' ?>><?= h($type) ?></option><?php endforeach; ?></select></label>
                    <label>Units<input type="number" name="units" min="1" max="20" required value="<?= (int) $request['units'] ?>"></label>
                    <label>Province
                    <?php blife_location_picker($philippineProvinces, 'request', 'requestEditMap' . (int) $request['id'], ['province' => $request['location'], 'city' => $request['city'] ?? '', 'barangay' => $request['barangay'] ?? '', 'latitude' => $request['latitude'] ?? '', 'longitude' => $request['longitude'] ?? ''], 'location'); ?>
                    <label>Needed by<input type="date" name="needed_by" min="<?= h(date('Y-m-d')) ?>" required value="<?= h($request['needed_by']) ?>"></label>
                    <label>Urgency<select name="urgency" required><?php foreach (['routine' => 'Routine', 'urgent' => 'Urgent', 'emergency' => 'Emergency'] as $urgencyValue => $urgencyLabel): ?><option value="<?= h($urgencyValue) ?>" <?= $request['urgency'] === $urgencyValue ? 'selected' : '' ?>><?= h($urgencyLabel) ?></option><?php endforeach; ?></select></label>
                    <label>Details<textarea name="details" maxlength="1000" rows="3"><?= h($request['details']) ?></textarea></label>
                    <button class="button button-primary" type="submit">Save request</button>
                </form>
            </dialog>
        <?php endif; ?>
        <div class="request-heading">
            <span class="blood-mark"><?= h($request['blood_type']) ?></span>
            <div class="post-author">
                <div class="post-author-heading">
                    <strong><?= h($requesterName) ?></strong><?php if (!empty($request['requester_facility_verified'])): ?><span class="verified-donor" title="Administrator verified facility">✓ Verified facility</span><?php endif; ?>
                    <?php if ($isOwn): ?><span class="post-author-you">You</span><?php endif; ?>
                </div>
                <span>Blood Request</span>
            </div>
            <div class="request-heading-badges">
                <span class="urgency-badge urgency-<?= h($request['urgency'] ?? 'routine') ?>"><?= h(ucfirst($request['urgency'] ?? 'routine')) ?></span>
                <?php if ($isOwn): ?><span class="status-badge status-<?= h($request['effective_status'] ?? $request['status']) ?>"><?= h(ucfirst($request['effective_status'] ?? $request['status'])) ?></span><?php endif; ?>
            </div>
        </div>
        <div class="donation-offer-details request-offer-details">
            <p><strong>Blood Type:</strong> <?= h($request['blood_type']) ?></p>
            <p><strong>Unit:</strong> <?= (int) $request['units'] ?></p>
            <p><strong>Location:</strong> <?= h(trim(($request['city'] ?? '') . ', ' . $request['location'], ', ')) ?></p>
            <?php if (!empty($request['hospital'])): ?><p><strong>Hospital:</strong> <?= h($request['hospital']) ?></p><?php endif; ?>
            <p><strong>Needed by:</strong> <?= h(date('M j, Y', strtotime($request['needed_by']))) ?></p>
        </div>
        <?php if ($request['details'] !== ''): ?><p class="request-details"><?= nl2br(h($request['details'])) ?></p><?php endif; ?>
        <p class="request-date"><?= (int) ($request['response_count'] ?? 0) ?> response<?= (int) ($request['response_count'] ?? 0) === 1 ? '' : 's' ?> received</p>
        <?php if ($isOwn): ?>
            <?php if ($showResponses && !empty($request['responses'])): ?>
                <section class="request-responses" aria-label="Offers from people responding to your request">
                    <h4>Offers from potential donors</h4>
                    <?php foreach ($request['responses'] as $response): ?>
                        <?php $responderName = trim(($response['fname'] ?? '') . ' ' . ($response['lname'] ?? '')); ?>
                        <article class="request-response">
                            <div class="request-response-heading">
                                <strong><?= h($responderName !== '' ? $responderName : 'BloodLife member') ?></strong>
                                <time datetime="<?= h($response['created_at']) ?>"><?= h(date('M j, Y g:i a', strtotime($response['created_at']))) ?></time>
                            </div>
                            <p><?= $response['message'] !== '' ? nl2br(h($response['message'])) : 'This member offered to help without a message.' ?></p>
                            <?php if ($response['approval_status'] === 'pending' && ($request['effective_status'] ?? $request['status']) === 'open' && $request['needed_by'] >= date('Y-m-d')): ?>
                                <form class="accept-offer-form" method="post" action="action.php">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                    <input type="hidden" name="action" value="accept_request_response">
                                    <input type="hidden" name="response_id" value="<?= (int) $response['response_id'] ?>">
                                    <button class="button button-primary button-small" type="submit">Accept offer</button>
                                </form>
                            <?php elseif ($response['approval_status'] === 'accepted'): ?>
                                <!-- <p class="offer-accepted-label">Offer accepted — private messaging is open.</p> -->
                                <a class="button button-secondary button-small" href="dashboard.php?page=messages&amp;thread=request-<?= (int) $response['response_id'] ?>">Open conversation in Messages</a>
                            <?php else: ?>
                                <p class="offer-pending-label">Waiting for your decision.</p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
        <?php elseif ($request['already_responded']): ?>
            <p class="responded-note"><?= ($request['viewer_response_status'] ?? '') === 'accepted' ? 'Your offer was accepted.' : 'Your offer is waiting for the requester to review it.' ?> <a href="dashboard.php?page=responded">View your response</a></p>
        <?php else: ?>
            <details class="request-offer">
                <summary class="button button-primary">Offer to help</summary>
                <form class="response-form" method="post" action="action.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="respond"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                    <label>Message to the requester <span class="optional">(optional)</span><textarea name="message" maxlength="500" rows="2">Hello! I'm interested in helping with your blood request. Please let me know how I can assist.</textarea></label>
                    <button class="button button-secondary" type="submit">Send offer</button>
                </form>
            </details>
        <?php endif; ?>
        <!-- <p class="form-help">A response is an offer to connect, not confirmed blood availability. Coordinate with the listed facility.</p> -->
    </article>
    <?php
}

function render_offer_message_form(int $postId, int $seekerId, string $csrfToken, bool $isReply, bool $isFirstMessage = false, string $returnPage = 'donation-offers'): void
{
    $formId = 'offer-message-' . $postId . '-' . $seekerId;
    ?>
    <form class="offer-message-form<?= $isFirstMessage ? ' offer-first-message-form' : '' ?>" method="post" action="action.php">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <input type="hidden" name="action" value="donation_offer_message">
        <input type="hidden" name="post_id" value="<?= $postId ?>">
        <input type="hidden" name="seeker_id" value="<?= $seekerId ?>">
        <input type="hidden" name="return_page" value="<?= h($returnPage) ?>">
        <?php if ($isFirstMessage): ?>
            <div class="offer-first-message-fields">
                <label class="offer-urgency-label" for="<?= h($formId) ?>-urgency">How urgent is the need?
                    <select id="<?= h($formId) ?>-urgency" name="urgency" required>
                        <option value="routine">Routine</option>
                        <option value="urgent">Urgent</option>
                        <option value="emergency">Emergency</option>
                    </select>
                </label>
                <!-- <p class="offer-conversation-help">Urgency is self-reported, not medically verified. For emergencies, contact a hospital or emergency service immediately.</p> -->
            </div>
        <?php endif; ?>
        <label class="visually-hidden" for="<?= h($formId) ?>"><?= $isReply ? 'Reply to this private conversation' : 'Message the donor privately' ?></label>
        <div class="message-composer-row">
            <input id="<?= h($formId) ?>" type="text" name="message" maxlength="1000" required placeholder="<?= $isReply ? 'Write a private reply...' : 'Ask the donor about this offer...' ?>">
            <button class="message-send-button" type="submit" aria-label="<?= $isReply ? 'Send reply' : 'Send message to donor' ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.8 20.5 21.5 12 2.8 3.5 2.5 10l11 2-11 2 .3 6.5Z" /></svg></button>
        </div>
    </form>
    <?php
}

function render_post_card(array $post, array $comments, string $csrfToken, int $userId, bool $donationOfferView = false, bool $offerMessagesAvailable = false, bool $donationStatusAvailable = false, bool $donationTimeAvailable = false, array $philippineProvinces = []): void
{
    $authorName = trim((string) ($post['author_facility_name'] ?? ''));
    if ($authorName === '') $authorName = trim(($post['author_name'] ?? '') . ' ' . ($post['author_last_name'] ?? ''));
    if ($authorName === '') {
        $authorName = 'BLife PH member';
    }
    $isOwnPost = (int) $post['user_id'] === $userId;
    $hasCommunityActions = ($post['post_type'] ?? '') === 'community';
    ?>
    <article class="post-card panel<?= $donationOfferView ? ' donation-offer-card' : '' ?>" id="post-<?= (int) $post['id'] ?>">
        <div class="post-meta">
            <span class="avatar"><?= h(member_initials($authorName)) ?></span>
            <div class="post-author">
                <div class="post-author-heading"><strong><?= h($authorName) ?></strong><?php if (!empty($post['author_facility_verified'])): ?><span class="verified-donor" title="Administrator verified facility">✓ Verified facility</span><?php elseif ($donationOfferView && !empty($post['donor_verified'])): ?><span class="verified-donor" title="Verified by a BLife PH administrator">✓ Verified donor</span><?php endif; ?><?php if ($isOwnPost): ?><span class="post-author-you">You</span><?php endif; ?></div>
                <span style="font-size: 15px;"><?= h(post_label($post['post_type'])) ?><?php if (!$donationOfferView && !empty($post['author_city'])): ?> · <?= h($post['author_city']) ?><?php endif; ?></span>
            </div>
            <time datetime="<?= h($post['created_at']) ?>"><?= h(date('M j, Y', strtotime($post['created_at']))) ?></time>
        </div>
        <?php if ($post['post_type'] === 'event' && !empty($post['event_title'])): ?><h3 class="event-card-title"><?= h($post['event_title']) ?></h3><?php endif; ?>
        <?php if ($post['post_type'] === 'event' && !empty($post['event_date'])): ?><p class="event-date-label">Event date: <?= h(date('M j, Y', strtotime($post['event_date']))) ?><?php if (!empty($post['event_time'])): ?> · <?= h(substr($post['event_time'], 0, 5)) ?><?php endif; ?></p><?php endif; ?>
        <?php if ($post['post_type'] === 'event' && (!empty($post['venue']) || !empty($post['event_city']))): ?><p class="event-date-label"><?= h(trim(($post['venue'] ?? '') . ' ' . ($post['event_city'] ?? ''))) ?></p><?php endif; ?>
        <?php if ($post['post_type'] === 'event' && !empty($post['organizer'])): ?><p class="event-date-label">Organizer: <?= h($post['organizer']) ?></p><?php endif; ?>
        <?php if ($post['post_type'] === 'event' && !empty($post['public_contact'])): ?><p class="event-date-label">Public contact: <?= h($post['public_contact']) ?></p><?php endif; ?>
        <?php if ($post['post_type'] === 'donation' && !empty($post['donation_blood_type'])): ?>
            <?php if ($isOwnPost && $donationStatusAvailable): $offerStatus = $post['donation_status'] ?? 'available'; ?><p><span class="status-badge status-<?= h($offerStatus) ?>"><?= h(ucfirst($offerStatus)) ?></span></p><?php endif; ?>
            <div class="donation-offer-details">
                <p class="donation-blood-row"><strong>Blood type:</strong><span class="blood-type-badge"><?= h($post['donation_blood_type']) ?></span></p>
                <p><strong>Location:</strong><span class="donation-location"> <?= h($post['donation_location'] ?: 'Not specified') ?></span></p>
                <p><strong>Availability:</strong><span>
                    <?php
                    $availabilityParts = [];
                    if (!empty($post['donation_available_date'])) {
                        $availabilityParts[] = date('M j, Y', strtotime($post['donation_available_date']));
                    }
                    if (!empty($post['donation_available_time'])) {
                        $availabilityParts[] = date('g:i a', strtotime($post['donation_available_time']));
                    }
                    echo $availabilityParts ? h(implode(' · ', $availabilityParts)) : 'Not specified';
                    ?>
                </span></p>
            </div>
        <?php endif; ?>
        <p class="post-content<?= $donationOfferView ? ' donation-offer-description' : '' ?>"><?= nl2br(h($post['content'])) ?></p>
        <!-- <?php if ($post['post_type'] === 'donation'): ?><p class="form-help offer-safety-note">Availability is not guaranteed. Avoid sharing private contact details; confirm screening and donation with an authorized facility.</p><?php endif; ?> -->
        <?php if ($donationOfferView && !$isOwnPost && $offerMessagesAvailable): ?>
            <a class="button button-primary message-donor-button" href="dashboard.php?page=messages&amp;offer=<?= (int) $post['id'] ?>">✉ Message donor</a>
        <?php endif; ?>
        <?php if ($donationOfferView && !$isOwnPost && $offerMessagesAvailable): ?>
            <?php $offerContactCount = (int) ($post['offer_contact_count'] ?? 0); ?>
            <p class="offer-contact-count"><?= $offerContactCount ?> member<?= $offerContactCount === 1 ? '' : 's' ?> contacted the offer owner</p>
        <?php endif; ?>
        <?php if ($hasCommunityActions): ?>
            <div class="post-counts"><span><?= (int) $post['like_count'] ?> likes</span><button type="button" data-disclosure-toggle="comments-<?= (int) $post['id'] ?>" aria-controls="comments-<?= (int) $post['id'] ?>" aria-expanded="false"><?= (int) $post['comment_count'] ?> comments</button></div>
        <?php endif; ?>
        <div class="post-actions<?= $donationOfferView ? ' donation-offer-actions' : '' ?>">
            <?php if ($hasCommunityActions): ?>
                <form method="post" action="action.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="post_interaction"><input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>"><input type="hidden" name="interaction" value="like"><input type="hidden" name="return_page" value="<?= $post['post_type'] === 'donation' ? 'donation-offers' : 'home' ?>">
                    <button type="submit" style="text-decoration:underline;" class="<?= $post['liked_by_me'] ? 'selected' : '' ?>"><?= $post['liked_by_me'] ? 'Liked' : ' Like' ?></button>
                </form>
                <button type="button" data-disclosure-toggle="comments-<?= (int) $post['id'] ?>" aria-controls="comments-<?= (int) $post['id'] ?>" aria-expanded="false" style="text-decoration:underline;">Comment</button>
                <!-- <button type="button" style="text-decoration:underline;" class="share-post-button" data-share-form="sharePost<?= (int) $post['id'] ?>" data-share-url="<?= h('dashboard.php?page=home&post=' . (int) $post['id']) ?>" data-share-title="<?= h($authorName . ' on BLifePH') ?>">Share</button> -->
                <form class="visually-hidden share-record-form" id="sharePost<?= (int) $post['id'] ?>" method="post" action="action.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="post_share"><input type="hidden" name="async" value="1"><input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>"><input type="hidden" name="return_page" value="<?= $post['post_type'] === 'donation' ? 'donation-offers' : 'home' ?>">
                </form>
                <form method="post" action="action.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="post_interaction"><input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>"><input type="hidden" name="interaction" value="save"><input type="hidden" name="return_page" value="<?= $post['post_type'] === 'donation' ? 'donation-offers' : 'home' ?>">
                    <button type="submit"  style="text-decoration:underline;" class="<?= $post['saved_by_me'] ? 'selected' : '' ?>"><?= $post['saved_by_me'] ? 'Saved' : 'Save' ?></button>
                </form>
            <?php endif; ?>
            <?php if ($hasCommunityActions && (int) $post['user_id'] !== $userId && !$post['reported_by_me']): ?>
                <button type="button" data-disclosure-toggle="report-form-<?= (int) $post['id'] ?>" aria-controls="report-form-<?= (int) $post['id'] ?>" aria-expanded="false" style="text-decoration:underline;">Report</button>
            <?php elseif ($hasCommunityActions && $post['reported_by_me']): ?><span class="reported-label">Reported</span><?php endif; ?>
            <?php if ($donationOfferView && !$isOwnPost && !$post['reported_by_me']): ?>
                <details class="offer-report">
                    <summary>Report offer</summary>
                    <form class="confirm-report-form" method="post" action="action.php">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="post_interaction"><input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>"><input type="hidden" name="interaction" value="report"><input type="hidden" name="return_page" value="donation-offers">
                        <label class="visually-hidden" for="report-reason-<?= (int) $post['id'] ?>">Reason for reporting this offer</label>
                        <input id="report-reason-<?= (int) $post['id'] ?>" name="report_reason" maxlength="255" required placeholder="Reason for report">
                        <button type="submit">Submit report</button>
                    </form>
                </details>
            <?php elseif ($donationOfferView && $post['reported_by_me']): ?><span class="reported-label">Reported</span><?php endif; ?>
            <?php if ($isOwnPost): ?>
                <details class="content-management-menu">
                    <summary aria-label="Manage your <?= h($post['post_type'] === 'donation' ? 'donation offer' : 'post') ?>">&hellip;</summary>
                    <div class="content-management-panel">
                        <button class="content-menu-action" type="button" data-modal-open="editPost<?= (int) $post['id'] ?>">Edit Post</button>
                        <?php if ($post['post_type'] === 'donation' && $donationStatusAvailable): ?>
                            <form class="content-status-form" method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="donation_status"><input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>"><label>Offer status<select name="status"><?php foreach (['available' => 'Available', 'unavailable' => 'Unavailable', 'completed' => 'Completed'] as $statusValue => $statusLabel): ?><option value="<?= h($statusValue) ?>" <?= ($post['donation_status'] ?? 'available') === $statusValue ? 'selected' : '' ?>><?= h($statusLabel) ?></option><?php endforeach; ?></select></label><button class="text-button" type="submit">Update status</button></form>
                        <?php endif; ?>
                        <form class="confirm-delete-form" method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="delete_post"><input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>"><button class="delete-post-button" type="submit">Delete</button></form>
                    </div>
                </details>
            <?php endif; ?>
        </div>
        <?php if ($isOwnPost): ?>
            <dialog class="dashboard-modal" id="editPost<?= (int) $post['id'] ?>" aria-labelledby="editPostTitle<?= (int) $post['id'] ?>">
                <div class="modal-heading"><div><p class="eyebrow"><?= $post['post_type'] === 'donation' ? 'DONATION OFFER' : 'COMMUNITY' ?></p><h2 id="editPostTitle<?= (int) $post['id'] ?>">Edit your <?= $post['post_type'] === 'donation' ? 'offer' : 'post' ?></h2></div><button class="modal-close" type="button" data-modal-close aria-label="Close edit form">×</button></div>
                <form class="modal-form content-edit-form" method="post" action="action.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="edit_post"><input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                    <label>Post content<textarea name="content" maxlength="1000" rows="4" required><?= h($post['content']) ?></textarea></label>
                    <?php if ($post['post_type'] === 'donation'): ?>
                        <label>Blood type<select name="donation_blood_type" required><?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $type): ?><option value="<?= h($type) ?>" <?= ($post['donation_blood_type'] ?? '') === $type ? 'selected' : '' ?>><?= h($type) ?></option><?php endforeach; ?></select></label>
                        <?php blife_location_picker($philippineProvinces, 'donation', 'donationEditMap' . (int) $post['id'], ['province' => $post['donation_province'] ?? (in_array($post['donation_location'] ?? '', $philippineProvinces, true) ? $post['donation_location'] : ''), 'city' => $post['donation_city'] ?? (in_array($post['donation_location'] ?? '', $philippineProvinces, true) ? '' : ($post['donation_location'] ?? '')), 'barangay' => $post['donation_barangay'] ?? '', 'latitude' => $post['donation_latitude'] ?? '', 'longitude' => $post['donation_longitude'] ?? '']); ?>
                        <label>Available date<input type="date" name="donation_available_date" value="<?= h($post['donation_available_date'] ?? '') ?>"></label>
                        <?php if ($donationTimeAvailable): ?><label>Available time<input type="time" name="donation_available_time" value="<?= h($post['donation_available_time'] ?? '') ?>"></label><?php endif; ?>
                    <?php elseif ($post['post_type'] === 'event'): ?>
                        <label>Event date<input type="date" name="event_date" required value="<?= h($post['event_date'] ?? '') ?>"></label>
                    <?php endif; ?>
                    <button class="button button-primary" type="submit">Save changes</button>
                </form>
            </dialog>
        <?php endif; ?>
        <?php if ($hasCommunityActions && (int) $post['user_id'] !== $userId && !$post['reported_by_me']): ?>
            <div class="post-report-disclosure" id="report-form-<?= (int) $post['id'] ?>" hidden>
                <form class="post-report-form confirm-report-form" method="post" action="action.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="post_interaction"><input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>"><input type="hidden" name="interaction" value="report">
                    <input type="hidden" name="return_page" value="<?= $post['post_type'] === 'donation' ? 'donation-offers' : 'home' ?>">
                    <label class="visually-hidden" for="report-reason-<?= (int) $post['id'] ?>">Reason for reporting this post</label>
                    <input id="report-reason-<?= (int) $post['id'] ?>" name="report_reason" maxlength="255" required placeholder="Reason for report">
                    <button type="submit">Submit report</button>
                </form>
            </div>
        <?php endif; ?>
        <?php if ($hasCommunityActions): ?>
            <div class="post-comments" id="comments-<?= (int) $post['id'] ?>" hidden>
                <?php foreach ($comments as $comment): ?>
                    <?php $commentAuthor = trim(($comment['author_name'] ?? '') . ' ' . ($comment['author_last_name'] ?? '')); ?>
                    <div class="comment"><span class="avatar avatar-small"><?= h(member_initials($commentAuthor)) ?></span><div><div class="comment-author-line"><strong><?= h($commentAuthor ?: 'BLife PH member') ?></strong><?php if ((int) ($comment['commenter_id'] ?? 0) === $userId): ?><span class="comment-author-you">You</span><?php endif; ?></div><p><?= nl2br(h($comment['comment'])) ?></p></div></div>
                <?php endforeach; ?>
                <form class="comment-form" method="post" action="action.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="comment"><input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                    <input type="hidden" name="return_page" value="<?= $post['post_type'] === 'donation' ? 'donation-offers' : 'home' ?>">
                    <label class="visually-hidden" for="comment-<?= (int) $post['id'] ?>">Add a comment</label>
                    <input id="comment-<?= (int) $post['id'] ?>" type="text" name="comment" maxlength="500" required placeholder="Write a comment..."><button class="button button-small button-secondary" type="submit">Comment</button>
                </form>
            </div>
        <?php endif; ?>
    </article>
    <?php
}

