<?php

session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login('../login.php');

if (is_admin()) {
    header('Location: ../admin/dashboard.php');
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
        $targetPage = in_array($notification['notification_type'], ['request_response', 'request_message'], true)
            ? 'my-requests'
            : ($notification['notification_type'] === 'donation_offer_message' ? 'donation-offers' : 'my-posts');
        ?>
        <form class="notification-item <?= !$notification['is_read'] ? 'unread' : '' ?>" method="post" action="action.php">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="notification_open"><input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>"><input type="hidden" name="target_page" value="<?= h($targetPage) ?>">
            <button type="submit"><span class="avatar avatar-small"><?= h(member_initials($notification['actor_name'] ?: 'BLife PH member')) ?></span><span><strong><?= h($notification['actor_name'] ?: 'A BLife PH member') ?> <?= h($notification['message']) ?></strong><small><?= h(date('M j, Y g:i a', strtotime($notification['created_at']))) ?></small></span><span class="notification-open">View</span></button>
        </form>
        <?php
    endforeach;
}

$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$pages = [
    'home', 'find-blood', 'blood-requests', 'donation-offers', 'events', 'activity',
    'my-posts', 'my-requests', 'responded', 'saved', 'notifications',
    'profile', 'settings', 'help', 'search'
];
$requestedPage = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : 'home';
$aliases = ['overview' => 'home', 'donors' => 'find-blood', 'requests' => 'blood-requests', 'community' => 'home', 'donations' => 'donation-offers'];
$requestedPage = $aliases[$requestedPage] ?? $requestedPage;
$activePage = in_array($requestedPage, $pages, true) ? $requestedPage : 'home';
$searchQuery = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$donorBloodType = isset($_GET['btype']) && is_string($_GET['btype']) ? trim($_GET['btype']) : '';
$donorCity = isset($_GET['city']) && is_string($_GET['city']) ? trim($_GET['city']) : '';
$offerBloodType = isset($_GET['offer_btype']) && is_string($_GET['offer_btype']) ? trim($_GET['offer_btype']) : '';
$offerCity = isset($_GET['offer_city']) && is_string($_GET['offer_city']) ? trim($_GET['offer_city']) : '';
$userId = (int) current_user_id();
$csrfToken = $_SESSION['csrf_token'];
$flash = $_SESSION['member_flash'] ?? null;
unset($_SESSION['member_flash']);
$loginSuccess = isset($_GET['login']) && is_string($_GET['login']) && $_GET['login'] === 'success';

$profile = [
    'fname' => $_SESSION['first_name'] ?? '',
    'lname' => $_SESSION['last_name'] ?? '',
    'email' => $_SESSION['email'] ?? '',
    'btype' => $_SESSION['blood_type'] ?? '',
    'phone' => $_SESSION['phone'] ?? '',
    'city' => '',
    'donor_available' => 0,
    'notify_request_responses' => 1,
    'notify_post_comments' => 1
];
$posts = $openRequests = $myRequests = $respondedRequests = $donors = $commentsByPost = [];
$notificationRows = $searchPosts = $searchRequests = $offerMessagesByPost = [];
$unreadNotifications = 0;
$statistics = ['posts' => 0, 'requests' => 0, 'responses' => 0, 'saved' => 0];
$schemaReady = false;
$schemaError = '';
$donationFieldsAvailable = false;
$offerMessagesAvailable = false;

