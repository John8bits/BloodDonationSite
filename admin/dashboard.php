<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_admin('../login.php', '../dashboard/dashboard.php');

$firstName = (string) ($_SESSION['first_name'] ?? '');
$lastName = (string) ($_SESSION['last_name'] ?? '');

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function admin_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function admin_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
    );
    $stmt->execute([':table' => $table]);
    return (int) $stmt->fetchColumn() > 0;
}

function admin_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
    );
    $stmt->execute([':table' => $table, ':column' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

$pdo = (new Database())->getConnection();
$csrfToken = $_SESSION['csrf_token'];
$currentPage = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : 'overview';
$sections = [
    'overview' => 'Dashboard Overview',
    'members' => 'Member Management',
    'clinics' => 'Clinics & Blood Banks',
    'requests' => 'Blood Request Management',
    'posts' => 'Community Posts',
    'reports' => 'Reported Content',
    'events' => 'Donation Event Management',
    'analytics' => 'Reports & Analytics',
    'activity' => 'Admin Activity Log',
    'settings' => 'Settings'
];
$sidebarGroups = [
    'Overview' => ['overview' => 'Dashboard'],
    'User Management' => ['members' => 'Members', 'clinics' => 'Clinics & Blood Banks'],
    'Blood Services' => ['requests' => 'Blood Requests', 'events' => 'Donation Events'],
    'Content Management' => ['posts' => 'Community Posts', 'reports' => 'Reported Content'],
    'Reports & Monitoring' => ['analytics' => 'Analytics', 'activity' => 'Activity Logs']
];
if (!isset($sections[$currentPage])) {
    $currentPage = 'overview';
}

$schema = [
    'account_status' => admin_column_exists($pdo, 'uinfo', 'account_status'),
    'donor_available' => admin_column_exists($pdo, 'uinfo', 'donor_available'),
    'city' => admin_column_exists($pdo, 'uinfo', 'city'),
    'requests' => admin_table_exists($pdo, 'blood_requests'),
    'responses' => admin_table_exists($pdo, 'request_responses'),
    'posts' => admin_table_exists($pdo, 'community_posts'),
    'interactions' => admin_table_exists($pdo, 'post_interactions'),
    'comments' => admin_table_exists($pdo, 'post_comments'),
    'activity' => admin_table_exists($pdo, 'admin_activity_log'),
    'settings' => admin_table_exists($pdo, 'platform_settings'),
    'report_reason' => admin_column_exists($pdo, 'post_interactions', 'report_reason'),
    'message_reports' => admin_table_exists($pdo, 'member_message_reports'),
    'event_fields' => admin_column_exists($pdo, 'community_posts', 'event_title'),
    'clinics' => admin_table_exists($pdo, 'clinic_profiles')
];
$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);
$clinicEditFailure = $_SESSION['clinic_edit_failure'] ?? null;
unset($_SESSION['clinic_edit_failure']);
$query = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$bloodType = isset($_GET['btype']) && is_string($_GET['btype']) ? $_GET['btype'] : '';
$status = isset($_GET['status']) && is_string($_GET['status']) ? $_GET['status'] : '';
$urgency = isset($_GET['urgency']) && is_string($_GET['urgency']) ? $_GET['urgency'] : '';
$neededDate = isset($_GET['needed_by']) && is_string($_GET['needed_by']) ? $_GET['needed_by'] : '';
$cityFilter = isset($_GET['city']) && is_string($_GET['city']) ? trim($_GET['city']) : '';
$activeDonorCondition = $schema['account_status'] ? " AND account_status = 'active'" : '';

$stats = [
    'members' => (int) $pdo->query("SELECT COUNT(*) FROM uinfo WHERE role = 'user'")->fetchColumn(),
    'donors' => $schema['donor_available'] ? (int) $pdo->query("SELECT COUNT(*) FROM uinfo WHERE donor_available = 1 AND role = 'user'{$activeDonorCondition}")->fetchColumn() : 0,
    'requests' => $schema['requests'] ? (int) $pdo->query('SELECT COUNT(*) FROM blood_requests')->fetchColumn() : 0,
    'open' => $schema['requests'] ? (int) $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE status = 'open' AND needed_by >= CURDATE()")->fetchColumn() : 0,
    'fulfilled' => $schema['requests'] ? (int) $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE status = 'fulfilled'")->fetchColumn() : 0,
    'closed' => $schema['requests'] ? (int) $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE status IN ('cancelled', 'expired') OR (status = 'open' AND needed_by < CURDATE())")->fetchColumn() : 0,
    'posts' => $schema['posts'] ? (int) $pdo->query('SELECT COUNT(*) FROM community_posts')->fetchColumn() : 0,
    'reports' => $schema['interactions'] ? (int) $pdo->query("SELECT COUNT(*) FROM post_interactions WHERE interaction = 'report'")->fetchColumn() : 0,
    'responses' => $schema['responses'] ? (int) $pdo->query('SELECT COUNT(*) FROM request_responses')->fetchColumn() : 0
];

$rows = [];
$messageReportRows = [];
$bloodBreakdown = [];
$cityBreakdown = [];
$statusBreakdown = [];
$registrationTrend = [];
$reportTrend = [];
$editingEvent = null;
$clinicRows = [];
$moderationGuidelines = '';
$platformInfo = '';

if ($currentPage === 'members') {
    $conditions = ["role = 'user'"];
    $params = [];
    if ($query !== '') {
        $conditions[] = '(fname LIKE :q_fname OR lname LIKE :q_lname OR email LIKE :q_email OR phone LIKE :q_phone)';
        $searchPattern = '%' . $query . '%';
        $params[':q_fname'] = $searchPattern;
        $params[':q_lname'] = $searchPattern;
        $params[':q_email'] = $searchPattern;
        $params[':q_phone'] = $searchPattern;
    }
    if (in_array($bloodType, ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], true)) {
        $conditions[] = 'btype = :btype';
        $params[':btype'] = $bloodType;
    }
    if ($schema['city'] && $cityFilter !== '') {
        $conditions[] = 'city LIKE :city';
        $params[':city'] = '%' . $cityFilter . '%';
    }
    if ($schema['donor_available'] && isset($_GET['donor']) && in_array($_GET['donor'], ['0', '1'], true)) {
        $conditions[] = 'donor_available = :donor';
        $params[':donor'] = (int) $_GET['donor'];
    }
    if ($schema['account_status'] && in_array($status, ['active', 'suspended'], true)) {
        $conditions[] = 'account_status = :account_status';
        $params[':account_status'] = $status;
    }
    $citySelect = $schema['city'] ? 'city' : "'' AS city";
    $donorSelect = $schema['donor_available'] ? 'donor_available' : '0 AS donor_available';
    $statusSelect = $schema['account_status'] ? 'account_status' : "'migration_required' AS account_status";
    $stmt = $pdo->prepare(
        "SELECT id, fname, lname, email, btype, phone, {$citySelect}, {$donorSelect}, {$statusSelect}, created_at
         FROM uinfo WHERE " . implode(' AND ', $conditions) . ' ORDER BY created_at DESC LIMIT 250'
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
}

if ($currentPage === 'clinics' && $schema['clinics']) {
    $facilityType = isset($_GET['facility_type']) && is_string($_GET['facility_type']) ? $_GET['facility_type'] : '';
    $clinicStatus = isset($_GET['clinic_status']) && is_string($_GET['clinic_status']) ? $_GET['clinic_status'] : '';
    $conditions = ["u.role = 'clinic'"];
    $params = [];
    if ($query !== '') {
        $conditions[] = '(cp.facility_name LIKE :name OR cp.official_email LIKE :email OR cp.city LIKE :city OR cp.province LIKE :province OR u.email LIKE :login_email)';
        foreach (['name','email','city','province','login_email'] as $key) $params[':' . $key] = '%' . $query . '%';
    }
    if (in_array($facilityType, ['clinic','blood_bank'], true)) {
        $conditions[] = 'cp.facility_type = :facility_type';
        $params[':facility_type'] = $facilityType;
    }
    if ($schema['account_status'] && in_array($clinicStatus, ['active','suspended'], true)) {
        $conditions[] = 'u.account_status = :clinic_status';
        $params[':clinic_status'] = $clinicStatus;
    }
    $clinicStatusSelect = $schema['account_status'] ? 'u.account_status' : "'migration_required' AS account_status";
    $clinicStmt = $pdo->prepare("SELECT u.id, u.email AS login_email, {$clinicStatusSelect}, u.created_at AS account_created_at, cp.*,
        (SELECT COUNT(*) FROM blood_requests br WHERE br.requester_id = u.id) AS request_count,
        (SELECT COUNT(*) FROM request_responses rr JOIN blood_requests br2 ON br2.id = rr.request_id WHERE br2.requester_id = u.id) AS response_count
        FROM uinfo u JOIN clinic_profiles cp ON cp.user_id = u.id WHERE " . implode(' AND ', $conditions) . " ORDER BY u.created_at DESC LIMIT 250");
    $clinicStmt->execute($params);
    $clinicRows = $clinicStmt->fetchAll();
}

if ($currentPage === 'requests' && $schema['requests']) {
    $conditions = ['1 = 1'];
    $params = [];
    if ($query !== '') {
        $conditions[] = '(br.hospital LIKE :q_hospital OR br.location LIKE :q_location OR br.details LIKE :q_details OR u.fname LIKE :q_fname OR u.lname LIKE :q_lname)';
        $searchPattern = '%' . $query . '%';
        foreach (['hospital', 'location', 'details', 'fname', 'lname'] as $field) {
            $params[':q_' . $field] = $searchPattern;
        }
    }
    if (in_array($bloodType, ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], true)) {
        $conditions[] = 'br.blood_type = :btype';
        $params[':btype'] = $bloodType;
    }
    if (in_array($status, ['open', 'fulfilled', 'cancelled', 'expired'], true)) {
        $conditions[] = $status === 'expired'
            ? "(br.status = 'expired' OR (br.status = 'open' AND br.needed_by < CURDATE()))"
            : 'br.status = :status';
        if ($status !== 'expired') {
            $params[':status'] = $status;
        }
    }
    if (in_array($urgency, ['urgent', 'upcoming'], true)) {
        $conditions[] = $urgency === 'urgent'
            ? '(br.needed_by >= CURDATE() AND br.needed_by <= DATE_ADD(CURDATE(), INTERVAL 3 DAY))'
            : 'br.needed_by > DATE_ADD(CURDATE(), INTERVAL 3 DAY)';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $neededDate)) {
        $conditions[] = 'br.needed_by = :needed_by';
        $params[':needed_by'] = $neededDate;
    }
    $responseSelect = $schema['responses']
        ? '(SELECT COUNT(*) FROM request_responses rr WHERE rr.request_id = br.id) AS response_count'
        : '0 AS response_count';
    $stmt = $pdo->prepare(
        "SELECT br.*, u.fname, u.lname, {$responseSelect}
         FROM blood_requests br JOIN uinfo u ON u.id = br.requester_id
         WHERE " . implode(' AND ', $conditions) . " ORDER BY FIELD(br.urgency, 'emergency', 'urgent', 'routine'), br.needed_by ASC, br.created_at DESC LIMIT 250"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
}

if (($currentPage === 'posts' || $currentPage === 'events') && $schema['posts']) {
    $postType = $currentPage === 'events' ? "post_type = 'event'" : "post_type <> 'event'";
    $likeCountSelect = $schema['interactions']
        ? "(SELECT COUNT(*) FROM post_interactions likes WHERE likes.post_id = cp.id AND likes.interaction = 'like') AS like_count"
        : '0 AS like_count';
    $commentCountSelect = $schema['comments']
        ? '(SELECT COUNT(*) FROM post_comments comments WHERE comments.post_id = cp.id) AS comment_count'
        : '0 AS comment_count';
    $stmt = $pdo->prepare(
        "SELECT cp.*, u.fname, u.lname, {$likeCountSelect}, {$commentCountSelect}
         FROM community_posts cp JOIN uinfo u ON u.id = cp.user_id
         WHERE {$postType} AND (cp.content LIKE :q_content OR u.fname LIKE :q_fname OR u.lname LIKE :q_lname)
         ORDER BY cp.created_at DESC LIMIT 250"
    );
    $searchPattern = '%' . $query . '%';
    $stmt->execute([':q_content' => $searchPattern, ':q_fname' => $searchPattern, ':q_lname' => $searchPattern]);
    $rows = $stmt->fetchAll();
    if ($currentPage === 'events' && isset($_GET['edit']) && is_string($_GET['edit']) && ctype_digit($_GET['edit'])) {
        $eventStmt = $pdo->prepare("SELECT * FROM community_posts WHERE id = :id AND post_type = 'event' LIMIT 1");
        $eventStmt->execute([':id' => (int) $_GET['edit']]);
        $editingEvent = $eventStmt->fetch() ?: null;
    }
}

if ($currentPage === 'reports' && $schema['interactions'] && $schema['posts']) {
    $reportReasonSelect = $schema['report_reason'] ? 'pi.report_reason' : "'' AS report_reason";
    $stmt = $pdo->query(
        "SELECT pi.post_id, pi.user_id AS reporter_id, pi.created_at AS reported_at,
                {$reportReasonSelect},
                cp.content, cp.post_type, u.fname AS reporter_first, u.lname AS reporter_last,
                author.fname AS author_first, author.lname AS author_last,
                (SELECT COUNT(*) FROM post_interactions reports WHERE reports.post_id = cp.id AND reports.interaction = 'report') AS report_count,
                (SELECT COUNT(*) FROM post_interactions repeat_reports
                 JOIN community_posts repeat_posts ON repeat_posts.id = repeat_reports.post_id
                 WHERE repeat_posts.user_id = cp.user_id AND repeat_reports.interaction = 'report') AS author_report_count
         FROM post_interactions pi
         JOIN community_posts cp ON cp.id = pi.post_id
         JOIN uinfo u ON u.id = pi.user_id
         JOIN uinfo author ON author.id = cp.user_id
         WHERE pi.interaction = 'report'
         ORDER BY pi.created_at ASC LIMIT 250"
    );
    $rows = $stmt->fetchAll();
}

if ($currentPage === 'reports' && $schema['message_reports']) {
    $stmt = $pdo->query(
        "SELECT r.id, r.message_type, r.reason, r.status, r.created_at,
                r.message_id, reporter.fname AS reporter_first, reporter.lname AS reporter_last,
                COALESCE(offer_message.message, request_message.message) AS reported_message,
                COALESCE(offer_sender.fname, request_sender.fname) AS sender_first,
                COALESCE(offer_sender.lname, request_sender.lname) AS sender_last
         FROM member_message_reports r
         JOIN uinfo reporter ON reporter.id = r.reporter_id
         LEFT JOIN donation_offer_messages offer_message
           ON r.message_type = 'donation_offer' AND offer_message.id = r.message_id
         LEFT JOIN request_response_messages request_message
           ON r.message_type = 'request_response' AND request_message.id = r.message_id
         LEFT JOIN uinfo offer_sender ON offer_sender.id = offer_message.sender_id
         LEFT JOIN uinfo request_sender ON request_sender.id = request_message.sender_id
         ORDER BY (r.status = 'pending') DESC, r.created_at ASC LIMIT 250"
    );
    $messageReportRows = $stmt->fetchAll();
}

if ($currentPage === 'activity' && $schema['activity']) {
    $stmt = $pdo->query(
        'SELECT l.*, u.fname, u.lname FROM admin_activity_log l
         LEFT JOIN uinfo u ON u.id = l.admin_id
         ORDER BY l.created_at DESC LIMIT 300'
    );
    $rows = $stmt->fetchAll();
}

if ($currentPage === 'settings' && $schema['settings']) {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM platform_settings WHERE setting_key IN ('platform_info', 'moderation_guidelines')");
    foreach ($settingsStmt->fetchAll() as $settingRow) {
        if ($settingRow['setting_key'] === 'platform_info') {
            $platformInfo = $settingRow['setting_value'];
        } elseif ($settingRow['setting_key'] === 'moderation_guidelines') {
            $moderationGuidelines = $settingRow['setting_value'];
        }
    }
}

if ($currentPage === 'overview' || $currentPage === 'analytics') {
    if ($schema['requests']) {
        $bloodBreakdown = $pdo->query('SELECT blood_type AS label, COUNT(*) AS total FROM blood_requests GROUP BY blood_type ORDER BY total DESC')->fetchAll();
        $cityBreakdown = $pdo->query('SELECT location AS label, COUNT(*) AS total FROM blood_requests GROUP BY location ORDER BY total DESC LIMIT 10')->fetchAll();
        $statusBreakdown = $pdo->query("SELECT CASE WHEN status = 'open' AND needed_by < CURDATE() THEN 'expired' ELSE status END AS label, COUNT(*) AS total FROM blood_requests GROUP BY label ORDER BY total DESC")->fetchAll();
    }
    $registrationTrend = $pdo->query(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS label, COUNT(*) AS total
         FROM uinfo WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
         GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY label"
    )->fetchAll();
    if ($schema['interactions']) {
        if ($schema['activity']) {
            $reportTrend = $pdo->query(
                "SELECT label, COUNT(*) AS total FROM (
                    SELECT DATE_FORMAT(created_at, '%Y-%m') AS label
                    FROM post_interactions WHERE interaction = 'report'
                    UNION ALL
                    SELECT DATE_FORMAT(created_at, '%Y-%m') AS label
                    FROM admin_activity_log
                    WHERE action IN ('remove_reported_post', 'dismiss_post_report')
                 ) report_events
                 WHERE label >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m')
                 GROUP BY label ORDER BY label"
            )->fetchAll();
        } else {
            $reportTrend = $pdo->query(
                "SELECT DATE_FORMAT(created_at, '%Y-%m') AS label, COUNT(*) AS total
                 FROM post_interactions WHERE interaction = 'report'
                 AND created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
                 GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY label"
            )->fetchAll();
        }
    }
}

