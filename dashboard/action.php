<?php

session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/post_location_validation.php';
require_once __DIR__ . '/../includes/member_session_security.php';
$philippineProvinces = require __DIR__ . '/../config/philippine_provinces.php';

require_login('../login.php');

if (is_admin()) {
    header('Location: ../admin/dashboard.php');
    exit;
}

function redirect_member(string $message, string $type, string $section, ?int $postId = null, ?string $thread = null): void
{
    $aliases = ['overview' => 'home', 'community' => 'home', 'donors' => 'find-blood', 'requests' => 'blood-requests'];
    $section = $aliases[$section] ?? $section;
    $_SESSION['member_flash'] = ['message' => $message, 'type' => $type];
    $base = is_clinic() ? '../clinic/dashboard.php' : 'dashboard.php';
    $location = $base . '?page=' . rawurlencode($section);
    if ($postId !== null && $postId > 0) {
        $location .= '#post-' . $postId;
    }
    if ($thread !== null && preg_match('/^(offer|request)-\d+(-\d+)?$/', $thread)) {
        $location .= (strpos($location, '?') === false ? '?' : '&') . 'thread=' . rawurlencode($thread);
    }
    header('Location: ' . $location);
    exit;
}

function member_return_page(): string
{
    $allowedPages = ['home', 'events', 'donation-offers', 'my-posts', 'saved', 'search', 'messages'];
    $page = $_POST['return_page'] ?? 'home';
    return is_string($page) && in_array($page, $allowedPages, true) ? $page : 'home';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !is_string($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    redirect_member('Your session check failed. Please try again.', 'error', 'overview');
}

foreach ($_POST as $value) {
    if (!is_string($value)) {
        redirect_member('Invalid form data. Please review the form and try again.', 'error', 'overview');
    }
}

$userId = (int) current_user_id();
$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$action = $_POST['action'] ?? '';

if (is_clinic() && (int)($_SESSION['must_change_password'] ?? 0) === 1) {
    header('Location: ../clinic/dashboard.php?page=settings');
    exit;
}
if (is_clinic() && !in_array($action, ['request_message', 'accept_request_response', 'notification_open', 'donation_offer_message'], true)) {
    header('Location: ../clinic/dashboard.php');
    exit;
}

try {
    $pdo = (new Database())->getConnection();
    if (current_role() === 'user' && !member_session_is_active($pdo, $userId)) {
        $_SESSION = [];
        session_destroy();
        header('Location: ../login.php?session_revoked=1');
        exit;
    }
    $memberSecurityActions = ['privacy_settings', 'change_password', 'request_email_change', 'revoke_session', 'deactivate_account', 'request_account_deletion'];
    if (current_role() === 'user' && in_array($action, $memberSecurityActions, true)) {
        $securitySchema = $pdo->prepare("SELECT (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('member_privacy_preferences','member_sessions','member_email_change_tokens','member_account_deletion_requests')) AS tables_ready, (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'uinfo' AND COLUMN_NAME = 'member_deactivated_at') AS deactivation_ready");
        $securitySchema->execute();
        $securityState = $securitySchema->fetch(PDO::FETCH_ASSOC);
        if ((int)($securityState['tables_ready'] ?? 0) !== 4 || (int)($securityState['deactivation_ready'] ?? 0) !== 1) {
            redirect_member('Run database_sql/member_privacy_security_migration.sql before using account security settings.', 'error', 'settings');
        }
    }
    $accountStatusCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'uinfo' AND COLUMN_NAME = 'account_status'"
    );
    $accountStatusCheck->execute();
    if ((int) $accountStatusCheck->fetchColumn() > 0) {
        $statusStmt = $pdo->prepare('SELECT account_status FROM uinfo WHERE id = :id LIMIT 1');
        $statusStmt->execute([':id' => $userId]);
        if ($statusStmt->fetchColumn() !== 'active') {
            session_destroy();
            header('Location: ../login.php?suspended=1');
            exit;
        }
    }

    if (is_clinic()) {
        $mustChangeStmt = $pdo->prepare("SELECT must_change_password FROM uinfo WHERE id = :id AND role = 'clinic' LIMIT 1");
        $mustChangeStmt->execute([':id' => $userId]);
        if ((int) $mustChangeStmt->fetchColumn() === 1) {
            $_SESSION['must_change_password'] = 1;
            header('Location: ../clinic/dashboard.php?page=settings');
            exit;
        }
    }

    if (current_role() === 'user' && $action !== 'profile') {
        $profileCheck = $pdo->prepare("SELECT fname, lname, phone, btype, city FROM uinfo WHERE id = :id AND role = 'user' LIMIT 1");
        $profileCheck->execute([':id' => $userId]);
        $memberDetails = $profileCheck->fetch();
        if (!$memberDetails || trim((string) $memberDetails['fname']) === '' || trim((string) $memberDetails['lname']) === '' || trim((string) $memberDetails['phone']) === '' || empty($memberDetails['btype']) || trim((string) $memberDetails['city']) === '') {
            redirect_member('Complete your profile before using the member dashboard.', 'error', 'profile');
        }
    }

    switch ($action) {
        case 'profile':
            $firstName = trim($_POST['fname'] ?? '');
            $lastName = trim($_POST['lname'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $bloodType = $_POST['btype'] ?? '';
            $errors = [];

            if ($firstName === '' || strlen($firstName) > 100) {
                $errors[] = 'Enter a first name of up to 100 characters.';
            }
            if ($lastName === '' || strlen($lastName) > 100) {
                $errors[] = 'Enter a last name of up to 100 characters.';
            }
            if ($phone === '' || strlen($phone) > 20) {
                $errors[] = 'Enter a phone number of up to 20 characters.';
            }
            if (!in_array($city, $philippineProvinces, true)) {
                $errors[] = 'Select a valid province.';
            }
            if (!in_array($bloodType, $bloodTypes, true)) {
                $errors[] = 'Select a blood type.';
            }
            if ($errors) {
                redirect_member(implode(' ', $errors), 'error', 'profile');
            }

            $completionColumn = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'uinfo' AND COLUMN_NAME = 'profile_completed_at'");
            $completionColumn->execute();
            $completionAvailable = (int) $completionColumn->fetchColumn() > 0;
            $stmt = $pdo->prepare(
                'UPDATE uinfo SET fname = :fname, lname = :lname, phone = :phone,
                    city = :city, btype = :btype' . ($completionAvailable ? ', profile_completed_at = COALESCE(profile_completed_at, NOW())' : '') . '
                 WHERE id = :id AND role = \'user\''
            );
            $stmt->execute([
                ':fname' => $firstName,
                ':lname' => $lastName,
                ':phone' => $phone,
                ':city' => $city,
                ':btype' => $bloodType === '' ? null : $bloodType,
                ':id' => $userId
            ]);
            $_SESSION['first_name'] = $firstName;
            $_SESSION['last_name'] = $lastName;
            $_SESSION['phone'] = $phone;
            $_SESSION['blood_type'] = $bloodType;
            redirect_member('Your member profile has been updated.', 'success', 'home');

        case 'settings':
            $notifyResponses = ($_POST['notify_request_responses'] ?? '') === '1' ? 1 : 0;
            $notifyComments = ($_POST['notify_post_comments'] ?? '') === '1' ? 1 : 0;
            $stmt = $pdo->prepare(
                'UPDATE uinfo
                 SET notify_request_responses = :notify_responses,
                     notify_post_comments = :notify_comments
                 WHERE id = :id'
            );
            $stmt->execute([
                ':notify_responses' => $notifyResponses,
                ':notify_comments' => $notifyComments,
                ':id' => $userId
            ]);
            redirect_member('Your notification preferences have been saved.', 'success', 'notifications');

        case 'privacy_settings':
            if (current_role() !== 'user') {
                redirect_member('This setting is available to member accounts only.', 'error', 'settings');
            }
            $privacyTable = $pdo->query("SHOW TABLES LIKE 'member_privacy_preferences'")->fetchColumn();
            if (!$privacyTable) {
                redirect_member('Run database_sql/member_privacy_security_migration.sql before changing privacy settings.', 'error', 'settings');
            }
            $directoryVisible = ($_POST['donor_available'] ?? '') === '1' ? 1 : 0;
            $privacyValues = [
                'show_first_name' => ($_POST['show_first_name'] ?? '') === '1' ? 1 : 0,
                'show_blood_type' => ($_POST['show_blood_type'] ?? '') === '1' ? 1 : 0,
                'show_province' => ($_POST['show_province'] ?? '') === '1' ? 1 : 0,
                'location_discovery_enabled' => ($_POST['location_discovery_enabled'] ?? '') === '1' ? 1 : 0,
                'optional_privacy_consent' => ($_POST['optional_privacy_consent'] ?? '') === '1' ? 1 : 0
            ];
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE uinfo SET donor_available = :visible WHERE id = :id AND role = \'user\'')->execute([':visible' => $directoryVisible, ':id' => $userId]);
            $privacyUpdate = $pdo->prepare('INSERT INTO member_privacy_preferences (user_id, show_first_name, show_blood_type, show_province, location_discovery_enabled, optional_privacy_consent, privacy_consent_updated_at) VALUES (:id, :first_name, :blood_type, :province, :location, :consent, IF(:consent_timestamp = 1, NOW(), NULL)) ON DUPLICATE KEY UPDATE show_first_name = VALUES(show_first_name), show_blood_type = VALUES(show_blood_type), show_province = VALUES(show_province), location_discovery_enabled = VALUES(location_discovery_enabled), optional_privacy_consent = VALUES(optional_privacy_consent), privacy_consent_updated_at = IF(VALUES(optional_privacy_consent) = 1, COALESCE(privacy_consent_updated_at, NOW()), NULL)');
            $privacyUpdate->execute([':id' => $userId, ':first_name' => $privacyValues['show_first_name'], ':blood_type' => $privacyValues['show_blood_type'], ':province' => $privacyValues['show_province'], ':location' => $privacyValues['location_discovery_enabled'], ':consent' => $privacyValues['optional_privacy_consent'], ':consent_timestamp' => $privacyValues['optional_privacy_consent']]);
            $pdo->commit();
            redirect_member('Your privacy settings have been saved.', 'success', 'settings');

        case 'change_password':
            if (current_role() !== 'user') redirect_member('This action is available to members only.', 'error', 'settings');
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $passwordStmt = $pdo->prepare("SELECT password FROM uinfo WHERE id = :id AND role = 'user' LIMIT 1");
            $passwordStmt->execute([':id' => $userId]);
            $passwordHash = $passwordStmt->fetchColumn();
            if (!$passwordHash || !password_verify($currentPassword, $passwordHash)) redirect_member('Your current password is incorrect.', 'error', 'settings');
            if (strlen($newPassword) < 12 || strlen($newPassword) > 200 || $newPassword !== $confirmPassword) redirect_member('Use a matching new password with at least 12 characters.', 'error', 'settings');
            $pdo->prepare('UPDATE uinfo SET password = :password WHERE id = :id')->execute([':password' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => $userId]);
            $pdo->prepare('UPDATE member_sessions SET revoked_at = NOW() WHERE user_id = :id AND session_hash <> :current_hash AND revoked_at IS NULL')->execute([':id' => $userId, ':current_hash' => hash('sha256', session_id())]);
            redirect_member('Password updated. Other active sessions were signed out.', 'success', 'settings');

        case 'request_email_change':
            if (current_role() !== 'user') redirect_member('This action is available to members only.', 'error', 'settings');
            $newEmail = strtolower(trim($_POST['new_email'] ?? ''));
            $currentPassword = $_POST['current_password'] ?? '';
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL) || strlen($newEmail) > 255) redirect_member('Enter a valid email address.', 'error', 'settings');
            $accountStmt = $pdo->prepare("SELECT email, password FROM uinfo WHERE id = :id AND role = 'user' LIMIT 1");
            $accountStmt->execute([':id' => $userId]);
            $account = $accountStmt->fetch();
            if (!$account || !password_verify($currentPassword, $account['password'])) redirect_member('Your current password is incorrect.', 'error', 'settings');
            if (strcasecmp($newEmail, (string)$account['email']) === 0) redirect_member('That is already your account email.', 'error', 'settings');
            $duplicateEmail = $pdo->prepare('SELECT id FROM uinfo WHERE email = :email LIMIT 1');
            $duplicateEmail->execute([':email' => $newEmail]);
            if ($duplicateEmail->fetchColumn()) redirect_member('That email address is already in use.', 'error', 'settings');
            $baseUrl = rtrim((string)getenv('BLIFE_PUBLIC_BASE_URL'), '/');
            $fromEmail = (string)getenv('BLIFE_MAIL_FROM');
            if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL) || !preg_match('#^https://#i', $baseUrl)) redirect_member('Email verification is not configured. Contact the site administrator.', 'error', 'settings');
            $rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $pdo->prepare('DELETE FROM member_email_change_tokens WHERE user_id = :id')->execute([':id' => $userId]);
            $pdo->prepare('INSERT INTO member_email_change_tokens (user_id, new_email, token_hash, expires_at) VALUES (:id, :email, :token, DATE_ADD(NOW(), INTERVAL 30 MINUTE))')->execute([':id' => $userId, ':email' => $newEmail, ':token' => hash('sha256', $rawToken)]);
            $verifyUrl = $baseUrl . '/dashboard/verify_email.php?token=' . rawurlencode($rawToken);
            $headers = "From: BLife PH <{$fromEmail}>\r\n" . "Content-Type: text/plain; charset=UTF-8\r\n";
            if (!mail($newEmail, 'Verify your BLife PH email change', "Use this link within 30 minutes to confirm your new account email:\n\n{$verifyUrl}\n\nIf you did not request this change, you can ignore this message.", $headers)) {
                $pdo->prepare('DELETE FROM member_email_change_tokens WHERE user_id = :id')->execute([':id' => $userId]);
                redirect_member('We could not send the verification email. Please try again later.', 'error', 'settings');
            }
            redirect_member('A verification link was sent to the new email address.', 'success', 'settings');

        case 'revoke_session':
            if (current_role() !== 'user') redirect_member('This action is available to members only.', 'error', 'settings');
            $sessionRecordId = filter_var($_POST['session_record_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$sessionRecordId) redirect_member('Choose a valid session.', 'error', 'settings');
            $currentHash = hash('sha256', session_id());
            $sessionRevoke = $pdo->prepare('UPDATE member_sessions SET revoked_at = NOW() WHERE id = :session_id AND user_id = :user_id AND session_hash <> :current_hash AND revoked_at IS NULL');
            $sessionRevoke->execute([':session_id' => $sessionRecordId, ':user_id' => $userId, ':current_hash' => $currentHash]);
            redirect_member($sessionRevoke->rowCount() ? 'That session has been signed out.' : 'Your current session cannot be revoked here.', 'success', 'settings');

        case 'deactivate_account':
            if (current_role() !== 'user' || ($_POST['confirm_deactivation'] ?? '') !== '1') redirect_member('Confirm the deactivation request to continue.', 'error', 'settings');
            $currentPassword = $_POST['current_password'] ?? '';
            $checkPassword = $pdo->prepare("SELECT password FROM uinfo WHERE id = :id AND role = 'user'");
            $checkPassword->execute([':id' => $userId]);
            if (!password_verify($currentPassword, (string)$checkPassword->fetchColumn())) redirect_member('Your current password is incorrect.', 'error', 'settings');
            $pdo->prepare('UPDATE uinfo SET member_deactivated_at = NOW(), donor_available = 0 WHERE id = :id AND role = \'user\'')->execute([':id' => $userId]);
            $pdo->prepare('UPDATE member_sessions SET revoked_at = NOW() WHERE user_id = :id AND revoked_at IS NULL')->execute([':id' => $userId]);
            $_SESSION = [];
            session_destroy();
            header('Location: ../login.php?deactivated=1');
            exit;

        case 'request_account_deletion':
            if (current_role() !== 'user' || ($_POST['confirm_deletion'] ?? '') !== '1') redirect_member('Confirm the deletion request to continue.', 'error', 'settings');
            $currentPassword = $_POST['current_password'] ?? '';
            $checkPassword = $pdo->prepare("SELECT password FROM uinfo WHERE id = :id AND role = 'user'");
            $checkPassword->execute([':id' => $userId]);
            if (!password_verify($currentPassword, (string)$checkPassword->fetchColumn())) redirect_member('Your current password is incorrect.', 'error', 'settings');
            $pdo->beginTransaction();
            $pdo->prepare('SELECT id FROM uinfo WHERE id = :id AND role = \'user\' FOR UPDATE')->execute([':id' => $userId]);
            $pendingCheck = $pdo->prepare("SELECT id FROM member_account_deletion_requests WHERE user_id = :id AND status = 'pending' LIMIT 1");
            $pendingCheck->execute([':id' => $userId]);
            if (!$pendingCheck->fetchColumn()) {
                $pdo->prepare("INSERT INTO member_account_deletion_requests (user_id, status) VALUES (:id, 'pending')")->execute([':id' => $userId]);
            }
            $pdo->commit();
            redirect_member('Your deletion request was recorded for administrator review. Your account and related records have not been deleted.', 'success', 'settings');

        case 'post':
            $postType = $_POST['post_type'] ?? '';
            $content = trim($_POST['content'] ?? '');
            $eventDateValue = $_POST['event_date'] ?? '';
            if (!in_array($postType, ['community', 'donation', 'event', 'success_story'], true)) {
                redirect_member('Choose a valid post category.', 'error', 'community');
            }
            if ($content === '' || strlen($content) > 1000) {
                redirect_member(
                    'Your post must contain 1 to 1,000 characters.',
                    'error',
                    $postType === 'donation' ? 'donation-offers' : 'community'
                );
            }
            $eventDate = null;
            if ($postType === 'event') {
                $eventDate = DateTime::createFromFormat('!Y-m-d', $eventDateValue);
                if (!$eventDate || $eventDate->format('Y-m-d') !== $eventDateValue || $eventDateValue < date('Y-m-d')) {
                    redirect_member('Choose a valid event date today or later.', 'error', 'home');
                }
            }
            if ($postType === 'donation') {
                $donationBloodType = $_POST['donation_blood_type'] ?? '';
                $postLocation = blife_validate_post_location($_POST['donation_province'] ?? '', $_POST['donation_city'] ?? '', $_POST['donation_barangay'] ?? '', $_POST['donation_latitude'] ?? '', $_POST['donation_longitude'] ?? '', $philippineProvinces);
                $donationLocation = $postLocation ? $postLocation['city'] . ', ' . $postLocation['province'] : '';
                $availableDateValue = $_POST['donation_available_date'] ?? '';
                $availableTimeValue = $_POST['donation_available_time'] ?? '';
                if (!in_array($donationBloodType, $bloodTypes, true)) {
                    redirect_member('Select the blood type you are offering to donate.', 'error', 'donation-offers');
                }
                if (!$postLocation) {
                    redirect_member('Select a valid province, city or municipality, and map point in the Philippines.', 'error', 'donation-offers');
                }

                $availableDate = null;
                if ($availableDateValue !== '') {
                    $availableDate = DateTime::createFromFormat('!Y-m-d', $availableDateValue);
                    if (!$availableDate || $availableDate->format('Y-m-d') !== $availableDateValue || $availableDateValue < date('Y-m-d')) {
                        redirect_member('Choose a valid availability date today or later.', 'error', 'donation-offers');
                    }
                }
                $availableTime = null;
                if ($availableTimeValue !== '') {
                    $availableTime = DateTime::createFromFormat('!H:i', $availableTimeValue);
                    if (!$availableTime || $availableTime->format('H:i') !== $availableTimeValue) {
                        redirect_member('Choose a valid availability time.', 'error', 'donation-offers');
                    }
                }

                $donationColumns = $pdo->prepare(
                    "SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'community_posts'
                       AND COLUMN_NAME IN ('donation_blood_type', 'donation_location', 'donation_available_date')"
                );
                $donationColumns->execute();
                if ((int) $donationColumns->fetchColumn() !== 3) {
                    redirect_member('Donation offers are not enabled yet. Apply database_sql/donation_offers_migration.sql, then try again.', 'error', 'donation-offers');
                }
                $locationColumnStmt = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME IN ('donation_province','donation_city','donation_barangay','donation_latitude','donation_longitude')");
                if ((int) $locationColumnStmt->fetchColumn() !== 5) redirect_member('Apply database_sql/post_location_coordinates_migration.sql before publishing map locations.', 'error', 'donation-offers');

                $availabilityTimeColumn = $pdo->prepare(
                    "SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'community_posts'
                       AND COLUMN_NAME = 'donation_available_time'"
                );
                $availabilityTimeColumn->execute();
                if ((int) $availabilityTimeColumn->fetchColumn() > 0) {
                    $stmt = $pdo->prepare(
                        "INSERT INTO community_posts
                            (user_id, post_type, content, event_date, donation_blood_type, donation_location, donation_available_date, donation_available_time, donation_province, donation_city, donation_barangay, donation_latitude, donation_longitude)
                         VALUES (:user_id, 'donation', :content, NULL, :blood_type, :location, :available_date, :available_time, :province, :city, :barangay, :latitude, :longitude)"
                    );
                    $offerValues = [
                        ':user_id' => $userId,
                        ':content' => $content,
                        ':blood_type' => $donationBloodType,
                        ':location' => $donationLocation,
                        ':available_date' => $availableDate ? $availableDate->format('Y-m-d') : null,
                        ':available_time' => $availableTime ? $availableTime->format('H:i:s') : null
                        ,':province' => $postLocation['province'], ':city' => $postLocation['city'], ':barangay' => $postLocation['barangay'], ':latitude' => $postLocation['latitude'], ':longitude' => $postLocation['longitude']
                    ];
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO community_posts
                            (user_id, post_type, content, event_date, donation_blood_type, donation_location, donation_available_date, donation_province, donation_city, donation_barangay, donation_latitude, donation_longitude)
                         VALUES (:user_id, 'donation', :content, NULL, :blood_type, :location, :available_date, :province, :city, :barangay, :latitude, :longitude)"
                    );
                    $offerValues = [
                        ':user_id' => $userId,
                        ':content' => $content,
                        ':blood_type' => $donationBloodType,
                        ':location' => $donationLocation,
                        ':available_date' => $availableDate ? $availableDate->format('Y-m-d') : null
                        ,':province' => $postLocation['province'], ':city' => $postLocation['city'], ':barangay' => $postLocation['barangay'], ':latitude' => $postLocation['latitude'], ':longitude' => $postLocation['longitude']
                    ];
                }
                $stmt->execute($offerValues);
                redirect_member('Your donation offer has been published to the community.', 'success', 'donation-offers');
            }
            $stmt = $pdo->prepare(
                'INSERT INTO community_posts (user_id, post_type, content, event_date)
                 VALUES (:user_id, :post_type, :content, :event_date)'
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':post_type' => $postType,
                ':content' => $content,
                ':event_date' => $eventDate ? $eventDate->format('Y-m-d') : null
            ]);
            redirect_member('Your post has been shared with the community.', 'success', 'home');

        case 'post_interaction':
            $postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
            $interaction = $_POST['interaction'] ?? '';
            if (!$postId || !in_array($interaction, ['like', 'save', 'report'], true)) {
                redirect_member('Choose a valid post action.', 'error', 'home');
            }

            $pdo->beginTransaction();
            $postStmt = $pdo->prepare('SELECT id, user_id FROM community_posts WHERE id = :id FOR UPDATE');
            $postStmt->execute([':id' => $postId]);
            $targetPost = $postStmt->fetch();
            if (!$targetPost) {
                $pdo->rollBack();
                redirect_member('That post is no longer available.', 'error', 'home');
            }
            if ($interaction === 'report' && (int) $targetPost['user_id'] === $userId) {
                $pdo->rollBack();
                redirect_member('You cannot report your own post.', 'error', 'home');
            }

            $existingStmt = $pdo->prepare(
                'SELECT post_id FROM post_interactions
                 WHERE post_id = :post_id AND user_id = :user_id AND interaction = :interaction'
            );
            $existingStmt->execute([
                ':post_id' => $postId,
                ':user_id' => $userId,
                ':interaction' => $interaction
            ]);
            $exists = (bool) $existingStmt->fetch();

            if ($interaction === 'report') {
                if (!$exists) {
                    $reportReason = trim($_POST['report_reason'] ?? '');
                    if ($reportReason === '' || strlen($reportReason) > 255) {
                        $pdo->rollBack();
                        redirect_member('Select or enter a reason for reporting this post.', 'error', 'home');
                    }
                    $reasonColumnCheck = $pdo->prepare(
                        "SELECT COUNT(*) FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'post_interactions' AND COLUMN_NAME = 'report_reason'"
                    );
                    $reasonColumnCheck->execute();
                    if ((int) $reasonColumnCheck->fetchColumn() > 0) {
                        $insertStmt = $pdo->prepare(
                            'INSERT INTO post_interactions (post_id, user_id, interaction, report_reason)
                             VALUES (:post_id, :user_id, :interaction, :reason)'
                        );
                        $insertStmt->execute([
                            ':post_id' => $postId,
                            ':user_id' => $userId,
                            ':interaction' => $interaction,
                            ':reason' => $reportReason
                        ]);
                    } else {
                        $insertStmt = $pdo->prepare(
                            'INSERT INTO post_interactions (post_id, user_id, interaction)
                             VALUES (:post_id, :user_id, :interaction)'
                        );
                        $insertStmt->execute([':post_id' => $postId, ':user_id' => $userId, ':interaction' => $interaction]);
                    }
                }
            } elseif ($exists) {
                $deleteStmt = $pdo->prepare(
                    'DELETE FROM post_interactions
                     WHERE post_id = :post_id AND user_id = :user_id AND interaction = :interaction'
                );
                $deleteStmt->execute([':post_id' => $postId, ':user_id' => $userId, ':interaction' => $interaction]);
            } else {
                $insertStmt = $pdo->prepare(
                    'INSERT INTO post_interactions (post_id, user_id, interaction)
                     VALUES (:post_id, :user_id, :interaction)'
                );
                $insertStmt->execute([':post_id' => $postId, ':user_id' => $userId, ':interaction' => $interaction]);
            }
            if (
                !$exists &&
                in_array($interaction, ['like', 'save'], true) &&
                (int) $targetPost['user_id'] !== $userId
            ) {
                $notificationType = $interaction === 'like' ? 'post_like' : 'post_save';
                $notificationMessage = $interaction === 'like' ? 'liked your post.' : 'saved your post.';
                $notifyStmt = $pdo->prepare(
                    'INSERT INTO member_notifications (user_id, actor_id, notification_type, target_id, message)
                     SELECT id, :actor_id, :notification_type, :target_id, :message
                     FROM uinfo
                     WHERE id = :user_id AND notify_post_comments = 1'
                );
                $notifyStmt->execute([
                    ':actor_id' => $userId,
                    ':notification_type' => $notificationType,
                    ':target_id' => $postId,
                    ':message' => $notificationMessage,
                    ':user_id' => (int) $targetPost['user_id']
                ]);
            }
            $pdo->commit();
            $message = $interaction === 'report'
                ? 'The post has been reported for administrator review.'
                : ($exists ? ($interaction === 'like' ? 'Like removed.' : 'Post removed from saved items.') : ($interaction === 'like' ? 'Post liked.' : 'Post saved for later.'));
            redirect_member($message, 'success', member_return_page());

        case 'post_share':
            $postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$postId) {
                redirect_member('Choose a valid post to share.', 'error', 'home');
            }
            $postStmt = $pdo->prepare('SELECT user_id FROM community_posts WHERE id = :id LIMIT 1');
            $postStmt->execute([':id' => $postId]);
            $targetPost = $postStmt->fetch();
            if (!$targetPost) {
                redirect_member('That post is no longer available.', 'error', 'home');
            }
            if ((int) $targetPost['user_id'] !== $userId) {
                $notifyStmt = $pdo->prepare(
                    'INSERT INTO member_notifications (user_id, actor_id, notification_type, target_id, message)
                     SELECT id, :actor_id, \'post_share\', :target_id, :message
                     FROM uinfo
                     WHERE id = :user_id AND notify_post_comments = 1'
                );
                $notifyStmt->execute([
                    ':actor_id' => $userId,
                    ':target_id' => $postId,
                    ':message' => 'shared your post.',
                    ':user_id' => (int) $targetPost['user_id']
                ]);
            }
            if (($_POST['async'] ?? '') === '1') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true], JSON_THROW_ON_ERROR);
                exit;
            }
            redirect_member('The post share has been recorded.', 'success', member_return_page());

        case 'delete_post':
            $postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$postId) {
                redirect_member('Choose a valid post to delete.', 'error', 'my-posts');
            }
            $ownedPostStmt = $pdo->prepare('SELECT post_type FROM community_posts WHERE id = :id AND user_id = :user_id LIMIT 1');
            $ownedPostStmt->execute([':id' => $postId, ':user_id' => $userId]);
            $ownedPostType = $ownedPostStmt->fetchColumn();
            if ($ownedPostType === false) {
                redirect_member('That post could not be deleted because it is not yours or is no longer available.', 'error', 'my-posts');
            }
            if ($ownedPostType === 'donation') {
                $offerMessagesTable = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donation_offer_messages'");
                $offerMessagesTable->execute();
                if ((int) $offerMessagesTable->fetchColumn() > 0) {
                    $activeOfferStmt = $pdo->prepare('SELECT COUNT(*) FROM donation_offer_messages WHERE post_id = :post_id');
                    $activeOfferStmt->execute([':post_id' => $postId]);
                    if ((int) $activeOfferStmt->fetchColumn() > 0) {
                        redirect_member('This offer has conversation history and cannot be deleted. Mark it unavailable or completed to preserve those messages.', 'error', 'my-posts');
                    }
                }
            }
            $stmt = $pdo->prepare('DELETE FROM community_posts WHERE id = :id AND user_id = :user_id');
            $stmt->execute([':id' => $postId, ':user_id' => $userId]);
            if ($stmt->rowCount() !== 1) {
                redirect_member('That post could not be deleted because it is not yours or is no longer available.', 'error', 'my-posts');
            }
            $notifyStmt = $pdo->prepare(
                "DELETE FROM member_notifications
                 WHERE user_id = :user_id
                   AND notification_type IN ('post_like', 'post_comment', 'post_share', 'post_save')
                   AND target_id = :post_id"
            );
            $notifyStmt->execute([':user_id' => $userId, ':post_id' => $postId]);
            redirect_member('Your post and its comments have been deleted.', 'success', 'my-posts');

        case 'edit_post':
            $postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
            $content = trim($_POST['content'] ?? '');
            if (!$postId || $content === '' || strlen($content) > 1000) {
                redirect_member('Post content must contain 1 to 1,000 characters.', 'error', 'my-posts');
            }
            $ownedPostStmt = $pdo->prepare('SELECT post_type FROM community_posts WHERE id = :id AND user_id = :user_id LIMIT 1');
            $ownedPostStmt->execute([':id' => $postId, ':user_id' => $userId]);
            $postType = $ownedPostStmt->fetchColumn();
            if ($postType === false) {
                redirect_member('That post is not available to edit.', 'error', 'my-posts');
            }
            if ($postType === 'donation') {
                $bloodType = $_POST['donation_blood_type'] ?? '';
                $postLocation = blife_validate_post_location($_POST['donation_province'] ?? '', $_POST['donation_city'] ?? '', $_POST['donation_barangay'] ?? '', $_POST['donation_latitude'] ?? '', $_POST['donation_longitude'] ?? '', $philippineProvinces);
                $location = $postLocation ? $postLocation['city'] . ', ' . $postLocation['province'] : '';
                $availableDate = $_POST['donation_available_date'] ?? '';
                $availableTime = $_POST['donation_available_time'] ?? '';
                if (!in_array($bloodType, $bloodTypes, true) || !$postLocation) {
                    redirect_member('Choose a valid blood type, province, city or municipality, and map point.', 'error', 'my-posts');
                }
                $locationColumnCount = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME IN ('donation_province','donation_city','donation_barangay','donation_latitude','donation_longitude')")->fetchColumn();
                if ($locationColumnCount !== 5) redirect_member('Apply database_sql/post_location_coordinates_migration.sql before saving map locations.', 'error', 'my-posts');
                $availableDateObject = $availableDate !== '' ? DateTime::createFromFormat('!Y-m-d', $availableDate) : null;
                if ($availableDate !== '' && (!$availableDateObject || $availableDateObject->format('Y-m-d') !== $availableDate)) {
                    redirect_member('Choose a valid availability date.', 'error', 'my-posts');
                }
                if ($availableTime !== '' && (!DateTime::createFromFormat('!H:i', $availableTime) || DateTime::createFromFormat('!H:i', $availableTime)->format('H:i') !== $availableTime)) redirect_member('Choose a valid availability time.', 'error', 'my-posts');
                $hasTime = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'community_posts' AND COLUMN_NAME = 'donation_available_time'")->fetchColumn() > 0;
                $sql = 'UPDATE community_posts SET content = :content, donation_blood_type = :blood_type, donation_location = :location, donation_available_date = :available_date, donation_province=:province, donation_city=:city, donation_barangay=:barangay, donation_latitude=:latitude, donation_longitude=:longitude';
                if ($hasTime) $sql .= ', donation_available_time = :available_time';
                $sql .= ' WHERE id = :id AND user_id = :user_id AND post_type = \'donation\'';
                $editStmt = $pdo->prepare($sql);
                $values = [':content' => $content, ':blood_type' => $bloodType, ':location' => $location, ':available_date' => $availableDate !== '' ? $availableDate : null, ':province'=>$postLocation['province'], ':city'=>$postLocation['city'], ':barangay'=>$postLocation['barangay'], ':latitude'=>$postLocation['latitude'], ':longitude'=>$postLocation['longitude'], ':id' => $postId, ':user_id' => $userId];
                if ($hasTime) $values[':available_time'] = $availableTime !== '' ? $availableTime : null;
                $editStmt->execute($values);
            } else {
                $eventDate = null;
                if ($postType === 'event') {
                    $eventDateValue = $_POST['event_date'] ?? '';
                    $eventDate = DateTime::createFromFormat('!Y-m-d', $eventDateValue);
                    if (!$eventDate || $eventDate->format('Y-m-d') !== $eventDateValue) {
                        redirect_member('Choose a valid event date.', 'error', 'my-posts');
                    }
                }
                $editStmt = $pdo->prepare('UPDATE community_posts SET content = :content, event_date = :event_date WHERE id = :id AND user_id = :user_id');
                $editStmt->execute([':content' => $content, ':event_date' => $eventDate ? $eventDate->format('Y-m-d') : null, ':id' => $postId, ':user_id' => $userId]);
            }
            redirect_member('Your post has been updated.', 'success', $postType === 'donation' ? 'donation-offers' : 'home', $postId);

        case 'donation_status':
            $postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
            $status = $_POST['status'] ?? '';
            if (!$postId || !in_array($status, ['available', 'unavailable', 'completed'], true)) {
                redirect_member('Choose a valid donation offer status.', 'error', 'my-posts');
            }
            $statusColumn = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'community_posts' AND COLUMN_NAME = 'donation_status'");
            $statusColumn->execute();
            if ((int) $statusColumn->fetchColumn() === 0) {
                redirect_member('Donation status support is not installed. Apply database_sql/donation_offer_status_migration.sql.', 'error', 'my-posts');
            }
            $ownedOffer = $pdo->prepare("SELECT id FROM community_posts WHERE id = :id AND user_id = :user_id AND post_type = 'donation'");
            $ownedOffer->execute([':id' => $postId, ':user_id' => $userId]);
            if (!$ownedOffer->fetchColumn()) redirect_member('That donation offer is not yours or is no longer available.', 'error', 'my-posts');
            $statusStmt = $pdo->prepare("UPDATE community_posts SET donation_status = :status WHERE id = :id AND user_id = :user_id AND post_type = 'donation'");
            $statusStmt->execute([':status' => $status, ':id' => $postId, ':user_id' => $userId]);
            redirect_member('Donation offer status updated.', 'success', 'my-posts', $postId);

        case 'comment':
            $postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
            $comment = trim($_POST['comment'] ?? '');
            if (!$postId || $comment === '' || strlen($comment) > 500) {
                redirect_member('Comments must contain 1 to 500 characters.', 'error', 'community');
            }
            $stmt = $pdo->prepare('SELECT id, user_id FROM community_posts WHERE id = :id');
            $stmt->execute([':id' => $postId]);
            $targetPost = $stmt->fetch();
            if (!$targetPost) {
                redirect_member('That community post is no longer available.', 'error', 'community');
            }
            $stmt = $pdo->prepare(
                'INSERT INTO post_comments (post_id, user_id, comment)
                 VALUES (:post_id, :user_id, :comment)'
            );
            $stmt->execute([
                ':post_id' => $postId,
                ':user_id' => $userId,
                ':comment' => $comment
            ]);
            if ((int) $targetPost['user_id'] !== $userId) {
                $notifyStmt = $pdo->prepare(
                    'INSERT INTO member_notifications (user_id, actor_id, notification_type, target_id, message)
                     SELECT id, :actor_id, \'post_comment\', :target_id, :message
                     FROM uinfo
                     WHERE id = :user_id AND notify_post_comments = 1'
                );
                $notifyStmt->execute([
                    ':actor_id' => $userId,
                    ':target_id' => $postId,
                    ':message' => 'commented on your post.',
                    ':user_id' => (int) $targetPost['user_id']
                ]);
            }
            redirect_member('Your comment has been added.', 'success', member_return_page());

        case 'request':
            $bloodType = $_POST['blood_type'] ?? '';
            $units = filter_var($_POST['units'] ?? null, FILTER_VALIDATE_INT);
            // Keep the legacy non-null database column compatible while the form no longer collects a facility.
            $hospital = '';
            $location = trim($_POST['location'] ?? '');
            $postLocation = blife_validate_post_location($location, $_POST['request_city'] ?? '', $_POST['request_barangay'] ?? '', $_POST['request_latitude'] ?? '', $_POST['request_longitude'] ?? '', $philippineProvinces);
            $urgency = $_POST['urgency'] ?? '';
            $neededBy = $_POST['needed_by'] ?? '';
            $details = trim($_POST['details'] ?? '');
            $date = DateTime::createFromFormat('!Y-m-d', $neededBy);
            $dateIsValid = $date && $date->format('Y-m-d') === $neededBy;

            if (!in_array($bloodType, $bloodTypes, true)) {
                redirect_member('Select a valid blood type for this request.', 'error', 'requests');
            }
            if (!in_array($urgency, ['routine', 'urgent', 'emergency'], true)) {
                redirect_member('Select a valid urgency level for this request.', 'error', 'requests');
            }
            if (!$units || $units < 1 || $units > 20) {
                redirect_member('Requested units must be between 1 and 20.', 'error', 'requests');
            }
            if (!$postLocation) {
                redirect_member('Select a valid province, city or municipality, and map point in the Philippines.', 'error', 'requests');
            }
            $requestLocationColumns = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='blood_requests' AND COLUMN_NAME IN ('city','barangay','latitude','longitude')");
            if ((int) $requestLocationColumns->fetchColumn() !== 4) redirect_member('Apply database_sql/post_location_coordinates_migration.sql before publishing map locations.', 'error', 'requests');
            if (!$dateIsValid || $neededBy < date('Y-m-d')) {
                redirect_member('Choose a valid date today or later.', 'error', 'requests');
            }
            if (strlen($details) > 1000) {
                redirect_member('Request details must be 1,000 characters or fewer.', 'error', 'requests');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO blood_requests
                    (requester_id, blood_type, units, hospital, location, city, barangay, latitude, longitude, needed_by, urgency, details)
                 VALUES (:requester_id, :blood_type, :units, :hospital, :location, :city, :barangay, :latitude, :longitude, :needed_by, :urgency, :details)'
            );
            $stmt->execute([
                ':requester_id' => $userId,
                ':blood_type' => $bloodType,
                ':units' => $units,
                ':hospital' => $hospital,
                ':location' => $location,
                ':city' => $postLocation['city'], ':barangay' => $postLocation['barangay'], ':latitude' => $postLocation['latitude'], ':longitude' => $postLocation['longitude'],
                ':needed_by' => $neededBy,
                ':urgency' => $urgency,
                ':details' => $details
            ]);
            redirect_member('Your blood request is now visible to the community.', 'success', 'requests');

        case 'respond':
            $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
            $message = trim($_POST['message'] ?? '');
            if (!$requestId || strlen($message) > 500) {
                redirect_member('Enter a valid request response of up to 500 characters.', 'error', 'requests');
            }
            $stmt = $pdo->prepare(
                "SELECT id, urgency FROM blood_requests
                 WHERE id = :id AND requester_id <> :user_id AND status = 'open' AND needed_by >= CURDATE()"
            );
            $stmt->execute([':id' => $requestId, ':user_id' => $userId]);
            $request = $stmt->fetch();
            if (!$request) {
                redirect_member('That request is closed or is not available to respond to.', 'error', 'requests');
            }
            $stmt = $pdo->prepare(
                'SELECT id FROM request_responses WHERE request_id = :request_id AND user_id = :user_id'
            );
            $stmt->execute([':request_id' => $requestId, ':user_id' => $userId]);
            if ($stmt->fetch()) {
                redirect_member('You have already responded to that request.', 'error', 'requests');
            }
            $stmt = $pdo->prepare(
                "INSERT INTO request_responses (request_id, user_id, message, status)
                 VALUES (:request_id, :user_id, :message, 'pending')"
            );
            $stmt->execute([
                ':request_id' => $requestId,
                ':user_id' => $userId,
                ':message' => $message
            ]);
            $notifyStmt = $pdo->prepare(
                'INSERT INTO member_notifications (user_id, actor_id, notification_type, target_id, message)
                 SELECT u.id, :actor_id, \'request_response\', :target_id, :message
                 FROM uinfo u
                 JOIN blood_requests br ON br.requester_id = u.id
                 WHERE br.id = :request_id AND u.notify_request_responses = 1'
            );
            $notifyStmt->execute([
                ':actor_id' => $userId,
                ':target_id' => $requestId,
                ':message' => 'offered to help with your ' . ucfirst((string) $request['urgency']) . ' blood request.',
                ':request_id' => $requestId
            ]);
            redirect_member('Your offer has been sent to the requester for review.', 'success', 'requests');

        case 'accept_request_response':
            $responseId = filter_var($_POST['response_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$responseId) {
                redirect_member('Choose a valid offer to accept.', 'error', 'my-requests');
            }

            $pdo->beginTransaction();
            $offerStmt = $pdo->prepare(
                'SELECT rr.id, rr.status AS approval_status, rr.user_id AS responder_id,
                        br.id AS request_id, br.status AS request_status, br.needed_by, br.urgency
                 FROM request_responses rr
                 JOIN blood_requests br ON br.id = rr.request_id
                 WHERE rr.id = :response_id AND br.requester_id = :requester_id
                 FOR UPDATE'
            );
            $offerStmt->execute([':response_id' => $responseId, ':requester_id' => $userId]);
            $offer = $offerStmt->fetch();
            if (!$offer) {
                $pdo->rollBack();
                redirect_member('That offer is not attached to one of your blood requests.', 'error', 'my-requests');
            }
            if ($offer['approval_status'] !== 'pending') {
                $pdo->rollBack();
                redirect_member('That offer has already been reviewed.', 'info', 'my-requests');
            }
            if ($offer['request_status'] !== 'open' || $offer['needed_by'] < date('Y-m-d')) {
                $pdo->rollBack();
                redirect_member('Offers can only be accepted for an open, current request.', 'error', 'my-requests');
            }

            $acceptStmt = $pdo->prepare(
                "UPDATE request_responses SET status = 'accepted'
                 WHERE id = :response_id AND status = 'pending'"
            );
            $acceptStmt->execute([':response_id' => $responseId]);
            if ($acceptStmt->rowCount() !== 1) {
                $pdo->rollBack();
                redirect_member('That offer has already been reviewed.', 'info', 'my-requests');
            }

            $notifyStmt = $pdo->prepare(
                'INSERT INTO member_notifications (user_id, actor_id, notification_type, target_id, message)
                 SELECT id, :actor_id, \'request_response\', :target_id, :message
                 FROM uinfo
                 WHERE id = :recipient_id AND notify_request_responses = 1'
            );
            $notifyStmt->execute([
                ':actor_id' => $userId,
                ':target_id' => (int) $offer['request_id'],
                ':message' => 'accepted your offer to help with a ' . ucfirst((string) $offer['urgency']) . ' blood request. You can now message privately.',
                ':recipient_id' => (int) $offer['responder_id']
            ]);
            $pdo->commit();
            redirect_member('Offer accepted. Private messaging is now available.', 'success', 'my-requests');

        case 'request_status':
            $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
            $status = $_POST['status'] ?? '';
            if (!$requestId || !in_array($status, ['fulfilled', 'cancelled'], true)) {
                redirect_member('Choose a valid request status.', 'error', 'requests');
            }
            $stmt = $pdo->prepare(
                "UPDATE blood_requests SET status = :status
                 WHERE id = :id AND requester_id = :user_id AND status = 'open' AND needed_by >= CURDATE()"
            );
            $stmt->execute([
                ':status' => $status,
                ':id' => $requestId,
                ':user_id' => $userId
            ]);
            if ($stmt->rowCount() !== 1) {
                redirect_member('Only your open requests can be updated.', 'error', 'requests');
            }
            redirect_member('Your request status has been updated.', 'success', 'requests');

        case 'edit_request':
            $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
            $bloodType = $_POST['blood_type'] ?? '';
            $units = filter_var($_POST['units'] ?? null, FILTER_VALIDATE_INT);
            $location = trim($_POST['location'] ?? '');
            $postLocation = blife_validate_post_location($location, $_POST['request_city'] ?? '', $_POST['request_barangay'] ?? '', $_POST['request_latitude'] ?? '', $_POST['request_longitude'] ?? '', $philippineProvinces);
            $neededBy = $_POST['needed_by'] ?? '';
            $urgency = $_POST['urgency'] ?? '';
            $details = trim($_POST['details'] ?? '');
            $date = DateTime::createFromFormat('!Y-m-d', $neededBy);
            if (!$requestId || !in_array($bloodType, $bloodTypes, true) || !$units || $units < 1 || $units > 20 || !$postLocation || !$date || $date->format('Y-m-d') !== $neededBy || $neededBy < date('Y-m-d') || !in_array($urgency, ['routine', 'urgent', 'emergency'], true) || strlen($details) > 1000) {
                redirect_member('Review the blood request fields and try again.', 'error', 'my-requests');
            }
            $requestLocationColumns = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='blood_requests' AND COLUMN_NAME IN ('city','barangay','latitude','longitude')");
            if ((int) $requestLocationColumns->fetchColumn() !== 4) redirect_member('Apply database_sql/post_location_coordinates_migration.sql before saving map locations.', 'error', 'my-requests');
            $hasAcceptedResponse = $pdo->prepare("SELECT COUNT(*) FROM request_responses rr JOIN blood_requests br ON br.id = rr.request_id WHERE br.id = :id AND br.requester_id = :user_id AND rr.status = 'accepted'");
            $hasAcceptedResponse->execute([':id' => $requestId, ':user_id' => $userId]);
            if ((int) $hasAcceptedResponse->fetchColumn() > 0) redirect_member('This request has an accepted donor conversation, so its details are locked to preserve that transaction.', 'error', 'my-requests');
            $editableRequest = $pdo->prepare("SELECT id FROM blood_requests WHERE id = :id AND requester_id = :user_id AND status = 'open'");
            $editableRequest->execute([':id' => $requestId, ':user_id' => $userId]);
            if (!$editableRequest->fetchColumn()) redirect_member('Only your open requests can be edited.', 'error', 'my-requests');
            $editRequestStmt = $pdo->prepare("UPDATE blood_requests SET blood_type = :blood_type, units = :units, location = :location, city=:city, barangay=:barangay, latitude=:latitude, longitude=:longitude, needed_by = :needed_by, urgency = :urgency, details = :details WHERE id = :id AND requester_id = :user_id AND status = 'open'");
            $editRequestStmt->execute([':blood_type' => $bloodType, ':units' => $units, ':location' => $location, ':city'=>$postLocation['city'], ':barangay'=>$postLocation['barangay'], ':latitude'=>$postLocation['latitude'], ':longitude'=>$postLocation['longitude'], ':needed_by' => $neededBy, ':urgency' => $urgency, ':details' => $details, ':id' => $requestId, ':user_id' => $userId]);
            redirect_member('Your blood request has been updated.', 'success', 'my-requests');

        case 'delete_request':
            $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$requestId) redirect_member('Choose a valid blood request.', 'error', 'my-requests');
            $responseCountStmt = $pdo->prepare('SELECT COUNT(*) FROM request_responses rr JOIN blood_requests br ON br.id = rr.request_id WHERE br.id = :id AND br.requester_id = :user_id');
            $responseCountStmt->execute([':id' => $requestId, ':user_id' => $userId]);
            if ((int) $responseCountStmt->fetchColumn() > 0) redirect_member('This request has donor responses or conversations and cannot be deleted. Close it as fulfilled or cancelled to preserve its history.', 'error', 'my-requests');
            $deleteRequestStmt = $pdo->prepare('DELETE FROM blood_requests WHERE id = :id AND requester_id = :user_id');
            $deleteRequestStmt->execute([':id' => $requestId, ':user_id' => $userId]);
            if ($deleteRequestStmt->rowCount() !== 1) redirect_member('That request is not yours or is no longer available.', 'error', 'my-requests');
            redirect_member('Your blood request has been deleted.', 'success', 'my-requests');

        case 'request_message':
            $responseId = filter_var($_POST['response_id'] ?? null, FILTER_VALIDATE_INT);
            $message = trim($_POST['message'] ?? '');
            $returnToMessages = ($_POST['return_page'] ?? '') === 'messages';
            if (!$responseId || $message === '' || strlen($message) > 1000) {
                redirect_member('Enter a reply of 1 to 1,000 characters.', 'error', $returnToMessages ? 'messages' : 'my-requests', null, $returnToMessages && $responseId ? 'request-' . $responseId : null);
            }

            $threadStmt = $pdo->prepare(
                'SELECT rr.id, rr.status AS approval_status, rr.user_id AS responder_id, br.id AS request_id,
                        br.requester_id, br.status, br.needed_by, br.urgency
                 FROM request_responses rr
                 JOIN blood_requests br ON br.id = rr.request_id
                 WHERE rr.id = :response_id
                   AND (br.requester_id = :requester_id OR rr.user_id = :responder_id)
                 LIMIT 1'
            );
            $threadStmt->execute([
                ':response_id' => $responseId,
                ':requester_id' => $userId,
                ':responder_id' => $userId
            ]);
            $thread = $threadStmt->fetch();
            if (!$thread) {
                redirect_member('That conversation is not available to your account.', 'error', $returnToMessages ? 'messages' : 'my-requests', null, $returnToMessages ? 'request-' . $responseId : null);
            }
            if ($thread['approval_status'] !== 'accepted') {
                redirect_member('The requester must accept your offer before private messaging is available.', 'error', (int) $thread['requester_id'] === $userId ? 'my-requests' : 'responded');
            }
            if ($thread['status'] !== 'open' || $thread['needed_by'] < date('Y-m-d')) {
                redirect_member('This blood request is closed, so its conversation can no longer receive replies.', 'error', $thread['requester_id'] == $userId ? 'my-requests' : 'responded');
            }

            $recipientId = (int) $thread['requester_id'] === $userId
                ? (int) $thread['responder_id']
                : (int) $thread['requester_id'];
            $pdo->beginTransaction();
            $messageStmt = $pdo->prepare(
                'INSERT INTO request_response_messages (response_id, sender_id, message)
                 VALUES (:response_id, :sender_id, :message)'
            );
            $messageStmt->execute([
                ':response_id' => $responseId,
                ':sender_id' => $userId,
                ':message' => $message
            ]);
            $notifyStmt = $pdo->prepare(
                'INSERT INTO member_notifications (user_id, actor_id, notification_type, target_id, message)
                 SELECT id, :actor_id, \'request_message\', :target_id, :message
                 FROM uinfo
                 WHERE id = :recipient_id AND notify_request_responses = 1'
            );
            $notifyStmt->execute([
                ':actor_id' => $userId,
                ':target_id' => (int) $thread['request_id'],
                ':message' => 'sent you a private message about your ' . ucfirst((string) $thread['urgency']) . ' blood request.',
                ':recipient_id' => $recipientId
            ]);
            $pdo->commit();
            if ($returnToMessages) {
                redirect_member('Your private reply has been sent.', 'success', 'messages', null, 'request-' . $responseId);
            }
            redirect_member('Your private reply has been sent.', 'success', (int) $thread['requester_id'] === $userId ? 'my-requests' : 'responded');

        case 'donation_offer_message':
            $postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
            $seekerId = filter_var($_POST['seeker_id'] ?? null, FILTER_VALIDATE_INT);
            $message = trim($_POST['message'] ?? '');
            $urgency = $_POST['urgency'] ?? '';
            $returnToMessages = ($_POST['return_page'] ?? '') === 'messages';
            if (!$postId || !$seekerId || $message === '' || strlen($message) > 1000) {
                redirect_member('Enter a private offer message of 1 to 1,000 characters.', 'error', $returnToMessages ? 'messages' : 'donation-offers', null, $returnToMessages && $postId && $seekerId ? 'offer-' . $postId . '-' . $seekerId : null);
            }

            $offerStmt = $pdo->prepare(
                "SELECT id, user_id FROM community_posts
                 WHERE id = :id AND post_type = 'donation'
                   AND (donation_available_date IS NULL OR donation_available_date >= CURDATE())
                 LIMIT 1"
            );
            $offerStmt->execute([':id' => $postId]);
            $offer = $offerStmt->fetch();
            if (!$offer) {
                redirect_member('That donation offer is no longer available.', 'error', $returnToMessages ? 'messages' : 'donation-offers', null, $returnToMessages ? 'offer-' . $postId . '-' . $seekerId : null);
            }

            $offerOwnerId = (int) $offer['user_id'];
            if ($offerOwnerId === $seekerId || ($userId !== $offerOwnerId && $userId !== $seekerId)) {
                redirect_member('This private conversation is not available to your account.', 'error', $returnToMessages ? 'messages' : 'donation-offers');
            }
            $threadStmt = $pdo->prepare(
                'SELECT 1 FROM donation_offer_messages
                 WHERE post_id = :post_id AND seeker_id = :seeker_id LIMIT 1'
            );
            $threadStmt->execute([':post_id' => $postId, ':seeker_id' => $seekerId]);
            $threadExists = (bool) $threadStmt->fetchColumn();
            if ($userId === $offerOwnerId && !$threadExists) {
                redirect_member('You can reply only to a member who has contacted you about this offer.', 'error', $returnToMessages ? 'messages' : 'donation-offers');
            }
            if ($userId === $seekerId && !$threadExists && !in_array($urgency, ['routine', 'urgent', 'emergency'], true)) {
                redirect_member('Select an urgency level before contacting the donor.', 'error', $returnToMessages ? 'messages' : 'donation-offers', $returnToMessages ? null : $postId, $returnToMessages ? 'offer-' . $postId . '-' . $seekerId : null);
            }

            $recipientId = $userId === $offerOwnerId ? $seekerId : $offerOwnerId;
            $pdo->beginTransaction();
            $messageStmt = $pdo->prepare(
                'INSERT INTO donation_offer_messages (post_id, seeker_id, sender_id, message, urgency)
                 VALUES (:post_id, :seeker_id, :sender_id, :message, :urgency)'
            );
            $messageStmt->execute([
                ':post_id' => $postId,
                ':seeker_id' => $seekerId,
                ':sender_id' => $userId,
                ':message' => $message,
                ':urgency' => $userId === $seekerId && !$threadExists ? $urgency : null
            ]);
            $notifyStmt = $pdo->prepare(
                'INSERT INTO member_notifications (user_id, actor_id, notification_type, target_id, message)
                 SELECT id, :actor_id, \'donation_offer_message\', :target_id, :message
                 FROM uinfo
                 WHERE id = :recipient_id AND notify_post_comments = 1'
            );
            $notifyStmt->execute([
                ':actor_id' => $userId,
                ':target_id' => $postId,
                ':message' => 'sent you a private message about a donation offer.',
                ':recipient_id' => $recipientId
            ]);
            $pdo->commit();
            if ($returnToMessages) {
                redirect_member('Your private message has been sent.', 'success', 'messages', null, 'offer-' . $postId . '-' . $seekerId);
            }
            redirect_member('Your private message has been sent.', 'success', 'donation-offers', $postId);

        case 'message_report':
            $messageType = $_POST['message_type'] ?? '';
            $messageId = filter_var($_POST['message_id'] ?? null, FILTER_VALIDATE_INT);
            $reason = trim($_POST['reason'] ?? '');
            if (!$messageId || !in_array($messageType, ['donation_offer', 'request_response'], true) || $reason === '' || strlen($reason) > 255) {
                redirect_member('Choose a message and provide a report reason of up to 255 characters.', 'error', 'messages');
            }

            if ($messageType === 'donation_offer') {
                $messageStmt = $pdo->prepare(
                    'SELECT m.id, m.sender_id, m.post_id, m.seeker_id, cp.user_id AS donor_id
                     FROM donation_offer_messages m
                     JOIN community_posts cp ON cp.id = m.post_id
                     WHERE m.id = :message_id AND cp.post_type = \'donation\'
                       AND (cp.user_id = :donor_viewer OR m.seeker_id = :seeker_viewer)
                     LIMIT 1'
                );
                $messageStmt->execute([
                    ':message_id' => $messageId,
                    ':donor_viewer' => $userId,
                    ':seeker_viewer' => $userId
                ]);
                $reportTarget = $messageStmt->fetch();
                if (!$reportTarget || (int) $reportTarget['sender_id'] === $userId) {
                    redirect_member('You can report only a message from another participant in your conversation.', 'error', 'messages');
                }
                $threadKey = 'offer-' . (int) $reportTarget['post_id'] . '-' . (int) $reportTarget['seeker_id'];
            } else {
                $messageStmt = $pdo->prepare(
                    'SELECT m.id, m.sender_id, rr.id AS response_id
                     FROM request_response_messages m
                     JOIN request_responses rr ON rr.id = m.response_id
                     JOIN blood_requests br ON br.id = rr.request_id
                     WHERE m.id = :message_id
                       AND (br.requester_id = :requester_viewer OR rr.user_id = :responder_viewer)
                     LIMIT 1'
                );
                $messageStmt->execute([
                    ':message_id' => $messageId,
                    ':requester_viewer' => $userId,
                    ':responder_viewer' => $userId
                ]);
                $reportTarget = $messageStmt->fetch();
                if (!$reportTarget || (int) $reportTarget['sender_id'] === $userId) {
                    redirect_member('You can report only a message from another participant in your conversation.', 'error', 'messages');
                }
                $threadKey = 'request-' . (int) $reportTarget['response_id'];
            }

            $existingReport = $pdo->prepare(
                'SELECT id FROM member_message_reports
                 WHERE reporter_id = :reporter_id AND message_type = :message_type AND message_id = :message_id
                 LIMIT 1'
            );
            $existingReport->execute([
                ':reporter_id' => $userId,
                ':message_type' => $messageType,
                ':message_id' => $messageId
            ]);
            if ($existingReport->fetchColumn()) {
                redirect_member('You have already reported that message.', 'info', 'messages', null, $threadKey);
            }
            $reportStmt = $pdo->prepare(
                'INSERT INTO member_message_reports (reporter_id, message_type, message_id, reason)
                 VALUES (:reporter_id, :message_type, :message_id, :reason)'
            );
            $reportStmt->execute([
                ':reporter_id' => $userId,
                ':message_type' => $messageType,
                ':message_id' => $messageId,
                ':reason' => $reason
            ]);
            redirect_member('The message has been reported for administrator review.', 'success', 'messages', null, $threadKey);

        case 'notification_open':
            $notificationId = filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$notificationId) {
                redirect_member('Choose a valid notification.', 'error', 'notifications');
            }
            $stmt = $pdo->prepare(
                'SELECT notification_type, target_id, actor_id FROM member_notifications
                 WHERE id = :id AND user_id = :user_id LIMIT 1'
            );
            $stmt->execute([':id' => $notificationId, ':user_id' => $userId]);
            $notification = $stmt->fetch();
            if (!$notification) {
                redirect_member('That notification is no longer available.', 'error', 'notifications');
            }
            $stmt = $pdo->prepare('UPDATE member_notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id');
            $stmt->execute([':id' => $notificationId, ':user_id' => $userId]);
            if ($notification['notification_type'] === 'request_message') {
                $threadStmt = $pdo->prepare(
                    'SELECT rr.id AS response_id
                     FROM request_responses rr
                     JOIN blood_requests br ON br.id = rr.request_id
                     WHERE br.id = :request_id
                       AND rr.status = \'accepted\'
                       AND (br.requester_id = :requester_id OR rr.user_id = :responder_id)
                       AND (br.requester_id = :actor_requester OR rr.user_id = :actor_responder)
                     LIMIT 1'
                );
                $threadStmt->execute([
                    ':request_id' => (int) $notification['target_id'],
                    ':requester_id' => $userId,
                    ':responder_id' => $userId,
                    ':actor_requester' => (int) $notification['actor_id'],
                    ':actor_responder' => (int) $notification['actor_id']
                ]);
                $thread = $threadStmt->fetch();
                if (!$thread) {
                    redirect_member('That conversation is no longer available.', 'error', 'notifications');
                }
                redirect_member('Conversation opened.', 'success', 'messages', null, 'request-' . (int) $thread['response_id']);
            }
            if ($notification['notification_type'] === 'request_response') {
                $requestStmt = $pdo->prepare(
                    'SELECT br.requester_id,
                            EXISTS (
                                SELECT 1 FROM request_responses rr
                                WHERE rr.request_id = br.id AND rr.user_id = :responder_id
                            ) AS is_responder
                     FROM blood_requests br
                     WHERE br.id = :request_id
                     LIMIT 1'
                );
                $requestStmt->execute([
                    ':responder_id' => $userId,
                    ':request_id' => (int) $notification['target_id']
                ]);
                $request = $requestStmt->fetch();
                if (!$request || ((int) $request['requester_id'] !== $userId && !$request['is_responder'])) {
                    redirect_member('That blood-request update is no longer available.', 'error', 'notifications');
                }
                redirect_member(
                    'Blood-request update opened.',
                    'success',
                    (int) $request['requester_id'] === $userId ? 'my-requests' : 'responded'
                );
            }
            if ($notification['notification_type'] === 'donation_offer_message') {
                $offerThreadStmt = $pdo->prepare(
                    "SELECT cp.id, cp.user_id
                     FROM community_posts cp
                     WHERE cp.id = :post_id AND cp.post_type = 'donation'
                       AND (cp.user_id = :owner_id OR EXISTS (
                           SELECT 1 FROM donation_offer_messages m
                           WHERE m.post_id = cp.id AND m.seeker_id = :seeker_id
                       ))
                     LIMIT 1"
                );
                $offerThreadStmt->execute([
                    ':post_id' => (int) $notification['target_id'],
                    ':owner_id' => $userId,
                    ':seeker_id' => $userId
                ]);
                $offerForNotification = $offerThreadStmt->fetch();
                if (!$offerForNotification) {
                    redirect_member('That donation conversation is no longer available.', 'error', 'notifications');
                }
                $seekerId = (int) $offerForNotification['user_id'] === $userId
                    ? (int) $notification['actor_id']
                    : $userId;
                $threadExistsStmt = $pdo->prepare(
                    'SELECT 1 FROM donation_offer_messages
                     WHERE post_id = :post_id AND seeker_id = :seeker_id
                     LIMIT 1'
                );
                $threadExistsStmt->execute([
                    ':post_id' => (int) $notification['target_id'],
                    ':seeker_id' => $seekerId
                ]);
                if (!$threadExistsStmt->fetchColumn()) {
                    redirect_member('That donation conversation is no longer available.', 'error', 'notifications');
                }
                redirect_member('Donation offer conversation opened.', 'success', 'messages', null, 'offer-' . (int) $notification['target_id'] . '-' . $seekerId);
            }
            if (in_array($notification['notification_type'], ['post_like', 'post_comment', 'post_share', 'post_save'], true)) {
                redirect_member('Post activity opened.', 'success', 'my-posts', (int) $notification['target_id']);
            }
            redirect_member('Notification opened.', 'success', $notification['notification_type'] === 'request_response' ? 'my-requests' : 'my-posts');

        case 'notifications_read_all':
            $stmt = $pdo->prepare('UPDATE member_notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0');
            $stmt->execute([':user_id' => $userId]);
            redirect_member('All notifications have been marked as read.', 'success', 'notifications');

        default:
            redirect_member('Choose a valid member action.', 'error', 'overview');
    }
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    redirect_member('We could not save that change. Please try again.', 'error', 'overview');
}