try {
    $pdo = (new Database())->getConnection();
    $requiredTables = ['community_posts', 'blood_requests', 'request_responses', 'request_response_messages', 'post_comments', 'post_interactions', 'member_notifications'];
    $optionalTables = ['donation_offer_messages'];
    $tablesToCheck = array_merge($requiredTables, $optionalTables);
    $tableQuery = $pdo->prepare(
        'SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (' . implode(',', array_fill(0, count($tablesToCheck), '?')) . ')'
    );
    $tableQuery->execute($tablesToCheck);
    $availableTables = $tableQuery->fetchAll(PDO::FETCH_COLUMN);
    $offerMessagesAvailable = in_array('donation_offer_messages', $availableTables, true);
    $requiredColumns = [
        'uinfo' => ['fname', 'lname', 'email', 'btype', 'phone', 'city', 'donor_available', 'notify_request_responses', 'notify_post_comments'],
        'community_posts' => ['id', 'user_id', 'post_type', 'content', 'event_date', 'created_at'],
        'blood_requests' => ['id', 'requester_id', 'blood_type', 'units', 'hospital', 'location', 'needed_by', 'urgency', 'details', 'status', 'created_at'],
        'request_responses' => ['id', 'request_id', 'user_id', 'message', 'created_at'],
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
            $donationFieldsAvailable = isset(
                $columnInfo['donation_blood_type'],
                $columnInfo['donation_location'],
                $columnInfo['donation_available_date']
            );
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
        $profileStmt = $pdo->prepare('SELECT fname, lname, email, btype, phone, city, donor_available, notify_request_responses, notify_post_comments' . $accountStatusSelect . ' FROM uinfo WHERE id = :id LIMIT 1');
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

        $countStmt = $pdo->prepare(
            "SELECT
                (SELECT COUNT(*) FROM community_posts WHERE user_id = :posts_user) AS posts,
                (SELECT COUNT(*) FROM blood_requests WHERE requester_id = :requests_user) AS requests,
                (SELECT COUNT(*) FROM request_responses WHERE user_id = :responses_user) AS responses,
                (SELECT COUNT(*) FROM post_interactions WHERE user_id = :saved_user AND interaction = 'save') AS saved"
        );
        $countStmt->execute([
            ':posts_user' => $userId,
            ':requests_user' => $userId,
            ':responses_user' => $userId,
            ':saved_user' => $userId
        ]);
        $statistics = $countStmt->fetch();

        $unreadStmt = $pdo->prepare('SELECT COUNT(*) FROM member_notifications WHERE user_id = :user_id AND is_read = 0');
        $unreadStmt->execute([':user_id' => $userId]);
        $unreadNotifications = (int) $unreadStmt->fetchColumn();

        $donorConditions = ['donor_available = 1', 'id <> :member_id'];
        if ($accountStatusAvailable) {
            $donorConditions[] = "account_status = 'active'";
        }
        $donorParams = [':member_id' => $userId];
        if ($donorBloodType !== '' && in_array($donorBloodType, $bloodTypes, true)) {
            $donorConditions[] = 'btype = :blood_type';
            $donorParams[':blood_type'] = $donorBloodType;
        }
        if ($donorCity !== '') {
            $donorConditions[] = 'city LIKE :city';
            $donorParams[':city'] = '%' . $donorCity . '%';
        }
        $donorStmt = $pdo->prepare('SELECT fname, btype, city FROM uinfo WHERE ' . implode(' AND ', $donorConditions) . ' ORDER BY city, fname LIMIT 50');
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
            'SELECT rr.id AS response_id, rr.request_id, rr.user_id AS responder_id, rr.message, rr.created_at, responder.fname, responder.lname
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
            'SELECT br.*, rr.id AS response_id, rr.message AS response_message, rr.created_at AS responded_at, u.fname AS requester_name
             FROM request_responses rr
             JOIN blood_requests br ON br.id = rr.request_id
             JOIN uinfo u ON u.id = br.requester_id
             WHERE rr.user_id = :user_id
             ORDER BY rr.created_at DESC LIMIT 50'
        );
        $respondedStmt->execute([':user_id' => $userId]);
        $respondedRequests = $respondedStmt->fetchAll();

        $requestMessagesByResponse = [];
        $requestMessagesStmt = $pdo->prepare(
            'SELECT rrm.response_id, rrm.sender_id, rrm.message, rrm.created_at,
                    sender.fname, sender.lname
             FROM request_response_messages rrm
             JOIN request_responses rr ON rr.id = rrm.response_id
             JOIN blood_requests br ON br.id = rr.request_id
             JOIN uinfo sender ON sender.id = rrm.sender_id
             WHERE br.requester_id = :requester_id OR rr.user_id = :responder_id
             ORDER BY rrm.created_at ASC'
        );
        $requestMessagesStmt->execute([':requester_id' => $userId, ':responder_id' => $userId]);
        foreach ($requestMessagesStmt->fetchAll() as $message) {
            $requestMessagesByResponse[(int) $message['response_id']][] = $message;
        }
        foreach ($requestResponsesByRequest as &$responses) {
            foreach ($responses as &$response) {
                $response['thread_messages'] = $requestMessagesByResponse[(int) $response['response_id']] ?? [];
            }
            unset($response);
        }
        unset($responses);
        foreach ($respondedRequests as &$response) {
            $response['thread_messages'] = $requestMessagesByResponse[(int) $response['response_id']] ?? [];
        }
        unset($response);
        foreach ($myRequests as &$request) {
            $request['responses'] = $requestResponsesByRequest[(int) $request['id']] ?? [];
        }
        unset($request);

        $requestConditions = ["br.status = 'open'", 'br.needed_by >= CURDATE()'];
        $requestParams = [];
        if ($activePage === 'search' && $searchQuery !== '') {
            foreach (['hospital', 'location', 'type', 'details'] as $field) {
                $requestParams[':request_' . $field . '_search'] = '%' . $searchQuery . '%';
            }
            $requestConditions[] = '(br.hospital LIKE :request_hospital_search OR br.location LIKE :request_location_search OR br.blood_type LIKE :request_type_search OR br.details LIKE :request_details_search)';
        }
        $communityRequestStmt = $pdo->prepare(
            "SELECT br.*, CASE WHEN br.status = 'open' AND br.needed_by < CURDATE() THEN 'expired' ELSE br.status END AS effective_status,
                u.fname AS requester_name,
                EXISTS (SELECT 1 FROM request_responses rr WHERE rr.request_id = br.id AND rr.user_id = :responder_id) AS already_responded,
                (SELECT COUNT(*) FROM request_responses rr WHERE rr.request_id = br.id) AS response_count
             FROM blood_requests br JOIN uinfo u ON u.id = br.requester_id
             WHERE " . implode(' AND ', $requestConditions) . '
             ORDER BY FIELD(br.urgency, \'emergency\', \'urgent\', \'routine\'), br.needed_by ASC, br.created_at DESC'
        );
        $communityRequestStmt->execute(array_merge([':responder_id' => $userId], $requestParams));
        $openRequests = $communityRequestStmt->fetchAll();
        foreach ($openRequests as &$request) {
            if ((int) $request['requester_id'] === $userId) {
                $request['responses'] = $requestResponsesByRequest[(int) $request['id']] ?? [];
            }
        }
        unset($request);

        $postConditions = [];
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
        } elseif ($activePage === 'saved') {
            $postConditions[] = "EXISTS (SELECT 1 FROM post_interactions saved WHERE saved.post_id = cp.id AND saved.user_id = :saved_viewer AND saved.interaction = 'save')";
            $postParams[':saved_viewer'] = $userId;
        } elseif ($activePage === 'events') {
            $postConditions[] = "cp.post_type = 'event'";
            $postConditions[] = '(cp.event_date IS NULL OR cp.event_date >= CURDATE())';
        } elseif ($activePage === 'donation-offers') {
            $postConditions[] = "cp.post_type = 'donation'";
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
            $extraPostColumns[] = 'cp.venue';
            $extraPostColumns[] = 'cp.event_city';
        }
        if ($donationFieldsAvailable) {
            $extraPostColumns[] = 'cp.donation_blood_type';
            $extraPostColumns[] = 'cp.donation_location';
            $extraPostColumns[] = 'cp.donation_available_date';
        }
        $extraPostSelect = $extraPostColumns ? ', ' . implode(', ', $extraPostColumns) : '';
        $postStmt = $pdo->prepare(
            "SELECT cp.id, cp.user_id, cp.post_type, cp.content, cp.event_date, cp.created_at{$extraPostSelect},
                    u.fname AS author_name, u.lname AS author_last_name, u.city AS author_city,
                    (SELECT COUNT(*) FROM post_interactions pi WHERE pi.post_id = cp.id AND pi.interaction = 'like') AS like_count,
                    (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = cp.id) AS comment_count,
                    EXISTS (SELECT 1 FROM post_interactions mine WHERE mine.post_id = cp.id AND mine.user_id = :like_viewer AND mine.interaction = 'like') AS liked_by_me,
                    EXISTS (SELECT 1 FROM post_interactions mine WHERE mine.post_id = cp.id AND mine.user_id = :save_viewer AND mine.interaction = 'save') AS saved_by_me,
                    EXISTS (SELECT 1 FROM post_interactions mine WHERE mine.post_id = cp.id AND mine.user_id = :report_viewer AND mine.interaction = 'report') AS reported_by_me
             FROM community_posts cp JOIN uinfo u ON u.id = cp.user_id
             {$postWhere}
             ORDER BY {$postOrder} LIMIT 40"
        );
        $postStmt->execute($postParams);
        $posts = $postStmt->fetchAll();
        if ($activePage === 'search') {
            $searchPosts = $posts;
        }

        if ($activePage === 'donation-offers' && $offerMessagesAvailable && $posts) {
            $postIds = array_map(static function ($post) {
                return (int) $post['id'];
            }, $posts);
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));
            $offerMessageStmt = $pdo->prepare(
                "SELECT m.post_id, m.seeker_id, m.sender_id, m.message, m.urgency, m.created_at,
                        sender.fname AS sender_first_name, sender.lname AS sender_last_name,
                        seeker.fname AS seeker_first_name, seeker.lname AS seeker_last_name
                 FROM donation_offer_messages m
                 JOIN community_posts cp ON cp.id = m.post_id
                 JOIN uinfo sender ON sender.id = m.sender_id
                 JOIN uinfo seeker ON seeker.id = m.seeker_id
                 WHERE m.post_id IN ({$placeholders})
                   AND (m.seeker_id = ? OR cp.user_id = ?)
                 ORDER BY m.post_id, m.seeker_id, m.created_at, m.id"
            );
            $offerMessageStmt->execute(array_merge($postIds, [$userId, $userId]));
            foreach ($offerMessageStmt->fetchAll() as $offerMessage) {
                $postId = (int) $offerMessage['post_id'];
                $seekerId = (int) $offerMessage['seeker_id'];
                if (!isset($offerMessagesByPost[$postId][$seekerId])) {
                    $offerMessagesByPost[$postId][$seekerId] = [
                        'seeker_name' => trim((string) $offerMessage['seeker_first_name'] . ' ' . (string) $offerMessage['seeker_last_name']),
                        'urgency' => 'routine',
                        'latest_message' => '',
                        'latest_at' => '',
                        'messages' => []
                    ];
                }
                $offerMessage['sender_name'] = trim((string) $offerMessage['sender_first_name'] . ' ' . (string) $offerMessage['sender_last_name']);
                $thread = &$offerMessagesByPost[$postId][$seekerId];
                if ($offerMessage['urgency'] !== null && $offerMessage['urgency'] !== '') {
                    $thread['urgency'] = $offerMessage['urgency'];
                }
                $thread['latest_message'] = $offerMessage['message'];
                $thread['latest_at'] = $offerMessage['created_at'];
                $thread['messages'][] = $offerMessage;
                unset($thread);
            }
            foreach ($offerMessagesByPost as &$postThreads) {
                uasort($postThreads, static function (array $first, array $second): int {
                    $priority = ['emergency' => 3, 'urgent' => 2, 'routine' => 1];
                    $urgencyOrder = ($priority[$second['urgency']] ?? 0) <=> ($priority[$first['urgency']] ?? 0);
                    return $urgencyOrder !== 0 ? $urgencyOrder : strcmp($second['latest_at'], $first['latest_at']);
                });
            }
            unset($postThreads);
        }

        if ($posts && $activePage !== 'donation-offers') {
            $postIds = array_map(static function ($post) {
                return (int) $post['id'];
            }, $posts);
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));
            $commentStmt = $pdo->prepare(
                "SELECT pc.post_id, pc.comment, pc.created_at, u.fname AS author_name, u.lname AS author_last_name
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
            'SELECT n.id, n.notification_type, n.target_id, n.message, n.is_read, n.created_at,
                    u.fname AS actor_name
             FROM member_notifications n LEFT JOIN uinfo u ON u.id = n.actor_id
             WHERE n.user_id = :user_id ORDER BY n.created_at DESC LIMIT 100'
        );
        $notificationStmt->execute([':user_id' => $userId]);
        $notificationRows = $notificationStmt->fetchAll();
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
$navGroups = [
    'Main Menu' => [
        'home' => ['🏠︎', 'Home'],
        'blood-requests' => ['+', 'Blood Requests'],
        'donation-offers' => ['♥', 'Donation Offers'],
        'events' => ['◷', 'Donation Events']
    ],
    'My Activities' => [
        'activity' => ['▤', 'My Activity'],
        'my-posts' => ['▧', 'My Posts'],
        'my-requests' => ['♥', 'My Blood Requests'],
        'responded' => ['↗', "Requests I've Responded To"],
        'saved' => ['☆', 'Saved Posts']
    ],
    'Account' => [
        'profile' => ['👤', 'My Profile'],
        'settings' => ['⚙', 'Settings & Privacy'],
        'help' => ['?', 'Help & Support']
    ]
];
$pageTitles = [
    'home' => 'Community Feed',
    'find-blood' => 'Find Blood',
    'blood-requests' => 'Blood Requests',
    'donation-offers' => 'Donation Offers',
    'events' => 'Donation Events',
    'activity' => 'My Activity',
    'my-posts' => 'My Posts',
    'my-requests' => 'My Blood Requests',
    'responded' => "Requests I've Responded To",
    'saved' => 'Saved Posts',
    'notifications' => 'Notifications',
    'profile' => 'My Profile',
    'settings' => 'Settings & Privacy',
    'help' => 'Help & Support',
    'search' => 'Search Results'
];