if ($currentPage === 'analytics' && isset($_GET['export']) && $_GET['export'] === 'requests' && $schema['requests']) {
    $responseExport = $schema['responses']
        ? '(SELECT COUNT(*) FROM request_responses rr WHERE rr.request_id = blood_requests.id)'
        : '0';
    $exportRows = $pdo->query(
        "SELECT blood_type, location, hospital, status, needed_by, created_at, {$responseExport} AS response_count
         FROM blood_requests ORDER BY created_at DESC"
    )->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="blife-blood-request-report.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Blood type', 'Location', 'Hospital', 'Status', 'Needed by', 'Created at', 'Responses']);
    foreach ($exportRows as $exportRow) {
        $values = array_values($exportRow);
        foreach ($values as &$value) {
            $value = (string) $value;
            if (preg_match('/^[\s]*[=+\-@]/', $value)) {
                $value = "'" . $value;
            }
        }
        unset($value);
        fputcsv($output, $values);
    }
    fclose($output);
    exit;
}

$title = $sections[$currentPage];
$adminName = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));
$showClinicCreateAction = $currentPage === 'clinics' && $schema['clinics'] && $schema['account_status'];
$showEventCreateAction = $currentPage === 'events' && $schema['event_fields'] && !$editingEvent;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= admin_h($title) ?> | BLife PH Admin</title>
    <link rel="stylesheet" href="../assets/dashb.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body class="admin-layout">
    <aside class="admin-sidebar" id="adminSidebar">
        <a class="admin-brand" href="dashboard.php"><img src="../images/BlifePH_no_bg.png" alt=""><span>BLife
                PH</span></a>
        <nav aria-label="Admin navigation">
            <?php foreach ($sidebarGroups as $groupLabel => $links): ?>
                <div class="admin-nav-group">
                    <p class="admin-sidebar-section-label"><?= admin_h($groupLabel) ?></p>
                    <?php foreach ($links as $key => $label): ?>
                        <a href="dashboard.php?page=<?= admin_h($key) ?>" class="<?= $currentPage === $key ? 'active' : '' ?>"
                            <?= $currentPage === $key ? 'aria-current="page"' : '' ?>>
                            <i class="admin-nav-icon fa-solid <?= admin_h([
                                'overview' => 'fa-gauge-high', 'members' => 'fa-users', 'clinics' => 'fa-hospital',
                                'requests' => 'fa-droplet', 'posts' => 'fa-comments', 'reports' => 'fa-flag',
                                'events' => 'fa-calendar-days', 'analytics' => 'fa-chart-column',
                                'activity' => 'fa-clock-rotate-left', 'settings' => 'fa-gear'
                            ][$key] ?? 'fa-circle') ?>" aria-hidden="true"></i>
                            <span class="admin-nav-label"><?= admin_h($label) ?></span>
                            <?php if ($key === 'reports' && $stats['reports'] > 0): ?><b><?= (int) $stats['reports'] ?></b><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <div class="admin-nav-group admin-nav-system-group">
                <p class="admin-sidebar-section-label">System</p>
                <a href="dashboard.php?page=settings" class="<?= $currentPage === 'settings' ? 'active' : '' ?>" <?= $currentPage === 'settings' ? 'aria-current="page"' : '' ?>>
                    <i class="admin-nav-icon fa-solid fa-gear" aria-hidden="true"></i><span class="admin-nav-label">Settings</span>
                </a>
                <a href="../logout.php" class="admin-logout"><i class="admin-nav-icon fa-solid fa-right-from-bracket" aria-hidden="true"></i><span class="admin-nav-label">Sign Out</span></a>
            </div>
        </nav>
        <div class="admin-sidebar-user">
            <span><?= admin_h($adminName ?: 'Administrator') ?></span><small>Administrator</small>
        </div>
    </aside>
    <main class="admin-main">
        <header class="admin-page-header">
            <button class="admin-menu-toggle" id="adminMenuToggle" aria-label="Toggle admin navigation"
                aria-expanded="false">☰</button>
            <h1><?= admin_h($title) ?></h1>
            <?php if ($showClinicCreateAction): ?>
                <button class="admin-button admin-header-create" type="button" data-admin-dialog-open="createClinicDialog"><i class="fa-solid fa-plus" aria-hidden="true"></i> Create Clinic / Blood Bank</button>
            <?php elseif ($showEventCreateAction): ?>
                <button class="admin-button admin-header-create" type="button" data-admin-dialog-open="createEventDialog"><i class="fa-solid fa-plus" aria-hidden="true"></i> Create Donation Event</button>
            <?php else: ?>
                <span class="admin-role">ADMIN</span>
            <?php endif; ?>
        </header>

        <!-- this part remove some code -->

        <?php if ($currentPage === 'overview'): ?>
            <section class="admin-stat-grid" aria-label="Platform overview">
                <?php foreach ([
                    'Registered members' => $stats['members'],
                    // 'Donor-search opt-ins' => $stats['donors'],
                    'Blood requests' => $stats['requests'],
                    'Open requests' => $stats['open'],
                    // 'Fulfilled requests' => $stats['fulfilled'],
                    'Cancelled / expired' => $stats['closed'],
                    'Community posts' => $stats['posts'],
                    'Pending reports' => $stats['reports'],
                    'Member responses' => $stats['responses']
                ] as $label => $value): ?>
                    <article class="admin-stat"><span><?= admin_h($label) ?></span><strong><?= number_format($value) ?></strong>
                    </article>
                <?php endforeach; ?>
            </section>
            <?php require __DIR__ . '/partials/analytics.php'; ?>
        <?php elseif ($currentPage === 'members'): ?>
            <form class="admin-filter-form" method="get">
                <input type="hidden" name="page" value="members">
                <input name="q" value="<?= admin_h($query) ?>" placeholder="Search name">
                <select name="btype">
                    <option value="">Any blood type</option>
                    <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $type): ?>
                        <option <?= $bloodType === $type ? 'selected' : '' ?>><?= $type ?></option><?php endforeach; ?>
                </select>
                <?php if ($schema['city']): ?><input name="city" value="<?= admin_h($cityFilter) ?>"
                        placeholder="Province"><?php endif; ?>
                <button type="submit">Filter members</button>
            </form>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Email</th>
                            <th>Blood type</th>
                            <th>Province</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $member): ?>
                            <tr>
                                <td><?= admin_h(trim($member['fname'] . ' ' . $member['lname']) ?: 'Unnamed member') ?></td>
                                <td><?= admin_h($member['email']) ?></td>
                                <td><?= admin_h($member['btype'] ?: '—') ?></td>
                                <td><?= admin_h($member['city'] ?: '—') ?></td>
                                <td><?= admin_h($member['account_status']) ?></td>
                                <td><?= admin_h(date('M j, Y', strtotime($member['created_at']))) ?></td>
                                <td><?php if ((int) $member['id'] === (int) current_user_id()): ?>You<?php else: ?>
                                        <?php if ($schema['account_status']): ?>
                                            <form class="admin-inline-form" method="post" action="action.php"><input type="hidden"
                                                    name="csrf_token" value="<?= admin_h($csrfToken) ?>"><input type="hidden"
                                                    name="action" value="member_status"><input type="hidden" name="user_id"
                                                    value="<?= (int) $member['id'] ?>"><input type="hidden" name="status"
                                                    value="<?= $member['account_status'] === 'suspended' ? 'active' : 'suspended' ?>"><button
                                                    class="admin-button secondary"><?= $member['account_status'] === 'suspended' ? 'Reactivate' : 'Suspend' ?></button>
                                            </form><?php endif; ?>
                                        <form class="admin-inline-form confirm-delete" method="post" action="action.php"><input
                                                type="hidden" name="csrf_token" value="<?= admin_h($csrfToken) ?>"><input
                                                type="hidden" name="action" value="member_delete"><input type="hidden"
                                                name="user_id" value="<?= (int) $member['id'] ?>"><button
                                                class="admin-button danger">Remove</button></form>
                                    <?php endif; ?>
                                </td>
                            </tr><?php endforeach; ?>
                    </tbody>
                </table><?php if (!$rows): ?>
                    <p class="empty-state">No members match these filters.</p><?php endif; ?>
            </div>
        <?php elseif ($currentPage === 'clinics'): ?>
            <?php if (!$schema['clinics']): ?><div class="admin-alert info">Apply <code>database_sql/clinic_accounts_migration.sql</code> after the member panel and admin enhancement migrations.</div><?php else: ?>
                <?php if (!$schema['account_status']): ?><div class="admin-alert info">Clinic profiles exist, but account status is missing. Run <code>database_sql/blife_admin_enhancements.sql</code> before creating clinics or changing their status.</div><?php endif; ?>
                <dialog class="admin-dialog clinic-create-dialog" id="createClinicDialog" aria-labelledby="createClinicTitle"><form method="post" action="action.php" class="admin-dialog-form"><input type="hidden" name="csrf_token" value="<?= admin_h($csrfToken) ?>"><input type="hidden" name="action" value="clinic_create"><header class="admin-dialog-heading"><div><p>NEW INSTITUTION</p><h2 id="createClinicTitle">Create Clinic / Blood Bank</h2></div><button type="button" class="admin-dialog-close" data-admin-dialog-close aria-label="Close dialog">×</button></header>
                    <div class="clinic-create-body"><section class="admin-form-section"><h3>Institution Information</h3><div class="admin-dialog-grid"><div class="clinic-location-fields" data-facility-location data-location-api="../api/philippine_locations.php"><label>Province<select name="province" required data-facility-province><option value="">Loading provinces...</option></select></label><label>City / Municipality<select name="city" required disabled data-facility-city><option value="">Select province first</option></select></label><label>Barangay<select name="barangay" disabled data-facility-barangay><option value="">Select city / municipality first</option></select></label><label>Authorized Representative<input name="authorized_representative" maxlength="160" required></label><p class="clinic-location-error" data-facility-location-error role="status" hidden></p></div><label>Facility Name<input name="facility_name" maxlength="180" required></label><label>Facility Type<select name="facility_type" required><option value="clinic">Clinic</option><option value="blood_bank">Blood Bank</option></select></label><label>Contact Number<input name="contact_number" maxlength="30" required></label><label>Official Email Address<input type="email" name="official_email" maxlength="255" required></label>
                    </div></section><section class="admin-form-section"><h3>Account Information</h3><div class="admin-dialog-grid clinic-account-grid"><label>Login Email<input type="email" name="login_email" maxlength="255" required></label><label>Temporary Password<input type="password" name="temporary_password" minlength="12" autocomplete="new-password" required><small>At least 12 characters. The facility will change it after its first login.</small></label>
                </div></section></div><footer class="admin-dialog-actions clinic-create-footer"><button class="admin-button secondary" type="button" data-admin-dialog-close>Cancel</button><button class="admin-button" type="submit"><i class="fa-solid fa-plus" aria-hidden="true"></i> Create Account</button></footer></form></dialog>
                <form class="admin-filter-form clinic-filter-form" method="get"><input type="hidden" name="page" value="clinics"><input name="q" value="<?= admin_h($query) ?>" placeholder="Search facility, login email, location"><select name="facility_type"><option value="">All facility types</option><option value="clinic" <?= ($_GET['facility_type'] ?? '') === 'clinic' ? 'selected' : '' ?>>Clinic</option><option value="blood_bank" <?= ($_GET['facility_type'] ?? '') === 'blood_bank' ? 'selected' : '' ?>>Blood bank</option></select><select name="clinic_status"><option value="">All statuses</option><option value="active" <?= ($_GET['clinic_status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option><option value="suspended" <?= ($_GET['clinic_status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option></select><button>Search facilities</button></form>
                <?php foreach ($clinicRows as $clinic): $clinicEditValues = is_array($clinicEditFailure) && (int) ($clinicEditFailure['clinic_id'] ?? 0) === (int) $clinic['user_id'] ? $clinicEditFailure['values'] : []; ?><article class="admin-panel clinic-record"><header><div class="clinic-record-title"><h2><?= admin_h($clinic['facility_name']) ?> <?php if ((int) $clinic['is_verified']): ?><span class="clinic-verified">✓ Admin verified</span><?php endif; ?></h2><p><?= admin_h(ucwords(str_replace('_',' ',$clinic['facility_type']))) ?> · <?= admin_h($clinic['city'] . ', ' . $clinic['province']) ?></p></div><span class="clinic-status <?= admin_h($clinic['account_status']) ?>"><?= admin_h(ucfirst($clinic['account_status'])) ?></span><button class="admin-button clinic-edit-trigger" type="button" data-admin-dialog-open="editClinicDialog<?= (int) $clinic['user_id'] ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit Institution</button></header>
                <dl class="clinic-details-grid">
                    <div><dt>Address</dt><dd><?= admin_h($clinic['facility_address']) ?></dd></div><div><dt>City</dt><dd><?= admin_h($clinic['city']) ?></dd></div>
                    <div><dt>Province</dt><dd><?= admin_h($clinic['province']) ?></dd></div><div><dt>Contact Number</dt><dd><?= admin_h($clinic['contact_number']) ?></dd></div>
                    <div><dt>Official Email</dt><dd><?= admin_h($clinic['official_email']) ?></dd></div><div><dt>Login Email</dt><dd><?= admin_h($clinic['login_email']) ?></dd></div>
                    <div><dt>Representative</dt><dd><?= admin_h($clinic['authorized_representative']) ?></dd></div><div><dt>Created</dt><dd><?= admin_h(date('M j, Y', strtotime($clinic['account_created_at']))) ?></dd></div>
                    <div class="clinic-detail-description"><dt>Description</dt><dd><?= admin_h($clinic['description'] ?: '—') ?></dd></div>
                </dl>
                <dialog class="admin-dialog clinic-edit-dialog" id="editClinicDialog<?= (int) $clinic['user_id'] ?>" aria-labelledby="editClinicTitle<?= (int) $clinic['user_id'] ?>" aria-describedby="editClinicDescription<?= (int) $clinic['user_id'] ?>" data-reopen="<?= $clinicEditValues ? 'true' : 'false' ?>"><form class="admin-dialog-form clinic-edit-form" method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= admin_h($csrfToken) ?>"><input type="hidden" name="action" value="clinic_update"><input type="hidden" name="clinic_id" value="<?= (int) $clinic['user_id'] ?>"><header class="admin-dialog-heading"><div><p>INSTITUTION DETAILS</p><h2 id="editClinicTitle<?= (int) $clinic['user_id'] ?>">Edit Institution Information</h2><span id="editClinicDescription<?= (int) $clinic['user_id'] ?>"></span></div><button type="button" class="admin-dialog-close" data-admin-dialog-close aria-label="Close edit institution dialog">×</button></header>
                    <div class="clinic-edit-body"><div class="clinic-edit-grid">
                        <label>Facility Name<input name="facility_name" maxlength="180" value="<?= admin_h($clinicEditValues['facility_name'] ?? $clinic['facility_name']) ?>" required></label>
                        <label>Facility Type<select name="facility_type" required><option value="clinic" <?= ($clinicEditValues['facility_type'] ?? $clinic['facility_type']) === 'clinic' ? 'selected' : '' ?>>Clinic</option><option value="blood_bank" <?= ($clinicEditValues['facility_type'] ?? $clinic['facility_type']) === 'blood_bank' ? 'selected' : '' ?>>Blood Bank</option></select></label>
                        <div class="clinic-edit-location-fields" data-facility-location data-location-api="../api/philippine_locations.php" data-current-province="<?= admin_h($clinicEditValues['province'] ?? $clinic['province']) ?>" data-current-city="<?= admin_h($clinicEditValues['city'] ?? $clinic['city']) ?>" data-current-barangay="<?= admin_h($clinicEditValues['barangay'] ?? ($clinic['barangay'] ?? '')) ?>"><label>Province<select name="province" required data-facility-province><option value="">Loading provinces...</option></select></label><label>City / Municipality<select name="city" required disabled data-facility-city><option value="">Select province first</option></select></label><label>Barangay<select name="barangay" disabled data-facility-barangay><option value="">Select city / municipality first</option></select></label><label>Representative<input name="authorized_representative" maxlength="160" value="<?= admin_h($clinicEditValues['authorized_representative'] ?? $clinic['authorized_representative']) ?>" required></label><p class="clinic-location-error" data-facility-location-error role="status" hidden></p></div>
                        <label>Contact Number<input name="contact_number" maxlength="30" value="<?= admin_h($clinicEditValues['contact_number'] ?? $clinic['contact_number']) ?>" required></label>
                        <label>Official Email<input type="email" name="official_email" maxlength="255" value="<?= admin_h($clinicEditValues['official_email'] ?? $clinic['official_email']) ?>" required></label>
                    </div></div><footer class="admin-dialog-actions clinic-edit-footer"><button class="admin-button secondary" type="button" data-admin-dialog-close>Cancel</button><button class="admin-button" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save Changes</button></footer>
                </form></dialog>
                <section class="clinic-admin-actions" aria-label="Administrative institution actions"><h3>Administrative Actions</h3><div class="clinic-admin-actions-row">
                <form class="admin-inline-form" method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= admin_h($csrfToken) ?>"><input type="hidden" name="action" value="clinic_status"><input type="hidden" name="clinic_id" value="<?= (int) $clinic['user_id'] ?>"><input type="hidden" name="status" value="<?= $clinic['account_status'] === 'active' ? 'suspended' : 'active' ?>"><button class="admin-button secondary" <?= !$schema['account_status'] ? 'disabled title="Run the admin enhancement migration first."' : '' ?>><?= $clinic['account_status'] === 'active' ? 'Suspend Institution' : 'Activate Institution' ?></button></form><form class="admin-inline-form" method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= admin_h($csrfToken) ?>"><input type="hidden" name="action" value="clinic_verification"><input type="hidden" name="clinic_id" value="<?= (int) $clinic['user_id'] ?>"><input type="hidden" name="verified" value="<?= (int) $clinic['is_verified'] ? '0' : '1' ?>"><button class="admin-button secondary"><?= (int) $clinic['is_verified'] ? 'Remove Verification' : 'Verify Institution' ?></button></form><form class="admin-inline-form" method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= admin_h($csrfToken) ?>"><input type="hidden" name="action" value="clinic_password_reset"><input type="hidden" name="clinic_id" value="<?= (int) $clinic['user_id'] ?>"><label>New temporary password<input type="password" name="temporary_password" minlength="12" required autocomplete="new-password"></label><button class="admin-button secondary">Require Password Change</button></form></div></section></article><?php endforeach; ?><?php if (!$clinicRows): ?><div class="clinic-empty-state"><i class="fa-solid fa-hospital" aria-hidden="true"></i><p>No clinics or blood banks found</p></div><?php endif; ?>
            <?php endif; ?>
        <?php elseif ($currentPage === 'requests'): ?>
            <?php if (!$schema['requests']): ?>
                <div class="admin-alert info">Blood request tables are not installed. Apply the member panel migration first.
                </div><?php else: ?>
                <form class="admin-filter-form" method="get">
                    <input type="hidden" name="page" value="requests"><input name="q" value="<?= admin_h($query) ?>"
                        placeholder="Search Requester"><select name="btype">
                        <option value="">Any blood type</option>
                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $type): ?>
                            <option <?= $bloodType === $type ? 'selected' : '' ?>><?= $type ?></option><?php endforeach; ?>
                    </select>
                    <button>Filter requests</button>
                </form>
                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Request</th>
                                <th>Requester</th>
                                <th>Province</th>
                                <th>Urgency</th>
                                <th>Needed by</th>
                                <th>Responses</th>
                                <th>Status</th>
                                <th>Removal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $request):
                                $requestExpired = $request['status'] === 'open' && $request['needed_by'] < date('Y-m-d'); ?>
                                <tr>
                                    <td><?= admin_h($request['blood_type']) ?> · <?= (int) $request['units'] ?>
                                        unit(s)<br><small><?= admin_h($request['details']) ?></small></td>
                                    <td><?= admin_h(trim($request['fname'] . ' ' . $request['lname'])) ?></td>
                                    <td><?= admin_h($request['location']) ?></td>
                                    <td><span class="urgency-badge urgency-<?= admin_h($request['urgency'] ?? 'routine') ?>"><?= admin_h(ucfirst($request['urgency'] ?? 'routine')) ?></span></td>
                                    <td><?= admin_h($request['needed_by']) ?></td>
                                    <td><?= (int) $request['response_count'] ?></td>
                                    <td><?= $requestExpired ? 'Expired' : admin_h(ucfirst($request['status'])) ?><?php if (!empty($request['status_reason'])): ?><br><small><?= admin_h($request['status_reason']) ?></small><?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" action="action.php" class="request-removal-form confirm-delete"><input
                                                type="hidden" name="csrf_token" value="<?= admin_h($csrfToken) ?>"><input
                                                type="hidden" name="action" value="request_remove"><input type="hidden"
                                                name="request_id" value="<?= (int) $request['id'] ?>"><input name="reason"
                                                maxlength="255" placeholder="Removal reason" aria-label="Removal reason"
                                                required><button class="admin-button danger" type="submit">Remove</button></form>
                                    </td>
                                </tr><?php endforeach; ?>
                        </tbody>
                    </table><?php if (!$rows): ?>
                        <p class="empty-state">No requests found.</p><?php endif; ?>
                </div><?php endif; ?>
        <?php elseif ($currentPage === 'posts'): ?>
            <form class="admin-filter-form" method="get"><input type="hidden" name="page" value="posts"><input name="q"
                    value="<?= admin_h($query) ?>" placeholder="Search post or author"><button>Search posts</button></form>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Post</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>Engagement</th>
                            <th>Published</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($rows as $post): ?>
                            <tr>
                                <td>
                                    <details>
                                        <summary>
                                            <?= admin_h(function_exists('mb_strimwidth') ? mb_strimwidth($post['content'], 0, 100, '…') : substr($post['content'], 0, 100)) ?>
                                        </summary>
                                        <p><?= nl2br(admin_h($post['content'])) ?></p>
                                    </details>
                                </td>
                                <td><?= admin_h(trim($post['fname'] . ' ' . $post['lname'])) ?></td>
                                <td><?= admin_h($post['post_type']) ?></td>
                                <td><?= (int) $post['like_count'] ?> likes · <?= (int) $post['comment_count'] ?> comments</td>
                                <td><?= admin_h($post['created_at']) ?></td>
                                <td>
                                    <form method="post" action="action.php" class="admin-inline-form confirm-delete"><input
                                            type="hidden" name="csrf_token" value="<?= admin_h($csrfToken) ?>"><input
                                            type="hidden" name="action" value="post_remove"><input type="hidden" name="post_id"
                                            value="<?= (int) $post['id'] ?>"><input name="reason" maxlength="255"
                                            placeholder="Moderation reason" required><button
                                            class="admin-button danger">Remove</button></form>
                                </td>
                            </tr><?php endforeach; ?>
                    </tbody>
                </table><?php if (!$rows): ?>
                    <p class="empty-state">No community posts found.</p><?php endif; ?>
            </div>
        <?php elseif ($currentPage === 'reports'): ?>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Reported post</th>
                            <th>Author</th>
                            <th>Reporter / reason</th>
                            <th>Report date</th>
                            <th>Counts</th>
                            <th>Review action</th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($rows as $report): ?>
                            <tr>
                                <td>
                                    <details>
                                        <summary>
                                            <?= admin_h(function_exists('mb_strimwidth') ? mb_strimwidth($report['content'], 0, 100, '…') : substr($report['content'], 0, 100)) ?>
                                        </summary>
                                        <p><?= nl2br(admin_h($report['content'])) ?></p>
                                    </details>
                                </td>
                                <td><?= admin_h(trim($report['author_first'] . ' ' . $report['author_last'])) ?></td>
                                <td><?= admin_h(trim($report['reporter_first'] . ' ' . $report['reporter_last'])) ?><br><small><?= admin_h($report['report_reason'] ?: 'No reason supplied') ?></small>
                                </td>
                                <td><?= admin_h($report['reported_at']) ?></td>
                                <td><?= (int) $report['report_count'] ?>
                                    reports<br><small><?= (int) $report['author_report_count'] ?> on this author</small></td>
                                <td>
                                    <form method="post" action="review_report.php" class="admin-action-form report-review-form">
                                        <input type="hidden" name="csrf_token" value="<?= admin_h($csrfToken) ?>"><input
                                            type="hidden" name="post_id" value="<?= (int) $report['post_id'] ?>"><input
                                            type="hidden" name="reporter_id" value="<?= (int) $report['reporter_id'] ?>"><input
                                            name="reason" maxlength="255" placeholder="Review reason" required><button
                                            name="action" value="dismiss" class="admin-button secondary">Dismiss</button><button
                                            name="action" value="delete" class="admin-button danger">Remove post</button>
                                    </form>
                                </td>
                            </tr><?php endforeach; ?>
                    </tbody>
                </table><?php if (!$rows): ?>
                    <p class="empty-state">No posts are awaiting report review.</p><?php endif; ?>
            </div>
            <section class="admin-panel">
                <h2>Reported private messages</h2>
                <?php if (!$schema['message_reports']): ?>
                    <p class="admin-alert info">Message reports are unavailable until
                        <code>database_sql/messages_inbox_migration.sql</code> is applied.
                    </p>
                <?php else: ?>
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Message / sender</th>
                                    <th>Reporter / reason</th>
                                    <th>Report date</th>
                                    <th>Status</th>
                                    <th>Review</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($messageReportRows as $report): ?>
                                    <tr>
                                        <td><strong><?= admin_h($report['message_type'] === 'donation_offer' ? 'Donation offer' : 'Blood request') ?></strong><br><?= nl2br(admin_h($report['reported_message'] ?: '[Message no longer available]')) ?><br><small>From
                                                <?= admin_h(trim($report['sender_first'] . ' ' . $report['sender_last']) ?: 'Former member') ?></small>
                                        </td>
                                        <td><?= admin_h(trim($report['reporter_first'] . ' ' . $report['reporter_last']) ?: 'Former member') ?><br><small><?= admin_h($report['reason']) ?></small>
                                        </td>
                                        <td><?= admin_h($report['created_at']) ?></td>
                                        <td><?= admin_h(ucfirst($report['status'])) ?></td>
                                        <td><?php if ($report['status'] === 'pending'): ?>
                                                <form method="post" action="action.php"><input type="hidden" name="csrf_token"
                                                        value="<?= admin_h($csrfToken) ?>"><input type="hidden" name="action"
                                                        value="message_report_review"><input type="hidden" name="report_id"
                                                        value="<?= (int) $report['id'] ?>"><button class="admin-button secondary">Mark
                                                        reviewed</button></form><?php else: ?>Reviewed<?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table><?php if (!$messageReportRows): ?>
                            <p class="empty-state">No private messages have been reported.</p><?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php elseif ($currentPage === 'events'): ?>
            <?php if (!$schema['event_fields']): ?>
                <div class="admin-alert info">Donation event management needs the updated event columns. Apply
                    <code>database_sql/blife_admin_modules_migration.sql</code>.
                </div>
            <?php else: ?>
                <?php if (!$editingEvent): ?><dialog class="admin-dialog admin-event-dialog" id="createEventDialog" aria-labelledby="createEventTitle"><div class="admin-event-dialog-inner"><header class="admin-dialog-heading"><div><p>COMMUNITY EVENT</p><h2 id="createEventTitle">Create Donation Event</h2></div><button type="button" class="admin-dialog-close" data-admin-dialog-close aria-label="Close dialog">×</button></header><?php endif; ?>
                <section class="admin-panel">
                    <h2><?= $editingEvent ? 'Edit event announcement' : 'Create donation event announcement' ?></h2>
                    <form class="admin-event-form" method="post" action="action.php"><input type="hidden" name="csrf_token"
                            value="<?= admin_h($csrfToken) ?>"><input type="hidden" name="action" value="event_save"><input
                            type="hidden" name="post_id" value="<?= (int) ($editingEvent['id'] ?? 0) ?>"><label>Event
                            title<input name="event_title" maxlength="160" required
                                value="<?= admin_h($editingEvent['event_title'] ?? '') ?>"></label><label>Description<textarea
                                name="content" maxlength="1000" rows="3"
                                required><?= admin_h($editingEvent['content'] ?? '') ?></textarea></label>
                        <div class="admin-event-grid"><label>Organizer<input name="organizer" maxlength="160"
                                    value="<?= admin_h($editingEvent['organizer'] ?? '') ?>"></label><label>Date<input
                                    name="event_date" type="date" required
                                    value="<?= admin_h($editingEvent['event_date'] ?? '') ?>"></label><label>Time<input
                                    name="event_time" type="time"
                                    value="<?= admin_h($editingEvent['event_time'] ?? '') ?>"></label><label>Venue<input
                                    name="venue" maxlength="180"
                                    value="<?= admin_h($editingEvent['venue'] ?? '') ?>"></label><label>City<input
                                    name="event_city" maxlength="120"
                                    value="<?= admin_h($editingEvent['event_city'] ?? '') ?>"></label><label>Public
                                contact<input name="public_contact" maxlength="180"
                                    value="<?= admin_h($editingEvent['public_contact'] ?? '') ?>"></label><label>Status<select
                                    name="event_status"><?php foreach (['published', 'draft', 'cancelled', 'completed'] as $value): ?>
                                        <option value="<?= $value ?>" <?= ($editingEvent['event_status'] ?? 'published') === $value ? 'selected' : '' ?>><?= ucfirst($value) ?></option><?php endforeach; ?>
                                </select></label></div>
                        <p class="form-help">Announcements are not verified; attending or registering is not provided by this
                            platform.</p><div class="admin-event-actions"><button
                            class="admin-button"><?= $editingEvent ? 'Save changes' : 'Create event' ?></button><?php if ($editingEvent): ?><a
                                class="admin-button secondary" href="dashboard.php?page=events">Cancel edit</a><?php else: ?><button class="admin-button secondary" type="button" data-admin-dialog-close>Cancel</button><?php endif; ?></div>
                    </form>
                </section><?php if (!$editingEvent): ?></div></dialog><?php endif; ?>
                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Date / Venue</th>
                                <th>Organizer</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($rows as $event): ?>
                                <tr>
                                    <td><?= admin_h($event['event_title']) ?><br><small><?= admin_h($event['content']) ?></small>
                                    </td>
                                    <td><?= admin_h($event['event_date']) ?><br><?= admin_h($event['venue'] . ', ' . $event['event_city']) ?>
                                    </td>
                                    <td><?= admin_h($event['organizer']) ?></td>
                                    <td><?= admin_h($event['event_status']) ?></td>
                                    <td><a class="admin-button secondary"
                                            href="dashboard.php?page=events&amp;edit=<?= (int) $event['id'] ?>">Edit</a>
                                        <form method="post" action="action.php" class="admin-inline-form event-status-form"><input
                                                type="hidden" name="csrf_token" value="<?= admin_h($csrfToken) ?>"><input
                                                type="hidden" name="action" value="event_status"><input type="hidden" name="post_id"
                                                value="<?= (int) $event['id'] ?>"><select name="status">
                                                <option value="published">Publish</option>
                                                <option value="draft">Unpublish</option>
                                                <option value="cancelled">Cancel</option>
                                                <option value="completed">Complete</option>
                                            </select><button class="admin-button">Update</button></form>
                                    </td>
                                </tr><?php endforeach; ?>
                        </tbody>
                    </table><?php if (!$rows): ?>
                        <p class="empty-state">No donation events have been created.</p><?php endif; ?>
                </div><?php endif; ?>
        <?php elseif ($currentPage === 'analytics'): ?>
            <?php require __DIR__ . '/partials/analytics.php'; ?>
            <?php if ($schema['requests']): ?><a class="admin-button"
                    href="dashboard.php?page=analytics&amp;export=requests">Export requests CSV</a><?php endif; ?>
        <?php elseif ($currentPage === 'activity'): ?>
            <?php if (!$schema['activity']): ?>
                <div class="admin-alert info">The audit table is not installed. Apply the admin enhancement migration.</div>
            <?php else: ?>
                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Administrator</th>
                                <th>Action</th>
                                <th>Record</th>
                                <th>Reason / details</th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($rows as $log): ?>
                                <tr>
                                    <td><?= admin_h($log['created_at']) ?></td>
                                    <td><?= admin_h(trim(($log['fname'] ?? '') . ' ' . ($log['lname'] ?? '')) ?: 'Admin #' . $log['admin_id']) ?>
                                    </td>
                                    <td><?= admin_h($log['action']) ?></td>
                                    <td><?= admin_h($log['affected_table']) ?> #<?= (int) $log['affected_record_id'] ?></td>
                                    <td><?= admin_h($log['reason'] ?: $log['action_details']) ?></td>
                                </tr><?php endforeach; ?>
                        </tbody>
                    </table><?php if (!$rows): ?>
                        <p class="empty-state">No recorded admin actions yet.</p><?php endif; ?>
                </div><?php endif; ?>
        <?php elseif ($currentPage === 'settings'): ?>
            <?php if (!$schema['settings']): ?>
                <div class="admin-alert info">Settings storage needs the admin enhancement migration.</div><?php else: ?>
                <section class="admin-panel">
                    <h2>Platform information and moderation guidance</h2>
                    <form class="admin-event-form" method="post" action="action.php"><input type="hidden" name="csrf_token"
                            value="<?= admin_h($csrfToken) ?>"><input type="hidden" name="action"
                            value="save_settings"><label>Platform information<textarea name="platform_info" rows="4"
                                maxlength="3000"
                                required><?= admin_h($platformInfo ?: 'BLife PH helps community members connect about blood requests and potential donation support. The platform does not confirm blood availability, determine medical eligibility, or replace authorized hospitals and blood banks.') ?></textarea></label><label>Moderation
                            guidelines<textarea name="moderation_guidelines" rows="5" maxlength="3000"
                                required><?= admin_h($moderationGuidelines ?: 'Review reports in context, remove content that violates community rules, and record a clear reason for moderation. Do not represent content moderation as hospital confirmation or medical verification.') ?></textarea></label><button
                            class="admin-button">Save settings</button></form>
                </section><?php endif; ?>
        <?php endif; ?>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../validation/admin-clinic-location.js"></script>
    <script src="../validation/auth-notices.js"></script>
    <script>
        const menuButton = document.getElementById('adminMenuToggle');
        const sidebar = document.getElementById('adminSidebar');
        menuButton.addEventListener('click', () => {
            const open = sidebar.classList.toggle('open');
            menuButton.setAttribute('aria-expanded', String(open));
        });
        document.querySelectorAll('.confirm-delete').forEach((form) => form.addEventListener('submit', (event) => {
            if (!window.confirm('Remove this record? This action cannot be undone.')) event.preventDefault();
        }));
        document.querySelectorAll('.report-review-form').forEach((form) => form.addEventListener('submit', (event) => {
            if (event.submitter && event.submitter.value === 'delete' &&
                !window.confirm('Remove this post and its related comments? This cannot be undone.')) event.preventDefault();
        }));
        document.querySelectorAll('.event-status-form').forEach((form) => form.addEventListener('submit', (event) => {
            if (form.elements.status.value === 'cancelled' &&
                !window.confirm('Cancel this donation event announcement?')) event.preventDefault();
        }));
        <?php if (is_array($flash) && isset($flash['message'], $flash['type'])): ?>
        if (window.Swal) {
            Swal.fire({icon: <?= json_encode($flash['type'] === 'success' ? 'success' : 'error') ?>, text: <?= json_encode($flash['message'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>});
        } else {
            window.alert(<?= json_encode($flash['message'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
        }
        <?php endif; ?>
        const dialogSnapshot = (dialog) => Array.from(dialog.querySelectorAll('.clinic-edit-form input:not([type="hidden"]), .clinic-edit-form select, .clinic-edit-form textarea')).map((field) => field.value).join('\u001f');
        const openAdminDialog = (dialog, opener = null) => {
            if (!dialog || dialog.open) return;
            dialog.dataset.closing = 'false';
            dialog.classList.remove('clinic-dialog-closing');
            dialog._returnFocus = opener || document.activeElement;
            dialog._baseline = dialogSnapshot(dialog);
            dialog.showModal();
            requestAnimationFrame(() => dialog.querySelector('input:not([type="hidden"]), select, textarea')?.focus());
        };
        const closeAdminDialog = (dialog) => {
            if (!dialog?.open) return;
            if (dialog.dataset.submitting === 'true' || dialog.dataset.closing === 'true') return;
            if (dialog.classList.contains('clinic-edit-dialog') && dialogSnapshot(dialog) !== dialog._baseline && !window.confirm('Discard your unsaved institution changes?')) return;
            if (dialog.classList.contains('clinic-edit-dialog')) {
                dialog.dataset.closing = 'true';
                dialog.classList.add('clinic-dialog-closing');
                window.setTimeout(() => { dialog.close(); dialog.classList.remove('clinic-dialog-closing'); dialog.dataset.closing = 'false'; }, 160);
            } else dialog.close();
        };
        document.querySelectorAll('[data-admin-dialog-open]').forEach((button) => button.addEventListener('click', () => openAdminDialog(document.getElementById(button.dataset.adminDialogOpen), button)));
        document.querySelectorAll('[data-admin-dialog-close]').forEach((button) => button.addEventListener('click', () => closeAdminDialog(button.closest('dialog'))));
        document.querySelectorAll('.admin-dialog').forEach((dialog) => dialog.addEventListener('click', (event) => {
            if (event.target === dialog) closeAdminDialog(dialog);
        }));
        document.querySelectorAll('.clinic-edit-dialog').forEach((dialog) => {
            dialog.addEventListener('cancel', (event) => {
                event.preventDefault();
                closeAdminDialog(dialog);
            });
            dialog.addEventListener('close', () => dialog._returnFocus?.focus());
            dialog.addEventListener('keydown', (event) => {
                if (event.key !== 'Tab') return;
                const focusable = Array.from(dialog.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [href], [tabindex]:not([tabindex="-1"])')).filter((item) => item.offsetParent !== null);
                if (!focusable.length) return;
                const first = focusable[0], last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            });
            dialog.querySelector('form')?.addEventListener('submit', (event) => {
                if (!event.currentTarget.reportValidity()) { event.preventDefault(); return; }
                dialog.dataset.submitting = 'true';
                const submit = event.currentTarget.querySelector('button[type="submit"]');
                if (submit) { submit.disabled = true; submit.dataset.originalText = submit.textContent.trim(); submit.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Saving…'; }
            });
            if (dialog.dataset.reopen === 'true') openAdminDialog(dialog, document.querySelector(`[data-admin-dialog-open="${dialog.id}"]`));
        });
    </script>
</body>

</html>