function render_post_composer(string $csrfToken, string $initials, bool $donationOnly = false, bool $asModal = false): void
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
            <p class="form-help">Let members know what type and area you may be able to help with. Your private phone number is not shown.</p>
            <div class="form-row">
                <label>Blood type
                    <select name="donation_blood_type" <?= $donationOnly ? 'required' : '' ?>>
                        <option value="">Select blood type</option>
                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $type): ?>
                            <option value="<?= h($type) ?>"><?= h($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>City or province
                    <input type="text" name="donation_location" maxlength="120" placeholder="Where you can donate" <?= $donationOnly ? 'required' : '' ?>>
                </label>
            </div>
            <label>Available on <span class="optional">(optional)</span>
                <input type="date" name="donation_available_date" min="<?= h(date('Y-m-d')) ?>">
            </label>
        </div>
        <?php else: ?>
            <p class="form-help">Share questions, experiences, or updates related to blood donation and the BLife PH community. Keep posts respectful and protect private medical information.</p>
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
</head>
<body>
    <header class="topbar">
        <button class="icon-button sidebar-toggle" id="sidebarToggle" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="memberSidebar">
            <span aria-hidden="true">☰</span>
        </button>
        <a class="brand" href="dashboard.php?page=home" aria-label="BLife PH home">
            <img src="../images/BlifePH_no_bg.png" alt="">
            <span>BLife PH</span>
        </a>
        <form class="global-search" method="get" action="dashboard.php" role="search">
            <input type="hidden" name="page" value="search">
            <label class="visually-hidden" for="globalSearch">Search posts and blood requests</label>
            <span aria-hidden="true">⌕</span>
            <input id="globalSearch" type="search" name="q" value="<?= $activePage === 'search' ? h($searchQuery) : '' ?>" placeholder="Search posts, blood requests, events..." maxlength="120">
            <button type="submit" aria-label="Search">Search</button>
        </form>
        <div class="topbar-actions">
            <details class="notification-menu">
                <summary class="notification-link" aria-label="Notifications<?= $unreadNotifications ? ', ' . $unreadNotifications . ' unread' : '' ?>" aria-controls="notificationDropdown">
                    <span aria-hidden="true">🕭</span>
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
            <details class="profile-menu">
                <summary aria-label="Open profile menu">
                    <span class="avatar"><?= h($initials) ?></span>
                    <span class="profile-menu-name"><?= h($displayName) ?></span>
                    <span aria-hidden="true">⌄</span>
                </summary>
                <div class="profile-dropdown">
                    <a href="dashboard.php?page=profile">My Profile</a>
                    <a href="dashboard.php?page=settings">Settings &amp; Privacy</a>
                    <a href="../logout.php">Log Out</a>
                </div>
            </details>
        </div>
    </header>

    <div class="mobile-scrim" id="mobileScrim"></div>
    <div class="dashboard-layout">
        <aside class="sidebar" id="memberSidebar" aria-label="Dashboard sidebar">
            <nav class="sidebar-nav">
                <?php foreach ($navGroups as $groupName => $links): ?>
                    <div class="nav-group">
                        <p class="nav-group-title"><?= h($groupName) ?></p>
                        <?php foreach ($links as $page => $item): ?>
                            <a class="sidebar-link <?= $activePage === $page ? 'active' : '' ?>"
                               href="dashboard.php?page=<?= h($page) ?>"
                               aria-label="<?= h($item[1]) ?>"
                               title="<?= h($item[1]) ?>"
                               <?= $activePage === $page ? 'aria-current="page"' : '' ?>>
                               <span class="nav-icon" aria-hidden="true"><?= h($item[0]) ?></span>
                               <span class="nav-label"><?= h($item[1]) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-bottom">
                <!-- <p>One community. Many ways to help.</p> -->
                <?php if ($activePage === 'home'): ?>
                    <button class="sidebar-link sidebar-action" type="button" data-modal-open="createPostModal"><span class="nav-icon" aria-hidden="true">＋</span><span class="nav-label">Create Post</span></button>
                <?php endif; ?>
                <a class="sidebar-link logout-sidebar" href="../logout.php"><span class="nav-icon" aria-hidden="true">↪</span><span class="nav-label">Log Out</span></a>
            </div>
        </aside>

        <main class="dashboard-main">
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
                    <p>Run the migrations in <code>database_sql</code>, including <code>request_response_messages_migration.sql</code>, against the configured database. Existing data is retained.</p>
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
                                    <?php render_post_card($post, $commentsByPost[$post['id']] ?? [], $csrfToken, $userId); ?>
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
                <?php elseif ($activePage === 'donation-offers'): ?>

                    <?php if ($donationFieldsAvailable): ?>
                        <?php render_post_composer($csrfToken, $initials, true); ?>
                    <?php else: ?>
                        <div class="notice notice-error" role="alert">Donation offers need the database update in <code>database_sql/donation_offers_migration.sql</code>. Existing member accounts and blood requests remain unchanged.</div>
                    <?php endif; ?>
                    <?php if ($donationFieldsAvailable): ?>
                        <form class="filter-form donation-offer-filter" method="get" action="dashboard.php">
                            <input type="hidden" name="page" value="donation-offers">
                            <label>Blood type<select name="offer_btype"><option value="">All blood types</option><?php foreach ($bloodTypes as $type): ?><option value="<?= h($type) ?>" <?= $offerBloodType === $type ? 'selected' : '' ?>><?= h($type) ?></option><?php endforeach; ?></select></label>
                            <label>City or province<input type="search" name="offer_city" maxlength="120" value="<?= h($offerCity) ?>" placeholder="Search donation area"></label>
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
                                <?php render_post_card($post, $commentsByPost[$post['id']] ?? [], $csrfToken, $userId, true, $offerMessagesByPost[$post['id']] ?? [], $offerMessagesAvailable); ?>
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
                            <div class="section-heading"><div><p class="eyebrow"></p><h2>Open community requests</h2></div></div>
                            <p class="form-help"> <a href="dashboard.php?page=donation-offers"> Browse or publish donation offers</a>.</p>
                            <?php if (!$openRequests): ?><div class="empty-state panel"><strong>No open requests right now.</strong></div><?php else: ?>
                                <?php foreach ($openRequests as $request): ?><?php render_request_card($request, $csrfToken, (int) $request['requester_id'] === $userId); ?><?php endforeach; ?>
                            <?php endif; ?>
                        </section>
                        <aside class="right-rail">
                            <section class="panel safety-panel">
                                <p class="eyebrow">NEED BLOOD?</p>
                                <h2>Post a blood request now</h2>
                                <!-- <button class="button button-primary" type="button" data-modal-open="requestModal">＋ Request Blood</button> -->
                            </section>
                        </aside>
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
                                <label>Hospital or blood bank<input type="text" name="hospital" maxlength="160" required></label>
                                <label>City or province<input type="text" name="location" maxlength="160" required></label>
                                <label>Urgency
                                    <select name="urgency" required>
                                        <option value="routine">Routine</option>
                                        <option value="urgent">Urgent</option>
                                        <option value="emergency">Emergency</option>
                                    </select>
                                </label>
                                <label>Needed by<input type="date" name="needed_by" min="<?= h(date('Y-m-d')) ?>" required></label>
                                <label>Additional details <span class="optional">(optional)</span><textarea name="details" maxlength="1000" rows="3" placeholder="Avoid sharing sensitive medical information."></textarea></label>
                                <button class="button button-primary" type="submit">Publish request</button>
                        </form>
                    </dialog>
                <?php elseif ($activePage === 'events'): ?>
                    <section class="panel">
                        <p class="form-help">Event details and dates are shared by members and are not independently verified by BLife PH. Contact the organizer or facility before attending.</p>
                        <?php if (!$posts): ?><div class="empty-state"><strong>No upcoming donation event announcements.</strong><span>Members can share an event from Home / Feed.</span><a class="button button-primary" href="dashboard.php?page=home">Go to community feed</a></div><?php else: ?>
                            <?php foreach ($posts as $post): ?><?php render_post_card($post, $commentsByPost[$post['id']] ?? [], $csrfToken, $userId); ?><?php endforeach; ?>
                        <?php endif; ?>
                    </section>
                <?php elseif ($activePage === 'activity'): ?>
                    <section class="activity-grid">
                        <?php foreach ([
                            ['my-posts', 'My Posts', (int) $statistics['posts'], 'Community updates and event announcements you shared.'],
                            ['my-requests', 'My Blood Requests', (int) $statistics['requests'], 'Requests you created and their current status.'],
                            ['responded', "Requests I've Responded To", (int) $statistics['responses'], 'Requests where you offered to help.'],
                            ['saved', 'Saved Posts', (int) $statistics['saved'], 'Posts you saved for later.']
                        ] as $activity): ?>
                            <a class="panel activity-card" href="dashboard.php?page=<?= h($activity[0]) ?>"><span class="activity-number"><?= $activity[2] ?></span><strong><?= h($activity[1]) ?></strong><span><?= h($activity[3]) ?></span><span class="activity-link">View activity →</span></a>
                        <?php endforeach; ?>
                    </section>
                <?php elseif ($activePage === 'my-posts' || $activePage === 'saved'): ?>
                    <section class="feed-column">
                        <?php if (!$posts): ?><div class="empty-state panel"><strong><?= $activePage === 'saved' ? 'No saved posts yet.' : 'You have not shared any posts yet.' ?></strong><span><?= $activePage === 'saved' ? 'Save posts from the community feed to find them here.' : 'Share an update, event, or success story with the community.' ?></span><?php if ($activePage === 'my-posts'): ?><a class="button button-primary" href="dashboard.php?page=home#postComposer">Create a Post</a><?php endif; ?></div><?php else: ?>
                            <?php foreach ($posts as $post): ?><?php render_post_card($post, $commentsByPost[$post['id']] ?? [], $csrfToken, $userId); ?><?php endforeach; ?>
                        <?php endif; ?>
                    </section>
                <?php elseif ($activePage === 'my-requests' || $activePage === 'responded'): ?>
                    <?php $requestList = $activePage === 'my-requests' ? $myRequests : $respondedRequests; ?>
                    <?php if (!$requestList): ?><div class="empty-state panel"><strong><?= $activePage === 'my-requests' ? 'You have not posted a blood request.' : "You haven't responded to any requests yet." ?></strong><span>Browse requests to offer help, or post a request when you need one.</span><a class="button button-primary" href="dashboard.php?page=blood-requests">View Blood Requests</a></div><?php else: ?>
                        <section class="request-list">
                            <?php foreach ($requestList as $request): ?>
                                <?php if ($activePage === 'my-requests'): ?><?php render_request_card($request, $csrfToken, true); ?>
                                <?php else: ?>
                                    <article class="request-card panel">
                                        <div class="request-heading"><span class="blood-mark"><?= h($request['blood_type']) ?></span><div><h3><?= (int) $request['units'] ?> unit<?= (int) $request['units'] === 1 ? '' : 's' ?> requested</h3><p>Requested by <?= h($request['requester_name'] ?: 'BLife PH member') ?></p></div><span class="status-badge status-<?= h($request['effective_status'] ?? $request['status']) ?>"><?= h(ucfirst($request['effective_status'] ?? $request['status'])) ?></span></div>
                                        <p><strong><?= h($request['hospital']) ?></strong> · <?= h($request['location']) ?> · needed <?= h(date('M j, Y', strtotime($request['needed_by']))) ?></p>
                                        <div class="private-response"><strong>Your offer to help</strong><p><?= $request['response_message'] !== '' ? nl2br(h($request['response_message'])) : 'You offered to help without a message.' ?></p></div>
                                        <?php render_request_conversation((int) $request['response_id'], $request['thread_messages'] ?? [], $csrfToken, $userId, $request['status'] === 'open' && $request['needed_by'] >= date('Y-m-d')); ?>
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
                        <?php if (!$notificationRows): ?><div class="empty-state"><strong>You're all caught up.</strong><span>Replies to your requests and comments on your posts will appear here.</span></div><?php else: ?>
                            <div class="notification-list"><?php render_notification_items($notificationRows, $csrfToken); ?></div>
                        <?php endif; ?>
                    </section>
                <?php elseif ($activePage === 'profile'): ?>
                    <section class="panel profile-panel">
                        <p class="form-help">One member account can be used to request blood and offer to donate. Phone and email are never shown in the donor directory.</p>
                        <form class="stacked-form profile-form" method="post" action="action.php">
                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="profile">
                            <div class="form-row"><label>First name<input type="text" name="fname" maxlength="100" value="<?= h($profile['fname']) ?>" required></label><label>Last name<input type="text" name="lname" maxlength="100" value="<?= h($profile['lname']) ?>" required></label></div>
                            <label>Email<input type="email" value="<?= h($profile['email']) ?>" disabled></label>
                            <div class="form-row"><label>Phone number <span class="optional">(private)</span><input type="tel" name="phone" maxlength="20" value="<?= h($profile['phone']) ?>"></label><label>Blood type<select name="btype"><option value="">Not provided</option><?php foreach ($bloodTypes as $type): ?><option value="<?= h($type) ?>" <?= $profile['btype'] === $type ? 'selected' : '' ?>><?= h($type) ?></option><?php endforeach; ?></select></label></div>
                            <label>City or province<input type="text" name="city" maxlength="120" value="<?= h($profile['city']) ?>"></label>
                            <label class="checkbox-label"><input type="checkbox" name="donor_available" value="1" <?= $profile['donor_available'] ? 'checked' : '' ?>><span>Show my first name, blood type, and city to members searching for potential donors. This is not confirmation of blood availability.</span></label>
                            <button class="button button-primary" type="submit">Save Profile</button>
                        </form>
                    </section>
                <?php elseif ($activePage === 'settings'): ?>
                    <section class="panel profile-panel">
                        <p class="form-help">Choose which community updates can appear in your notifications. You can turn off donor-directory visibility from <a href="dashboard.php?page=profile">My Profile</a>.</p>
                        <form class="stacked-form profile-form" method="post" action="action.php">
                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="settings">
                            <label class="checkbox-label"><input type="checkbox" name="notify_request_responses" value="1" <?= $profile['notify_request_responses'] ? 'checked' : '' ?>><span>Notify me when another member responds to or messages me about a blood request.</span></label>
                            <label class="checkbox-label"><input type="checkbox" name="notify_post_comments" value="1" <?= $profile['notify_post_comments'] ? 'checked' : '' ?>><span>Notify me about activity on my posts and private messages about my donation offers.</span></label>
                            <button class="button button-primary" type="submit">Save Preferences</button>
                        </form>
                        <div class="privacy-callout"><strong>Your privacy matters</strong><p>Donor search only shows your first name, blood type, and city after you opt in. Phone, email, precise addresses, and medical records are not displayed to other members.</p></div>
                    </section>
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
                            <?php if (!$searchRequests): ?><p class="empty-inline">No matching open blood requests.</p><?php else: ?><?php foreach ($searchRequests as $request): ?><?php render_request_card($request, $csrfToken, (int) $request['requester_id'] === $userId); ?><?php endforeach; ?><?php endif; ?>
                            <h2>Community posts and events</h2>
                            <?php if (!$searchPosts): ?><p class="empty-inline">No matching community posts or events.</p><?php else: ?><?php foreach ($searchPosts as $post): ?><?php render_post_card($post, $commentsByPost[$post['id']] ?? [], $csrfToken, $userId); ?><?php endforeach; ?><?php endif; ?>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <p class="page-footnote">BLife PH connects people. Actual blood screening, inventory confirmation, and transfusion must be handled by authorized medical providers.</p>
            <?php endif; ?>
        </main>
    </div>
    <script src="../validation/user-dashboard.js"></script>
</body>
</html>
<?php

function render_request_card(array $request, string $csrfToken, bool $isOwn): void
{
    ?>
    <article class="request-card panel donation-offer-card">
        <div class="request-heading">
            <span class="blood-mark"><?= h($request['blood_type']) ?></span>
            <div><h3><?= $isOwn ? 'Your request' : 'Blood request' ?></h3><p><?= !$isOwn ? 'Posted by ' . h($request['requester_name'] ?: 'BLife PH member') : h($request['blood_type']) ?></p></div>
            <div class="request-heading-badges">
                <span class="urgency-badge urgency-<?= h($request['urgency'] ?? 'routine') ?>"><?= h(ucfirst($request['urgency'] ?? 'routine')) ?></span>
                <?php if ($isOwn): ?><span class="status-badge status-<?= h($request['effective_status'] ?? $request['status']) ?>"><?= h(ucfirst($request['effective_status'] ?? $request['status'])) ?></span><?php endif; ?>
            </div>
        </div>
        <div class="donation-offer-details request-offer-details">
            <p><strong>Unit:</strong> <?= (int) $request['units'] ?></p>
            <p><strong>Location:</strong> <?= h($request['location']) ?></p>
            <p><strong>Hospital:</strong> <?= h($request['hospital']) ?></p>
            <p><strong>Needed by:</strong> <?= h(date('M j, Y', strtotime($request['needed_by']))) ?></p>
        </div>
        <?php if ($request['details'] !== ''): ?><p class="request-details"><?= nl2br(h($request['details'])) ?></p><?php endif; ?>
        <?php if ($isOwn): ?>
            <p class="request-date"><?= (int) $request['response_count'] ?> response<?= (int) $request['response_count'] === 1 ? '' : 's' ?> received</p>
            <?php if (!empty($request['responses'])): ?>
                <section class="request-responses" aria-label="Messages from people responding to your request">
                    <h4>Messages from potential donors</h4>
                    <?php foreach ($request['responses'] as $response): ?>
                        <?php $responderName = trim(($response['fname'] ?? '') . ' ' . ($response['lname'] ?? '')); ?>
                        <article class="request-response">
                            <div class="request-response-heading">
                                <strong><?= h($responderName !== '' ? $responderName : 'BloodLife member') ?></strong>
                                <time datetime="<?= h($response['created_at']) ?>"><?= h(date('M j, Y g:i a', strtotime($response['created_at']))) ?></time>
                            </div>
                            <p><?= $response['message'] !== '' ? nl2br(h($response['message'])) : 'This member offered to help without a message.' ?></p>
                            <?php render_request_conversation((int) $response['response_id'], $response['thread_messages'] ?? [], $csrfToken, (int) current_user_id(), ($request['effective_status'] ?? $request['status']) === 'open' && $request['needed_by'] >= date('Y-m-d')); ?>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
            <?php if (($request['effective_status'] ?? $request['status']) === 'open'): ?>
                <form class="inline-actions confirm-cancel-form" method="post" action="action.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="request_status"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                    <button class="button button-secondary" type="submit" name="status" value="fulfilled">Mark fulfilled</button>
                    <button class="text-button cancel-request-button" type="submit" name="status" value="cancelled">Cancel request</button>
                </form>
            <?php endif; ?>
        <?php elseif ($request['already_responded']): ?>
            <p class="responded-note">You have responded to this request. <a href="dashboard.php?page=responded">View your response</a></p>
        <?php else: ?>
            <form class="response-form" method="post" action="action.php">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="respond"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                <label>Offer to help with a private message <span class="optional">(optional)</span><textarea name="message" maxlength="500" rows="2" placeholder="Let the requester know how you may be able to help."></textarea></label>
                <button class="button button-primary" type="submit">Send response</button>
            </form>
        <?php endif; ?>
        <!-- <p class="form-help">A response is an offer to connect, not confirmed blood availability. Coordinate with the listed facility.</p> -->
    </article>
    <?php
}

function render_request_conversation(int $responseId, array $messages, string $csrfToken, int $userId, bool $canReply): void
{
    ?>
    <section class="request-conversation" aria-label="Private conversation">
        <h5>Private conversation</h5>
        <?php if (!$messages): ?><p class="conversation-empty">No replies yet. You can continue the conversation here.</p><?php else: ?>
            <ol class="conversation-messages">
                <?php foreach ($messages as $message): ?>
                    <?php $senderName = trim(($message['fname'] ?? '') . ' ' . ($message['lname'] ?? '')); ?>
                    <li class="<?= (int) $message['sender_id'] === $userId ? 'sent' : 'received' ?>">
                        <div class="conversation-message-heading">
                            <strong><?= (int) $message['sender_id'] === $userId ? 'You' : h($senderName !== '' ? $senderName : 'BloodLife member') ?></strong>
                            <time datetime="<?= h($message['created_at']) ?>"><?= h(date('M j, Y g:i a', strtotime($message['created_at']))) ?></time>
                        </div>
                        <p><?= nl2br(h($message['message'])) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
        <?php if ($canReply): ?>
            <form class="conversation-reply-form" method="post" action="action.php">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="action" value="request_message">
                <input type="hidden" name="response_id" value="<?= $responseId ?>">
                <label>Reply privately<textarea name="message" maxlength="1000" rows="2" required placeholder="Write a message to continue this conversation..."></textarea></label>
                <button class="button button-primary button-small" type="submit">Send message</button>
            </form>
        <?php else: ?>
            <p class="conversation-closed">This request is closed. The conversation can no longer receive replies.</p>
        <?php endif; ?>
        <p class="conversation-privacy-note">Your contact details stay private. Coordinate any donation through an authorized medical facility.</p>
    </section>
    <?php
}

function render_offer_message_form(int $postId, int $seekerId, string $csrfToken, bool $isReply, bool $isFirstMessage = false): void
{
    $formId = 'offer-message-' . $postId . '-' . $seekerId;
    ?>
    <form class="offer-message-form<?= $isFirstMessage ? ' offer-first-message-form' : '' ?>" method="post" action="action.php">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <input type="hidden" name="action" value="donation_offer_message">
        <input type="hidden" name="post_id" value="<?= $postId ?>">
        <input type="hidden" name="seeker_id" value="<?= $seekerId ?>">
        <?php if ($isFirstMessage): ?>
            <label class="offer-urgency-label" for="<?= h($formId) ?>-urgency">How urgent is the need?
                <select id="<?= h($formId) ?>-urgency" name="urgency" required style="width: 100px;">
                    <option value="routine">Routine</option>
                    <option value="urgent">Urgent</option>
                    <option value="emergency">Emergency</option>
                </select>
            </label>
            <p class="offer-conversation-help">Urgency is self-reported and helps donors prioritize messages. For emergencies, contact a hospital or emergency service immediately.</p>
        <?php endif; ?>
        <label class="visually-hidden" for="<?= h($formId) ?>"><?= $isReply ? 'Reply to this private conversation' : 'Message the donor privately' ?></label>
        <textarea id="<?= h($formId) ?>" name="message" maxlength="1000" rows="2" required placeholder="<?= $isReply ? 'Write a private reply...' : 'Ask the donor about this offer...' ?>"></textarea>
        <button class="button button-primary button-small" type="submit"><?= $isReply ? 'Send reply' : 'Message donor' ?></button>
    </form>
    <?php
}

function render_offer_message_thread(array $thread, int $postId, int $seekerId, string $csrfToken, int $userId, bool $isOfferOwner): void
{
    ?>
    <details class="offer-message-thread" <?= $isOfferOwner ? '' : 'open' ?>>
        <?php if ($isOfferOwner): ?>
            <summary class="offer-thread-summary">
                <span class="offer-thread-title">
                    <strong><?= h($thread['seeker_name'] ?: 'Interested member') ?></strong>
                    <span class="urgency-badge urgency-<?= h($thread['urgency']) ?>"><?= h(ucfirst($thread['urgency'])) ?></span>
                    <span class="offer-thread-count"><?= count($thread['messages']) ?> message<?= count($thread['messages']) === 1 ? '' : 's' ?></span>
                </span>
                <span class="offer-thread-preview"><?= h($thread['latest_message']) ?></span>
                <time datetime="<?= h($thread['latest_at']) ?>"><?= h(date('M j, Y g:i a', strtotime($thread['latest_at']))) ?></time>
            </summary>
        <?php else: ?>
            <h4>Your conversation with the donor</h4>
        <?php endif; ?>
        <ol class="offer-message-list">
            <?php foreach ($thread['messages'] as $message): ?>
                <?php $isOwnMessage = (int) $message['sender_id'] === $userId; ?>
                <li class="<?= $isOwnMessage ? 'sent' : 'received' ?>">
                    <div class="offer-message-meta">
                        <strong><?= $isOwnMessage ? 'You' : h($message['sender_name'] ?: 'BloodLife member') ?></strong>
                        <time datetime="<?= h($message['created_at']) ?>"><?= h(date('M j, Y g:i a', strtotime($message['created_at']))) ?></time>
                    </div>
                    <p><?= nl2br(h($message['message'])) ?></p>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php render_offer_message_form($postId, $seekerId, $csrfToken, true); ?>
    </details>
    <?php
}

function render_donation_offer_conversation(array $post, array $threads, string $csrfToken, int $userId): void
{
    $postId = (int) $post['id'];
    $isOfferOwner = (int) $post['user_id'] === $userId;
    ?>
    <section class="donation-offer-conversation" aria-label="Private messages about this donation offer">
        <?php if ($isOfferOwner): ?>
            <!-- <h3>Interested members</h3>
            <p class="offer-conversation-help">Conversations are ordered by the urgency selected by each member. Urgency is self-reported; review each request and reply individually.</p> -->
            <?php if (!$threads): ?>
                <p class="offer-conversation-empty">No one has contacted you about this offer yet.</p>
            <?php else: ?>
                <?php foreach ($threads as $seekerId => $thread): ?>
                    <?php render_offer_message_thread($thread, $postId, (int) $seekerId, $csrfToken, $userId, true); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php else: ?>
            <h3>Contact the donor privately</h3>
            <p class="offer-conversation-help">Your messages are visible only to you and the donor. Do not share sensitive medical information.</p>
            <?php if (isset($threads[$userId])): ?>
                <?php render_offer_message_thread($threads[$userId], $postId, $userId, $csrfToken, $userId, false); ?>
            <?php else: ?>
                <?php render_offer_message_form($postId, $userId, $csrfToken, false, true); ?>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <?php
}

function render_post_card(array $post, array $comments, string $csrfToken, int $userId, bool $donationOfferView = false, array $offerThreads = [], bool $offerMessagesAvailable = false): void
{
    $authorName = trim(($post['author_name'] ?? '') . ' ' . ($post['author_last_name'] ?? ''));
    if ($authorName === '') {
        $authorName = 'BloodLife member';
    }
    $isOwnPost = (int) $post['user_id'] === $userId;
    ?>
    <article class="post-card panel<?= $donationOfferView ? ' donation-offer-card' : '' ?>" id="post-<?= (int) $post['id'] ?>">
        <div class="post-meta">
            <span class="avatar"><?= h(member_initials($authorName)) ?></span>
            <div class="post-author">
                <div class="post-author-heading"><strong><?= h($authorName) ?></strong><?php if ($isOwnPost): ?><span class="post-author-you">You</span><?php endif; ?></div>
                <span><?= h(post_label($post['post_type'])) ?><?php if (!$donationOfferView && !empty($post['author_city'])): ?> · <?= h($post['author_city']) ?><?php endif; ?></span>
            </div>
            <time datetime="<?= h($post['created_at']) ?>"><?= h(date('M j, Y', strtotime($post['created_at']))) ?></time>
        </div>
        <?php if ($post['post_type'] === 'event' && !empty($post['event_title'])): ?><h3 class="event-card-title"><?= h($post['event_title']) ?></h3><?php endif; ?>
        <?php if ($post['post_type'] === 'event' && !empty($post['event_date'])): ?><p class="event-date-label">Event date: <?= h(date('M j, Y', strtotime($post['event_date']))) ?><?php if (!empty($post['event_time'])): ?> · <?= h(substr($post['event_time'], 0, 5)) ?><?php endif; ?></p><?php endif; ?>
        <?php if ($post['post_type'] === 'event' && (!empty($post['venue']) || !empty($post['event_city']))): ?><p class="event-date-label"><?= h(trim(($post['venue'] ?? '') . ' ' . ($post['event_city'] ?? ''))) ?></p><?php endif; ?>
        <?php if ($post['post_type'] === 'donation' && !empty($post['donation_blood_type'])): ?>
            <div class="donation-offer-details">
                <p><strong>Blood Type:</strong> <?= h($post['donation_blood_type']) ?></p>
                <p><strong>Area:</strong> <?= h($post['donation_location'] ?: 'Not specified') ?></p>
                <p><strong>Availability:</strong> <?= !empty($post['donation_available_date']) ? h(date('M j, Y', strtotime($post['donation_available_date']))) : 'Not specified' ?></p>
            </div>
        <?php endif; ?>
        <p class="post-content<?= $donationOfferView ? ' donation-offer-description' : '' ?>"><?= nl2br(h($post['content'])) ?></p>
        <!-- <?php if ($post['post_type'] === 'donation'): ?><p class="form-help offer-safety-note">Availability is not guaranteed. Avoid sharing private contact details; confirm screening and donation with an authorized facility.</p><?php endif; ?> -->
        <?php if ($donationOfferView && $offerMessagesAvailable): ?>
            <?php render_donation_offer_conversation($post, $offerThreads, $csrfToken, $userId); ?>
        <?php endif; ?>
        <?php if (!$donationOfferView): ?>
            <div class="post-counts"><span><?= (int) $post['like_count'] ?> likes</span><button type="button" data-disclosure-toggle="comments-<?= (int) $post['id'] ?>" aria-controls="comments-<?= (int) $post['id'] ?>" aria-expanded="false"><?= (int) $post['comment_count'] ?> comments</button></div>
        <?php endif; ?>
        <div class="post-actions<?= $donationOfferView ? ' donation-offer-actions' : '' ?>">
            <?php if (!$donationOfferView): ?>
                <form method="post" action="action.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="post_interaction"><input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>"><input type="hidden" name="interaction" value="like"><input type="hidden" name="return_page" value="<?= $post['post_type'] === 'donation' ? 'donation-offers' : 'home' ?>">
                    <button type="submit" class="<?= $post['liked_by_me'] ? 'selected' : '' ?>"><?= $post['liked_by_me'] ? '♥ Liked' : '♡ Like' ?></button>
                </form>
                <button type="button" data-disclosure-toggle="comments-<?= (int) $post['id'] ?>" aria-controls="comments-<?= (int) $post['id'] ?>" aria-expanded="false">▤ Comment</button>
                <button type="button" class="share-post-button" data-share-form="sharePost<?= (int) $post['id'] ?>" data-share-url="<?= h('dashboard.php?page=home&post=' . (int) $post['id']) ?>" data-share-title="<?= h($authorName . ' on BLifePH') ?>">↗ Share</button>
                <form class="visually-hidden share-record-form" id="sharePost<?= (int) $post['id'] ?>" method="post" action="action.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="post_share"><input type="hidden" name="async" value="1"><input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>"><input type="hidden" name="return_page" value="<?= $post['post_type'] === 'donation' ? 'donation-offers' : 'home' ?>">
                </form>
                <form method="post" action="action.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="post_interaction"><input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>"><input type="hidden" name="interaction" value="save"><input type="hidden" name="return_page" value="<?= $post['post_type'] === 'donation' ? 'donation-offers' : 'home' ?>">
                    <button type="submit" class="<?= $post['saved_by_me'] ? 'selected' : '' ?>"><?= $post['saved_by_me'] ? '★ Saved' : '☆ Save' ?></button>
                </form>
            <?php endif; ?>
            <?php if (!$donationOfferView && (int) $post['user_id'] !== $userId && !$post['reported_by_me']): ?>
                <button type="button" data-disclosure-toggle="report-form-<?= (int) $post['id'] ?>" aria-controls="report-form-<?= (int) $post['id'] ?>" aria-expanded="false">⚑ Report</button>
            <?php elseif (!$donationOfferView && $post['reported_by_me']): ?><span class="reported-label">Reported</span><?php endif; ?>
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
                <form class="confirm-delete-form" method="post" action="action.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="delete_post"><input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                    <button class="delete-post-button" type="submit">Delete</button>
                </form>
            <?php endif; ?>
        </div>
        <?php if (!$donationOfferView && (int) $post['user_id'] !== $userId && !$post['reported_by_me']): ?>
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
        <?php if (!$donationOfferView): ?>
            <div class="post-comments" id="comments-<?= (int) $post['id'] ?>" hidden>
                <?php foreach ($comments as $comment): ?>
                    <?php $commentAuthor = trim(($comment['author_name'] ?? '') . ' ' . ($comment['author_last_name'] ?? '')); ?>
                    <div class="comment"><span class="avatar avatar-small"><?= h(member_initials($commentAuthor)) ?></span><div><strong><?= h($commentAuthor ?: 'BLife PH member') ?></strong><p><?= nl2br(h($comment['comment'])) ?></p></div></div>
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
